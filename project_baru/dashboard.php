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

$merk_options = [];
$res_merk = mysqli_query($mysqli, "SELECT id_merk, nama_merk FROM merk_kendaraan ORDER BY nama_merk ASC");
if ($res_merk) {
    $merk_options = mysqli_fetch_all($res_merk, MYSQLI_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Restrict modifications to Admin role
    if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
        header('Location: dashboard.php?error=unauthorized');
        exit;
    }

    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $kode  = trim($_POST['kode_unik_kendaraan'] ?? '');
        $id_merk = intval($_POST['id_merk']          ?? 0);
        $model = trim($_POST['model_kendaraan']     ?? '');
        $jenis = trim($_POST['jenis_kendaraan']     ?? '');
        $harga = $_POST['harga_per_hari']           ?? '';

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
                    $ext = pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION);
                    $gambar_nama = time() . '_' . $kode . '.' . $ext;
                    move_uploaded_file($tmp, 'uploads/' . $gambar_nama);
                }

                $stmt = mysqli_prepare($mysqli, "INSERT INTO kendaraan (kode_unik_kendaraan, id_merk, nama_kendaraan, jenis_kendaraan, harga_per_hari, gambar) VALUES (?, ?, ?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt, 'sisssd', $kode, $id_merk, $nama, $jenis, $harga, $gambar_nama);

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
                    $ext = pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION);
                    $nama_file_baru = time() . '_' . $kode . '.' . $ext;
                    
                    if (move_uploaded_file($tmp, 'uploads/' . $nama_file_baru)) {
                        if (!empty($kendaraan_edit['gambar']) && file_exists('uploads/' . $kendaraan_edit['gambar'])) {
                            unlink('uploads/' . $kendaraan_edit['gambar']);
                        }
                        $gambar_baru = $nama_file_baru;
                    }
                }

                $upd = mysqli_prepare($mysqli, "UPDATE kendaraan SET id_merk = ?, nama_kendaraan = ?, jenis_kendaraan = ?, harga_per_hari = ?, gambar = ? WHERE kode_unik_kendaraan = ?");
                mysqli_stmt_bind_param($upd, 'isssds', $id_merk_baru, $nama_baru, $jenis_baru, $harga_baru, $gambar_baru, $kode);

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
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) {
    $page = 1;
}
$offset = ($page - 1) * $limit;

// Menangkap keyword pencarian dari URL
$search = trim($_GET['search'] ?? '');

