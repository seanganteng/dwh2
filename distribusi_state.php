<?php
require_once __DIR__ . '/inc/db.php';

// Inisialisasi koneksi database
$pdo = getPDO();

// Tipe data peta: pelanggan atau seller
$tipe = isset($_GET['tipe']) && $_GET['tipe'] === 'seller' ? 'seller' : 'pelanggan';

// Ambil parameter filter dari URL
$selectedYear = isset($_GET['year']) && $_GET['year'] !== '' ? (int)$_GET['year'] : null;
$selectedMonth = isset($_GET['month']) && $_GET['month'] !== '' ? (int)$_GET['month'] : null;
$selectedCategory = isset($_GET['category']) && $_GET['category'] !== '' ? $_GET['category'] : null;
$selectedState = isset($_GET['state']) && $_GET['state'] !== '' ? $_GET['state'] : null;
$selectedPayment = isset($_GET['payment']) && $_GET['payment'] !== '' ? $_GET['payment'] : null;

// Gunakan tabel dim_seller jika tersedia, jika tidak gunakan dim_penjual
$sellerTable = null;
try {
    $tbl = $pdo->prepare("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_name IN ('dim_seller','dim_penjual') LIMIT 1");
    $tbl->execute();
    $found = $tbl->fetchColumn();
    if ($found) $sellerTable = $found;
} catch (Exception $e) {
    // Abaikan error dan gunakan nama tabel default jika tidak tersedia
}

// Peta koordinat negara bagian Brazil untuk visualisasi marker
$stateCoords = [
    'SP' => ['lat' => -23.5505, 'lng' => -46.6333, 'nama' => 'São Paulo'],
    'RJ' => ['lat' => -22.9068, 'lng' => -43.1729, 'nama' => 'Rio de Janeiro'],
    'MG' => ['lat' => -19.9167, 'lng' => -43.9345, 'nama' => 'Minas Gerais'],
    'RS' => ['lat' => -30.0346, 'lng' => -51.2177, 'nama' => 'Rio Grande do Sul'],
    'PR' => ['lat' => -25.4284, 'lng' => -49.2733, 'nama' => 'Paraná'],
    'SC' => ['lat' => -27.5954, 'lng' => -48.5480, 'nama' => 'Santa Catarina'],
    'BA' => ['lat' => -12.9714, 'lng' => -38.5014, 'nama' => 'Bahia'],
    'DF' => ['lat' => -15.7801, 'lng' => -47.9292, 'nama' => 'Distrito Federal'],
    'GO' => ['lat' => -16.6869, 'lng' => -49.2648, 'nama' => 'Goiás'],
    'PE' => ['lat' => -8.0476,  'lng' => -34.8770, 'nama' => 'Pernambuco'],
    'CE' => ['lat' => -3.7172,  'lng' => -38.5433, 'nama' => 'Ceará'],
    'PA' => ['lat' => -1.4558,  'lng' => -48.4902, 'nama' => 'Pará'],
    'MA' => ['lat' => -2.5307,  'lng' => -44.3068, 'nama' => 'Maranhão'],
    'ES' => ['lat' => -20.3155, 'lng' => -40.3088, 'nama' => 'Espírito Santo'],
    'MT' => ['lat' => -15.6014, 'lng' => -56.0979, 'nama' => 'Mato Grosso'],
    'MS' => ['lat' => -20.4483, 'lng' => -54.6326, 'nama' => 'Mato Grosso do Sul'],
    'AM' => ['lat' => -3.1190,  'lng' => -60.0217, 'nama' => 'Amazonas'],
    'RN' => ['lat' => -5.7945,  'lng' => -35.2110, 'nama' => 'Rio Grande do Norte'],
    'PB' => ['lat' => -7.1195,  'lng' => -36.8235, 'nama' => 'Paraíba'],
    'AL' => ['lat' => -9.5713,  'lng' => -36.7820, 'nama' => 'Alagoas'],
    'SE' => ['lat' => -10.9472, 'lng' => -37.0731, 'nama' => 'Sergipe'],
    'PI' => ['lat' => -5.0920,  'lng' => -42.8038, 'nama' => 'Piauí'],
    'RO' => ['lat' => -8.7612,  'lng' => -63.9020, 'nama' => 'Rondônia'],
    'TO' => ['lat' => -10.1753, 'lng' => -48.2982, 'nama' => 'Tocantins'],
    'AC' => ['lat' => -9.9740,  'lng' => -67.8076, 'nama' => 'Acre'],
    'AP' => ['lat' => 0.9020,   'lng' => -52.0030, 'nama' => 'Amapá'],
    'RR' => ['lat' => 2.8235,   'lng' => -60.6758, 'nama' => 'Roraima'],
];

