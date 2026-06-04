<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$id_sewa = trim($_GET['id'] ?? '');

if (!empty($id_sewa)) {
    $stmt = mysqli_prepare($mysqli, "DELETE FROM penyewaan WHERE id_sewa = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id_sewa);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

header('Location: sewa.php?msg=deleted');
exit;
?>
