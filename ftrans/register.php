<?php
require_once 'config.php';

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama     = trim($_POST['nama']     ?? '');
    $email    = trim($_POST['email']    ?? '');
    $no_hp    = trim($_POST['no_hp']    ?? '');
    $password = $_POST['password']      ?? '';
    $konfirm  = $_POST['konfirmasi']    ?? '';

    if (empty($nama) || empty($email) || empty($no_hp) || empty($password) || empty($konfirm)) {
        $error = 'Semua field wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid.';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter.';
    } elseif ($password !== $konfirm) {
        $error = 'Konfirmasi password tidak cocok.';
    } else {
        $cek = mysqli_prepare($mysqli, "SELECT id FROM users WHERE email = ?");
        mysqli_stmt_bind_param($cek, 's', $email);
        mysqli_stmt_execute($cek);
        mysqli_stmt_store_result($cek);

        if (mysqli_stmt_num_rows($cek) > 0) {
            $error = 'Email sudah terdaftar. Gunakan email lain.';
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $ins = mysqli_prepare($mysqli, "INSERT INTO users (nama, email, password, no_hp) VALUES (?, ?, ?, ?)");
            mysqli_stmt_bind_param($ins, 'ssss', $nama, $email, $hashed, $no_hp);

            if (mysqli_stmt_execute($ins)) {
                $success = 'Akun berhasil dibuat! Silakan <a href="login.php" class="text-primary fw-bold">login di sini</a>.';
            } else {
                $error = 'Terjadi kesalahan. Coba lagi.';
            }
            mysqli_stmt_close($ins);
        }
        mysqli_stmt_close($cek);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <title>Sign Up — FTrans</title>
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
            <div class="d-flex justify-content-center mb-2">
              <img src="assets/img/logo.png" alt="FTrans Logo" style="height: 52px;">
            </div>
          </div>
          <div class="card p-4 shadow-sm">
            <div class="card-body d-flex flex-column gap-4">
              <h2 class="h4 text-center mb-0">Create your account</h2>
              
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
                  <label class="form-label" for="nama">Full Name</label>
                  <input class="form-control" id="nama" name="nama" type="text" placeholder="Your full name" value="<?= htmlspecialchars($_POST['nama'] ?? '') ?>" required>
                </div>
                <div class="col-12">
                  <label class="form-label" for="email">Email Address</label>
                  <input class="form-control" id="email" name="email" type="email" placeholder="your@email.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                </div>
                <div class="col-12">
                  <label class="form-label" for="no_hp">Nomor Handphone</label>
                  <input class="form-control" id="no_hp" name="no_hp" type="text" placeholder="Contoh: 08123456789" value="<?= htmlspecialchars($_POST['no_hp'] ?? '') ?>" required>
                </div>
                <div class="col-12">
                  <label class="form-label" for="password">Password (Min. 6 characters)</label>
                  <input class="form-control" id="password" name="password" type="password" placeholder="Choose a password" required>
                </div>
                <div class="col-12">
                  <label class="form-label" for="konfirmasi">Confirm Password</label>
                  <input class="form-control" id="konfirmasi" name="konfirmasi" type="password" placeholder="Retype password" required>
                </div>
                <div class="col-12 mt-4">
                  <button class="btn btn-primary w-100 py-2" type="submit">Sign Up</button>
                </div>
              </form>
              <?php endif; ?>
            </div>
          </div>
          <div class="text-center text-body-secondary">
            Already have an Account?
            <a href="login.php" class="text-decoration-none fw-semibold">Sign In</a>
          </div>
        </div>
      </div>
    </div>
    <!-- CoreUI and necessary plugins-->
    <script src="vendors/@coreui/coreui/js/coreui.bundle.min.js"></script>
  </body>
</html>