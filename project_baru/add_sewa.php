<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$error = '';

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
        // Save to database
        $stmt = mysqli_prepare($mysqli, "INSERT INTO penyewaan (id_user, kode_unik_kendaraan, tanggal_sewa, tanggal_kembali, total_biaya, status) VALUES (?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'iisssd', $id_user, $kode_unik, $tgl_sewa, $tgl_kembali, $total_biaya, $status);

        if (mysqli_stmt_execute($stmt)) {
            header('Location: sewa.php?msg=added');
            exit;
        } else {
            $error = 'Gagal menyimpan transaksi. Coba lagi.';
        }
        mysqli_stmt_close($stmt);
    }
}

// Default values for dates
$default_sewa = date('Y-m-d\TH:i');
$default_kembali = date('Y-m-d\TH:i', strtotime('+1 day'));
?>
<!DOCTYPE html>
<html lang="id">
<?php
$title = 'Tambah Penyewaan — FTrans';
include 'partials/head.php';
?>
<body>
  <?php 
  $activePage = 'sewa';
  include 'partials/sidebar.php'; 
  ?>
  <div class="wrapper d-flex flex-column min-vh-100">
    <?php 
    $pageTitle = 'Tambah Penyewaan Baru';
    include 'partials/header.php'; 
    ?>
    <div class="body flex-grow-1">
      <div class="container-lg px-4">
        
        <div class="row">
          <div class="col-12 col-lg-8">
            <div class="card mb-4 shadow-sm border border-secondary border-opacity-10">
              <div class="card-header bg-body-tertiary">
                <h5 class="mb-0 text-body"><i class="fa fa-plus me-2 text-primary"></i>Form Transaksi Penyewaan</h5>
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
                      <option value="" disabled selected>-- Pilih Penyewa --</option>
                      <?php foreach ($users as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= ($_POST['id_user'] ?? '') == $u['id'] ? 'selected' : '' ?>>
                          <?= htmlspecialchars($u['nama']) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                  <div class="col-md-6">
                    <label class="form-label" for="kode_unik">Kendaraan *</label>
                    <select id="kode_unik" name="kode_unik_kendaraan" class="form-select" required>
                      <option value="" disabled selected>-- Pilih Kendaraan --</option>
                      <?php foreach ($vehicles as $k): ?>
                        <option value="<?= $k['kode_unik_kendaraan'] ?>" data-price="<?= $k['harga_per_hari'] ?>" <?= ($_POST['kode_unik_kendaraan'] ?? '') == $k['kode_unik_kendaraan'] ? 'selected' : '' ?>>
                          <?= htmlspecialchars($k['nama_kendaraan']) ?> (Kode: <?= $k['kode_unik_kendaraan'] ?>) - Rp <?= number_format($k['harga_per_hari'], 0, ',', '.') ?>/hari
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                  <div class="col-md-6">
                    <label class="form-label" for="tanggal_sewa">Tanggal Sewa *</label>
                    <input type="datetime-local" id="tanggal_sewa" name="tanggal_sewa" class="form-control" value="<?= htmlspecialchars($_POST['tanggal_sewa'] ?? $default_sewa) ?>" required>
                  </div>

                  <div class="col-md-6">
                    <label class="form-label" for="tanggal_kembali">Tanggal Kembali *</label>
                    <input type="datetime-local" id="tanggal_kembali" name="tanggal_kembali" class="form-control" value="<?= htmlspecialchars($_POST['tanggal_kembali'] ?? $default_kembali) ?>" required>
                  </div>

                  <div class="col-md-6">
                    <label class="form-label" for="total_biaya">Total Biaya (Rp) *</label>
                    <div class="input-group">
                      <span class="input-group-text">Rp</span>
                      <input type="number" id="total_biaya" name="total_biaya" class="form-control" placeholder="0" min="0" value="<?= htmlspecialchars($_POST['total_biaya'] ?? '') ?>" required>
                    </div>
                    <div class="form-text text-muted">Akan dihitung otomatis berdasarkan durasi hari & harga sewa kendaraan.</div>
                  </div>

                  <div class="col-md-6">
                    <label class="form-label" for="status">Status Penyewaan *</label>
                    <select id="status" name="status" class="form-select" required>
                      <option value="booking" <?= ($_POST['status'] ?? '') === 'booking' ? 'selected' : '' ?>>Booking</option>
                      <option value="sedang_disewa" <?= ($_POST['status'] ?? '') === 'sedang_disewa' ? 'selected' : '' ?>>Sedang Disewa</option>
                      <option value="selesai" <?= ($_POST['status'] ?? '') === 'selesai' ? 'selected' : '' ?>>Selesai</option>
                      <option value="dibatalkan" <?= ($_POST['status'] ?? '') === 'dibatalkan' ? 'selected' : '' ?>>Dibatalkan</option>
                    </select>
                  </div>

                  <div class="col-12 mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary"><i class="fa fa-save me-1"></i> Simpan</button>
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
