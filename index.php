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
$selectedCategory = isset($_GET['category']) && $_GET['category'] !== '' ? $_GET['category'] : null;
$selectedState = isset($_GET['state']) && $_GET['state'] !== '' ? $_GET['state'] : null;
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

// available categories for filter (dim_produk)
try {
    $catStmt = $pdo->query("SELECT DISTINCT kategori_inggris FROM dim_produk WHERE kategori_inggris IS NOT NULL AND kategori_inggris <> '' ORDER BY kategori_inggris");
    $categories = $catStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $categories = [];
}

// available states for filter (dim_pelanggan)
try {
    $stateFilterStmt = $pdo->query("SELECT DISTINCT state FROM dim_pelanggan WHERE state IS NOT NULL AND state <> '' ORDER BY state");
    $stateOptions = $stateFilterStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $stateOptions = [];
}

// ============================================================
// KPI - SEMUA MENGIKUTI FILTER
// ============================================================

// 1. TOTAL PELANGGAN - HYBRID
try {
    $hasTimeFilter = ($selectedYear !== null || $selectedMonth !== null);
    
    if (!$hasTimeFilter) {
        $custWhere = [];
        $custParams = [];
        $custQuery = "SELECT COUNT(*) FROM dim_pelanggan dc";
        
        if ($selectedState) {
            $custWhere[] = 'dc.state = :state';
            $custParams[':state'] = $selectedState;
        }
        
        if (!empty($custWhere)) {
            $custQuery .= ' WHERE ' . implode(' AND ', $custWhere);
        }
        
        $custStmt = $pdo->prepare($custQuery);
        $custStmt->execute($custParams);
        $kpis['total_customers'] = (int) $custStmt->fetchColumn();
        $kpis['total_customers_label'] = 'Semua pelanggan di database';
        
    } else {
        $custWhere = [];
        $custParams = [];
        $custQuery = "SELECT COUNT(DISTINCT fp.pelanggan_key) 
            FROM fakta_penjualan fp
            JOIN dim_waktu w ON fp.waktu_id = w.waktu_id
            JOIN dim_produk dp ON fp.produk_key = dp.produk_key
            JOIN dim_pelanggan dc ON fp.pelanggan_key = dc.pelanggan_key";
        
        if ($selectedYear) {
            $custWhere[] = 'w.tahun = :year';
            $custParams[':year'] = $selectedYear;
        }
        if ($selectedMonth) {
            $custWhere[] = 'w.bulan = :month';
            $custParams[':month'] = $selectedMonth;
        }
        if ($selectedCategory) {
            $custWhere[] = 'dp.kategori_inggris = :category';
            $custParams[':category'] = $selectedCategory;
        }
        if ($selectedState) {
            $custWhere[] = 'dc.state = :state';
            $custParams[':state'] = $selectedState;
        }
        
        if (!empty($custWhere)) {
            $custQuery .= ' WHERE ' . implode(' AND ', $custWhere);
        }
        
        $custStmt = $pdo->prepare($custQuery);
        $custStmt->execute($custParams);
        $kpis['total_customers'] = (int) $custStmt->fetchColumn();
        
        $timeDesc = '';
        if ($selectedYear && $selectedMonth) {
            $timeDesc = ' di ' . $months[$selectedMonth] . ' ' . $selectedYear;
        } elseif ($selectedYear) {
            $timeDesc = ' di tahun ' . $selectedYear;
        } elseif ($selectedMonth) {
            $timeDesc = ' di bulan ' . $months[$selectedMonth];
        }
        $kpis['total_customers_label'] = 'Pelanggan aktif bertransaksi' . $timeDesc;
    }
    
} catch (Exception $e) { 
    $kpis['total_customers'] = 0; 
    $kpis['total_customers_label'] = 'Error menghitung pelanggan';
}

