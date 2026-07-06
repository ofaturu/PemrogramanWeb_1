<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$error_add = '';
$error_edit = '';
$error_edit_id = '';

$get_car_code = trim($_GET['rent_vehicle'] ?? '');
$default_sewa = date('Y-m-d\TH:i', strtotime('+1 hour'));
$default_kembali = date('Y-m-d\TH:i', strtotime('+1 day +1 hour'));

// Fetch users for dropdown
if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
    $users_query = "SELECT id, nama FROM users ORDER BY nama ASC";
    $users_res = mysqli_query($mysqli, $users_query);
    $users = mysqli_fetch_all($users_res, MYSQLI_ASSOC);
} else {
    $users = [
        [
            'id' => $_SESSION['user_id'],
            'nama' => $_SESSION['user_nama']
        ]
    ];
}

// Fetch vehicles for dropdown
$vehicles_query = "SELECT kode_unik_kendaraan, nama_kendaraan, harga_per_hari FROM kendaraan ORDER BY nama_kendaraan ASC";
$vehicles_res = mysqli_query($mysqli, $vehicles_query);
$vehicles = mysqli_fetch_all($vehicles_res, MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
            $id_user = $_POST['id_user'] ?? '';
            $status = $_POST['status'] ?? 'booking';
        } else {
            $id_user = $_SESSION['user_id'];
            $status = 'booking';
        }
        $kode_unik = $_POST['kode_unik_kendaraan'] ?? '';
        $tgl_sewa = $_POST['tanggal_sewa'] ?? '';
        $tgl_kembali = $_POST['tanggal_kembali'] ?? '';
        $total_biaya = $_POST['total_biaya'] ?? '';

        if (empty($id_user) || empty($kode_unik) || empty($tgl_sewa) || empty($tgl_kembali) || $total_biaya === '') {
            $error_add = 'Semua field wajib diisi.';
        } elseif (strtotime($tgl_kembali) < strtotime($tgl_sewa)) {
            $error_add = 'Tanggal kembali tidak boleh mendahului tanggal sewa.';
        } else {
            $stmt = mysqli_prepare($mysqli, "INSERT INTO penyewaan (id_user, kode_unik_kendaraan, tanggal_sewa, tanggal_kembali, total_biaya, status) VALUES (?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, 'iissis', $id_user, $kode_unik, $tgl_sewa, $tgl_kembali, $total_biaya, $status);

            if (mysqli_stmt_execute($stmt)) {
                $id_sewa = mysqli_insert_id($mysqli);

                // Update vehicle status to 'disewa'
                mysqli_query($mysqli, "UPDATE kendaraan SET status_kendaraan = 'disewa' WHERE kode_unik_kendaraan = " . intval($kode_unik));

                // Fetch vehicle and user details to draft custom notification messages
                $user_info_res = mysqli_query($mysqli, "SELECT nama FROM users WHERE id = " . intval($id_user));
                $user_info = mysqli_fetch_assoc($user_info_res);
                $user_name = $user_info['nama'] ?? 'User';

                $veh_info_res = mysqli_query($mysqli, "SELECT nama_kendaraan FROM kendaraan WHERE kode_unik_kendaraan = " . intval($kode_unik));
                $veh_info = mysqli_fetch_assoc($veh_info_res);
                $veh_name = $veh_info['nama_kendaraan'] ?? 'Kendaraan';

                // Add notifications
                add_notification($id_user, "Pemesanan Kendaraan Berhasil", "Pemesanan kendaraan {$veh_name} Anda berhasil dibuat. Silakan selesaikan pembayaran.");
                add_notification(null, "Pemesanan Baru Masuk", "Penyewa {$user_name} baru saja melakukan pemesanan kendaraan {$veh_name}.");

                require_once 'send_invoice.php';
                try {
                    send_invoice_email($id_sewa, 'invoice');
                } catch (\Exception $e) {
                    error_log("Failed to send SMTP invoice email: " . $e->getMessage());
                }
                
                header('Location: sewa.php?msg=added');
                exit;
            } else {
                $error_add = 'Gagal menyimpan transaksi. Coba lagi.';
            }
            mysqli_stmt_close($stmt);
        }
    } elseif ($action === 'edit') {
        $id_sewa = $_POST['id_sewa'] ?? '';
        $error_edit_id = $id_sewa;

        if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
            $id_user = $_POST['id_user'] ?? '';
            $status = $_POST['status'] ?? 'booking';
        } else {
            // Non-admin can only edit their own rental
            $check_stmt = mysqli_prepare($mysqli, "SELECT id_user, status, bukti_pembayaran FROM penyewaan WHERE id_sewa = ?");
            mysqli_stmt_bind_param($check_stmt, 'i', $id_sewa);
            mysqli_stmt_execute($check_stmt);
            mysqli_stmt_bind_result($check_stmt, $owner_id, $db_status, $db_bukti);
            mysqli_stmt_fetch($check_stmt);
            mysqli_stmt_close($check_stmt);
            
            if ($owner_id != $_SESSION['user_id']) {
                header('Location: sewa.php?error=unauthorized');
                exit;
            }

            if (!empty($db_bukti) || $db_status !== 'booking') {
                header('Location: sewa.php?error=cannot_edit_paid');
                exit;
            }
            
            $id_user = $_SESSION['user_id'];
            $status = $db_status;
        }

        $kode_unik = $_POST['kode_unik_kendaraan'] ?? '';
        $tgl_sewa = $_POST['tanggal_sewa'] ?? '';
        $tgl_kembali = $_POST['tanggal_kembali'] ?? '';
        $total_biaya = $_POST['total_biaya'] ?? '';

        if (empty($id_sewa)) {
            $error_edit = 'ID transaksi penyewaan tidak ditemukan.';
        } elseif (empty($id_user) || empty($kode_unik) || empty($tgl_sewa) || empty($tgl_kembali) || $total_biaya === '') {
            $error_edit = 'Semua field wajib diisi.';
        } elseif (strtotime($tgl_kembali) < strtotime($tgl_sewa)) {
            $error_edit = 'Tanggal kembali tidak boleh mendahului tanggal sewa.';
        } else {
            // Get current vehicle code before update to see if it changed
            $orig_stmt = mysqli_prepare($mysqli, "SELECT kode_unik_kendaraan FROM penyewaan WHERE id_sewa = ?");
            mysqli_stmt_bind_param($orig_stmt, 'i', $id_sewa);
            mysqli_stmt_execute($orig_stmt);
            mysqli_stmt_bind_result($orig_stmt, $orig_kode);
            mysqli_stmt_fetch($orig_stmt);
            mysqli_stmt_close($orig_stmt);

            $upd = mysqli_prepare($mysqli, "UPDATE penyewaan SET id_user = ?, kode_unik_kendaraan = ?, tanggal_sewa = ?, tanggal_kembali = ?, total_biaya = ?, status = ? WHERE id_sewa = ?");
            mysqli_stmt_bind_param($upd, 'iissisi', $id_user, $kode_unik, $tgl_sewa, $tgl_kembali, $total_biaya, $status, $id_sewa);

            if (mysqli_stmt_execute($upd)) {
                // Fetch vehicle name for notification
                $veh_info_res = mysqli_query($mysqli, "SELECT nama_kendaraan FROM kendaraan WHERE kode_unik_kendaraan = " . intval($kode_unik));
                $veh_info = mysqli_fetch_assoc($veh_info_res);
                $veh_name = $veh_info['nama_kendaraan'] ?? 'Kendaraan';

                // Map status label for notification message
                $status_clean = str_replace('_', ' ', $status);
                add_notification($id_user, "Pembaruan Transaksi #INV-{$id_sewa}", "Transaksi sewa kendaraan {$veh_name} Anda telah diperbarui. Status saat ini: " . ucfirst($status_clean));

                // If status was changed to sedang_disewa, trigger receipt email
                if ($status === 'sedang_disewa') {
                    require_once 'send_invoice.php';
                    try {
                        send_invoice_email($id_sewa, 'receipt');
                    } catch (\Exception $e) {
                        error_log("Failed to send SMTP receipt email from edit modal: " . $e->getMessage());
                    }
                }

                // If vehicle changed, set original vehicle back to 'tersedia'
                if ($orig_kode != $kode_unik) {
                    mysqli_query($mysqli, "UPDATE kendaraan SET status_kendaraan = 'tersedia' WHERE kode_unik_kendaraan = " . intval($orig_kode));
                }

                // Update vehicle status based on rental status
                if ($status === 'selesai' || $status === 'batal') {
                    mysqli_query($mysqli, "UPDATE kendaraan SET status_kendaraan = 'tersedia' WHERE kode_unik_kendaraan = " . intval($kode_unik));
                } else {
                    mysqli_query($mysqli, "UPDATE kendaraan SET status_kendaraan = 'disewa' WHERE kode_unik_kendaraan = " . intval($kode_unik));
                }
                
                header('Location: sewa.php?msg=updated');
                exit;
            } else {
                $error_edit = 'Gagal menyimpan perubahan. Coba lagi.';
            }
        }
    } elseif ($action === 'verify_payment') {
        $id_sewa = $_POST['id_sewa'] ?? '';
        
        // Secure - only admin can verify
        if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
            header('Location: sewa.php?error=unauthorized');
            exit;
        }

        if (!empty($id_sewa)) {
            // Get vehicle code first
            $v_stmt = mysqli_prepare($mysqli, "SELECT kode_unik_kendaraan FROM penyewaan WHERE id_sewa = ?");
            mysqli_stmt_bind_param($v_stmt, 'i', $id_sewa);
            mysqli_stmt_execute($v_stmt);
            mysqli_stmt_bind_result($v_stmt, $v_kode);
            mysqli_stmt_fetch($v_stmt);
            mysqli_stmt_close($v_stmt);

            // Update status to sedang_disewa
            $upd = mysqli_prepare($mysqli, "UPDATE penyewaan SET status = 'sedang_disewa' WHERE id_sewa = ?");
            mysqli_stmt_bind_param($upd, 'i', $id_sewa);
            
            if (mysqli_stmt_execute($upd)) {
                mysqli_stmt_close($upd);

                // Fetch id_user and vehicle name for notification
                $rental_details_res = mysqli_query($mysqli, "
                    SELECT p.id_user, k.nama_kendaraan 
                    FROM penyewaan p 
                    JOIN kendaraan k ON p.kode_unik_kendaraan = k.kode_unik_kendaraan 
                    WHERE p.id_sewa = " . intval($id_sewa)
                );
                $rental_details = mysqli_fetch_assoc($rental_details_res);
                $notif_user_id = $rental_details['id_user'] ?? 0;
                $veh_name = $rental_details['nama_kendaraan'] ?? 'Kendaraan';

                // Add notification
                add_notification($notif_user_id, "Pembayaran Terverifikasi", "Pembayaran sewa kendaraan {$veh_name} Anda telah diverifikasi oleh Admin. Selamat berkendara!");

                if (!empty($v_kode)) {
                    mysqli_query($mysqli, "UPDATE kendaraan SET status_kendaraan = 'disewa' WHERE kode_unik_kendaraan = " . intval($v_kode));
                }

                require_once 'send_invoice.php';
                try {
                    send_invoice_email($id_sewa, 'receipt');
                } catch (\Exception $e) {
                    error_log("Failed to send SMTP receipt email: " . $e->getMessage());
                }
                
                header('Location: sewa.php?msg=payment_verified');
                exit;
            } else {
                mysqli_stmt_close($upd);
                $error_edit = 'Gagal memverifikasi pembayaran. Coba lagi.';
            }
        }
    }
}

