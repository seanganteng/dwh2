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

| No | Nama | NIM |
|:--:|------|-----|
| 1 | **Destian Junaidi** | 2024100003 |
| 2 | **M. Rasya Harjanto L** | 2024100068 |
| 3 | **Seanmichael Ferdian** | 2024100028 |
| 4 | **Khanti Sudhanta Yaputra** | 2024100005 |

</div>

---

## 🏗️ Arsitektur Data Warehouse

```
┌─────────────────────────────────────────────────────────┐
│                    DATA SOURCES                         │
│     DB: brazilian_ecommerce (PostgreSQL via dblink)     │
│   orders · customers · sellers · products · payments    │
└────────────────────────┬────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────┐
│                   ETL PROCESS                           │
│         Extract (dblink) → Transform → Load             │
│              PHP Scripts + Data Cleansing               │
└────────────────────────┬────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────┐
│           DATA WAREHOUSE (PostgreSQL + dblink)          │
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
│              PRESENTATION LAYER                         │
│            PHP + CSS Dashboard                          │
│   📊 Charts · 📋 Tables · 🗺️ Maps · 📈 Reports        │
└─────────────────────────────────────────────────────────┘
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

## 🤝 Kontribusi

Proyek ini merupakan tugas akademik **Kelompok 4** — Program Studi Sistem Informasi.

```
Mata Kuliah  : Data Warehouse
Institusi    : Universitas Buddhi Dharma Tangerang
Tahun        : 2026
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