// 2. TOTAL PRODUK
try {
    $prodWhere = [];
    $prodParams = [];
    $prodQuery = "SELECT COUNT(DISTINCT fp.produk_key) 
        FROM fakta_penjualan fp
        JOIN dim_waktu w ON fp.waktu_id = w.waktu_id
        JOIN dim_produk dp ON fp.produk_key = dp.produk_key
        JOIN dim_pelanggan dc ON fp.pelanggan_key = dc.pelanggan_key";
    
    if ($selectedYear) {
        $prodWhere[] = 'w.tahun = :year';
        $prodParams[':year'] = $selectedYear;
    }
    if ($selectedMonth) {
        $prodWhere[] = 'w.bulan = :month';
        $prodParams[':month'] = $selectedMonth;
    }
    if ($selectedCategory) {
        $prodWhere[] = 'dp.kategori_inggris = :category';
        $prodParams[':category'] = $selectedCategory;
    }
    if ($selectedState) {
        $prodWhere[] = 'dc.state = :state';
        $prodParams[':state'] = $selectedState;
    }
    
    if (!empty($prodWhere)) {
        $prodQuery .= ' WHERE ' . implode(' AND ', $prodWhere);
    }
    
    $prodStmt = $pdo->prepare($prodQuery);
    $prodStmt->execute($prodParams);
    $kpis['total_products'] = (int) $prodStmt->fetchColumn();
} catch (Exception $e) { 
    $kpis['total_products'] = 0; 
}

// 3. TOTAL KATEGORI
try {
    $catWhere = [];
    $catParams = [];
    $catQuery = "SELECT COUNT(DISTINCT dp.kategori_inggris) 
        FROM fakta_penjualan fp
        JOIN dim_waktu w ON fp.waktu_id = w.waktu_id
        JOIN dim_produk dp ON fp.produk_key = dp.produk_key
        JOIN dim_pelanggan dc ON fp.pelanggan_key = dc.pelanggan_key";
    
    if ($selectedYear) {
        $catWhere[] = 'w.tahun = :year';
        $catParams[':year'] = $selectedYear;
    }
    if ($selectedMonth) {
        $catWhere[] = 'w.bulan = :month';
        $catParams[':month'] = $selectedMonth;
    }
    if ($selectedCategory) {
        $catWhere[] = 'dp.kategori_inggris = :category';
        $catParams[':category'] = $selectedCategory;
    }
    if ($selectedState) {
        $catWhere[] = 'dc.state = :state';
        $catParams[':state'] = $selectedState;
    }
    
    if (!empty($catWhere)) {
        $catQuery .= ' WHERE ' . implode(' AND ', $catWhere);
    }
    
    $catStmt = $pdo->prepare($catQuery);
    $catStmt->execute($catParams);
    $kpis['total_categories'] = (int) $catStmt->fetchColumn();
} catch (Exception $e) { 
    $kpis['total_categories'] = 0; 
}

// 4. TOTAL PRODUK TERJUAL
try {
    $orderWhere = [];
    $orderParams = [];
    $orderQuery = "SELECT COALESCE(SUM(fp.jumlah), 0) FROM fakta_penjualan fp
        JOIN dim_waktu w ON fp.waktu_id = w.waktu_id
        JOIN dim_produk dp ON fp.produk_key = dp.produk_key
        JOIN dim_pelanggan dc ON fp.pelanggan_key = dc.pelanggan_key";
    
    if ($selectedYear) {
        $orderWhere[] = 'w.tahun = :y';
        $orderParams[':y'] = $selectedYear;
    }
    if ($selectedMonth) {
        $orderWhere[] = 'w.bulan = :m';
        $orderParams[':m'] = $selectedMonth;
    }
    if ($selectedCategory) {
        $orderWhere[] = 'dp.kategori_inggris = :category';
        $orderParams[':category'] = $selectedCategory;
    }
    if ($selectedState) {
        $orderWhere[] = 'dc.state = :state';
        $orderParams[':state'] = $selectedState;
    }
    
    if (!empty($orderWhere)) {
        $orderQuery .= ' WHERE ' . implode(' AND ', $orderWhere);
    }
    $stmt = $pdo->prepare($orderQuery);
    $stmt->execute($orderParams);
    $kpis['total_orders'] = (int) $stmt->fetchColumn();
} catch (Exception $e) { 
    $kpis['total_orders'] = 0; 
}

