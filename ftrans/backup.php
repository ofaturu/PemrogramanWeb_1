<?php
require_once 'config.php';

// Secure page - Admin only
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// 1. Handle SQL Backup Generation and Download on POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'download') {
    $tables = [];
    $result = mysqli_query($mysqli, "SHOW TABLES");
    while ($row = mysqli_fetch_row($result)) {
        $tables[] = $row[0];
    }

    $sql = "-- FTrans Car Rental Database Backup\n";
    $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $sql .= "-- MySQL Server Version: " . mysqli_get_server_info($mysqli) . "\n";
    $sql .= "-- PHP Version: " . phpversion() . "\n\n";
    $sql .= "SET FOREIGN_KEY_CHECKS=0;\n";
    $sql .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
    $sql .= "START TRANSACTION;\n\n";

    foreach ($tables as $table) {
        // Table structure
        $res_struct = mysqli_query($mysqli, "SHOW CREATE TABLE `$table`");
        $row_struct = mysqli_fetch_row($res_struct);
        $sql .= "-- --------------------------------------------------------\n";
        $sql .= "-- Table structure for table `$table`\n";
        $sql .= "-- --------------------------------------------------------\n";
        $sql .= "DROP TABLE IF EXISTS `$table`;\n";
        $sql .= $row_struct[1] . ";\n\n";

        // Table data
        $res_data = mysqli_query($mysqli, "SELECT * FROM `$table`");
        $num_fields = mysqli_num_fields($res_data);
        
        $sql .= "-- Dumping data for table `$table`\n";
        $has_rows = false;
        while ($row = mysqli_fetch_row($res_data)) {
            if (!$has_rows) {
                $sql .= "INSERT INTO `$table` VALUES\n";
                $has_rows = true;
            } else {
                $sql .= ",\n";
            }
            $sql .= "(";
            for ($j = 0; $j < $num_fields; $j++) {
                if (isset($row[$j])) {
                    $val = mysqli_real_escape_string($mysqli, $row[$j]);
                    // Check if numeric and doesn't require quotes
                    if (is_numeric($row[$j]) && (strval(intval($row[$j])) === $row[$j] || strval(floatval($row[$j])) === $row[$j])) {
                        $sql .= $row[$j];
                    } else {
                        $sql .= "'" . $val . "'";
                    }
                } else {
                    $sql .= "NULL";
                }
            }
            $sql .= ")";
        }
        if ($has_rows) {
            $sql .= ";\n";
        }
        $sql .= "\n";
    }

    $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
    $sql .= "COMMIT;\n";

    // Clean buffer to prevent corruption
    if (ob_get_level()) {
        ob_end_clean();
    }

    // Output SQL file as attachment download
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="backup_ftrans_' . date('Y-m-d_H-i-s') . '.sql"');
    header('Content-Length: ' . strlen($sql));
    echo $sql;
    exit;
}

// 2. Fetch system/database info for UI display
$db_name = DB_NAME; // Loaded from config/env
$server_info = mysqli_get_server_info($mysqli);

// Count tables
$tables_count = 0;
$tables_list = [];
$res_t = mysqli_query($mysqli, "SHOW TABLES");
if ($res_t) {
    while ($r = mysqli_fetch_row($res_t)) {
        $tables_list[] = $r[0];
    }
    $tables_count = count($tables_list);
}

