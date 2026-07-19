<?php
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$id_sewa = intval($_GET['id'] ?? 0);
if ($id_sewa <= 0) {
    echo json_encode(['error' => 'Invalid ID']);
    exit;
}

// Fetch rental status
$stmt = mysqli_prepare($mysqli, "SELECT status, id_user FROM penyewaan WHERE id_sewa = ?");
mysqli_stmt_bind_param($stmt, 'i', $id_sewa);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$rental = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$rental) {
    echo json_encode(['error' => 'Rental not found']);
    exit;
}

// Verify ownership (Admin or rental owner)
if (($_SESSION['user_role'] ?? 'user') !== 'admin' && $rental['id_user'] != $_SESSION['user_id']) {
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

$status = $rental['status'];
$is_verified = ($status === 'sedang_disewa' || $status === 'selesai');

echo json_encode([
    'id_sewa'     => $id_sewa,
    'status'      => $status,
    'is_verified' => $is_verified
]);
exit;
