<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$nama_user = htmlspecialchars($_SESSION['user_nama']);
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kode  = trim($_POST['kode_unik_kendaraan'] ?? '');
    $nama  = trim($_POST['nama_kendaraan']      ?? '');
    $jenis = trim($_POST['jenis_kendaraan']     ?? '');
    $harga = $_POST['harga_per_hari']           ?? '';

    if (empty($kode) || empty($nama) || empty($jenis) || $harga === '') {
        $error = 'Semua field wajib diisi.';
    } elseif (!is_numeric($harga) || $harga < 0) {
        $error = 'Harga per hari harus berupa angka positif.';
    } else {
        $cek = mysqli_prepare($mysqli, "SELECT kode_unik_kendaraan FROM kendaraan WHERE kode_unik_kendaraan = ?");
        mysqli_stmt_bind_param($cek, 's', $kode);
        mysqli_stmt_execute($cek);
        mysqli_stmt_store_result($cek);

        if (mysqli_stmt_num_rows($cek) > 0) {
            $error = 'Kode kendaraan sudah digunakan. Gunakan kode lain.';
        } else {
            // Proses Upload Gambar
            $gambar_nama = null;
            if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] === UPLOAD_ERR_OK) {
                $tmp = $_FILES['gambar']['tmp_name'];
                $ext = pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION);
                $gambar_nama = time() . '_' . $kode . '.' . $ext;
                move_uploaded_file($tmp, 'uploads/' . $gambar_nama);
            }

            $stmt = mysqli_prepare($mysqli, "INSERT INTO kendaraan (kode_unik_kendaraan, nama_kendaraan, jenis_kendaraan, harga_per_hari, gambar) VALUES (?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, 'sssds', $kode, $nama, $jenis, $harga, $gambar_nama);

            if (mysqli_stmt_execute($stmt)) {
                header('Location: dashboard.php?msg=added');
                exit;
            } else {
                $error = 'Gagal menyimpan data. Coba lagi.';
            }
            mysqli_stmt_close($stmt);
        }
        mysqli_stmt_close($cek);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<?php
$title = 'Tambah Kendaraan — FTrans';
include 'partials/head.php';
?>
<body>
  <?php 
  $activePage = 'add';
  include 'partials/sidebar.php'; 
  ?>
  <div class="wrapper d-flex flex-column min-vh-100">
    <?php 
    $pageTitle = 'Tambah Kendaraan Baru';
    include 'partials/header.php'; 
    ?>
    <div class="body flex-grow-1">
      <div class="container-lg px-4">
        
        <div class="row">
          <div class="col-12 col-lg-8">
            <div class="card mb-4 shadow-sm border border-secondary border-opacity-10">
              <div class="card-header bg-body-tertiary">
                <h5 class="mb-0 text-body"><i class="fa fa-plus me-2 text-primary"></i>Form Data Kendaraan Baru</h5>
              </div>
              <div class="card-body p-4">
                <?php if ($error): ?>
                    <div class="alert alert-danger border-0 bg-danger bg-opacity-10 text-danger small py-2 px-3 mb-4">
                        <i class="fa fa-exclamation-triangle me-1"></i> <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" enctype="multipart/form-data" novalidate class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label" for="kode_unik">Kode Unik Kendaraan *</label>
                    <input type="text" id="kode_unik" name="kode_unik_kendaraan" class="form-control" placeholder="Contoh: 1122" value="<?= htmlspecialchars($_POST['kode_unik_kendaraan'] ?? '') ?>" required>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label" for="nama">Nama Kendaraan *</label>
                    <input type="text" id="nama" name="nama_kendaraan" class="form-control" placeholder="Contoh: Toyota Avanza" value="<?= htmlspecialchars($_POST['nama_kendaraan'] ?? '') ?>" required>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label" for="jenis">Jenis Kendaraan *</label>
                    <select id="jenis" name="jenis_kendaraan" class="form-select" required>
                      <option value="" disabled <?= empty($_POST['jenis_kendaraan']) ? 'selected' : '' ?>>-- Pilih Jenis Kendaraan --</option>
                      <option value="Roda 2" <?= ($_POST['jenis_kendaraan'] ?? '') === 'Roda 2' ? 'selected' : '' ?>>Roda 2 (Motor)</option>
                      <option value="Roda 4" <?= ($_POST['jenis_kendaraan'] ?? '') === 'Roda 4' ? 'selected' : '' ?>>Roda 4 (Mobil)</option>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label" for="harga">Harga per Hari (Rp) *</label>
                    <input type="number" id="harga" name="harga_per_hari" class="form-control" placeholder="Contoh: 150000" min="0" value="<?= htmlspecialchars($_POST['harga_per_hari'] ?? '') ?>" required>
                  </div>
                  <div class="col-12">
                    <label class="form-label" for="gambar">Upload Gambar Kendaraan (Opsional)</label>
                    <input type="file" id="gambar" name="gambar" class="form-control" accept="image/*">
                  </div>
                  <div class="col-12 mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary"><i class="fa fa-save me-1"></i> Simpan</button>
                    <a href="dashboard.php" class="btn btn-secondary">Batal</a>
                  </div>
                </form>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
    <?php include 'partials/footer.php'; ?>