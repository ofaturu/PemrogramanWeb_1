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
    $query = "SELECT k.*, m.nama_merk 
              FROM kendaraan k 
              LEFT JOIN merk_kendaraan m ON k.id_merk = m.id_merk 
              ORDER BY k.harga_per_hari DESC";
    $result = mysqli_query($mysqli, $query);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $vehicles[] = $row;
        }
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
    <title>FTrans — Sewa Mobil Mewah & Premium Terbaik</title>
    
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
            <button class="navbar-toggler border-0" type="button" data-coreui-toggle="collapse" data-coreui-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
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
                    <!-- Theme Toggle Switcher -->
                    <button class="btn-theme-toggle" id="theme-toggle-btn" title="Ganti Tema">
                        <i class="fas fa-moon" id="theme-icon"></i>
                    </button>
                    
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <a href="dashboard.php" class="btn btn-outline-gold btn-sm px-4">
                            <i class="fa fa-user me-1"></i> Dashboard
                        </a>
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

    <!-- --- FLEET SECTION --- -->
    <section class="py-5 mt-5" id="armada">
        <div class="container py-4">
            <div class="text-center mb-5 reveal">
                <span class="text-primary fw-semibold text-uppercase tracking-wider small">Eksklusif</span>
                <h2 class="fw-bold mt-2">Armada Mobil Mewah Kami</h2>
                <div class="mx-auto bg-primary" style="width: 60px; height: 3px; border-radius: 2px;"></div>
                <p class="text-muted mt-3 max-w-2xl mx-auto">Kami menyediakan berbagai tipe kendaraan premium berkualitas tinggi yang selalu prima dan siap menemani perjalanan penting Anda.</p>
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
                                ?>
                                    <div class="car-slide">
                                        <div class="car-card text-start">
                                            <div class="car-img-wrapper">
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
                                                        <span class="badge bg-secondary bg-opacity-10 text-secondary mb-2 small text-uppercase"><?= htmlspecialchars($car['nama_merk'] ?? 'Premium') ?></span>
                                                        <h5 class="fw-bold mb-0 text-truncate" style="max-width: 200px;"><?= htmlspecialchars($car['nama_kendaraan']) ?></h5>
                                                    </div>
                                                    <div class="text-end ms-3 flex-shrink-0">
                                                        <span class="text-primary fw-bold fs-5">Rp <?= number_format($car['harga_per_hari'], 0, ',', '.') ?> <span class="text-muted fw-normal" style="font-size: 0.75rem;">/ Hari</span></span>
                                                    </div>
                                                </div>
                                                
                                                <div class="row g-2 mb-4">
                                                    <div class="col-6 car-spec-item">
                                                        <i class="fa fa-cogs"></i> Otomatis
                                                    </div>
                                                    <div class="col-6 car-spec-item">
                                                        <i class="fa fa-user"></i> 2 Seater
                                                    </div>
                                                    <div class="col-7 car-spec-item">
                                                        <i class="fa fa-gas-pump"></i> Shell V-Power
                                                    </div>
                                                    <div class="col-5 car-spec-item">
                                                        <i class="fa fa-shield-alt"></i> Asuransi
                                                    </div>
                                                </div>
                                                
                                                <div class="mt-auto">
                                                    <?php if (isset($_SESSION['user_id'])): ?>
                                                        <a href="sewa.php?car_code=<?= $car['kode_unik_kendaraan'] ?>" class="btn btn-outline-gold w-100 py-2">Sewa Sekarang <i class="fa fa-arrow-right ms-1"></i></a>
                                                    <?php else: ?>
                                                        <a href="login.php" class="btn btn-outline-gold w-100 py-2">Login untuk Menyewa <i class="fa fa-arrow-right ms-1"></i></a>
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
                    <div class="review-card-item">
                        <div class="review-card">
                            <div class="star-rating">
                                <i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i>
                            </div>
                            <p class="review-text">"Pelayanan VIP yang luar biasa! Kondisi mobil Audi A8 sangat mulus seperti keluar dari showroom. Sangat merekomendasikan untuk keperluan bisnis penting."</p>
                            <div class="d-flex align-items-center gap-3">
                                <img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=150&h=150&q=80" alt="Avatar" class="reviewer-avatar">
                                <div>
                                    <h6 class="fw-bold mb-0">Rian Pratama</h6>
                                    <span class="text-muted small">Pengusaha & Kolektor</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="review-card-item">
                        <div class="review-card">
                            <div class="star-rating">
                                <i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i>
                            </div>
                            <p class="review-text">"Sewa mobil di FTrans benar-benar mempermudah segalanya. Pemesanan cepat, konfirmasi kilat via email, dan verifikasi pembayaran admin sangat responsif."</p>
                            <div class="d-flex align-items-center gap-3">
                                <img src="https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=150&h=150&q=80" alt="Avatar" class="reviewer-avatar">
                                <div>
                                    <h6 class="fw-bold mb-0">Amanda Siregar</h6>
                                    <span class="text-muted small">Eksekutif Korporat</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="review-card-item">
                        <div class="review-card">
                            <div class="star-rating">
                                <i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i>
                            </div>
                            <p class="review-text">"Pilihan terbaik untuk sewa kendaraan mewah di kota ini. Proses upload bukti bayar lewat HP sangat praktis dan email invoice PDF langsung masuk."</p>
                            <div class="d-flex align-items-center gap-3">
                                <img src="https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=150&h=150&q=80" alt="Avatar" class="reviewer-avatar">
                                <div>
                                    <h6 class="fw-bold mb-0">Hendri Kusuma</h6>
                                    <span class="text-muted small">Direktur Keuangan</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Set 2 (Duplicated for infinite scroll looping) -->
                    <div class="review-card-item">
                        <div class="review-card">
                            <div class="star-rating">
                                <i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i>
                            </div>
                            <p class="review-text">"Pelayanan VIP yang luar biasa! Kondisi mobil Audi A8 sangat mulus seperti keluar dari showroom. Sangat merekomendasikan untuk keperluan bisnis penting."</p>
                            <div class="d-flex align-items-center gap-3">
                                <img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=150&h=150&q=80" alt="Avatar" class="reviewer-avatar">
                                <div>
                                    <h6 class="fw-bold mb-0">Rian Pratama</h6>
                                    <span class="text-muted small">Pengusaha & Kolektor</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="review-card-item">
                        <div class="review-card">
                            <div class="star-rating">
                                <i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i>
                            </div>
                            <p class="review-text">"Sewa mobil di FTrans benar-benar mempermudah segalanya. Pemesanan cepat, konfirmasi kilat via email, dan verifikasi pembayaran admin sangat responsif."</p>
                            <div class="d-flex align-items-center gap-3">
                                <img src="https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=150&h=150&q=80" alt="Avatar" class="reviewer-avatar">
                                <div>
                                    <h6 class="fw-bold mb-0">Amanda Siregar</h6>
                                    <span class="text-muted small">Eksekutif Korporat</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="review-card-item">
                        <div class="review-card">
                            <div class="star-rating">
                                <i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i><i class="fa fa-star"></i>
                            </div>
                            <p class="review-text">"Pilihan terbaik untuk sewa kendaraan mewah di kota ini. Proses upload bukti bayar lewat HP sangat praktis dan email invoice PDF langsung masuk."</p>
                            <div class="d-flex align-items-center gap-3">
                                <img src="https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=150&h=150&q=80" alt="Avatar" class="reviewer-avatar">
                                <div>
                                    <h6 class="fw-bold mb-0">Hendri Kusuma</h6>
                                    <span class="text-muted small">Direktur Keuangan</span>
                                </div>
                            </div>
                        </div>
                    </div>
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
    <footer class="bg-dark text-light py-5 mt-5 border-top border-secondary border-opacity-25">
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
</body>
</html>
