<?php
require_once 'config.php';

$error = '';
$success = '';

$email = trim($_GET['email'] ?? $_POST['email'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp            = trim($_POST['otp'] ?? '');
    $new_password   = $_POST['password'] ?? '';
    $konfirm        = $_POST['konfirmasi'] ?? '';

    if (empty($email)) {
        $error = 'Email wajib diisi.';
    } elseif (empty($otp)) {
        $error = 'Kode OTP wajib diisi.';
    } elseif (empty($new_password) || empty($konfirm)) {
        $error = 'Password baru dan konfirmasinya wajib diisi.';
    } elseif (strlen($new_password) < 6) {
        $error = 'Password minimal 6 karakter.';
    } elseif ($new_password !== $konfirm) {
        $error = 'Konfirmasi password tidak cocok.';
    } else {
        $stmt = mysqli_prepare($mysqli, "SELECT otp_code, otp_expiry FROM users WHERE email = ?");
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);

        if (!$user) {
            $error = 'Email tidak ditemukan.';
        } else {
            $now = date('Y-m-d H:i:s');
            if ($user['otp_code'] !== $otp) {
                $error = 'Kode OTP salah. Silakan periksa kembali email Anda.';
            } elseif ($now > $user['otp_expiry']) {
                $error = 'Kode OTP telah kedaluwarsa. Silakan minta reset password kembali.';
            } else {
                // OTP matches and is valid! Update password
                $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                $update = mysqli_prepare($mysqli, "UPDATE users SET password = ?, is_verified = 1, otp_code = NULL, otp_expiry = NULL WHERE email = ?");
                mysqli_stmt_bind_param($update, 'ss', $hashed, $email);
                if (mysqli_stmt_execute($update)) {
                    $success = 'Kata sandi Anda berhasil diperbarui! Silakan <a href="login.php" class="alert-link fw-bold">Login di sini</a> dengan kata sandi baru Anda.';
                } else {
                    $error = 'Gagal memperbarui kata sandi. Silakan coba lagi.';
                }
                mysqli_stmt_close($update);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <title>Atur Ulang Password — FTrans</title>
    <!-- Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Main styles for this application-->
    <link href="css/style.css" rel="stylesheet">
    <script src="js/color-modes.js"></script>
  </head>
  <body>
    <div class="bg-body-tertiary min-vh-100 d-flex flex-row align-items-center">
      <div class="container" style="max-width: 32rem">
        <div class="d-flex flex-column gap-4">
          <div class="text-center">
            <h2 class="text-primary fw-bold d-flex align-items-center justify-content-center gap-2">
              <i class="fa fa-car fa-lg"></i> FTrans
            </h2>
          </div>
          <div class="card p-4 shadow-sm">
            <div class="card-body d-flex flex-column gap-4">
              <h2 class="h4 text-center mb-0">Atur Ulang Kata Sandi</h2>
              <p class="text-center text-body-secondary small mb-0">Masukkan kode OTP yang dikirimkan ke email Anda beserta kata sandi baru Anda.</p>
              
              <?php if ($error): ?>
                  <div class="alert alert-danger border-0 bg-danger bg-opacity-10 text-danger small py-2 px-3">
                      <i class="fa fa-exclamation-triangle me-1"></i> <?= htmlspecialchars($error) ?>
                  </div>
              <?php endif; ?>
              
              <?php if ($success): ?>
                  <div class="alert alert-success border-0 bg-success bg-opacity-10 text-success small py-2 px-3">
                      <i class="fa fa-check-circle me-1"></i> <?= $success ?>
                  </div>
              <?php endif; ?>

              <?php if (!$success): ?>
              <form class="row g-3" method="POST" action="" autocomplete="off" novalidate>
                <div class="col-12">
                  <label class="form-label" for="email">Alamat Email</label>
                  <input class="form-control" id="email" name="email" type="email" placeholder="your@email.com" value="<?= htmlspecialchars($email) ?>" required>
                </div>
                <div class="col-12">
                  <label class="form-label" for="otp">Kode OTP Reset</label>
                  <input class="form-control text-center fw-bold fs-4" id="otp" name="otp" type="text" maxlength="6" placeholder="******" style="letter-spacing: 4px;" required>
                </div>
                <div class="col-12">
                  <label class="form-label" for="password">Kata Sandi Baru (Min. 6 Karakter)</label>
                  <input class="form-control" id="password" name="password" type="password" placeholder="Password baru" required>
                </div>
                <div class="col-12">
                  <label class="form-label" for="konfirmasi">Konfirmasi Kata Sandi Baru</label>
                  <input class="form-control" id="konfirmasi" name="konfirmasi" type="password" placeholder="Ulangi password baru" required>
                </div>
                <div class="col-12 mt-4">
                  <button class="btn btn-primary w-100 py-2" type="submit">Simpan Kata Sandi Baru</button>
                </div>
              </form>
              <?php endif; ?>
            </div>
          </div>
          <div class="text-center text-body-secondary">
            Kembali ke 
            <a href="login.php" class="text-decoration-none fw-semibold">Sign In</a>
          </div>
        </div>
      </div>
    </div>
    <!-- CoreUI and necessary plugins-->
    <script src="vendors/@coreui/coreui/js/coreui.bundle.min.js"></script>
  </body>
</html>
