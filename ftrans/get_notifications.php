<?php
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['user_role'] ?? 'user';

// 1. Handle Mark as Read via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $notif_id = intval($_POST['id'] ?? 0);

    if ($action === 'read' && $notif_id > 0) {
        if ($role === 'admin') {
            $stmt = mysqli_prepare($mysqli, "UPDATE notifications SET is_read = 1 WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'i', $notif_id);
        } else {
            $stmt = mysqli_prepare($mysqli, "UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
            mysqli_stmt_bind_param($stmt, 'ii', $notif_id, $user_id);
        }
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        echo json_encode(['success' => true]);
        exit;
    }
    
    if ($action === 'read_all') {
        if ($role === 'admin') {
            mysqli_query($mysqli, "UPDATE notifications SET is_read = 1 WHERE user_id IS NULL OR user_id = {$user_id}");
        } else {
            mysqli_query($mysqli, "UPDATE notifications SET is_read = 1 WHERE user_id = {$user_id}");
        }
        echo json_encode(['success' => true]);
        exit;
    }
}

// 2. Handle GET Fetch notifications
$query = "";
if ($role === 'admin') {
    $query = "SELECT id, title, message, created_at FROM notifications WHERE (user_id IS NULL OR user_id = ?) AND is_read = 0 ORDER BY id DESC LIMIT 10";
} else {
    $query = "SELECT id, title, message, created_at FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY id DESC LIMIT 10";
}

$stmt = mysqli_prepare($mysqli, $query);
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$notifs = mysqli_fetch_all($res, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

echo json_encode(['notifications' => $notifs]);
exit;
