<?php
require_once 'config.php';

// Ambil pengaturan halaman landing page
$settings = [];
$res_settings = mysqli_query($mysqli, "SELECT setting_key, setting_value FROM landing_settings");
if ($res_settings) {
    while ($row = mysqli_fetch_assoc($res_settings)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

// Nilai cadangan (fallback) jika tidak ditemukan di database
$landing_hero_title = $settings['hero_title'] ?? 'Eksplorasi Perjalanan Kelas Dunia Bersama Kami.';
$landing_hero_subtitle = $settings['hero_subtitle'] ?? 'Nikmati kenyamanan berkendara terbaik dengan armada mobil mewah dan pelayanan VIP yang dirancang khusus untuk memenuhi standar eksklusivitas Anda.';
$landing_hero_image_raw = $settings['hero_image'] ?? 'https://images.unsplash.com/photo-1614162692292-7ac56d7f7f1e?auto=format&fit=crop&w=1000&q=80';
$landing_hero_image = (strpos($landing_hero_image_raw, 'http') !== false) ? $landing_hero_image_raw : 'uploads/' . $landing_hero_image_raw;

// Ambil data kendaraan dari database
$vehicles = [];
if ($mysqli) {
    $query = "SELECT k.*, m.nama_merk, AVG(rv.bintang) as avg_rating, COUNT(rv.id) as count_reviews
              FROM kendaraan k 
              LEFT JOIN merk_kendaraan m ON k.id_merk = m.id_merk 
              LEFT JOIN reviews rv ON k.kode_unik_kendaraan = rv.kode_unik_kendaraan
              GROUP BY k.kode_unik_kendaraan
              ORDER BY k.harga_per_hari DESC";
    $result = mysqli_query($mysqli, $query);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $vehicles[] = $row;
        }
    }
}

// Ambil ulasan terbaru dari database
$db_reviews = [];
if ($mysqli) {
    $rev_q = "SELECT r.*, u.nama AS nama_user, k.nama_kendaraan, m.nama_merk
              FROM reviews r
              JOIN users u ON r.id_user = u.id
              JOIN kendaraan k ON r.kode_unik_kendaraan = k.kode_unik_kendaraan
              LEFT JOIN merk_kendaraan m ON k.id_merk = m.id_merk
              ORDER BY r.created_at DESC
              LIMIT 10";
    $rev_res = mysqli_query($mysqli, $rev_q);
    if ($rev_res) {
        while ($row = mysqli_fetch_assoc($rev_res)) {
            $db_reviews[] = [
                'rating' => intval($row['bintang']),
                'text' => htmlspecialchars($row['ulasan']),
                'name' => htmlspecialchars($row['nama_user']),
                'subtitle' => 'Penyewa ' . htmlspecialchars($row['nama_kendaraan']),
                'avatar' => 'https://ui-avatars.com/api/?name=' . urlencode($row['nama_user']) . '&background=random&color=fff'
            ];
        }
    }
}

$static_reviews = [
    [
        'rating' => 5,
        'text' => "Pelayanan VIP yang luar biasa! Kondisi mobil Audi A8 sangat mulus seperti keluar dari showroom. Sangat merekomendasikan untuk keperluan bisnis penting.",
        'name' => "Rian Pratama",
        'subtitle' => "Pengusaha & Kolektor",
        'avatar' => "https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=150&h=150&q=80"
    ],
    [
        'rating' => 5,
        'text' => "Sewa mobil di FTrans benar-benar mempermudah segalanya. Pemesanan cepat, konfirmasi kilat via email, dan verifikasi pembayaran admin sangat responsif.",
        'name' => "Amanda Siregar",
        'subtitle' => "Eksekutif Korporat",
        'avatar' => "https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=150&h=150&q=80"
    ],
    [
        'rating' => 5,
        'text' => "Pilihan terbaik untuk sewa kendaraan mewah di kota ini. Proses upload bukti bayar lewat HP sangat praktis dan email invoice PDF langsung masuk.",
        'name' => "Hendri Kusuma",
        'subtitle' => "Direktur Keuangan",
        'avatar' => "https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=150&h=150&q=80"
    ]
];

$merged_reviews = array_merge($db_reviews, $static_reviews);

// Ambil data statistik dari database untuk integrasi dengan dashboard
$total_armada = 0;
$transaksi_sukses = 0;
$pelanggan_aktif = 0;

