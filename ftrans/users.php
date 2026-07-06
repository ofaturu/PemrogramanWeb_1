<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$nama_user = htmlspecialchars($_SESSION['user_nama']);

// Pagination settings
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) {
    $page = 1;
}
$offset = ($page - 1) * $limit;

// Menangkap keyword pencarian dari URL
$search = trim($_GET['search'] ?? '');
$search_param = "%" . $search . "%";

if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
    // Count total matching users
    $count_query = "SELECT COUNT(*) FROM users WHERE nama LIKE ? OR email LIKE ?";
    $c_stmt = mysqli_prepare($mysqli, $count_query);
    mysqli_stmt_bind_param($c_stmt, 'ss', $search_param, $search_param);
    mysqli_stmt_execute($c_stmt);
    mysqli_stmt_bind_result($c_stmt, $total_items);
    mysqli_stmt_fetch($c_stmt);
    mysqli_stmt_close($c_stmt);

    $total_pages = ceil($total_items / $limit);
    if ($page > $total_pages && $total_pages > 0) {
        $page = $total_pages;
        $offset = ($page - 1) * $limit;
    }

    // Fetch matching users
    $query = "SELECT u.id, u.nama, u.email, u.no_hp, u.role, COUNT(p.id_sewa) AS jumlah_sewa
              FROM users u
              LEFT JOIN penyewaan p ON u.id = p.id_user
              WHERE u.nama LIKE ? OR u.email LIKE ?
              GROUP BY u.id
              ORDER BY u.nama ASC
              LIMIT ? OFFSET ?";
    $stmt = mysqli_prepare($mysqli, $query);
    mysqli_stmt_bind_param($stmt, 'ssii', $search_param, $search_param, $limit, $offset);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $users_list = mysqli_fetch_all($res, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
} else {
    // Non-admin can only see themselves
    $total_items = 1;
    $total_pages = 1;
    $offset = 0;

    $query = "SELECT u.id, u.nama, u.email, u.no_hp, u.role, COUNT(p.id_sewa) AS jumlah_sewa
              FROM users u
              LEFT JOIN penyewaan p ON u.id = p.id_user
              WHERE u.id = ?
              GROUP BY u.id";
    $stmt = mysqli_prepare($mysqli, $query);
    mysqli_stmt_bind_param($stmt, 'i', $_SESSION['user_id']);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $users_list = mysqli_fetch_all($res, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
}

$total = count($users_list);
?>
<!DOCTYPE html>
<html lang="id">
<?php
$title = 'Data User — FTrans';
include 'partials/head.php';
?>
<body>
  <?php 
  $activePage = 'users';
  include 'partials/sidebar.php'; 
  ?>
  <div class="wrapper d-flex flex-column min-vh-100">
    <?php 
    $pageTitle = 'Data User';
    include 'partials/header.php'; 
    ?>
    <div class="body flex-grow-1">
      <div class="container-lg px-4">

        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
            <div class="alert alert-success border-0 bg-success bg-opacity-10 text-success small py-2 px-3 mb-4">
                <i class="fa fa-check-circle me-1"></i> User berhasil dihapus dari sistem.
            </div>
        <?php elseif (isset($_GET['error']) && $_GET['error'] === 'self_delete'): ?>
            <div class="alert alert-danger border-0 bg-danger bg-opacity-10 text-danger small py-2 px-3 mb-4">
                <i class="fa fa-exclamation-triangle me-1"></i> Anda tidak dapat menghapus akun Anda sendiri.
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
        <div class="card mb-4 shadow-sm border border-secondary border-opacity-10">
          <div class="card-header d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 bg-body-tertiary">
            <h5 class="mb-0 text-body"><i class="fa fa-users me-2 text-primary"></i>Daftar User</h5>
            
            <div class="d-flex flex-column flex-sm-row align-items-sm-center gap-2 w-100 w-sm-auto justify-content-end">
              <form method="GET" action="" class="d-flex align-items-center gap-2" style="max-width: 300px; width: 100%;">
                <div class="input-group input-group-sm">
                  <input type="text" name="search" class="form-control" placeholder="Cari nama atau email..." value="<?= htmlspecialchars($search) ?>">
                  <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i></button>
                  <?php if (!empty($search)): ?>
                    <a href="users.php" class="btn btn-secondary"><i class="fa fa-times"></i></a>
                  <?php endif; ?>
                </div>
              </form>
              <div class="dropdown mt-2 mt-sm-0">
                <button class="btn btn-success btn-sm dropdown-toggle text-nowrap" type="button" data-coreui-toggle="dropdown" aria-expanded="false">
                  <i class="fa fa-download me-1"></i> Export
                </button>
                <ul class="dropdown-menu">
                  <li><a class="dropdown-item" href="export.php?target=users&format=excel<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" target="_blank"><i class="fa fa-file-excel text-success me-2"></i> Excel (.xlsx)</a></li>
                  <li><a class="dropdown-item" href="export.php?target=users&format=word<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" target="_blank"><i class="fa fa-file-word text-primary me-2"></i> Word (.docx)</a></li>
                  <li><a class="dropdown-item" href="export.php?target=users&format=pdf<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" target="_blank"><i class="fa fa-file-pdf text-danger me-2"></i> PDF (.pdf)</a></li>
                </ul>
              </div>
            </div>
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-hover table-striped align-middle mb-0">
                <thead class="table-light text-body-secondary fw-semibold small">
                  <tr>
                    <th scope="col" class="ps-4" style="width: 80px;">No</th>
                    <th scope="col" style="width: 100px;">ID</th>
                    <th scope="col">Nama User</th>
                    <th scope="col">Email</th>
                    <th scope="col" style="width: 150px;">No HP</th>
                    <th scope="col" style="width: 120px;">Role</th>
                    <th scope="col" style="width: 200px;">Jumlah Sewa</th>
                    <th scope="col" class="pe-4 text-end" style="width: 150px;">Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if ($total > 0): ?>
                      <?php foreach ($users_list as $i => $u): ?>
                      <tr>
                          <td class="ps-4 fw-semibold text-body-secondary"><?= $offset + $i + 1 ?></td>
                          <td class="text-body-secondary">#<?= htmlspecialchars($u['id']) ?></td>
                          <td class="text-body fw-bold"><?= htmlspecialchars($u['nama']) ?></td>
                          <td class="text-body-secondary"><?= htmlspecialchars($u['email']) ?></td>
                          <td class="text-body-secondary"><?= htmlspecialchars($u['no_hp'] ?? '-') ?></td>
                          <td>
                              <span class="badge bg-<?= ($u['role'] === 'admin') ? 'danger' : 'success' ?> bg-opacity-10 text-<?= ($u['role'] === 'admin') ? 'danger' : 'success' ?> px-2.5 py-1.5 fw-semibold" style="font-size: 0.85rem;">
                                  <?= htmlspecialchars(ucfirst($u['role'])) ?>
                              </span>
                          </td>
                          <td>
                              <span class="badge bg-secondary bg-opacity-10 text-secondary px-2.5 py-1.5 fw-semibold" style="font-size: 0.85rem;">
                                  <i class="fa fa-car me-1"></i> <?= htmlspecialchars($u['jumlah_sewa']) ?> Transaksi
                              </span>
                          </td>
                           <td class="pe-4 text-end">
                              <div class="d-inline-flex gap-1">
                                   <a href="export.php?target=users&format=pdf&id=<?= urlencode($u['id']) ?>" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1" title="Cetak Riwayat PDF" target="_blank">
                                      <i class="fa fa-print"></i> Cetak Riwayat
                                  </a>
                                  <button type="button" class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-1" data-coreui-toggle="modal" data-coreui-target="#userDetailModal-<?= $u['id'] ?>">
                                      <i class="fa fa-info-circle"></i> Detail Sewa
                                  </button>
                                  <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin' && $u['id'] != $_SESSION['user_id']): ?>
                                      <a href="delete_user.php?id=<?= urlencode($u['id']) ?>" class="btn btn-outline-danger btn-sm d-inline-flex align-items-center gap-1" onclick="return confirm('Apakah Anda yakin ingin menghapus user ini beserta seluruh riwayat transaksinya?')" title="Hapus User">
                                          <i class="fa fa-trash"></i> Hapus
                                      </a>
                                  <?php endif; ?>
                              </div>
                           </td>
                      </tr>
                      <?php endforeach; ?>
                  <?php else: ?>
                      <tr>
                          <td colspan="8" class="text-center py-5 text-muted">
                              <i class="fa fa-folder-open fa-2x mb-3 text-muted d-block"></i>
                              Belum ada data user.
                          </td>
                      </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
          <!-- Pagination Footer -->
          <div class="card-footer py-3 d-flex flex-column flex-sm-row justify-content-between align-items-center gap-3 bg-body-tertiary border-top border-secondary border-opacity-10">
            <div class="text-muted small">
              Menampilkan <?= $total_items > 0 ? $offset + 1 : 0 ?> sampai <?= min($offset + $limit, $total_items) ?> dari <?= $total_items ?> user
            </div>
            <?php if ($total_pages > 1): ?>
              <nav aria-label="Page navigation">
                <ul class="pagination pagination-sm mb-0">
                  <!-- Previous Page -->
                  <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $page - 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" aria-label="Previous">
                      <span aria-hidden="true">&laquo;</span>
                    </a>
                  </li>
                  <!-- Page Numbers -->
                  <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                    <li class="page-item <?= ($page == $p) ? 'active' : '' ?>">
                      <a class="page-link" href="?page=<?= $p ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"><?= $p ?></a>
                    </li>
                  <?php endfor; ?>
                  <!-- Next Page -->
                  <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $page + 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" aria-label="Next">
                      <span aria-hidden="true">&raquo;</span>
                    </a>
                  </li>
                </ul>
              </nav>
            <?php endif; ?>
          </div>
        </div>
        <?php else: ?>
        <!-- Standard User Inline Personal Details (User Mode) -->
        <?php 
        $u = $users_list[0] ?? ['id' => $_SESSION['user_id'], 'nama' => $_SESSION['user_nama'], 'email' => '', 'no_hp' => ''];
        $user_id = $u['id'];
        $rental_query = "SELECT p.id_sewa, p.tanggal_sewa, p.tanggal_kembali, p.total_biaya, p.status, k.nama_kendaraan
                         FROM penyewaan p
                         LEFT JOIN kendaraan k ON p.kode_unik_kendaraan = k.kode_unik_kendaraan
                         WHERE p.id_user = ?
                         ORDER BY p.tanggal_sewa DESC";
        $r_stmt = mysqli_prepare($mysqli, $rental_query);
        mysqli_stmt_bind_param($r_stmt, 'i', $user_id);
        mysqli_stmt_execute($r_stmt);
        $r_res = mysqli_stmt_get_result($r_stmt);
        $user_rentals = mysqli_fetch_all($r_res, MYSQLI_ASSOC);
        mysqli_stmt_close($r_stmt);
        ?>
        <div class="row">
          <div class="col-md-4 mb-4">
            <div class="card shadow-sm border border-secondary border-opacity-10 h-100">
              <div class="card-header bg-body-tertiary">
                <h6 class="mb-0 text-body"><i class="fa fa-user me-2 text-primary"></i>Informasi Akun</h6>
              </div>
              <div class="card-body">
                <div class="mb-3">
                  <div class="text-muted small">Nama Pengguna</div>
                  <div class="fw-bold fs-5 text-body-emphasis"><?= htmlspecialchars($u['nama']) ?></div>
                </div>
                <div class="mb-3">
                  <div class="text-muted small">Alamat Email</div>
                  <div class="text-body-emphasis fw-semibold"><?= htmlspecialchars($u['email']) ?></div>
                </div>
                <div class="mb-3">
                  <div class="text-muted small">Nomor Handphone</div>
                  <div class="text-body-emphasis fw-semibold"><?= htmlspecialchars($u['no_hp'] ?? '-') ?></div>
                </div>
                <hr class="text-body-secondary my-3">
                <a href="profile.php" class="btn btn-outline-primary btn-sm w-100"><i class="fa fa-edit me-1"></i> Edit Data Diri</a>
              </div>
            </div>
          </div>
          
          <div class="col-md-8 mb-4">
            <div class="card shadow-sm border border-secondary border-opacity-10 h-100">
              <div class="card-header d-flex align-items-center justify-content-between bg-body-tertiary">
                <h6 class="mb-0 text-body"><i class="fa fa-history me-2 text-success"></i>Riwayat Transaksi Rental</h6>
                <span class="badge bg-success bg-opacity-10 text-success"><?= count($user_rentals) ?> Transaksi</span>
              </div>
              <div class="card-body p-0">
                <?php if (count($user_rentals) > 0): ?>
                  <div class="table-responsive">
                    <table class="table table-hover table-striped align-middle mb-0">
                      <thead class="table-light text-body-secondary fw-semibold small">
                        <tr>
                          <th scope="col" class="ps-4">No</th>
                          <th scope="col">Nama Kendaraan</th>
                          <th scope="col">Lama Sewa</th>
                          <th scope="col">Total Biaya</th>
                          <th scope="col">Status</th>
                          <th scope="col" class="pe-4 text-end">Aksi</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($user_rentals as $idx => $rent): ?>
                          <?php
                          $t_sewa = strtotime($rent['tanggal_sewa']);
                          $t_kembali = strtotime($rent['tanggal_kembali']);
                          $diff_days = ceil(($t_kembali - $t_sewa) / 86400);
                          if ($diff_days <= 0) $diff_days = 1;
                          ?>
                          <tr>
                            <td class="ps-4 text-body-secondary"><?= $idx + 1 ?></td>
                            <td class="fw-bold text-body-emphasis"><?= htmlspecialchars($rent['nama_kendaraan'] ?? 'N/A') ?></td>
                            <td><?= $diff_days ?> Hari</td>
                            <td class="fw-bold text-success">Rp <?= number_format($rent['total_biaya'], 0, ',', '.') ?></td>
                            <td>
                              <?php
                              $st = $rent['status'];
                              if ($st === 'booking') {
                                  echo '<span class="badge bg-warning text-dark px-2 py-1">Booking</span>';
                              } elseif ($st === 'sedang_disewa') {
                                  echo '<span class="badge bg-info text-white px-2 py-1">Sedang Disewa</span>';
                              } elseif ($st === 'selesai') {
                                  echo '<span class="badge bg-success text-white px-2 py-1">Selesai</span>';
                              } else {
                                  echo '<span class="badge bg-danger text-white px-2 py-1">Dibatalkan</span>';
                              }
                              ?>
                            </td>
                            <td class="pe-4 text-end">
                              <a href="bayar.php?id=<?= $rent['id_sewa'] ?>" class="btn btn-sm btn-primary text-white"><i class="fa fa-receipt me-1"></i> Rincian & Bayar</a>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                <?php else: ?>
                  <div class="text-center py-5 text-muted">
                    <i class="fa fa-folder-open fa-3x mb-3 text-secondary"></i>
                    <p class="mb-0">Belum ada riwayat transaksi penyewaan yang tercatat.</p>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
        <?php endif; ?>

      </div>
    </div>

    <?php 
    include 'partials/footer.php'; 
    ?>

    <!-- Modals for User Details -->
    <?php foreach ($users_list as $u): ?>
        <?php
        // Fetch rentals for this user
        $user_id = $u['id'];
        $rental_query = "SELECT p.tanggal_sewa, p.tanggal_kembali, p.total_biaya, p.status, k.nama_kendaraan
                         FROM penyewaan p
                         LEFT JOIN kendaraan k ON p.kode_unik_kendaraan = k.kode_unik_kendaraan
                         WHERE p.id_user = ?
                         ORDER BY p.tanggal_sewa DESC";
        $r_stmt = mysqli_prepare($mysqli, $rental_query);
        mysqli_stmt_bind_param($r_stmt, 'i', $user_id);
        mysqli_stmt_execute($r_stmt);
        $r_res = mysqli_stmt_get_result($r_stmt);
        $user_rentals = mysqli_fetch_all($r_res, MYSQLI_ASSOC);
        mysqli_stmt_close($r_stmt);
        ?>
        <div class="modal fade animate__animated animate__fadeIn" id="userDetailModal-<?= $u['id'] ?>" tabindex="-1" aria-labelledby="userDetailModalLabel-<?= $u['id'] ?>" aria-hidden="true">
          <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 12px;">
              <div class="modal-header bg-primary text-white" style="border-top-left-radius: 12px; border-top-right-radius: 12px;">
                <h5 class="modal-title" id="userDetailModalLabel-<?= $u['id'] ?>">
                    <i class="fa fa-info-circle me-2"></i>Detail Riwayat Sewa — <?= htmlspecialchars($u['nama']) ?>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-coreui-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body p-4">
                <div class="mb-4">
                  <div class="row g-2">
                    <div class="col-sm-4">
                      <div class="text-muted small">Nama User</div>
                      <div class="fw-bold text-body-emphasis"><?= htmlspecialchars($u['nama']) ?></div>
                    </div>
                    <div class="col-sm-4">
                      <div class="text-muted small">Alamat Email</div>
                      <div class="fw-bold text-body-emphasis"><?= htmlspecialchars($u['email']) ?></div>
                    </div>
                    <div class="col-sm-4">
                      <div class="text-muted small">Nomor Handphone</div>
                      <div class="fw-bold text-body-emphasis"><?= htmlspecialchars($u['no_hp'] ?? '-') ?></div>
                    </div>
                  </div>
                </div>
                
                <h6 class="fw-bold mb-3 text-body-secondary"><i class="fa fa-list me-1"></i> Daftar Kendaraan Yang Disewa</h6>
                
                <?php if (count($user_rentals) > 0): ?>
                  <div class="table-responsive border rounded-3 overflow-hidden">
                    <table class="table table-hover table-striped align-middle mb-0">
                      <thead class="table-light text-body-secondary fw-semibold small">
                        <tr>
                          <th scope="col" class="ps-3" style="width: 60px;">No</th>
                          <th scope="col">Nama Kendaraan</th>
                          <th scope="col">Lama Meminjam</th>
                          <th scope="col">Harga Harus Dibayar</th>
                          <th scope="col" class="pe-3">Status</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($user_rentals as $idx => $rent): ?>
                          <?php
                          // Calculate duration in days
                          $t_sewa = strtotime($rent['tanggal_sewa']);
                          $t_kembali = strtotime($rent['tanggal_kembali']);
                          if ($rent['tanggal_kembali'] === '0000-00-00 00:00:00' || !$t_kembali || $t_kembali <= $t_sewa) {
                              $durasi = '<span class="text-info font-monospace small">Belum dikembalikan</span>';
                          } else {
                              $diff_seconds = $t_kembali - $t_sewa;
                              $diff_days = ceil($diff_seconds / 86400);
                              $durasi = '<b>' . $diff_days . '</b> hari';
                          }
                          ?>
                          <tr>
                            <td class="ps-3 fw-semibold text-body-secondary"><?= $idx + 1 ?></td>
                            <td>
                              <span class="text-body fw-bold"><?= htmlspecialchars($rent['nama_kendaraan'] ?? 'Kendaraan Terhapus/N/A') ?></span>
                            </td>
                            <td><?= $durasi ?></td>
                            <td class="fw-bold text-success">
                              <?= $rent['total_biaya'] !== null ? 'Rp ' . number_format($rent['total_biaya'], 0, ',', '.') : '-' ?>
                            </td>
                            <td class="pe-3">
                              <?php
                              $st = $rent['status'] ?? 'booking';
                              if ($st === 'booking') {
                                  echo '<span class="badge bg-warning bg-opacity-10 text-warning px-2 py-1">Booking</span>';
                              } elseif ($st === 'sedang_disewa') {
                                  echo '<span class="badge bg-info bg-opacity-10 text-info px-2 py-1">Sedang Disewa</span>';
                              } elseif ($st === 'selesai') {
                                  echo '<span class="badge bg-success bg-opacity-10 text-success px-2 py-1">Selesai</span>';
                              } elseif ($st === 'dibatalkan') {
                                  echo '<span class="badge bg-danger bg-opacity-10 text-danger px-2 py-1">Dibatalkan</span>';
                              }
                              ?>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                <?php else: ?>
                  <div class="text-center py-5 border rounded-3 bg-body-tertiary">
                    <i class="fa fa-folder-open fa-2x mb-2 text-body-secondary"></i>
                    <p class="mb-0 text-body-secondary">Belum ada riwayat penyewaan untuk user ini.</p>
                  </div>
                <?php endif; ?>
              </div>
              <div class="modal-footer" style="border-bottom-left-radius: 12px; border-bottom-right-radius: 12px;">
                <button type="button" class="btn btn-secondary px-4" data-coreui-dismiss="modal">Tutup</button>
              </div>
            </div>
          </div>
        </div>
    <?php endforeach; ?>
</body>
</html>
