# FTrans — Sistem Informasi Penyewaan Mobil Mewah & Premium

FTrans adalah web aplikasi penyewaan kendaraan mewah dan premium berbasis PHP Native. Aplikasi ini dirancang dengan antarmuka modern, responsif, dan kaya fitur, mulai dari sistem booking otomatis, integrasi pembayaran digital, notifikasi real-time, sistem verifikasi email berbasis OTP, hingga ekspor laporan multiformat.

Aplikasi ini dapat diakses secara langsung melalui tautan produksi: **[https://ftrans.xo.je](https://ftrans.xo.je)**

---

## 🚀 Teknologi yang Digunakan

Aplikasi ini dibangun menggunakan arsitektur web modern dengan komponen-komponen berikut:

1. **Backend**: PHP Native (v8.1 atau lebih baru) dengan arsitektur prosedural-struktural terorganisir.
2. **Frontend**: HTML5 (struktur semantik), Vanilla CSS3 (variabel kustom, glassmorphism, flexbox/grid), dan Bootstrap 5 / CoreUI 5.
3. **Interaktivitas & Dinamis**: JavaScript (PWA Service Worker, status polling, Chart.js, visual feedback).
4. **Database**: MySQL / MariaDB dengan API koneksi MySQLi (`mysqli_prepare` untuk keamanan SQL Injection).
5. **Manajemen Dependensi**: Composer untuk mengelola pustaka pihak ketiga.
6. **Version Control**: Git & GitHub (`https://github.com/ofaturu/PemrogramanWeb_1`).
7. **Web Server**: Apache (XAMPP untuk lingkungan pengembangan lokal dan InfinityFree untuk produksi).

---

## ✨ Fitur-Fitur Unggulan (Nilai Tambah)

1. **Autentikasi & Verifikasi OTP**: Pendaftaran akun aman dengan sistem verifikasi kode OTP 6-digit yang dikirim ke email pengguna via SMTP.
2. **Lupa & Reset Kata Sandi**: Pengaturan ulang sandi terproteksi menggunakan OTP kedaluwarsa (15 menit) lewat email.
3. **REST API Sederhana**:
   - `check_status.php`: API JSON untuk melacak status transaksi secara real-time.
   - `get_notifications.php`: API JSON untuk mengambil dan memperbarui notifikasi pengguna secara asinkron.
4. **Notifikasi Email SMTP**: Pengiriman email otomatis untuk tagihan baru (*invoice*), bukti pembayaran sukses (*receipt*), kode verifikasi pendaftaran, dan reset password menggunakan **PHPMailer**.
5. **Dukungan PWA (Progressive Web App)**: Integrasi Service Worker (`sw.js`) dan manifes ikon PWA untuk dukungan mode luring (caching aset statis) dan instalasi aplikasi di handphone.
6. **Dark & Light Mode**: Switcher tema dinamis terintegrasi di dashboard admin dan landing page via `js/color-modes.js` dengan persistensi `localStorage`.
7. **Ekspor Laporan Lengkap**: Ekspor data (Armada, Transaksi, Pengguna, Analisis) ke format **PDF (via mPDF)**, **Excel (via PhpSpreadsheet)**, dan **Word (via PHPWord)**.
8. **QR Code & Barcode Dinamis**: QR Code verifikasi transaksi (scan untuk cek status pembayaran secara instan) dan barcode faktur Code128 yang digenerate dinamis pada ekspor PDF.
9. **Grafik Interaktif**: Visualisasi data analitis pendapatan vs biaya operasional servis bulanan dan merk terpopuler menggunakan **Chart.js** yang responsif terhadap perubahan tema gelap/terang.
10. **Cadangan Basis Data (Database Backup)**: Fitur admin untuk mengekspor keseluruhan struktur dan data tabel MySQL secara instan dalam format `.sql` langsung dari aplikasi.

---

## 📁 Struktur Folder Proyek

```text
ftrans/
├── api/                       # Direktori cadangan untuk endpoints REST API
├── assets/                    # Favicon, ikon PWA, dan aset gambar
│   └── favicon/               # Manifest dan ikon multi-ukuran PWA
├── css/                       # Stylesheets aplikasi
│   ├── landing.css            # Desain kustom landing page (Dark & Glassmorphism)
│   └── style.css              # CoreUI / Bootstrap styling custom
├── js/                        # JavaScript scripts
│   ├── color-modes.js         # Pengelola dark/light mode
│   └── config.js              # Konfigurasi frontend
├── partials/                  # Potongan layout reusable (header, sidebar, footer)
├── uploads/                   # Folder media upload bukti bayar & gambar armada
├── vendor/                    # Dependensi Composer (PHPMailer, mPDF, PhpSpreadsheet, dll)
├── .env                       # Variabel lingkungan (Credentials SMTP, Xendit API, dll)
├── config.php                 # Konfigurasi koneksi database & skema migrasi tabel
├── backup.php                 # Logika backup database (.sql)
├── bayar.php                  # Halaman pembayaran & polling transaksi
├── check_status.php           # API endpoint cek pembayaran
├── export.php                 # Logika ekspor PDF/Excel/Word + QR & Barcode
├── index.php                  # Halaman landing page utama
├── sw.js                      # Service Worker PWA
└── ftrans.sql                 # Dump database MySQL awal
```

---

## 🛠️ Cara Instalasi Lokal (Development)

Ikuti langkah-langkah berikut untuk menjalankan FTrans di komputer lokal Anda:

### Prasyarat
- Komputer dengan sistem operasi Windows.
- **XAMPP** terinstal (memiliki Apache, PHP v8.1 ke atas, dan MySQL/MariaDB).
- **Composer** terinstal secara global.

### Langkah-Langkah

1. **Clone Repositori**:
   Tempatkan folder proyek ke dalam direktori `htdocs` XAMPP Anda (misalnya `C:\xampp\htdocs\PemrogramanWeb_1\ftrans`).
   ```bash
   git clone https://github.com/ofaturu/PemrogramanWeb_1.git
   ```

2. **Instal Dependensi PHP**:
   Buka terminal di dalam folder `ftrans` dan jalankan:
   ```bash
   composer install
   ```

3. **Impor Database**:
   - Aktifkan modul Apache dan MySQL pada XAMPP Control Panel.
   - Buka browser dan pergi ke `http://localhost/phpmyadmin`.
   - Buat database baru bernama `if0_42443860_ftrans` (atau nama kustom Anda).
   - Pilih database tersebut, buka tab **Import**, pilih file `ftrans.sql`, dan klik **Import**.

4. **Konfigurasi Environment (`.env`)**:
   Konfigurasikan file `.env` di direktori root dengan menyesuaikan kredensial Anda:
   ```env
   GOOGLE_CLIENT_ID=your_google_client_id
   GOOGLE_CLIENT_SECRET=your_google_client_secret
   GOOGLE_REDIRECT_URL=http://localhost/PemrogramanWeb_1/ftrans/callback.php
   SMTP_HOST=smtp.gmail.com
   SMTP_USER=email_anda@gmail.com
   SMTP_PASS=app_password_gmail_anda
   SMTP_PORT=465
   XENDIT_SECRET_KEY=xnd_development_...
   XENDIT_CALLBACK_TOKEN=token_callback_anda
   ```

5. **Konfigurasi Database (`config.php`)**:
   Secara default, `config.php` akan membaca variabel lingkungan dari `.env`. Jika variabel database tidak diatur di `.env`, sistem akan menggunakan fallback otomatis ke kredensial produksi. Untuk pengembangan lokal, Anda dapat mengatur `DB_HOST`, `DB_USER`, `DB_PASS`, dan `DB_NAME` di file `.env` Anda sendiri:
   ```env
   DB_HOST=localhost
   DB_USER=root
   DB_PASS=
   DB_NAME=if0_42443860_ftrans
   ```

6. **Jalankan Aplikasi**:
   Buka browser Anda dan akses aplikasi melalui:
   `http://localhost/PemrogramanWeb_1/ftrans/index.php`

---

## 🔒 Hak Akses Akun Demo (Uji Coba)

Untuk menguji fitur panel admin dan pengguna, Anda dapat masuk menggunakan akun demo berikut:

* **Role Administrator**:
  - Email: `fatchurrachman001@gmail.com`
  - Password: `admin` (atau password default yang tertera pada database)
* **Role Member / Pelanggan**:
  - Email: `ofaturu@gmail.com`
  - Password: `user` (atau password terdaftar di database)
