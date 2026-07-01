<?php
require_once 'config.php';

// Secure the page - user must be logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$id_sewa = intval($_GET['id'] ?? 0);
if ($id_sewa <= 0) {
    header('Location: sewa.php');
    exit;
}

// Fetch rental details
$stmt = mysqli_prepare($mysqli, "
    SELECT p.*, u.nama AS nama_user, u.email AS email_user, k.nama_kendaraan, k.harga_per_hari, m.nama_merk 
    FROM penyewaan p 
    JOIN users u ON p.id_user = u.id 
    JOIN kendaraan k ON p.kode_unik_kendaraan = k.kode_unik_kendaraan 
    LEFT JOIN merk_kendaraan m ON k.id_merk = m.id_merk
    WHERE p.id_sewa = ?
");
mysqli_stmt_bind_param($stmt, 'i', $id_sewa);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$rental = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$rental) {
    header('Location: sewa.php');
    exit;
}

// Verify ownership (Non-admins can only see/pay their own bookings)
if (isset($_SESSION['user_role']) && $_SESSION['user_role'] !== 'admin' && $rental['id_user'] != $_SESSION['user_id']) {
    header('Location: sewa.php?error=unauthorized');
    exit;
}

$error = '';
$success = '';

// Handle upload receipt submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($rental['status'] !== 'booking') {
        $error = 'Transaksi ini sudah diproses dan tidak dapat menerima pembayaran baru.';
    } elseif (isset($_FILES['bukti_pembayaran']) && $_FILES['bukti_pembayaran']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['bukti_pembayaran']['tmp_name'];
        $file_name = $_FILES['bukti_pembayaran']['name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        $allowed_exts = ['jpg', 'jpeg', 'png'];
        if (!in_array($file_ext, $allowed_exts)) {
            $error = 'Format file tidak diizinkan. Hanya JPG, JPEG, dan PNG.';
        } else {
            // Ensure uploads directory exists
            $upload_dir = __DIR__ . '/uploads';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Generate unique filename
            $new_filename = 'receipt_' . $id_sewa . '_' . time() . '.' . $file_ext;
            if (move_uploaded_file($file_tmp, $upload_dir . '/' . $new_filename)) {
                // Update database
                $upd_stmt = mysqli_prepare($mysqli, "UPDATE penyewaan SET bukti_pembayaran = ?, waktu_bayar = NOW() WHERE id_sewa = ?");
                mysqli_stmt_bind_param($upd_stmt, 'si', $new_filename, $id_sewa);
                
                if (mysqli_stmt_execute($upd_stmt)) {
                    $success = 'Bukti pembayaran berhasil diunggah! Menunggu konfirmasi admin.';
                    // Refresh rental data
                    $rental['bukti_pembayaran'] = $new_filename;
                    $rental['waktu_bayar'] = date('Y-m-d H:i:s');
                } else {
                    $error = 'Gagal memperbarui database. Coba lagi.';
                }
                mysqli_stmt_close($upd_stmt);
            } else {
                $error = 'Gagal menyimpan file ke server.';
            }
        }
    } else {
        $error = 'Silakan pilih file bukti pembayaran terlebih dahulu.';
    }
}

// Calculate rental duration
$t_sewa = strtotime($rental['tanggal_sewa']);
$t_kembali = strtotime($rental['tanggal_kembali']);
$diff_days = ceil(($t_kembali - $t_sewa) / 86400);
if ($diff_days <= 0) $diff_days = 1;

