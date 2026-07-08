<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$error   = '';
$success = '';

$stmt = mysqli_prepare($mysqli, "SELECT nama, email, no_hp FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user   = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_baru  = trim($_POST['nama'] ?? '');
    $email_baru = trim($_POST['email'] ?? '');
    $no_hp_baru = trim($_POST['no_hp'] ?? '');
    $pass_baru  = $_POST['password'] ?? '';

    if (empty($nama_baru) || empty($email_baru) || empty($no_hp_baru)) {
        $error = 'Nama, Email, dan Nomor Handphone wajib diisi.';
    } elseif (!filter_var($email_baru, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid.';
    } else {
        $cek_email = mysqli_prepare($mysqli, "SELECT id FROM users WHERE email = ? AND id != ?");
        mysqli_stmt_bind_param($cek_email, 'si', $email_baru, $user_id);
        mysqli_stmt_execute($cek_email);
        mysqli_stmt_store_result($cek_email);

        if (mysqli_stmt_num_rows($cek_email) > 0) {
            $error = 'Email tersebut sudah digunakan oleh akun lain.';
        } else {
            if (empty($pass_baru)) {
                $upd = mysqli_prepare($mysqli, "UPDATE users SET nama = ?, email = ?, no_hp = ? WHERE id = ?");
                mysqli_stmt_bind_param($upd, 'sssi', $nama_baru, $email_baru, $no_hp_baru, $user_id);
            } else {
                if(strlen($pass_baru) < 6) {
                    $error = 'Password baru minimal 6 karakter.';
                } else {
                    $hashed = password_hash($pass_baru, PASSWORD_DEFAULT);
                    $upd = mysqli_prepare($mysqli, "UPDATE users SET nama = ?, email = ?, password = ?, no_hp = ? WHERE id = ?");
                    mysqli_stmt_bind_param($upd, 'ssssi', $nama_baru, $email_baru, $hashed, $no_hp_baru, $user_id);
                }
            }

            if (empty($error)) {
                if (mysqli_stmt_execute($upd)) {
                    $success = 'Profil berhasil diperbarui!';
                    $_SESSION['user_nama'] = $nama_baru;
                    $user['nama'] = $nama_baru;
                    $user['email'] = $email_baru;
                    $user['no_hp'] = $no_hp_baru;
                } else {
                    $error = 'Gagal memperbarui profil.';
                }
                mysqli_stmt_close($upd);
            }
        }
        mysqli_stmt_close($cek_email);
    }
}

$nama_user = htmlspecialchars($_SESSION['user_nama']);
?>
<!DOCTYPE html>
<html lang="id">
<?php
$title = 'My Profile — FTrans';
include 'partials/head.php';
?>
<body>
  <?php 
  $activePage = 'profile';
  include 'partials/sidebar.php'; 
  ?>
  <div class="wrapper d-flex flex-column min-vh-100">
    <?php 
    $pageTitle = 'My Profile';
    include 'partials/header.php'; 
    ?>
    <div class="body flex-grow-1">
      <div class="container-lg px-4">
        
        <div class="row justify-content-center">
          <div class="col-12 col-lg-8">
            <div class="card mb-4 shadow-sm border border-secondary border-opacity-10">
              <div class="card-header bg-body-tertiary">
                <h5 class="mb-0 text-body"><i class="fa fa-user-edit me-2 text-primary"></i>My Profile</h5>
              </div>
              <div class="card-body p-4">
                <?php if (isset($_GET['incomplete']) || empty($user['no_hp'])): ?>
                    <div class="alert alert-warning border-0 bg-warning bg-opacity-10 text-warning py-3 px-3 mb-4">
                        <i class="fa fa-exclamation-triangle me-1"></i> <strong>Pemberitahuan:</strong> Silakan lengkapi data diri Anda (terutama Nomor Handphone) terlebih dahulu sebelum dapat melanjutkan penggunaan fitur lainnya.
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger border-0 bg-danger bg-opacity-10 text-danger small py-2 px-3 mb-4">
                        <i class="fa fa-exclamation-triangle me-1"></i> <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success border-0 bg-success bg-opacity-10 text-success small py-2 px-3 mb-4">
                        <i class="fa fa-check-circle me-1"></i> <?= htmlspecialchars($success) ?>
                    </div>
                <?php endif; ?>

                <!-- Membership Card Info -->
                <?php
                $membership = getUserMembership($user_id, $mysqli);
                $tier = $membership['tier'];
                $discount_pct = $membership['discount'] * 100;
                $rentals_count = $membership['completed_rentals'];

                $badge_class = 'bg-secondary';
                $border_style = '';
                if ($tier === 'bronze') {
                    $badge_class = 'bg-warning text-dark';
                    $border_style = 'border: 2px dashed #CD7F32;';
                } elseif ($tier === 'silver') {
                    $badge_class = 'bg-info text-dark';
                    $border_style = 'border: 2px dashed #C0C0C0;';
                } elseif ($tier === 'gold') {
                    $badge_class = 'bg-warning text-dark fw-bold';
                    $border_style = 'border: 2px dashed #FFD700;';
                }
                ?>
                <div class="card mb-4 border shadow-sm bg-body-tertiary" style="border-radius: 12px; <?= $border_style ?>">
                  <div class="card-body p-4 d-flex align-items-center justify-content-between flex-wrap gap-3">
                    <div class="d-flex align-items-center gap-3">
                      <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-circle d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                        <i class="fa fa-id-card fa-2x"></i>
                      </div>
                      <div>
                        <h6 class="text-uppercase font-monospace text-muted mb-1" style="font-size: 0.75rem;">Status Keanggotaan</h6>
                        <h4 class="fw-bold text-body-emphasis mb-0 d-flex align-items-center gap-2">
                          <?= ucfirst($tier) ?> Member 
                          <span class="badge <?= $badge_class ?> fs-7 px-2.5 py-1 rounded-pill">
                            Diskon <?= $discount_pct ?>%
                          </span>
                        </h4>
                      </div>
                    </div>
                    <div class="text-md-end">
                      <div class="text-muted small">Total Rental Selesai:</div>
                      <div class="fw-bold fs-5 text-primary"><i class="fa fa-car me-1"></i><?= $rentals_count ?> Transaksi</div>
                    </div>
                  </div>
                </div>

                <form method="POST" action="" novalidate class="row g-3">
                  <div class="col-md-4">
                    <label class="form-label" for="nama">Nama Lengkap</label>
                    <input type="text" id="nama" name="nama" class="form-control" value="<?= htmlspecialchars($user['nama']) ?>" required>
                  </div>
                  <div class="col-md-4">
                    <label class="form-label" for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
                  </div>
                  <div class="col-md-4">
                    <label class="form-label" for="no_hp">Nomor Handphone</label>
                    <input type="text" id="no_hp" name="no_hp" class="form-control" value="<?= htmlspecialchars($user['no_hp'] ?? '') ?>" placeholder="Contoh: 08123456789" required>
                  </div>
                  <div class="col-12"><hr class="my-3 text-body-secondary"></div>
                  <div class="col-12">
                    <label class="form-label" for="password">Password Baru</label>
                    <input type="password" id="password" name="password" class="form-control" placeholder="Kosongkan jika tidak ingin mengubah password">
                    <div class="form-text text-muted">Hanya isi jika Anda ingin mengganti password akun ini.</div>
                  </div>
                  <div class="col-12 mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-success"><i class="fa fa-save me-1"></i> Simpan Profil</button>
                    <a href="dashboard.php" class="btn btn-secondary">Kembali</a>
                  </div>
                </form>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
    <?php include 'partials/footer.php'; ?>