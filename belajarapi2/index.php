<?php
require_once 'api_helper.php';

// Ambil seluruh data mahasiswa dari API
$response_data = callAPI('GET', $api_url);
$mahasiswa_list = (isset($response_data['status']) && $response_data['status'] === 'error') ? [] : $response_data;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard CRUD Mahasiswa - API Client</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts: Outfit -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Outfit', sans-serif;
            background-color: #f4f7f6;
            color: #333;
        }
        .navbar-custom {
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .card {
            border: none;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }
        .table {
            vertical-align: middle;
        }
        .table thead {
            background-color: #f1f3f5;
        }
        .table thead th {
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
            color: #495057;
            border-bottom: 2px solid #dee2e6;
            padding: 15px;
        }
        .table tbody tr {
            transition: background-color 0.2s ease;
        }
        .table tbody tr:hover {
            background-color: rgba(30, 60, 114, 0.03);
        }
        .table td {
            padding: 15px;
            border-bottom: 1px solid #e9ecef;
        }
        .btn-action {
            padding: 6px 12px;
            font-size: 0.85rem;
            font-weight: 500;
            border-radius: 8px;
            transition: all 0.2s ease;
        }
        .btn-action:hover {
            transform: translateY(-1px);
        }
        .btn-tambah {
            background: linear-gradient(135deg, #11998e, #38ef7d);
            border: none;
            color: white;
            font-weight: 500;
            padding: 10px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(17, 153, 142, 0.2);
            transition: all 0.3s ease;
        }
        .btn-tambah:hover {
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(17, 153, 142, 0.35);
        }
        .alert-custom {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-dark navbar-custom py-3 mb-5">
    <div class="container">
        <span class="navbar-brand mb-0 h1 fw-bold fs-4">
            <i class="bi bi-mortarboard-fill me-2"></i>Sistem Akademik API Client
        </span>
        <span class="text-white-50 small">Pemrograman Web 1</span>
    </div>
</nav>

<div class="container mb-5">
    <!-- Flash Message -->
    <?php if (isset($_SESSION['flash'])): ?>
        <div class="alert alert-<?= ($_SESSION['flash']['status'] ?? 'error') === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show alert-custom p-4 mb-4" role="alert">
            <div class="d-flex align-items-center">
                <i class="bi <?= ($_SESSION['flash']['status'] ?? 'error') === 'success' ? 'bi-check-circle-fill text-success' : 'bi-exclamation-triangle-fill text-danger' ?> fs-3 me-3"></i>
                <div>
                    <h5 class="alert-heading mb-1 fw-semibold">
                        <?= ($_SESSION['flash']['status'] ?? '') === 'success' ? 'Operasi Berhasil!' : 'Operasi Gagal!' ?>
                    </h5>
                    <p class="mb-0 text-secondary"><?= htmlspecialchars($_SESSION['flash']['message'] ?? 'Terjadi kesalahan tidak dikenal.') ?></p>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['flash']); ?>
    <?php endif; ?>

    <!-- Main Content -->
    <div class="card">
        <div class="card-header bg-white border-bottom py-4">
            <div class="row align-items-center justify-content-between">
                <div class="col-sm-6 mb-3 mb-sm-0">
                    <h4 class="mb-0 fw-semibold text-dark">Daftar Mahasiswa</h4>
                    <p class="mb-0 text-muted small mt-1">Mengelola data mahasiswa yang terintegrasi dengan REST API</p>
                </div>
                <div class="col-sm-auto">
                    <button type="button" class="btn btn-tambah d-inline-flex align-items-center" data-bs-toggle="modal" data-bs-target="#modalTambah">
                        <i class="bi bi-plus-lg me-2"></i> Tambah Mahasiswa
                    </button>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th class="text-center" style="width: 15%;">NIM</th>
                            <th style="width: 50%;">Nama Lengkap</th>
                            <th class="text-center" style="width: 15%;">ID Prodi</th>
                            <th class="text-center" style="width: 20%;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($mahasiswa_list) && is_array($mahasiswa_list)): ?>
                            <?php foreach ($mahasiswa_list as $row): ?>
                            <tr>
                                <td class="text-center fw-medium text-secondary"><?= htmlspecialchars($row['nim'] ?? '') ?></td>
                                <td class="fw-semibold text-dark"><?= htmlspecialchars($row['nama'] ?? '') ?></td>
                                <td class="text-center text-muted"><?= htmlspecialchars($row['id_prodi'] ?? '') ?></td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-action btn-warning me-1" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#modalEdit" 
                                            data-nim="<?= $row['nim'] ?>" 
                                            data-nama="<?= htmlspecialchars($row['nama'] ?? '') ?>" 
                                            data-id-prodi="<?= $row['id_prodi'] ?? '' ?>">
                                        <i class="bi bi-pencil-square me-1"></i> Edit
                                    </button>
                                    <a href="hapus.php?nim=<?= $row['nim'] ?>" 
                                       class="btn btn-action btn-danger" 
                                       onclick="return confirm('Apakah Anda yakin ingin menghapus data mahasiswa dengan NIM <?= $row['nim'] ?>?');">
                                        <i class="bi bi-trash-fill me-1"></i> Hapus
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-5">
                                    <i class="bi bi-inbox fs-1 d-block mb-3 text-secondary"></i>
                                    Tidak ada data mahasiswa ditemukan.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Include Modals -->
<?php 
include 'modal_tambah.php'; 
include 'modal_edit.php'; 
?>

<!-- Footer -->
<footer class="footer mt-auto py-4 bg-white border-top">
    <div class="container text-center">
        <span class="text-muted small">&copy; 2026 Sistem Akademik Client. All rights reserved.</span>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modalEdit = document.getElementById('modalEdit');
    if (modalEdit) {
        modalEdit.addEventListener('show.bs.modal', function (event) {
            // Button that triggered the modal
            const button = event.relatedTarget;
            // Extract info from data-bs-* attributes
            const nim = button.getAttribute('data-nim');
            const nama = button.getAttribute('data-nama');
            const idProdi = button.getAttribute('data-id-prodi');
            
            // Update the modal's content.
            const inputNim = modalEdit.querySelector('#edit-nim');
            const inputNama = modalEdit.querySelector('#edit-nama');
            const inputIdProdi = modalEdit.querySelector('#edit-id_prodi');
            
            inputNim.value = nim;
            inputNama.value = nama;
            inputIdProdi.value = idProdi;
        });
    }
});
</script>
</body>
</html>