if (!empty($search)) {
    $search_param = "%" . $search . "%";
    
    // Count total matching items
    $count_stmt = mysqli_prepare($mysqli, "SELECT COUNT(*) FROM kendaraan WHERE kode_unik_kendaraan LIKE ? OR nama_kendaraan LIKE ? OR jenis_kendaraan LIKE ?");
    mysqli_stmt_bind_param($count_stmt, 'sss', $search_param, $search_param, $search_param);
    mysqli_stmt_execute($count_stmt);
    mysqli_stmt_bind_result($count_stmt, $total_items);
    mysqli_stmt_fetch($count_stmt);
    mysqli_stmt_close($count_stmt);
    
    $total_pages = ceil($total_items / $limit);
    if ($page > $total_pages && $total_pages > 0) {
        $page = $total_pages;
        $offset = ($page - 1) * $limit;
    }

    $stmt = mysqli_prepare($mysqli, "SELECT * FROM kendaraan WHERE kode_unik_kendaraan LIKE ? OR nama_kendaraan LIKE ? OR jenis_kendaraan LIKE ? ORDER BY kode_unik_kendaraan ASC LIMIT ? OFFSET ?");
    mysqli_stmt_bind_param($stmt, 'sssii', $search_param, $search_param, $search_param, $limit, $offset);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $kendaraan = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
} else {
    // Count total items
    $count_res = mysqli_query($mysqli, "SELECT COUNT(*) FROM kendaraan");
    $count_row = mysqli_fetch_row($count_res);
    $total_items = $count_row[0];
    
    $total_pages = ceil($total_items / $limit);
    if ($page > $total_pages && $total_pages > 0) {
        $page = $total_pages;
        $offset = ($page - 1) * $limit;
    }

    $stmt = mysqli_prepare($mysqli, "SELECT * FROM kendaraan ORDER BY kode_unik_kendaraan ASC LIMIT ? OFFSET ?");
    mysqli_stmt_bind_param($stmt, 'ii', $limit, $offset);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $kendaraan = mysqli_fetch_all($result, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
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
              <form action="dashboard.php" method="GET" class="d-flex w-100 w-sm-auto">
                <input type="text" name="search" class="form-control form-control-sm me-2" placeholder="Cari nama, kode, jenis..." value="<?= htmlspecialchars($search) ?>" style="min-width: 200px;">
                <button type="submit" class="btn btn-primary btn-sm me-1"><i class="fa fa-search"></i></button>
                <?php if(!empty($search)): ?>
                    <a href="dashboard.php" class="btn btn-danger btn-sm" title="Reset Pencarian"><i class="fa fa-times"></i></a>
                <?php endif; ?>
              </form>
              <div class="dropdown mt-2 mt-sm-0">
                <button class="btn btn-success btn-sm dropdown-toggle text-nowrap" type="button" data-coreui-toggle="dropdown" aria-expanded="false">
                  <i class="fa fa-download me-1"></i> Export
                </button>
                <ul class="dropdown-menu">
                  <li><a class="dropdown-item" href="export.php?target=kendaraan&format=excel<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"><i class="fa fa-file-excel text-success me-2"></i> Excel (.xlsx)</a></li>
                  <li><a class="dropdown-item" href="export.php?target=kendaraan&format=word<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"><i class="fa fa-file-word text-primary me-2"></i> Word (.docx)</a></li>
                  <li><a class="dropdown-item" href="export.php?target=kendaraan&format=pdf<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"><i class="fa fa-file-pdf text-danger me-2"></i> PDF (.pdf)</a></li>
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
                          <td class="text-body fw-bold"><?= htmlspecialchars($k['nama_kendaraan']) ?></td>
                          <td>
                              <span class="badge bg-info bg-opacity-10 text-info px-2 py-1">
                                    <?= (strtolower($k['jenis_kendaraan']) === 'roda 2') ? 'Roda 2' : 'Roda 4' ?>
                              </span>
                          </td>
                          <td class="text-body fw-semibold">Rp <?= number_format($k['harga_per_hari'], 0, ',', '.') ?></td>
                          <td class="pe-4 text-end">
                              <div class="btn-group btn-group-sm" role="group">
                                  <a href="export.php?target=kendaraan&format=pdf&kode=<?= urlencode($k['kode_unik_kendaraan']) ?>" class="btn btn-outline-secondary d-flex align-items-center gap-1" title="Cetak PDF">
                                      <i class="fa fa-print"></i> Cetak
                                  </a>
                                  <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                                      <button type="button" class="btn btn-outline-info d-flex align-items-center gap-1" data-coreui-toggle="modal" data-coreui-target="#editModal-<?= $k['kode_unik_kendaraan'] ?>">
                                          <i class="fa fa-edit"></i> Edit
                                      </button>
                                      <button type="button" class="btn btn-outline-danger d-flex align-items-center gap-1" data-coreui-toggle="modal" data-coreui-target="#deleteModal-<?= $k['kode_unik_kendaraan'] ?>">
                                          <i class="fa fa-trash"></i> Hapus
                                      </button>
                                  <?php endif; ?>
                              </div>

                              <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                              <!-- Edit Modal -->
                              <div class="modal fade text-start" id="editModal-<?= $k['kode_unik_kendaraan'] ?>" tabindex="-1" aria-labelledby="editModalLabel-<?= $k['kode_unik_kendaraan'] ?>" aria-hidden="true">
                                <div class="modal-dialog modal-lg modal-dialog-centered">
                                  <div class="modal-content border-0 shadow-lg">
                                    <div class="modal-header bg-info text-white">
                                      <h5 class="modal-title" id="editModalLabel-<?= $k['kode_unik_kendaraan'] ?>"><i class="fa fa-edit me-2"></i>Edit Data Kendaraan</h5>
                                      <button type="button" class="btn-close btn-close-white" data-coreui-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <form method="POST" action="" enctype="multipart/form-data" novalidate>
                                      <div class="modal-body p-4">
                                        <?php if (!empty($error_edit) && $error_edit_kode == $k['kode_unik_kendaraan']): ?>
                                            <div class="alert alert-danger border-0 bg-danger bg-opacity-10 text-danger small py-2 px-3 mb-4">
                                                <i class="fa fa-exclamation-triangle me-1"></i> <?= htmlspecialchars($error_edit) ?>
                                            </div>
                                        <?php endif; ?>

                                        <input type="hidden" name="action" value="edit">
                                        <input type="hidden" name="kode_unik_kendaraan" value="<?= htmlspecialchars($k['kode_unik_kendaraan']) ?>">

                                        <div class="row g-3">
                                          <div class="col-md-6">
                                            <label class="form-label" for="kode_unik_<?= $k['kode_unik_kendaraan'] ?>">Kode Unik Kendaraan</label>
                                            <input type="text" id="kode_unik_<?= $k['kode_unik_kendaraan'] ?>" class="form-control text-warning bg-dark border border-secondary" value="<?= htmlspecialchars($k['kode_unik_kendaraan']) ?>" readonly disabled>
                                          </div>
                                          <?php
                                          if ($error_edit_kode == $k['kode_unik_kendaraan']) {
                                              $cur_id_merk = intval($_POST['id_merk'] ?? 0);
                                              $cur_model = $_POST['model_kendaraan'] ?? '';
                                          } else {
                                              $cur_id_merk = $k['id_merk'];
                                              // Get the brand name for this vehicle's id_merk to strip it from nama_kendaraan
                                              $cur_brand_name = '';
                                              foreach ($merk_options as $mo) {
                                                  if ($mo['id_merk'] == $cur_id_merk) {
                                                      $cur_brand_name = $mo['nama_merk'];
                                                      break;
                                                  }
                                              }
                                              $cur_model = $k['nama_kendaraan'];
                                              if (!empty($cur_brand_name) && stripos($k['nama_kendaraan'], $cur_brand_name) === 0) {
                                                  $cur_model = trim(substr($k['nama_kendaraan'], strlen($cur_brand_name)));
                                              }
                                          }
                                          ?>
                                          <div class="col-md-6">
                                            <label class="form-label" for="merk_<?= $k['kode_unik_kendaraan'] ?>">Merk Kendaraan *</label>
                                            <select id="merk_<?= $k['kode_unik_kendaraan'] ?>" name="id_merk" class="form-select select2-brand-edit" data-modal-id="editModal-<?= $k['kode_unik_kendaraan'] ?>" required>
                                              <option value="" disabled>-- Pilih Merk Kendaraan --</option>
                                              <?php foreach ($merk_options as $option): ?>
                                                <option value="<?= $option['id_merk'] ?>" <?= ($cur_id_merk == $option['id_merk']) ? 'selected' : '' ?>><?= htmlspecialchars(ucwords($option['nama_merk'])) ?></option>
                                              <?php endforeach; ?>
                                            </select>
                                          </div>
                                          <div class="col-md-6">
                                            <label class="form-label" for="model_<?= $k['kode_unik_kendaraan'] ?>">Model Kendaraan *</label>
                                            <input type="text" id="model_<?= $k['kode_unik_kendaraan'] ?>" name="model_kendaraan" class="form-control" placeholder="Contoh: Avanza atau Vario 150" value="<?= htmlspecialchars($cur_model) ?>" required>
                                          </div>
                                          <div class="col-md-6">
                                            <label class="form-label" for="jenis_<?= $k['kode_unik_kendaraan'] ?>">Jenis Kendaraan *</label>
                                            <select id="jenis_<?= $k['kode_unik_kendaraan'] ?>" name="jenis_kendaraan" class="form-select" required>
                                              <?php 
                                              $curr_jenis = $error_edit_kode == $k['kode_unik_kendaraan'] ? ($_POST['jenis_kendaraan'] ?? $k['jenis_kendaraan']) : $k['jenis_kendaraan'];
                                              $j_curr = strtolower(trim($curr_jenis)); 
                                              ?>
                                              <option value="Roda 2" <?= $j_curr === 'roda 2' ? 'selected' : '' ?>>Roda 2 (Motor)</option>
                                              <option value="Roda 4" <?= $j_curr === 'roda 4' ? 'selected' : '' ?>>Roda 4 (Mobil)</option>
                                            </select>
                                          </div>
                                          <div class="col-md-6">
                                            <label class="form-label" for="harga_<?= $k['kode_unik_kendaraan'] ?>">Harga per Hari (Rp) *</label>
                                            <input type="number" id="harga_<?= $k['kode_unik_kendaraan'] ?>" name="harga_per_hari" class="form-control" min="0" value="<?= htmlspecialchars($error_edit_kode == $k['kode_unik_kendaraan'] ? ($_POST['harga_per_hari'] ?? $k['harga_per_hari']) : $k['harga_per_hari']) ?>" required>
                                          </div>
                                          <div class="col-12 my-3">
                                            <label class="form-label d-block">Gambar Saat Ini</label>
                                            <?php if (!empty($k['gambar']) && file_exists('uploads/' . $k['gambar'])): ?>
                                                <img src="uploads/<?= htmlspecialchars($k['gambar']) ?>" alt="Mobil" class="rounded mb-2 img-thumbnail" style="width: 150px; height: 100px; object-fit: cover;">
                                            <?php else: ?>
                                                <span class="badge bg-dark mb-2 py-2 px-3">Belum ada gambar</span>
                                            <?php endif; ?>
                                            <input type="file" id="gambar_<?= $k['kode_unik_kendaraan'] ?>" name="gambar" class="form-control" accept="image/*">
                                            <div class="form-text text-muted">Biarkan kosong jika tidak ingin mengubah gambar.</div>
                                          </div>
                                        </div>
                                      </div>
                                      <div class="modal-footer bg-light">
                                        <button type="button" class="btn btn-secondary" data-coreui-dismiss="modal">Batal</button>
                                        <button type="submit" class="btn btn-info text-white"><i class="fa fa-save me-1"></i> Simpan Perubahan</button>
                                      </div>
                                    </form>
                                  </div>
                                </div>
                              </div>

                              <!-- Delete Confirmation Modal -->
                              <div class="modal fade text-start" id="deleteModal-<?= $k['kode_unik_kendaraan'] ?>" tabindex="-1" aria-labelledby="deleteModalLabel-<?= $k['kode_unik_kendaraan'] ?>" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                  <div class="modal-content border-0 shadow-lg">
                                    <div class="modal-header bg-danger text-white">
                                      <h5 class="modal-title" id="deleteModalLabel-<?= $k['kode_unik_kendaraan'] ?>"><i class="fa fa-exclamation-triangle me-2"></i>Konfirmasi Hapus</h5>
                                      <button type="button" class="btn-close btn-close-white" data-coreui-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body p-4 text-center">
                                      <i class="fa fa-trash fa-3x text-danger mb-3"></i>
                                      <h5 class="mb-2">Apakah Anda yakin ingin menghapus kendaraan ini?</h5>
                                      <p class="text-muted mb-0"><b><?= htmlspecialchars($k['nama_kendaraan']) ?></b> (Kode: <?= htmlspecialchars($k['kode_unik_kendaraan']) ?>)</p>
                                      <p class="text-danger small mt-2 mb-0"><i class="fa fa-info-circle"></i> Tindakan ini tidak dapat dibatalkan.</p>
                                    </div>
                                    <div class="modal-footer bg-light justify-content-center">
                                      <button type="button" class="btn btn-secondary px-4" data-coreui-dismiss="modal">Batal</button>
                                      <a href="delete.php?kode=<?= urlencode($k['kode_unik_kendaraan']) ?>" class="btn btn-danger px-4">Hapus</a>
                                    </div>
                                  </div>
                                </div>
                              </div>
                              <?php endif; ?>
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
        </div>

      </div>
    </div>

    <!-- Tambah Kendaraan Modal -->
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
                <div class="col-12">
                  <label class="form-label" for="add_gambar">Upload Gambar Kendaraan (Opsional)</label>
                  <input type="file" id="add_gambar" name="gambar" class="form-control" accept="image/*">
                </div>
              </div>
            </div>
            <div class="modal-footer bg-light">
              <button type="button" class="btn btn-secondary" data-coreui-dismiss="modal">Batal</button>
              <button type="submit" class="btn btn-primary"><i class="fa fa-save me-1"></i> Simpan</button>
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
            <div class="modal-footer bg-light">
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

    <?php include 'partials/footer.php'; ?>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
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
    });
    </script>