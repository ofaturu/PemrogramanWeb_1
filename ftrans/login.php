<?php
require_once 'config.php';

$error = '';

// Check for error queries passed from callback page
if (isset($_GET['error'])) {
    if ($_GET['error'] === 'google_auth') {
        $error = 'Gagal melakukan autentikasi dengan Google.';
    } elseif ($_GET['error'] === 'no_code') {
        $error = 'Kode otorisasi Google tidak ditemukan.';
    } elseif ($_GET['error'] === 'db_error') {
        $error = 'Gagal memproses data pengguna di database.';
    } else {
        $error = htmlspecialchars($_GET['error']);
    }
}

$google_client_id = $_ENV['GOOGLE_CLIENT_ID'] ?? getenv('GOOGLE_CLIENT_ID');
$google_redirect_url = $_ENV['GOOGLE_REDIRECT_URL'] ?? getenv('GOOGLE_REDIRECT_URL');

$google_auth_url = "";
if (!empty($google_client_id) && !empty($google_redirect_url)) {
    $params = [
        'client_id' => $google_client_id,
        'redirect_uri' => $google_redirect_url,
        'response_type' => 'code',
        'scope' => 'openid email profile',
        'access_type' => 'online',
        'prompt' => 'select_account'
    ];
    $google_auth_url = "https://accounts.google.com/o/oauth2/v2/auth?" . http_build_query($params);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Email dan password wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid.';
    } else {
        $stmt = mysqli_prepare($mysqli, "SELECT id, nama, password, role FROM users WHERE email = ?");
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user   = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_nama'] = $user['nama'];
            $_SESSION['user_role'] = $user['role'];
            // Session flag to indicate if they logged in via Google
            $_SESSION['logged_in_via_google'] = false; 
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Email atau password salah.';
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
    <title>Sign In — FTrans</title>
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
              <h2 class="h4 text-center mb-0">Sign In to your account</h2>
              
              <?php if ($error): ?>
                  <div class="alert alert-danger border-0 bg-danger bg-opacity-10 text-danger small py-2 px-3">
                      <i class="fa fa-exclamation-triangle me-1"></i> <?= htmlspecialchars($error) ?>
                  </div>
              <?php endif; ?>

              <form class="row g-3" method="POST" action="" autocomplete="off" novalidate>
                <div class="col-12">
                  <label class="form-label" for="email">Email Address</label>
                  <input class="form-control" id="email" name="email" type="email" placeholder="your@email.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                </div>
                <div class="col-12">
                  <label class="form-label" for="password">Password</label>
                  <input class="form-control" id="password" name="password" type="password" placeholder="Your password" required>
                </div>
                <div class="col-12 mt-4">
                  <button class="btn btn-primary w-100 py-2" type="submit">Sign In</button>
                </div>
              </form>

              <?php if (!empty($google_auth_url)): ?>
                <div class="position-relative text-center my-1">
                  <hr class="text-body-secondary">
                  <span class="position-absolute top-50 start-50 translate-middle bg-body px-3 text-body-secondary small">atau</span>
                </div>
                <div class="col-12">
                  <a href="<?= htmlspecialchars($google_auth_url) ?>" class="btn btn-outline-danger w-100 py-2 d-flex align-items-center justify-content-center gap-2">
                    <i class="fab fa-google"></i> Masuk dengan Google
                  </a>
                </div>
              <?php endif; ?>
            </div>
          </div>
          <div class="text-center text-body-secondary">
            Don't have an Account?
            <a href="register.php" class="text-decoration-none fw-semibold">Sign Up</a>
          </div>
        </div>
      </div>
    </div>
    <!-- CoreUI and necessary plugins-->
    <script src="vendors/@coreui/coreui/js/coreui.bundle.min.js"></script>
  </body>
</html>