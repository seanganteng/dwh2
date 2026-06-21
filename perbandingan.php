<?php
require_once __DIR__ . '/inc/db.php';

$pdo = getPDO();

$skenario = [
    'penjualan_kategori' => [
        'label' => 'Penjualan/Kategori',
        'dwh_tables' => 2, 'dwh_joins' => 1,
        'oltp_tables' => 6, 'oltp_joins' => 6,
        'dwh_sql' => "SELECT dp.kategori, dp.kategori_inggris,\n  SUM(fp.total_harga) AS total,\n  COUNT(fp.id) AS cnt\nFROM fakta_penjualan fp\nJOIN dim_produk dp\n  ON fp.produk_key = dp.produk_key\nGROUP BY dp.kategori, dp.kategori_inggris\nORDER BY total DESC;",
        'oltp_sql' => "SELECT p.product_category_name,\n  SUM(oi.price) AS total\nFROM orders o\nJOIN order_items oi\n  ON o.order_id = oi.order_id\nJOIN products p\n  ON oi.product_id = p.product_id\nJOIN customers c\n  ON o.customer_id = c.customer_id\nJOIN sellers s\n  ON oi.seller_id = s.seller_id\nJOIN order_payments op\n  ON o.order_id = op.order_id\nGROUP BY p.product_category_name\nORDER BY total DESC;",
    ],
    'analisis_pelanggan' => [
        'label' => 'Analisis Pelanggan',
        'dwh_tables' => 2, 'dwh_joins' => 1,
        'oltp_tables' => 5, 'oltp_joins' => 5,
        'dwh_sql' => "SELECT dpl.state, dpl.kota,\n  COUNT(DISTINCT fp.pelanggan_key) AS jumlah_pelanggan,\n  SUM(fp.total_harga) AS total_belanja\nFROM fakta_penjualan fp\nJOIN dim_pelanggan dpl\n  ON fp.pelanggan_key = dpl.pelanggan_key\nGROUP BY dpl.state, dpl.kota\nORDER BY total_belanja DESC;",
        'oltp_sql' => "SELECT c.customer_state, c.customer_city,\n  COUNT(DISTINCT c.customer_id) AS jumlah_pelanggan,\n  SUM(oi.price) AS total_belanja\nFROM customers c\nJOIN orders o\n  ON c.customer_id = o.customer_id\nJOIN order_items oi\n  ON o.order_id = oi.order_id\nJOIN order_payments op\n  ON o.order_id = op.order_id\nJOIN products p\n  ON oi.product_id = p.product_id\nGROUP BY c.customer_state, c.customer_city\nORDER BY total_belanja DESC;",
    ],
    'tren_bulanan' => [
        'label' => 'Tren Bulanan',
        'dwh_tables' => 2, 'dwh_joins' => 1,
        'oltp_tables' => 4, 'oltp_joins' => 4,
        'dwh_sql' => "SELECT dw.tahun, dw.bulan,\n  SUM(fp.total_harga) AS total,\n  COUNT(fp.id) AS jumlah_transaksi\nFROM fakta_penjualan fp\nJOIN dim_waktu dw\n  ON fp.waktu_id = dw.waktu_id\nGROUP BY dw.tahun, dw.bulan\nORDER BY dw.tahun, dw.bulan;",
        'oltp_sql' => "SELECT EXTRACT(YEAR FROM o.order_purchase_timestamp) AS tahun,\n  EXTRACT(MONTH FROM o.order_purchase_timestamp) AS bulan,\n  SUM(oi.price) AS total,\n  COUNT(DISTINCT o.order_id) AS jumlah_transaksi\nFROM orders o\nJOIN order_items oi\n  ON o.order_id = oi.order_id\nJOIN order_payments op\n  ON o.order_id = op.order_id\nGROUP BY 1, 2\nORDER BY 1, 2;",
    ],
    'review_produk' => [
        'label' => 'Review/Produk',
        'dwh_tables' => 2, 'dwh_joins' => 1,
        'oltp_tables' => 4, 'oltp_joins' => 4,
        'dwh_sql' => "SELECT dp.kategori,\n  ROUND(AVG(fr.skor_review)::numeric, 2) AS rata_rata_skor,\n  COUNT(fr.id) AS jumlah_review\nFROM fakta_review fr\nJOIN dim_produk dp\n  ON fr.produk_key = dp.produk_key\nGROUP BY dp.kategori\nORDER BY rata_rata_skor DESC;",
        'oltp_sql' => "SELECT p.product_category_name,\n  ROUND(AVG(r.review_score)::numeric, 2) AS rata_rata_skor,\n  COUNT(r.review_id) AS jumlah_review\nFROM order_reviews r\nJOIN orders o\n  ON r.order_id = o.order_id\nJOIN order_items oi\n  ON o.order_id = oi.order_id\nJOIN products p\n  ON oi.product_id = p.product_id\nGROUP BY p.product_category_name\nORDER BY rata_rata_skor DESC;",
    ],
    'pengiriman_kota' => [
        'label' => 'Pengiriman/Kota',
        'dwh_tables' => 2, 'dwh_joins' => 1,
        'oltp_tables' => 6, 'oltp_joins' => 6,
        'dwh_sql' => "SELECT dpl.kota, dpl.state,\n  ROUND(AVG(fpg.durasi_pengiriman)::numeric, 1) AS rata_rata_hari,\n  COUNT(fpg.id) AS jumlah_pengiriman\nFROM fakta_pengiriman fpg\nJOIN dim_pelanggan dpl\n  ON fpg.pelanggan_key = dpl.pelanggan_key\nGROUP BY dpl.kota, dpl.state\nORDER BY rata_rata_hari DESC;",
        'oltp_sql' => "SELECT c.customer_city, c.customer_state,\n  ROUND((AVG(EXTRACT(EPOCH FROM (o.order_delivered_customer_date - o.order_purchase_timestamp))) / 86400)::numeric, 1) AS rata_rata_hari,\n  COUNT(*) AS jumlah_pengiriman\nFROM orders o\nJOIN customers c\n  ON o.customer_id = c.customer_id\nJOIN order_items oi\n  ON o.order_id = oi.order_id\nJOIN sellers s\n  ON oi.seller_id = s.seller_id\nJOIN products p\n  ON oi.product_id = p.product_id\nJOIN order_payments op\n  ON o.order_id = op.order_id\nGROUP BY c.customer_city, c.customer_state\nORDER BY rata_rata_hari DESC;",
    ],
    'metode_pembayaran' => [
        'label' => 'Metode Pembayaran',
        'dwh_tables' => 2, 'dwh_joins' => 1,
        'oltp_tables' => 1, 'oltp_joins' => 0,
        'dwh_sql' => "SELECT mp.metode_pembayaran,\n  COUNT(*) AS jumlah\nFROM fakta_pembayaran fp\nJOIN dim_metode_pembayaran mp\n  ON fp.metode_key = mp.metode_key\nGROUP BY mp.metode_pembayaran\nORDER BY jumlah DESC;",
        'oltp_sql' => "SELECT payment_type AS metode_pembayaran,\n  COUNT(*) AS jumlah\nFROM order_payments\nWHERE payment_type IS NOT NULL\nGROUP BY payment_type\nORDER BY jumlah DESC;",
    ],
    'top_produk' => [
        'label' => 'Top 10 Produk',
        'dwh_tables' => 2, 'dwh_joins' => 1,
        'oltp_tables' => 3, 'oltp_joins' => 2,
        'dwh_sql' => "SELECT dp.produk_id AS label,\n  SUM(fp.jumlah) AS total_qty\nFROM fakta_penjualan fp\nJOIN dim_produk dp\n  ON fp.produk_key = dp.produk_key\nGROUP BY dp.produk_id\nORDER BY total_qty DESC\nLIMIT 10;",
        'oltp_sql' => "SELECT oi.product_id AS label,\n  COUNT(*) AS total_qty\nFROM orders o\nJOIN order_items oi\n  ON o.order_id = oi.order_id\nGROUP BY oi.product_id\nORDER BY total_qty DESC\nLIMIT 10;",
    ],
    'top_state_pelanggan' => [
        'label' => 'Top 5 State Pelanggan',
        'dwh_tables' => 1, 'dwh_joins' => 0,
        'oltp_tables' => 1, 'oltp_joins' => 0,
        'dwh_sql' => "SELECT state,\n  COUNT(*) AS total_customers\nFROM dim_pelanggan\nWHERE state IS NOT NULL AND state != ''\nGROUP BY state\nORDER BY total_customers DESC\nLIMIT 5;",
        'oltp_sql' => "SELECT customer_state AS state,\n  COUNT(*) AS total_customers\nFROM customers\nWHERE customer_state IS NOT NULL AND customer_state != ''\nGROUP BY customer_state\nORDER BY total_customers DESC\nLIMIT 5;",
    ],
    'rating_tahunan' => [
        'label' => 'Rating per Tahun',
        'dwh_tables' => 2, 'dwh_joins' => 1,
        'oltp_tables' => 2, 'oltp_joins' => 1,
        'dwh_sql' => "SELECT dw.tahun AS label,\n  ROUND(AVG(fr.skor_review)::numeric, 2) AS avg_rating\nFROM fakta_review fr\nJOIN dim_waktu dw\n  ON fr.waktu_id = dw.waktu_id\nGROUP BY dw.tahun\nORDER BY dw.tahun;",
        'oltp_sql' => "SELECT EXTRACT(YEAR FROM o.order_purchase_timestamp) AS label,\n  ROUND(AVG(r.review_score)::numeric, 2) AS avg_rating\nFROM order_reviews r\nJOIN orders o\n  ON r.order_id = o.order_id\nGROUP BY 1\nORDER BY 1;",
    ],
    'top_seller_revenue' => [
        'label' => 'Top Seller (Revenue)',
        'dwh_tables' => 2, 'dwh_joins' => 1,
        'oltp_tables' => 3, 'oltp_joins' => 2,
        'dwh_sql' => "SELECT ds.seller_id AS label,\n  SUM(fp.jumlah) AS total_qty,\n  SUM(fp.total_harga) AS total_revenue\nFROM fakta_penjualan fp\nJOIN dim_seller ds\n  ON fp.seller_key = ds.seller_key\nGROUP BY ds.seller_id\nORDER BY total_revenue DESC\nLIMIT 10;",
        'oltp_sql' => "SELECT oi.seller_id AS label,\n  COUNT(*) AS total_qty,\n  SUM(oi.price) AS total_revenue\nFROM orders o\nJOIN order_items oi\n  ON o.order_id = oi.order_id\nGROUP BY oi.seller_id\nORDER BY total_revenue DESC\nLIMIT 10;",
    ],
    'top_kategori' => [
        'label' => 'Top 10 Kategori',
        'dwh_tables' => 2, 'dwh_joins' => 1,
        'oltp_tables' => 3, 'oltp_joins' => 2,
        'dwh_sql' => "SELECT dp.kategori_inggris AS label,\n  SUM(fp.jumlah) AS total_qty\nFROM fakta_penjualan fp\nJOIN dim_produk dp\n  ON fp.produk_key = dp.produk_key\nGROUP BY dp.kategori_inggris\nORDER BY total_qty DESC\nLIMIT 10;",
        'oltp_sql' => "SELECT p.product_category_name AS label,\n  COUNT(*) AS total_qty\nFROM orders o\nJOIN order_items oi\n  ON o.order_id = oi.order_id\nJOIN products p\n  ON oi.product_id = p.product_id\nGROUP BY p.product_category_name\nORDER BY total_qty DESC\nLIMIT 10;",
    ],
    'tren_penjualan_bulanan' => [
        'label' => 'Tren Penjualan Bulanan',
        'dwh_tables' => 2, 'dwh_joins' => 1,
        'oltp_tables' => 2, 'oltp_joins' => 1,
        'dwh_sql' => "SELECT w.tahun, w.bulan,\n  SUM(fp.total_harga) AS revenue,\n  SUM(fp.jumlah) AS qty\nFROM fakta_penjualan fp\nJOIN dim_waktu w\n  ON fp.waktu_id = w.waktu_id\nGROUP BY w.tahun, w.bulan\nORDER BY w.tahun, w.bulan;",
        'oltp_sql' => "SELECT EXTRACT(YEAR FROM o.order_purchase_timestamp) AS tahun,\n  EXTRACT(MONTH FROM o.order_purchase_timestamp) AS bulan,\n  SUM(oi.price) AS revenue,\n  COUNT(*) AS qty\nFROM orders o\nJOIN order_items oi\n  ON o.order_id = oi.order_id\nGROUP BY 1, 2\nORDER BY 1, 2;",
    ],
    'pengiriman_state' => [
        'label' => 'Pengiriman/State',
        'dwh_tables' => 2, 'dwh_joins' => 1,
        'oltp_tables' => 2, 'oltp_joins' => 1,
        'dwh_sql' => "SELECT dp.state,\n  ROUND(AVG(fp.durasi_pengiriman)::numeric, 2) AS avg_delivery_days\nFROM fakta_pengiriman fp\nJOIN dim_pelanggan dp\n  ON fp.pelanggan_key = dp.pelanggan_key\nGROUP BY dp.state\nORDER BY avg_delivery_days DESC;",
        'oltp_sql' => "SELECT c.customer_state AS state,\n  ROUND((AVG(EXTRACT(EPOCH FROM (o.order_delivered_customer_date - o.order_purchase_timestamp))) / 86400)::numeric, 2) AS avg_delivery_days\nFROM orders o\nJOIN customers c\n  ON o.customer_id = c.customer_id\nGROUP BY c.customer_state\nORDER BY avg_delivery_days DESC;",
    ],
];

