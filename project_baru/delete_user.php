<?php
require_once 'config.php';

// Secure the page - only admins can access this script
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$id_user = trim($_GET['id'] ?? '');

if (!empty($id_user)) {
    // Prevent admin from deleting themselves
    if ($id_user == $_SESSION['user_id']) {
        header('Location: users.php?error=self_delete');
        exit;
    }
    
    // Execute delete query. rentals (penyewaan) will be cascade-deleted automatically due to DB foreign keys.
    $stmt = mysqli_prepare($mysqli, "DELETE FROM users WHERE id = ?");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $id_user);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    
    header('Location: users.php?msg=deleted');
    exit;
} else {
    header('Location: users.php');
    exit;
}
?>
