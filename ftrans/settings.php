<?php
require_once 'config.php';

// Verifikasi autentikasi dan peran (role)
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: dashboard.php?error=unauthorized');
    exit;
}

$error   = '';
$success = '';

// Ambil pengaturan yang saat ini aktif
$settings = [];
$res = mysqli_query($mysqli, "SELECT setting_key, setting_value FROM landing_settings");
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

// Tangani pengiriman formulir (form submission)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $hero_title = trim($_POST['hero_title'] ?? '');
    $hero_subtitle = trim($_POST['hero_subtitle'] ?? '');
    
    if (empty($hero_title) || empty($hero_subtitle)) {
        $error = 'Judul dan deskripsi landing page wajib diisi.';
    } else {
        // Tangani pengunggahan gambar
        $hero_image = $settings['hero_image'] ?? '';
        if (isset($_FILES['hero_image']) && $_FILES['hero_image']['error'] === UPLOAD_ERR_OK) {
            $tmp = $_FILES['hero_image']['tmp_name'];
            $ext = pathinfo($_FILES['hero_image']['name'], PATHINFO_EXTENSION);
            $new_filename = 'landing_hero_' . time() . '.' . $ext;
            
            if (move_uploaded_file($tmp, 'uploads/' . $new_filename)) {
                // Hapus gambar lama jika merupakan unggahan lokal
                if (!empty($hero_image) && strpos($hero_image, 'http') === false && file_exists('uploads/' . $hero_image)) {
                    unlink('uploads/' . $hero_image);
                }
                $hero_image = $new_filename;
            } else {
                $error = 'Gagal mengunggah gambar baru.';
            }
        }

        if (empty($error)) {
            // Simpan perubahan judul (hero_title)
            $stmt = mysqli_prepare($mysqli, "INSERT INTO landing_settings (setting_key, setting_value) VALUES ('hero_title', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            mysqli_stmt_bind_param($stmt, 'ss', $hero_title, $hero_title);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            // Simpan perubahan deskripsi (hero_subtitle)
            $stmt = mysqli_prepare($mysqli, "INSERT INTO landing_settings (setting_key, setting_value) VALUES ('hero_subtitle', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            mysqli_stmt_bind_param($stmt, 'ss', $hero_subtitle, $hero_subtitle);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            // Simpan perubahan gambar (hero_image)
            $stmt = mysqli_prepare($mysqli, "INSERT INTO landing_settings (setting_key, setting_value) VALUES ('hero_image', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            mysqli_stmt_bind_param($stmt, 'ss', $hero_image, $hero_image);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            $success = 'Pengaturan landing page berhasil disimpan!';
            
            // Segarkan kembali pengaturan yang disimpan
            $settings['hero_title'] = $hero_title;
            $settings['hero_subtitle'] = $hero_subtitle;
            $settings['hero_image'] = $hero_image;
        }
    }
}

$nama_user = htmlspecialchars($_SESSION['user_nama']);
?>
<!DOCTYPE html>
<html lang="id">
<?php
$title = 'Pengaturan Landing Page — FTrans';
include 'partials/head.php';
?>
<body>
  <?php 
  $activePage = 'settings';
  include 'partials/sidebar.php'; 
  ?>
  <div class="wrapper d-flex flex-column min-vh-100">
    <?php 
    $pageTitle = 'Pengaturan Landing Page';
    include 'partials/header.php'; 
    ?>
    <div class="body flex-grow-1">
      <div class="container-lg px-4">
        
        <div class="row justify-content-center">
          <div class="col-12 col-lg-8">
            <div class="card mb-4 shadow-sm border border-secondary border-opacity-10">
              <div class="card-header bg-body-tertiary">
                <h5 class="mb-0 text-body"><i class="fa fa-cog me-2 text-primary"></i>Pengaturan Landing Page</h5>
              </div>
              <div class="card-body p-4">
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

                <form method="POST" action="" enctype="multipart/form-data" class="row g-3">
                  <div class="col-12">
                    <label class="form-label fw-bold text-body" for="hero_title">Judul Hero (Headline)</label>
                    <textarea id="hero_title" name="hero_title" rows="2" class="form-control" required><?= htmlspecialchars($settings['hero_title'] ?? '') ?></textarea>
                    <div class="form-text text-muted">Judul besar utama yang tampil di bagian atas halaman depan.</div>
                  </div>
                  
                  <div class="col-12">
                    <label class="form-label fw-bold text-body" for="hero_subtitle">Deskripsi Hero (Subtitle)</label>
                    <textarea id="hero_subtitle" name="hero_subtitle" rows="4" class="form-control" required><?= htmlspecialchars($settings['hero_subtitle'] ?? '') ?></textarea>
                    <div class="form-text text-muted">Deskripsi penjelasan paragraf yang tampil tepat di bawah judul utama.</div>
                  </div>

                  <div class="col-12"><hr class="my-3 text-body-secondary"></div>
                  
                  <div class="col-12">
                    <label class="form-label fw-bold text-body" for="hero_image">Gambar Hero</label>
                    
                    <div class="mb-3">
                      <?php 
                      $current_img = $settings['hero_image'] ?? '';
                      $img_src = (strpos($current_img, 'http') !== false) ? $current_img : 'uploads/' . $current_img;
                      if (!empty($current_img)):
                      ?>
                        <div class="text-muted small mb-2">Gambar Saat Ini:</div>
                        <img src="<?= htmlspecialchars($img_src) ?>" alt="Landing Hero Preview" class="img-thumbnail" style="max-height: 200px; max-width: 100%; object-fit: cover;">
                      <?php else: ?>
                        <div class="text-muted small mb-2">Belum ada gambar yang dikonfigurasi.</div>
                      <?php endif; ?>
                    </div>

                    <input type="file" id="hero_image" name="hero_image" class="form-control" accept="image/*">
                    <div class="form-text text-muted">Unggah gambar baru untuk mengganti gambar mobil sport di bagian samping hero section. Format: JPG, PNG, WEBP.</div>
                  </div>
                  
                  <div class="col-12 mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary"><i class="fa fa-save me-1"></i> Simpan Pengaturan</button>
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
  </div>
  <!-- CoreUI Bundle -->
  <script src="vendors/@coreui/coreui/js/coreui.bundle.min.js"></script>
</body>
</html>
