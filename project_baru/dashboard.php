<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$nama_user = htmlspecialchars($_SESSION['user_nama']);

// Menangkap keyword pencarian dari URL
$search = trim($_GET['search'] ?? '');

if (!empty($search)) {
    // Logika Search: Menggunakan LIKE dengan % untuk mencari data yang mengandung keyword
    $search_param = "%" . $search . "%";
    
    $stmt = mysqli_prepare($mysqli, "SELECT * FROM kendaraan WHERE kode_unik_kendaraan LIKE ? OR nama_kendaraan LIKE ? OR jenis_kendaraan LIKE ? ORDER BY kode_unik_kendaraan ASC");
    mysqli_stmt_bind_param($stmt, 'sss', $search_param, $search_param, $search_param);
    mysqli_stmt_execute($stmt);
    
    $result = mysqli_stmt_get_result($stmt);
    $kendaraan = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
} else {
    // Jika tidak ada pencarian, tampilkan semua data
    $result = mysqli_query($mysqli, "SELECT * FROM kendaraan ORDER BY kode_unik_kendaraan ASC");
    $kendaraan = mysqli_fetch_all($result, MYSQLI_ASSOC);
}

$total = count($kendaraan);
$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<?php
$title = 'Dashboard — FTrans';
include 'partials/head.php';
?>
<body>
  <?php 
  $activePage = 'dashboard';
  include 'partials/sidebar.php'; 
  ?>
  <div class="wrapper d-flex flex-column min-vh-100">
    <?php 
    $pageTitle = 'Data Kendaraan';
    include 'partials/header.php'; 
    ?>
    <div class="body flex-grow-1">
      <div class="container-lg px-4">
        
        <?php if ($msg === 'added'): ?>
            <div class="alert alert-success border-0 text-success bg-success bg-opacity-10 py-2 px-3 mb-4">
                <i class="fa fa-check-circle me-1"></i> Kendaraan berhasil ditambahkan.
            </div>
        <?php elseif ($msg === 'updated'): ?>
            <div class="alert alert-success border-0 text-success bg-success bg-opacity-10 py-2 px-3 mb-4">
                <i class="fa fa-check-circle me-1"></i> Data berhasil diperbarui.
            </div>
        <?php elseif ($msg === 'deleted'): ?>
            <div class="alert alert-danger border-0 text-danger bg-danger bg-opacity-10 py-2 px-3 mb-4">
                <i class="fa fa-trash me-1"></i> Kendaraan berhasil dihapus.
            </div>
        <?php endif; ?>

        <div class="card mb-4 shadow-sm border border-secondary border-opacity-10">
          <div class="card-header d-flex flex-column flex-md-row align-items-center justify-content-between gap-3 bg-body-tertiary">
            <h5 class="mb-0 text-body"><i class="fa fa-list me-2 text-primary"></i>Daftar Kendaraan</h5>
            
            <div class="d-flex flex-column flex-sm-row gap-2 w-100 w-md-auto justify-content-end align-items-sm-center">
              <form action="dashboard.php" method="GET" class="d-flex w-100 w-sm-auto">
                <input type="text" name="search" class="form-control form-control-sm me-2" placeholder="Cari nama, kode, jenis..." value="<?= htmlspecialchars($search) ?>" style="min-width: 200px;">
                <button type="submit" class="btn btn-primary btn-sm me-1"><i class="fa fa-search"></i></button>
                <?php if(!empty($search)): ?>
                    <a href="dashboard.php" class="btn btn-danger btn-sm" title="Reset Pencarian"><i class="fa fa-times"></i></a>
                <?php endif; ?>
              </form>
              <a href="add.php" class="btn btn-primary btn-sm text-nowrap mt-2 mt-sm-0"><i class="fa fa-plus me-1"></i> Tambah</a>
            </div>
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-hover table-striped align-middle mb-0">
                <thead class="table-light text-body-secondary fw-semibold small">
                  <tr>
                    <th scope="col" class="ps-4">No</th>
                    <th scope="col">Gambar</th>
                    <th scope="col">Kode Unik</th>
                    <th scope="col">Nama Kendaraan</th>
                    <th scope="col">Jenis</th>
                    <th scope="col">Harga / Hari</th>
                    <th scope="col" class="pe-4 text-end">Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if ($total > 0): ?>
                      <?php foreach ($kendaraan as $i => $k): ?>
                      <tr>
                          <td class="ps-4 fw-semibold text-body-secondary"><?= $i + 1 ?></td>
                          <td>
                              <?php if (!empty($k['gambar']) && file_exists('uploads/' . $k['gambar'])): ?>
                                  <img src="uploads/<?= htmlspecialchars($k['gambar']) ?>" alt="Kendaraan" class="rounded border" style="width: 70px; height: 50px; object-fit: cover;">
                              <?php else: ?>
                                  <span class="text-muted small">No Image</span>
                              <?php endif; ?>
                          </td>
                          <td>
                              <span class="badge bg-dark text-warning border border-warning px-2 py-1 fs-6"><?= htmlspecialchars($k['kode_unik_kendaraan']) ?></span>
                          </td>
                          <td class="text-body fw-bold"><?= htmlspecialchars($k['nama_kendaraan']) ?></td>
                          <td>
                              <span class="badge bg-info bg-opacity-10 text-info px-2 py-1">
                                  <?= (strtolower($k['jenis_kendaraan']) === 'roda 2') ? 'Roda 2' : 'Roda 4' ?>
                              </span>
                          </td>
                          <td class="text-body fw-semibold">Rp <?= number_format($k['harga_per_hari'], 0, ',', '.') ?></td>
                          <td class="pe-4 text-end">
                              <div class="btn-group btn-group-sm" role="group">
                                  <a class="btn btn-outline-info d-flex align-items-center gap-1" href="edit.php?kode=<?= urlencode($k['kode_unik_kendaraan']) ?>">
                                      <i class="fa fa-edit"></i> Edit
                                  </a>
                                  <a class="btn btn-outline-danger d-flex align-items-center gap-1" href="delete.php?kode=<?= urlencode($k['kode_unik_kendaraan']) ?>" onclick="return confirm('Yakin hapus kendaraan ini?')">
                                      <i class="fa fa-trash"></i> Hapus
                                  </a>
                              </div>
                          </td>
                      </tr>
                      <?php endforeach; ?>
                  <?php else: ?>
                      <tr>
                          <td colspan="7" class="text-center py-5 text-muted">
                              <?php if(!empty($search)): ?>
                                  <i class="fa fa-info-circle fa-2x mb-3 text-muted d-block"></i>
                                  Kendaraan dengan kata kunci "<b><?= htmlspecialchars($search) ?></b>" tidak ditemukan.
                              <?php else: ?>
                                  <i class="fa fa-folder-open fa-2x mb-3 text-muted d-block"></i>
                                  Belum ada data kendaraan.
                              <?php endif; ?>
                          </td>
                      </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

      </div>
    </div>
    <?php include 'partials/footer.php'; ?>