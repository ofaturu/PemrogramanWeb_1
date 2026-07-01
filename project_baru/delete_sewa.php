<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$id_sewa = trim($_GET['id'] ?? '');

if (!empty($id_sewa)) {
    // If not admin, check if the rental belongs to this user
    if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
        $check = mysqli_prepare($mysqli, "SELECT id_user FROM penyewaan WHERE id_sewa = ?");
        mysqli_stmt_bind_param($check, 'i', $id_sewa);
        mysqli_stmt_execute($check);
        mysqli_stmt_bind_result($check, $owner_id);
        mysqli_stmt_fetch($check);
        mysqli_stmt_close($check);
        
        if ($owner_id != $_SESSION['user_id']) {
            header('Location: sewa.php?error=unauthorized');
            exit;
        }
    }

    $stmt = mysqli_prepare($mysqli, "DELETE FROM penyewaan WHERE id_sewa = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id_sewa);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

header('Location: sewa.php?msg=deleted');
exit;
?>