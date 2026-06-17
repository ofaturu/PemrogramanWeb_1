<?php
// ==========================================
// 1. KONFIGURASI & FUNGSI HELPER
// ==========================================
$api_url = "http://172.16.9.36/belajarrelasi/api.php";

/**
 * Fungsi untuk melakukan request ke API (cURL)
 */
function callAPI($method, $url, $data = false) {
    $curl = curl_init();
    
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    if ($data) {
        // Mengirim data dalam format JSON sesuai struktur yang diminta
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($curl);
    curl_close($curl);
    
    return json_decode($response, true);
}

// ==========================================
// 2. LOGIC CRUD (CREATE, UPDATE, DELETE)
// ==========================================

// Handle Create (POST) & Update (PUT)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = [
        "nim" => (int)$_POST['nim'], // Casting ke int sesuai format JSON
        "nama" => $_POST['nama'],
        "id_prodi" => (int)$_POST['id_prodi']
    ];

    if (isset($_POST['aksi']) && $_POST['aksi'] == 'tambah') {
        callAPI('POST', $api_url, $payload);
    } elseif (isset($_POST['aksi']) && $_POST['aksi'] == 'edit') {
        // Catatan: Beberapa API PHP native menggunakan POST untuk update, 
        // Jika API kamu RESTful standar, gunakan 'PUT'.
        callAPI('PUT', $api_url, $payload); 
    }
    
    // Redirect untuk mencegah resubmission form
    header("Location: index.php");
    exit;
}

// Handle Delete (DELETE)
if (isset($_GET['hapus'])) {
    $nim = $_GET['hapus'];
    // Asumsi API menerima NIM via parameter URL untuk method DELETE
    callAPI('DELETE', $api_url . "?nim=" . $nim);
    header("Location: index.php");
    exit;
}

// ==========================================
// 3. LOGIC READ (GET)
// ==========================================
// Ambil semua data mahasiswa untuk ditampilkan di tabel
$response_data = callAPI('GET', $api_url);
$mahasiswa_list = is_array($response_data) ? $response_data : [];

// Variabel untuk menampung data yang akan diedit (jika mode edit aktif)
$edit_mode = false;
$edit_data = ['nim' => '', 'nama' => '', 'id_prodi' => ''];

if (isset($_GET['edit'])) {
    $edit_mode = true;
    $nim_edit = $_GET['edit'];
    // Ambil data spesifik. Jika API mendukung GET by ID:
    // $edit_data = callAPI('GET', $api_url . "?nim=" . $nim_edit);
    
    // Alternatif (Pencarian lokal dari list yang sudah ditarik):
    foreach ($mahasiswa_list as $mhs) {
        if ($mhs['nim'] == $nim_edit) {
            $edit_data = $mhs;
            break;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRUD Mahasiswa - API Client</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-5">
    <div class="row">
        <div class="col-md-4 mb-4">
            <div class="card shadow-sm">
                <div class="card-header <?= $edit_mode ? 'bg-warning' : 'bg-primary text-white' ?>">
                    <h5 class="card-title mb-0">
                        <?= $edit_mode ? 'Edit Data Mahasiswa' : 'Tambah Mahasiswa Baru' ?>
                    </h5>
                </div>
                <div class="card-body">
                    <form action="index.php" method="POST">
                        <input type="hidden" name="aksi" value="<?= $edit_mode ? 'edit' : 'tambah' ?>">
                        
                        <div class="mb-3">
                            <label for="nim" class="form-label">NIM</label>
                            <input type="number" class="form-control" id="nim" name="nim" 
                                   value="<?= htmlspecialchars($edit_data['nim']) ?>" 
                                   <?= $edit_mode ? 'readonly' : 'required' ?>>
                            <?php if($edit_mode): ?>
                                <small class="text-muted">NIM tidak dapat diubah saat mode edit.</small>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <label for="nama" class="form-label">Nama Lengkap</label>
                            <input type="text" class="form-control" id="nama" name="nama" 
                                   value="<?= htmlspecialchars($edit_data['nama']) ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="id_prodi" class="form-label">ID Prodi</label>
                            <input type="number" class="form-control" id="id_prodi" name="id_prodi" 
                                   value="<?= htmlspecialchars($edit_data['id_prodi']) ?>" required>
                        </div>
                        
                        <button type="submit" class="btn <?= $edit_mode ? 'btn-warning' : 'btn-primary' ?> w-100">
                            <?= $edit_mode ? 'Update Data' : 'Simpan Data' ?>
                        </button>
                        
                        <?php if($edit_mode): ?>
                            <a href="index.php" class="btn btn-secondary w-100 mt-2">Batal</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-dark text-white">
                    <h5 class="card-title mb-0">Daftar Mahasiswa</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered mb-0">
                            <thead class="table-secondary">
                                <tr>
                                    <th class="text-center">No</th>
                                    <th>NIM</th>
                                    <th>Nama</th>
                                    <th class="text-center">ID Prodi</th>
                                    <th class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($mahasiswa_list)): ?>
                                    <?php $no = 1; foreach ($mahasiswa_list as $row): ?>
                                    <tr>
                                        <td class="text-center align-middle"><?= $no++ ?></td>
                                        <td class="align-middle"><?= htmlspecialchars($row['nim'] ?? '') ?></td>
                                        <td class="align-middle"><?= htmlspecialchars($row['nama'] ?? '') ?></td>
                                        <td class="text-center align-middle"><?= htmlspecialchars($row['id_prodi'] ?? '') ?></td>
                                        <td class="text-center align-middle">
                                            <a href="index.php?edit=<?= $row['nim'] ?>" class="btn btn-sm btn-warning">Edit</a>
                                            <a href="index.php?hapus=<?= $row['nim'] ?>" 
                                               class="btn btn-sm btn-danger" 
                                               onclick="return confirm('Apakah kamu yakin ingin menghapus data ini?');">
                                               Hapus
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>z
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-3">Tidak ada data ditemukan.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>