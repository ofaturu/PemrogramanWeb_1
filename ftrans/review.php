<?php
require_once 'config.php';

// Secure page - user must be logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$id_sewa = intval($_GET['id'] ?? 0);
if ($id_sewa <= 0) {
    header('Location: users.php');
    exit;
}

// Fetch rental details to verify it belongs to user and is completed (status = selesai)
$stmt = mysqli_prepare($mysqli, "
    SELECT p.*, k.nama_kendaraan, k.gambar, m.nama_merk
    FROM penyewaan p
    JOIN kendaraan k ON p.kode_unik_kendaraan = k.kode_unik_kendaraan
    LEFT JOIN merk_kendaraan m ON k.id_merk = m.id_merk
    WHERE p.id_sewa = ? AND p.id_user = ?
");
mysqli_stmt_bind_param($stmt, 'ii', $id_sewa, $_SESSION['user_id']);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$rental = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$rental) {
    header('Location: users.php?error=unauthorized');
    exit;
}

if ($rental['status'] !== 'selesai') {
    header('Location: users.php?error=not_completed');
    exit;
}

// Check if a review already exists for this rental
$check_stmt = mysqli_prepare($mysqli, "SELECT id FROM reviews WHERE id_sewa = ?");
mysqli_stmt_bind_param($check_stmt, 'i', $id_sewa);
mysqli_stmt_execute($check_stmt);
$check_res = mysqli_stmt_get_result($check_stmt);
$existing_review = mysqli_fetch_assoc($check_res);
mysqli_stmt_close($check_stmt);

