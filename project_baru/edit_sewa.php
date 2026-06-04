<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$id_sewa = trim($_GET['id'] ?? '');
$error = '';

if (empty($id_sewa)) {
    header('Location: sewa.php');
    exit;
}

// Fetch rental details
$stmt = mysqli_prepare($mysqli, "SELECT * FROM penyewaan WHERE id_sewa = ?");
mysqli_stmt_bind_param($stmt, 'i', $id_sewa);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$rental = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$rental) {
    header('Location: sewa.php');
    exit;
}

// Fetch users for dropdown
$users_query = "SELECT id, nama FROM users ORDER BY nama ASC";
$users_res = mysqli_query($mysqli, $users_query);
$users = mysqli_fetch_all($users_res, MYSQLI_ASSOC);

// Fetch vehicles for dropdown
$vehicles_query = "SELECT kode_unik_kendaraan, nama_kendaraan, harga_per_hari FROM kendaraan ORDER BY nama_kendaraan ASC";
$vehicles_res = mysqli_query($mysqli, $vehicles_query);
$vehicles = mysqli_fetch_all($vehicles_res, MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_user = $_POST['id_user'] ?? '';
    $kode_unik = $_POST['kode_unik_kendaraan'] ?? '';
    $tgl_sewa = $_POST['tanggal_sewa'] ?? '';
    $tgl_kembali = $_POST['tanggal_kembali'] ?? '';
    $total_biaya = $_POST['total_biaya'] ?? '';
    $status = $_POST['status'] ?? 'booking';

    if (empty($id_user) || empty($kode_unik) || empty($tgl_sewa) || empty($tgl_kembali) || $total_biaya === '') {
        $error = 'Semua field wajib diisi.';
    } elseif (strtotime($tgl_kembali) < strtotime($tgl_sewa)) {
        $error = 'Tanggal kembali tidak boleh mendahului tanggal sewa.';
    } else {
        // Update database
        $upd = mysqli_prepare($mysqli, "UPDATE penyewaan SET id_user = ?, kode_unik_kendaraan = ?, tanggal_sewa = ?, tanggal_kembali = ?, total_biaya = ?, status = ? WHERE id_sewa = ?");
        mysqli_stmt_bind_param($upd, 'iisssdi', $id_user, $kode_unik, $tgl_sewa, $tgl_kembali, $total_biaya, $status, $id_sewa);

        if (mysqli_stmt_execute($upd)) {
            header('Location: sewa.php?msg=updated');
            exit;
        } else {
            $error = 'Gagal menyimpan perubahan. Coba lagi.';
        }
        mysqli_stmt_close($upd);
    }
    // Update local variables for form pre-population on post error
    $rental['id_user'] = $id_user;
    $rental['kode_unik_kendaraan'] = $kode_unik;
    $rental['tanggal_sewa'] = $tgl_sewa;
    $rental['tanggal_kembali'] = $tgl_kembali;
    $rental['total_biaya'] = $total_biaya;
    $rental['status'] = $status;
}

