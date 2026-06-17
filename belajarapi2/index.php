<?php
session_start(); // Memulai session untuk flash message

$api_url = "http://172.16.9.36/belajarrelasi/api.php";

// Fungsi cURL yang disempurnakan
function callAPI($method, $url, $data = false) {
    $curl = curl_init();
    
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($curl, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    
    if ($data) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($curl);
    $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    
    // Fallback jika API tidak mengembalikan JSON yang valid
    $decoded = json_decode($response, true);
    if (!$decoded) {
        return ["status" => "error", "message" => "HTTP Code $httpcode. API Response tidak valid: $response"];
    }
    return $decoded;
}

// ==========================================
// LOGIC ACTION (POST, PUT, DELETE)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = [
        "nim" => (int)$_POST['nim'],
        "nama" => $_POST['nama'],
        "id_prodi" => (int)$_POST['id_prodi']
    ];

    if (isset($_POST['aksi']) && $_POST['aksi'] == 'tambah') {
        $_SESSION['flash'] = callAPI('POST', $api_url, $payload);
    } elseif (isset($_POST['aksi']) && $_POST['aksi'] == 'edit') {
        $_SESSION['flash'] = callAPI('PUT', $api_url, $payload); 
    }
    
    header("Location: index.php");
    exit;
}

if (isset($_GET['hapus'])) {
    $nim = (int)$_GET['hapus'];
    // Kirim data DELETE via JSON Body, bukan parameter URL
    $_SESSION['flash'] = callAPI('DELETE', $api_url, ["nim" => $nim]);
    header("Location: index.php");
    exit;
}

// ==========================================
// LOGIC READ (GET)
// ==========================================
$response_data = callAPI('GET', $api_url);
$mahasiswa_list = (isset($response_data['status']) && $response_data['status'] === 'error') ? [] : $response_data;

$edit_mode = false;
$edit_data = ['nim' => '', 'nama' => '', 'id_prodi' => ''];

if (isset($_GET['edit'])) {
    $edit_mode = true;
    $nim_edit = $_GET['edit'];
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
    <?php if (isset($_SESSION['flash'])): ?>
        <div class="alert alert-<?= ($_SESSION['flash']['status'] ?? 'error') === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show shadow-sm" role="alert">
            <strong><?= ($_SESSION['flash']['status'] ?? '') === 'success' ? 'Berhasil!' : 'Gagal!' ?></strong> 
            <?= htmlspecialchars($_SESSION['flash']['message'] ?? 'Terjadi kesalahan tidak dikenal.') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['flash']); ?>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-4 mb-4">
            <div class="card shadow-sm">
                <div class="card-header <?= $edit_mode ? 'bg-warning' : 'bg-primary text-white' ?>">
                    <h5 class="card-title mb-0">
                        <?= $edit_mode ? 'Edit Data Mahasiswa' : 'Tambah Mahasiswa' ?>
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
                                    <th class="text-center">NIM</th>
                                    <th>Nama</th>
                                    <th class="text-center">ID Prodi</th>
                                    <th class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($mahasiswa_list) && is_array($mahasiswa_list)): ?>
                                    <?php foreach ($mahasiswa_list as $row): ?>
                                    <tr>
                                        <td class="text-center align-middle"><?= htmlspecialchars($row['nim'] ?? '') ?></td>
                                        <td class="align-middle"><?= htmlspecialchars($row['nama'] ?? '') ?></td>
                                        <td class="text-center align-middle"><?= htmlspecialchars($row['id_prodi'] ?? '') ?></td>
                                        <td class="text-center align-middle">
                                            <a href="index.php?edit=<?= $row['nim'] ?>" class="btn btn-sm btn-warning">Edit</a>
                                            <a href="index.php?hapus=<?= $row['nim'] ?>" 
                                               class="btn btn-sm btn-danger" 
                                               onclick="return confirm('Hapus data NIM <?= $row['nim'] ?>?');">
                                               Hapus
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" class="text-center text-muted py-3">Tidak ada data.</td></tr>
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