<?php
require_once __DIR__ . '/inc/db.php';

$pdo = getPDO();
$selectedYear = isset($_GET['year']) && $_GET['year'] !== '' ? (int)$_GET['year'] : null;
$selectedMonth = isset($_GET['month']) && $_GET['month'] !== '' ? (int)$_GET['month'] : null;
$topByOptions = [
    'product' => 'Produk',
    'category' => 'Kategori',
];
$selectedTopBy = isset($_GET['top_by']) && array_key_exists($_GET['top_by'], $topByOptions) ? $_GET['top_by'] : 'product';
$months = [
    1 => 'Januari',
    2 => 'Februari',
    3 => 'Maret',
    4 => 'April',
    5 => 'Mei',
    6 => 'Juni',
    7 => 'Juli',
    8 => 'Agustus',
    9 => 'September',
    10 => 'Oktober',
    11 => 'November',
    12 => 'Desember',
];

// available years for filter
try {
    $yearsStmt = $pdo->query('SELECT DISTINCT tahun FROM dim_waktu ORDER BY tahun DESC');
    $years = $yearsStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $years = [];
}

// available months for selected year filter
try {
    if ($selectedYear) {
        $monthsStmt = $pdo->prepare('SELECT DISTINCT bulan FROM dim_waktu WHERE tahun = :y ORDER BY bulan');
        $monthsStmt->execute([':y' => $selectedYear]);
    } else {
        $monthsStmt = $pdo->query('SELECT DISTINCT bulan FROM dim_waktu ORDER BY bulan');
    }
    $availableMonths = $monthsStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $availableMonths = [];
}

// Fetch KPIs
$kpis = [];
try {
    $kpis['total_customers'] = (int) $pdo->query('SELECT COUNT(*) FROM dim_pelanggan')->fetchColumn();
} catch (Exception $e) { $kpis['total_customers'] = 0; }
try {
    $kpis['total_products'] = (int) $pdo->query('SELECT COUNT(*) FROM dim_produk')->fetchColumn();
} catch (Exception $e) { $kpis['total_products'] = 0; }
try {
    $kpis['total_categories'] = (int) $pdo->query("SELECT COUNT(DISTINCT kategori) FROM dim_produk")->fetchColumn();
} catch (Exception $e) { $kpis['total_categories'] = 0; }
try {
    $orderWhere = [];
    $orderParams = [];
    $orderQuery = "SELECT COALESCE(SUM(fp.jumlah), 0) FROM fakta_penjualan fp JOIN dim_waktu w ON fp.waktu_id = w.waktu_id";
    if ($selectedYear) {
        $orderWhere[] = 'w.tahun = :y';
        $orderParams[':y'] = $selectedYear;
    }
    if ($selectedMonth) {
        $orderWhere[] = 'w.bulan = :m';
        $orderParams[':m'] = $selectedMonth;
    }
    if ($orderWhere) {
        $orderQuery .= ' WHERE ' . implode(' AND ', $orderWhere);
    }
    $stmt = $pdo->prepare($orderQuery);
    $stmt->execute($orderParams);
    $kpis['total_orders'] = (int) $stmt->fetchColumn();
} catch (Exception $e) { $kpis['total_orders'] = 0; }
try {
    if ($selectedYear) {
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(fp.total_harga),0) FROM fakta_penjualan fp JOIN dim_waktu w ON fp.waktu_id = w.waktu_id WHERE w.tahun = :y");
        $stmt->execute([':y' => $selectedYear]);
        $kpis['total_revenue'] = (float) $stmt->fetchColumn();
    } else {
        $kpis['total_revenue'] = (float) $pdo->query('SELECT COALESCE(SUM(total_harga),0) FROM fakta_penjualan')->fetchColumn();
    }
} catch (Exception $e) { $kpis['total_revenue'] = 0; }
try {
    $avg = $pdo->query('SELECT AVG(durasi_pengiriman) FROM fakta_pengiriman')->fetchColumn();
    $kpis['avg_delivery_days'] = $avg !== null ? round((float)$avg,2) : null;
} catch (Exception $e) { $kpis['avg_delivery_days'] = null; }

// Payment method usage for chart
try {
    $paymentWhere = [];
    $paymentParams = [];
    if ($selectedYear) {
        $paymentWhere[] = 'w.tahun = :y';
        $paymentParams[':y'] = $selectedYear;
    }
    if ($selectedMonth) {
        $paymentWhere[] = 'w.bulan = :m';
        $paymentParams[':m'] = $selectedMonth;
    }
    $paymentQuery = 'SELECT mp.metode_pembayaran, COUNT(*) AS jumlah
        FROM fakta_pembayaran fp
        JOIN dim_metode_pembayaran mp ON fp.metode_key = mp.metode_key
        JOIN dim_waktu w ON fp.waktu_id = w.waktu_id';
    if ($paymentWhere) {
        $paymentQuery .= ' WHERE ' . implode(' AND ', $paymentWhere);
    }
    $paymentQuery .= ' GROUP BY mp.metode_pembayaran ORDER BY jumlah DESC';
    $stmt = $pdo->prepare($paymentQuery);
    $stmt->execute($paymentParams);
    $paymentMethods = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $paymentMethods = [];
}

$top10Items = [];
try {
    $top10Query = $selectedTopBy === 'category'
        ? "SELECT dp.kategori_inggris AS label, SUM(fp.jumlah) AS total_qty
            FROM fakta_penjualan fp
            JOIN dim_produk dp ON fp.produk_key = dp.produk_key
            JOIN dim_waktu w ON fp.waktu_id = w.waktu_id"
        : "SELECT dp.produk_id AS label, SUM(fp.jumlah) AS total_qty
            FROM fakta_penjualan fp
            JOIN dim_produk dp ON fp.produk_key = dp.produk_key
            JOIN dim_waktu w ON fp.waktu_id = w.waktu_id";
    if ($selectedYear) {
        $top10Query .= ' WHERE w.tahun = :y';
    }
    if ($selectedMonth) {
        $top10Query .= $selectedYear ? ' AND w.bulan = :m' : ' WHERE w.bulan = :m';
    }
    $top10Query .= $selectedTopBy === 'category'
        ? ' GROUP BY dp.kategori_inggris ORDER BY total_qty DESC LIMIT 10'
        : ' GROUP BY dp.produk_id ORDER BY total_qty DESC LIMIT 10';
    $top10Stmt = $pdo->prepare($top10Query);
    $top10Params = [];
    if ($selectedYear) {
        $top10Params[':y'] = $selectedYear;
    }
    if ($selectedMonth) {
        $top10Params[':m'] = $selectedMonth;
    }
    $top10Stmt->execute($top10Params);
    $top10Items = $top10Stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $top10Items = [];
}

// Top 5 states with most customers
$top5States = [];
try {
    $stateQuery = "SELECT state, COUNT(*) AS total_customers
        FROM dim_pelanggan
        WHERE state IS NOT NULL AND state != ''
        GROUP BY state
        ORDER BY total_customers DESC
        LIMIT 5";
    $stateStmt = $pdo->prepare($stateQuery);
    $stateStmt->execute();
    $top5States = $stateStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $top5States = [];
}