// Calculate DB size estimation
$db_size_mb = 0.0;
$res_s = mysqli_query($mysqli, "
    SELECT SUM(data_length + index_length) AS size 
    FROM information_schema.TABLES 
    WHERE table_schema = '" . mysqli_real_escape_string($mysqli, $db_name) . "'
");
if ($res_s) {
    $row_s = mysqli_fetch_assoc($res_s);
    $bytes = $row_s['size'] ?? 0;
    $db_size_mb = round($bytes / 1024 / 1024, 2);
}

$activePage = 'backup';
?>
<!DOCTYPE html>
<html lang="id">
<?php
$title = 'Backup Database — FTrans';
include 'partials/head.php';
?>
<body>
  <?php include 'partials/sidebar.php'; ?>
  <div class="wrapper d-flex flex-column min-vh-100">
    <?php 
    $pageTitle = 'Backup Database';
    include 'partials/header.php'; 
    ?>
    <div class="body flex-grow-1">
      <div class="container-lg px-4">
        
        <div class="row justify-content-center">
          <div class="col-lg-8">
            
            <div class="card shadow-sm border-0 mb-4" style="border-radius: 12px;">
              <div class="card-header bg-primary text-white py-3" style="border-top-left-radius: 12px; border-top-right-radius: 12px;">
                <h5 class="mb-0 fw-bold"><i class="fa fa-database me-2"></i>Pengelola Salinan Database (Backup)</h5>
              </div>
              <div class="card-body p-4 text-center">
                
                <div class="d-flex flex-column align-items-center mb-4 border-bottom pb-4">
                  <div class="stats-icon-wrapper bg-warning bg-opacity-10 text-warning mb-3" style="width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2.5rem;">
                    <i class="fa fa-server"></i>
                  </div>
                  <h4 class="fw-bold mb-1">Informasi Database Aktif</h4>
                  <p class="text-muted small">Kelola dan amankan semua data transaksi, penyewa, armada, ulasan, dan notifikasi.</p>
                </div>

                <div class="row g-3 text-start mb-4">
                  <div class="col-md-6">
                    <div class="p-3 border rounded bg-light bg-opacity-50">
                      <div class="text-muted small">Nama Database:</div>
                      <div class="fw-bold text-body-emphasis"><?= htmlspecialchars($db_name) ?></div>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="p-3 border rounded bg-light bg-opacity-50">
                      <div class="text-muted small">Versi MySQL Server:</div>
                      <div class="fw-bold text-body-emphasis"><?= htmlspecialchars($server_info) ?></div>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="p-3 border rounded bg-light bg-opacity-50">
                      <div class="text-muted small">Jumlah Tabel Terdaftar:</div>
                      <div class="fw-bold text-body-emphasis"><?= $tables_count ?> Tabel</div>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="p-3 border rounded bg-light bg-opacity-50">
                      <div class="text-muted small">Estimasi Ukuran Data:</div>
                      <div class="fw-bold text-body-emphasis"><?= $db_size_mb ?> MB</div>
                    </div>
                  </div>
                </div>

                <div class="text-start mb-4">
                  <h6 class="fw-bold"><i class="fa fa-table me-2 text-primary"></i>Daftar Tabel yang Dicakup:</h6>
                  <div class="d-flex flex-wrap gap-2 mt-2">
                    <?php foreach ($tables_list as $t): ?>
                      <span class="badge bg-secondary bg-opacity-10 text-secondary border px-3 py-2 small" style="border-radius: 6px; font-family: monospace;"><?= htmlspecialchars($t) ?></span>
                    <?php endforeach; ?>
                  </div>
                </div>

                <div class="alert alert-warning border-0 bg-warning bg-opacity-10 text-warning text-start p-3 mb-4" style="border-radius: 8px; font-size: 0.85rem;">
                  <i class="fa fa-exclamation-triangle me-2"></i><strong>Penting untuk Keamanan:</strong> File backup ini berisi data kredensial sensitif (termasuk email dan password pengguna). Pastikan untuk menyimpannya di tempat yang aman dan batasi aksesnya hanya untuk pengelola sistem yang terpercaya.
                </div>

                <form method="POST" action="">
                  <input type="hidden" name="action" value="download">
                  <button type="submit" class="btn btn-warning py-3 px-5 text-dark fw-bold shadow-sm" style="font-size: 1.05rem; border-radius: 8px;">
                    <i class="fa fa-download me-2"></i>Unduh Backup SQL Sekarang
                  </button>
                </form>

              </div>
            </div>

          </div>
        </div>

      </div>
    </div>
    <?php include 'partials/footer.php'; ?>
  </div>
</body>
</html>
