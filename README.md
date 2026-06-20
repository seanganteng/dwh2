<div align="center">

<!-- Banner / Hero -->
<img src="https://capsule-render.vercel.app/api?type=waving&color=0:1a7f3c,50:009c3b,100:FFDF00&height=200&section=header&text=Brazilian%20E-Commerce%20DW&fontSize=38&fontColor=ffffff&fontAlignY=38&desc=Data%20Warehouse%20·%20Olist%20Dataset&descAlignY=58&descSize=16" width="100%"/>

<br/>

<!-- Badges -->
![PHP](https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white)
![CSS3](https://img.shields.io/badge/CSS3-1572B6?style=for-the-badge&logo=css3&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-005C84?style=for-the-badge&logo=mysql&logoColor=white)
![Status](https://img.shields.io/badge/Status-Active-009c3b?style=for-the-badge)
![License](https://img.shields.io/badge/License-MIT-FFDF00?style=for-the-badge)

<br/>

# 🇧🇷 Brazilian E-Commerce Data Warehouse
### *Kelompok 4 — Analisis Data Toko E-Commerce Brasil (Olist)*

</div>

---

## 📌 Tentang Proyek

> **Data Warehouse** berbasis dataset **Olist Brazilian E-Commerce** — platform marketplace terbesar di Brasil yang menghubungkan merchant kecil dengan berbagai channel penjualan. Proyek ini membangun sistem data warehouse untuk menganalisis tren penjualan, perilaku pelanggan, performa produk, dan logistik pengiriman.

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
├── 📦 olist_orders_dataset.csv          # Data pesanan utama
├── 👤 olist_customers_dataset.csv       # Data pelanggan
├── 🏪 olist_sellers_dataset.csv         # Data penjual
├── 🛍️  olist_products_dataset.csv        # Data produk
├── 📋 olist_order_items_dataset.csv     # Item dalam pesanan
├── 💳 olist_order_payments_dataset.csv  # Data pembayaran
├── ⭐ olist_order_reviews_dataset.csv   # Ulasan pelanggan
├── 🌍 olist_geolocation_dataset.csv     # Data geolokasi
└── 🏷️  product_category_name_translation.csv
```

---

## 🏗️ Arsitektur Data Warehouse

```
┌─────────────────────────────────────────────────────────┐
│                    DATA SOURCES                          │
│         CSV Files (Olist Brazilian E-Commerce)          │
└────────────────────────┬────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────┐
│                   ETL PROCESS                            │
│   Extract → Transform → Load                            │
│   (PHP Scripts + Data Cleansing)                        │
└────────────────────────┬────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────┐
│               DATA WAREHOUSE (MySQL)                     │
│  ┌──────────────┐    ┌──────────────────────────────┐   │
│  │  FACT TABLES │    │      DIMENSION TABLES        │   │
│  │              │    │                              │   │
│  │ fact_orders  │◄───│ dim_customers   dim_products │   │
│  │ fact_reviews │    │ dim_sellers     dim_time     │   │
│  │ fact_payments│    │ dim_location    dim_category │   │
│  └──────────────┘    └──────────────────────────────┘   │
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

## ⭐ Skema Star Schema

```sql
                    ┌──────────────┐
                    │  dim_time    │
                    │─────────────-│
                    │ time_id (PK) │
                    │ date         │
                    │ month        │
                    │ quarter      │
                    │ year         │
                    └──────┬───────┘
                           │
┌──────────────┐    ┌──────┴───────┐    ┌──────────────────┐
│ dim_customers│    │  fact_orders │    │   dim_products   │
│──────────────│    │──────────────│    │──────────────────│
│customer_id   ├───►│ order_id(PK) │◄───│ product_id       │
│customer_city │    │ customer_id  │    │ product_name     │
│customer_state│    │ seller_id    │    │ category_name    │
│customer_zip  │    │ product_id   │    │ product_weight   │
└──────────────┘    │ time_id      │    └──────────────────┘
                    │ price        │
┌──────────────┐    │ freight_value│    ┌──────────────────┐
│  dim_sellers │    │ payment_type │    │  dim_location    │
│──────────────│    │ review_score │    │──────────────────│
│ seller_id    ├───►│ order_status │◄───│ zip_code         │
│ seller_city  │    └──────────────┘    │ city             │
│ seller_state │                        │ state            │
└──────────────┘                        │ lat / lng        │
                                        └──────────────────┘
```

---

## 🚀 Cara Menjalankan Proyek

### Prasyarat

Pastikan sudah terinstall:
- ✅ PHP >= 7.4
- ✅ MySQL / MariaDB >= 5.7
- ✅ Apache / XAMPP / WAMP
- ✅ Composer (opsional)

### Langkah Instalasi

**1. Clone Repository**
```bash
git clone https://github.com/kelompok4/brazilian-ecommerce-dw.git
cd brazilian-ecommerce-dw
```

**2. Setup Database**
```bash
# Buat database baru
mysql -u root -p -e "CREATE DATABASE olist_dw;"

# Import skema dan data
mysql -u root -p olist_dw < database/schema.sql
mysql -u root -p olist_dw < database/seed.sql
```

**3. Konfigurasi Koneksi**
```php
// config/database.php
<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'your_password');
define('DB_NAME', 'olist_dw');
?>
```

**4. Proses ETL**
```bash
# Jalankan skrip ETL untuk load data dari CSV
php etl/extract.php
php etl/transform.php
php etl/load.php
```

**5. Jalankan Aplikasi**
```bash
# Pindahkan ke folder htdocs (XAMPP) atau www (WAMP)
cp -r . /xampp/htdocs/olist-dw

# Akses melalui browser
http://localhost/olist-dw
```

---

## 📊 Fitur Dashboard

<div align="center">

| Fitur | Deskripsi |
|-------|-----------|
| 📈 **Sales Overview** | Tren penjualan bulanan & tahunan |
| 🏆 **Top Products** | Produk & kategori terlaris |
| 👥 **Customer Analytics** | Distribusi & perilaku pelanggan |
| 🗺️ **Geo Visualization** | Peta persebaran penjualan per state |
| ⭐ **Review Analysis** | Analisis sentimen & skor ulasan |
| 💳 **Payment Insights** | Metode & tren pembayaran |
| 🚚 **Delivery Performance** | Waktu pengiriman & ketepatan |
| 📦 **Seller Performance** | Ranking & performa merchant |

</div>

---

## 🛠️ Teknologi yang Digunakan

<div align="center">

| Layer | Teknologi | Fungsi |
|-------|-----------|--------|
| **Backend** | PHP 8.x | Server-side logic & ETL |
| **Database** | MySQL | Data Warehouse storage |
| **Frontend** | CSS3, HTML5 | Tampilan dashboard |
| **Visualisasi** | Chart.js / D3.js | Grafik interaktif |
| **Server** | Apache (XAMPP) | Web server lokal |

</div>

---

## 📁 Struktur Folder

```
brazilian-ecommerce-dw/
│
├── 📂 assets/
│   ├── css/           # Stylesheet utama
│   ├── js/            # Script visualisasi
│   └── img/           # Gambar & ikon
│
├── 📂 config/
│   └── database.php   # Konfigurasi koneksi DB
│
├── 📂 database/
│   ├── schema.sql     # DDL skema DW
│   └── seed.sql       # Sample data
│
├── 📂 etl/
│   ├── extract.php    # Ekstrak dari CSV
│   ├── transform.php  # Transformasi & cleansing
│   └── load.php       # Load ke data warehouse
│
├── 📂 pages/
│   ├── dashboard.php  # Halaman utama
│   ├── sales.php      # Analisis penjualan
│   ├── customers.php  # Analisis pelanggan
│   ├── products.php   # Analisis produk
│   └── reports.php    # Laporan lengkap
│
├── 📂 includes/
│   ├── header.php     # Header global
│   ├── footer.php     # Footer global
│   └── navbar.php     # Navigasi
│
├── index.php          # Entry point
└── README.md          # Dokumentasi ini
```

---

## 📈 Contoh Query Analitik

```sql
-- Total Penjualan per Bulan
SELECT 
    dt.year, dt.month,
    COUNT(fo.order_id) AS total_orders,
    SUM(fo.price) AS total_revenue,
    AVG(fo.review_score) AS avg_review
FROM fact_orders fo
JOIN dim_time dt ON fo.time_id = dt.time_id
WHERE fo.order_status = 'delivered'
GROUP BY dt.year, dt.month
ORDER BY dt.year, dt.month;

-- Top 10 Kategori Produk Berdasarkan Revenue
SELECT 
    dp.category_name,
    COUNT(fo.order_id) AS total_orders,
    SUM(fo.price) AS total_revenue
FROM fact_orders fo
JOIN dim_products dp ON fo.product_id = dp.product_id
GROUP BY dp.category_name
ORDER BY total_revenue DESC
LIMIT 10;

-- Distribusi Rating Ulasan per State
SELECT 
    dl.state,
    AVG(fo.review_score) AS avg_score,
    COUNT(*) AS total_reviews
FROM fact_orders fo
JOIN dim_location dl ON fo.zip_code = dl.zip_code
GROUP BY dl.state
ORDER BY avg_score DESC;
```

---

## 🧪 Insight Utama

- 🏙️ **São Paulo** adalah state dengan volume transaksi tertinggi
- 💳 **Kartu Kredit** menjadi metode pembayaran yang paling dominan
- 📦 Rata-rata waktu pengiriman berkisar antara **7–12 hari kerja**
- ⭐ Rata-rata skor ulasan pelanggan berada di angka **4.1 / 5.0**
- 📈 Puncak penjualan terjadi pada periode **November** (Black Friday)

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