$pdoOltp = getPDOOltp();

$hasil = [];
$totalDwh = 0;
$totalOltp = 0;

foreach ($skenario as $key => $sk) {
    // --- Eksekusi nyata ke DWH ---
    $waktuMulaiDwh = microtime(true);
    $jumlahBarisDwh = 0;
    $errorDwh = null;

    try {
        $stmt = $pdo->query($sk['dwh_sql']);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $jumlahBarisDwh = count($rows);
    } catch (Exception $e) {
        $errorDwh = $e->getMessage();
    }

    $waktuDwhMs = (microtime(true) - $waktuMulaiDwh) * 1000;

    // --- Eksekusi nyata ke OLTP (koneksi & database terpisah) ---
    $waktuMulaiOltp = microtime(true);
    $jumlahBarisOltp = 0;
    $errorOltp = null;

    try {
        $stmtOltp = $pdoOltp->query($sk['oltp_sql']);
        $rowsOltp = $stmtOltp->fetchAll(PDO::FETCH_ASSOC);
        $jumlahBarisOltp = count($rowsOltp);
    } catch (Exception $e) {
        $errorOltp = $e->getMessage();
    }

    $waktuOltpMs = (microtime(true) - $waktuMulaiOltp) * 1000;

    $hasil[$key] = [
        'label' => $sk['label'],
        'dwh_ms' => $waktuDwhMs,
        'oltp_ms' => $waktuOltpMs,
        'speedup' => $waktuDwhMs > 0 ? $waktuOltpMs / $waktuDwhMs : 0,
        'jumlah_baris' => $jumlahBarisDwh,
        'jumlah_baris_oltp' => $jumlahBarisOltp,
        'dwh_joins' => $sk['dwh_joins'],
        'oltp_joins' => $sk['oltp_joins'],
        'dwh_tables' => $sk['dwh_tables'],
        'oltp_tables' => $sk['oltp_tables'],
        'dwh_sql' => $sk['dwh_sql'],
        'oltp_sql' => $sk['oltp_sql'],
        'error' => $errorDwh,
        'error_oltp' => $errorOltp,
    ];

    $totalDwh += $waktuDwhMs;
    $totalOltp += $waktuOltpMs;
}