// Sales by month for chart
try {
    $where = [];
    $params = [];
    if ($selectedYear) {
        $where[] = 'w.tahun = :y';
        $params[':y'] = $selectedYear;
    }
    if ($selectedMonth) {
        $where[] = 'w.bulan = :m';
        $params[':m'] = $selectedMonth;
    }

    if ($selectedYear && $selectedMonth) {
        $sql = "SELECT w.tanggal AS label, SUM(fp.total_harga) AS revenue, SUM(fp.jumlah) AS qty
            FROM fakta_penjualan fp
            JOIN dim_waktu w ON fp.waktu_id = w.waktu_id";
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' GROUP BY w.tanggal ORDER BY w.tanggal';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($selectedYear) {
        $sql = "SELECT w.bulan, SUM(fp.total_harga) AS revenue, SUM(fp.jumlah) AS qty
            FROM fakta_penjualan fp
            JOIN dim_waktu w ON fp.waktu_id = w.waktu_id";
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' GROUP BY w.bulan ORDER BY w.bulan';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($selectedMonth) {
        $sql = "SELECT w.tahun, SUM(fp.total_harga) AS revenue, SUM(fp.jumlah) AS qty
            FROM fakta_penjualan fp
            JOIN dim_waktu w ON fp.waktu_id = w.waktu_id";
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' GROUP BY w.tahun ORDER BY w.tahun';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $pdo->query("SELECT w.tahun, w.bulan, SUM(fp.total_harga) AS revenue, SUM(fp.jumlah) AS qty
            FROM fakta_penjualan fp
            JOIN dim_waktu w ON fp.waktu_id = w.waktu_id
            GROUP BY w.tahun, w.bulan
            ORDER BY w.tahun, w.bulan");
        $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $sales = [];
}

// Prepare labels and data
$labels = [];
$dataRevenue = [];
$dataQty = [];
foreach ($sales as $row) {
    if ($selectedYear && $selectedMonth) {
        $labels[] = date('j M', strtotime($row['label']));
    } elseif ($selectedYear) {
        $labels[] = str_pad($row['bulan'],2,'0',STR_PAD_LEFT);
    } elseif ($selectedMonth) {
        $labels[] = $row['tahun'];
    } else {
        $labels[] = $row['tahun'] . '-' . str_pad($row['bulan'],2,'0',STR_PAD_LEFT);
    }
    $dataRevenue[] = (float) $row['revenue'];
    $dataQty[] = isset($row['qty']) ? (int) $row['qty'] : 0;
}
$paymentLabels = [];
$paymentData = [];
foreach ($paymentMethods as $row) {
    $paymentLabels[] = $row['metode_pembayaran'];
    $paymentData[] = (int) $row['jumlah'];
}
$top10Labels = [];
$top10Data = [];
foreach ($top10Items as $row) {
    $top10Labels[] = $row['label'];
    $top10Data[] = (int) $row['total_qty'];
}
$stateLabels = [];
$stateData = [];
foreach ($top5States as $row) {
    $stateLabels[] = $row['state'];
    $stateData[] = (int) $row['total_customers'];
}

$avgStateLabels = [];
$avgStateData = [];
try {
    $avgStateQuery = "SELECT dp.state, ROUND(AVG(fp.durasi_pengiriman), 2) AS avg_delivery_days
        FROM fakta_pengiriman fp
        JOIN dim_pelanggan dp ON fp.pelanggan_key = dp.pelanggan_key
        JOIN dim_waktu w ON fp.waktu_id = w.waktu_id";
    $avgStateWhere = [];
    $avgStateParams = [];
    if ($selectedYear) {
        $avgStateWhere[] = 'w.tahun = :y';
        $avgStateParams[':y'] = $selectedYear;
    }
    if ($selectedMonth) {
        $avgStateWhere[] = 'w.bulan = :m';
        $avgStateParams[':m'] = $selectedMonth;
    }
    if ($avgStateWhere) {
        $avgStateQuery .= ' WHERE ' . implode(' AND ', $avgStateWhere);
    }
    $avgStateQuery .= ' GROUP BY dp.state ORDER BY avg_delivery_days DESC';
    $avgStateStmt = $pdo->prepare($avgStateQuery);
    $avgStateStmt->execute($avgStateParams);
    $avgStateItems = $avgStateStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($avgStateItems as $row) {
        $avgStateLabels[] = $row['state'];
        $avgStateData[] = (float) $row['avg_delivery_days'];
    }
} catch (Exception $e) {
    $avgStateLabels = [];
    $avgStateData = [];
}

$avgCategoryLabels = [];
$avgCategoryData = [];
try {
    $avgCategoryQuery = "SELECT
        dp.kategori_inggris AS kategori,
        COUNT(*) AS total_pengiriman,
        ROUND(
            AVG(fp.durasi_pengiriman),
            2
        ) AS rata_rata_hari_pengiriman
    FROM fakta_pengiriman fp
    JOIN dim_produk dp
        ON fp.produk_key = dp.produk_key
    JOIN dim_waktu w ON fp.waktu_id = w.waktu_id";
    $avgCategoryWhere = [];
    $avgCategoryParams = [];
    if ($selectedYear) {
        $avgCategoryWhere[] = 'w.tahun = :y';
        $avgCategoryParams[':y'] = $selectedYear;
    }
    if ($selectedMonth) {
        $avgCategoryWhere[] = 'w.bulan = :m';
        $avgCategoryParams[':m'] = $selectedMonth;
    }
    if ($avgCategoryWhere) {
        $avgCategoryQuery .= ' WHERE ' . implode(' AND ', $avgCategoryWhere);
    }
    $avgCategoryQuery .= ' GROUP BY dp.kategori_inggris ORDER BY rata_rata_hari_pengiriman DESC';
    $avgCategoryStmt = $pdo->prepare($avgCategoryQuery);
    $avgCategoryStmt->execute($avgCategoryParams);
    $avgCategoryItems = $avgCategoryStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($avgCategoryItems as $row) {
        $avgCategoryLabels[] = $row['kategori'];
        $avgCategoryData[] = (float) $row['rata_rata_hari_pengiriman'];
    }
} catch (Exception $e) {
    $avgCategoryLabels = [];
    $avgCategoryData = [];
}
$ratingLabels = [];
$ratingData = [];
$ratingMonthLabels = [];
$ratingMonthData = [];
try {
    $ratingYearQuery = "SELECT dw.tahun AS label, ROUND(AVG(fr.skor_review), 2) AS avg_rating
        FROM fakta_review fr
        JOIN dim_waktu dw ON fr.waktu_id = dw.waktu_id";
    $ratingMonthQuery = "SELECT CONCAT(dw.tahun, '-', LPAD(dw.bulan::text, 2, '0')) AS label, ROUND(AVG(fr.skor_review), 2) AS avg_rating
        FROM fakta_review fr
        JOIN dim_waktu dw ON fr.waktu_id = dw.waktu_id";
    $ratingWhere = [];
    $ratingParams = [];
    if ($selectedYear) {
        $ratingWhere[] = 'dw.tahun = :y';
        $ratingParams[':y'] = $selectedYear;
    }
    if ($selectedMonth) {
        $ratingWhere[] = 'dw.bulan = :m';
        $ratingParams[':m'] = $selectedMonth;
    }
    if ($ratingWhere) {
        $whereClause = ' WHERE ' . implode(' AND ', $ratingWhere);
        $ratingYearQuery .= $whereClause;
        $ratingMonthQuery .= $whereClause;
    }
    $ratingYearQuery .= ' GROUP BY dw.tahun ORDER BY dw.tahun';
    $ratingMonthQuery .= " GROUP BY CONCAT(dw.tahun, '-', LPAD(dw.bulan::text, 2, '0')) ORDER BY CONCAT(dw.tahun, '-', LPAD(dw.bulan::text, 2, '0'))";
    $ratingStmt = $pdo->prepare($ratingYearQuery);
    $ratingStmt->execute($ratingParams);
    $ratingItems = $ratingStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($ratingItems as $row) {
        $ratingLabels[] = $row['label'];
        $ratingData[] = (float) $row['avg_rating'];
    }
    $ratingMonthStmt = $pdo->prepare($ratingMonthQuery);
    $ratingMonthStmt->execute($ratingParams);
    $ratingMonthItems = $ratingMonthStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($ratingMonthItems as $row) {
        $ratingMonthLabels[] = $row['label'];
        $ratingMonthData[] = (float) $row['avg_rating'];
    }
} catch (Exception $e) {
    $ratingLabels = [];
    $ratingData = [];
    $ratingMonthLabels = [];
    $ratingMonthData = [];
}

$topSellerLabelsQty = [];
$topSellerQty = [];
$topSellerLabelsRevenue = [];
$topSellerRevenue = [];
try {
    $sellerQuery = "SELECT ds.seller_id AS label, SUM(fp.jumlah) AS total_qty, SUM(fp.total_harga) AS total_revenue
        FROM fakta_penjualan fp
        JOIN dim_seller ds ON fp.seller_key = ds.seller_key
        JOIN dim_waktu w ON fp.waktu_id = w.waktu_id";
    $sellerWhere = [];
    $sellerParams = [];
    if ($selectedYear) {
        $sellerWhere[] = 'w.tahun = :y';
        $sellerParams[':y'] = $selectedYear;
    }
    if ($selectedMonth) {
        $sellerWhere[] = 'w.bulan = :m';
        $sellerParams[':m'] = $selectedMonth;
    }
    if ($sellerWhere) {
        $sellerQuery .= ' WHERE ' . implode(' AND ', $sellerWhere);
    }
    $sellerQuery .= ' GROUP BY ds.seller_id';
    $sellerStmt = $pdo->prepare($sellerQuery);
    $sellerStmt->execute($sellerParams);
    $topSellerItems = $sellerStmt->fetchAll(PDO::FETCH_ASSOC);

    $topSellerByQty = $topSellerItems;
    usort($topSellerByQty, fn($a, $b) => $b['total_qty'] <=> $a['total_qty']);
    $topSellerByQty = array_slice($topSellerByQty, 0, 10);
    foreach ($topSellerByQty as $row) {
        $topSellerLabelsQty[] = $row['label'];
        $topSellerQty[] = (int) $row['total_qty'];
    }

    $topSellerByRevenue = $topSellerItems;
    usort($topSellerByRevenue, fn($a, $b) => $b['total_revenue'] <=> $a['total_revenue']);
    $topSellerByRevenue = array_slice($topSellerByRevenue, 0, 10);
    foreach ($topSellerByRevenue as $row) {
        $topSellerLabelsRevenue[] = $row['label'];
        $topSellerRevenue[] = (float) $row['total_revenue'];
    }

    $topCustomerLabelsRevenue = [];
    $topCustomerRevenue = [];
    try {
        $customerQuery = "SELECT COALESCE(dp.pelanggan_id, CAST(fp.pelanggan_key AS varchar)) AS label, SUM(fp.total_harga) AS total_revenue
            FROM fakta_penjualan fp
            LEFT JOIN dim_pelanggan dp ON fp.pelanggan_key = dp.pelanggan_key
            JOIN dim_waktu w ON fp.waktu_id = w.waktu_id";
        $customerWhere = [];
        $customerParams = [];
        if ($selectedYear) {
            $customerWhere[] = 'w.tahun = :y';
            $customerParams[':y'] = $selectedYear;
        }
        if ($selectedMonth) {
            $customerWhere[] = 'w.bulan = :m';
            $customerParams[':m'] = $selectedMonth;
        }
        if ($customerWhere) {
            $customerQuery .= ' WHERE ' . implode(' AND ', $customerWhere);
        }
        $customerQuery .= ' GROUP BY COALESCE(dp.pelanggan_id, CAST(fp.pelanggan_key AS varchar)) ORDER BY total_revenue DESC LIMIT 10';
        $customerStmt = $pdo->prepare($customerQuery);
        $customerStmt->execute($customerParams);
        $topCustomerItems = $customerStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($topCustomerItems as $row) {
            $topCustomerLabelsRevenue[] = $row['label'];
            $topCustomerRevenue[] = (float) $row['total_revenue'];
        }
    } catch (Exception $e) {
        $topCustomerLabelsRevenue = [];
        $topCustomerRevenue = [];
    }
} catch (Exception $e) {
    $topSellerLabelsQty = [];
    $topSellerQty = [];
    $topSellerLabelsRevenue = [];
    $topSellerRevenue = [];
    $topCustomerLabelsRevenue = [];
    $topCustomerRevenue = [];
}

?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dasbor Data Warehouse</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/mobile.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>

        .chart-wrap { min-height: 260px; position: relative; box-sizing: border-box; padding-bottom: 1.2rem; }
        .chart-wrap canvas { width: 100% !important; min-height: 260px !important; height: auto !important; display: block; }
        .chart-wrap { max-height: none; }
        .card.h-100 .chart-wrap { min-height: 220px; }

        @media (max-width: 768px) {
            .chart-wrap { overflow-y: visible; }
            .chart-wrap canvas { min-height: 260px !important; height: auto !important; }
        }

        .marker-badge {
            display: inline-block;
            background: rgba(0,0,0,0.7);
            color: #fff;
            padding: 3px 8px;
            border-radius: 14px;
            font-size: 12px;
            line-height: 1;
            border: 0;
            box-shadow: 0 2px 6px rgba(0,0,0,0.25);
            white-space: nowrap;
        }
    </style>
</head>
<body class="p-4">
<div class="container">
    <h1 class="mb-4">📊Dashboard Data Warehouse📊</h1>

    <form method="get" class="row g-3 mb-4 align-items-end">
        <div class="col-6 col-md-3">
            <label for="year" class="form-label">Tahun</label>
            <select id="year" name="year" class="form-select">
                <option value="">Semua tahun</option>
                <?php foreach ($years as $y): ?>
                    <option value="<?php echo $y; ?>" <?php echo ($selectedYear && $selectedYear == $y) ? 'selected' : ''; ?>><?php echo $y; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3">
            <label for="top_by" class="form-label">Top 10 Berdasarkan</label>
            <select id="top_by" name="top_by" class="form-select">
                <?php foreach ($topByOptions as $value => $label): ?>
                    <option value="<?php echo $value; ?>" <?php echo ($selectedTopBy === $value) ? 'selected' : ''; ?>><?php echo $label; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3">
            <label for="month" class="form-label">Bulan</label>
            <select id="month" name="month" class="form-select">
                <option value="">Semua bulan</option>
                <?php foreach ($availableMonths as $num): ?>
                    <option value="<?php echo $num; ?>" <?php echo ($selectedMonth && $selectedMonth == $num) ? 'selected' : ''; ?>><?php echo $months[$num]; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto d-flex align-items-end gap-2">
            <button class="btn btn-primary">Terapkan Perubahan</button>
            <a href="index.php" class="btn btn-secondary">Reset Page</a>
            <a href="perbandingan.php" class="btn btn-info text-white">Perbandingan OLTP vs DWH</a>
            <a href="prediktif.php" class="btn btn-warning text-dark">Analisis Business Intelligent</a>
        </div>
    </form>

    <div class="row g-3 mb-4">

        <div class="col-6 col-md-3">
            <div class="card kpi">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2 text-muted">👥Total Pelanggan</h6>
                    <h3><?php echo number_format($kpis['total_customers']); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card kpi">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2 text-muted">📦Total Produk</h6>
                    <h3><?php echo number_format($kpis['total_products']); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card kpi">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2 text-muted">🎲Total Kategori</h6>
                    <h3><?php echo number_format($kpis['total_categories']); ?></h3>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="card kpi">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2 text-muted">💵Total Produk Terjual</h6>
                    <h3><?php echo number_format($kpis['total_orders']); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card kpi">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2 text-muted">💲Revenue</h6>
                    <h3>R$ <?php echo number_format($kpis['total_revenue'],2,',','.'); ?></h3>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4 align-items-stretch">
        <div class="col-12 col-lg-8">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <h5 class="card-title mb-1">💲Revenue Per Waktu</h5>
                            <p class="muted mb-1">Filter tahun untuk melihat revenue per bulan</p>
                        </div>
                    </div>
                    <div class="chart-wrap mb-3">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-4 d-flex mt-4 mt-lg-0">
            <div class="card h-100 w-100">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title">💳Metode Pembayaran Terpopuler</h5>
                    <div class="chart-wrap flex-fill">
                        <canvas id="paymentChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">🏅Top 10 <?php echo $selectedTopBy === 'category' ? 'Kategori' : 'Produk'; ?> Terlaris</h5>
                    <p class="text-muted">Menampilkan top 10 <?php echo strtolower($topByOptions[$selectedTopBy]); ?> berdasarkan jumlah terjual</p>
                    <div class="chart-wrap">
                        <canvas id="top10Chart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h5 class="card-title mb-1">🏅Top 10 Seller Terbaik</h5>
                            <p class="text-muted mb-0">Pilih tampilan jumlah barang terjual atau total revenue.</p>
                        </div>
                        <div style="width: 220px;">
                            <select id="sellerMetricSelect" class="form-select form-select-sm">
                                <option value="qty">Jumlah Barang Terjual</option>
                                <option value="revenue">Total Revenue</option>
                            </select>
                        </div>
                    </div>
                    <div class="chart-wrap">
                        <canvas id="topSellerChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">🏅Top 10 Pelanggan Berdasarkan Revenue Pembelian</h5>
                    <p class="text-muted">Menampilkan 10 pelanggan dengan total revenue pembelian terbesar.</p>
                    <div class="chart-wrap">
                        <canvas id="topCustomerRevenueChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-12 col-lg-8">
            <div class="card h-100">
                <div class="card-body">
                    <div class="mb-2">
                        <h5 id="mapTitle" class="mb-2">🗺️Peta Persebaran Pelanggan di Brazil</h5>
                        <div>
                            <button id="btnPelanggan" onclick="loadMapData('pelanggan')" class="btn btn-primary btn-sm">Pelanggan</button>
                            <button id="btnSeller" onclick="loadMapData('seller')" class="btn btn-secondary btn-sm">Seller</button>
                        </div>
                    </div>
                    <div id="map" style="width: 100%; height: 520px;"></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-4">
            <div class="card h-100">
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title">🏅Top 5 State dengan Pelanggan Terbanyak</h5>
                    <p class="text-muted">Menampilkan 5 state dengan jumlah pelanggan terbanyak</p>
                    <div class="chart-wrap flex-fill">
                        <canvas id="stateCustomersChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title mb-3">📊Rata-rata Waktu Pengiriman per State</h5>
                    <div class="chart-wrap" style="min-height: 340px;">
                        <canvas id="avgStateChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title mb-3">📊Rata-rata Waktu Pengiriman per Kategori</h5>
                    <div class="chart-wrap" style="min-height: 320px;">
                        <canvas id="avgCategoryChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-6">
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title mb-3">📊Rata-rata Rating Kepuasan Pelanggan per Tahun</h5>
                    <div class="chart-wrap" style="min-height: 280px;">
                        <canvas id="ratingChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-6">
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title mb-3">📊Rata-rata Rating Kepuasan Pelanggan per Bulan</h5>
                    <div class="chart-wrap" style="min-height: 280px;">
                        <canvas id="ratingMonthChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
const labels = <?php echo json_encode($labels); ?>;
const dataRevenue = <?php echo json_encode($dataRevenue); ?>;
const paymentLabels = <?php echo json_encode($paymentLabels); ?>;
const paymentData = <?php echo json_encode($paymentData); ?>;
const top10Labels = <?php echo json_encode($top10Labels); ?>;
const top10Data = <?php echo json_encode($top10Data); ?>;
const stateLabels = <?php echo json_encode($stateLabels); ?>;
const stateData = <?php echo json_encode($stateData); ?>;
const avgStateLabels = <?php echo json_encode($avgStateLabels); ?>;
const avgStateData = <?php echo json_encode($avgStateData); ?>;
const avgCategoryLabels = <?php echo json_encode($avgCategoryLabels); ?>;
const avgCategoryData = <?php echo json_encode($avgCategoryData); ?>;
const ratingLabels = <?php echo json_encode($ratingLabels); ?>;
const ratingData = <?php echo json_encode($ratingData); ?>;
const ratingMonthLabels = <?php echo json_encode($ratingMonthLabels); ?>;
const ratingMonthData = <?php echo json_encode($ratingMonthData); ?>;
const topSellerLabelsQty = <?php echo json_encode($topSellerLabelsQty); ?>;
const topSellerQty = <?php echo json_encode($topSellerQty); ?>;
const topSellerLabelsRevenue = <?php echo json_encode($topSellerLabelsRevenue); ?>;
const topSellerRevenue = <?php echo json_encode($topSellerRevenue); ?>;
const topCustomerLabelsRevenue = <?php echo json_encode($topCustomerLabelsRevenue); ?>;
const topCustomerRevenue = <?php echo json_encode($topCustomerRevenue); ?>;
// Ensure revenue arrays are numeric (coerce any strings to numbers)
console.log('topCustomerLabelsRevenue (from PHP):', topCustomerLabelsRevenue);
console.log('topCustomerRevenue (raw from PHP):', topCustomerRevenue);
for (let i = 0; i < topCustomerRevenue.length; i++) {
    topCustomerRevenue[i] = Number(topCustomerRevenue[i]) || 0;
}
console.log('topCustomerRevenue (coerced numeric):', topCustomerRevenue);
const dataQty = <?php echo json_encode($dataQty); ?>;

// Helper: split long labels into multiple lines
function wrapLabel(text, maxLen) {
    if (!text) return text;
    const s = String(text);
    const words = s.split(/[_\s\-]+/);
    const lines = [];
    let line = '';
    for (const w of words) {
        if (!line) {
            if (w.length <= maxLen) { line = w; }
            else { for (let i = 0; i < w.length; i += maxLen) lines.push(w.slice(i, i + maxLen)); }
        } else {
            if ((line + ' ' + w).length <= maxLen) line = line + ' ' + w;
            else { lines.push(line); if (w.length <= maxLen) line = w; else { for (let i = 0; i < w.length; i += maxLen) lines.push(w.slice(i, i + maxLen)); line = ''; } }
        }
    }
    if (line) lines.push(line);
    return lines.length > 1 ? lines : lines[0];
}

// Helper: detect mobile viewport
function isMobileViewport() {
    return window.matchMedia('(max-width: 768px)').matches;
}

// Helper: set canvas height proportional to number of labels on mobile
function setCanvasHeightForLabels(canvasId, labelsArray, perItem = 26, minH = 260) {
    try {
        const canvas = document.getElementById(canvasId);
        if (!canvas) return;
        const isMobileNow = isMobileViewport();
        if (!isMobileNow) { canvas.style.height = ''; canvas.style.minHeight = ''; return; }
        const count = Array.isArray(labelsArray) ? labelsArray.length : 0;
        const h = Math.max(minH, count * perItem);
        canvas.style.height = h + 'px';
        canvas.style.minHeight = h + 'px';
    } catch (e) { console.warn('setCanvasHeightForLabels error', e); }
}

function shouldUseMobileList(labels, values, maxItems = 8, maxLabelLen = 24) {
    if (!isMobileViewport()) return false;
    if (!Array.isArray(labels)) return false;
    if (labels.length > maxItems) return true;
    return labels.some(lbl => typeof lbl === 'string' && lbl.length > maxLabelLen);
}

function resetMobileList(canvasId) {
    try {
        const canvas = document.getElementById(canvasId);
        if (!canvas) return;
        const wrap = canvas.closest('.chart-wrap');
        if (!wrap) return;
        const fallback = wrap.querySelector('.mobile-list-container');
        if (fallback) fallback.remove();
        canvas.style.display = '';
    } catch (e) { console.warn('resetMobileList error', e); }
}

// Render a simple vertical list with small bar visuals for mobile (fallback for very long category lists)
function renderListChart(canvasId, labels, values, seriesLabel) {
    try {
        const canvas = document.getElementById(canvasId);
        if (!canvas) return false;
        const wrap = canvas.closest('.chart-wrap');
        if (!wrap) return false;
        let fallback = wrap.querySelector('.mobile-list-container');
        if (!fallback) {
            fallback = document.createElement('div');
            fallback.className = 'mobile-list-container';
            wrap.insertBefore(fallback, canvas);
        }
        const nums = Array.isArray(values) ? values.map(v => Number(v) || 0) : [];
        const max = nums.length ? Math.max(...nums) : 1;
        const title = document.createElement('div');
        title.className = 'mobile-list-title';
        title.innerText = seriesLabel || '';
        fallback.innerHTML = '';
        fallback.appendChild(title);
        for (let i = 0; i < labels.length; i++) {
            const lbl = labels[i] || '';
            const val = nums[i] || 0;
            const pct = Math.round((val / (max || 1)) * 100);
            const item = document.createElement('div');
            item.className = 'mobile-list-item';
            item.innerHTML = `<div class="mli-label">${lbl}</div><div class="mli-bar"><div class="mli-fill" style="width:${pct}%"></div></div><div class="mli-val">${val.toLocaleString('id-ID')}</div>`;
            fallback.appendChild(item);
        }
        canvas.style.display = 'none';
        return true;
    } catch (e) { console.warn('renderListChart error', e); return false; }
}

function createOrRenderChart(canvasId, config, labels, values, seriesLabel) {
    resetMobileList(canvasId);
    if (shouldUseMobileList(labels, values)) {
        return renderListChart(canvasId, labels, values, seriesLabel);
    }
    return createChart(canvasId, config);
}

let topSellerChart = null;

function createChart(canvasId, config) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) {
        console.warn(`${canvasId} canvas not found`);
        return null;
    }
    try {
        const chart = new Chart(canvas, config);
        // ensure Chart resizes to CSS-set dimensions (especially after we set canvas.style.height)
        try { setTimeout(() => { if (chart && typeof chart.resize === 'function') chart.resize(); }, 50); } catch(e){}
        return chart;
    } catch (err) {
        console.error(`${canvasId} chart error:`, err);
        return null;
    }
}

function initCharts() {
    const isMobile = window.matchMedia('(max-width: 768px)').matches;
    createChart('revenueChart', {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    type: 'line',
                    label: 'Revenue',
                    data: dataRevenue,
                    borderColor: 'rgb(92, 192, 255)',
                    backgroundColor: 'rgba(75,192,192,0.2)',
                    tension: 0.2,
                    fill: true,
                    yAxisID: 'yRevenue'
                },
                {
                    type: 'bar',
                    label: 'Jumlah Produk Terjual',
                    data: dataQty,
                    backgroundColor: 'rgba(255, 159, 64, 0.7)',
                    borderColor: 'rgba(255, 159, 64, 1)',
                    borderWidth: 1,
                    yAxisID: 'yQty'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    callbacks: {
                        title: function(items) {
                            if (!items || !items.length) return '';
                            return items[0].label;
                        },
                        label: function(context) {
                            const label = context.dataset.label || '';
                            const value = context.parsed && context.parsed.y !== undefined ? context.parsed.y : context.raw;
                            if (label === 'Revenue') {
                                return label + ': R$ ' + Number(value).toLocaleString('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                            }
                            return label + ': ' + Number(value).toLocaleString('id-ID');
                        }
                    }
                }
            },
            scales: {
                x: {
                    ticks: {
                        autoSkip: true,
                        maxRotation: isMobile ? 45 : 0,
                        minRotation: isMobile ? 20 : 0,
                        maxTicksLimit: isMobile ? 6 : 12
                    }
                },
                yRevenue: {
                    type: 'linear',
                    position: 'left',
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) { return 'R$ ' + Number(value).toLocaleString('id-ID'); }
                    }
                },
                yQty: {
                    type: 'linear',
                    position: 'right',
                    beginAtZero: true,
                    ticks: { precision: 0 }
                }
            }
        }
    });

    createChart('paymentChart', {
        type: 'bar',
        data: {
            labels: paymentLabels,
            datasets: [{
                label: 'Transaction Count',
                data: paymentData,
                backgroundColor: 'rgba(54, 162, 235, 0.7)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
        }
    });

    // on mobile, make the top10 chart tall enough to list items vertically
    setCanvasHeightForLabels('top10Chart', top10Labels, 36, 380);
    createOrRenderChart('top10Chart', {
        type: 'bar',
        data: {
            labels: top10Labels,
            datasets: [{
                label: 'Quantity Sold',
                data: top10Data,
                backgroundColor: 'rgba(255, 159, 64, 0.8)',
                borderColor: 'rgba(255, 159, 64, 1)',
                borderWidth: 1,
                borderRadius: 6,
                maxBarThickness: 30
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    labels: { boxWidth: 12, boxHeight: 12, padding: 12 }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const value = context.parsed.x;
                            return `Quantity Sold: ${Number(value).toLocaleString('id-ID')}`;
                        }
                    }
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0,
                        autoSkip: true,
                        maxTicksLimit: isMobile ? 6 : 12,
                        callback: function(value) { return value.toLocaleString('id-ID'); }
                    }
                },
                y: {
                    ticks: {
                        autoSkip: false,
                        maxRotation: 0,
                        minRotation: 0,
                        padding: 8,
                        callback: function(value) {
                            const label = (typeof this.getLabelForValue === 'function') ? this.getLabelForValue(value) : value;
                            if (typeof label !== 'string') return label;
                            return wrapLabel(label, 18);
                        }
                    }
                }
            }
        }
    }, top10Labels, top10Data, 'Quantity Sold');

    createChart('stateCustomersChart', {
        type: 'bar',
        data: {
            labels: stateLabels,
            datasets: [{
                label: 'Jumlah Pelanggan',
                data: stateData,
                backgroundColor: 'rgba(75, 192, 192, 0.7)',
                borderColor: 'rgba(75, 192, 192, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
        }
    });

    createChart('avgStateChart', {
        type: 'bar',
        data: {
            labels: avgStateLabels,
            datasets: [{
                label: 'Rata-rata Pengiriman (hari)',
                data: avgStateData,
                backgroundColor: 'rgba(54, 162, 235, 0.8)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: {
                    beginAtZero: true,
                    ticks: { precision: 0 }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const value = context.parsed.x;
                            const dsLabel = context.dataset && context.dataset.label ? context.dataset.label : 'Rata-rata';
                            return `${dsLabel}: ${value.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} hari`;
                        }
                    }
                }
            }
        }
    });

    // on mobile, render avgCategory as vertical list for long label sets; otherwise render chart
    if (isMobile) {
        if (!renderListChart('avgCategoryChart', avgCategoryLabels, avgCategoryData, 'Rata-rata Pengiriman (hari)')) {
            setCanvasHeightForLabels('avgCategoryChart', avgCategoryLabels, 36, 420);
            createChart('avgCategoryChart', {
                type: 'bar',
                data: {
                    labels: avgCategoryLabels,
                    datasets: [{
                        label: 'Rata-rata Pengiriman (hari)',
                        data: avgCategoryData,
                        backgroundColor: 'rgba(255, 159, 64, 0.8)',
                        borderColor: 'rgba(255, 159, 64, 1)',
                        borderWidth: 1
                    }]
                },
                options: (function(){
                    const mobile = isMobile;
                    return {
                        indexAxis: mobile ? 'y' : 'x',
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: mobile ? {
                            x: { beginAtZero: true, ticks: { precision: 0 } },
                            y: { ticks: { autoSkip: false, callback: function(v){ const label = (typeof this.getLabelForValue === 'function') ? this.getLabelForValue(v) : v; return typeof label === 'string' ? wrapLabel(label,18) : label; } } }
                        } : {
                            x: { ticks: { autoSkip: true, maxRotation: isMobile ? 45 : 75, minRotation: isMobile ? 20 : 45, maxTicksLimit: isMobile ? 6 : 12, align: 'start', callback: function(v){ const label = (typeof this.getLabelForValue === 'function') ? this.getLabelForValue(v) : v; return typeof label === 'string' ? wrapLabel(label,12) : label; } } },
                            y: { beginAtZero: true, ticks: { precision: 0 } }
                        },
                        plugins: {
                            tooltip: { callbacks: { label: function(context){ const dsLabel = context.dataset && context.dataset.label ? context.dataset.label : 'Rata-rata'; const val = (context.parsed && (typeof context.parsed.x !== 'undefined' ? context.parsed.x : context.parsed.y)) || context.raw || 0; return `${dsLabel}: ${Number(val).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} hari`; } } }
                        }
                    };
                })()
            });
        }
    } else {
        setCanvasHeightForLabels('avgCategoryChart', avgCategoryLabels, 36, 420);
        createChart('avgCategoryChart', {
            type: 'bar',
            data: {
                labels: avgCategoryLabels,
                datasets: [{
                    label: 'Rata-rata Pengiriman (hari)',
                    data: avgCategoryData,
                    backgroundColor: 'rgba(255, 159, 64, 0.8)',
                    borderColor: 'rgba(255, 159, 64, 1)',
                    borderWidth: 1
                }]
            },
            options: (function(){
                const mobile = isMobile;
                return {
                    indexAxis: mobile ? 'y' : 'x',
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: mobile ? {
                        x: { beginAtZero: true, ticks: { precision: 0 } },
                        y: { ticks: { autoSkip: false, callback: function(v){ const label = (typeof this.getLabelForValue === 'function') ? this.getLabelForValue(v) : v; return typeof label === 'string' ? wrapLabel(label,18) : label; } } }
                    } : {
                        x: { ticks: { autoSkip: true, maxRotation: isMobile ? 45 : 75, minRotation: isMobile ? 20 : 45, maxTicksLimit: isMobile ? 6 : 12, align: 'start', callback: function(v){ const label = (typeof this.getLabelForValue === 'function') ? this.getLabelForValue(v) : v; return typeof label === 'string' ? wrapLabel(label,12) : label; } } },
                        y: { beginAtZero: true, ticks: { precision: 0 } }
                    },
                    plugins: {
                        tooltip: { callbacks: { label: function(context){ const dsLabel = context.dataset && context.dataset.label ? context.dataset.label : 'Rata-rata'; const val = (context.parsed && (typeof context.parsed.x !== 'undefined' ? context.parsed.x : context.parsed.y)) || context.raw || 0; return `${dsLabel}: ${Number(val).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} hari`; } } }
                    }
                };
            })()
        });
    }

    // ensure canvas has enough height on mobile so bars are visible
    const tcCanvas = document.getElementById('topCustomerRevenueChart');
    if (isMobile && tcCanvas) {
        tcCanvas.style.height = Math.max(220, topCustomerLabelsRevenue.length * 30) + 'px';
    } else if (tcCanvas) {
        tcCanvas.style.height = '';
    }

    // on mobile, make top customer revenue chart tall enough
    setCanvasHeightForLabels('topCustomerRevenueChart', topCustomerLabelsRevenue, 36, 420);
    createOrRenderChart('topCustomerRevenueChart', {
        type: 'bar',
        data: {
            labels: topCustomerLabelsRevenue,
            datasets: [{
                label: 'Revenue Pembelian',
                data: topCustomerRevenue,
                backgroundColor: 'rgba(255, 99, 132, 0.7)',
                borderColor: 'rgba(255, 99, 132, 1)',
                borderWidth: 1
            }]
        },
        options: (function(){
            const isMobile = window.matchMedia('(max-width: 768px)').matches;
            return {
                indexAxis: isMobile ? 'y' : 'x',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const idx = context.dataIndex;
                                const raw = (context.dataset && Array.isArray(context.dataset.data)) ? context.dataset.data[idx] : (context.parsed && (typeof context.parsed.x !== 'undefined' ? context.parsed.x : context.parsed.y));
                                const num = Number(raw) || 0;
                                return `Revenue: R$ ${num.toLocaleString('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
                            }
                        }
                    }
                },
                scales: isMobile ? {
                    x: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value){ return 'R$ ' + Number(value).toLocaleString('id-ID'); }
                        }
                    },
                    y: {
                        ticks: {
                            autoSkip: false,
                            maxTicksLimit: 10,
                            callback: function(value){
                                const label = (typeof this.getLabelForValue === 'function') ? this.getLabelForValue(value) : value;
                                if (typeof label !== 'string') return label;
                                return wrapLabel(label,18);
                            }
                        }
                    }
                } : {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0,
                            callback: function(value) { return 'R$ ' + Number(value).toLocaleString('id-ID'); }
                        }
                    }
                }
            };
        })()
    }, topCustomerLabelsRevenue, topCustomerRevenue, 'Revenue Pembelian');

    // Rating charts
    createChart('ratingChart', {
        type: 'bar',
        data: {
            labels: ratingLabels,
            datasets: [{
                label: 'Rata-rata Rating (Skor)',
                data: ratingData,
                backgroundColor: 'rgba(54, 162, 235, 0.8)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    max: 5,
                    ticks: { stepSize: 0.5 }
                }
            }
        }
    });

    createChart('ratingMonthChart', {
        type: 'bar',
        data: {
            labels: ratingMonthLabels,
            datasets: [{
                label: 'Rata-rata Rating (Skor)',
                data: ratingMonthData,
                backgroundColor: 'rgba(255, 159, 64, 0.8)',
                borderColor: 'rgba(255, 159, 64, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    max: 5,
                    ticks: { stepSize: 0.5 }
                }
            }
        }
    });
}

function getTopSellerChartConfig(metric) {
    const isRevenue = metric === 'revenue';
    const isMobile = window.matchMedia('(max-width: 768px)').matches;
    const indexAxis = isMobile ? 'y' : 'x';

    // For mobile we prefer horizontal bars (indexAxis: 'y') so long seller IDs
    // render as readable vertical list. Swap scales accordingly.
    const scales = isMobile ? {
        x: {
            beginAtZero: true,
            ticks: {
                precision: 0,
                callback: function(value) { return isRevenue ? 'R$ ' + value.toLocaleString('id-ID') : value.toLocaleString('id-ID'); }
            }
        },
        y: {
            ticks: {
                autoSkip: false,
                maxTicksLimit: 10,
                callback: function(value) {
                    const label = (typeof this.getLabelForValue === 'function') ? this.getLabelForValue(value) : value;
                    if (typeof label !== 'string') return label;
                    const max = 18;
                    return label.length > max ? label.slice(0, max - 1) + '…' : label;
                }
            }
        }
    } : {
        y: {
            beginAtZero: true,
            ticks: {
                precision: 0,
                callback: function(value) { return isRevenue ? 'R$ ' + value.toLocaleString('id-ID') : value.toLocaleString('id-ID'); }
            }
        }
    };

    return {
        type: 'bar',
        data: {
            labels: isRevenue ? topSellerLabelsRevenue : topSellerLabelsQty,
            datasets: [{
                label: isRevenue ? 'Total Revenue' : 'Jumlah Terjual',
                data: isRevenue ? topSellerRevenue : topSellerQty,
                backgroundColor: isRevenue ? 'rgba(99, 132, 255, 0.7)' : 'rgba(153, 102, 255, 0.7)',
                borderColor: isRevenue ? 'rgba(99, 132, 255, 1)' : 'rgba(153, 102, 255, 1)',
                borderWidth: 1
            }]
        },
        options: {
            indexAxis: indexAxis,
            responsive: true,
            maintainAspectRatio: false,
            layout: {
                padding: {
                    left: isMobile ? 6 : 16,
                    right: 6,
                    top: 6,
                    bottom: 6
                }
            },
            plugins: {
                legend: { display: isMobile ? false : true },
                title: {
                    display: true,
                    text: isRevenue ? 'Top 10 Seller berdasarkan Total Revenue' : 'Top 10 Seller berdasarkan Jumlah Terjual',
                    padding: { top: 4, bottom: 8 }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const idx = context.dataIndex;
                            const raw = (context.dataset && Array.isArray(context.dataset.data)) ? context.dataset.data[idx] : (context.parsed && (context.parsed.x !== undefined ? context.parsed.x : context.parsed.y));
                            const num = Number(raw) || 0;
                            return isRevenue
                                ? `Revenue: R$ ${num.toLocaleString('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`
                                : `Jumlah Terjual: ${num.toLocaleString('id-ID')}`;
                        }
                    }
                }
            },
            scales: scales
        }
    };
}