// 5. REVENUE
try {
    $revWhere = [];
    $revParams = [];
    $revQuery = "SELECT COALESCE(SUM(fp.total_harga),0) FROM fakta_penjualan fp
        JOIN dim_waktu w ON fp.waktu_id = w.waktu_id
        JOIN dim_produk dp ON fp.produk_key = dp.produk_key
        JOIN dim_pelanggan dc ON fp.pelanggan_key = dc.pelanggan_key";
    
    if ($selectedYear) {
        $revWhere[] = 'w.tahun = :y';
        $revParams[':y'] = $selectedYear;
    }
    if ($selectedMonth) {
        $revWhere[] = 'w.bulan = :m';
        $revParams[':m'] = $selectedMonth;
    }
    if ($selectedCategory) {
        $revWhere[] = 'dp.kategori_inggris = :category';
        $revParams[':category'] = $selectedCategory;
    }
    if ($selectedState) {
        $revWhere[] = 'dc.state = :state';
        $revParams[':state'] = $selectedState;
    }
    
    if (!empty($revWhere)) {
        $revQuery .= ' WHERE ' . implode(' AND ', $revWhere);
    }
    $stmt = $pdo->prepare($revQuery);
    $stmt->execute($revParams);
    $kpis['total_revenue'] = (float) $stmt->fetchColumn();
} catch (Exception $e) { 
    $kpis['total_revenue'] = 0; 
}

// 6. AVG DELIVERY
try {
    $avgWhere = [];
    $avgParams = [];
    $avgQuery = "SELECT AVG(fpg.durasi_pengiriman) FROM fakta_pengiriman fpg
        JOIN dim_produk dp ON fpg.produk_key = dp.produk_key
        JOIN dim_pelanggan dc ON fpg.pelanggan_key = dc.pelanggan_key
        JOIN dim_waktu w ON fpg.waktu_id = w.waktu_id";
    
    if ($selectedYear) {
        $avgWhere[] = 'w.tahun = :year';
        $avgParams[':year'] = $selectedYear;
    }
    if ($selectedMonth) {
        $avgWhere[] = 'w.bulan = :month';
        $avgParams[':month'] = $selectedMonth;
    }
    if ($selectedCategory) {
        $avgWhere[] = 'dp.kategori_inggris = :category';
        $avgParams[':category'] = $selectedCategory;
    }
    if ($selectedState) {
        $avgWhere[] = 'dc.state = :state';
        $avgParams[':state'] = $selectedState;
    }
    
    if (!empty($avgWhere)) {
        $avgQuery .= ' WHERE ' . implode(' AND ', $avgWhere);
    }
    $avgStmt = $pdo->prepare($avgQuery);
    $avgStmt->execute($avgParams);
    $avg = $avgStmt->fetchColumn();
    $kpis['avg_delivery_days'] = $avg !== null ? round((float)$avg,2) : null;
} catch (Exception $e) { 
    $kpis['avg_delivery_days'] = null; 
}

// ============================================================
// PAYMENT METHOD CHART
// ============================================================
try {
    $paymentWhere = [];
    $paymentParams = [];
    $paymentQuery = 'SELECT mp.metode_pembayaran, COUNT(*) AS jumlah
        FROM fakta_pembayaran fp
        JOIN dim_metode_pembayaran mp ON fp.metode_key = mp.metode_key
        JOIN dim_waktu w ON fp.waktu_id = w.waktu_id
        JOIN dim_pelanggan dc ON fp.pelanggan_key = dc.pelanggan_key';
    
    if ($selectedYear) {
        $paymentWhere[] = 'w.tahun = :y';
        $paymentParams[':y'] = $selectedYear;
    }
    if ($selectedMonth) {
        $paymentWhere[] = 'w.bulan = :m';
        $paymentParams[':m'] = $selectedMonth;
    }
    if ($selectedState) {
        $paymentWhere[] = 'dc.state = :state';
        $paymentParams[':state'] = $selectedState;
    }
    
    if (!empty($paymentWhere)) {
        $paymentQuery .= ' WHERE ' . implode(' AND ', $paymentWhere);
    }
    $paymentQuery .= ' GROUP BY mp.metode_pembayaran ORDER BY jumlah DESC';
    $stmt = $pdo->prepare($paymentQuery);
    $stmt->execute($paymentParams);
    $paymentMethods = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $paymentMethods = [];
}