$data = [];
$max = 0;

try {
    // Cek apakah ada filter waktu (tahun atau bulan)
    $hasTimeFilter = ($selectedYear !== null || $selectedMonth !== null);
    
    if (!$hasTimeFilter) {
        // ============================================================
        // TIDAK ADA FILTER WAKTU -> Ambil dari tabel dimensi
        // ============================================================
        if ($tipe === 'seller') {
            if ($sellerTable) {
                $sql = "SELECT state, COUNT(*) AS jumlah FROM " . $sellerTable . " WHERE state IS NOT NULL AND state != '' GROUP BY state ORDER BY jumlah DESC";
            } else {
                $sql = "SELECT state, COUNT(*) AS jumlah FROM dim_penjual WHERE state IS NOT NULL AND state != '' GROUP BY state ORDER BY jumlah DESC";
            }
        } else {
            $sql = "SELECT state, COUNT(*) AS jumlah FROM dim_pelanggan WHERE state IS NOT NULL AND state != '' GROUP BY state ORDER BY jumlah DESC";
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } else {
        // ============================================================
        // ADA FILTER WAKTU -> Hitung dari fakta_penjualan
        // ============================================================
        $where = [];
        $params = [];
        
        if ($tipe === 'seller') {
            // Untuk seller, gunakan dim_seller
            $sellerTableName = $sellerTable ?: 'dim_penjual';
            $sql = "SELECT ds.state, COUNT(DISTINCT fp.pelanggan_key) AS jumlah
                FROM fakta_penjualan fp
                JOIN dim_waktu w ON fp.waktu_id = w.waktu_id
                JOIN dim_produk dp ON fp.produk_key = dp.produk_key
                JOIN " . $sellerTableName . " ds ON fp.seller_key = ds.seller_key
                WHERE ds.state IS NOT NULL AND ds.state != ''";
        } else {
            // Untuk pelanggan
            $sql = "SELECT dc.state, COUNT(DISTINCT fp.pelanggan_key) AS jumlah
                FROM fakta_penjualan fp
                JOIN dim_waktu w ON fp.waktu_id = w.waktu_id
                JOIN dim_produk dp ON fp.produk_key = dp.produk_key
                JOIN dim_pelanggan dc ON fp.pelanggan_key = dc.pelanggan_key
                WHERE dc.state IS NOT NULL AND dc.state != ''";
        }
        
        // Tambahkan JOIN ke metode pembayaran jika ada filter payment
        if ($selectedPayment) {
            $sql .= " JOIN dim_metode_pembayaran mp ON fp.metode_key = mp.metode_key";
        }
        
        // Filter tahun
        if ($selectedYear) {
            $where[] = 'w.tahun = :year';
            $params[':year'] = $selectedYear;
        }
        
        // Filter bulan
        if ($selectedMonth) {
            $where[] = 'w.bulan = :month';
            $params[':month'] = $selectedMonth;
        }
        
        // Filter kategori produk
        if ($selectedCategory) {
            $where[] = 'dp.kategori_inggris = :category';
            $params[':category'] = $selectedCategory;
        }
        
        // Filter state pelanggan (jika memilih state tertentu)
        if ($selectedState) {
            if ($tipe === 'seller') {
                $where[] = 'ds.state = :state';
            } else {
                $where[] = 'dc.state = :state';
            }
            $params[':state'] = $selectedState;
        }
        
        // Filter metode pembayaran
        if ($selectedPayment) {
            $where[] = 'mp.metode_pembayaran = :payment';
            $params[':payment'] = $selectedPayment;
        }
        
        // Tambahkan WHERE jika ada filter
        if (!empty($where)) {
            $sql .= ' AND ' . implode(' AND ', $where);
        }
        
        // GROUP BY state
        if ($tipe === 'seller') {
            $sql .= ' GROUP BY ds.state ORDER BY jumlah DESC';
        } else {
            $sql .= ' GROUP BY dc.state ORDER BY jumlah DESC';
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Proses hasil query
    foreach ($rows as $row) {
        $key = trim($row['state']);
        $jumlah = (int) $row['jumlah'];
        if (!isset($stateCoords[$key])) {
            continue;
        }
        if ($jumlah > $max) {
            $max = $jumlah;
        }
        $coords = $stateCoords[$key];
        $data[] = [
            'state' => $key,
            'nama' => $coords['nama'],
            'jumlah' => $jumlah,
            'lat' => $coords['lat'],
            'lng' => $coords['lng'],
        ];
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}

header('Content-Type: application/json');
echo json_encode(['tipe' => $tipe, 'data' => $data, 'max' => $max]);