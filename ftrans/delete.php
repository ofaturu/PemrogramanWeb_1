<?php
require_once 'config.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$kode = trim($_GET['kode'] ?? '');

if (!empty($kode)) {
    // Ambil data gambarnya dulu buat dihapus
    $stmt = mysqli_prepare($mysqli, "SELECT gambar FROM kendaraan WHERE kode_unik_kendaraan = ?");
    mysqli_stmt_bind_param($stmt, 's', $kode);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $kendaraan = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    // Hapus dari database
    $del = mysqli_prepare($mysqli, "DELETE FROM kendaraan WHERE kode_unik_kendaraan = ?");
    mysqli_stmt_bind_param($del, 's', $kode);
    mysqli_stmt_execute($del);
    mysqli_stmt_close($del);
    
    // Hapus file fisik jika ada
    if ($kendaraan && !empty($kendaraan['gambar']) && file_exists('uploads/' . $kendaraan['gambar'])) {
        unlink('uploads/' . $kendaraan['gambar']);
    }
    
    header('Location: dashboard.php?msg=deleted');
    exit;
} else {
    header('Location: dashboard.php');
    exit;
}
?>