function renderTopSellerChart(metric = 'qty') {
    const normalizedMetric = metric === 'revenue' ? 'revenue' : 'qty';
    if (!topSellerChart) {
        // ensure canvas has enough height to display all labels on mobile
        const canvas = document.getElementById('topSellerChart');
        const labelsCount = (normalizedMetric === 'revenue') ? topSellerLabelsRevenue.length : topSellerLabelsQty.length;
        if (window.matchMedia('(max-width: 768px)').matches) {
            // allocate ~32px per bar (including gaps), with a sensible minimum
            canvas.style.height = Math.max(240, labelsCount * 32) + 'px';
        } else {
            canvas.style.height = '';
        }

        // debug: print labels & values to console to verify correct data
        console.log('Creating topSellerChart', {
            metric: normalizedMetric,
            labels: (normalizedMetric === 'revenue') ? topSellerLabelsRevenue : topSellerLabelsQty,
            values: (normalizedMetric === 'revenue') ? topSellerRevenue : topSellerQty
        });
        topSellerChart = createChart('topSellerChart', getTopSellerChartConfig(normalizedMetric));
        return;
    }

    const isRevenue = normalizedMetric === 'revenue';
    // update data
    const labelsForMetric = isRevenue ? topSellerLabelsRevenue : topSellerLabelsQty;
    let valuesForMetric = isRevenue ? topSellerRevenue : topSellerQty;
    // ensure numeric values
    valuesForMetric = valuesForMetric.map(v => Number(v) || 0);
    console.log('Updating topSellerChart', { metric: normalizedMetric, labels: labelsForMetric, values: valuesForMetric });
    topSellerChart.data.labels = labelsForMetric;
    topSellerChart.data.datasets[0].label = isRevenue ? 'Total Revenue' : 'Jumlah Terjual';
    topSellerChart.data.datasets[0].data = valuesForMetric;

    // ensure canvas height adapts when metric changes (so all labels are visible on mobile)
    const canvas = document.getElementById('topSellerChart');
    const labelsCount = (isRevenue) ? topSellerLabelsRevenue.length : topSellerLabelsQty.length;
    if (window.matchMedia('(max-width: 768px)').matches) {
        canvas.style.height = Math.max(240, labelsCount * 32) + 'px';
    } else {
        canvas.style.height = '';
    }

    // update responsive options according to current viewport
    const isMobile = window.matchMedia('(max-width: 768px)').matches;
    const indexAxis = isMobile ? 'y' : 'x';
    topSellerChart.options.indexAxis = indexAxis;
    topSellerChart.options.plugins = topSellerChart.options.plugins || {};
    topSellerChart.options.plugins.legend = { display: isMobile ? false : true };
    topSellerChart.options.layout = topSellerChart.options.layout || {};
    topSellerChart.options.layout.padding = { left: isMobile ? 6 : 16, right: 6, top: 6, bottom: 6 };

    // rebuild scales for updated axis orientation
    if (isMobile) {
        topSellerChart.options.scales = {
            x: { beginAtZero: true, ticks: { precision: 0, callback: function(v){ return isRevenue ? 'R$ ' + Number(v).toLocaleString('id-ID') : Number(v).toLocaleString('id-ID'); } } },
            y: { ticks: { autoSkip: false, maxTicksLimit: 10, callback: function(value){ const label = (typeof this.getLabelForValue === 'function') ? this.getLabelForValue(value) : value; if (typeof label !== 'string') return label; const max = 18; return label.length > max ? label.slice(0, max - 1) + '…' : label; } } }
        };
    } else {
        topSellerChart.options.scales = { y: { beginAtZero: true, ticks: { precision: 0, callback: function(v){ return isRevenue ? 'R$ ' + Number(v).toLocaleString('id-ID') : Number(v).toLocaleString('id-ID'); } } } };
    }

    topSellerChart.options.plugins.title = topSellerChart.options.plugins.title || {};
    topSellerChart.options.plugins.title.text = isRevenue ? 'Top 10 Seller berdasarkan Total Revenue' : 'Top 10 Seller berdasarkan Jumlah Terjual';

    topSellerChart.update();
}