// Define active page
$activePage = 'sewa';
?>
<!DOCTYPE html>
<html lang="id">
<?php
$title = 'Pembayaran Invoice #' . str_pad($id_sewa, 5, '0', STR_PAD_LEFT) . ' — FTrans';
include 'partials/head.php';
?>
<body>
  <?php include 'partials/sidebar.php'; ?>
  <div class="wrapper d-flex flex-column min-vh-100">
    <?php 
    $pageTitle = 'Pembayaran';
    include 'partials/header.php'; 
    ?>
    <div class="body flex-grow-1">
      <div class="container-lg px-4">
        
        <div class="mb-4">
          <a href="sewa.php" class="btn btn-outline-secondary btn-sm"><i class="fa fa-arrow-left me-1"></i> Kembali ke Data Penyewaan</a>
        </div>

        <div class="row g-4">
          <!-- Left side: Invoice Details -->
          <div class="col-lg-7">
            <div class="card shadow-sm border-0 mb-4" style="border-radius: 12px;">
              <div class="card-header bg-primary text-white py-3" style="border-top-left-radius: 12px; border-top-right-radius: 12px;">
                <h5 class="mb-0 fw-bold"><i class="fa fa-file-invoice me-2"></i>Rincian Tagihan (Invoice)</h5>
              </div>
              <div class="card-body p-4">
                
                <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
                  <div>
                    <h6 class="text-muted small mb-1">Nomor Invoice</h6>
                    <h5 class="fw-bold mb-0">#INV-<?= str_pad($id_sewa, 5, '0', STR_PAD_LEFT) ?></h5>
                  </div>
                  <div>
                    <h6 class="text-muted small mb-1">Status Transaksi</h6>
                    <?php
                    $status = $rental['status'] ?? 'booking';
                    $bukti = $rental['bukti_pembayaran'] ?? '';
                    if ($status === 'booking') {
                        if (empty($bukti)) {
                            echo '<span class="badge bg-danger bg-opacity-10 text-danger px-3 py-2 fw-semibold fs-7 border border-danger border-opacity-25">Belum Dibayar</span>';
                        } else {
                            echo '<span class="badge bg-warning bg-opacity-10 text-warning px-3 py-2 fw-semibold fs-7 border border-warning border-opacity-25">Menunggu Verifikasi</span>';
                        }
                    } elseif ($status === 'sedang_disewa') {
                        echo '<span class="badge bg-success bg-opacity-10 text-success px-3 py-2 fw-semibold fs-7 border border-success border-opacity-25">Sedang Sewa (Lunas)</span>';
                    } elseif ($status === 'selesai') {
                        echo '<span class="badge bg-secondary bg-opacity-10 text-secondary px-3 py-2 fw-semibold fs-7 border border-secondary border-opacity-25">Selesai</span>';
                    } elseif ($status === 'dibatalkan') {
                        echo '<span class="badge bg-dark bg-opacity-10 text-dark px-3 py-2 fw-semibold fs-7 border border-dark border-opacity-25">Dibatalkan</span>';
                    }
                    ?>
                  </div>
                </div>

                <div class="row g-3">
                  <div class="col-sm-6">
                    <span class="text-muted small d-block">Nama Penyewa</span>
                    <span class="fw-bold text-body-emphasis"><?= htmlspecialchars($rental['nama_user']) ?></span>
                  </div>
                  <div class="col-sm-6">
                    <span class="text-muted small d-block">Alamat Email</span>
                    <span class="text-body-emphasis"><?= htmlspecialchars($rental['email_user']) ?></span>
                  </div>
                  <div class="col-sm-6 mt-3">
                    <span class="text-muted small d-block">Nama Kendaraan</span>
                    <span class="fw-bold text-body-emphasis"><?= htmlspecialchars($rental['nama_kendaraan']) ?> (<?= htmlspecialchars(ucwords($rental['nama_merk'] ?? '')) ?>)</span>
                  </div>
                  <div class="col-sm-6 mt-3">
                    <span class="text-muted small d-block">Tarif Sewa / Hari</span>
                    <span class="fw-bold text-body-emphasis">Rp <?= number_format($rental['harga_per_hari'], 0, ',', '.') ?></span>
                  </div>
                  <div class="col-12 mt-3 border-top pt-3">
                    <span class="text-muted small d-block">Periode Penyewaan</span>
                    <span class="text-body-emphasis fw-semibold">
                      <i class="fa fa-calendar-alt me-1 text-primary"></i> 
                      <?= date('d F Y, H:i', $t_sewa) ?> s.d. <?= date('d F Y, H:i', $t_kembali) ?> 
                      <span class="badge bg-info bg-opacity-10 text-info ms-2"><?= $diff_days ?> Hari</span>
                    </span>
                  </div>
                </div>

                <div class="mt-4 bg-light p-3 rounded border d-flex justify-content-between align-items-center">
                  <h5 class="mb-0 fw-bold text-body">Total Biaya</h5>
                  <h4 class="mb-0 fw-bold text-primary">Rp <?= number_format($rental['total_biaya'], 0, ',', '.') ?></h4>
                </div>

              </div>
            </div>
          </div>

          <!-- Right side: Payment Method & Upload Proof -->
          <div class="col-lg-5">
            <?php if ($error): ?>
                <div class="alert alert-danger border-0 bg-danger bg-opacity-10 text-danger p-3 mb-4" style="border-radius: 8px;">
                    <i class="fa fa-exclamation-triangle me-2"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success border-0 bg-success bg-opacity-10 text-success p-3 mb-4" style="border-radius: 8px;">
                    <i class="fa fa-check-circle me-2"></i> <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <div class="card shadow-sm border-0 mb-4" style="border-radius: 12px;">
              <div class="card-header bg-dark text-white py-3" style="border-top-left-radius: 12px; border-top-right-radius: 12px;">
                <h5 class="mb-0 fw-bold"><i class="fa fa-credit-card me-2"></i>Konfirmasi Pembayaran</h5>
              </div>
              <div class="card-body p-4">
                
                <?php if ($status === 'booking' && empty($bukti)): ?>
                    <!-- Form for uploading transfer receipt -->
                    <div class="mb-4">
                      <h6 class="fw-bold mb-2">PILIHAN REKENING BANK:</h6>
                      <div class="p-3 border rounded mb-2 d-flex align-items-center bg-body-tertiary">
                        <i class="fa fa-university fa-2x me-3 text-secondary"></i>
                        <div>
                          <div class="small text-muted fw-bold">BANK BCA</div>
                          <div class="fs-5 fw-bold text-primary">123456789</div>
                          <div class="small text-muted">a.n. FTrans Car Rental</div>
                        </div>
                      </div>
                    </div>

                    <form method="POST" action="" enctype="multipart/form-data" class="row g-3" novalidate>
                      <div class="col-12">
                        <label class="form-label fw-bold" for="bukti_pembayaran">Unggah Bukti Transfer *</label>
                        <input type="file" class="form-control" id="bukti_pembayaran" name="bukti_pembayaran" accept="image/*" required>
                        <div class="form-text text-muted">Harap unggah tangkapan layar (screenshot) bukti transfer resmi dengan nominal yang sesuai. Format: JPG, JPEG, PNG.</div>
                      </div>
                      <div class="col-12 mt-4">
                        <button type="submit" class="btn btn-primary w-100 py-2.5 fw-bold"><i class="fa fa-upload me-2"></i>Kirim Bukti Pembayaran</button>
                      </div>
                    </form>

                <?php elseif (!empty($bukti)): ?>
                    <!-- Proof uploaded, show status info & image preview -->
                    <div class="text-center py-2">
                      <?php if ($status === 'booking'): ?>
                          <div class="mb-3 text-warning">
                            <i class="fa fa-clock fa-3x animate__animated animate__pulse animate__infinite"></i>
                          </div>
                          <h6 class="fw-bold text-warning mb-2">Menunggu Verifikasi Admin</h6>
                          <p class="text-muted small mb-4">Bukti transfer Anda telah kami terima pada tanggal <strong><?= date('d M Y, H:i', strtotime($rental['waktu_bayar'])) ?></strong> dan sedang diverifikasi oleh admin.</p>
                      <?php else: ?>
                          <div class="mb-3 text-success">
                            <i class="fa fa-check-circle fa-3x"></i>
                          </div>
                          <h6 class="fw-bold text-success mb-2">Pembayaran Berhasil / Lunas</h6>
                          <p class="text-muted small mb-4">Pembayaran Anda telah diverifikasi oleh admin pada tanggal <strong><?= date('d M Y, H:i', strtotime($rental['waktu_bayar'])) ?></strong>.</p>
                      <?php endif; ?>
                      
                      <div class="border rounded p-2 bg-body-tertiary">
                        <div class="small text-muted mb-2 text-start fw-bold">Pratinjau Bukti Pembayaran:</div>
                        <img src="uploads/<?= htmlspecialchars($bukti) ?>" alt="Bukti Transfer" class="img-fluid rounded border shadow-sm" style="max-height: 250px;">
                      </div>
                    </div>
                <?php else: ?>
                    <!-- Bookings cancelled without payment -->
                    <div class="text-center py-4 text-muted">
                      <i class="fa fa-ban fa-3x mb-3 text-danger"></i>
                      <h6 class="fw-bold text-danger">Transaksi Dibatalkan</h6>
                      <p class="small mb-0">Transaksi penyewaan ini telah dibatalkan atau kedaluwarsa sehingga pembayaran tidak dapat diproses.</p>
                    </div>
                <?php endif; ?>

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
