<?php
require_once 'config.php';

// Log webhook request for debugging
$raw_payload = file_get_contents('php://input');
error_log("Xendit Webhook Raw Payload: " . $raw_payload);

$data = json_decode($raw_payload, true);

if (!$data) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'Invalid payload']);
    exit;
}

// Check callback token if set (optional security check)
// $callback_token = $_ENV['XENDIT_CALLBACK_TOKEN'] ?? getenv('XENDIT_CALLBACK_TOKEN') ?? '';

$external_id = $data['external_id'] ?? '';
$status = $data['status'] ?? '';
$amount = $data['amount'] ?? 0;
$payment_method = $data['payment_method'] ?? 'XENDIT';

// Parse external_id (e.g. "sewa-12-1688582232")
if (preg_match('/^sewa-(\d+)-/', $external_id, $matches)) {
    $id_sewa = intval($matches[1]);
} else {
    // If not matching pattern, try numeric check
    $id_sewa = intval(str_replace('sewa-', '', $external_id));
}

if ($id_sewa > 0 && $status === 'PAID') {
    // Fetch rental details
    $stmt = mysqli_prepare($mysqli, "SELECT p.id_user, p.kode_unik_kendaraan, p.status, u.nama AS user_name, k.nama_kendaraan FROM penyewaan p JOIN users u ON p.id_user = u.id JOIN kendaraan k ON p.kode_unik_kendaraan = k.kode_unik_kendaraan WHERE p.id_sewa = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id_sewa);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $rental = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    if ($rental && $rental['status'] === 'booking') {
        // Update transaction status to 'sedang_disewa'
        $upd = mysqli_prepare($mysqli, "UPDATE penyewaan SET status = 'sedang_disewa', waktu_bayar = NOW() WHERE id_sewa = ?");
        mysqli_stmt_bind_param($upd, 'i', $id_sewa);
        
        if (mysqli_stmt_execute($upd)) {
            mysqli_stmt_close($upd);

            // Update vehicle status to 'disewa'
            mysqli_query($mysqli, "UPDATE kendaraan SET status_kendaraan = 'disewa' WHERE kode_unik_kendaraan = " . intval($rental['kode_unik_kendaraan']));

            // Send notification
            add_notification($rental['id_user'], "Pembayaran Xendit Sukses", "Pembayaran Anda via {$payment_method} sebesar Rp " . number_format($amount, 0, ',', '.') . " untuk sewa {$rental['nama_kendaraan']} berhasil diterima.");
            add_notification(null, "Pembayaran Masuk (Xendit)", "Pembayaran penyewaan #INV-{$id_sewa} oleh {$rental['user_name']} sebesar Rp " . number_format($amount, 0, ',', '.') . " telah sukses dibayar via Xendit.");

            // Send dynamic receipt email via PHPMailer
            require_once 'send_invoice.php';
            try {
                send_invoice_email($id_sewa, 'receipt');
            } catch (\Exception $e) {
                error_log("Failed to send SMTP receipt email from Xendit webhook: " . $e->getMessage());
            }

            echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
            exit;
        } else {
            mysqli_stmt_close($upd);
            header('HTTP/1.1 500 Internal Server Error');
            echo json_encode(['error' => 'Database update failed']);
            exit;
        }
    } else {
        echo json_encode(['success' => true, 'message' => 'Transaction already processed or not found']);
        exit;
    }
}

header('HTTP/1.1 200 OK');
echo json_encode(['success' => true, 'message' => 'No action taken']);
exit;
