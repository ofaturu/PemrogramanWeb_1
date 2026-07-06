<?php
require_once 'config.php';

// Secure page: user must be logged in and must be admin
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if (isset($_SESSION['user_role']) && $_SESSION['user_role'] !== 'admin') {
    header('Location: analytics.php');
    exit;
}

$error = '';
$success = '';

// Handle actions (add or finish maintenance)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $kode_unik = intval($_POST['kode_unik_kendaraan'] ?? 0);
        $deskripsi = trim($_POST['deskripsi'] ?? '');
        $tgl_mulai = $_POST['tanggal_mulai'] ?? date('Y-m-d');

        if ($kode_unik <= 0 || empty($deskripsi) || empty($tgl_mulai)) {
            $error = 'Semua field wajib diisi.';
        } else {
            // Check if vehicle exists and is available
            $v_res = mysqli_query($mysqli, "SELECT status_kendaraan FROM kendaraan WHERE kode_unik_kendaraan = " . $kode_unik);
            $v_row = mysqli_fetch_assoc($v_res);
            if ($v_row && $v_row['status_kendaraan'] === 'disewa') {
                $error = 'Kendaraan sedang disewa dan tidak dapat dimasukkan perawatan.';
            } else {
                // Insert maintenance record
                $stmt = mysqli_prepare($mysqli, "INSERT INTO maintenance (kode_unik_kendaraan, deskripsi, tanggal_mulai) VALUES (?, ?, ?)");
                mysqli_stmt_bind_param($stmt, 'iss', $kode_unik, $deskripsi, $tgl_mulai);
                if (mysqli_stmt_execute($stmt)) {
                    // Update vehicle status to 'perawatan'
                    mysqli_query($mysqli, "UPDATE kendaraan SET status_kendaraan = 'perawatan' WHERE kode_unik_kendaraan = " . $kode_unik);
                    $success = 'Kendaraan berhasil dimasukkan ke daftar perawatan.';
                } else {
                    $error = 'Gagal menyimpan data perawatan.';
                }
                mysqli_stmt_close($stmt);
            }
        }
    } elseif ($action === 'finish') {
        $m_id = intval($_POST['maintenance_id'] ?? 0);
        $biaya = intval($_POST['biaya'] ?? 0);
        $tgl_selesai = $_POST['tanggal_selesai'] ?? date('Y-m-d');

        if ($m_id <= 0 || empty($tgl_selesai)) {
            $error = 'ID Perawatan dan Tanggal Selesai wajib diisi.';
        } else {
            // Get vehicle code first
            $m_res = mysqli_query($mysqli, "SELECT kode_unik_kendaraan FROM maintenance WHERE id = " . $m_id);
            $m_row = mysqli_fetch_assoc($m_res);
            
            if ($m_row) {
                $kode_unik = $m_row['kode_unik_kendaraan'];
                
                // Update maintenance record
                $stmt = mysqli_prepare($mysqli, "UPDATE maintenance SET biaya = ?, tanggal_selesai = ? WHERE id = ?");
                mysqli_stmt_bind_param($stmt, 'isi', $biaya, $tgl_selesai, $m_id);
                if (mysqli_stmt_execute($stmt)) {
                    // Update vehicle status back to 'tersedia'
                    mysqli_query($mysqli, "UPDATE kendaraan SET status_kendaraan = 'tersedia' WHERE kode_unik_kendaraan = " . $kode_unik);
                    $success = 'Perawatan kendaraan selesai. Kendaraan kini tersedia kembali.';
                } else {
                    $error = 'Gagal memperbarui data perawatan.';
                }
                mysqli_stmt_close($stmt);
            } else {
                $error = 'Data perawatan tidak ditemukan.';
            }
        }
    }
}