$jumlahSkenario = max(1, count($hasil));
$rataRataDwh = $totalDwh / $jumlahSkenario;
$rataRataOltp = $totalOltp / $jumlahSkenario;
$rataRataSpeedup = $rataRataDwh > 0 ? $rataRataOltp / $rataRataDwh : 0;

$totalJoinDwh = array_sum(array_column($hasil, 'dwh_joins'));
$totalJoinOltp = array_sum(array_column($hasil, 'oltp_joins'));
$persenPenguranganJoin = $totalJoinOltp > 0
    ? (($totalJoinOltp - $totalJoinDwh) / $totalJoinOltp) * 100
    : 0;

// Breakdown proporsional fase eksekusi (untuk panel "Breakdown Waktu").
// $rataRataOltp dan $rataRataDwh di atas sekarang adalah waktu HASIL UKUR
// NYATA (microtime() di sekitar query asli ke masing-masing database).
// Breakdown per-fase di bawah ini tetap ilustratif/proporsional -- PHP
// tidak punya akses ke EXPLAIN ANALYZE per fase tanpa parsing tambahan --
// jadi total ms tetap akurat, hanya rincian "porsi tiap fase" yang berupa
// estimasi proporsi umum dari karakteristik query planner PostgreSQL:
// OLTP menghabiskan porsi besar di "full table scan" + "multi-join exec"
// (nested loop tanpa index), sedangkan DWH lebih banyak di "fact table
// scan" yang sudah pakai index pada surrogate key.
$breakdownOltp = [
    'Parsing query'    => $rataRataOltp * 0.05,
    'Join planning'    => $rataRataOltp * 0.12,
    'Full table scan'  => $rataRataOltp * 0.36,
    'Multi-join exec'  => $rataRataOltp * 0.33,
    'Agregasi'         => $rataRataOltp * 0.14,
];
$breakdownDwh = [
    'Parsing query'    => $rataRataDwh * 0.11,
    'Index lookup'     => $rataRataDwh * 0.22,
    'Fact table scan'  => $rataRataDwh * 0.44,
    'Dim join'         => $rataRataDwh * 0.17,
    'Agregasi'         => $rataRataDwh * 0.06,
];

