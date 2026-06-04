<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$kode  = trim($_GET['kode'] ?? '');
$error = '';
$nama_user = htmlspecialchars($_SESSION['user_nama']);

if (empty($kode)) {
    header('Location: dashboard.php');
    exit;
}

$stmt = mysqli_prepare($mysqli, "SELECT * FROM kendaraan WHERE kode_unik_kendaraan = ?");
mysqli_stmt_bind_param($stmt, 's', $kode);
mysqli_stmt_execute($stmt);
$result     = mysqli_stmt_get_result($stmt);
$kendaraan  = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$kendaraan) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_baru  = trim($_POST['nama_kendaraan']  ?? '');
    $jenis_baru = trim($_POST['jenis_kendaraan'] ?? '');
    $harga_baru = $_POST['harga_per_hari']       ?? '';
    $gambar_baru = $kendaraan['gambar'];

    if (empty($nama_baru) || empty($jenis_baru) || $harga_baru === '') {
        $error = 'Semua field wajib diisi.';
    } elseif (!is_numeric($harga_baru) || $harga_baru < 0) {
        $error = 'Harga per hari harus berupa angka positif.';
    } else {
        // Cek jika upload gambar baru
        if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] === UPLOAD_ERR_OK) {
            $tmp = $_FILES['gambar']['tmp_name'];
            $ext = pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION);
            $nama_file_baru = time() . '_' . $kode . '.' . $ext;
            
            if (move_uploaded_file($tmp, 'uploads/' . $nama_file_baru)) {
                if (!empty($kendaraan['gambar']) && file_exists('uploads/' . $kendaraan['gambar'])) {
                    unlink('uploads/' . $kendaraan['gambar']);
                }
                $gambar_baru = $nama_file_baru;
            }
        }

        $upd = mysqli_prepare($mysqli, "UPDATE kendaraan SET nama_kendaraan = ?, jenis_kendaraan = ?, harga_per_hari = ?, gambar = ? WHERE kode_unik_kendaraan = ?");
        mysqli_stmt_bind_param($upd, 'ssdss', $nama_baru, $jenis_baru, $harga_baru, $gambar_baru, $kode);

        if (mysqli_stmt_execute($upd)) {
            header('Location: dashboard.php?msg=updated');
            exit;
        } else {
            $error = 'Gagal memperbarui data. Coba lagi.';
        }
        mysqli_stmt_close($upd);
    }
    $kendaraan['nama_kendaraan']  = $nama_baru;
    $kendaraan['jenis_kendaraan'] = $jenis_baru;
    $kendaraan['harga_per_hari']  = $harga_baru;
    $kendaraan['gambar']          = $gambar_baru;
}
?>
<!DOCTYPE html>
<html lang="id">
<?php
$title = 'Edit Kendaraan — FTrans';
include 'partials/head.php';
?>
<body>
  <?php 
  $activePage = 'dashboard';
  include 'partials/sidebar.php'; 
  ?>
  <div class="wrapper d-flex flex-column min-vh-100">
    <?php 
    $pageTitle = 'Edit Data Kendaraan';
    include 'partials/header.php'; 
    ?>
    <div class="body flex-grow-1">
      <div class="container-lg px-4">
        
        <div class="row">
          <div class="col-12 col-lg-8">
            <div class="card mb-4 shadow-sm border border-secondary border-opacity-10">
              <div class="card-header bg-body-tertiary">
                <h5 class="mb-0 text-body"><i class="fa fa-edit me-2 text-primary"></i>Edit Data Kendaraan</h5>
              </div>
              <div class="card-body p-4">
                <?php if ($error): ?>
                    <div class="alert alert-danger border-0 bg-danger bg-opacity-10 text-danger small py-2 px-3 mb-4">
                        <i class="fa fa-exclamation-triangle me-1"></i> <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" enctype="multipart/form-data" novalidate class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label" for="kode_unik">Kode Unik Kendaraan</label>
                    <input type="text" id="kode_unik" class="form-control text-warning bg-dark border border-secondary" value="<?= htmlspecialchars($kode) ?>" readonly disabled>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label" for="nama">Nama Kendaraan *</label>
                    <input type="text" id="nama" name="nama_kendaraan" class="form-control" value="<?= htmlspecialchars($kendaraan['nama_kendaraan']) ?>" required>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label" for="jenis">Jenis Kendaraan *</label>
                    <select id="jenis" name="jenis_kendaraan" class="form-select" required>
                      <?php $j_curr = strtolower(trim($kendaraan['jenis_kendaraan'])); ?>
                      <option value="Roda 2" <?= $j_curr === 'roda 2' ? 'selected' : '' ?>>Roda 2 (Motor)</option>
                      <option value="Roda 4" <?= $j_curr === 'roda 4' ? 'selected' : '' ?>>Roda 4 (Mobil)</option>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label" for="harga">Harga per Hari (Rp) *</label>
                    <input type="number" id="harga" name="harga_per_hari" class="form-control" min="0" value="<?= htmlspecialchars($kendaraan['harga_per_hari']) ?>" required>
                  </div>
                  <div class="col-12 my-3">
                    <label class="form-label d-block">Gambar Saat Ini</label>
                    <?php if (!empty($kendaraan['gambar']) && file_exists('uploads/' . $kendaraan['gambar'])): ?>
                        <img src="uploads/<?= htmlspecialchars($kendaraan['gambar']) ?>" alt="Mobil" class="rounded mb-2 img-thumbnail" style="width: 150px; height: 100px; object-fit: cover;">
                    <?php else: ?>
                        <span class="badge bg-dark mb-2 py-2 px-3">Belum ada gambar</span>
                    <?php endif; ?>
                    <input type="file" id="gambar" name="gambar" class="form-control" accept="image/*">
                    <div class="form-text text-muted">Biarkan kosong jika tidak ingin mengubah gambar.</div>
                  </div>
                  <div class="col-12 mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-info text-white"><i class="fa fa-save me-1"></i> Simpan Perubahan</button>
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