// Fetch active maintenance
$active_maint = [];
$res_active = mysqli_query($mysqli, "
    SELECT m.*, k.nama_kendaraan, k.warna 
    FROM maintenance m 
    JOIN kendaraan k ON m.kode_unik_kendaraan = k.kode_unik_kendaraan 
    WHERE m.tanggal_selesai IS NULL 
    ORDER BY m.tanggal_mulai DESC
");
if ($res_active) {
    $active_maint = mysqli_fetch_all($res_active, MYSQLI_ASSOC);
}

// Fetch past maintenance
$past_maint = [];
$res_past = mysqli_query($mysqli, "
    SELECT m.*, k.nama_kendaraan, k.warna 
    FROM maintenance m 
    JOIN kendaraan k ON m.kode_unik_kendaraan = k.kode_unik_kendaraan 
    WHERE m.tanggal_selesai IS NOT NULL 
    ORDER BY m.tanggal_selesai DESC 
    LIMIT 20
");
if ($res_past) {
    $past_maint = mysqli_fetch_all($res_past, MYSQLI_ASSOC);
}

// Fetch available vehicles (status = tersedia) for dropdown
$available_vehs = [];
$res_vehs = mysqli_query($mysqli, "SELECT kode_unik_kendaraan, nama_kendaraan FROM kendaraan WHERE status_kendaraan = 'tersedia' ORDER BY nama_kendaraan ASC");
if ($res_vehs) {
    $available_vehs = mysqli_fetch_all($res_vehs, MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="id">
<?php
$title = 'Perawatan Kendaraan — FTrans';
include 'partials/head.php';
?>
<body>
  <?php 
  $activePage = 'maintenance';
  include 'partials/sidebar.php'; 
  ?>
  <div class="wrapper d-flex flex-column min-vh-100">
    <?php 
    $pageTitle = 'Perawatan Kendaraan';
    include 'partials/header.php'; 
    ?>
    <div class="body flex-grow-1">
      <div class="container-lg px-4">
        
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

        <div class="row">
          <!-- Active Maintenance Column -->
          <div class="col-lg-8 mb-4">
            <div class="card shadow-sm border border-secondary border-opacity-10 h-100">
              <div class="card-header bg-body-tertiary d-flex align-items-center justify-content-between">
                <h5 class="mb-0 text-body"><i class="fa fa-tools me-2 text-warning"></i>Kendaraan Dalam Perawatan</h5>
                <span class="badge bg-warning text-dark"><?= count($active_maint) ?> Aktif</span>
              </div>
              <div class="card-body p-0">
                <?php if (count($active_maint) > 0): ?>
                  <div class="table-responsive">
                    <table class="table table-hover table-striped align-middle mb-0">
                      <thead class="table-light text-body-secondary fw-semibold small">
                        <tr>
                          <th scope="col" class="ps-4">Kendaraan</th>
                          <th scope="col">Deskripsi Kerusakan/Servis</th>
                          <th scope="col">Mulai Perawatan</th>
                          <th scope="col" class="pe-4 text-end">Aksi</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($active_maint as $m): ?>
                          <tr>
                            <td class="ps-4 fw-bold text-body-emphasis">
                              <?= htmlspecialchars($m['nama_kendaraan']) ?> <span class="badge bg-dark-subtle text-dark-emphasis small fw-normal"><?= htmlspecialchars($m['warna']) ?></span>
                            </td>
                            <td><?= htmlspecialchars($m['deskripsi']) ?></td>
                            <td><?= date('d M Y', strtotime($m['tanggal_mulai'])) ?></td>
                            <td class="pe-4 text-end">
                              <button type="button" class="btn btn-sm btn-success text-white" data-coreui-toggle="modal" data-coreui-target="#finishModal-<?= $m['id'] ?>">
                                <i class="fa fa-check me-1"></i> Selesai
                              </button>

                              <!-- Finish Maintenance Modal -->
                              <div class="modal fade text-start" id="finishModal-<?= $m['id'] ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                  <div class="modal-content border-0 shadow-lg">
                                    <div class="modal-header bg-success text-white">
                                      <h5 class="modal-title"><i class="fa fa-check-circle me-2"></i>Selesaikan Perawatan</h5>
                                      <button type="button" class="btn-close btn-close-white" data-coreui-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <form method="POST" action="">
                                      <div class="modal-body p-4">
                                        <input type="hidden" name="action" value="finish">
                                        <input type="hidden" name="maintenance_id" value="<?= $m['id'] ?>">
                                        <div class="mb-3">
                                          <label class="form-label">Kendaraan</label>
                                          <input type="text" class="form-control" value="<?= htmlspecialchars($m['nama_kendaraan']) ?>" readonly disabled>
                                        </div>
                                        <div class="mb-3">
                                          <label class="form-label" for="biaya-<?= $m['id'] ?>">Biaya Perawatan (Rp) *</label>
                                          <input type="number" id="biaya-<?= $m['id'] ?>" name="biaya" class="form-control" min="0" placeholder="Contoh: 150000" required>
                                        </div>
                                        <div class="mb-3">
                                          <label class="form-label" for="tgl_selesai-<?= $m['id'] ?>">Tanggal Selesai *</label>
                                          <input type="date" id="tgl_selesai-<?= $m['id'] ?>" name="tanggal_selesai" class="form-control" value="<?= date('Y-m-d') ?>" required>
                                        </div>
                                      </div>
                                      <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-coreui-dismiss="modal">Batal</button>
                                        <button type="submit" class="btn btn-success"><i class="fa fa-save me-1"></i> Simpan & Selesaikan</button>
                                      </div>
                                    </form>
                                  </div>
                                </div>
                              </div>

                            </td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                <?php else: ?>
                  <div class="text-center py-5 text-muted">
                    <i class="fa fa-check-circle fa-3x mb-3 text-success"></i>
                    <p class="mb-0">Semua armada dalam kondisi prima! Tidak ada kendaraan dalam perawatan.</p>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <!-- Add Maintenance Column -->
          <div class="col-lg-4 mb-4">
            <div class="card shadow-sm border border-secondary border-opacity-10 h-100">
              <div class="card-header bg-body-tertiary">
                <h5 class="mb-0 text-body"><i class="fa fa-plus me-2 text-primary"></i>Tambah Perawatan</h5>
              </div>
              <div class="card-body p-4">
                <?php if (count($available_vehs) > 0): ?>
                  <form method="POST" action="">
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                      <label class="form-label" for="kode_unik">Pilih Kendaraan *</label>
                      <select id="kode_unik" name="kode_unik_kendaraan" class="form-select" required>
                        <option value="" disabled selected>-- Pilih Kendaraan --</option>
                        <?php foreach ($available_vehs as $v): ?>
                          <option value="<?= $v['kode_unik_kendaraan'] ?>"><?= htmlspecialchars($v['nama_kendaraan']) ?> (Kode: <?= $v['kode_unik_kendaraan'] ?>)</option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="mb-3">
                      <label class="form-label" for="deskripsi">Deskripsi Servis/Perbaikan *</label>
                      <textarea id="deskripsi" name="deskripsi" class="form-control" rows="3" placeholder="Ganti oli, ganti ban, perbaikan rem, dsb..." required></textarea>
                    </div>
                    <div class="mb-3">
                      <label class="form-label" for="tanggal_mulai">Tanggal Mulai Servis *</label>
                      <input type="date" id="tanggal_mulai" name="tanggal_mulai" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100"><i class="fa fa-save me-1"></i> Mulai Perawatan</button>
                  </form>
                <?php else: ?>
                  <div class="text-center py-4 text-muted">
                    <i class="fa fa-exclamation-circle fa-2x mb-2 text-warning"></i>
                    <p class="mb-0 small">Tidak ada armada berstatus 'Tersedia'. Kosongkan sewa atau selesaikan servis kendaraan lainnya terlebih dahulu.</p>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>

        <!-- History Maintenance Section -->
        <div class="card shadow-sm border border-secondary border-opacity-10 mt-4">
          <div class="card-header bg-body-tertiary">
            <h5 class="mb-0 text-body"><i class="fa fa-history me-2 text-secondary"></i>Riwayat Perbaikan Kendaraan</h5>
          </div>
          <div class="card-body p-0">
            <?php if (count($past_maint) > 0): ?>
              <div class="table-responsive">
                <table class="table table-hover table-striped align-middle mb-0">
                  <thead class="table-light text-body-secondary fw-semibold small">
                    <tr>
                      <th scope="col" class="ps-4">No</th>
                      <th scope="col">Kendaraan</th>
                      <th scope="col">Deskripsi Perbaikan</th>
                      <th scope="col">Tanggal Mulai</th>
                      <th scope="col">Tanggal Selesai</th>
                      <th scope="col" class="pe-4 text-end">Biaya Servis</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($past_maint as $idx => $pm): ?>
                      <tr>
                        <td class="ps-4 text-body-secondary"><?= $idx + 1 ?></td>
                        <td class="fw-bold text-body-emphasis"><?= htmlspecialchars($pm['nama_kendaraan']) ?></td>
                        <td><?= htmlspecialchars($pm['deskripsi']) ?></td>
                        <td><?= date('d M Y', strtotime($pm['tanggal_mulai'])) ?></td>
                        <td><?= date('d M Y', strtotime($pm['tanggal_selesai'])) ?></td>
                        <td class="pe-4 text-end fw-bold text-danger">Rp <?= number_format($pm['biaya'], 0, ',', '.') ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php else: ?>
              <div class="text-center py-5 text-muted">
                <i class="fa fa-folder-open fa-2x mb-2 text-secondary"></i>
                <p class="mb-0">Belum ada riwayat perbaikan yang tercatat.</p>
              </div>
            <?php endif; ?>
          </div>
        </div>

      </div>
    </div>
    <?php include 'partials/footer.php'; ?>
  </div>
</body>
</html>