if ($mysqli) {
    // Total armada
    $res_armada = mysqli_query($mysqli, "SELECT COUNT(*) as count FROM kendaraan");
    if ($res_armada) {
        $row = mysqli_fetch_assoc($res_armada);
        $total_armada = $row['count'] ?? 0;
    }

    // Transaksi sukses (sedang disewa atau selesai)
    $res_transaksi = mysqli_query($mysqli, "SELECT COUNT(*) as count FROM penyewaan WHERE status IN ('sedang_disewa', 'selesai')");
    if ($res_transaksi) {
        $row = mysqli_fetch_assoc($res_transaksi);
        $transaksi_sukses = $row['count'] ?? 0;
    }

    // Member aktif
    $res_pelanggan = mysqli_query($mysqli, "SELECT COUNT(*) as count FROM users WHERE role = 'user'");
    if ($res_pelanggan) {
        $row = mysqli_fetch_assoc($res_pelanggan);
        $pelanggan_aktif = $row['count'] ?? 0;
    }
}

// Petakan jenis dan merk kendaraan ke gambar stock berkualitas tinggi
function getCarImageUrl($car) {
    $brand = strtolower(trim($car['nama_merk'] ?? ''));
    $name = strtolower(trim($car['nama_kendaraan'] ?? ''));
    $jenis = strtolower(trim($car['jenis_kendaraan'] ?? ''));
    
    // 1. Roda 2 (Motorcycle)
    if ($jenis === 'roda 2') {
        if (strpos($name, 'vespa') !== false) {
            return 'https://images.unsplash.com/photo-1568772585407-9361f9bf3a87?auto=format&fit=crop&w=800&q=80'; // Vespa Scooter
        }
        return 'https://images.unsplash.com/photo-1558981806-ec527fa84c39?auto=format&fit=crop&w=800&q=80'; // Premium Motorcycle
    }
    
    // 2. Roda 4 (Car)
    if ($brand === 'audi') {
        // Audi A8 is a luxury sedan, not R8 supercar
        if (strpos($name, 'a8') !== false || strpos($name, 'sedan') !== false || strpos($name, 'a6') !== false) {
            return 'https://images.unsplash.com/photo-1542282088-fe8426682b8f?auto=format&fit=crop&w=800&q=80'; // Audi Sedan (A8 style)
        }
        return 'https://images.unsplash.com/photo-1603584173870-7f23fdae1b7a?auto=format&fit=crop&w=800&q=80'; // Audi R8 Supercar
    } elseif ($brand === 'bmw') {
        return 'https://images.unsplash.com/photo-1555215695-3004980ad54e?auto=format&fit=crop&w=800&q=80'; // BMW M8
    } elseif ($brand === 'honda') {
        return 'https://images.unsplash.com/photo-1619682817481-e994891cd1f5?auto=format&fit=crop&w=800&q=80'; // Honda NSX
    } elseif ($brand === 'toyota') {
        return 'https://images.unsplash.com/photo-1605559424843-9e4c228bf1c2?auto=format&fit=crop&w=800&q=80'; // Toyota GR Supra / Land Cruiser
    } elseif ($brand === 'mitsubishi') {
        return 'https://images.unsplash.com/photo-1617531653332-bd46c24f2068?auto=format&fit=crop&w=800&q=80'; // Mitsubishi Lancer Evolution X
    } elseif ($brand === 'nissan') {
        return 'https://images.unsplash.com/photo-1502877338535-766e1452684a?auto=format&fit=crop&w=800&q=80'; // Nissan GT-R
    } elseif ($brand === 'mazda') {
        return 'https://images.unsplash.com/photo-1580273916550-e323be2ae537?auto=format&fit=crop&w=800&q=80'; // Mazda RX-7
    } elseif ($brand === 'mercedes' || $brand === 'benz' || $brand === 'mercedes-benz') {
        return 'https://images.unsplash.com/photo-1618843479313-40f8afb4b4d8?auto=format&fit=crop&w=800&q=80'; // Mercedes-Benz AMG GT
    } elseif ($brand === 'porsche') {
        return 'https://images.unsplash.com/photo-1503376780353-7e6692767b70?auto=format&fit=crop&w=800&q=80'; // Porsche 911 GT3 RS
    } elseif ($brand === 'ford') {
        return 'https://images.unsplash.com/photo-1502877338535-766e1452684a?auto=format&fit=crop&w=800&q=80'; // Ford Mustang
    }
    
    // Default luxury car image
    return 'https://images.unsplash.com/photo-1618843479313-40f8afb4b4d8?auto=format&fit=crop&w=800&q=80';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <title>FTrans Sewa Mobil Mewah & Premium Terbaik</title>
    
    <!-- PWA & Mobile Icons -->
    <link rel="manifest" href="assets/favicon/manifest.json">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="FTrans">
    <meta name="theme-color" content="#1e293b">

    <!-- Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- CSS Stylesheets -->
    <link href="css/style.css" rel="stylesheet">
    <link href="css/landing.css" rel="stylesheet">
    
    <!-- Theme engine script (Loads in head to prevent visual flashing) -->
    <script src="js/color-modes.js"></script>
</head>
<body>

    <!-- --- NAVIGATION BAR --- -->
    <nav class="navbar navbar-expand-lg navbar-luxury fixed-top py-3">
        <div class="container">
            <a class="navbar-brand brand-logo" href="index.php">
                <i class="fa fa-car me-2"></i>F<span>Trans</span>
            </a>
            <button class="navbar-toggler border-0 shadow-none text-main" type="button" data-coreui-toggle="collapse" data-coreui-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <i class="fa fa-bars"></i>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item">
                        <a class="nav-link nav-link-custom" href="#hero">Beranda</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link nav-link-custom" href="#armada">Armada</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link nav-link-custom" href="#cara-kerja">Cara Kerja</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link nav-link-custom" href="#review">Ulasan</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link nav-link-custom" href="#faq">FAQ</a>
                    </li>
                </ul>
                <div class="d-flex align-items-center gap-3 mt-3 mt-lg-0">
                    <!-- CoreUI Theme Selector Dropdown (Unified with Dashboard) -->
                    <div class="dropdown">
                        <button class="btn btn-link nav-link py-2 px-2 d-flex align-items-center text-main border-0 bg-transparent shadow-none" type="button" aria-expanded="false" data-coreui-toggle="dropdown" title="Ganti Tema">
                            <i class="fas fa-adjust fs-5"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border border-secondary border-opacity-25" style="background: var(--bg-card);">
                            <li>
                                <button class="dropdown-item d-flex align-items-center gap-2 border-0 py-2 w-100 text-start" type="button" data-coreui-theme-value="light">
                                    <i class="fas fa-sun text-warning"></i> Terang
                                </button>
                            </li>
                            <li>
                                <button class="dropdown-item d-flex align-items-center gap-2 border-0 py-2 w-100 text-start" type="button" data-coreui-theme-value="dark">
                                    <i class="fas fa-moon text-primary"></i> Gelap
                                </button>
                            </li>
                            <li>
                                <button class="dropdown-item d-flex align-items-center gap-2 border-0 py-2 w-100 text-start" type="button" data-coreui-theme-value="auto">
                                    <i class="fas fa-magic text-info"></i> Otomatis
                                </button>
                            </li>
                        </ul>
                    </div>

                    <?php if (isset($_SESSION['user_id'])): ?>
                        <!-- User Dropdown Menu (Unified with Dashboard) -->
                        <div class="dropdown">
                            <a class="nav-link py-0 pe-0 d-flex align-items-center gap-2 dropdown-toggle text-decoration-none shadow-none" data-coreui-toggle="dropdown" href="#" role="button" aria-expanded="false">
                                <div class="avatar avatar-md border border-secondary bg-primary text-white fw-bold d-flex align-items-center justify-content-center" style="width:36px; height:36px; border-radius:50%;">
                                    <?= strtoupper(substr(htmlspecialchars($_SESSION['user_nama'] ?? 'U'), 0, 1)) ?>
                                </div>
                                <span class="d-none d-md-inline-block text-main fw-semibold"><?= htmlspecialchars($_SESSION['user_nama'] ?? 'User') ?></span>
                            </a>
                            <div class="dropdown-menu dropdown-menu-end pt-0 shadow-sm border border-secondary border-opacity-25" style="background: var(--bg-card); overflow: hidden;">
                                <div class="dropdown-header text-body-secondary fw-semibold bg-body-secondary bg-opacity-25 py-2 px-3 mb-2" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">Menu Utama</div>
                                <a class="dropdown-item d-flex align-items-center gap-2 py-2 text-main" href="dashboard.php">
                                    <i class="fa fa-table text-muted"></i> Data Kendaraan
                                </a>
                                <a class="dropdown-item d-flex align-items-center gap-2 py-2 text-main" href="profile.php">
                                    <i class="fa fa-user-edit text-muted"></i> My Profile
                                </a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item d-flex align-items-center gap-2 py-2 text-danger" href="logout.php">
                                    <i class="fa fa-sign-out-alt text-danger"></i> Log Out
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-outline-gold btn-sm px-4">Sign In</a>
                        <a href="register.php" class="btn btn-gold btn-sm px-4">Sign Up</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- --- HERO SECTION --- -->
    <section class="hero-section" id="hero">
        <div class="container">
            <div class="row align-items-center g-5">
                <div class="col-lg-6">
                    <span class="luxury-badge">
                        <i class="fa fa-gem"></i> Premium Car Rental
                    </span>
                    <h1 class="hero-title fw-bold text-main">
                        <?= htmlspecialchars($landing_hero_title) ?>
                    </h1>
                    <p class="hero-subtitle">
                        <?= htmlspecialchars($landing_hero_subtitle) ?>
                    </p>
                    <div class="d-flex flex-wrap gap-3">
                        <a href="#armada" class="btn btn-gold">Lihat Armada <i class="fa fa-arrow-right ms-1"></i></a>
                        <a href="#cara-kerja" class="btn btn-outline-gold">Bagaimana Cara Kerja?</a>
                    </div>
                </div>
                <div class="col-lg-6 hero-image-wrapper text-center">
                    <img src="<?= htmlspecialchars($landing_hero_image) ?>" alt="Luxury Porsche Car" class="img-fluid rounded-4 shadow-lg reveal" style="max-height: 400px; object-fit: cover;">
                </div>
            </div>
        </div>
    </section>

    <!-- --- QUICK BOOKING CARD --- -->
    <div class="container">
        <div class="booking-assistant-card reveal">
            <h5 class="fw-bold mb-4 text-center text-lg-start"><i class="fa fa-search me-2 text-primary"></i>Cari & Booking Cepat</h5>
            <form action="sewa.php" method="GET">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="assistant-form-label" for="select-car">Pilih Kendaraan</label>
                        <select class="form-select assistant-input" id="select-car" name="car_code">
                            <option value="">Semua Kendaraan Mewah</option>
                            <?php foreach ($vehicles as $car): ?>
                                <option value="<?= $car['kode_unik_kendaraan'] ?>">
                                    <?= htmlspecialchars($car['nama_kendaraan']) ?> - Rp <?= number_format($car['harga_per_hari'], 0, ',', '.') ?>/hari
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="assistant-form-label" for="rent-date">Tanggal Sewa</label>
                        <input type="datetime-local" class="form-control assistant-input" id="rent-date" name="tanggal_sewa" required>
                    </div>
                    <div class="col-md-3">
                        <label class="assistant-form-label" for="return-date">Tanggal Kembali</label>
                        <input type="datetime-local" class="form-control assistant-input" id="return-date" name="tanggal_kembali" required>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-gold w-100 py-3" style="padding: 10px 12px;"><i class="fa fa-key me-1"></i> Sewa</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- --- STATS SECTION --- -->
    <div class="container mt-5 pt-3">
        <div class="row g-4 justify-content-center text-center">
            <div class="col-md-4 reveal">
                <div class="stats-card">
                    <div class="stats-icon-wrapper">
                        <i class="fa fa-car-side"></i>
                    </div>
                    <h2 class="stats-number fw-extrabold mt-3"><?= number_format($total_armada) ?></h2>
                    <p class="stats-label text-muted mb-0">Armada Mewah Tersedia</p>
                </div>
            </div>
            <div class="col-md-4 reveal">
                <div class="stats-card">
                    <div class="stats-icon-wrapper">
                        <i class="fa fa-handshake"></i>
                    </div>
                    <h2 class="stats-number fw-extrabold mt-3"><?= number_format($transaksi_sukses) ?>+</h2>
                    <p class="stats-label text-muted mb-0">Transaksi Sewa Sukses</p>
                </div>
            </div>
            <div class="col-md-4 reveal">
                <div class="stats-card">
                    <div class="stats-icon-wrapper">
                        <i class="fa fa-user-check"></i>
                    </div>
                    <h2 class="stats-number fw-extrabold mt-3"><?= number_format($pelanggan_aktif) ?>+</h2>
                    <p class="stats-label text-muted mb-0">Pelanggan Aktif Terdaftar</p>
                </div>
            </div>
        </div>
    </div>

    <!-- --- FLEET SECTION --- -->
    <section class="py-5 mt-5" id="armada">
        <div class="container py-4">
            <div class="text-center mb-5 reveal">
                <span class="text-primary fw-semibold text-uppercase tracking-wider small">Eksklusif</span>
                <h2 class="fw-bold mt-2">Armada Kendaraan Mewah Kami</h2>
                <div class="mx-auto bg-primary" style="width: 60px; height: 3px; border-radius: 2px;"></div>
                <p class="text-muted mt-3 max-w-2xl mx-auto">Kami menyediakan berbagai tipe kendaraan premium berkualitas tinggi yang selalu prima dan siap menemani perjalanan penting Anda.</p>
            </div>

            <!-- Category Filter Buttons -->
            <div class="d-flex justify-content-center gap-2 mb-4 mt-n2 reveal">
                <button class="btn btn-outline-gold px-4 py-2 active btn-filter" data-filter="all">Semua Armada</button>
                <button class="btn btn-outline-gold px-4 py-2 btn-filter" data-filter="roda 4"><i class="fa fa-car me-2"></i> Mobil (Roda 4)</button>
                <button class="btn btn-outline-gold px-4 py-2 btn-filter" data-filter="roda 2"><i class="fa fa-motorcycle me-2"></i> Motor (Roda 2)</button>
            </div>

            <div class="position-relative reveal">
                <!-- Navigation Buttons -->
                <button class="slider-nav-btn prev-btn" id="slide-prev" aria-label="Previous Slide">
                    <i class="fa fa-chevron-left"></i>
                </button>
                <button class="slider-nav-btn next-btn" id="slide-next" aria-label="Next Slide">
                    <i class="fa fa-chevron-right"></i>
                </button>
                
                <div class="car-slider-container">
                    <div class="car-slider" id="car-slider">
                        <?php if (empty($vehicles)): ?>
                            <div class="w-100 text-center text-muted py-5">
                                <i class="fa fa-info-circle fa-2x mb-3 text-secondary"></i>
                                <p>Belum ada data kendaraan yang tersedia di database.</p>
                            </div>
                        <?php else: ?>
                            <?php 
                            // Render tiga set daftar kendaraan untuk mendukung perulangan scroll tak terbatas (infinite loop)
                            for ($set = 0; $set < 3; $set++):
                                foreach ($vehicles as $car): 
                                    $car_img = (!empty($car['gambar']) && file_exists('uploads/' . $car['gambar'])) ? 'uploads/' . $car['gambar'] : '';
                                    $status_k = strtolower(trim($car['status_kendaraan'] ?? 'tersedia'));
                                ?>
                                    <div class="car-slide" data-jenis="<?= htmlspecialchars(strtolower($car['jenis_kendaraan'])) ?>" data-set="<?= $set ?>">
                                        <div class="car-card text-start">
                                            <div class="car-img-wrapper position-relative">
                                                <!-- Status Badge (Absolute Positioned over Image) -->
                                                <?php if ($status_k === 'disewa'): ?>
                                                    <span class="badge bg-warning text-dark position-absolute top-0 end-0 m-3 px-3 py-2 fw-bold" style="z-index: 5; box-shadow: 0 4px 10px rgba(0,0,0,0.15); font-size: 0.75rem;"><i class="fa fa-key me-1"></i>Sedang Disewa</span>
                                                <?php elseif ($status_k === 'perawatan'): ?>
                                                    <span class="badge bg-danger text-white position-absolute top-0 end-0 m-3 px-3 py-2 fw-bold" style="z-index: 5; box-shadow: 0 4px 10px rgba(0,0,0,0.15); font-size: 0.75rem;"><i class="fa fa-tools me-1"></i>Dalam Perawatan</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success text-white position-absolute top-0 end-0 m-3 px-3 py-2 fw-bold" style="z-index: 5; box-shadow: 0 4px 10px rgba(0,0,0,0.15); font-size: 0.75rem;"><i class="fa fa-check me-1"></i>Tersedia</span>
                                                <?php endif; ?>

                                                <?php if (!empty($car_img)): ?>
                                                    <img src="<?= $car_img ?>" alt="<?= htmlspecialchars($car['nama_kendaraan']) ?>" class="car-img">
                                                <?php else: ?>
                                                    <div class="w-100 h-100 d-flex flex-column align-items-center justify-content-center bg-body-secondary text-muted opacity-50">
                                                        <i class="fa fa-image fa-3x mb-2"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="p-4 d-flex flex-column flex-grow-1">
                                                <div class="d-flex justify-content-between align-items-start mb-3">
                                                    <div>
                                                        <div class="d-flex flex-wrap align-items-center gap-1 mb-2">
                                                            <span class="badge bg-secondary bg-opacity-10 text-secondary small text-uppercase" style="font-size: 0.65rem;"><?= htmlspecialchars($car['nama_merk'] ?? 'Premium') ?></span>
                                                            <span class="badge car-meta-badge"><i class="fa fa-cubes me-1"></i>Stok: <?= intval($car['stok'] ?? 1) ?></span>
                                                            <span class="badge car-meta-badge"><i class="fa fa-palette me-1"></i><?= htmlspecialchars($car['warna'] ?? 'Hitam') ?></span>
                                                        </div>
                                                        <h5 class="fw-bold mb-0 text-truncate" style="max-width: 170px;"><?= htmlspecialchars($car['nama_kendaraan']) ?></h5>
                                                        <!-- Rating Stars -->
                                                        <?php
                                                        $rating = floatval($car['avg_rating'] ?? 0);
                                                        $count_rv = intval($car['count_reviews'] ?? 0);
                                                        ?>
                                                        <div class="d-flex align-items-center mt-1" style="font-size: 0.8rem;">
                                                            <div class="text-warning me-1">
                                                                <?php
                                                                $full_stars = floor($rating);
                                                                $has_half = ($rating - $full_stars) >= 0.5;
                                                                for ($i = 1; $i <= 5; $i++) {
                                                                    if ($i <= $full_stars) {
                                                                        echo '<i class="fas fa-star"></i>';
                                                                    } elseif ($i == $full_stars + 1 && $has_half) {
                                                                        echo '<i class="fas fa-star-half-alt"></i>';
                                                                    } else {
                                                                        echo '<i class="far fa-star"></i>';
                                                                    }
                                                                }
                                                                ?>
                                                            </div>
                                                            <span class="text-body-secondary fw-semibold small" style="font-size: 0.72rem;">
                                                                <?= $count_rv > 0 ? number_format($rating, 1) . ' (' . $count_rv . ')' : 'Belum ada ulasan' ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <div class="text-end ms-2 flex-shrink-0">
                                                        <span class="text-primary fw-bold fs-5" style="font-size: 1.1rem !important;">Rp <?= number_format($car['harga_per_hari'], 0, ',', '.') ?> <span class="text-muted fw-normal" style="font-size: 0.7rem;">/ Hari</span></span>
                                                    </div>
                                                </div>
                                                
                                                <div class="row g-2 mb-4">
                                                    <div class="col-6 car-spec-item">
                                                        <i class="fa fa-cogs"></i> <?= htmlspecialchars($car['transmisi'] ?? 'Matic') ?>
                                                    </div>
                                                    <div class="col-6 car-spec-item">
                                                        <i class="fa fa-user"></i> <?= htmlspecialchars($car['tempat_duduk'] ?? '5 Seater') ?>
                                                    </div>
                                                    <div class="col-6 car-spec-item">
                                                        <i class="fa fa-gas-pump"></i> <?= htmlspecialchars($car['bahan_bakar'] ?? 'Bensin') ?>
                                                    </div>
                                                    <div class="col-6 car-spec-item">
                                                        <i class="fa fa-shield-alt"></i> Asuransi
                                                    </div>
                                                </div>
                                                
                                                <div class="mt-auto">
                                                    <?php if ($status_k === 'disewa'): ?>
                                                        <button class="btn btn-secondary w-100 py-2 border-0" disabled><i class="fa fa-ban me-1"></i> Sedang Disewa</button>
                                                    <?php elseif ($status_k === 'perawatan'): ?>
                                                        <button class="btn btn-danger bg-opacity-75 text-white w-100 py-2 border-0" disabled><i class="fa fa-tools me-1"></i> Dalam Perawatan</button>
                                                    <?php else: ?>
                                                        <?php if (isset($_SESSION['user_id'])): ?>
                                                            <a href="sewa.php?car_code=<?= $car['kode_unik_kendaraan'] ?>" class="btn btn-outline-gold w-100 py-2">Sewa Sekarang <i class="fa fa-arrow-right ms-1"></i></a>
                                                        <?php else: ?>
                                                            <a href="login.php" class="btn btn-outline-gold w-100 py-2">Login untuk Menyewa <i class="fa fa-arrow-right ms-1"></i></a>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php 
                                endforeach; 
                            endfor;
                            ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- --- HOW IT WORKS SECTION --- -->
    <section class="py-5 bg-body-secondary bg-opacity-50" id="cara-kerja">
        <div class="container py-4">
            <div class="text-center mb-5 reveal">
                <span class="text-primary fw-semibold text-uppercase small">Langkah Mudah</span>
                <h2 class="fw-bold mt-2">Cara Kerja Penyewaan</h2>
                <div class="mx-auto bg-primary" style="width: 60px; height: 3px; border-radius: 2px;"></div>
                <p class="text-muted mt-3">Prosedur penyewaan yang cepat, aman, dan tanpa proses birokrasi berbelit.</p>
            </div>

            <div class="row g-4 justify-content-center">
                <div class="col-md-4 reveal">
                    <div class="step-card">
                        <div class="step-icon-wrapper">
                            <i class="fa fa-car-side"></i>
                        </div>
                        <h5 class="fw-bold">1. Pilih Mobil Impian</h5>
                        <p class="text-muted small">Cari dan pilih kendaraan mewah pilihan Anda dari halaman katalog online kami yang selalu terupdate.</p>
                    </div>
                </div>
                <div class="col-md-4 reveal">
                    <div class="step-card">
                        <div class="step-icon-wrapper">
                            <i class="fa fa-credit-card"></i>
                        </div>
                        <h5 class="fw-bold">2. Selesaikan Pembayaran</h5>
                        <p class="text-muted small">Lakukan pembayaran secara mudah melalui transfer bank, lalu unggah bukti pembayaran di akun Anda.</p>
                    </div>
                </div>
                <div class="col-md-4 reveal">
                    <div class="step-card">
                        <div class="step-icon-wrapper">
                            <i class="fa fa-key"></i>
                        </div>
                        <h5 class="fw-bold">3. Ambil Kunci & Mulai</h5>
                        <p class="text-muted small">Kunci mobil dan armada premium Anda siap diambil atau dikirim ke lokasi Anda setelah verifikasi instan.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- --- TESTIMONIALS SECTION --- -->
    <section class="py-5" id="review">
        <div class="container py-4">
            <div class="text-center mb-5 reveal">
                <span class="text-primary fw-semibold text-uppercase small">Testimoni</span>
                <h2 class="fw-bold mt-2">Ulasan Pelanggan Premium</h2>
                <div class="mx-auto bg-primary" style="width: 60px; height: 3px; border-radius: 2px;"></div>
                <p class="text-muted mt-3">Pendapat mereka yang telah menikmati kenyamanan sewa mobil premium FTrans.</p>
            </div>

            <div class="reviews-marquee-container reveal">
                <div class="reviews-marquee">
                    <!-- Set 1 -->
                    <?php foreach ($merged_reviews as $r): ?>
                    <div class="review-card-item">
                        <div class="review-card">
                            <div class="star-rating mb-2" style="color: #eab308;">
                                <?php
                                for ($i = 1; $i <= 5; $i++) {
                                    echo $i <= $r['rating'] ? '<i class="fa fa-star"></i>' : '<i class="far fa-star"></i>';
                                }
                                ?>
                            </div>
                            <p class="review-text">"<?= $r['text'] ?>"</p>
                            <div class="d-flex align-items-center gap-3">
                                <img src="<?= $r['avatar'] ?>" alt="Avatar" class="reviewer-avatar" style="width: 45px; height: 45px; border-radius: 50%; object-fit: cover;">
                                <div>
                                    <h6 class="fw-bold mb-0"><?= $r['name'] ?></h6>
                                    <span class="text-muted small"><?= $r['subtitle'] ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <!-- Set 2 (Duplicated for infinite scroll looping) -->
                    <?php foreach ($merged_reviews as $r): ?>
                    <div class="review-card-item">
                        <div class="review-card">
                            <div class="star-rating mb-2" style="color: #eab308;">
                                <?php
                                for ($i = 1; $i <= 5; $i++) {
                                    echo $i <= $r['rating'] ? '<i class="fa fa-star"></i>' : '<i class="far fa-star"></i>';
                                }
                                ?>
                            </div>
                            <p class="review-text">"<?= $r['text'] ?>"</p>
                            <div class="d-flex align-items-center gap-3">
                                <img src="<?= $r['avatar'] ?>" alt="Avatar" class="reviewer-avatar" style="width: 45px; height: 45px; border-radius: 50%; object-fit: cover;">
                                <div>
                                    <h6 class="fw-bold mb-0"><?= $r['name'] ?></h6>
                                    <span class="text-muted small"><?= $r['subtitle'] ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- --- FAQ SECTION --- -->
    <section class="py-5 bg-body-secondary bg-opacity-50" id="faq">
        <div class="container py-4" style="max-width: 800px;">
            <div class="text-center mb-5 reveal">
                <span class="text-primary fw-semibold text-uppercase small">FAQ</span>
                <h2 class="fw-bold mt-2">Pertanyaan Umum (Bantuan)</h2>
                <div class="mx-auto bg-primary" style="width: 60px; height: 3px; border-radius: 2px;"></div>
                <p class="text-muted mt-3">Menjawab keraguan Anda tentang pelayanan sewa kendaraan mewah FTrans.</p>
            </div>

            <div class="accordion border-0 reveal" id="faqAccordion">
                <div class="accordion-item mb-3 border rounded shadow-sm overflow-hidden bg-card">
                    <h2 class="accordion-header">
                        <button class="accordion-button fw-bold py-3 bg-transparent text-main" type="button" data-coreui-toggle="collapse" data-coreui-target="#faq-collapseOne">
                            Apakah sewa mobil sudah termasuk sopir dan asuransi?
                        </button>
                    </h2>
                    <div id="faq-collapseOne" class="accordion-collapse collapse show" data-coreui-parent="#faqAccordion">
                        <div class="accordion-body text-muted small">
                            Semua harga sewa yang tertera adalah tarif dasar *lepas kunci* per hari. Kami menyediakan opsi tambahan berupa asuransi komprehensif penuh serta jasa sopir VIP berpengalaman jika dibutuhkan saat konfirmasi pesanan.
                        </div>
                    </div>
                </div>
                <div class="accordion-item mb-3 border rounded shadow-sm overflow-hidden bg-card">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed fw-bold py-3 bg-transparent text-main" type="button" data-coreui-toggle="collapse" data-coreui-target="#faq-collapseTwo">
                            Bagaimana cara konfirmasi pembayaran?
                        </button>
                    </h2>
                    <div id="faq-collapseTwo" class="accordion-collapse collapse" data-coreui-parent="#faqAccordion">
                        <div class="accordion-body text-muted small">
                            Setelah memesan kendaraan, Anda akan menerima email tagihan (Invoice) PDF. Lakukan transfer ke rekening bank resmi kami, buka menu **Pembayaran** di dashboard web Anda, lalu unggah foto bukti transfer. Admin kami akan segera memverifikasinya.
                        </div>
                    </div>
                </div>
                <div class="accordion-item mb-3 border rounded shadow-sm overflow-hidden bg-card">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed fw-bold py-3 bg-transparent text-main" type="button" data-coreui-toggle="collapse" data-coreui-target="#faq-collapseThree">
                            Apakah ada batas minimal durasi sewa?
                        </button>
                    </h2>
                    <div id="faq-collapseThree" class="accordion-collapse collapse" data-coreui-parent="#faqAccordion">
                        <div class="accordion-body text-muted small">
                            Durasi minimal sewa di FTrans adalah 1 hari (24 jam). Kami juga melayani sistem sewa mingguan atau bulanan dengan diskon khusus premium untuk Anda.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- --- FOOTER SECTION --- -->
    <footer class="footer-landing py-5 mt-5">
        <div class="container py-3">
            <div class="row g-4">
                <div class="col-lg-5">
                    <h5 class="fw-bold mb-3"><i class="fa fa-car text-primary me-2"></i>FTrans Car Rental</h5>
                    <p class="text-secondary small max-w-sm">Memberikan pengalaman berkendara mewah dan tak terlupakan dengan layanan yang terjamin, cepat, dan profesional.</p>
                </div>
                <div class="col-6 col-lg-3">
                    <h6 class="fw-bold text-uppercase mb-3 small tracking-wider">Tautan Cepat</h6>
                    <ul class="list-unstyled d-flex flex-column gap-2 small text-secondary">
                        <li><a href="#hero" class="text-secondary text-decoration-none hover-white">Tentang Kami</a></li>
                        <li><a href="#armada" class="text-secondary text-decoration-none hover-white">Armada Mobil</a></li>
                        <li><a href="#cara-kerja" class="text-secondary text-decoration-none hover-white">Cara Kerja</a></li>
                        <li><a href="#faq" class="text-secondary text-decoration-none hover-white">Bantuan & FAQ</a></li>
                    </ul>
                </div>
                <div class="col-6 col-lg-4">
                    <h6 class="fw-bold text-uppercase mb-3 small tracking-wider">Hubungi Kami</h6>
                    <ul class="list-unstyled d-flex flex-column gap-2 small text-secondary">
                        <li><i class="fa fa-map-marker-alt me-2 text-primary"></i> Jl. Premium Luxury No. 7,Surakarta.</li>
                        <li><i class="fa fa-phone-alt me-2 text-primary"></i> +62 821 8888 9999</li>
                        <li><i class="fa fa-envelope me-2 text-primary"></i> support@ftrans.com</li>
                    </ul>
                </div>
            </div>
            <hr class="my-4 border-secondary border-opacity-20">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
                <p class="mb-0 text-secondary small">&copy; <?= date('Y') ?> FTrans Car Rental. All rights reserved.</p>
                <div class="d-flex gap-3 text-secondary">
                    <a href="#" class="text-secondary"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" class="text-secondary"><i class="fab fa-instagram"></i></a>
                    <a href="#" class="text-secondary"><i class="fab fa-twitter"></i></a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap & necessary scripts -->
    <script src="vendors/@coreui/coreui/js/coreui.bundle.min.js"></script>
    <script src="js/landing.js"></script>
    <script src="js/pwa.js"></script>
</body>
</html>