window.addEventListener('load', () => {
    initCharts();
    renderTopSellerChart('qty');
    const sellerMetricSelect = document.getElementById('sellerMetricSelect');
    if (sellerMetricSelect) {
        sellerMetricSelect.addEventListener('change', function() {
            // rebuild top seller chart according to selected metric; also keep other charts in sync
            rebuildCharts(this.value);
        });
    }
    try { map.invalidateSize(); } catch (e) {}
});

// Destroy and recreate charts to ensure config (indexAxis, scales) follow current filters/viewport
function rebuildCharts(preferredSellerMetric) {
    const canvasIds = ['revenueChart','paymentChart','top10Chart','stateCustomersChart','avgStateChart','avgCategoryChart','topCustomerRevenueChart','ratingChart','ratingMonthChart','topSellerChart'];
    canvasIds.forEach(id => {
        const c = document.getElementById(id);
        if (!c) return;
        try {
            // Prefer Chart.getChart (Chart.js v3+). If unavailable, fallback to Chart.instances (v2)
            if (typeof Chart.getChart === 'function') {
                const existing = Chart.getChart(c);
                if (existing) existing.destroy();
            } else if (Chart.instances) {
                for (const k in Chart.instances) {
                    const inst = Chart.instances[k];
                    if (inst && inst.canvas && inst.canvas.id === id) {
                        try { inst.destroy(); } catch (e) {}
                    }
                }
            }
        } catch (e) {
            console.warn('Error destroying chart', id, e);
        }
    });

    // reset module-level references
    try { topSellerChart = null; } catch (e) {}

    console.log('Rebuilding charts — initCharts start');
    try {
        initCharts();
        const metric = preferredSellerMetric || (document.getElementById('sellerMetricSelect') ? document.getElementById('sellerMetricSelect').value : 'qty');
        renderTopSellerChart(metric);
        console.log('Rebuilding charts — initCharts done');
    } catch (err) {
        console.error('Error recreating charts', err);
    }
}

