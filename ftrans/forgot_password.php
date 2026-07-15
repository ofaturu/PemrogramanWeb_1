<?php
require_once 'config.php';
require_once 'send_invoice.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        $error = 'Email wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid.';
    } else {
        $stmt = mysqli_prepare($mysqli, "SELECT nama FROM users WHERE email = ?");
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);

        if (!$user) {
            $error = 'Email tidak terdaftar di sistem.';
        } else {
            $otp = strval(rand(100000, 999999));
            $otp_expiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));

            $update = mysqli_prepare($mysqli, "UPDATE users SET otp_code = ?, otp_expiry = ? WHERE email = ?");
            mysqli_stmt_bind_param($update, 'sss', $otp, $otp_expiry, $email);
            if (mysqli_stmt_execute($update)) {
                try {
                    send_reset_otp_email($email, $user['nama'], $otp);
                    header('Location: reset_password.php?email=' . urlencode($email) . '&msg=sent');
                    exit;
                } catch (\Exception $e) {
                    error_log("Failed to send reset password OTP email: " . $e->getMessage());
                    $error = 'Gagal mengirim email OTP. Silakan periksa koneksi SMTP Anda.';
                }
            } else {
                $error = 'Terjadi kesalahan sistem saat memproses permintaan reset password.';
            }
            mysqli_stmt_close($update);
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
    <title>Lupa Password — FTrans</title>
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
              <h2 class="h4 text-center mb-0">Lupa Kata Sandi</h2>
              <p class="text-center text-body-secondary small mb-0">Masukkan alamat email Anda. Kami akan mengirimkan kode OTP 6 digit untuk mengatur ulang kata sandi Anda.</p>
              
              <?php if ($error): ?>
                  <div class="alert alert-danger border-0 bg-danger bg-opacity-10 text-danger small py-2 px-3">
                      <i class="fa fa-exclamation-triangle me-1"></i> <?= htmlspecialchars($error) ?>
                  </div>
              <?php endif; ?>

              <form class="row g-3" method="POST" action="" autocomplete="off" novalidate>
                <div class="col-12">
                  <label class="form-label" for="email">Alamat Email</label>
                  <input class="form-control" id="email" name="email" type="email" placeholder="your@email.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                </div>
                <div class="col-12 mt-4">
                  <button class="btn btn-primary w-100 py-2" type="submit">Kirim Kode OTP</button>
                </div>
              </form>
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