if ($existing_review) {
    header('Location: users.php?msg=already_reviewed');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rating = intval($_POST['rating'] ?? 0);
    $ulasan = trim($_POST['ulasan'] ?? '');

    if ($rating < 1 || $rating > 5) {
        $error = 'Harap berikan rating bintang antara 1 sampai 5.';
    } elseif (empty($ulasan)) {
        $error = 'Harap tulis ulasan Anda.';
    } else {
        $ins = mysqli_prepare($mysqli, "INSERT INTO reviews (id_sewa, id_user, kode_unik_kendaraan, bintang, ulasan) VALUES (?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($ins, 'iiiis', $id_sewa, $_SESSION['user_id'], $rental['kode_unik_kendaraan'], $rating, $ulasan);
        
        if (mysqli_stmt_execute($ins)) {
            $success = 'Terima kasih atas ulasan Anda! Kontribusi Anda sangat berharga bagi kami.';
            // Refresh logic - prevent form resubmission
            header("Location: users.php?msg=review_success");
            exit;
        } else {
            $error = 'Terjadi kesalahan sistem saat menyimpan ulasan Anda.';
        }
        mysqli_stmt_close($ins);
    }
}

$activePage = 'users';
?>
<!DOCTYPE html>
<html lang="id">
<?php
$title = 'Beri Ulasan Rental #' . str_pad($id_sewa, 5, '0', STR_PAD_LEFT) . ' — FTrans';
include 'partials/head.php';
?>
<style>
/* Premium interactive rating stars styling */
.rating-stars {
    display: flex;
    flex-direction: row-reverse;
    justify-content: center;
    gap: 8px;
}
.rating-stars input {
    display: none;
}
.rating-stars label {
    font-size: 2.5rem;
    color: #cbd5e1; /* Gray star default */
    cursor: pointer;
    transition: color 0.2s ease-in-out, transform 0.1s ease;
}
.rating-stars label:hover,
.rating-stars label:hover ~ label,
.rating-stars input:checked ~ label {
    color: #eab308; /* Gold color */
}
.rating-stars label:active {
    transform: scale(0.9);
}
</style>
<body>
  <?php include 'partials/sidebar.php'; ?>
  <div class="wrapper d-flex flex-column min-vh-100">
    <?php 
    $pageTitle = 'Beri Ulasan';
    include 'partials/header.php'; 
    ?>
    <div class="body flex-grow-1">
      <div class="container-lg px-4">
        
        <div class="mb-4">
          <a href="users.php" class="btn btn-outline-secondary btn-sm"><i class="fa fa-arrow-left me-1"></i> Kembali ke Riwayat Transaksi</a>
        </div>

        <div class="row justify-content-center">
          <div class="col-lg-8">
            
            <?php if ($error): ?>
                <div class="alert alert-danger border-0 bg-danger bg-opacity-10 text-danger p-3 mb-4" style="border-radius: 8px;">
                    <i class="fa fa-exclamation-triangle me-2"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <div class="card shadow-sm border-0 mb-4" style="border-radius: 12px;">
              <div class="card-header bg-primary text-white py-3" style="border-top-left-radius: 12px; border-top-right-radius: 12px;">
                <h5 class="mb-0 fw-bold"><i class="fa fa-star me-2"></i>Berikan Ulasan Perjalanan Anda</h5>
              </div>
              <div class="card-body p-4 text-center">
                
                <div class="d-flex flex-column align-items-center mb-4 border-bottom pb-4">
                  <div class="avatar avatar-xl bg-body-secondary border border-secondary mb-3" style="width: 80px; height: 80px; border-radius: 12px; overflow: hidden; display: flex; align-items: center; justify-content: center;">
                    <?php 
                    $car_img = (!empty($rental['gambar']) && file_exists('uploads/' . $rental['gambar'])) ? 'uploads/' . $rental['gambar'] : '';
                    if ($car_img): ?>
                      <img src="<?= $car_img ?>" alt="Car" style="width: 100%; height: 100%; object-fit: cover;">
                    <?php else: ?>
                      <i class="fa fa-car fa-2x text-muted"></i>
                    <?php endif; ?>
                  </div>
                  <h5 class="fw-bold mb-1"><?= htmlspecialchars($rental['nama_kendaraan']) ?></h5>
                  <span class="badge bg-secondary bg-opacity-10 text-secondary mb-3 text-uppercase"><?= htmlspecialchars($rental['nama_merk'] ?? 'Premium') ?></span>
                  
                  <div class="text-muted small">Nomor Invoice: <strong class="text-body-emphasis">#INV-<?= str_pad($id_sewa, 5, '0', STR_PAD_LEFT) ?></strong></div>
                  <div class="text-muted small">Periode Sewa: <strong class="text-body-emphasis"><?= date('d M Y', strtotime($rental['tanggal_sewa'])) ?> - <?= date('d M Y', strtotime($rental['tanggal_kembali'])) ?></strong></div>
                </div>

                <form method="POST" action="" class="text-start" novalidate>
                  <div class="mb-4 text-center">
                    <label class="form-label fw-bold d-block mb-3 fs-6">Seberapa Puas Anda dengan Layanan & Kendaraan Kami? *</label>
                    <div class="rating-stars">
                      <input type="radio" id="star5" name="rating" value="5" required>
                      <label for="star5" title="Sangat Puas (5 Bintang)"><i class="fas fa-star"></i></label>
                      <input type="radio" id="star4" name="rating" value="4">
                      <label for="star4" title="Puas (4 Bintang)"><i class="fas fa-star"></i></label>
                      <input type="radio" id="star3" name="rating" value="3">
                      <label for="star3" title="Cukup Puas (3 Bintang)"><i class="fas fa-star"></i></label>
                      <input type="radio" id="star2" name="rating" value="2">
                      <label for="star2" title="Kurang Puas (2 Bintang)"><i class="fas fa-star"></i></label>
                      <input type="radio" id="star1" name="rating" value="1">
                      <label for="star1" title="Tidak Puas (1 Bintang)"><i class="fas fa-star"></i></label>
                    </div>
                  </div>

                  <div class="mb-4">
                    <label class="form-label fw-bold" for="ulasan">Tulis Ulasan / Pengalaman Anda *</label>
                    <textarea class="form-control" id="ulasan" name="ulasan" rows="4" placeholder="Ceritakan bagaimana performa kendaraan, kebersihan, dan kenyamanan perjalanan Anda..." required></textarea>
                    <div class="form-text text-muted">Ulasan Anda akan membantu pelanggan lain dalam memilih kendaraan terbaik.</div>
                  </div>

                  <div class="mt-4">
                    <button type="submit" class="btn btn-primary w-100 py-2.5 fw-bold text-white shadow-sm"><i class="fa fa-paper-plane me-2"></i>Kirim Ulasan</button>
                  </div>
                </form>

              </div>
            </div>

          </div>
        </div>

      </div>
    </div>
    <?php include 'partials/footer.php'; ?>
  </div>
</body>
</html>
