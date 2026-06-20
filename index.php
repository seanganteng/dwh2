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
        /* Chart and map layout helpers */
        .chart-wrap { min-height: 260px; position: relative; box-sizing: border-box; padding-bottom: 1.2rem; }
        .chart-wrap canvas { width: 100% !important; height: 100% !important; display: block; }
        .card.h-100 .chart-wrap { min-height: 220px; }

        /* Small badge for map markers */
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
            <a href="prediktif.php" class="btn btn-warning text-dark">Analisis Prediktif DWH ????</a>
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
        <!-- 'Total Order' card removed per request -->
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
        <div class="col-12 col-lg-4 d-flex">
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
const dataQty = <?php echo json_encode($dataQty); ?>;

let topSellerChart = null;

function createChart(canvasId, config) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) {
        console.warn(`${canvasId} canvas not found`);
        return null;
    }
    try {
        return new Chart(canvas, config);
    } catch (err) {
        console.error(`${canvasId} chart error:`, err);
        return null;
    }
}

function initCharts() {
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

    createChart('top10Chart', {
        type: 'bar',
        data: {
            labels: top10Labels,
            datasets: [{
                label: 'Quantity Sold',
                data: top10Data,
                backgroundColor: 'rgba(255, 159, 64, 0.7)',
                borderColor: 'rgba(255, 159, 64, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
        }
    });

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
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: {
                    ticks: {
                        autoSkip: false,
                        maxRotation: 75,
                        minRotation: 45,
                        align: 'start'
                    }
                },
                y: {
                    beginAtZero: true,
                    ticks: { precision: 0 }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const dsLabel = context.dataset && context.dataset.label ? context.dataset.label : 'Rata-rata';
                            return `${dsLabel}: ${context.parsed.y.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} hari`;
                        }
                    }
                }
            }
        }
    });

    createChart('topCustomerRevenueChart', {
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
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return `Revenue: R$ ${context.parsed.y.toLocaleString('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0,
                        callback: function(value) {
                            return 'R$ ' + Number(value).toLocaleString('id-ID');
                        }
                    }
                }
            }
        }
    });

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
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: isRevenue ? 'Top 10 Seller berdasarkan Total Revenue' : 'Top 10 Seller berdasarkan Jumlah Terjual'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const value = context.parsed.y;
                            return isRevenue
                                ? `Revenue: R$ ${value.toLocaleString('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`
                                : `Jumlah Terjual: ${value.toLocaleString('id-ID')}`;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0,
                        callback: function(value) {
                            return isRevenue ? 'R$ ' + value.toLocaleString('id-ID') : value.toLocaleString('id-ID');
                        }
                    }
                }
            }
        }
    };
}

function renderTopSellerChart(metric = 'qty') {
    const normalizedMetric = metric === 'revenue' ? 'revenue' : 'qty';
    if (!topSellerChart) {
        topSellerChart = createChart('topSellerChart', getTopSellerChartConfig(normalizedMetric));
        return;
    }

    const isRevenue = normalizedMetric === 'revenue';
    topSellerChart.data.labels = isRevenue ? topSellerLabelsRevenue : topSellerLabelsQty;
    topSellerChart.data.datasets[0].label = isRevenue ? 'Total Revenue' : 'Jumlah Terjual';
    topSellerChart.data.datasets[0].data = isRevenue ? topSellerRevenue : topSellerQty;
    topSellerChart.options.plugins.title.text = isRevenue ? 'Top 10 Seller berdasarkan Total Revenue' : 'Top 10 Seller berdasarkan Jumlah Terjual';
    topSellerChart.options.scales.y.ticks.callback = function(value) {
        return isRevenue ? 'R$ ' + value.toLocaleString('id-ID') : value.toLocaleString('id-ID');
    };
    topSellerChart.update();
}

window.addEventListener('load', () => {
    initCharts();
    renderTopSellerChart('qty');
    const sellerMetricSelect = document.getElementById('sellerMetricSelect');
    if (sellerMetricSelect) {
        sellerMetricSelect.addEventListener('change', function() {
            renderTopSellerChart(this.value);
        });
    }
    try { map.invalidateSize(); } catch (e) {}
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