// Attach listeners to form filters so charts rebuild client-side when user changes filters (if filters are dynamic)
['year','top_by','month'].forEach(fid => {
    const el = document.getElementById(fid);
    if (el) {
        el.addEventListener('change', function(){
            // if form submission is used, page will reload; otherwise rebuild charts
            setTimeout(() => rebuildCharts(), 50);
        });
    }
});


// Leaflet map loading logic
const map = L.map('map').setView([-14.2350, -51.9253], 4);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors'
}).addTo(map);

let markersLayer = L.layerGroup().addTo(map);

function loadMapData(tipe = 'pelanggan') {
    fetch(`distribusi_state.php?tipe=${tipe}`)
        .then(res => res.json())
        .then(json => {
            if (json.error) {
                console.error('Error:', json.error);
                return;
            }
            renderMarkers(json.data, json.max);
            try { map.invalidateSize(); } catch (e) {}
        })
        .catch(err => console.error('Fetch error:', err));
}

function renderMarkers(data, maxVal) {
    markersLayer.clearLayers();

    // Build counts and compute min/max
    const counts = data.map(d => (typeof d.jumlah === 'number' ? d.jumlah : 0));
    const minCount = counts.length ? Math.min(...counts) : 0;
    const maxCount = maxVal || (counts.length ? Math.max(...counts) : 1);

    // Radius range in meters (tune these to your zoom level)
    const minRadius = 15000; // increased base size
    const maxRadius = 200000; // increased max size for very large counts

    data.forEach(item => {
        if (item.lat === null || item.lng === null) return;

        const count = item.jumlah || 0;

        let radius;
        // normalize then sqrt-scale so area better reflects count
        const norm = (maxCount === minCount) ? 0.5 : Math.max(0, Math.min(1, (count - minCount) / (maxCount - minCount)));
        const scaled = Math.sqrt(norm);
        radius = minRadius + scaled * (maxRadius - minRadius);

        function getColorByRatio(r) {
            if (r >= 0.66) return '#e74c3c'; // banyak - merah
            if (r >= 0.33) return '#f1c40f'; // sedang - kuning
            return '#2ecc71'; // sedikit - hijau
        }

        const ratio = count / (maxCount || 1);
        const color = getColorByRatio(ratio);

        const circle = L.circle([item.lat, item.lng], {
            color: color,
            fillColor: color,
            fillOpacity: 0.7,
            radius: radius
        });

        // Add a small solid center marker so the circle doesn't look empty
        // pixelRadius scaled from 6..18 depending on scaled value
        const pixelRadius = Math.round(6 + scaled * 12);
        const inner = L.circleMarker([item.lat, item.lng], {
            radius: pixelRadius,
            color: '#ffffff',
            weight: 2,
            fillColor: color,
            fillOpacity: 1
        });

        circle.bindPopup(`<b>${item.nama} (${item.state})</b><br>Jumlah: <strong>${item.jumlah.toLocaleString('id-ID')}</strong>`);
        circle.bindTooltip(`${item.nama}: ${item.jumlah.toLocaleString('id-ID')}`, { permanent: false, direction: 'top' });

        markersLayer.addLayer(circle);
        markersLayer.addLayer(inner);

        // Add a small badge showing the count so markers feel less empty
        const badgeHtml = `<div class="marker-badge">${item.jumlah.toLocaleString('id-ID')}</div>`;
        const badgeIcon = L.divIcon({
            html: badgeHtml,
            className: '',
            iconSize: null,
            iconAnchor: [0, -(pixelRadius + 12)]
        });
        const badge = L.marker([item.lat, item.lng], { icon: badgeIcon, interactive: false });
        markersLayer.addLayer(badge);
    });
}

loadMapData('pelanggan');

</script>

</body>
</html>