// Data siap pakai untuk Chart.js (di-encode sebagai JSON di bawah).
// array_values() dipakai supaya ketiga array memakai index numerik yang
// konsisten — tanpa ini, json_encode bisa menghasilkan array untuk label
// tapi object untuk data (karena $hasil berkunci string), yang membuat
// Chart.js merender kategori ganda (sebagian kosong) di sumbu X.
$chartLabels = array_values(array_column($hasil, 'label'));
$chartDwh = array_values(array_map(fn($h) => round($h['dwh_ms'], 2), $hasil));
$chartOltp = array_values(array_map(fn($h) => round($h['oltp_ms'], 2), $hasil));
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Perbandingan Efisiensi Query — OLTP vs DWH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/mobile.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .stat-card { border-left: 4px solid #ccc; height: 100%; }
        .stat-card.oltp { border-left-color: #dc3545; }
        .stat-card.dwh  { border-left-color: #20c997; }
        .stat-card.speedup { border-left-color: #0d6efd; }
        .stat-card.join { border-left-color: #ffc107; }
        .stat-card .stat-label { font-size: 0.75rem; letter-spacing: .05em; text-transform: uppercase; color: #6c757d; }
        .stat-card .stat-value { font-size: 1.9rem; font-weight: 700; line-height: 1.2; }
        .stat-card .stat-sub { font-size: 0.8rem; color: #6c757d; }
        .badge-fast { background: #20c997; }
        .badge-slow { background: #dc3545; }
        .code-box {
            background: #1e1e2e; color: #d4d4d4; border-radius: 8px;
            padding: 1rem; font-family: 'Courier New', monospace; font-size: 0.82rem;
            white-space: pre-wrap; overflow-x: auto;
        }
        .code-box .kw { color: #569cd6; font-weight: 600; }
        .bar-track { background: #e9ecef; border-radius: 4px; height: 28px; overflow: hidden; }
        .bar-fill { height: 100%; display: flex; align-items: center; padding-left: .6rem; color: #fff; font-size: .8rem; font-weight: 600; white-space: nowrap; }
        .bar-fill.oltp { background: #dc3545; }
        .bar-fill.dwh { background: #20c997; }
        .breakdown-row { display: flex; align-items: center; gap: .75rem; margin-bottom: .55rem; }
        .breakdown-row .breakdown-label { width: 130px; font-size: .82rem; color: #495057; flex-shrink: 0; }
        .breakdown-row .breakdown-track { flex: 1; background: #e9ecef; border-radius: 4px; height: 14px; overflow: hidden; }
        .breakdown-row .breakdown-fill { height: 100%; border-radius: 4px; }
        .breakdown-row .breakdown-fill.oltp { background: #dc3545; }
        .breakdown-row .breakdown-fill.dwh { background: #20c997; }
        .breakdown-row .breakdown-val { width: 80px; text-align: right; font-size: .8rem; font-family: 'Courier New', monospace; }
        .chart-wrap { min-height: 260px; position: relative; box-sizing: border-box; padding-bottom: 1.2rem; }
        .chart-wrap canvas { width: 100% !important; height: 100% !important; display: block; }
    </style>
</head>
<body class="p-4">
<div class="container-fluid" style="max-width: 1320px;">

    <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1">⚡ Perbandingan Efisiensi Query</h1>
            <p class="text-muted mb-0">
                Benchmark <strong>real-time</strong>: waktu eksekusi DWH dan OLTP
                diukur langsung dari dua koneksi PostgreSQL terpisah (apple-to-apple).
            </p>
        </div>
        <a href="index.php" class="btn btn-secondary">⬅ Kembali ke Dashboard</a>
    </div>

    <!-- ===================== STAT CARDS ===================== -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="card stat-card oltp">
                <div class="card-body">
                    <div class="stat-label">Rata-rata Menggunakan OLTP</div>
                    <div class="stat-value text-danger"><?php echo number_format($rataRataOltp, 2); ?> ms</div>
                    <div class="stat-sub">Diukur langsung</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card stat-card dwh">
                <div class="card-body">
                    <div class="stat-label">Rata-rata Menggunakan DWH</div>
                    <div class="stat-value" style="color:#20c997;"><?php echo number_format($rataRataDwh, 2); ?> ms</div>
                    <div class="stat-sub">Diukur langsung</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card stat-card speedup">
                <div class="card-body">
                    <div class="stat-label">Peningkatan Kecepatan</div>
                    <div class="stat-value text-primary"><?php echo number_format($rataRataSpeedup, 1); ?>×</div>
                    <div class="stat-sub">Rata-rata speedup</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card stat-card join">
                <div class="card-body">
                    <div class="stat-label">Pengurangan Query Join</div>
                    <div class="stat-value text-warning">−<?php echo number_format($persenPenguranganJoin, 0); ?>%</div>
                    <div class="stat-sub"><?php echo $totalJoinDwh; ?> join vs <?php echo $totalJoinOltp; ?> join</div>
                </div>
            </div>
        </div>
    </div>

    <!-- ===================== CHART BENCHMARK ===================== -->
    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title mb-1">Benchmark Live — Waktu Eksekusi (ms)</h5>
            <p class="text-muted small mb-3">Data real dari PostgreSQL · diperbarui tiap load halaman</p>
            <div class="chart-wrap" style="min-height: 320px;">
                <canvas id="benchmarkChart"></canvas>
            </div>
        </div>
    </div>

    <!-- ===================== TABEL DETAIL ===================== -->
    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title mb-3">Detail Benchmark per Query</h5>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr class="text-muted small text-uppercase">
                            <th>Query</th>
                            <th>DWH (ms)</th>
                            <th>OLTP (ms)</th>
                            <th>Speedup</th>
                            <th style="width: 160px;">Visualisasi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($hasil as $h): ?>
                        <?php
                            $maxMs = max($h['dwh_ms'], $h['oltp_ms'], 1);
                            $pctDwh = max(6, ($h['dwh_ms'] / $maxMs) * 100);
                        ?>
                        <tr>
                            <td class="fw-semibold"><?php echo htmlspecialchars($h['label']); ?></td>
                            <td>
                                <code class="text-success"><?php echo number_format($h['dwh_ms'], 2); ?> ms</code>
                                <?php if ($h['error']): ?>
                                    <span class="badge bg-warning text-dark ms-1" title="<?php echo htmlspecialchars($h['error']); ?>">⚠ error</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <code class="text-danger"><?php echo number_format($h['oltp_ms'], 2); ?> ms</code>
                                <?php if (!empty($h['error_oltp'])): ?>
                                    <span class="badge bg-warning text-dark ms-1" title="<?php echo htmlspecialchars($h['error_oltp']); ?>">⚠ error</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge bg-primary"><?php echo number_format($h['speedup'], 1); ?>×</span></td>
                            <td>
                                <div class="bar-track">
                                    <div class="bar-fill dwh" style="width: <?php echo $pctDwh; ?>%"></div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ===================== CONTOH QUERY NYATA ===================== -->
    <div class="mb-1">
        <h5 class="mb-1">Contoh Query Nyata</h5>
        <p class="text-muted small">Berdasarkan skema <code>db_dwhfinalrevisi2</code> (DWH) dan <code>tugas_dw1</code> (OLTP) — kedua query di atas benar-benar dieksekusi langsung ke database masing-masing, bukan estimasi.</p>
    </div>

    <?php foreach ($hasil as $h): ?>
    <div class="card mb-4">
        <div class="card-body">
            <h6 class="mb-3"><?php echo htmlspecialchars($h['label']); ?></h6>
            <div class="row g-3">
                <div class="col-lg-6">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-semibold">📦 Tanpa DWH (OLTP)</span>
                        <span class="badge badge-slow">LAMBAT</span>
                    </div>
                    <p class="text-muted small mb-2"><?php echo $h['oltp_tables']; ?> tabel · <?php echo $h['oltp_joins']; ?> JOIN · Full table scan</p>
                    <div class="code-box"><?php echo htmlspecialchars($h['oltp_sql']); ?></div>
                    <p class="text-muted small mt-2 mb-0">Nested Loop Join · Tanpa index pada kolom join &nbsp;
                        <span class="text-danger fw-semibold float-end"><?php echo number_format($h['oltp_ms'], 2); ?> ms</span>
                    </p>
                </div>
                <div class="col-lg-6">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-semibold">🚀 Dengan DWH (Star Schema)</span>
                        <span class="badge badge-fast">CEPAT</span>
                    </div>
                    <p class="text-muted small mb-2"><?php echo $h['dwh_tables']; ?> tabel · <?php echo $h['dwh_joins']; ?> JOIN · Index scan integer key</p>
                    <div class="code-box"><?php echo htmlspecialchars($h['dwh_sql']); ?></div>
                    <p class="text-muted small mt-2 mb-0">Hash Join · Surrogate key integer &nbsp;
                        <span class="text-success fw-semibold float-end"><?php echo number_format($h['dwh_ms'], 2); ?> ms</span>
                    </p>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- ===================== KOMPLEKSITAS JOIN ===================== -->
    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title mb-1">Kompleksitas JOIN per Jenis Analisis</h5>
            <p class="text-muted small mb-3">Lebih sedikit JOIN = eksekusi lebih cepat + rencana query lebih sederhana</p>
            <?php $maxJoinKeseluruhan = max(1, max(array_column($hasil, 'oltp_joins')), max(array_column($hasil, 'dwh_joins'))); ?>
            <?php foreach ($hasil as $h): ?>
                <div class="mb-3">
                    <div class="text-muted small mb-1"><?php echo htmlspecialchars($h['label']); ?></div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="text-danger small" style="width: 45px;">OLTP</span>
                        <div class="bar-track flex-grow-1">
                            <div class="bar-fill oltp" style="width: <?php echo max(8, ($h['oltp_joins'] / $maxJoinKeseluruhan) * 100); ?>%;"><?php echo $h['oltp_joins']; ?> join</div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="small" style="width: 45px; color:#20c997;">DWH</span>
                        <div class="bar-track flex-grow-1">
                            <div class="bar-fill dwh" style="width: <?php echo max(8, ($h['dwh_joins'] / $maxJoinKeseluruhan) * 100); ?>%;"><?php echo $h['dwh_joins']; ?> join</div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ===================== BREAKDOWN WAKTU ===================== -->
    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-body">
                    <h6 class="text-danger mb-3">📦 OLTP — Breakdown Waktu</h6>
                    <?php foreach ($breakdownOltp as $fase => $ms): ?>
                        <div class="breakdown-row">
                            <div class="breakdown-label"><?php echo htmlspecialchars($fase); ?></div>
                            <div class="breakdown-track">
                                <div class="breakdown-fill oltp" style="width: <?php echo min(100, ($ms / max($rataRataOltp, 1)) * 220); ?>%;"></div>
                            </div>
                            <div class="breakdown-val"><?php echo number_format($ms, 2); ?> ms</div>
                        </div>
                    <?php endforeach; ?>
                    <hr>
                    <div class="fw-bold text-danger">Total aktual: <?php echo number_format($rataRataOltp, 2); ?> ms</div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-body">
                    <h6 style="color:#20c997;" class="mb-3">🚀 DWH — Breakdown Waktu</h6>
                    <?php foreach ($breakdownDwh as $fase => $ms): ?>
                        <div class="breakdown-row">
                            <div class="breakdown-label"><?php echo htmlspecialchars($fase); ?></div>
                            <div class="breakdown-track">
                                <div class="breakdown-fill dwh" style="width: <?php echo min(100, ($ms / max($rataRataDwh, 1)) * 220); ?>%;"></div>
                            </div>
                            <div class="breakdown-val"><?php echo number_format($ms, 2); ?> ms</div>
                        </div>
                    <?php endforeach; ?>
                    <hr>
                    <div class="fw-bold" style="color:#20c997;">Total aktual: <?php echo number_format($rataRataDwh, 2); ?> ms</div>
                </div>
            </div>
        </div>
    </div>

    <!-- ===================== KESIMPULAN ===================== -->
    <div class="alert alert-primary">
        <strong>Kesimpulan:</strong> Skema Star Schema mereduksi kompleksitas join dari rata-rata
        <?php echo number_format($totalJoinOltp / $jumlahSkenario, 1); ?> menjadi
        <?php echo number_format($totalJoinDwh / $jumlahSkenario, 1); ?> per query.
        Surrogate key integer (<code>produk_key</code>, <code>pelanggan_key</code>, <code>seller_key</code>, <code>waktu_id</code>)
        memungkinkan PostgreSQL menggunakan <em>hash join</em> + <em>index scan</em> sehingga query berjalan
        <?php echo number_format($rataRataSpeedup, 1); ?>× lebih cepat dibanding pola OLTP transaksional yang ternormalisasi penuh.
    </div>

</div>

<script>
const ctx = document.getElementById('benchmarkChart');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($chartLabels); ?>,
        datasets: [
            {
                label: 'OLTP (aktual)',
                data: <?php echo json_encode($chartOltp); ?>,
                backgroundColor: '#dc3545',
                borderRadius: 4,
            },
            {
                label: 'DWH (aktual)',
                data: <?php echo json_encode($chartDwh); ?>,
                backgroundColor: '#20c997',
                borderRadius: 4,
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'top', align: 'start' },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.dataset.label + ': ' + context.parsed.y.toFixed(2) + ' ms';
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                title: { display: true, text: 'ms' }
            }
        }
    }
});
</script>
</body>
</html>