// ============================================================
// TOP 10 PRODUK/KATEGORI
// ============================================================
$top10Items = [];
try {
    $top10Where = [];
    $top10Params = [];
    $top10Query = $selectedTopBy === 'category'
        ? "SELECT dp.kategori_inggris AS label, SUM(fp.jumlah) AS total_qty
            FROM fakta_penjualan fp
            JOIN dim_produk dp ON fp.produk_key = dp.produk_key
            JOIN dim_waktu w ON fp.waktu_id = w.waktu_id
            JOIN dim_pelanggan dc ON fp.pelanggan_key = dc.pelanggan_key"
        : "SELECT dp.produk_id AS label, SUM(fp.jumlah) AS total_qty
            FROM fakta_penjualan fp
            JOIN dim_produk dp ON fp.produk_key = dp.produk_key
            JOIN dim_waktu w ON fp.waktu_id = w.waktu_id
            JOIN dim_pelanggan dc ON fp.pelanggan_key = dc.pelanggan_key";
    
    if ($selectedYear) {
        $top10Where[] = 'w.tahun = :y';
        $top10Params[':y'] = $selectedYear;
    }
    if ($selectedMonth) {
        $top10Where[] = 'w.bulan = :m';
        $top10Params[':m'] = $selectedMonth;
    }
    if ($selectedCategory && $selectedTopBy !== 'category') {
        $top10Where[] = 'dp.kategori_inggris = :category';
        $top10Params[':category'] = $selectedCategory;
    }
    if ($selectedState) {
        $top10Where[] = 'dc.state = :state';
        $top10Params[':state'] = $selectedState;
    }
    
    if (!empty($top10Where)) {
        $top10Query .= ' WHERE ' . implode(' AND ', $top10Where);
    }
    $top10Query .= $selectedTopBy === 'category'
        ? ' GROUP BY dp.kategori_inggris ORDER BY total_qty DESC LIMIT 10'
        : ' GROUP BY dp.produk_id ORDER BY total_qty DESC LIMIT 10';
    $top10Stmt = $pdo->prepare($top10Query);
    $top10Stmt->execute($top10Params);
    $top10Items = $top10Stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $top10Items = [];
}

// ============================================================
// TOP 5 STATES - HYBRID
// ============================================================
$top5States = [];
$top5StatesLabel = '';
try {
    $hasTimeFilter = ($selectedYear !== null || $selectedMonth !== null);
    
    if (!$hasTimeFilter) {
        $stateQuery = "SELECT state, COUNT(*) AS total_customers
            FROM dim_pelanggan
            WHERE state IS NOT NULL AND state != ''
            GROUP BY state
            ORDER BY total_customers DESC
            LIMIT 5";
        $stateStmt = $pdo->prepare($stateQuery);
        $stateStmt->execute();
        $top5States = $stateStmt->fetchAll(PDO::FETCH_ASSOC);
        $top5StatesLabel = 'Semua pelanggan di database';
        
    } else {
        $stateWhere = [];
        $stateParams = [];
        $stateQuery = "SELECT dc.state, COUNT(DISTINCT fp.pelanggan_key) AS total_customers
            FROM fakta_penjualan fp
            JOIN dim_waktu w ON fp.waktu_id = w.waktu_id
            JOIN dim_produk dp ON fp.produk_key = dp.produk_key
            JOIN dim_pelanggan dc ON fp.pelanggan_key = dc.pelanggan_key
            WHERE dc.state IS NOT NULL AND dc.state != ''";
        
        if ($selectedYear) {
            $stateWhere[] = 'w.tahun = :year';
            $stateParams[':year'] = $selectedYear;
        }
        if ($selectedMonth) {
            $stateWhere[] = 'w.bulan = :month';
            $stateParams[':month'] = $selectedMonth;
        }
        if ($selectedCategory) {
            $stateWhere[] = 'dp.kategori_inggris = :category';
            $stateParams[':category'] = $selectedCategory;
        }
        if ($selectedState) {
            $stateWhere[] = 'dc.state = :state';
            $stateParams[':state'] = $selectedState;
        }
        
        if (!empty($stateWhere)) {
            $stateQuery .= ' AND ' . implode(' AND ', $stateWhere);
        }
        
        $stateQuery .= ' GROUP BY dc.state ORDER BY total_customers DESC LIMIT 5';
        $stateStmt = $pdo->prepare($stateQuery);
        $stateStmt->execute($stateParams);
        $top5States = $stateStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $timeDesc = '';
        if ($selectedYear && $selectedMonth) {
            $timeDesc = ' di ' . $months[$selectedMonth] . ' ' . $selectedYear;
        } elseif ($selectedYear) {
            $timeDesc = ' di tahun ' . $selectedYear;
        } elseif ($selectedMonth) {
            $timeDesc = ' di bulan ' . $months[$selectedMonth];
        }
        $top5StatesLabel = 'Pelanggan aktif bertransaksi' . $timeDesc;
    }
} catch (Exception $e) {
    $top5States = [];
    $top5StatesLabel = 'Error menghitung data';
}