// Pagination settings
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) {
    $page = 1;
}
$offset = ($page - 1) * $limit;

// Count total items and Fetch rentals depending on role
if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
    $count_res = mysqli_query($mysqli, "SELECT COUNT(*) FROM penyewaan");
    $count_row = mysqli_fetch_row($count_res);
    $total_items = $count_row[0];

    $total_pages = ceil($total_items / $limit);
    if ($page > $total_pages && $total_pages > 0) {
        $page = $total_pages;
        $offset = ($page - 1) * $limit;
    }

    $query = "SELECT p.*, u.nama AS nama_user, k.nama_kendaraan, k.harga_per_hari 
              FROM penyewaan p 
              LEFT JOIN users u ON p.id_user = u.id 
              LEFT JOIN kendaraan k ON p.kode_unik_kendaraan = k.kode_unik_kendaraan 
              ORDER BY p.id_sewa DESC
              LIMIT ? OFFSET ?";
    $stmt = mysqli_prepare($mysqli, $query);
    mysqli_stmt_bind_param($stmt, 'ii', $limit, $offset);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rentals = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
} else {
    $count_stmt = mysqli_prepare($mysqli, "SELECT COUNT(*) FROM penyewaan WHERE id_user = ?");
    mysqli_stmt_bind_param($count_stmt, 'i', $_SESSION['user_id']);
    mysqli_stmt_execute($count_stmt);
    mysqli_stmt_bind_result($count_stmt, $total_items);
    mysqli_stmt_fetch($count_stmt);
    mysqli_stmt_close($count_stmt);

    $total_pages = ceil($total_items / $limit);
    if ($page > $total_pages && $total_pages > 0) {
        $page = $total_pages;
        $offset = ($page - 1) * $limit;
    }

    $query = "SELECT p.*, u.nama AS nama_user, k.nama_kendaraan, k.harga_per_hari 
              FROM penyewaan p 
              LEFT JOIN users u ON p.id_user = u.id 
              LEFT JOIN kendaraan k ON p.kode_unik_kendaraan = k.kode_unik_kendaraan 
              WHERE p.id_user = ?
              ORDER BY p.id_sewa DESC
              LIMIT ? OFFSET ?";
    $stmt = mysqli_prepare($mysqli, $query);
    mysqli_stmt_bind_param($stmt, 'iii', $_SESSION['user_id'], $limit, $offset);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rentals = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
}

