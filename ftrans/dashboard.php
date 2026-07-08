<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$nama_user = htmlspecialchars($_SESSION['user_nama']);
$error_add = '';
$error_edit = '';
$error_edit_kode = '';
$error_booking = '';

$merk_options = [];
$res_merk = mysqli_query($mysqli, "SELECT id_merk, nama_merk FROM merk_kendaraan ORDER BY nama_merk ASC");
if ($res_merk) {
    $merk_options = mysqli_fetch_all($res_merk, MYSQLI_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_booking') {
        $id_user = $_SESSION['user_id'];
        $status = 'booking';
        $kode_unik = $_POST['kode_unik_kendaraan'] ?? '';
        $tgl_sewa = $_POST['tanggal_sewa'] ?? '';
        $tgl_kembali = $_POST['tanggal_kembali'] ?? '';

        // Fetch vehicle details
        $v_res = mysqli_query($mysqli, "SELECT harga_per_hari, nama_kendaraan FROM kendaraan WHERE kode_unik_kendaraan = " . intval($kode_unik));
        $v_data = mysqli_fetch_assoc($v_res);
        $harga_per_hari = $v_data['harga_per_hari'] ?? 0;
        $veh_name = $v_data['nama_kendaraan'] ?? 'Kendaraan';

        $durasi_hari = ceil((strtotime($tgl_kembali) - strtotime($tgl_sewa)) / 86400);
        if ($durasi_hari <= 0) $durasi_hari = 1;
        $total_biaya = $durasi_hari * $harga_per_hari;

        if (empty($kode_unik) || empty($tgl_sewa) || empty($tgl_kembali)) {
            $error_booking = 'Semua field wajib diisi.';
        } elseif (strtotime($tgl_kembali) < strtotime($tgl_sewa)) {
            $error_booking = 'Tanggal kembali tidak boleh mendahului tanggal sewa.';
        } else {
            $stmt = mysqli_prepare($mysqli, "INSERT INTO penyewaan (id_user, kode_unik_kendaraan, tanggal_sewa, tanggal_kembali, total_biaya, status) VALUES (?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, 'iissis', $id_user, $kode_unik, $tgl_sewa, $tgl_kembali, $total_biaya, $status);

            if (mysqli_stmt_execute($stmt)) {
                $id_sewa = mysqli_insert_id($mysqli);

                // Update vehicle status to 'disewa'
                mysqli_query($mysqli, "UPDATE kendaraan SET status_kendaraan = 'disewa' WHERE kode_unik_kendaraan = " . intval($kode_unik));

                // Add notifications
                add_notification($id_user, "Pemesanan Kendaraan Berhasil", "Pemesanan kendaraan {$veh_name} Anda berhasil dibuat. Silakan selesaikan pembayaran.");
                
                $user_name = $_SESSION['user_nama'] ?? 'User';
                add_notification(null, "Pemesanan Baru Masuk", "Penyewa {$user_name} baru saja melakukan pemesanan kendaraan {$veh_name}.");

                require_once 'send_invoice.php';
                try {
                    send_invoice_email($id_sewa, 'invoice');
                } catch (\Exception $e) {
                    error_log("Failed to send SMTP invoice email: " . $e->getMessage());
                }

                header('Location: bayar.php?id=' . $id_sewa . '&msg=booking_success');
                exit;
            } else {
                $error_booking = 'Gagal menyimpan transaksi. Coba lagi.';
            }
            mysqli_stmt_close($stmt);
        }
    } else {
        // Restrict modifications to Admin role
        if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
            header('Location: dashboard.php?error=unauthorized');
            exit;
        }
    }

    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $kode  = trim($_POST['kode_unik_kendaraan'] ?? '');
        $id_merk = intval($_POST['id_merk']          ?? 0);
        $model = trim($_POST['model_kendaraan']     ?? '');
        $jenis = trim($_POST['jenis_kendaraan']     ?? '');
        $harga = $_POST['harga_per_hari']           ?? '';
        $transmisi = trim($_POST['transmisi']       ?? 'Matic');
        $tempat_duduk = trim($_POST['tempat_duduk'] ?? '5 Seater');
        $bahan_bakar = trim($_POST['bahan_bakar']   ?? 'Bensin');
        $status_kendaraan = trim($_POST['status_kendaraan'] ?? 'tersedia');
        $stok = intval($_POST['stok']               ?? 1);
        $warna = trim($_POST['warna']               ?? 'Hitam');

        $brand = '';
        foreach ($merk_options as $mo) {
            if ($mo['id_merk'] == $id_merk) {
                $brand = $mo['nama_merk'];
                break;
            }
        }
        $nama = trim(ucwords($brand) . ' ' . $model);

        if (empty($kode) || empty($id_merk) || empty($model) || empty($jenis) || $harga === '') {
            $error_add = 'Semua field wajib diisi.';
        } elseif (!is_numeric($harga) || $harga < 0) {
            $error_add = 'Harga per hari harus berupa angka positif.';
        } else {
            $cek = mysqli_prepare($mysqli, "SELECT kode_unik_kendaraan FROM kendaraan WHERE kode_unik_kendaraan = ?");
            mysqli_stmt_bind_param($cek, 's', $kode);
            mysqli_stmt_execute($cek);
            mysqli_stmt_store_result($cek);

            if (mysqli_stmt_num_rows($cek) > 0) {
                $error_add = 'Kode kendaraan sudah digunakan. Gunakan kode lain.';
            } else {
                // Proses Upload Gambar
                $gambar_nama = null;
                if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] === UPLOAD_ERR_OK) {
                    $tmp = $_FILES['gambar']['tmp_name'];
                    $ext = strtolower(pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION));
                    $sanitized_model = strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $model));
                    $gambar_nama = $sanitized_model . '.' . $ext;
                    move_uploaded_file($tmp, 'uploads/' . $gambar_nama);
                }

                $stmt = mysqli_prepare($mysqli, "INSERT INTO kendaraan (kode_unik_kendaraan, id_merk, nama_kendaraan, jenis_kendaraan, harga_per_hari, gambar, transmisi, tempat_duduk, bahan_bakar, status_kendaraan, stok, warna) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt, 'sissssssssis', $kode, $id_merk, $nama, $jenis, $harga, $gambar_nama, $transmisi, $tempat_duduk, $bahan_bakar, $status_kendaraan, $stok, $warna);

                if (mysqli_stmt_execute($stmt)) {
                    header('Location: dashboard.php?msg=added');
                    exit;
                } else {
                    $error_add = 'Gagal menyimpan data. Coba lagi.';
                }
                mysqli_stmt_close($stmt);
            }
            mysqli_stmt_close($cek);
        }
    } elseif ($action === 'edit') {
        $kode  = trim($_POST['kode_unik_kendaraan'] ?? '');
        $id_merk_baru  = intval($_POST['id_merk']  ?? 0);
        $model_baru = trim($_POST['model_kendaraan'] ?? '');
        $jenis_baru = trim($_POST['jenis_kendaraan'] ?? '');
        $harga_baru = $_POST['harga_per_hari']       ?? '';
        $transmisi_baru = trim($_POST['transmisi']   ?? 'Matic');
        $tempat_duduk_baru = trim($_POST['tempat_duduk'] ?? '5 Seater');
        $bahan_bakar_baru = trim($_POST['bahan_bakar'] ?? 'Bensin');
        $status_kendaraan_baru = trim($_POST['status_kendaraan'] ?? 'tersedia');
        $stok_baru = intval($_POST['stok']           ?? 1);
        $warna_baru = trim($_POST['warna']           ?? 'Hitam');
        $error_edit_kode = $kode;

        $brand_baru = '';
        foreach ($merk_options as $mo) {
            if ($mo['id_merk'] == $id_merk_baru) {
                $brand_baru = $mo['nama_merk'];
                break;
            }
        }
        $nama_baru = trim(ucwords($brand_baru) . ' ' . $model_baru);

        if (empty($kode)) {
            $error_edit = 'Kode unik kendaraan tidak ditemukan.';
        } elseif (empty($id_merk_baru) || empty($model_baru) || empty($jenis_baru) || $harga_baru === '') {
            $error_edit = 'Semua field wajib diisi.';
        } elseif (!is_numeric($harga_baru) || $harga_baru < 0) {
            $error_edit = 'Harga per hari harus berupa angka positif.';
        } else {
            $stmt = mysqli_prepare($mysqli, "SELECT gambar FROM kendaraan WHERE kode_unik_kendaraan = ?");
            mysqli_stmt_bind_param($stmt, 's', $kode);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $kendaraan_edit = mysqli_fetch_assoc($res);
            mysqli_stmt_close($stmt);

            if ($kendaraan_edit) {
                $gambar_baru = $kendaraan_edit['gambar'];
                if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] === UPLOAD_ERR_OK) {
                    $tmp = $_FILES['gambar']['tmp_name'];
                    $ext = strtolower(pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION));
                    $sanitized_model = strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $model_baru));
                    $nama_file_baru = $sanitized_model . '.' . $ext;
                    
                    if (move_uploaded_file($tmp, 'uploads/' . $nama_file_baru)) {
                        if (!empty($kendaraan_edit['gambar']) && file_exists('uploads/' . $kendaraan_edit['gambar']) && $kendaraan_edit['gambar'] !== $nama_file_baru) {
                            unlink('uploads/' . $kendaraan_edit['gambar']);
                        }
                        $gambar_baru = $nama_file_baru;
                    }
                }

                $upd = mysqli_prepare($mysqli, "UPDATE kendaraan SET id_merk = ?, nama_kendaraan = ?, jenis_kendaraan = ?, harga_per_hari = ?, gambar = ?, transmisi = ?, tempat_duduk = ?, bahan_bakar = ?, status_kendaraan = ?, stok = ?, warna = ? WHERE kode_unik_kendaraan = ?");
                mysqli_stmt_bind_param($upd, 'issssssssiss', $id_merk_baru, $nama_baru, $jenis_baru, $harga_baru, $gambar_baru, $transmisi_baru, $tempat_duduk_baru, $bahan_bakar_baru, $status_kendaraan_baru, $stok_baru, $warna_baru, $kode);

                if (mysqli_stmt_execute($upd)) {
                    header('Location: dashboard.php?msg=updated');
                    exit;
                } else {
                    $error_edit = 'Gagal memperbarui data. Coba lagi.';
                }
                mysqli_stmt_close($upd);
            } else {
                $error_edit = 'Data kendaraan tidak ditemukan.';
            }
        }
    }
}

