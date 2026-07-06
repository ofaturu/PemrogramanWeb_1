<?php
require_once 'config.php';

// Secure page: user must be logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['user_role'] ?? 'user';
$nama_user = htmlspecialchars($_SESSION['user_nama']);

// Metric calculations
$total_income = 0;
$active_bookings = 0;
$total_vehicles = 0;
$total_users = 0;
$personal_spent = 0;
$personal_bookings = 0;

if ($role === 'admin') {
    // Total income (verified/completed bookings)
    $res = mysqli_query($mysqli, "SELECT SUM(total_biaya) AS total FROM penyewaan WHERE status IN ('sedang_disewa', 'selesai')");
    $row = mysqli_fetch_assoc($res);
    $total_income = $row['total'] ?? 0;

    // Active bookings
    $res = mysqli_query($mysqli, "SELECT COUNT(*) AS total FROM penyewaan WHERE status = 'sedang_disewa'");
    $row = mysqli_fetch_assoc($res);
    $active_bookings = $row['total'] ?? 0;

    // Total vehicles
    $res = mysqli_query($mysqli, "SELECT COUNT(*) AS total FROM kendaraan");
    $row = mysqli_fetch_assoc($res);
    $total_vehicles = $row['total'] ?? 0;

    // Total users (customers)
    $res = mysqli_query($mysqli, "SELECT COUNT(*) AS total FROM users WHERE role = 'user'");
    $row = mysqli_fetch_assoc($res);
    $total_users = $row['total'] ?? 0;

    // Monthly income (Last 6 Months)
    $monthly_income_data = [];
    $monthly_income_labels = [];
    $res = mysqli_query($mysqli, "
        SELECT DATE_FORMAT(tanggal_sewa, '%M %Y') AS month, SUM(total_biaya) AS total, DATE_FORMAT(tanggal_sewa, '%Y-%m') AS raw_month
        FROM penyewaan 
        WHERE status IN ('sedang_disewa', 'selesai')
        GROUP BY raw_month 
        ORDER BY raw_month ASC 
        LIMIT 6
    ");
    while ($row = mysqli_fetch_assoc($res)) {
        $monthly_income_labels[] = $row['month'];
        $monthly_income_data[] = intval($row['total']);
    }

    // Top Vehicle Brands
    $top_brands_data = [];
    $top_brands_labels = [];
    $res = mysqli_query($mysqli, "
        SELECT m.nama_merk, COUNT(p.id_sewa) AS count 
        FROM penyewaan p 
        JOIN kendaraan k ON p.kode_unik_kendaraan = k.kode_unik_kendaraan 
        JOIN merk_kendaraan m ON k.id_merk = m.id_merk 
        GROUP BY m.id_merk 
        ORDER BY count DESC 
        LIMIT 5
    ");
    while ($row = mysqli_fetch_assoc($res)) {
        $top_brands_labels[] = ucwords($row['nama_merk']);
        $top_brands_data[] = intval($row['count']);
    }

} else {
    // Active bookings (Personal)
    $stmt = mysqli_prepare($mysqli, "SELECT COUNT(*) FROM penyewaan WHERE id_user = ? AND status = 'sedang_disewa'");
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $active_bookings);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);

    // Total spent (Personal)
    $stmt = mysqli_prepare($mysqli, "SELECT SUM(total_biaya) FROM penyewaan WHERE id_user = ? AND status IN ('sedang_disewa', 'selesai')");
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $personal_spent);
    mysqli_stmt_fetch($stmt);
    $personal_spent = $personal_spent ?? 0;
    mysqli_stmt_close($stmt);

    // Total bookings (Personal)
    $stmt = mysqli_prepare($mysqli, "SELECT COUNT(*) FROM penyewaan WHERE id_user = ?");
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $personal_bookings);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);

    // Personal bookings per month (Last 6 Months)
    $personal_bookings_data = [];
    $personal_bookings_labels = [];
    $stmt = mysqli_prepare($mysqli, "
        SELECT DATE_FORMAT(tanggal_sewa, '%M %Y') AS month, COUNT(*) AS count, DATE_FORMAT(tanggal_sewa, '%Y-%m') AS raw_month
        FROM penyewaan 
        WHERE id_user = ?
        GROUP BY raw_month 
        ORDER BY raw_month ASC 
        LIMIT 6
    ");
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $personal_bookings_labels[] = $row['month'];
        $personal_bookings_data[] = intval($row['count']);
    }
    mysqli_stmt_close($stmt);
}
?>
<!DOCTYPE html>
<html lang="id">
<?php
$title = 'Analitik & Grafik — FTrans';
include 'partials/head.php';
?>
<!-- Include Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<body>
  <?php 
  $activePage = 'analytics';
  include 'partials/sidebar.php'; 
  ?>
  <div class="wrapper d-flex flex-column min-vh-100">
    <?php 
    $pageTitle = 'Analitik & Grafik';
    include 'partials/header.php'; 
    ?>
    <div class="body flex-grow-1">
      <div class="container-lg px-4">
        
        <!-- Summary Cards Row -->
        <div class="row g-4 mb-4">
          <?php if ($role === 'admin'): ?>
            <!-- Admin Widgets -->
            <div class="col-sm-6 col-xl-3">
              <div class="card border-0 shadow-sm bg-primary text-white h-100 overflow-hidden position-relative">
                <div class="card-body p-4 d-flex flex-column justify-content-between">
                  <div>
                    <div class="text-white-50 small fw-semibold text-uppercase mb-2">Total Pendapatan</div>
                    <div class="fs-3 fw-bold">Rp <?= number_format($total_income, 0, ',', '.') ?></div>
                  </div>
                  <i class="fa fa-wallet position-absolute end-0 bottom-0 mb-3 me-3 text-white-50 opacity-25" style="font-size: 4rem;"></i>
                </div>
              </div>
            </div>
            <div class="col-sm-6 col-xl-3">
              <div class="card border-0 shadow-sm bg-info text-white h-100 overflow-hidden position-relative">
                <div class="card-body p-4 d-flex flex-column justify-content-between">
                  <div>
                    <div class="text-white-50 small fw-semibold text-uppercase mb-2">Sewa Aktif</div>
                    <div class="fs-3 fw-bold"><?= $active_bookings ?> Kendaraan</div>
                  </div>
                  <i class="fa fa-key position-absolute end-0 bottom-0 mb-3 me-3 text-white-50 opacity-25" style="font-size: 4rem;"></i>
                </div>
              </div>
            </div>
            <div class="col-sm-6 col-xl-3">
              <div class="card border-0 shadow-sm bg-success text-white h-100 overflow-hidden position-relative">
                <div class="card-body p-4 d-flex flex-column justify-content-between">
                  <div>
                    <div class="text-white-50 small fw-semibold text-uppercase mb-2">Armada Kendaraan</div>
                    <div class="fs-3 fw-bold"><?= $total_vehicles ?> Unit</div>
                  </div>
                  <i class="fa fa-car position-absolute end-0 bottom-0 mb-3 me-3 text-white-50 opacity-25" style="font-size: 4rem;"></i>
                </div>
              </div>
            </div>
            <div class="col-sm-6 col-xl-3">
              <div class="card border-0 shadow-sm bg-warning text-white h-100 overflow-hidden position-relative">
                <div class="card-body p-4 d-flex flex-column justify-content-between">
                  <div>
                    <div class="text-white-50 small fw-semibold text-uppercase mb-2">Pelanggan Terdaftar</div>
                    <div class="fs-3 fw-bold"><?= $total_users ?> Pengguna</div>
                  </div>
                  <i class="fa fa-users position-absolute end-0 bottom-0 mb-3 me-3 text-white-50 opacity-25" style="font-size: 4rem;"></i>
                </div>
              </div>
            </div>
          <?php else: ?>
            <!-- User Widgets -->
            <div class="col-sm-6 col-xl-4">
              <div class="card border-0 shadow-sm bg-primary text-white h-100 overflow-hidden position-relative">
                <div class="card-body p-4 d-flex flex-column justify-content-between">
                  <div>
                    <div class="text-white-50 small fw-semibold text-uppercase mb-2">Total Pengeluaran</div>
                    <div class="fs-3 fw-bold">Rp <?= number_format($personal_spent, 0, ',', '.') ?></div>
                  </div>
                  <i class="fa fa-money-bill-wave position-absolute end-0 bottom-0 mb-3 me-3 text-white-50 opacity-25" style="font-size: 4rem;"></i>
                </div>
              </div>
            </div>
            <div class="col-sm-6 col-xl-4">
              <div class="card border-0 shadow-sm bg-info text-white h-100 overflow-hidden position-relative">
                <div class="card-body p-4 d-flex flex-column justify-content-between">
                  <div>
                    <div class="text-white-50 small fw-semibold text-uppercase mb-2">Sewa Sedang Aktif</div>
                    <div class="fs-3 fw-bold"><?= $active_bookings ?> Sewa</div>
                  </div>
                  <i class="fa fa-road position-absolute end-0 bottom-0 mb-3 me-3 text-white-50 opacity-25" style="font-size: 4rem;"></i>
                </div>
              </div>
            </div>
            <div class="col-sm-12 col-xl-4">
              <div class="card border-0 shadow-sm bg-success text-white h-100 overflow-hidden position-relative">
                <div class="card-body p-4 d-flex flex-column justify-content-between">
                  <div>
                    <div class="text-white-50 small fw-semibold text-uppercase mb-2">Total Transaksi</div>
                    <div class="fs-3 fw-bold"><?= $personal_bookings ?> Kali Rental</div>
                  </div>
                  <i class="fa fa-history position-absolute end-0 bottom-0 mb-3 me-3 text-white-50 opacity-25" style="font-size: 4rem;"></i>
                </div>
              </div>
            </div>
          <?php endif; ?>
        </div>

        <!-- Charts Section -->
        <div class="row">
          <?php if ($role === 'admin'): ?>
            <!-- Admin Charts -->
            <div class="col-lg-8 mb-4">
              <div class="card shadow-sm h-100 border border-secondary border-opacity-10">
                <div class="card-header bg-body-tertiary">
                  <h6 class="mb-0 text-body"><i class="fa fa-chart-line me-2 text-primary"></i>Tren Pendapatan Bulanan (6 Bulan Terakhir)</h6>
                </div>
                <div class="card-body p-4">
                  <div style="position: relative; height:300px; width:100%;">
                    <canvas id="incomeChart"></canvas>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-lg-4 mb-4">
              <div class="card shadow-sm h-100 border border-secondary border-opacity-10">
                <div class="card-header bg-body-tertiary">
                  <h6 class="mb-0 text-body"><i class="fa fa-chart-bar me-2 text-success"></i>Merk Terfavorit (Populer)</h6>
                </div>
                <div class="card-body p-4 d-flex align-items-center justify-content-center">
                  <div style="position: relative; height:300px; width:100%;">
                    <canvas id="brandChart"></canvas>
                  </div>
                </div>
              </div>
            </div>
          <?php else: ?>
            <!-- User Charts -->
            <div class="col-lg-12 mb-4">
              <div class="card shadow-sm h-100 border border-secondary border-opacity-10">
                <div class="card-header bg-body-tertiary">
                  <h6 class="mb-0 text-body"><i class="fa fa-chart-bar me-2 text-primary"></i>Aktivitas Rental Anda per Bulan</h6>
                </div>
                <div class="card-body p-4">
                  <?php if (count($personal_bookings_data) > 0): ?>
                    <div style="position: relative; height:350px; width:100%;">
                      <canvas id="personalChart"></canvas>
                    </div>
                  <?php else: ?>
                    <div class="text-center py-5 text-muted">
                      <i class="fa fa-folder-open fa-3x mb-3 text-secondary"></i>
                      <p class="mb-0">Belum ada aktivitas rental yang tercatat untuk memvisualisasikan grafik.</p>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          <?php endif; ?>
        </div>

      </div>
    </div>
    <?php include 'partials/footer.php'; ?>
  </div>

  <script>
    document.addEventListener("DOMContentLoaded", function() {
      // Detect theme for styling charts
      const isDark = document.documentElement.getAttribute('data-coreui-theme') === 'dark';
      const textColor = isDark ? 'rgba(255,255,255,0.7)' : 'rgba(33,37,41,0.7)';
      const gridColor = isDark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.05)';

      const chartOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            labels: {
              color: textColor
            }
          }
        },
        scales: {
          x: {
            grid: { color: gridColor },
            ticks: { color: textColor }
          },
          y: {
            grid: { color: gridColor },
            ticks: { color: textColor }
          }
        }
      };

      <?php if ($role === 'admin'): ?>
      // 1. Income Chart (Admin)
      const ctxIncome = document.getElementById('incomeChart').getContext('2d');
      new Chart(ctxIncome, {
        type: 'line',
        data: {
          labels: <?= json_encode($monthly_income_labels) ?>,
          datasets: [{
            label: 'Pendapatan (Rp)',
            data: <?= json_encode($monthly_income_data) ?>,
            borderColor: '#5856d6',
            backgroundColor: 'rgba(88, 86, 214, 0.1)',
            borderWidth: 3,
            fill: true,
            tension: 0.3
          }]
        },
        options: chartOptions
      });

      // 2. Popular Brand Chart (Admin)
      const ctxBrand = document.getElementById('brandChart').getContext('2d');
      new Chart(ctxBrand, {
        type: 'doughnut',
        data: {
          labels: <?= json_encode($top_brands_labels) ?>,
          datasets: [{
            label: 'Kali Disewa',
            data: <?= json_encode($top_brands_data) ?>,
            backgroundColor: [
              '#5856d6',
              '#39f',
              '#1b9e3e',
              '#f9b115',
              '#e55353'
            ],
            borderWidth: isDark ? 2 : 1,
            borderColor: isDark ? '#212631' : '#fff'
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              position: 'bottom',
              labels: { color: textColor }
            }
          }
        }
      });
      <?php else: ?>
      
      <?php if (count($personal_bookings_data) > 0): ?>
      // 3. Personal Rent Activity (User)
      const ctxPersonal = document.getElementById('personalChart').getContext('2d');
      new Chart(ctxPersonal, {
        type: 'bar',
        data: {
          labels: <?= json_encode($personal_bookings_labels) ?>,
          datasets: [{
            label: 'Jumlah Transaksi Sewa',
            data: <?= json_encode($personal_bookings_data) ?>,
            backgroundColor: 'rgba(51, 153, 255, 0.75)',
            borderColor: '#39f',
            borderWidth: 1,
            borderRadius: 5
          }]
        },
        options: chartOptions
      });
      <?php endif; ?>
      <?php endif; ?>
    });
  </script>
</body>
</html>