// Prepare data untuk chart Top 5 States
$stateLabels = [];
$stateData = [];
foreach ($top5States as $row) {
    $stateLabels[] = $row['state'];
    $stateData[] = (int) $row['total_customers'];
}

// ============================================================
// SALES BY MONTH CHART
// ============================================================
try {
    $where = [];
    $params = [];
    $joins = "JOIN dim_waktu w ON fp.waktu_id = w.waktu_id
            JOIN dim_produk dp ON fp.produk_key = dp.produk_key
            JOIN dim_pelanggan dc ON fp.pelanggan_key = dc.pelanggan_key";
    
    if ($selectedYear) {
        $where[] = 'w.tahun = :y';
        $params[':y'] = $selectedYear;
    }
    if ($selectedMonth) {
        $where[] = 'w.bulan = :m';
        $params[':m'] = $selectedMonth;
    }
    if ($selectedCategory) {
        $where[] = 'dp.kategori_inggris = :category';
        $params[':category'] = $selectedCategory;
    }
    if ($selectedState) {
        $where[] = 'dc.state = :state';
        $params[':state'] = $selectedState;
    }

    if ($selectedYear && $selectedMonth) {
        $sql = "SELECT w.tanggal AS label, SUM(fp.total_harga) AS revenue, SUM(fp.jumlah) AS qty
            FROM fakta_penjualan fp
            $joins";
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' GROUP BY w.tanggal ORDER BY w.tanggal';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($selectedYear) {
        $sql = "SELECT w.bulan, SUM(fp.total_harga) AS revenue, SUM(fp.jumlah) AS qty
            FROM fakta_penjualan fp
            $joins";
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' GROUP BY w.bulan ORDER BY w.bulan';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($selectedMonth) {
        $sql = "SELECT w.tahun, SUM(fp.total_harga) AS revenue, SUM(fp.jumlah) AS qty
            FROM fakta_penjualan fp
            $joins";
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' GROUP BY w.tahun ORDER BY w.tahun';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $sql = "SELECT w.tahun, w.bulan, SUM(fp.total_harga) AS revenue, SUM(fp.jumlah) AS qty
            FROM fakta_penjualan fp
            $joins";
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' GROUP BY w.tahun, w.bulan ORDER BY w.tahun, w.bulan';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
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

// ============================================================
// AVG DELIVERY PER STATE
// ============================================================
$avgStateLabels = [];
$avgStateData = [];
try {
    $avgStateQuery = "SELECT dp.state, ROUND(AVG(fp.durasi_pengiriman), 2) AS avg_delivery_days
        FROM fakta_pengiriman fp
        JOIN dim_pelanggan dp ON fp.pelanggan_key = dp.pelanggan_key
        JOIN dim_produk dpr ON fp.produk_key = dpr.produk_key
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
    if ($selectedCategory) {
        $avgStateWhere[] = 'dpr.kategori_inggris = :category';
        $avgStateParams[':category'] = $selectedCategory;
    }
    if ($selectedState) {
        $avgStateWhere[] = 'dp.state = :state';
        $avgStateParams[':state'] = $selectedState;
    }
    
    if (!empty($avgStateWhere)) {
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

// ============================================================
// AVG DELIVERY PER CATEGORY
// ============================================================
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
    JOIN dim_produk dp ON fp.produk_key = dp.produk_key
    JOIN dim_pelanggan dc ON fp.pelanggan_key = dc.pelanggan_key
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
    if ($selectedCategory) {
        $avgCategoryWhere[] = 'dp.kategori_inggris = :category';
        $avgCategoryParams[':category'] = $selectedCategory;
    }
    if ($selectedState) {
        $avgCategoryWhere[] = 'dc.state = :state';
        $avgCategoryParams[':state'] = $selectedState;
    }
    
    if (!empty($avgCategoryWhere)) {
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

// ============================================================
// RATING CHARTS
// ============================================================
$ratingLabels = [];
$ratingData = [];
$ratingMonthLabels = [];
$ratingMonthData = [];
try {
    $ratingYearQuery = "SELECT dw.tahun AS label, ROUND(AVG(fr.skor_review), 2) AS avg_rating
        FROM fakta_review fr
        JOIN dim_waktu dw ON fr.waktu_id = dw.waktu_id
        JOIN dim_produk dp ON fr.produk_key = dp.produk_key
        JOIN dim_pelanggan dc ON fr.pelanggan_key = dc.pelanggan_key";
    $ratingMonthQuery = "SELECT CONCAT(dw.tahun, '-', LPAD(dw.bulan::text, 2, '0')) AS label, ROUND(AVG(fr.skor_review), 2) AS avg_rating
        FROM fakta_review fr
        JOIN dim_waktu dw ON fr.waktu_id = dw.waktu_id
        JOIN dim_produk dp ON fr.produk_key = dp.produk_key
        JOIN dim_pelanggan dc ON fr.pelanggan_key = dc.pelanggan_key";
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
    if ($selectedCategory) {
        $ratingWhere[] = 'dp.kategori_inggris = :category';
        $ratingParams[':category'] = $selectedCategory;
    }
    if ($selectedState) {
        $ratingWhere[] = 'dc.state = :state';
        $ratingParams[':state'] = $selectedState;
    }
    
    if (!empty($ratingWhere)) {
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

// ============================================================
// TOP SELLER
// ============================================================
$topSellerLabelsQty = [];
$topSellerQty = [];
$topSellerLabelsRevenue = [];
$topSellerRevenue = [];
try {
    $sellerQuery = "SELECT ds.seller_id AS label, SUM(fp.jumlah) AS total_qty, SUM(fp.total_harga) AS total_revenue
        FROM fakta_penjualan fp
        JOIN dim_seller ds ON fp.seller_key = ds.seller_key
        JOIN dim_waktu w ON fp.waktu_id = w.waktu_id
        JOIN dim_produk dp ON fp.produk_key = dp.produk_key
        JOIN dim_pelanggan dc ON fp.pelanggan_key = dc.pelanggan_key";
    
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
    if ($selectedCategory) {
        $sellerWhere[] = 'dp.kategori_inggris = :category';
        $sellerParams[':category'] = $selectedCategory;
    }
    if ($selectedState) {
        $sellerWhere[] = 'dc.state = :state';
        $sellerParams[':state'] = $selectedState;
    }
    
    if (!empty($sellerWhere)) {
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

    // ============================================================
    // TOP CUSTOMER
    // ============================================================
    $topCustomerLabelsRevenue = [];
    $topCustomerRevenue = [];
    try {
        $customerQuery = "SELECT COALESCE(dp.pelanggan_id, CAST(fp.pelanggan_key AS varchar)) AS label, SUM(fp.total_harga) AS total_revenue
            FROM fakta_penjualan fp
            LEFT JOIN dim_pelanggan dp ON fp.pelanggan_key = dp.pelanggan_key
            JOIN dim_waktu w ON fp.waktu_id = w.waktu_id
            JOIN dim_produk dpr ON fp.produk_key = dpr.produk_key";
        
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
        if ($selectedCategory) {
            $customerWhere[] = 'dpr.kategori_inggris = :category';
            $customerParams[':category'] = $selectedCategory;
        }
        if ($selectedState) {
            $customerWhere[] = 'dp.state = :state';
            $customerParams[':state'] = $selectedState;
        }
        
        if (!empty($customerWhere)) {
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
        .chart-wrap canvas { width: 100% !important; height: 100% !important; display: block; }
        .card.h-100 .chart-wrap { min-height: 220px; }
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
        .kpi small {
            display: block;
            font-size: 0.7rem;
            color: #6c757d;
            margin-top: 2px;
        }
    </style>
</head>
<body class="p-4">
<div class="container">
    <h1 class="mb-4">📊 Dashboard Data Warehouse 📊</h1>

    <form method="get" class="row g-3 mb-4 align-items-end" id="filterForm">
        <div class="col-6 col-md-3">
            <label for="year" class="form-label">Tahun</label>
            <select id="year" name="year" class="form-select filter-select">
                <option value="">Semua tahun</option>
                <?php foreach ($years as $y): ?>
                    <option value="<?php echo $y; ?>" <?php echo ($selectedYear && $selectedYear == $y) ? 'selected' : ''; ?>><?php echo $y; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3">
            <label for="month" class="form-label">Bulan</label>
            <select id="month" name="month" class="form-select filter-select">
                <option value="">Semua bulan</option>
                <?php foreach ($availableMonths as $num): ?>
                    <option value="<?php echo $num; ?>" <?php echo ($selectedMonth && $selectedMonth == $num) ? 'selected' : ''; ?>><?php echo $months[$num]; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3">
            <label for="category" class="form-label">Kategori Produk</label>
            <select id="category" name="category" class="form-select filter-select">
                <option value="">Semua kategori</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo ($selectedCategory && $selectedCategory === $cat) ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3">
            <label for="state" class="form-label">State Pelanggan</label>
            <select id="state" name="state" class="form-select filter-select">
                <option value="">Semua state</option>
                <?php foreach ($stateOptions as $st): ?>
                    <option value="<?php echo htmlspecialchars($st); ?>" <?php echo ($selectedState && $selectedState === $st) ? 'selected' : ''; ?>><?php echo htmlspecialchars($st); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3">
            <label for="top_by" class="form-label">Top 10 Berdasarkan:</label>
            <select id="top_by" name="top_by" class="form-select">
                <?php foreach ($topByOptions as $value => $label): ?>
                    <option value="<?php echo $value; ?>" <?php echo ($selectedTopBy === $value) ? 'selected' : ''; ?>><?php echo $label; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto d-flex align-items-end gap-2">
            <button class="btn btn-primary" id="applyFilters">Terapkan Perubahan</button>
            <a href="index.php" class="btn btn-secondary">Reset Page</a>
            <a href="perbandingan.php" class="btn btn-info text-white">Perbandingan OLTP vs DWH</a>
            <a href="prediktif.php" class="btn btn-warning text-dark">Analisis Business Intelligent</a>
        </div>
    </form>

    <!-- KPI CARDS -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card kpi">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2 text-muted">👥 Total Pelanggan</h6>
                    <h3 id="totalCustomers"><?php echo number_format($kpis['total_customers']); ?></h3>
                    <small><?php echo isset($kpis['total_customers_label']) ? $kpis['total_customers_label'] : 'Pelanggan aktif bertransaksi berdasarkan filter'; ?></small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card kpi">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2 text-muted">📦 Total Produk</h6>
                    <h3><?php echo number_format($kpis['total_products']); ?></h3>
                    <small>Produk terjual berdasarkan filter</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card kpi">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2 text-muted">🎲 Total Kategori</h6>
                    <h3><?php echo number_format($kpis['total_categories']); ?></h3>
                    <small>Kategori terjual berdasarkan filter</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card kpi">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2 text-muted">💵 Total Produk Terjual</h6>
                    <h3><?php echo number_format($kpis['total_orders']); ?></h3>
                    <small>Jumlah unit terjual berdasarkan filter</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card kpi">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2 text-muted">💲 Revenue</h6>
                    <h3>R$ <?php echo number_format($kpis['total_revenue'],2,',','.'); ?></h3>
                    <small>Total pendapatan berdasarkan filter</small>
                </div>
            </div>
        </div>
    </div>

    <!-- REVENUE CHART & PAYMENT CHART -->
    <div class="row mb-4 align-items-stretch">
        <div class="col-12 col-lg-8">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <h5 class="card-title mb-1">💲 Revenue Per Waktu</h5>
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
                    <h5 class="card-title">💳 Metode Pembayaran Terpopuler</h5>
                    <div class="chart-wrap flex-fill">
                        <canvas id="paymentChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- TOP 10 -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">🏅 Top 10 <?php echo $selectedTopBy === 'category' ? 'Kategori' : 'Produk'; ?> Terlaris</h5>
                    <div class="chart-wrap">
                        <canvas id="top10Chart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- TOP SELLER -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="card-title mb-1">🏅 Top 10 Seller Terbaik</h5>
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

    <!-- TOP CUSTOMER -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">🏅 Top 10 Pelanggan Berdasarkan Revenue Pembelian</h5>
                    <div class="chart-wrap">
                        <canvas id="topCustomerRevenueChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MAP & TOP STATES -->
    <div class="row mb-4">
        <div class="col-12 col-lg-8">
            <div class="card h-100">
                <div class="card-body">
                    <div class="mb-2">
                        <h5 id="mapTitle" class="mb-2">🗺️ Peta Persebaran Pelanggan di Brazil</h5>
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
                    <h5 class="card-title">🏅 Top 5 State dengan Pelanggan Terbanyak</h5>
                    <div class="chart-wrap flex-fill">
                        <canvas id="stateCustomersChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- AVG DELIVERY & RATING CHARTS -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title mb-3">📊 Rata-rata Waktu Pengiriman per State</h5>
                    <div class="chart-wrap" style="min-height: 340px;">
                        <canvas id="avgStateChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title mb-3">📊 Rata-rata Waktu Pengiriman per Kategori</h5>
                    <div class="chart-wrap" style="min-height: 320px;">
                        <canvas id="avgCategoryChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-6">
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title mb-3">📊 Rata-rata Rating Kepuasan Pelanggan per Tahun</h5>
                    <div class="chart-wrap" style="min-height: 280px;">
                        <canvas id="ratingChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-6">
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title mb-3">📊 Rata-rata Rating Kepuasan Pelanggan per Bulan</h5>
                    <div class="chart-wrap" style="min-height: 280px;">
                        <canvas id="ratingMonthChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
// ============================================================
// DATA DARI PHP
// ============================================================
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

// ============================================================
// CHART FUNCTIONS
// ============================================================
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
                                : `Total Revenue R$: ${value.toLocaleString('id-ID')}`;
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

// ============================================================
// MAP FUNCTIONS
// ============================================================
function getFilterParams() {
    const year = document.getElementById('year')?.value || '';
    const month = document.getElementById('month')?.value || '';
    const category = document.getElementById('category')?.value || '';
    const state = document.getElementById('state')?.value || '';
    return { year, month, category, state };
}

function loadMapData(tipe = 'pelanggan') {
    const filters = getFilterParams();
    let url = `distribusi_state.php?tipe=${tipe}`;
    if (filters.year) url += `&year=${filters.year}`;
    if (filters.month) url += `&month=${filters.month}`;
    if (filters.category) url += `&category=${encodeURIComponent(filters.category)}`;
    if (filters.state) url += `&state=${encodeURIComponent(filters.state)}`;
    
    fetch(url)
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

function reloadMap() {
    const btnPelanggan = document.getElementById('btnPelanggan');
    const tipe = btnPelanggan?.classList.contains('btn-primary') ? 'pelanggan' : 'seller';
    loadMapData(tipe);
}

// ============================================================
// LEAFLET MAP
// ============================================================
const map = L.map('map').setView([-14.2350, -51.9253], 4);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors'
}).addTo(map);

let markersLayer = L.layerGroup().addTo(map);

function renderMarkers(data, maxVal) {
    markersLayer.clearLayers();

    const counts = data.map(d => (typeof d.jumlah === 'number' ? d.jumlah : 0));
    const minCount = counts.length ? Math.min(...counts) : 0;
    const maxCount = maxVal || (counts.length ? Math.max(...counts) : 1);

    const minRadius = 15000;
    const maxRadius = 200000;

    data.forEach(item => {
        if (item.lat === null || item.lng === null) return;

        const count = item.jumlah || 0;
        const norm = (maxCount === minCount) ? 0.5 : Math.max(0, Math.min(1, (count - minCount) / (maxCount - minCount)));
        const scaled = Math.sqrt(norm);
        const radius = minRadius + scaled * (maxRadius - minRadius);

        function getColorByRatio(r) {
            if (r >= 0.66) return '#e74c3c';
            if (r >= 0.33) return '#f1c40f';
            return '#2ecc71';
        }

        const ratio = count / (maxCount || 1);
        const color = getColorByRatio(ratio);

        const circle = L.circle([item.lat, item.lng], {
            color: color,
            fillColor: color,
            fillOpacity: 0.7,
            radius: radius
        });

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

// ============================================================
// EVENT LISTENERS
// ============================================================
window.addEventListener('load', () => {
    initCharts();
    renderTopSellerChart('qty');
    
    const sellerMetricSelect = document.getElementById('sellerMetricSelect');
    if (sellerMetricSelect) {
        sellerMetricSelect.addEventListener('change', function() {
            renderTopSellerChart(this.value);
        });
    }
    
    // Load map dengan filter yang ada
    loadMapData('pelanggan');
    try { map.invalidateSize(); } catch (e) {}
});

// Event listener untuk reload map saat filter berubah
document.addEventListener('DOMContentLoaded', function() {
    const filterElements = ['year', 'month', 'category', 'state'];
    filterElements.forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('change', function() {
                setTimeout(reloadMap, 300);
            });
        }
    });
    
    const applyBtn = document.getElementById('applyFilters');
    if (applyBtn) {
        applyBtn.addEventListener('click', function(e) {
            setTimeout(reloadMap, 500);
        });
    }
});

</script>

</body>
</html>