// Format date values to fit datetime-local inputs
$sewa_val = date('Y-m-d\TH:i', strtotime($rental['tanggal_sewa']));
$kembali_val = date('Y-m-d\TH:i', strtotime($rental['tanggal_kembali']));
?>
<!DOCTYPE html>
<html lang="id">
<?php
$title = 'Edit Penyewaan — FTrans';
include 'partials/head.php';
?>
<body>
  <?php 
  $activePage = 'sewa';
  include 'partials/sidebar.php'; 
  ?>
  <div class="wrapper d-flex flex-column min-vh-100">
    <?php 
    $pageTitle = 'Edit Data Penyewaan';
    include 'partials/header.php'; 
    ?>
    <div class="body flex-grow-1">
      <div class="container-lg px-4">
        
        <div class="row">
          <div class="col-12 col-lg-8">
            <div class="card mb-4 shadow-sm border border-secondary border-opacity-10">
              <div class="card-header bg-body-tertiary">
                <h5 class="mb-0 text-body"><i class="fa fa-edit me-2 text-primary"></i>Edit Transaksi Penyewaan</h5>
              </div>
              <div class="card-body p-4">
                <?php if ($error): ?>
                    <div class="alert alert-danger border-0 bg-danger bg-opacity-10 text-danger small py-2 px-3 mb-4">
                        <i class="fa fa-exclamation-triangle me-1"></i> <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" novalidate class="row g-3" id="sewaForm">
                  <div class="col-md-6">
                    <label class="form-label" for="id_user">Nama Penyewa (User) *</label>
                    <select id="id_user" name="id_user" class="form-select" required>
                      <option value="" disabled>-- Pilih Penyewa --</option>
                      <?php foreach ($users as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= ($rental['id_user'] == $u['id']) ? 'selected' : '' ?>>
                          <?= htmlspecialchars($u['nama']) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                  <div class="col-md-6">
                    <label class="form-label" for="kode_unik">Kendaraan *</label>
                    <select id="kode_unik" name="kode_unik_kendaraan" class="form-select" required>
                      <option value="" disabled>-- Pilih Kendaraan --</option>
                      <?php foreach ($vehicles as $k): ?>
                        <option value="<?= $k['kode_unik_kendaraan'] ?>" data-price="<?= $k['harga_per_hari'] ?>" <?= ($rental['kode_unik_kendaraan'] == $k['kode_unik_kendaraan']) ? 'selected' : '' ?>>
                          <?= htmlspecialchars($k['nama_kendaraan']) ?> (Kode: <?= $k['kode_unik_kendaraan'] ?>) - Rp <?= number_format($k['harga_per_hari'], 0, ',', '.') ?>/hari
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                  <div class="col-md-6">
                    <label class="form-label" for="tanggal_sewa">Tanggal Sewa *</label>
                    <input type="datetime-local" id="tanggal_sewa" name="tanggal_sewa" class="form-control" value="<?= htmlspecialchars($sewa_val) ?>" required>
                  </div>

                  <div class="col-md-6">
                    <label class="form-label" for="tanggal_kembali">Tanggal Kembali *</label>
                    <input type="datetime-local" id="tanggal_kembali" name="tanggal_kembali" class="form-control" value="<?= htmlspecialchars($kembali_val) ?>" required>
                  </div>

                  <div class="col-md-6">
                    <label class="form-label" for="total_biaya">Total Biaya (Rp) *</label>
                    <div class="input-group">
                      <span class="input-group-text">Rp</span>
                      <input type="number" id="total_biaya" name="total_biaya" class="form-control" placeholder="0" min="0" value="<?= htmlspecialchars($rental['total_biaya']) ?>" required>
                    </div>
                    <div class="form-text text-muted">Akan dihitung otomatis berdasarkan durasi hari & harga sewa kendaraan.</div>
                  </div>

                  <div class="col-md-6">
                    <label class="form-label" for="status">Status Penyewaan *</label>
                    <select id="status" name="status" class="form-select" required>
                      <option value="booking" <?= ($rental['status'] === 'booking') ? 'selected' : '' ?>>Booking</option>
                      <option value="sedang_disewa" <?= ($rental['status'] === 'sedang_disewa') ? 'selected' : '' ?>>Sedang Disewa</option>
                      <option value="selesai" <?= ($rental['status'] === 'selesai') ? 'selected' : '' ?>>Selesai</option>
                      <option value="dibatalkan" <?= ($rental['status'] === 'dibatalkan') ? 'selected' : '' ?>>Dibatalkan</option>
                    </select>
                  </div>

                  <div class="col-12 mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary"><i class="fa fa-save me-1"></i> Simpan Perubahan</button>
                    <a href="sewa.php" class="btn btn-secondary">Batal</a>
                  </div>
                </form>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
    <?php include 'partials/footer.php'; ?>

    <script>
      // Auto-calculation of rental fees using JavaScript
      const vehicleSelect = document.getElementById('kode_unik');
      const dateSewa = document.getElementById('tanggal_sewa');
      const dateKembali = document.getElementById('tanggal_kembali');
      const totalBiayaInput = document.getElementById('total_biaya');

      function calculateTotal() {
        const selectedOption = vehicleSelect.options[vehicleSelect.selectedIndex];
        if (!selectedOption || selectedOption.value === "") {
          totalBiayaInput.value = "";
          return;
        }

        const pricePerDay = parseFloat(selectedOption.getAttribute('data-price')) || 0;
        const sewaVal = dateSewa.value;
        const kembaliVal = dateKembali.value;

        if (sewaVal && kembaliVal) {
          const t1 = new Date(sewaVal);
          const t2 = new Date(kembaliVal);
          const diffMs = t2 - t1;

          if (diffMs > 0) {
            // Convert to days, rounding up
            const diffDays = Math.ceil(diffMs / (1000 * 60 * 60 * 24));
            totalBiayaInput.value = diffDays * pricePerDay;
          } else {
            totalBiayaInput.value = 0;
          }
        }
      }

      vehicleSelect.addEventListener('change', calculateTotal);
      dateSewa.addEventListener('change', calculateTotal);
      dateKembali.addEventListener('change', calculateTotal);
    </script>
</body>
</html>