// Pagination settings
$limit = 12;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) {
    $page = 1;
}
$offset = ($page - 1) * $limit;

// Menangkap parameter search dan filter dari URL
$search = trim($_GET['search'] ?? '');
$f_jenis = trim($_GET['jenis'] ?? '');
$f_status = trim($_GET['status'] ?? '');
$f_merk = trim($_GET['merk'] ?? '');

$export_qs = "";
if (!empty($search)) $export_qs .= '&search=' . urlencode($search);
if (!empty($f_jenis)) $export_qs .= '&jenis=' . urlencode($f_jenis);
if (!empty($f_status)) $export_qs .= '&status=' . urlencode($f_status);
if (!empty($f_merk)) $export_qs .= '&merk=' . urlencode($f_merk);

$where_clauses = [];
$params = [];
$types = "";

if (!empty($search)) {
    $where_clauses[] = "(kode_unik_kendaraan LIKE ? OR nama_kendaraan LIKE ? OR jenis_kendaraan LIKE ?)";
    $search_param = "%" . $search . "%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

if (!empty($f_jenis)) {
    $where_clauses[] = "jenis_kendaraan = ?";
    $params[] = $f_jenis;
    $types .= "s";
}

if (!empty($f_status)) {
    $where_clauses[] = "status_kendaraan = ?";
    $params[] = $f_status;
    $types .= "s";
}

if (!empty($f_merk)) {
    $where_clauses[] = "id_merk = ?";
    $params[] = intval($f_merk);
    $types .= "i";
}

$where_sql = "";
if (count($where_clauses) > 0) {
    $where_sql = "WHERE " . implode(" AND ", $where_clauses);
}

// Count total matching items
if (count($params) > 0) {
    $count_stmt = mysqli_prepare($mysqli, "SELECT COUNT(*) FROM kendaraan $where_sql");
    mysqli_stmt_bind_param($count_stmt, $types, ...$params);
    mysqli_stmt_execute($count_stmt);
    mysqli_stmt_bind_result($count_stmt, $total_items);
    mysqli_stmt_fetch($count_stmt);
    mysqli_stmt_close($count_stmt);
} else {
    $count_res = mysqli_query($mysqli, "SELECT COUNT(*) FROM kendaraan");
    $count_row = mysqli_fetch_row($count_res);
    $total_items = $count_row[0];
}

$total_pages = ceil($total_items / $limit);
if ($page > $total_pages && $total_pages > 0) {
    $page = $total_pages;
    $offset = ($page - 1) * $limit;
}

// Fetch matching items
$query = "SELECT * FROM kendaraan $where_sql ORDER BY CASE WHEN status_kendaraan = 'tersedia' THEN 1 WHEN status_kendaraan = 'perawatan' THEN 2 WHEN status_kendaraan = 'disewa' THEN 3 ELSE 4 END ASC, kode_unik_kendaraan ASC LIMIT ? OFFSET ?";
$stmt = mysqli_prepare($mysqli, $query);

$bind_params = array_merge($params, [$limit, $offset]);
$bind_types = $types . "ii";

mysqli_stmt_bind_param($stmt, $bind_types, ...$bind_params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$kendaraan = mysqli_fetch_all($result, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

$total = count($kendaraan);
$msg = $_GET['msg'] ?? '';
$error_booking = '';
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

        <?php if (isset($_SESSION['import_error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show border-0 bg-danger bg-opacity-10 text-danger p-3 mb-4" role="alert">
                <i class="fa fa-exclamation-triangle me-2"></i> <?= htmlspecialchars($_SESSION['import_error']) ?>
                <button type="button" class="btn-close" data-coreui-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['import_error']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['import_result'])): ?>
            <?php 
            $res = $_SESSION['import_result']; 
            $inserted = $res['inserted'] ?? 0;
            $updated = $res['updated'] ?? 0;
            $failed = $res['failed'] ?? 0;
            $errors = $res['errors'] ?? [];
            unset($_SESSION['import_result']);
            ?>
            <div class="alert alert-info alert-dismissible fade show border-0 bg-info bg-opacity-10 text-info p-3 mb-4" role="alert">
                <h6 class="alert-heading fw-bold mb-2"><i class="fa fa-info-circle me-1"></i> Hasil Impor Data Kendaraan:</h6>
                <p class="mb-1 text-dark small">
                    <span class="badge bg-success me-1"><?= $inserted ?> baru</span>
                    <span class="badge bg-primary me-1"><?= $updated ?> diperbarui</span>
                    <span class="badge bg-danger"><?= $failed ?> gagal</span>
                </p>
                <?php if ($failed > 0 && !empty($errors)): ?>
                    <hr class="my-2 border-info border-opacity-25">
                    <div class="text-danger small" style="max-height: 150px; overflow-y: auto;">
                        <ul class="mb-0 ps-3">
                            <?php foreach (array_slice($errors, 0, 10) as $err): ?>
                                <li><?= htmlspecialchars($err) ?></li>
                            <?php endforeach; ?>
                            <?php if (count($errors) > 10): ?>
                                <li>... dan <?= count($errors) - 10 ?> baris kesalahan lainnya.</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <button type="button" class="btn-close" data-coreui-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card mb-4 shadow-sm border border-secondary border-opacity-10">
          <div class="card-header d-flex flex-column flex-md-row align-items-center justify-content-between gap-3 bg-body-tertiary">
            <h5 class="mb-0 text-body"><i class="fa fa-list me-2 text-primary"></i>Daftar Kendaraan</h5>
            
            <div class="d-flex flex-column flex-sm-row gap-2 w-100 w-md-auto justify-content-end align-items-sm-center">
              <div class="dropdown">
                <button class="btn btn-success btn-sm dropdown-toggle text-nowrap" type="button" data-coreui-toggle="dropdown" aria-expanded="false">
                  <i class="fa fa-download me-1"></i> Export
                </button>
                <ul class="dropdown-menu">
                  <li><a class="dropdown-item" href="export.php?target=kendaraan&format=excel<?= $export_qs ?>" target="_blank"><i class="fa fa-file-excel text-success me-2"></i> Excel (.xlsx)</a></li>
                  <li><a class="dropdown-item" href="export.php?target=kendaraan&format=word<?= $export_qs ?>" target="_blank"><i class="fa fa-file-word text-primary me-2"></i> Word (.docx)</a></li>
                  <li><a class="dropdown-item" href="export.php?target=kendaraan&format=pdf<?= $export_qs ?>" target="_blank"><i class="fa fa-file-pdf text-danger me-2"></i> PDF (.pdf)</a></li>
                </ul>
              </div>
              <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                <button type="button" class="btn btn-warning btn-sm text-nowrap mt-2 mt-sm-0 text-white" data-coreui-toggle="modal" data-coreui-target="#importKendaraanModal">
                  <i class="fa fa-upload me-1"></i> Import
                </button>
                <button type="button" class="btn btn-primary btn-sm text-nowrap mt-2 mt-sm-0" data-coreui-toggle="modal" data-coreui-target="#addKendaraanModal">
                  <i class="fa fa-plus me-1"></i> Tambah
                </button>
              <?php endif; ?>
            </div>
          </div>
          <!-- Filter Row -->
          <div class="p-3 bg-body-tertiary border-bottom">
            <form action="dashboard.php" method="GET" class="row g-2 align-items-center">
              <div class="col-md-3 col-sm-6">
                <div class="input-group input-group-sm">
                  <span class="input-group-text"><i class="fa fa-search"></i></span>
                  <input type="text" name="search" class="form-control" placeholder="Cari nama, kode..." value="<?= htmlspecialchars($search) ?>">
                </div>
              </div>
              <div class="col-md-2 col-sm-6">
                <select name="jenis" class="form-select form-select-sm">
                  <option value="">-- Semua Jenis --</option>
                  <option value="Roda 2" <?= $f_jenis === 'Roda 2' ? 'selected' : '' ?>>Roda 2 (Motor)</option>
                  <option value="Roda 4" <?= $f_jenis === 'Roda 4' ? 'selected' : '' ?>>Roda 4 (Mobil)</option>
                </select>
              </div>
              <div class="col-md-2 col-sm-6">
                <select name="status" class="form-select form-select-sm">
                  <option value="">-- Semua Status --</option>
                  <option value="tersedia" <?= $f_status === 'tersedia' ? 'selected' : '' ?>>Tersedia</option>
                  <option value="disewa" <?= $f_status === 'disewa' ? 'selected' : '' ?>>Sedang Disewa</option>
                  <option value="perawatan" <?= $f_status === 'perawatan' ? 'selected' : '' ?>>Dalam Perawatan</option>
                </select>
              </div>
              <div class="col-md-3 col-sm-6">
                <select name="merk" class="form-select form-select-sm">
                  <option value="">-- Semua Merk --</option>
                  <?php foreach ($merk_options as $mo): ?>
                    <option value="<?= $mo['id_merk'] ?>" <?= $f_merk == $mo['id_merk'] ? 'selected' : '' ?>><?= htmlspecialchars(ucwords($mo['nama_merk'])) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-2 col-sm-12 d-flex gap-1 justify-content-end">
                <button type="submit" class="btn btn-primary btn-sm px-3 w-100"><i class="fa fa-filter me-1"></i>Filter</button>
                <?php if(!empty($search) || !empty($f_jenis) || !empty($f_status) || !empty($f_merk)): ?>
                    <a href="dashboard.php" class="btn btn-danger btn-sm text-white px-2.5" title="Reset Filter"><i class="fa fa-sync"></i></a>
                <?php endif; ?>
              </div>
            </form>
          </div>
          <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
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
                    <th scope="col">Status</th>
                    <th scope="col">Harga / Hari</th>
                    <th scope="col" class="pe-4 text-end">Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if ($total > 0): ?>
                      <?php foreach ($kendaraan as $i => $k): ?>
                      <tr>
                          <td class="ps-4 fw-semibold text-body-secondary"><?= $offset + $i + 1 ?></td>
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
                          <td class="text-body fw-bold">
                              <?= htmlspecialchars($k['nama_kendaraan']) ?>
                              <div class="text-muted small mt-1" style="font-size: 0.75rem; font-weight: normal;">
                                  <span><i class="fa fa-cubes me-1"></i>Stok: <strong><?= intval($k['stok'] ?? 1) ?></strong></span> | 
                                  <span><i class="fa fa-palette me-1"></i>Warna: <strong><?= htmlspecialchars($k['warna'] ?? 'Hitam') ?></strong></span>
                              </div>
                          </td>
                          <td>
                              <span class="badge bg-info bg-opacity-10 text-info px-2 py-1">
                                    <?= (strtolower($k['jenis_kendaraan']) === 'roda 2') ? 'Roda 2' : 'Roda 4' ?>
                              </span>
                          </td>
                          <td>
                              <?php
                              $status = strtolower(trim($k['status_kendaraan'] ?? 'tersedia'));
                              if ($status === 'disewa'):
                                  echo '<span class="badge bg-warning text-dark px-2 py-1"><i class="fa fa-key me-1"></i>Sedang Disewa</span>';
                              elseif ($status === 'perawatan'):
                                  echo '<span class="badge bg-danger text-white px-2 py-1"><i class="fa fa-tools me-1"></i>Dalam Perawatan</span>';
                              else:
                                  echo '<span class="badge bg-success text-white px-2 py-1"><i class="fa fa-check me-1"></i>Tersedia</span>';
                              endif;
                              ?>
                          </td>
                          <td class="text-body fw-semibold">Rp <?= number_format($k['harga_per_hari'], 0, ',', '.') ?></td>
                          <td class="pe-4 text-end">
                              <div class="btn-group btn-group-sm" role="group">
                                  <a href="export.php?target=kendaraan&format=pdf&kode=<?= urlencode($k['kode_unik_kendaraan']) ?>" class="btn btn-outline-secondary d-flex align-items-center gap-1" title="Cetak PDF" target="_blank">
                                      <i class="fa fa-print"></i> Cetak
                                  </a>
                                  <button type="button" class="btn btn-outline-info d-flex align-items-center gap-1" data-coreui-toggle="modal" data-coreui-target="#editModal-<?= $k['kode_unik_kendaraan'] ?>">
                                      <i class="fa fa-edit"></i> Edit
                                  </button>
                                  <button type="button" class="btn btn-outline-danger d-flex align-items-center gap-1" data-coreui-toggle="modal" data-coreui-target="#deleteModal-<?= $k['kode_unik_kendaraan'] ?>">
                                      <i class="fa fa-trash"></i> Hapus
                                  </button>
                              </div>

                              <!-- Edit Modal -->
                              <div class="modal fade text-start" id="editModal-<?= $k['kode_unik_kendaraan'] ?>" tabindex="-1" aria-labelledby="editModalLabel-<?= $k['kode_unik_kendaraan'] ?>" aria-hidden="true">
                                <div class="modal-dialog modal-lg modal-dialog-centered">
                                  <div class="modal-content border-0 shadow-lg">
                                    <div class="modal-header bg-primary text-white">
                                      <h5 class="modal-title" id="editModalLabel-<?= $k['kode_unik_kendaraan'] ?>"><i class="fa fa-edit me-2"></i>Edit Data Kendaraan</h5>
                                      <button type="button" class="btn-close btn-close-white" data-coreui-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <form method="POST" action="" enctype="multipart/form-data" novalidate>
                                      <div class="modal-body p-4">
                                        <input type="hidden" name="action" value="edit">
                                        <input type="hidden" name="kode_unik_kendaraan" value="<?= htmlspecialchars($k['kode_unik_kendaraan']) ?>">

                                        <div class="row g-3">
                                          <div class="col-md-6">
                                            <label class="form-label" for="edit_kode_unik_<?= $k['kode_unik_kendaraan'] ?>">Kode Unik Kendaraan</label>
                                            <input type="text" id="edit_kode_unik_<?= $k['kode_unik_kendaraan'] ?>" class="form-control text-warning bg-dark border border-secondary" value="<?= htmlspecialchars($k['kode_unik_kendaraan']) ?>" readonly disabled>
                                          </div>
                                          <div class="col-md-6">
                                            <label class="form-label" for="edit_id_merk_<?= $k['kode_unik_kendaraan'] ?>">Merk Kendaraan *</label>
                                            <select id="edit_id_merk_<?= $k['kode_unik_kendaraan'] ?>" name="id_merk" class="form-select select2-brand-edit" data-modal-id="editModal-<?= $k['kode_unik_kendaraan'] ?>" required>
                                              <option value="" disabled>-- Pilih Merk Kendaraan --</option>
                                              <?php foreach ($merk_options as $option): ?>
                                                <option value="<?= $option['id_merk'] ?>" <?= (($error_edit_kode == $k['kode_unik_kendaraan'] ? ($_POST['id_merk'] ?? $k['id_merk']) : $k['id_merk']) == $option['id_merk']) ? 'selected' : '' ?>><?= htmlspecialchars(ucwords($option['nama_merk'])) ?></option>
                                              <?php endforeach; ?>
                                            </select>
                                          </div>
                                          <div class="col-md-6">
                                            <label class="form-label" for="edit_model_<?= $k['kode_unik_kendaraan'] ?>">Model Kendaraan *</label>
                                            <input type="text" id="edit_model_<?= $k['kode_unik_kendaraan'] ?>" name="model_kendaraan" class="form-control" value="<?= htmlspecialchars($error_edit_kode == $k['kode_unik_kendaraan'] ? ($_POST['model_kendaraan'] ?? '') : substr($k['nama_kendaraan'], strpos($k['nama_kendaraan'], ' ') + 1)) ?>" required>
                                          </div>
                                          <div class="col-md-6">
                                            <label class="form-label" for="edit_jenis_<?= $k['kode_unik_kendaraan'] ?>">Jenis Kendaraan *</label>
                                            <select id="edit_jenis_<?= $k['kode_unik_kendaraan'] ?>" name="jenis_kendaraan" class="form-select" required>
                                              <option value="Roda 2" <?= (($error_edit_kode == $k['kode_unik_kendaraan'] ? ($_POST['jenis_kendaraan'] ?? $k['jenis_kendaraan']) : $k['jenis_kendaraan']) === 'Roda 2') ? 'selected' : '' ?>>Roda 2 (Motor)</option>
                                              <option value="Roda 4" <?= (($error_edit_kode == $k['kode_unik_kendaraan'] ? ($_POST['jenis_kendaraan'] ?? $k['jenis_kendaraan']) : $k['jenis_kendaraan']) === 'Roda 4') ? 'selected' : '' ?>>Roda 4 (Mobil)</option>
                                            </select>
                                          </div>
                                          <div class="col-md-6">
                                            <label class="form-label" for="edit_harga_<?= $k['kode_unik_kendaraan'] ?>">Harga per Hari (Rp) *</label>
                                            <input type="number" id="edit_harga_<?= $k['kode_unik_kendaraan'] ?>" name="harga_per_hari" class="form-control" min="0" value="<?= htmlspecialchars($error_edit_kode == $k['kode_unik_kendaraan'] ? ($_POST['harga_per_hari'] ?? $k['harga_per_hari']) : $k['harga_per_hari']) ?>" required>
                                          </div>
                                          <div class="col-md-4">
                                            <label class="form-label" for="edit_transmisi_<?= $k['kode_unik_kendaraan'] ?>">Transmisi *</label>
                                            <select id="edit_transmisi_<?= $k['kode_unik_kendaraan'] ?>" name="transmisi" class="form-select" required>
                                              <option value="Matic" <?= (($error_edit_kode == $k['kode_unik_kendaraan'] ? ($_POST['transmisi'] ?? $k['transmisi']) : $k['transmisi']) === 'Matic') ? 'selected' : '' ?>>Matic</option>
                                              <option value="Manual" <?= (($error_edit_kode == $k['kode_unik_kendaraan'] ? ($_POST['transmisi'] ?? $k['transmisi']) : $k['transmisi']) === 'Manual') ? 'selected' : '' ?>>Manual</option>
                                            </select>
                                          </div>
                                          <div class="col-md-4">
                                            <label class="form-label" for="edit_tempat_duduk_<?= $k['kode_unik_kendaraan'] ?>">Jumlah Seat *</label>
                                            <input type="text" id="edit_tempat_duduk_<?= $k['kode_unik_kendaraan'] ?>" name="tempat_duduk" class="form-control" value="<?= htmlspecialchars($error_edit_kode == $k['kode_unik_kendaraan'] ? ($_POST['tempat_duduk'] ?? $k['tempat_duduk']) : ($k['tempat_duduk'] ?? '5 Seater')) ?>" required>
                                          </div>
                                          <div class="col-md-4">
                                            <label class="form-label" for="edit_bahan_bakar_<?= $k['kode_unik_kendaraan'] ?>">Bahan Bakar *</label>
                                            <input type="text" id="edit_bahan_bakar_<?= $k['kode_unik_kendaraan'] ?>" name="bahan_bakar" class="form-control" value="<?= htmlspecialchars($error_edit_kode == $k['kode_unik_kendaraan'] ? ($_POST['bahan_bakar'] ?? $k['bahan_bakar']) : ($k['bahan_bakar'] ?? 'Bensin')) ?>" required>
                                          </div>
                                          <div class="col-md-4">
                                            <label class="form-label" for="edit_status_<?= $k['kode_unik_kendaraan'] ?>">Status Ketersediaan *</label>
                                            <select id="edit_status_<?= $k['kode_unik_kendaraan'] ?>" name="status_kendaraan" class="form-select" required>
                                              <option value="tersedia" <?= (($error_edit_kode == $k['kode_unik_kendaraan'] ? ($_POST['status_kendaraan'] ?? $k['status_kendaraan']) : $k['status_kendaraan']) === 'tersedia') ? 'selected' : '' ?>>Tersedia</option>
                                              <option value="disewa" <?= (($error_edit_kode == $k['kode_unik_kendaraan'] ? ($_POST['status_kendaraan'] ?? $k['status_kendaraan']) : $k['status_kendaraan']) === 'disewa') ? 'selected' : '' ?>>Sedang Disewa</option>
                                              <option value="perawatan" <?= (($error_edit_kode == $k['kode_unik_kendaraan'] ? ($_POST['status_kendaraan'] ?? $k['status_kendaraan']) : $k['status_kendaraan']) === 'perawatan') ? 'selected' : '' ?>>Dalam Perawatan</option>
                                            </select>
                                          </div>
                                          <div class="col-md-4">
                                            <label class="form-label" for="edit_stok_<?= $k['kode_unik_kendaraan'] ?>">Jumlah Stok *</label>
                                            <input type="number" id="edit_stok_<?= $k['kode_unik_kendaraan'] ?>" name="stok" class="form-control" min="0" value="<?= htmlspecialchars($error_edit_kode == $k['kode_unik_kendaraan'] ? ($_POST['stok'] ?? $k['stok']) : ($k['stok'] ?? 1)) ?>" required>
                                          </div>
                                          <div class="col-md-4">
                                            <label class="form-label" for="edit_warna_<?= $k['kode_unik_kendaraan'] ?>">Warna Tersedia *</label>
                                            <input type="text" id="edit_warna_<?= $k['kode_unik_kendaraan'] ?>" name="warna" class="form-control" value="<?= htmlspecialchars($error_edit_kode == $k['kode_unik_kendaraan'] ? ($_POST['warna'] ?? $k['warna']) : ($k['warna'] ?? 'Hitam')) ?>" required>
                                          </div>
                                          <div class="col-12">
                                            <label class="form-label" for="edit_gambar_<?= $k['kode_unik_kendaraan'] ?>">Ganti Gambar Kendaraan (Opsional)</label>
                                            <input type="file" id="edit_gambar_<?= $k['kode_unik_kendaraan'] ?>" name="gambar" class="form-control" accept="image/*">
                                            <?php if (!empty($k['gambar'])): ?>
                                                <div class="form-text">Gambar saat ini: <code><?= htmlspecialchars($k['gambar']) ?></code></div>
                                            <?php endif; ?>
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

                              <!-- Delete Modal -->
                              <div class="modal fade text-start" id="deleteModal-<?= $k['kode_unik_kendaraan'] ?>" tabindex="-1" aria-labelledby="deleteModalLabel-<?= $k['kode_unik_kendaraan'] ?>" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                  <div class="modal-content border-0 shadow-lg">
                                    <div class="modal-header bg-danger text-white">
                                      <h5 class="modal-title" id="deleteModalLabel-<?= $k['kode_unik_kendaraan'] ?>"><i class="fa fa-exclamation-triangle me-2"></i>Konfirmasi Hapus</h5>
                                      <button type="button" class="btn-close btn-close-white" data-coreui-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body p-4 text-center">
                                      <i class="fa fa-trash fa-3x text-danger mb-3"></i>
                                      <h5 class="mb-2">Apakah Anda yakin ingin menghapus data kendaraan ini?</h5>
                                      <h6 class="text-body fw-bold mb-0"><?= htmlspecialchars($k['nama_kendaraan']) ?> (Kode: <?= htmlspecialchars($k['kode_unik_kendaraan']) ?>)</h6>
                                      <p class="text-danger small mt-2 mb-0"><i class="fa fa-info-circle"></i> Seluruh riwayat transaksi sewa kendaraan ini juga akan dihapus.</p>
                                    </div>
                                    <div class="modal-footer justify-content-center">
                                      <button type="button" class="btn btn-secondary px-4" data-coreui-dismiss="modal">Batal</button>
                                      <a href="delete.php?kode=<?= urlencode($k['kode_unik_kendaraan']) ?>" class="btn btn-danger px-4">Hapus</a>
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
                              Belum ada data kendaraan.
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
              Menampilkan <?= $total_items > 0 ? $offset + 1 : 0 ?> sampai <?= min($offset + $limit, $total_items) ?> dari <?= $total_items ?> kendaraan
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
          <?php else: ?>
          <!-- Standard User Grid Layout (Card representation) -->
          <div class="card-body p-4 bg-body-tertiary">
            <div class="row g-4">
              <?php if ($total > 0): ?>
                <?php foreach ($kendaraan as $k): ?>
                  <?php
                  $status = strtolower(trim($k['status_kendaraan'] ?? 'tersedia'));
                  ?>
                  <div class="col-sm-6 col-md-4 col-xl-3">
                    <div class="card border-0 shadow-sm h-100 overflow-hidden" style="border-radius: 12px;">
                      <!-- Image Header -->
                      <div class="position-relative bg-dark" style="height: 180px; overflow: hidden;">
                        <?php if (!empty($k['gambar']) && file_exists('uploads/' . $k['gambar'])): ?>
                          <img src="uploads/<?= htmlspecialchars($k['gambar']) ?>" alt="<?= htmlspecialchars($k['nama_kendaraan']) ?>" class="w-100 h-100" style="object-fit: cover; transition: transform 0.3s ease;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                        <?php else: ?>
                          <div class="w-100 h-100 d-flex flex-column align-items-center justify-content-center text-muted">
                            <i class="fa fa-car fa-2x mb-1"></i>
                            <span class="small">No Image</span>
                          </div>
                        <?php endif; ?>
                        
                        <!-- Status Badge -->
                        <div class="position-absolute top-0 end-0 m-2.5">
                          <?php if ($status === 'disewa'): ?>
                            <span class="badge bg-warning text-dark px-2.5 py-1.5 fw-semibold"><i class="fa fa-key me-1"></i>Disewa</span>
                          <?php elseif ($status === 'perawatan'): ?>
                            <span class="badge bg-danger text-white px-2.5 py-1.5 fw-semibold"><i class="fa fa-tools me-1"></i>Perawatan</span>
                          <?php else: ?>
                            <span class="badge bg-success text-white px-2.5 py-1.5 fw-semibold"><i class="fa fa-check me-1"></i>Tersedia</span>
                          <?php endif; ?>
                        </div>

                        <!-- Brand / Type Tag -->
                        <div class="position-absolute bottom-0 start-0 m-2.5">
                          <span class="badge bg-dark bg-opacity-75 text-white px-2 py-1.5 text-uppercase" style="font-size: 0.7rem;">
                            <?= htmlspecialchars($k['jenis_kendaraan'] ?? 'Roda 4') ?>
                          </span>
                        </div>
                      </div>

                      <!-- Card Body -->
                      <div class="card-body p-3 d-flex flex-column justify-content-between">
                        <div>
                          <div class="small text-muted font-monospace text-uppercase" style="font-size: 0.75rem;">Kode: <?= htmlspecialchars($k['kode_unik_kendaraan']) ?></div>
                          <h6 class="fw-bold mb-2 text-body-emphasis mt-0.5" style="font-size: 1.1rem; line-height: 1.3;"><?= htmlspecialchars($k['nama_kendaraan']) ?></h6>
                          
                          <div class="d-flex flex-wrap gap-1 mb-3 text-muted small" style="font-size: 0.8rem;">
                            <span class="badge bg-secondary bg-opacity-10 text-secondary"><i class="fa fa-palette me-1"></i><?= htmlspecialchars($k['warna'] ?? 'Hitam') ?></span>
                            <span class="badge bg-secondary bg-opacity-10 text-secondary"><i class="fa fa-cogs me-1"></i><?= htmlspecialchars($k['transmisi'] ?? 'Matic') ?></span>
                            <span class="badge bg-secondary bg-opacity-10 text-secondary"><i class="fa fa-users me-1"></i><?= htmlspecialchars($k['tempat_duduk'] ?? '5 Seat') ?></span>
                            <span class="badge bg-secondary bg-opacity-10 text-secondary"><i class="fa fa-gas-pump me-1"></i><?= htmlspecialchars($k['bahan_bakar'] ?? 'Bensin') ?></span>
                          </div>
                        </div>

                        <div>
                          <div class="border-top pt-2 d-flex align-items-center justify-content-between mb-3">
                            <span class="text-muted small">Tarif Harian</span>
                            <span class="fs-5 fw-bold text-primary">Rp <?= number_format($k['harga_per_hari'], 0, ',', '.') ?><span class="text-muted fw-normal" style="font-size: 0.75rem;">/hari</span></span>
                          </div>

                          <?php if ($status === 'tersedia'): ?>
                            <button type="button"
                                    class="btn btn-success text-white w-100 py-2 fw-semibold d-flex align-items-center justify-content-center gap-1"
                                    data-coreui-toggle="modal"
                                    data-coreui-target="#bookingModal"
                                    data-kode="<?= htmlspecialchars($k['kode_unik_kendaraan']) ?>"
                                    data-nama="<?= htmlspecialchars($k['nama_kendaraan']) ?>"
                                    data-harga="<?= htmlspecialchars($k['harga_per_hari']) ?>">
                              <i class="fa fa-key me-1"></i> Sewa Sekarang
                            </button>
                          <?php else: ?>
                            <button class="btn btn-secondary w-100 py-2 fw-semibold" disabled>
                              Tidak Tersedia
                            </button>
                          <?php endif; ?>
                        </div>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php else: ?>
                <div class="col-12 py-5 text-center text-muted">
                  <i class="fa fa-folder-open fa-3x mb-3 text-muted"></i>
                  <p class="mb-0">Tidak ada kendaraan yang sesuai dengan kriteria pencarian.</p>
                </div>
              <?php endif; ?>
            </div>
            
            <!-- User Pagination -->
            <?php if ($total_pages > 1): ?>
              <div class="d-flex justify-content-center mt-4">
                <nav aria-label="Page navigation">
                  <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                      <a class="page-link" href="?page=<?= $page - 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>">&laquo;</a>
                    </li>
                    <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                      <li class="page-item <?= ($page == $p) ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $p ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"><?= $p ?></a>
                      </li>
                    <?php endfor; ?>
                    <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                      <a class="page-link" href="?page=<?= $page + 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>">&raquo;</a>
                    </li>
                  </ul>
                </nav>
              </div>
            <?php endif; ?>
          </div>
          <?php endif; ?>
        </div>
    <div class="modal fade" id="addKendaraanModal" tabindex="-1" aria-labelledby="addKendaraanModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
          <div class="modal-header bg-primary text-white">
            <h5 class="modal-title" id="addKendaraanModalLabel"><i class="fa fa-plus me-2"></i>Tambah Kendaraan Baru</h5>
            <button type="button" class="btn-close btn-close-white" data-coreui-dismiss="modal" aria-label="Close"></button>
          </div>
          <form method="POST" action="" enctype="multipart/form-data" novalidate>
            <div class="modal-body p-4">
              <?php if (!empty($error_add)): ?>
                  <div class="alert alert-danger border-0 bg-danger bg-opacity-10 text-danger small py-2 px-3 mb-4">
                      <i class="fa fa-exclamation-triangle me-1"></i> <?= htmlspecialchars($error_add) ?>
                  </div>
              <?php endif; ?>

              <input type="hidden" name="action" value="add">

              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label" for="add_kode_unik">Kode Unik Kendaraan *</label>
                  <input type="text" id="add_kode_unik" name="kode_unik_kendaraan" class="form-control" placeholder="Contoh: 1122" value="<?= htmlspecialchars($_POST['kode_unik_kendaraan'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label" for="add_merk">Merk Kendaraan *</label>
                  <select id="add_merk" name="id_merk" class="form-select select2-brand" required>
                    <option value="" disabled selected>-- Pilih Merk Kendaraan --</option>
                    <?php foreach ($merk_options as $option): ?>
                      <option value="<?= $option['id_merk'] ?>" <?= (($_POST['id_merk'] ?? '') == $option['id_merk']) ? 'selected' : '' ?>><?= htmlspecialchars(ucwords($option['nama_merk'])) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label" for="add_model">Model Kendaraan *</label>
                  <input type="text" id="add_model" name="model_kendaraan" class="form-control" placeholder="Contoh: Avanza atau Vario 150" value="<?= htmlspecialchars($_POST['model_kendaraan'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label" for="add_jenis">Jenis Kendaraan *</label>
                  <select id="add_jenis" name="jenis_kendaraan" class="form-select" required>
                    <option value="" disabled <?= empty($_POST['jenis_kendaraan']) ? 'selected' : '' ?>>-- Pilih Jenis Kendaraan --</option>
                    <option value="Roda 2" <?= ($_POST['jenis_kendaraan'] ?? '') === 'Roda 2' ? 'selected' : '' ?>>Roda 2 (Motor)</option>
                    <option value="Roda 4" <?= ($_POST['jenis_kendaraan'] ?? '') === 'Roda 4' ? 'selected' : '' ?>>Roda 4 (Mobil)</option>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label" for="add_harga">Harga per Hari (Rp) *</label>
                  <input type="number" id="add_harga" name="harga_per_hari" class="form-control" placeholder="Contoh: 150000" min="0" value="<?= htmlspecialchars($_POST['harga_per_hari'] ?? '') ?>" required>
                </div>
                <div class="col-md-4">
                  <label class="form-label" for="add_transmisi">Transmisi *</label>
                  <select id="add_transmisi" name="transmisi" class="form-select" required>
                    <option value="Matic" <?= ($_POST['transmisi'] ?? '') === 'Matic' ? 'selected' : '' ?>>Matic</option>
                    <option value="Manual" <?= ($_POST['transmisi'] ?? '') === 'Manual' ? 'selected' : '' ?>>Manual</option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label" for="add_tempat_duduk">Jumlah Seat *</label>
                  <input type="text" id="add_tempat_duduk" name="tempat_duduk" class="form-control" placeholder="Contoh: 5 Seater, 2 Seater" value="<?= htmlspecialchars($_POST['tempat_duduk'] ?? '5 Seater') ?>" required>
                </div>
                <div class="col-md-4">
                  <label class="form-label" for="add_bahan_bakar">Bahan Bakar *</label>
                  <input type="text" id="add_bahan_bakar" name="bahan_bakar" class="form-control" placeholder="Contoh: Bensin, Solar, Shell V-Power" value="<?= htmlspecialchars($_POST['bahan_bakar'] ?? 'Bensin') ?>" required>
                </div>
                <div class="col-md-4">
                  <label class="form-label" for="add_status">Status Ketersediaan *</label>
                  <select id="add_status" name="status_kendaraan" class="form-select" required>
                    <option value="tersedia" <?= ($_POST['status_kendaraan'] ?? '') === 'tersedia' ? 'selected' : '' ?>>Tersedia</option>
                    <option value="disewa" <?= ($_POST['status_kendaraan'] ?? '') === 'disewa' ? 'selected' : '' ?>>Sedang Disewa</option>
                    <option value="perawatan" <?= ($_POST['status_kendaraan'] ?? '') === 'perawatan' ? 'selected' : '' ?>>Dalam Perawatan</option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label" for="add_stok">Jumlah Stok *</label>
                  <input type="number" id="add_stok" name="stok" class="form-control" min="0" placeholder="Contoh: 1" value="<?= htmlspecialchars($_POST['stok'] ?? '1') ?>" required>
                </div>
                <div class="col-md-4">
                  <label class="form-label" for="add_warna">Warna Tersedia *</label>
                  <input type="text" id="add_warna" name="warna" class="form-control" placeholder="Contoh: Hitam, Putih, Merah" value="<?= htmlspecialchars($_POST['warna'] ?? 'Hitam') ?>" required>
                </div>
                <div class="col-12">
                  <label class="form-label" for="add_gambar">Upload Gambar Kendaraan (Opsional)</label>
                  <input type="file" id="add_gambar" name="gambar" class="form-control" accept="image/*">
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

    <!-- Import Kendaraan Modal -->
    <div class="modal fade" id="importKendaraanModal" tabindex="-1" aria-labelledby="importKendaraanModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
          <div class="modal-header bg-warning text-white">
            <h5 class="modal-title" id="importKendaraanModalLabel"><i class="fa fa-upload me-2 text-white"></i>Import Data Kendaraan</h5>
            <button type="button" class="btn-close btn-close-white" data-coreui-dismiss="modal" aria-label="Close"></button>
          </div>
          <form method="POST" action="import.php" enctype="multipart/form-data" novalidate>
            <div class="modal-body p-4">
              <div class="mb-3">
                <p class="text-body-secondary small">Anda dapat mengimpor data kendaraan secara massal menggunakan file Excel (.xlsx) atau CSV. Pastikan format kolom sesuai dengan template.</p>
                <a href="import.php?action=template" class="btn btn-outline-primary btn-sm w-100 py-2"><i class="fa fa-download me-1"></i> Unduh Template Excel</a>
              </div>
              <hr class="text-secondary opacity-25">
              <div class="mb-3">
                <label class="form-label" for="excel_file">Pilih File Excel / CSV *</label>
                <input type="file" id="excel_file" name="excel_file" class="form-control" accept=".xlsx, .xls, .csv" required>
                <div class="form-text text-muted">Maksimal ukuran file: 5 MB.</div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-coreui-dismiss="modal">Batal</button>
              <button type="submit" class="btn btn-warning text-white"><i class="fa fa-upload me-1 text-white"></i> Mulai Impor</button>
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
                  echo 'Data telah berhasil disimpan.';
              } elseif ($msg === 'updated') {
                  echo 'Data telah berhasil diperbarui.';
              } elseif ($msg === 'deleted') {
                  echo 'Data telah berhasil dihapus.';
              } elseif ($msg === 'imported') {
                  echo 'Data telah berhasil diimpor.';
              }
              ?>
            </p>
            <button type="button" class="btn btn-success text-white w-100 py-2" data-coreui-dismiss="modal" style="border-radius: 8px;">OK</button>
          </div>
        </div>
      </div>
    </div>
    <!-- Modal Booking Sewa Instan -->
    <div class="modal fade" id="bookingModal" tabindex="-1" aria-labelledby="bookingModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px;">
          <div class="modal-header bg-success text-white" style="border-top-left-radius: 12px; border-top-right-radius: 12px;">
            <h5 class="modal-title" id="bookingModalLabel"><i class="fa fa-key me-2"></i>Sewa Kendaraan</h5>
            <button type="button" class="btn-close btn-close-white" data-coreui-dismiss="modal" aria-label="Close"></button>
          </div>
          <form method="POST" action="">
            <input type="hidden" name="action" value="add_booking">
            <input type="hidden" name="kode_unik_kendaraan" id="book_kode_unik">
            
            <div class="modal-body p-4">
              <?php if (!empty($error_booking)): ?>
                  <div class="alert alert-danger border-0 bg-danger bg-opacity-10 text-danger small py-2 px-3 mb-3">
                      <i class="fa fa-exclamation-triangle me-1"></i> <?= htmlspecialchars($error_booking) ?>
                  </div>
              <?php endif; ?>

              <div class="text-center mb-3">
                <h6 class="text-muted mb-1 text-uppercase font-monospace" style="font-size: 0.75rem;">Kendaraan Pilihan Anda:</h6>
                <h4 class="fw-bold text-body-emphasis mb-2" id="book_nama_kendaraan">Nama Kendaraan</h4>
                <div class="badge bg-primary fs-6 px-3 py-2 rounded-pill">
                  Rp <span id="book_harga_per_hari_text">0</span><span class="fs-7 fw-normal">/hari</span>
                </div>
              </div>
              <hr class="text-body-secondary my-3">
              
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label fw-semibold" for="book_tanggal_sewa">Tanggal Mulai Sewa *</label>
                  <input type="datetime-local" id="book_tanggal_sewa" name="tanggal_sewa" class="form-control" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-semibold" for="book_tanggal_kembali">Tanggal Pengembalian *</label>
                  <input type="datetime-local" id="book_tanggal_kembali" name="tanggal_kembali" class="form-control" required>
                </div>
                <div class="col-12 mt-4 bg-body-secondary p-3 rounded border border-secondary border-opacity-10 d-flex justify-content-between align-items-center">
                  <div>
                    <div class="text-muted small">Durasi Sewa:</div>
                    <div class="fw-bold text-body"><span id="book_durasi">1</span> Hari</div>
                  </div>
                  <div class="text-end">
                    <div class="text-muted small">Estimasi Total Biaya:</div>
                    <div class="fw-bold fs-5 text-success">Rp <span id="book_total_biaya_text">0</span></div>
                  </div>
                </div>
              </div>
            </div>
            <div class="modal-footer justify-content-center" style="border-bottom-left-radius: 12px; border-bottom-right-radius: 12px;">
              <button type="button" class="btn btn-secondary px-4" data-coreui-dismiss="modal">Batal</button>
              <button type="submit" class="btn btn-success text-white px-5 fw-bold"><i class="fa fa-check me-1"></i>Konfirmasi Sewa</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <?php include 'partials/footer.php'; ?>

    <script>
    (function initDashboard() {
      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDashboard);
        return;
      }
      // 1. Success Modal Trigger
      <?php if (!empty($msg)): ?>
      const successModal = new coreui.Modal(document.getElementById('successModal'));
      successModal.show();
      setTimeout(() => {
        successModal.hide();
      }, 3000);
      <?php endif; ?>

      // 2. Open Add Modal on validation error or sidebar query parameter
      <?php if (!empty($error_add) || isset($_GET['show_add_modal'])): ?>
      const addModal = new coreui.Modal(document.getElementById('addKendaraanModal'));
      addModal.show();
      <?php endif; ?>

      // 3. Open Edit Modal on validation error
      <?php if (!empty($error_edit) && !empty($error_edit_kode)): ?>
      const editModal = new coreui.Modal(document.getElementById('editModal-<?= $error_edit_kode ?>'));
      editModal.show();
      <?php endif; ?>

      // 4. Initialize Select2 for Add Modal
      $('#add_merk').select2({
        theme: 'bootstrap-5',
        dropdownParent: $('#addKendaraanModal')
      });

      // 5. Initialize Select2 for all Edit Modals
      $('.select2-brand-edit').each(function() {
        const selectEl = $(this);
        const modalId = selectEl.attr('data-modal-id');
        
        selectEl.select2({
          theme: 'bootstrap-5',
          dropdownParent: $('#' + modalId)
        });
      });


      // 6. Booking Modal - populate fields when modal is about to open
      //    CoreUI fires 'show.coreui.modal' with event.relatedTarget = the trigger button
      var bookingModalEl = document.getElementById('bookingModal');
      if (bookingModalEl) {
        bookingModalEl.addEventListener('show.coreui.modal', function(event) {
          var btn = event.relatedTarget;
          if (!btn) return;

          var kode  = btn.getAttribute('data-kode');
          var nama  = btn.getAttribute('data-nama');
          var harga = parseFloat(btn.getAttribute('data-harga')) || 0;

          document.getElementById('book_kode_unik').value = kode;
          document.getElementById('book_nama_kendaraan').textContent = nama;
          document.getElementById('book_harga_per_hari_text').textContent =
            new Intl.NumberFormat('id-ID').format(harga);

          // Default dates: now+1h for start, now+1d for return
          var now = new Date();
          now.setHours(now.getHours() + 1, 0, 0, 0);
          var tzoffset   = now.getTimezoneOffset() * 60000;
          var localStart = new Date(now - tzoffset).toISOString().slice(0, 16);
          var returnDate = new Date(now);
          returnDate.setDate(returnDate.getDate() + 1);
          var localReturn = new Date(returnDate - tzoffset).toISOString().slice(0, 16);

          var inputSewa    = document.getElementById('book_tanggal_sewa');
          var inputKembali = document.getElementById('book_tanggal_kembali');
          inputSewa.value    = localStart;
          inputKembali.value = localReturn;

          function recalc() {
            var t1 = new Date(inputSewa.value);
            var t2 = new Date(inputKembali.value);
            var diffMs   = t2 - t1;
            var diffDays = diffMs > 0 ? Math.ceil(diffMs / 86400000) : 1;
            document.getElementById('book_durasi').textContent = diffDays;
            document.getElementById('book_total_biaya_text').textContent =
              new Intl.NumberFormat('id-ID').format(diffDays * harga);
          }

          inputSewa.onchange    = recalc;
          inputKembali.onchange = recalc;
          recalc();
        });
      }


      // 7. Auto re-open booking modal on error
      <?php if (!empty($error_booking)): ?>
        <?php
        $err_kode = $_POST['kode_unik_kendaraan'] ?? '';
        $err_v_res = mysqli_query($mysqli, "SELECT harga_per_hari, nama_kendaraan FROM kendaraan WHERE kode_unik_kendaraan = " . intval($err_kode));
        $err_v_data = mysqli_fetch_assoc($err_v_res);
        $err_nama = $err_v_data['nama_kendaraan'] ?? 'Kendaraan';
        $err_harga = $err_v_data['harga_per_hari'] ?? 0;
        ?>
        document.getElementById('book_kode_unik').value = "<?= htmlspecialchars($err_kode) ?>";
        document.getElementById('book_nama_kendaraan').textContent = "<?= htmlspecialchars($err_nama) ?>";
        document.getElementById('book_harga_per_hari_text').textContent = new Intl.NumberFormat('id-ID').format(<?= $err_harga ?>);
        document.getElementById('book_tanggal_sewa').value = "<?= htmlspecialchars($_POST['tanggal_sewa'] ?? '') ?>";
        document.getElementById('book_tanggal_kembali').value = "<?= htmlspecialchars($_POST['tanggal_kembali'] ?? '') ?>";
        
        // Initial recalc
        const err_harga = <?= $err_harga ?>;
        const err_t1 = new Date(document.getElementById('book_tanggal_sewa').value);
        const err_t2 = new Date(document.getElementById('book_tanggal_kembali').value);
        const err_diff = err_t2 - err_t1;
        let err_days = 1;
        if (err_diff > 0) {
          err_days = Math.ceil(err_diff / (1000 * 60 * 60 * 24));
        }
        document.getElementById('book_durasi').textContent = err_days;
        document.getElementById('book_total_biaya_text').textContent = new Intl.NumberFormat('id-ID').format(err_days * err_harga);

        const bookingModalErr = coreui.Modal.getOrCreateInstance(document.getElementById('bookingModal'));
        bookingModalErr.show();
      <?php endif; ?>
    })();
    </script>