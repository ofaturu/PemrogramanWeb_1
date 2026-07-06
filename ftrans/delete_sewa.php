<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$id_sewa = trim($_GET['id'] ?? '');

if (!empty($id_sewa)) {
    // If not admin, check if the rental belongs to this user and is unpaid/editable
    if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
        $check = mysqli_prepare($mysqli, "SELECT id_user, status, bukti_pembayaran FROM penyewaan WHERE id_sewa = ?");
        mysqli_stmt_bind_param($check, 'i', $id_sewa);
        mysqli_stmt_execute($check);
        mysqli_stmt_bind_result($check, $owner_id, $db_status, $db_bukti);
        mysqli_stmt_fetch($check);
        mysqli_stmt_close($check);
        
        if ($owner_id != $_SESSION['user_id']) {
            header('Location: sewa.php?error=unauthorized');
            exit;
        }

        if (!empty($db_bukti) || $db_status !== 'booking') {
            header('Location: sewa.php?error=cannot_delete_paid');
            exit;
        }
    }

    // Get vehicle code before deleting
    $v_stmt = mysqli_prepare($mysqli, "SELECT kode_unik_kendaraan FROM penyewaan WHERE id_sewa = ?");
    mysqli_stmt_bind_param($v_stmt, 'i', $id_sewa);
    mysqli_stmt_execute($v_stmt);
    mysqli_stmt_bind_result($v_stmt, $kode_unik_k);
    mysqli_stmt_fetch($v_stmt);
    mysqli_stmt_close($v_stmt);

    $stmt = mysqli_prepare($mysqli, "DELETE FROM penyewaan WHERE id_sewa = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id_sewa);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if (!empty($kode_unik_k)) {
        mysqli_query($mysqli, "UPDATE kendaraan SET status_kendaraan = 'tersedia' WHERE kode_unik_kendaraan = " . intval($kode_unik_k));
    }
}

header('Location: sewa.php?msg=deleted');
exit;
?>