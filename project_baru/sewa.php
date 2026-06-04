<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Fetch all rentals with user name and vehicle name
$query = "SELECT p.*, u.nama AS nama_user, k.nama_kendaraan, k.harga_per_hari 
          FROM penyewaan p 
          LEFT JOIN users u ON p.id_user = u.id 
          LEFT JOIN kendaraan k ON p.kode_unik_kendaraan = k.kode_unik_kendaraan 
          ORDER BY p.id_sewa DESC";
$result = mysqli_query($mysqli, $query);
$rentals = mysqli_fetch_all($result, MYSQLI_ASSOC);
$total = count($rentals);

$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<?php
$title = 'Data Penyewaan — FTrans';
include 'partials/head.php';
?>
<body>
  <?php 
  $activePage = 'sewa';
  include 'partials/sidebar.php'; 
  ?>
  <div class="wrapper d-flex flex-column min-vh-100">
    <?php 
    $pageTitle = 'Data Penyewaan';
    include 'partials/header.php'; 
    ?>
    <div class="body flex-grow-1">
      <div class="container-lg px-4">
        
        <?php if ($msg === 'added'): ?>
            <div class="alert alert-success border-0 text-success bg-success bg-opacity-10 py-2 px-3 mb-4">
                <i class="fa fa-check-circle me-1"></i> Transaksi penyewaan berhasil ditambahkan.
            </div>
        <?php elseif ($msg === 'updated'): ?>
            <div class="alert alert-success border-0 text-success bg-success bg-opacity-10 py-2 px-3 mb-4">
                <i class="fa fa-check-circle me-1"></i> Transaksi berhasil diperbarui.
            </div>
        <?php elseif ($msg === 'deleted'): ?>
            <div class="alert alert-danger border-0 text-danger bg-danger bg-opacity-10 py-2 px-3 mb-4">
                <i class="fa fa-trash me-1"></i> Transaksi penyewaan berhasil dihapus.
            </div>
        <?php endif; ?>

        <div class="card mb-4 shadow-sm border border-secondary border-opacity-10">
          <div class="card-header d-flex align-items-center justify-content-between bg-body-tertiary">
            <h5 class="mb-0 text-body"><i class="fa fa-receipt me-2 text-primary"></i>Daftar Transaksi Penyewaan</h5>
            <a href="add_sewa.php" class="btn btn-primary btn-sm"><i class="fa fa-plus me-1"></i> Tambah Penyewaan</a>
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-hover table-striped align-middle mb-0">
                <thead class="table-light text-body-secondary fw-semibold small">
                  <tr>
                    <th scope="col" class="ps-4">No</th>
                    <th scope="col">Nama Penyewa</th>
                    <th scope="col">Kendaraan</th>
                    <th scope="col">Tanggal Sewa</th>
                    <th scope="col">Tanggal Kembali</th>
                    <th scope="col">Total Biaya</th>
                    <th scope="col">Status</th>
                    <th scope="col" class="pe-4 text-end">Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if ($total > 0): ?>
                      <?php foreach ($rentals as $i => $r): ?>
                      <tr>
                          <td class="ps-4 fw-semibold text-body-secondary"><?= $i + 1 ?></td>
                          <td class="text-body fw-bold"><?= htmlspecialchars($r['nama_user'] ?? 'N/A') ?></td>
                          <td>
                              <span class="text-body fw-semibold"><?= htmlspecialchars($r['nama_kendaraan'] ?? 'N/A') ?></span>
                              <span class="badge bg-dark text-warning border border-warning px-2 py-0.5 ms-1"><?= htmlspecialchars($r['kode_unik_kendaraan']) ?></span>
                          </td>
                          <td class="text-body-secondary small"><?= date('d M Y H:i', strtotime($r['tanggal_sewa'])) ?></td>
                          <td class="text-body-secondary small"><?= date('d M Y H:i', strtotime($r['tanggal_kembali'])) ?></td>
                          <td class="text-body fw-bold">
                              <?= $r['total_biaya'] !== null ? 'Rp ' . number_format($r['total_biaya'], 0, ',', '.') : '<span class="text-muted small">Belum dihitung</span>' ?>
                          </td>
                          <td>
                              <?php
                              $status = $r['status'] ?? 'booking';
                              if ($status === 'booking') {
                                  echo '<span class="badge bg-warning bg-opacity-10 text-warning px-2 py-1">Booking</span>';
                              } elseif ($status === 'sedang_disewa') {
                                  echo '<span class="badge bg-info bg-opacity-10 text-info px-2 py-1">Sedang Disewa</span>';
                              } elseif ($status === 'selesai') {
                                  echo '<span class="badge bg-success bg-opacity-10 text-success px-2 py-1">Selesai</span>';
                              } elseif ($status === 'dibatalkan') {
                                  echo '<span class="badge bg-danger bg-opacity-10 text-danger px-2 py-1">Dibatalkan</span>';
                              }
                              ?>
                          </td>
                          <td class="pe-4 text-end">
                              <div class="btn-group btn-group-sm" role="group">
                                  <a class="btn btn-outline-info d-flex align-items-center gap-1" href="edit_sewa.php?id=<?= urlencode($r['id_sewa']) ?>">
                                      <i class="fa fa-edit"></i> Edit
                                  </a>
                                  <a class="btn btn-outline-danger d-flex align-items-center gap-1" href="delete_sewa.php?id=<?= urlencode($r['id_sewa']) ?>" onclick="return confirm('Yakin hapus transaksi penyewaan ini?')">
                                      <i class="fa fa-trash"></i> Hapus
                                  </a>
                              </div>
                          </td>
                      </tr>
                      <?php endforeach; ?>
                  <?php else: ?>
                      <tr>
                          <td colspan="8" class="text-center py-5 text-muted">
                              <i class="fa fa-folder-open fa-2x mb-3 text-muted d-block"></i>
                              Belum ada data transaksi penyewaan.
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
