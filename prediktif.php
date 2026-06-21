<?php
require_once __DIR__ . '/inc/db.php';

$pdo = getPDO();

/* =========================================================
   BAGIAN 1: APA YANG TERJADI SAAT INI
   ========================================================= */

// 1a. Tren penjualan per bulan (seluruh histori) -> juga jadi basis regresi di Bagian 2
$trenBulanan = [];
try {
    $stmt = $pdo->query("
        SELECT dw.tahun, dw.bulan,
               SUM(fp.total_harga) AS total_penjualan,
               COUNT(fp.id) AS jumlah_transaksi
        FROM fakta_penjualan fp
        JOIN dim_waktu dw ON fp.waktu_id = dw.waktu_id
        GROUP BY dw.tahun, dw.bulan
        ORDER BY dw.tahun, dw.bulan
    ");
    $trenBulanan = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $trenBulanan = [];
}

// 1b. Kategori produk terlaris bulan/periode terakhir
$kategoriTerlaris = [];
try {
    $stmt = $pdo->query("
        SELECT dp.kategori, dp.kategori_inggris,
               SUM(fp.total_harga) AS total,
               SUM(fp.jumlah) AS qty
        FROM fakta_penjualan fp
        JOIN dim_produk dp ON fp.produk_key = dp.produk_key
        GROUP BY dp.kategori, dp.kategori_inggris
        ORDER BY total DESC
        LIMIT 5
    ");
    $kategoriTerlaris = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $kategoriTerlaris = [];
}

// 1c. Kategori produk paling lemah (calon "perlu perhatian")
$kategoriTerlemah = [];
try {
    $stmt = $pdo->query("
        SELECT dp.kategori, dp.kategori_inggris,
               SUM(fp.total_harga) AS total,
               SUM(fp.jumlah) AS qty
        FROM fakta_penjualan fp
        JOIN dim_produk dp ON fp.produk_key = dp.produk_key
        GROUP BY dp.kategori, dp.kategori_inggris
        ORDER BY total ASC
        LIMIT 5
    ");
    $kategoriTerlemah = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $kategoriTerlemah = [];
}

// 1d. Rata-rata skor review keseluruhan & jumlah review rendah (skor <= 2)
$reviewSummary = ['rata2' => 0, 'jumlah_review' => 0, 'review_rendah' => 0];
try {
    $row = $pdo->query("
        SELECT
            ROUND(AVG(skor_review)::numeric, 2) AS rata2,
            COUNT(*) AS jumlah_review,
            COUNT(*) FILTER (WHERE skor_review <= 2) AS review_rendah
        FROM fakta_review
    ")->fetch(PDO::FETCH_ASSOC);
    if ($row) $reviewSummary = $row;
} catch (Exception $e) {
}

// 1e. Rata-rata durasi pengiriman & kota dengan pengiriman paling lama
$pengirimanSummary = ['rata2' => 0];
try {
    $row = $pdo->query("SELECT ROUND(AVG(durasi_pengiriman)::numeric, 1) AS rata2 FROM fakta_pengiriman")->fetch(PDO::FETCH_ASSOC);
    if ($row) $pengirimanSummary = $row;
} catch (Exception $e) {
}

$kotaPengirimanLama = [];
try {
    $stmt = $pdo->query("
        SELECT dpl.kota, dpl.state,
               ROUND(AVG(fpg.durasi_pengiriman)::numeric, 1) AS rata_rata_hari,
               COUNT(*) AS jumlah
        FROM fakta_pengiriman fpg
        JOIN dim_pelanggan dpl ON fpg.pelanggan_key = dpl.pelanggan_key
        GROUP BY dpl.kota, dpl.state
        HAVING COUNT(*) >= 5
        ORDER BY rata_rata_hari DESC
        LIMIT 5
    ");
    $kotaPengirimanLama = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $kotaPengirimanLama = [];
}

// 1f. Metode pembayaran paling dominan
$metodePembayaran = [];
try {
    $stmt = $pdo->query("
        SELECT mp.metode_pembayaran,
               COUNT(*) AS jumlah,
               SUM(fp.total_bayar) AS total
        FROM fakta_pembayaran fp
        JOIN dim_metode_pembayaran mp ON fp.metode_key = mp.metode_key
        WHERE mp.metode_pembayaran IS NOT NULL
        GROUP BY mp.metode_pembayaran
        ORDER BY jumlah DESC
    ");
    $metodePembayaran = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $metodePembayaran = [];
}

// Bulan/periode terakhir yang tersedia di data (untuk narasi "saat ini")
$bulanTerakhir = !empty($trenBulanan) ? end($trenBulanan) : null;
reset($trenBulanan);

/* =========================================================
   BAGIAN 2: APA YANG AKAN TERJADI (PROYEKSI)
   Regresi linear sederhana: y = a + b*x
   x = indeks urutan bulan (0,1,2,...), y = total_penjualan
   b (slope) = (n*Σxy - Σx*Σy) / (n*Σx² - (Σx)²)
   a (intercept) = (Σy - b*Σx) / n
   ========================================================= */

function regresiLinear(array $xs, array $ys): array
{
    $n = count($xs);
    if ($n < 2) {
        return ['a' => 0, 'b' => 0, 'valid' => false];
    }
    $sumX = array_sum($xs);
    $sumY = array_sum($ys);
    $sumXY = 0;
    $sumX2 = 0;
    foreach ($xs as $i => $x) {
        $sumXY += $x * $ys[$i];
        $sumX2 += $x * $x;
    }
    $denom = ($n * $sumX2) - ($sumX * $sumX);
    if ($denom == 0) {
        return ['a' => $sumY / $n, 'b' => 0, 'valid' => false];
    }
    $b = (($n * $sumXY) - ($sumX * $sumY)) / $denom;
    $a = ($sumY - ($b * $sumX)) / $n;
    return ['a' => $a, 'b' => $b, 'valid' => true];
}

$xs = [];
$ysPenjualan = [];
$ysTransaksi = [];
foreach ($trenBulanan as $i => $row) {
    $xs[] = $i;
    $ysPenjualan[] = (float) $row['total_penjualan'];
    $ysTransaksi[] = (float) $row['jumlah_transaksi'];
}

$regPenjualan = regresiLinear($xs, $ysPenjualan);
$regTransaksi = regresiLinear($xs, $ysTransaksi);

// Proyeksi 3 bulan ke depan dari titik data terakhir
$jumlahBulanData = count($xs);
$proyeksi = [];
for ($i = 1; $i <= 3; $i++) {
    $xNext = $jumlahBulanData - 1 + $i;
    $prediksiPenjualan = $regPenjualan['a'] + ($regPenjualan['b'] * $xNext);
    $prediksiTransaksi = $regTransaksi['a'] + ($regTransaksi['b'] * $xNext);
    $proyeksi[] = [
        'ke' => $i,
        'penjualan' => max(0, $prediksiPenjualan),
        'transaksi' => max(0, round($prediksiTransaksi)),
    ];
}

// Arah tren & persentase pertumbuhan rata-rata per bulan (dari slope vs rata-rata)
$rataRataPenjualanBulanan = $jumlahBulanData > 0 ? array_sum($ysPenjualan) / $jumlahBulanData : 0;
$persenPertumbuhanPerBulan = $rataRataPenjualanBulanan > 0
    ? ($regPenjualan['b'] / $rataRataPenjualanBulanan) * 100
    : 0;
$arahTren = $persenPertumbuhanPerBulan > 1 ? 'naik' : ($persenPertumbuhanPerBulan < -1 ? 'turun' : 'stabil');

// Kategori dengan tren menanjak (bandingkan paruh pertama vs paruh kedua periode data)
$kategoriTren = [];
try {
    $stmt = $pdo->query("
        SELECT dp.kategori,
               dw.tahun, dw.bulan,
               SUM(fp.total_harga) AS total
        FROM fakta_penjualan fp
        JOIN dim_produk dp ON fp.produk_key = dp.produk_key
        JOIN dim_waktu dw ON fp.waktu_id = dw.waktu_id
        GROUP BY dp.kategori, dw.tahun, dw.bulan
        ORDER BY dp.kategori, dw.tahun, dw.bulan
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Susun per kategori jadi rangkaian waktu, lalu hitung slope masing-masing
    $perKategori = [];
    foreach ($rows as $r) {
        $perKategori[$r['kategori']][] = (float) $r['total'];
    }
    foreach ($perKategori as $kat => $seri) {
        if (count($seri) < 3) continue; // butuh minimal 3 titik biar slope bermakna
        $xsK = range(0, count($seri) - 1);
        $regK = regresiLinear($xsK, $seri);
        $rata = array_sum($seri) / count($seri);
        $persen = $rata > 0 ? ($regK['b'] / $rata) * 100 : 0;
        $kategoriTren[] = ['kategori' => $kat, 'persen' => $persen, 'rata' => $rata];
    }
    usort($kategoriTren, fn($a, $b) => $b['persen'] <=> $a['persen']);
} catch (Exception $e) {
    $kategoriTren = [];
}

$kategoriNaikTercepat = array_slice($kategoriTren, 0, 3);
$kategoriTurunTercepat = array_slice(array_reverse($kategoriTren), 0, 3);

$rekomendasi = [];

// Aturan 1: arah tren penjualan
if ($arahTren === 'turun') {
    $rekomendasi[] = [
        'prioritas' => 'Tinggi',
        'area' => 'Penjualan',
        'isi' => 'Tren penjualan bulanan menurun ('
            . number_format($persenPertumbuhanPerBulan, 1) . '%/bulan). Pertimbangkan promosi atau diskon pada kategori utama untuk menahan penurunan sebelum 3 bulan ke depan.',
    ];
} elseif ($arahTren === 'naik') {
    $rekomendasi[] = [
        'prioritas' => 'Sedang',
        'area' => 'Penjualan',
        'isi' => 'Tren penjualan bulanan naik ('
            . number_format($persenPertumbuhanPerBulan, 1) . '%/bulan). Pastikan stok & kapasitas pengiriman siap menghadapi proyeksi kenaikan permintaan.',
    ];
} else {
    $rekomendasi[] = [
        'prioritas' => 'Sedang',
        'area' => 'Penjualan',
        'isi' => 'Tren penjualan relatif stabil. Fokus pada efisiensi operasional sambil menguji strategi pertumbuhan baru (cross-sell/upsell).',
    ];
}

// Aturan 2: kategori dengan tren turun tercepat -> butuh perhatian
foreach ($kategoriTurunTercepat as $kt) {
    if ($kt['persen'] < -2) {
        $rekomendasi[] = [
            'prioritas' => 'Tinggi',
            'area' => 'Produk',
            'isi' => 'Kategori "' . $kt['kategori'] . '" menunjukkan tren menurun ('
                . number_format($kt['persen'], 1) . '%/bulan). Evaluasi harga, kualitas, atau lakukan bundling dengan produk populer.',
        ];
    }
}

// Aturan 3: kategori dengan tren naik tercepat -> peluang ekspansi
foreach ($kategoriNaikTercepat as $kt) {
    if ($kt['persen'] > 2) {
        $rekomendasi[] = [
            'prioritas' => 'Sedang',
            'area' => 'Produk',
            'isi' => 'Kategori "' . $kt['kategori'] . '" tumbuh ('
                . number_format($kt['persen'], 1) . '%/bulan). Tambah variasi produk atau perbesar alokasi marketing pada kategori ini.',
        ];
    }
}

// Aturan 4: review rendah
if ($reviewSummary['jumlah_review'] > 0) {
    $persenReviewRendah = ($reviewSummary['review_rendah'] / $reviewSummary['jumlah_review']) * 100;
    if ($persenReviewRendah > 15) {
        $rekomendasi[] = [
            'prioritas' => 'Tinggi',
            'area' => 'Kepuasan Pelanggan',
            'isi' => number_format($persenReviewRendah, 1) . '% review bernilai rendah (≤2). Audit kualitas produk/layanan pada kategori dengan rating terendah.',
        ];
    }
}

// Aturan 5: durasi pengiriman lama
if (!empty($kotaPengirimanLama)) {
    $kotaTerlama = $kotaPengirimanLama[0];
    if ($kotaTerlama['rata_rata_hari'] > ($pengirimanSummary['rata2'] * 1.3)) {
        $rekomendasi[] = [
            'prioritas' => 'Sedang',
            'area' => 'Logistik',
            'isi' => 'Kota ' . $kotaTerlama['kota'] . ' (' . $kotaTerlama['state'] . ') memiliki rata-rata pengiriman '
                . number_format($kotaTerlama['rata_rata_hari'], 1) . ' hari, jauh di atas rata-rata keseluruhan ('
                . number_format($pengirimanSummary['rata2'], 1) . ' hari). Evaluasi mitra logistik di area tersebut.',
        ];
    }
}

// Aturan 6: konsentrasi metode pembayaran
if (!empty($metodePembayaran)) {
    $totalTransaksiBayar = array_sum(array_column($metodePembayaran, 'jumlah'));
    $metodeUtama = $metodePembayaran[0];
    $persenMetodeUtama = $totalTransaksiBayar > 0 ? ($metodeUtama['jumlah'] / $totalTransaksiBayar) * 100 : 0;
    if ($persenMetodeUtama > 60) {
        $rekomendasi[] = [
            'prioritas' => 'Rendah',
            'area' => 'Pembayaran',
            'isi' => number_format($persenMetodeUtama, 1) . '% transaksi memakai "' . $metodeUtama['metode_pembayaran']
                . '". Diversifikasi metode pembayaran lain agar tidak terlalu bergantung pada satu kanal.',
        ];
    }
}

if (empty($rekomendasi)) {
    $rekomendasi[] = [
        'prioritas' => 'Rendah',
        'area' => 'Umum',
        'isi' => 'Tidak ditemukan anomali signifikan dari data saat ini. Lanjutkan monitoring berkala.',
    ];
}

$prioritasUrutan = ['Tinggi' => 0, 'Sedang' => 1, 'Rendah' => 2];
usort($rekomendasi, fn($a, $b) => $prioritasUrutan[$a['prioritas']] <=> $prioritasUrutan[$b['prioritas']]);

$bulanNama = [1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'Mei',6=>'Jun',7=>'Jul',8=>'Agu',9=>'Sep',10=>'Okt',11=>'Nov',12=>'Des'];

$labelHistoris = array_map(fn($r) => $bulanNama[(int)$r['bulan']] . " '" . substr($r['tahun'], -2), $trenBulanan);
$dataHistoris = array_map(fn($r) => round((float)$r['total_penjualan'], 2), $trenBulanan);

$labelProyeksi = [];
$dataProyeksiSaja = [];
$bulanAcuan = $bulanTerakhir ? (int)$bulanTerakhir['bulan'] : 1;
$tahunAcuan = $bulanTerakhir ? (int)$bulanTerakhir['tahun'] : date('Y');
foreach ($proyeksi as $p) {
    $b = $bulanAcuan + $p['ke'];
    $t = $tahunAcuan;
    while ($b > 12) { $b -= 12; $t++; }
    $labelProyeksi[] = $bulanNama[$b] . " '" . substr($t, -2) . ' (proy.)';
    $dataProyeksiSaja[] = round($p['penjualan'], 2);
}
$chartLabelsGabungan = array_merge($labelHistoris, $labelProyeksi);
// Garis historis: null di area proyeksi. Garis proyeksi: null di area historis, kecuali titik sambung terakhir.
$chartDataHistoris = array_merge($dataHistoris, array_fill(0, count($labelProyeksi), null));
$chartDataProyeksi = array_merge(
    array_fill(0, max(0, count($labelHistoris) - 1), null),
    [end($dataHistoris) ?: null],
    $dataProyeksiSaja
);
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Analisis Data Prediktif & Business Intelligence</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/mobile.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .section-icon { font-size: 1.4rem; }
        .stage-card { border-top: 4px solid #ccc; height: 100%; }
        .stage-card.sekarang { border-top-color: #0d6efd; }
        .stage-card.masa-depan { border-top-color: #6f42c1; }
        .stage-card.aksi { border-top-color: #fd7e14; }
        .mini-table th, .mini-table td { font-size: 0.85rem; }
        .badge-prioritas-tinggi { background: #dc3545; }
        .badge-prioritas-sedang { background: #ffc107; color: #212529; }
        .badge-prioritas-rendah { background: #6c757d; }
        .rekom-card { border-left: 4px solid #dee2e6; }
        .rekom-card.p-tinggi { border-left-color: #dc3545; }
        .rekom-card.p-sedang { border-left-color: #ffc107; }
        .rekom-card.p-rendah { border-left-color: #6c757d; }
        .trend-pill { font-size: .78rem; padding: .25rem .6rem; border-radius: 999px; font-weight: 600; }
        .trend-up { background: #d1f7e6; color: #0f9d68; }
        .trend-down { background: #fde2e1; color: #c0392b; }
        .chart-wrap { min-height: 260px; position: relative; box-sizing: border-box; padding-bottom: 1.2rem; }
        .chart-wrap canvas { width: 100% !important; height: 100% !important; display: block; }
    </style>
</head>
<body class="p-4">
<div class="container-fluid" style="max-width: 1320px;">

    <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1">🧠 Analisis Prediktif DWH</h1>
            <p class="text-muted mb-0">Seluruh angka dihitung langsung dari query SQL ke <code>Database Brazillian</code> — proyeksi memakai regresi linear sederhana</p>
        </div>
        <a href="index.php" class="btn btn-secondary">⬅ Kembali ke Dashboard</a>
    </div>

    <!-- ===================== NAV 3 TAHAP ===================== -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <a href="#sekarang" class="text-decoration-none">
                <div class="card stage-card sekarang">
                    <div class="card-body">
                        <span class="section-icon">⏱️</span>
                        <h6 class="mt-2 mb-1">1. Apa yang Terjadi Saat Ini</h6>
                        <p class="text-muted small mb-0">Kondisi penjualan, produk, review, &amp; pengiriman terkini.</p>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-4">
            <a href="#masa-depan" class="text-decoration-none">
                <div class="card stage-card masa-depan">
                    <div class="card-body">
                        <span class="section-icon">🔮</span>
                        <h6 class="mt-2 mb-1">2. Apa yang Akan Terjadi</h6>
                        <p class="text-muted small mb-0">Proyeksi 3 bulan ke depan berdasarkan tren historis.</p>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-4">
            <a href="#aksi" class="text-decoration-none">
                <div class="card stage-card aksi">
                    <div class="card-body">
                        <span class="section-icon">✅</span>
                        <h6 class="mt-2 mb-1">3. Apa yang Harus Dilakukan</h6>
                        <p class="text-muted small mb-0">Rekomendasi aksi berbasis aturan dari hasil analisis.</p>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <!-- ===================== BAGIAN 1: SAAT INI ===================== -->
    <h4 id="sekarang" class="mb-3">📍 1. Apa yang Terjadi Saat Ini</h4>

    <div class="row g-3 mb-3">
        <div class="col-md-3 col-6">
            <div class="card h-100"><div class="card-body">
                <div class="text-muted small text-uppercase">Total Bulan Tercatat</div>
                <div class="fs-3 fw-bold"><?php echo count($trenBulanan); ?></div>
            </div></div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card h-100"><div class="card-body">
                <div class="text-muted small text-uppercase">Rata-rata Skor Review</div>
                <div class="fs-3 fw-bold"><?php echo number_format($reviewSummary['rata2'] ?? 0, 2); ?> / 5</div>
            </div></div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card h-100"><div class="card-body">
                <div class="text-muted small text-uppercase">Rata-rata Durasi Kirim</div>
                <div class="fs-3 fw-bold"><?php echo number_format($pengirimanSummary['rata2'] ?? 0, 1); ?> hari</div>
            </div></div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card h-100"><div class="card-body">
                <div class="text-muted small text-uppercase">Kategori Terlaris</div>
                <div class="fs-5 fw-bold"><?php echo !empty($kategoriTerlaris) ? htmlspecialchars($kategoriTerlaris[0]['kategori']) : '-'; ?></div>
            </div></div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-body">
                    <h6 class="mb-3">🏆 Top 5 Kategori Penjualan</h6>
                    <div class="table-responsive">
                    <table class="table table-sm mini-table mb-0">
                        <thead><tr class="text-muted"><th>Kategori</th><th class="text-end">Total</th><th class="text-end">Qty</th></tr></thead>
                        <tbody>
                        <?php foreach ($kategoriTerlaris as $k): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($k['kategori'] ?? '-'); ?></td>
                                <td class="text-end">R$ <?php echo number_format((float)$k['total'], 0, ',', '.'); ?></td>
                                <td class="text-end"><?php echo number_format((int)$k['qty']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($kategoriTerlaris)): ?>
                            <tr><td colspan="3" class="text-muted text-center">Data tidak tersedia</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-body">
                    <h6 class="mb-3">⚠️ 5 Kategori Penjualan Terlemah</h6>
                    <div class="table-responsive">
                    <table class="table table-sm mini-table mb-0">
                        <thead><tr class="text-muted"><th>Kategori</th><th class="text-end">Total</th><th class="text-end">Qty</th></tr></thead>
                        <tbody>
                        <?php foreach ($kategoriTerlemah as $k): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($k['kategori'] ?? '-'); ?></td>
                                <td class="text-end">R$ <?php echo number_format((float)$k['total'], 0, ',', '.'); ?></td>
                                <td class="text-end"><?php echo number_format((int)$k['qty']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($kategoriTerlemah)): ?>
                            <tr><td colspan="3" class="text-muted text-center">Data tidak tersedia</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-body">
                    <h6 class="mb-3">🚚 5 Kota dengan Pengiriman Terlama</h6>
                    <div class="table-responsive">
                    <table class="table table-sm mini-table mb-0">
                        <thead><tr class="text-muted"><th>Kota</th><th>State</th><th class="text-end">Rata-rata Hari</th></tr></thead>
                        <tbody>
                        <?php foreach ($kotaPengirimanLama as $kt): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($kt['kota'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($kt['state'] ?? '-'); ?></td>
                                <td class="text-end"><?php echo number_format((float)$kt['rata_rata_hari'], 1); ?> hari</td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($kotaPengirimanLama)): ?>
                            <tr><td colspan="3" class="text-muted text-center">Data tidak tersedia</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-body">
                    <h6 class="mb-3">💳 Distribusi Metode Pembayaran</h6>
                    <div class="table-responsive">
                    <table class="table table-sm mini-table mb-0">
                        <thead><tr class="text-muted"><th>Metode</th><th class="text-end">Jumlah</th><th class="text-end">Total</th></tr></thead>
                        <tbody>
                        <?php foreach ($metodePembayaran as $m): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($m['metode_pembayaran'] ?? '-'); ?></td>
                                <td class="text-end"><?php echo number_format((int)$m['jumlah']); ?></td>
                                <td class="text-end">R$ <?php echo number_format((float)$m['total'], 0, ',', '.'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($metodePembayaran)): ?>
                            <tr><td colspan="3" class="text-muted text-center">Data tidak tersedia</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ===================== BAGIAN 2: MASA DEPAN ===================== -->
    <h4 id="masa-depan" class="mb-3">🔮 2. Apa yang Akan Terjadi (Proyeksi 3 Bulan)</h4>

    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                <h6 class="mb-0">Proyeksi Tren Penjualan</h6>
                <span class="trend-pill <?php echo $arahTren === 'turun' ? 'trend-down' : 'trend-up'; ?>">
                    <?php echo $arahTren === 'naik' ? '▲' : ($arahTren === 'turun' ? '▼' : '■'); ?>
                    <?php echo number_format($persenPertumbuhanPerBulan, 1); ?>% per bulan (<?php echo ucfirst($arahTren); ?>)
                </span>
            </div>
            <p class="text-muted small mb-3">Dihitung dengan regresi linear (least squares) dari <?php echo count($trenBulanan); ?> titik data bulanan historis. Garis putus-putus = proyeksi.</p>
            <div class="chart-wrap" style="min-height: 300px;">
                <canvas id="proyeksiChart"></canvas>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <?php foreach ($proyeksi as $p): ?>
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <div class="text-muted small text-uppercase">Bulan ke-<?php echo $p['ke']; ?></div>
                        <div class="fs-4 fw-bold text-primary"><R1></R1> <?php echo number_format($p['penjualan'], 0, ',', '.'); ?></div>
                        <div class="text-muted small"><?php echo number_format($p['transaksi']); ?> transaksi (estimasi)</div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-body">
                    <h6 class="mb-3">📈 Kategori dengan Tren Naik Tercepat</h6>
                    <?php foreach ($kategoriNaikTercepat as $kt): ?>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span><?php echo htmlspecialchars($kt['kategori']); ?></span>
                            <span class="trend-pill trend-up">▲ <?php echo number_format($kt['persen'], 1); ?>%/bln</span>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($kategoriNaikTercepat)): ?>
                        <p class="text-muted small mb-0">Belum cukup data historis untuk menghitung tren.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-body">
                    <h6 class="mb-3">📉 Kategori dengan Tren Turun Tercepat</h6>
                    <?php foreach ($kategoriTurunTercepat as $kt): ?>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span><?php echo htmlspecialchars($kt['kategori']); ?></span>
                            <span class="trend-pill trend-down">▼ <?php echo number_format($kt['persen'], 1); ?>%/bln</span>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($kategoriTurunTercepat)): ?>
                        <p class="text-muted small mb-0">Belum cukup data historis untuk menghitung tren.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ===================== BAGIAN 3: AKSI ===================== -->
    <h4 id="aksi" class="mb-3">✅ 3. Apa yang Harus Dilakukan</h4>

    <div class="mb-4">
        <?php foreach ($rekomendasi as $r): ?>
            <?php
                $kelasPrioritas = $r['prioritas'] === 'Tinggi' ? 'p-tinggi' : ($r['prioritas'] === 'Sedang' ? 'p-sedang' : 'p-rendah');
                $badgeKelas = $r['prioritas'] === 'Tinggi' ? 'badge-prioritas-tinggi' : ($r['prioritas'] === 'Sedang' ? 'badge-prioritas-sedang' : 'badge-prioritas-rendah');
            ?>
            <div class="card mb-2 rekom-card <?php echo $kelasPrioritas; ?>">
                <div class="card-body py-2">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div>
                            <span class="badge <?php echo $badgeKelas; ?> me-2"><?php echo $r['prioritas']; ?></span>
                            <span class="text-muted small me-2">[<?php echo htmlspecialchars($r['area']); ?>]</span>
                            <span><?php echo htmlspecialchars($r['isi']); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="alert alert-secondary small">
        <strong>Metodologi:</strong> Bagian 1 &amp; 2 murni hasil query SQL ke tabel <code>fakta_penjualan</code>, <code>fakta_review</code>, <code>fakta_pengiriman</code>, dan <code>fakta_pembayaran</code> beserta dimensinya.
        Proyeksi pada Bagian 2 memakai rumus regresi linear (least squares) yang dihitung manual di PHP — tanpa library machine learning.
        Rekomendasi pada Bagian 3 dihasilkan dari aturan ambang batas (rule-based) terhadap angka-angka tersebut, bukan model AI.
    </div>

</div>

<script>
const ctx2 = document.getElementById('proyeksiChart');
new Chart(ctx2, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($chartLabelsGabungan); ?>,
        datasets: [
            {
                label: 'Penjualan Historis',
                data: <?php echo json_encode($chartDataHistoris); ?>,
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13,110,253,0.08)',
                fill: true,
                tension: 0.25,
                spanGaps: false,
            },
            {
                label: 'Proyeksi',
                data: <?php echo json_encode($chartDataProyeksi); ?>,
                borderColor: '#6f42c1',
                borderDash: [6,5],
                backgroundColor: 'rgba(111,66,193,0.08)',
                fill: true,
                tension: 0.25,
                spanGaps: true,
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
                        if (context.parsed.y === null) return null;
                        return context.dataset.label + ': Rp ' + context.parsed.y.toLocaleString('id-ID');
                    }
                }
            }
        },
        scales: {
            y: { beginAtZero: true, ticks: { callback: (v) => 'Rp ' + v.toLocaleString('id-ID') } }
        }
    }
});
</script>
</body>
</html>