$total = count($rentals);

$msg = $_GET['msg'] ?? '';

// Retrieve and sanitize GET parameters from landing page
$get_car_code = isset($_GET['car_code']) ? intval($_GET['car_code']) : '';
$get_tgl_sewa = isset($_GET['tanggal_sewa']) ? trim($_GET['tanggal_sewa']) : '';
$get_tgl_kembali = isset($_GET['tanggal_kembali']) ? trim($_GET['tanggal_kembali']) : '';

// Default values for dates in Add modal
$default_sewa = !empty($get_tgl_sewa) ? date('Y-m-d\TH:i', strtotime($get_tgl_sewa)) : date('Y-m-d\TH:i');
$default_kembali = !empty($get_tgl_kembali) ? date('Y-m-d\TH:i', strtotime($get_tgl_kembali)) : date('Y-m-d\TH:i', strtotime('+1 day'));
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

        <?php if (isset($_GET['error'])): ?>
            <?php 
            $err_msg = 'Terjadi kesalahan sistem.';
            if ($_GET['error'] === 'cannot_edit_paid') {
                $err_msg = 'Transaksi tidak dapat diedit karena pembayaran telah diunggah atau transaksi sedang diproses. Silakan hubungi Admin untuk melakukan perubahan.';
            } elseif ($_GET['error'] === 'cannot_delete_paid') {
                $err_msg = 'Transaksi tidak dapat dibatalkan/dihapus karena pembayaran telah diunggah atau transaksi sedang diproses. Silakan hubungi Admin untuk mengajukan pembatalan.';
            } elseif ($_GET['error'] === 'unauthorized') {
                $err_msg = 'Akses ditolak: Anda tidak memiliki wewenang untuk mengakses transaksi ini.';
            }
            ?>
            <div class="alert alert-danger alert-dismissible fade show border-0 bg-danger bg-opacity-10 text-danger p-3 mb-4" style="border-radius: 8px;" role="alert">
                <i class="fa fa-exclamation-triangle me-2"></i><strong>Gagal!</strong> <?= htmlspecialchars($err_msg) ?>
                <button type="button" class="btn-close" data-coreui-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card mb-4 shadow-sm border border-secondary border-opacity-10">
          <div class="card-header d-flex align-items-center justify-content-between bg-body-tertiary">
            <h5 class="mb-0 text-body"><i class="fa fa-receipt me-2 text-primary"></i>Daftar Transaksi Penyewaan</h5>
            <div class="d-flex gap-2">
              <div class="dropdown">
                <button class="btn btn-success btn-sm dropdown-toggle text-nowrap" type="button" data-coreui-toggle="dropdown" aria-expanded="false">
                  <i class="fa fa-download me-1"></i> Export
                </button>
                <ul class="dropdown-menu">
                  <li><a class="dropdown-item" href="export.php?target=sewa&format=excel" target="_blank"><i class="fa fa-file-excel text-success me-2"></i> Excel (.xlsx)</a></li>
                  <li><a class="dropdown-item" href="export.php?target=sewa&format=word" target="_blank"><i class="fa fa-file-word text-primary me-2"></i> Word (.docx)</a></li>
                  <li><a class="dropdown-item" href="export.php?target=sewa&format=pdf" target="_blank"><i class="fa fa-file-pdf text-danger me-2"></i> PDF (.pdf)</a></li>
                </ul>
              </div>
              <button type="button" class="btn btn-primary btn-sm" data-coreui-toggle="modal" data-coreui-target="#addSewaModal"><i class="fa fa-plus me-1"></i> Tambah Penyewaan</button>
            </div>
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
                          <td class="ps-4 fw-semibold text-body-secondary"><?= $offset + $i + 1 ?></td>
                           <td class="text-body fw-bold">
                              <?= htmlspecialchars($r['nama_user'] ?? 'N/A') ?>
                              <div class="text-body-secondary font-monospace mt-1 d-flex align-items-center gap-1" style="font-size: 0.75rem; font-weight: normal;">
                                  #INV-<?= str_pad($r['id_sewa'], 5, '0', STR_PAD_LEFT) ?>
                                  <?php if ($r['status'] === 'sedang_disewa' || $r['status'] === 'selesai'): ?>
                                      <a href="export.php?target=sewa&format=pdf&id=<?= urlencode($r['id_sewa']) ?>" class="text-success p-0" title="Cetak Struk" target="_blank">
                                          <i class="fa fa-print"></i>
                                      </a>
                                  <?php endif; ?>
                              </div>
                           </td>
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
                                   <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                                   <a href="export.php?target=sewa&format=pdf&id=<?= urlencode($r['id_sewa']) ?>" class="btn btn-outline-secondary d-flex align-items-center gap-1" title="Cetak Struk/Invoice PDF" target="_blank">
                                       <i class="fa fa-print"></i> Struk
                                   </a>
                                   <?php endif; ?>

                                  <!-- Payment Action -->
                                  <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                                      <?php if ($r['status'] === 'booking' && !empty($r['bukti_pembayaran'])): ?>
                                          <button type="button" class="btn btn-outline-success d-flex align-items-center gap-1" data-coreui-toggle="modal" data-coreui-target="#verifyPaymentModal-<?= $r['id_sewa'] ?>" title="Verifikasi Pembayaran">
                                              <i class="fa fa-check"></i> Verifikasi
                                          </button>
                                      <?php endif; ?>
                                  <?php else: ?>
                                      <?php if ($r['status'] === 'booking'): ?>
                                          <?php if (empty($r['bukti_pembayaran'])): ?>
                                              <a href="bayar.php?id=<?= urlencode($r['id_sewa']) ?>" class="btn btn-outline-primary d-flex align-items-center gap-1" title="Bayar Sekarang">
                                                  <i class="fa fa-credit-card"></i> Bayar
                                              </a>
                                          <?php else: ?>
                                              <a href="bayar.php?id=<?= urlencode($r['id_sewa']) ?>" class="btn btn-outline-info d-flex align-items-center gap-1" title="Menunggu Verifikasi Admin">
                                                  <i class="fa fa-clock"></i> Status Bayar
                                              </a>
                                          <?php endif; ?>
                                      <?php endif; ?>
                                  <?php endif; ?>

                                   <?php 
                                   $can_user_edit = true;
                                   if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
                                       if (!empty($r['bukti_pembayaran']) || $r['status'] !== 'booking') {
                                           $can_user_edit = false;
                                       }
                                   }
                                   ?>

                                   <?php if ($can_user_edit): ?>
                                       <button type="button" class="btn btn-outline-info d-flex align-items-center gap-1" data-coreui-toggle="modal" data-coreui-target="#editSewaModal-<?= $r['id_sewa'] ?>">
                                           <i class="fa fa-edit"></i> Edit
                                       </button>
                                       <button type="button" class="btn btn-outline-danger d-flex align-items-center gap-1" data-coreui-toggle="modal" data-coreui-target="#deleteSewaModal-<?= $r['id_sewa'] ?>">
                                           <i class="fa fa-trash"></i> Hapus
                                       </button>
                                   <?php else: ?>
                                       <span class="text-body-secondary small ms-2 align-self-center"><i class="fa fa-info-circle me-1"></i>Hubungi Admin untuk perubahan/pembatalan</span>
                                   <?php endif; ?>
                              </div>

                              <!-- Edit Rental Modal -->
                              <div class="modal fade text-start" id="editSewaModal-<?= $r['id_sewa'] ?>" tabindex="-1" aria-labelledby="editSewaModalLabel-<?= $r['id_sewa'] ?>" aria-hidden="true">
                                <div class="modal-dialog modal-lg modal-dialog-centered">
                                  <div class="modal-content border-0 shadow-lg">
                                    <div class="modal-header bg-primary text-white">
                                      <h5 class="modal-title" id="editSewaModalLabel-<?= $r['id_sewa'] ?>"><i class="fa fa-edit me-2"></i>Edit Transaksi Penyewaan</h5>
                                      <button type="button" class="btn-close btn-close-white" data-coreui-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <form method="POST" action="" novalidate>
                                      <div class="modal-body p-4">
                                        <?php if (!empty($error_edit) && $error_edit_id == $r['id_sewa']): ?>
                                            <div class="alert alert-danger border-0 bg-danger bg-opacity-10 text-danger small py-2 px-3 mb-4">
                                                <i class="fa fa-exclamation-triangle me-1"></i> <?= htmlspecialchars($error_edit) ?>
                                            </div>
                                        <?php endif; ?>

                                        <input type="hidden" name="action" value="edit">
                                        <input type="hidden" name="id_sewa" value="<?= htmlspecialchars($r['id_sewa']) ?>">

                                        <div class="row g-3">
                                          <div class="col-md-6">
                                            <label class="form-label" for="edit_id_user-<?= $r['id_sewa'] ?>">Nama Penyewa (User) *</label>
                                            <select id="edit_id_user-<?= $r['id_sewa'] ?>" name="id_user" class="form-select" required>
                                              <option value="" disabled>-- Pilih Penyewa --</option>
                                              <?php foreach ($users as $u): ?>
                                                <option value="<?= $u['id'] ?>" <?= (($error_edit_id == $r['id_sewa'] ? ($_POST['id_user'] ?? $r['id_user']) : $r['id_user']) == $u['id']) ? 'selected' : '' ?>>
                                                  <?= htmlspecialchars($u['nama']) ?>
                                                </option>
                                              <?php endforeach; ?>
                                            </select>
                                          </div>

                                          <div class="col-md-6">
                                            <label class="form-label" for="edit_kode_unik-<?= $r['id_sewa'] ?>">Kendaraan *</label>
                                            <select id="edit_kode_unik-<?= $r['id_sewa'] ?>" name="kode_unik_kendaraan" class="form-select" required>
                                              <option value="" disabled>-- Pilih Kendaraan --</option>
                                              <?php foreach ($vehicles as $k): ?>
                                                <option value="<?= $k['kode_unik_kendaraan'] ?>" data-price="<?= $k['harga_per_hari'] ?>" <?= (($error_edit_id == $r['id_sewa'] ? ($_POST['kode_unik_kendaraan'] ?? $r['kode_unik_kendaraan']) : $r['kode_unik_kendaraan']) == $k['kode_unik_kendaraan']) ? 'selected' : '' ?>>
                                                  <?= htmlspecialchars($k['nama_kendaraan']) ?> (Kode: <?= $k['kode_unik_kendaraan'] ?>) - Rp <?= number_format($k['harga_per_hari'], 0, ',', '.') ?>/hari
                                                </option>
                                              <?php endforeach; ?>
                                            </select>
                                          </div>

                                          <div class="col-md-6">
                                            <label class="form-label" for="edit_tanggal_sewa-<?= $r['id_sewa'] ?>">Tanggal Sewa *</label>
                                            <input type="datetime-local" id="edit_tanggal_sewa-<?= $r['id_sewa'] ?>" name="tanggal_sewa" class="form-control" value="<?= htmlspecialchars(date('Y-m-d\TH:i', strtotime($error_edit_id == $r['id_sewa'] ? ($_POST['tanggal_sewa'] ?? $r['tanggal_sewa']) : $r['tanggal_sewa']))) ?>" required>
                                          </div>

                                          <div class="col-md-6">
                                            <label class="form-label" for="edit_tanggal_kembali-<?= $r['id_sewa'] ?>">Tanggal Kembali *</label>
                                            <input type="datetime-local" id="edit_tanggal_kembali-<?= $r['id_sewa'] ?>" name="tanggal_kembali" class="form-control" value="<?= htmlspecialchars(date('Y-m-d\TH:i', strtotime($error_edit_id == $r['id_sewa'] ? ($_POST['tanggal_kembali'] ?? $r['tanggal_kembali']) : $r['tanggal_kembali']))) ?>" required>
                                          </div>

                                          <div class="col-md-6">
                                            <label class="form-label" for="edit_total_biaya-<?= $r['id_sewa'] ?>">Total Biaya (Rp) *</label>
                                            <div class="input-group">
                                              <span class="input-group-text">Rp</span>
                                              <input type="number" id="edit_total_biaya-<?= $r['id_sewa'] ?>" name="total_biaya" class="form-control" min="0" value="<?= htmlspecialchars($error_edit_id == $r['id_sewa'] ? ($_POST['total_biaya'] ?? $r['total_biaya']) : $r['total_biaya']) ?>" required>
                                            </div>
                                            <div class="form-text text-muted">Akan dihitung otomatis berdasarkan durasi hari & harga sewa kendaraan.</div>
                                          </div>

                                          <div class="col-md-6">
                                            <label class="form-label" for="edit_status-<?= $r['id_sewa'] ?>">Status Penyewaan *</label>
                                            <select id="edit_status-<?= $r['id_sewa'] ?>" name="status" class="form-select" <?= (isset($_SESSION['user_role']) && $_SESSION['user_role'] !== 'admin') ? 'disabled' : '' ?> required>
                                              <?php $curr_status = $error_edit_id == $r['id_sewa'] ? ($_POST['status'] ?? $r['status']) : $r['status']; ?>
                                              <option value="booking" <?= $curr_status === 'booking' ? 'selected' : '' ?>>Booking</option>
                                              <option value="sedang_disewa" <?= $curr_status === 'sedang_disewa' ? 'selected' : '' ?>>Sedang Disewa</option>
                                              <option value="selesai" <?= $curr_status === 'selesai' ? 'selected' : '' ?>>Selesai</option>
                                              <option value="dibatalkan" <?= $curr_status === 'dibatalkan' ? 'selected' : '' ?>>Dibatalkan</option>
                                            </select>
                                          </div>
                                        </div>
                                      </div>
                                      <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-coreui-dismiss="modal">Batal</button>
                                        <button type="submit" class="btn btn-success"><i class="fa fa-save me-1"></i> Simpan Perubahan</button>
                                      </div>
                                    </form>
                                  </div>
                                </div>
                              </div>

                              <script>
                              (function() {
                                const id = "<?= $r['id_sewa'] ?>";
                                const vSel = document.getElementById('edit_kode_unik-' + id);
                                const dSewa = document.getElementById('edit_tanggal_sewa-' + id);
                                const dKembali = document.getElementById('edit_tanggal_kembali-' + id);
                                const tBiaya = document.getElementById('edit_total_biaya-' + id);
                                
                                function calc() {
                                  if (!vSel || vSel.selectedIndex < 0) return;
                                  const opt = vSel.options[vSel.selectedIndex];
                                  if (!opt || opt.value === "") { tBiaya.value = ""; return; }
                                  const price = parseFloat(opt.getAttribute('data-price')) || 0;
                                  const sewaVal = dSewa.value;
                                  const kembaliVal = dKembali.value;
                                  
                                  if (sewaVal && kembaliVal) {
                                    const t1 = new Date(sewaVal);
                                    const t2 = new Date(kembaliVal);
                                    const diffMs = t2 - t1;
                                    
                                    if (diffMs > 0) {
                                      const diffDays = Math.ceil(diffMs / (1000 * 60 * 60 * 24));
                                      tBiaya.value = diffDays * price;
                                    } else {
                                      tBiaya.value = 0;
                                    }
                                  }
                                }
                                
                                if (vSel && dSewa && dKembali && tBiaya) {
                                  vSel.addEventListener('change', calc);
                                  dSewa.addEventListener('change', calc);
                                  dKembali.addEventListener('change', calc);
                                }
                              })();
                              </script>

                              <!-- Verify Payment Modal (Admin Only) -->
                              <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin' && !empty($r['bukti_pembayaran'])): ?>
                              <div class="modal fade text-start" id="verifyPaymentModal-<?= $r['id_sewa'] ?>" tabindex="-1" aria-labelledby="verifyPaymentModalLabel-<?= $r['id_sewa'] ?>" aria-hidden="true">
                                <div class="modal-dialog modal-md modal-dialog-centered">
                                  <div class="modal-content border-0 shadow-lg" style="border-radius: 12px;">
                                    <div class="modal-header bg-success text-white" style="border-top-left-radius: 12px; border-top-right-radius: 12px;">
                                      <h5 class="modal-title" id="verifyPaymentModalLabel-<?= $r['id_sewa'] ?>"><i class="fa fa-check-circle me-2"></i>Verifikasi Pembayaran</h5>
                                      <button type="button" class="btn-close btn-close-white" data-coreui-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <form method="POST" action="">
                                      <input type="hidden" name="action" value="verify_payment">
                                      <input type="hidden" name="id_sewa" value="<?= $r['id_sewa'] ?>">
                                      <div class="modal-body p-4 text-center">
                                        <div class="mb-3 text-start">
                                          <div class="small text-muted fw-bold">Nomor Invoice:</div>
                                          <div class="fw-bold fs-6">#INV-<?= str_pad($r['id_sewa'], 5, '0', STR_PAD_LEFT) ?></div>
                                          <div class="small text-muted mt-2 fw-bold">Penyewa / Kendaraan:</div>
                                          <div class="text-body-emphasis"><?= htmlspecialchars($r['nama_user'] ?? 'N/A') ?> - <?= htmlspecialchars($r['nama_kendaraan'] ?? 'N/A') ?></div>
                                          <div class="small text-muted mt-2 fw-bold">Total Biaya:</div>
                                          <div class="fw-bold text-success">Rp <?= number_format($r['total_biaya'], 0, ',', '.') ?></div>
                                        </div>
                                        <div class="border rounded p-2 bg-body-tertiary mb-3">
                                          <div class="small text-muted mb-2 text-start fw-bold">Bukti Transfer:</div>
                                          <a href="uploads/<?= htmlspecialchars($r['bukti_pembayaran']) ?>" target="_blank" title="Klik untuk memperbesar">
                                            <img src="uploads/<?= htmlspecialchars($r['bukti_pembayaran']) ?>" alt="Bukti Transfer" class="img-fluid rounded border shadow-sm" style="max-height: 250px; object-fit: contain;">
                                          </a>
                                        </div>
                                        <p class="text-muted small">Harap pastikan nominal transfer pada bukti di atas sesuai dengan total biaya penyewaan sebelum menekan tombol verifikasi.</p>
                                      </div>
                                      <div class="modal-footer justify-content-center" style="border-bottom-left-radius: 12px; border-bottom-right-radius: 12px;">
                                        <button type="button" class="btn btn-secondary px-4" data-coreui-dismiss="modal">Batal</button>
                                        <button type="submit" class="btn btn-success text-white px-4 fw-bold">Setujui & Verifikasi</button>
                                      </div>
                                    </form>
                                  </div>
                                </div>
                              </div>
                              <?php endif; ?>

                              <!-- Delete Confirmation Modal -->
                              <div class="modal fade text-start" id="deleteSewaModal-<?= $r['id_sewa'] ?>" tabindex="-1" aria-labelledby="deleteSewaModalLabel-<?= $r['id_sewa'] ?>" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                  <div class="modal-content border-0 shadow-lg">
                                    <div class="modal-header bg-danger text-white">
                                      <h5 class="modal-title" id="deleteSewaModalLabel-<?= $r['id_sewa'] ?>"><i class="fa fa-exclamation-triangle me-2"></i>Konfirmasi Hapus</h5>
                                      <button type="button" class="btn-close btn-close-white" data-coreui-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body p-4 text-center">
                                      <i class="fa fa-trash fa-3x text-danger mb-3"></i>
                                      <h5 class="mb-2">Apakah Anda yakin ingin menghapus transaksi penyewaan ini?</h5>
                                      <p class="text-muted mb-0">Penyewa: <b><?= htmlspecialchars($r['nama_user'] ?? 'N/A') ?></b></p>
                                      <p class="text-muted mb-0">Kendaraan: <b><?= htmlspecialchars($r['nama_kendaraan'] ?? 'N/A') ?></b> (Kode: <?= htmlspecialchars($r['kode_unik_kendaraan']) ?>)</p>
                                      <p class="text-danger small mt-2 mb-0"><i class="fa fa-info-circle"></i> Tindakan ini tidak dapat dibatalkan.</p>
                                    </div>
                                    <div class="modal-footer justify-content-center">
                                      <button type="button" class="btn btn-secondary px-4" data-coreui-dismiss="modal">Batal</button>
                                      <a href="delete_sewa.php?id=<?= urlencode($r['id_sewa']) ?>" class="btn btn-danger px-4">Hapus</a>
                                    </div>
                                  </div>
                                </div>
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
          <!-- Pagination Footer -->
          <div class="card-footer py-3 d-flex flex-column flex-sm-row justify-content-between align-items-center gap-3 bg-body-tertiary border-top border-secondary border-opacity-10">
            <div class="text-muted small">
              Menampilkan <?= $total_items > 0 ? $offset + 1 : 0 ?> sampai <?= min($offset + $limit, $total_items) ?> dari <?= $total_items ?> transaksi
            </div>
            <?php if ($total_pages > 1): ?>
              <nav aria-label="Page navigation">
                <ul class="pagination pagination-sm mb-0">
                  <!-- Previous Page -->
                  <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $page - 1 ?>" aria-label="Previous">
                      <span aria-hidden="true">&laquo;</span>
                    </a>
                  </li>
                  <!-- Page Numbers -->
                  <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                    <li class="page-item <?= ($page == $p) ? 'active' : '' ?>">
                      <a class="page-link" href="?page=<?= $p ?>"><?= $p ?></a>
                    </li>
                  <?php endfor; ?>
                  <!-- Next Page -->
                  <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $page + 1 ?>" aria-label="Next">
                      <span aria-hidden="true">&raquo;</span>
                    </a>
                  </li>
                </ul>
              </nav>
            <?php endif; ?>
          </div>
        </div>

      </div>
    </div>

    <!-- Tambah Penyewaan Modal -->
    <div class="modal fade" id="addSewaModal" tabindex="-1" aria-labelledby="addSewaModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
          <div class="modal-header bg-primary text-white">
            <h5 class="modal-title" id="addSewaModalLabel"><i class="fa fa-plus me-2"></i>Tambah Penyewaan Baru</h5>
            <button type="button" class="btn-close btn-close-white" data-coreui-dismiss="modal" aria-label="Close"></button>
          </div>
          <form method="POST" action="" novalidate id="sewaFormAdd">
            <div class="modal-body p-4">
              <?php if (!empty($error_add)): ?>
                  <div class="alert alert-danger border-0 bg-danger bg-opacity-10 text-danger small py-2 px-3 mb-4">
                      <i class="fa fa-exclamation-triangle me-1"></i> <?= htmlspecialchars($error_add) ?>
                  </div>
              <?php endif; ?>

              <input type="hidden" name="action" value="add">

              <div class="row g-3">                <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                <div class="col-md-6">
                  <label class="form-label" for="add_id_user">Nama Penyewa (User) *</label>
                  <select id="add_id_user" name="id_user" class="form-select" required>
                    <option value="" disabled selected>-- Pilih Penyewa --</option>
                    <?php foreach ($users as $u): ?>
                      <option value="<?= $u['id'] ?>" <?= ($_POST['id_user'] ?? '') == $u['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($u['nama']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="col-md-6">
                  <label class="form-label" for="add_kode_unik">Kendaraan *</label>
                  <select id="add_kode_unik" name="kode_unik_kendaraan" class="form-select" required>
                    <option value="" disabled <?= empty($_POST['kode_unik_kendaraan'] ?? $get_car_code) ? 'selected' : '' ?>>-- Pilih Kendaraan --</option>
                    <?php foreach ($vehicles as $k): ?>
                      <option value="<?= $k['kode_unik_kendaraan'] ?>" data-price="<?= $k['harga_per_hari'] ?>" <?= (($_POST['kode_unik_kendaraan'] ?? $get_car_code) == $k['kode_unik_kendaraan']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($k['nama_kendaraan']) ?> (Kode: <?= $k['kode_unik_kendaraan'] ?>) - Rp <?= number_format($k['harga_per_hari'], 0, ',', '.') ?>/hari
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <?php else: ?>
                <input type="hidden" name="id_user" value="<?= $_SESSION['user_id'] ?>">
                <div class="col-md-12">
                  <label class="form-label" for="add_kode_unik">Kendaraan Pilihan *</label>
                  <?php if (!empty($get_car_code)): ?>
                    <input type="hidden" name="kode_unik_kendaraan" value="<?= htmlspecialchars($get_car_code) ?>">
                    <select id="add_kode_unik" class="form-select" disabled>
                      <?php foreach ($vehicles as $k): ?>
                        <option value="<?= $k['kode_unik_kendaraan'] ?>" data-price="<?= $k['harga_per_hari'] ?>" <?= ($get_car_code == $k['kode_unik_kendaraan']) ? 'selected' : '' ?>>
                          <?= htmlspecialchars($k['nama_kendaraan']) ?> - Rp <?= number_format($k['harga_per_hari'], 0, ',', '.') ?>/hari
                        </option>
                      <?php endforeach; ?>
                    </select>
                  <?php else: ?>
                    <select id="add_kode_unik" name="kode_unik_kendaraan" class="form-select" required>
                      <option value="" disabled selected>-- Pilih Kendaraan --</option>
                      <?php foreach ($vehicles as $k): ?>
                        <option value="<?= $k['kode_unik_kendaraan'] ?>" data-price="<?= $k['harga_per_hari'] ?>" <?= (($_POST['kode_unik_kendaraan'] ?? '') == $k['kode_unik_kendaraan']) ? 'selected' : '' ?>>
                          <?= htmlspecialchars($k['nama_kendaraan']) ?> - Rp <?= number_format($k['harga_per_hari'], 0, ',', '.') ?>/hari
                        </option>
                      <?php endforeach; ?>
                    </select>
                  <?php endif; ?>
                </div>
                <?php endif; ?>

                <div class="col-md-6">
                  <label class="form-label" for="add_tanggal_sewa">Tanggal Sewa *</label>
                  <input type="datetime-local" id="add_tanggal_sewa" name="tanggal_sewa" class="form-control" value="<?= htmlspecialchars($_POST['tanggal_sewa'] ?? $default_sewa) ?>" required>
                </div>

                <div class="col-md-6">
                  <label class="form-label" for="add_tanggal_kembali">Tanggal Kembali *</label>
                  <input type="datetime-local" id="add_tanggal_kembali" name="tanggal_kembali" class="form-control" value="<?= htmlspecialchars($_POST['tanggal_kembali'] ?? $default_kembali) ?>" required>
                </div>

                <div class="col-md-6">
                  <label class="form-label" for="add_total_biaya">Total Biaya (Rp) *</label>
                  <div class="input-group">
                    <span class="input-group-text">Rp</span>
                    <input type="number" id="add_total_biaya" name="total_biaya" class="form-control" placeholder="0" min="0" value="<?= htmlspecialchars($_POST['total_biaya'] ?? '') ?>" required>
                  </div>
                  <div class="form-text text-muted">Akan dihitung otomatis berdasarkan durasi hari & harga sewa kendaraan.</div>
                </div>

                <div class="col-md-6">
                  <label class="form-label" for="add_status">Status Penyewaan *</label>
                  <select id="add_status" name="status" class="form-select" <?= (isset($_SESSION['user_role']) && $_SESSION['user_role'] !== 'admin') ? 'disabled' : '' ?> required>
                    <option value="booking" <?= ($_POST['status'] ?? '') === 'booking' || (isset($_SESSION['user_role']) && $_SESSION['user_role'] !== 'admin') ? 'selected' : '' ?>>Booking</option>
                    <option value="sedang_disewa" <?= ($_POST['status'] ?? '') === 'sedang_disewa' ? 'selected' : '' ?>>Sedang Disewa</option>
                    <option value="selesai" <?= ($_POST['status'] ?? '') === 'selesai' ? 'selected' : '' ?>>Selesai</option>
                    <option value="dibatalkan" <?= ($_POST['status'] ?? '') === 'dibatalkan' ? 'selected' : '' ?>>Dibatalkan</option>
                  </select>
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-coreui-dismiss="modal">Batal</button>
              <button type="submit" class="btn btn-success"><i class="fa fa-save me-1"></i> Simpan</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Success Popup Modal -->
    <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-lg text-center" style="border-radius: 12px;">
          <div class="modal-body p-4">
            <div class="mb-3 text-success">
              <i class="fa fa-check-circle fa-4x animate__animated animate__bounceIn"></i>
            </div>
            <h4 class="modal-title fw-bold mb-2 text-success" id="successModalLabel">Berhasil!</h4>
            <p class="text-body-secondary mb-3" id="successModalMessage">
              <?php
              if ($msg === 'added') {
                  echo 'Transaksi penyewaan berhasil ditambahkan. Silakan cek email Anda untuk detail invoice/tagihan.';
              } elseif ($msg === 'updated') {
                  echo 'Transaksi berhasil diperbarui.';
              } elseif ($msg === 'deleted') {
                  echo 'Transaksi penyewaan berhasil dihapus.';
              } elseif ($msg === 'payment_verified') {
                  echo 'Pembayaran berhasil diverifikasi! Sistem telah mengirimkan email konfirmasi pembayaran.';
              }
              ?>
            </p>
            <button type="button" class="btn btn-success text-white w-100 py-2" data-coreui-dismiss="modal" style="border-radius: 8px;">OK</button>
          </div>
        </div>
      </div>
    </div>

    <?php include 'partials/footer.php'; ?>

    <script>
      // Add calculation of rental fees for Add modal
      (function() {
        const vehicleSelect = document.getElementById('add_kode_unik');
        const dateSewa = document.getElementById('add_tanggal_sewa');
        const dateKembali = document.getElementById('add_tanggal_kembali');
        const totalBiayaInput = document.getElementById('add_total_biaya');

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
              const diffDays = Math.ceil(diffMs / (1000 * 60 * 60 * 24));
              totalBiayaInput.value = diffDays * pricePerDay;
            } else {
              totalBiayaInput.value = 0;
            }
          }
        }

        if (vehicleSelect && dateSewa && dateKembali && totalBiayaInput) {
          vehicleSelect.addEventListener('change', calculateTotal);
          dateSewa.addEventListener('change', calculateTotal);
          dateKembali.addEventListener('change', calculateTotal);
          // Trigger initial calculation if prefilled on page load
          calculateTotal();
        }
      })();

      // Modal management on DOM load
      document.addEventListener('DOMContentLoaded', function() {
        // 1. Success Modal Trigger
        <?php if (!empty($msg)): ?>
        const successModal = new coreui.Modal(document.getElementById('successModal'));
        successModal.show();
        setTimeout(() => {
          successModal.hide();
        }, 3000);
        <?php endif; ?>

        // 2. Open Add Modal on validation error or if redirected with booking parameters
        <?php if (!empty($error_add) || !empty($get_car_code)): ?>
        const addModal = new coreui.Modal(document.getElementById('addSewaModal'));
        addModal.show();
        <?php endif; ?>

        // 3. Open Edit Modal on validation error
        <?php if (!empty($error_edit) && !empty($error_edit_id)): ?>
        const editModal = new coreui.Modal(document.getElementById('editSewaModal-<?= $error_edit_id ?>'));
        editModal.show();
        <?php endif; ?>
      });
    </script>
</body>
</html>
