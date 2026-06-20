<div align="center">

<!-- Banner / Hero -->
<img src="https://capsule-render.vercel.app/api?type=waving&color=0:1a7f3c,50:009c3b,100:FFDF00&height=200&section=header&text=Brazilian%20E-Commerce%20DW&fontSize=38&fontColor=ffffff&fontAlignY=38&desc=Data%20Warehouse%20·%20Olist%20Dataset&descAlignY=58&descSize=16" width="100%"/>

<br/>

<!-- Badges -->
![PHP](https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white)
![CSS3](https://img.shields.io/badge/CSS3-1572B6?style=for-the-badge&logo=css3&logoColor=white)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-316192?style=for-the-badge&logo=postgresql&logoColor=white)
![Status](https://img.shields.io/badge/Status-Active-009c3b?style=for-the-badge)
![License](https://img.shields.io/badge/License-MIT-FFDF00?style=for-the-badge)

<br/>

# 🇧🇷 Brazilian E-Commerce Data Warehouse
### *Kelompok 4 — Analisis Data Toko E-Commerce Brasil (Olist)*

</div>

---

## 📌 Tentang Proyek

> **Data Warehouse** berbasis dataset **Olist Brazilian E-Commerce** — platform marketplace terbesar di Brasil yang menghubungkan merchant kecil dengan berbagai channel penjualan. Proyek ini membangun sistem data warehouse menggunakan **Star Schema** dengan PostgreSQL dan ekstensi **dblink** untuk menganalisis tren penjualan, perilaku pelanggan, performa produk, dan logistik pengiriman.

Dataset mencakup **100.000+ order** dari tahun 2016–2018 dengan informasi lengkap mulai dari status pesanan, harga, pembayaran, ulasan pelanggan, hingga geolokasi.

---

## 👥 Tim Pengembang

<div align="center">

| No | Nama | NIM | Peran |
|:--:|------|-----|-------|
| 1 | **Destian Junaidi** | 20241000003 | 🛠️ Database Architect |
| 2 | **M. Rasya Harjanto L** | 2024100068 | 📊 ETL Engineer |
| 3 | **Seanmichael Ferdian** | 2024100028 | 🎨 Frontend Developer |
| 4 | **Khanti Sudhanta Yaputra** | 2024100005 | 📈 Data Analyst |

</div>

---

## 🗂️ Struktur Dataset (Olist)

Dataset terdiri dari beberapa tabel yang saling berelasi:

```
olist_dataset/
├── 📦 orders.csv                              # Data pesanan utama
├── 👤 customers.csv                           # Data pelanggan
├── 🏪 sellers.csv                             # Data penjual
├── 🛍️  products.csv                            # Data produk
├── 📋 order_items.csv                         # Item dalam pesanan
├── 💳 order_payments.csv                      # Data pembayaran
├── ⭐ order_reviews.csv                       # Ulasan pelanggan
├── 🌍 geolocation.csv                         # Data geolokasi
└── 🏷️  product_category_name_translation.csv  # Terjemahan kategori
```

---

## 🏗️ Arsitektur Data Warehouse

```
┌─────────────────────────────────────────────────────────┐
│                    DATA SOURCES                          │
│     DB: brazilian_ecommerce (PostgreSQL via dblink)      │
│   orders · customers · sellers · products · payments    │
└────────────────────────┬────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────┐
│                   ETL PROCESS                            │
│         Extract (dblink) → Transform → Load             │
│              PHP Scripts + Data Cleansing               │
└────────────────────────┬────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────┐
│           DATA WAREHOUSE (PostgreSQL + dblink)           │
│                                                         │
│  DIMENSION TABLES          FACT TABLES                  │
│  ─────────────────         ────────────────────         │
│  dim_waktu                 fakta_penjualan              │
│  dim_produk                fakta_pengiriman             │
│  dim_pelanggan             fakta_pembayaran             │
│  dim_seller                fakta_review                 │
│  dim_metode_pembayaran     fakta_seller                 │
└────────────────────────┬────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────┐
│              PRESENTATION LAYER                          │
│            PHP + CSS Dashboard                           │
│   📊 Charts · 📋 Tables · 🗺️ Maps · 📈 Reports          │
└─────────────────────────────────────────────────────────┘
```

---

## ⭐ Star Schema

```
                        ┌─────────────────────┐
                        │     dim_waktu        │
                        │─────────────────────│
                        │ waktu_id (PK) SERIAL │
                        │ tanggal DATE         │
                        │ hari INT             │
                        │ bulan INT            │
                        │ tahun INT            │
                        └──────────┬──────────┘
                                   │
          ┌──────────────┐         │         ┌──────────────────────┐
          │ dim_pelanggan│         │         │     dim_produk       │
          │──────────────│         │         │──────────────────────│
          │pelanggan_key ├─────────┤         │ produk_key (PK)      │
          │pelanggan_id  │         │         │ produk_id            │
          │kota          │    ┌────┴──────┐  │ kategori             │
          │state         │    │  FAKTA *  │  │ kategori_inggris     │
          └──────────────┘    │───────────│  │ panjang              │
                              │ waktu_id  │  │ berat                │
          ┌──────────────┐    │pelanggan  ├──┘──────────────────────┘
          │  dim_seller  │    │seller_key │
          │──────────────│    │produk_key │  ┌────────────────────────┐
          │ seller_key   ├────│metode_key │  │  dim_metode_pembayaran │
          │ seller_id    │    │ measures  │  │────────────────────────│
          │ kota         │    └───────────┘  │ metode_key (PK)        │
          │ state        │                   │ metode_pembayaran      │
          └──────────────┘                   └────────────────────────┘

  * fakta: fakta_penjualan · fakta_pengiriman · fakta_pembayaran
           fakta_review · fakta_seller
```

---

## 📐 Skema Database Lengkap

### Dimension Tables

<details>
<summary><b>📅 dim_waktu</b></summary>

```sql
CREATE TABLE dim_waktu (
    waktu_id SERIAL PRIMARY KEY,
    tanggal  DATE,
    hari     INT,
    bulan    INT,
    tahun    INT
);
```
</details>

<details>
<summary><b>🛍️ dim_produk</b></summary>

```sql
CREATE TABLE dim_produk (
    produk_key       SERIAL PRIMARY KEY,
    produk_id        VARCHAR,
    kategori         VARCHAR,
    kategori_inggris VARCHAR,
    panjang          INT,
    berat            INT
);
```
</details>

<details>
<summary><b>👤 dim_pelanggan</b></summary>

```sql
CREATE TABLE dim_pelanggan (
    pelanggan_key SERIAL PRIMARY KEY,
    pelanggan_id  VARCHAR,
    kota          VARCHAR,
    state         VARCHAR
);
```
</details>

<details>
<summary><b>🏪 dim_seller</b></summary>

```sql
CREATE TABLE dim_seller (
    seller_key SERIAL PRIMARY KEY,
    seller_id  VARCHAR,
    kota       VARCHAR,
    state      VARCHAR
);
```
</details>

<details>
<summary><b>💳 dim_metode_pembayaran</b></summary>

```sql
CREATE TABLE dim_metode_pembayaran (
    metode_key        SERIAL PRIMARY KEY,
    metode_pembayaran VARCHAR(50) UNIQUE
);
```
</details>

### Fact Tables

<details>
<summary><b>📦 fakta_penjualan</b></summary>

```sql
CREATE TABLE fakta_penjualan (
    id             SERIAL PRIMARY KEY,
    waktu_id       INT REFERENCES dim_waktu(waktu_id),
    produk_key     INT REFERENCES dim_produk(produk_key),
    pelanggan_key  INT REFERENCES dim_pelanggan(pelanggan_key),
    seller_key     INT REFERENCES dim_seller(seller_key),
    jumlah         INT,
    total_harga    NUMERIC
);
```
</details>

<details>
<summary><b>🚚 fakta_pengiriman</b></summary>

```sql
CREATE TABLE fakta_pengiriman (
    id                 SERIAL PRIMARY KEY,
    waktu_id           INT REFERENCES dim_waktu(waktu_id),
    pelanggan_key      INT REFERENCES dim_pelanggan(pelanggan_key),
    seller_key         INT REFERENCES dim_seller(seller_key),
    produk_key         INT REFERENCES dim_produk(produk_key),
    durasi_pengiriman  INT
);
```
</details>

<details>
<summary><b>💰 fakta_pembayaran</b></summary>

```sql
CREATE TABLE fakta_pembayaran (
    pembayaran_key SERIAL PRIMARY KEY,
    waktu_id       INT REFERENCES dim_waktu(waktu_id),
    pelanggan_key  INT REFERENCES dim_pelanggan(pelanggan_key),
    metode_key     INT REFERENCES dim_metode_pembayaran(metode_key),
    total_bayar    NUMERIC(12,2)
);
```
</details>

<details>
<summary><b>⭐ fakta_review</b></summary>

```sql
CREATE TABLE fakta_review (
    id            SERIAL PRIMARY KEY,
    waktu_id      INT REFERENCES dim_waktu(waktu_id),
    pelanggan_key INT REFERENCES dim_pelanggan(pelanggan_key),
    produk_key    INT REFERENCES dim_produk(produk_key),
    skor_review   INT
);
```
</details>

<details>
<summary><b>🏪 fakta_seller</b></summary>

```sql
CREATE TABLE fakta_seller (
    id             SERIAL PRIMARY KEY,
    waktu_id       INT REFERENCES dim_waktu(waktu_id),
    seller_key     INT REFERENCES dim_seller(seller_key),
    produk_key     INT REFERENCES dim_produk(produk_key),
    jumlah_terjual INT
);
```
</details>

---

## 🚀 Cara Menjalankan Proyek

### Prasyarat

Pastikan sudah terinstall:
- ✅ PHP >= 7.4 (dengan ekstensi `php-pgsql`)
- ✅ PostgreSQL >= 13
- ✅ Apache / XAMPP / WAMP
- ✅ Extension `dblink` untuk PostgreSQL

### Langkah Instalasi

**1. Clone Repository**
```bash
git clone https://github.com/kelompok4/brazilian-ecommerce-dw.git
cd brazilian-ecommerce-dw
```

**2. Siapkan Database Sumber**
```bash
# Buat database sumber (data mentah Olist)
psql -U postgres -c "CREATE DATABASE brazilian_ecommerce;"

# Import data mentah CSV ke database sumber
psql -U postgres -d brazilian_ecommerce -f database/staging.sql
```

**3. Buat Database Data Warehouse**
```bash
# Buat database data warehouse
psql -U postgres -c "CREATE DATABASE olist_dw;"

# Aktifkan extension dblink
psql -U postgres -d olist_dw -c "CREATE EXTENSION IF NOT EXISTS dblink;"

# Buat skema star schema
psql -U postgres -d olist_dw -f database/schema.sql
```

**4. Jalankan Proses ETL**
```bash
# Load semua dimension & fact table via dblink
psql -U postgres -d olist_dw -f database/etl_dimensions.sql
psql -U postgres -d olist_dw -f database/etl_facts.sql
```

**5. Konfigurasi Koneksi PHP**
```php
// config/database.php
<?php
define('DB_HOST', 'localhost');
define('DB_PORT', '5432');
define('DB_USER', 'postgres');
define('DB_PASS', 'root');
define('DB_NAME', 'olist_dw');

$dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME;
$pdo = new PDO($dsn, DB_USER, DB_PASS);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
?>
```

**6. Jalankan Aplikasi**
```bash
# Pindahkan ke folder htdocs (XAMPP)
cp -r . /xampp/htdocs/olist-dw

# Akses melalui browser
http://localhost/olist-dw
```

---

## 📊 Fitur Dashboard

<div align="center">

| Fitur | Tabel Sumber | Deskripsi |
|-------|-------------|-----------|
| 📈 **Sales Overview** | `fakta_penjualan` | Tren penjualan bulanan & tahunan |
| 🚚 **Delivery Analytics** | `fakta_pengiriman` | Rata-rata & distribusi durasi pengiriman |
| 💳 **Payment Insights** | `fakta_pembayaran` | Metode & tren nilai pembayaran |
| ⭐ **Review Analysis** | `fakta_review` | Distribusi skor ulasan per produk & region |
| 🏪 **Seller Performance** | `fakta_seller` | Ranking penjual berdasarkan volume |
| 🗺️ **Geo Visualization** | `dim_pelanggan` / `dim_seller` | Peta persebaran per state |
| 🛍️ **Top Categories** | `dim_produk` | Kategori produk terlaris |

</div>

---

## 🛠️ Teknologi yang Digunakan

<div align="center">

| Layer | Teknologi | Fungsi |
|-------|-----------|--------|
| **Backend** | PHP 8.x | Server-side logic & query |
| **Database** | PostgreSQL 13+ | Data Warehouse (Star Schema) |
| **ETL** | dblink (PostgreSQL) | Extract data antar database |
| **Frontend** | HTML5, CSS3 | Tampilan dashboard |
| **Visualisasi** | Chart.js | Grafik & chart interaktif |
| **Server** | Apache (XAMPP) | Web server lokal |

</div>

---

## 📁 Struktur Folder

```
brazilian-ecommerce-dw/
│
├── 📂 assets/
│   ├── css/                  # Stylesheet utama
│   ├── js/                   # Script Chart.js & interaksi
│   └── img/                  # Gambar & ikon
│
├── 📂 config/
│   └── database.php          # Konfigurasi koneksi PostgreSQL
│
├── 📂 database/
│   ├── staging.sql           # Import data mentah Olist
│   ├── schema.sql            # DDL star schema (dim + fakta)
│   ├── etl_dimensions.sql    # INSERT dimension via dblink
│   └── etl_facts.sql         # INSERT fact tables via dblink
│
├── 📂 pages/
│   ├── dashboard.php         # Halaman utama
│   ├── penjualan.php         # Analisis fakta_penjualan
│   ├── pengiriman.php        # Analisis fakta_pengiriman
│   ├── pembayaran.php        # Analisis fakta_pembayaran
│   ├── review.php            # Analisis fakta_review
│   └── seller.php            # Analisis fakta_seller
│
├── 📂 includes/
│   ├── header.php            # Header global
│   ├── footer.php            # Footer global
│   └── navbar.php            # Navigasi
│
├── index.php                 # Entry point
└── README.md                 # Dokumentasi ini
```

---

## 📈 Contoh Query Analitik

```sql
-- Total Penjualan per Bulan
SELECT
    dw.tahun,
    dw.bulan,
    COUNT(fp.id)       AS total_transaksi,
    SUM(fp.total_harga) AS total_revenue
FROM fakta_penjualan fp
JOIN dim_waktu dw ON fp.waktu_id = dw.waktu_id
GROUP BY dw.tahun, dw.bulan
ORDER BY dw.tahun, dw.bulan;

-- Top 10 Kategori Produk Berdasarkan Revenue
SELECT
    dp.kategori_inggris,
    SUM(fp.jumlah)      AS total_item_terjual,
    SUM(fp.total_harga) AS total_revenue
FROM fakta_penjualan fp
JOIN dim_produk dp ON fp.produk_key = dp.produk_key
GROUP BY dp.kategori_inggris
ORDER BY total_revenue DESC
LIMIT 10;

-- Rata-rata Durasi Pengiriman per State Pelanggan
SELECT
    dc.state,
    ROUND(AVG(fpg.durasi_pengiriman), 1) AS rata_hari_kirim
FROM fakta_pengiriman fpg
JOIN dim_pelanggan dc ON fpg.pelanggan_key = dc.pelanggan_key
GROUP BY dc.state
ORDER BY rata_hari_kirim ASC;

-- Distribusi Metode Pembayaran
SELECT
    dmp.metode_pembayaran,
    COUNT(*)               AS total_transaksi,
    SUM(fp.total_bayar)    AS total_nilai
FROM fakta_pembayaran fp
JOIN dim_metode_pembayaran dmp ON fp.metode_key = dmp.metode_key
GROUP BY dmp.metode_pembayaran
ORDER BY total_transaksi DESC;

-- Rata-rata Skor Review per Kategori Produk
SELECT
    dp.kategori_inggris,
    ROUND(AVG(fr.skor_review), 2) AS avg_review,
    COUNT(fr.id)                  AS total_review
FROM fakta_review fr
JOIN dim_produk dp ON fr.produk_key = dp.produk_key
GROUP BY dp.kategori_inggris
ORDER BY avg_review DESC;
```

---

## 🧪 Insight Utama

- 🏙️ **São Paulo** adalah state dengan volume transaksi tertinggi
- 💳 **Kartu Kredit** menjadi metode pembayaran paling dominan
- 📦 Rata-rata durasi pengiriman berkisar antara **7–12 hari**
- ⭐ Rata-rata skor review pelanggan berada di angka **4.1 / 5.0**
- 📈 Puncak penjualan terjadi di periode **November** (Black Friday)
- 🔗 ETL menggunakan **dblink** langsung antar database PostgreSQL

---

## 🤝 Kontribusi

Proyek ini merupakan tugas akademik **Kelompok 4** — Program Studi Sistem Informasi.

```
Mata Kuliah  : Data Warehouse
Institusi    : [Nama Universitas]
Tahun        : 2024
```

---

## 📄 Lisensi

Proyek ini dibuat untuk keperluan **akademik**. Dataset Olist tersedia secara publik di [Kaggle](https://www.kaggle.com/datasets/olistbr/brazilian-ecommerce) di bawah lisensi **Creative Commons**.

---

<div align="center">

<img src="https://capsule-render.vercel.app/api?type=waving&color=0:FFDF00,50:009c3b,100:1a7f3c&height=120&section=footer" width="100%"/>

**Made with 💚💛 by Kelompok 4 — Brazilian E-Commerce**

*Destian · Rasya · Seanmichael · Khanti*

</div>
