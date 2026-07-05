<?php
// Load environment and DB configuration if not already loaded
require_once 'config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once 'vendor/autoload.php';

/**
 * Sends a premium invoice or receipt email with a dynamic mPDF-generated attachment
 * using SMTP settings configured in the .env file.
 * 
 * @param int $id_sewa Rental Transaction ID
 * @param string $email_type Type of email: 'invoice' (tagihan) or 'receipt' (lunas)
 * @return bool Returns true on successful transmission
 * @throws Exception
 */
function send_invoice_email($id_sewa, $email_type = 'invoice') {
    global $mysqli;
    
    // Fetch transaction details
    $stmt = mysqli_prepare($mysqli, "
        SELECT p.*, u.nama AS nama_user, u.email AS email_user, k.nama_kendaraan, k.harga_per_hari, m.nama_merk
        FROM penyewaan p
        JOIN users u ON p.id_user = u.id
        JOIN kendaraan k ON p.kode_unik_kendaraan = k.kode_unik_kendaraan
        LEFT JOIN merk_kendaraan m ON k.id_merk = m.id_merk
        WHERE p.id_sewa = ?
    ");
    mysqli_stmt_bind_param($stmt, 'i', $id_sewa);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $rental = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);
    
    if (!$rental) {
        throw new Exception("Transaksi penyewaan dengan ID #{$id_sewa} tidak ditemukan di database.");
    }
    
    $t_sewa = strtotime($rental['tanggal_sewa']);
    $t_kembali = strtotime($rental['tanggal_kembali']);
    $diff_days = ceil(($t_kembali - $t_sewa) / 86400);
    if ($diff_days <= 0) $diff_days = 1;
    
    // ---------------------------------------------------------
    // 1. RENDER PDF INVOICE/RECEIPT WITH mPDF
    // ---------------------------------------------------------
    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'margin_left' => 15,
        'margin_right' => 15,
        'margin_top' => 15,
        'margin_bottom' => 15,
    ]);
    
    $pdf_title = ($email_type === 'invoice') ? 'FAKTUR / INVOICE TAGIHAN' : 'KUITANSI / BUKTI PEMBAYARAN';
    $status_label = ($email_type === 'invoice') ? 'BELUM BAYAR (Booking)' : 'LUNAS (Verified)';
    $status_color = ($email_type === 'invoice') ? '#DC2626' : '#16A34A';
    
    $pdf_html = '
    <style>
        body { font-family: "Segoe UI", Helvetica, Arial, sans-serif; color: #1E293B; font-size: 10.5pt; line-height: 1.5; }
        .header-table { width: 100%; border-bottom: 2px solid #3b82f6; margin-bottom: 25px; padding-bottom: 10px; }
        .logo-text { font-size: 24pt; font-weight: bold; color: #3B82F6; }
        .comp-title { font-size: 12pt; font-weight: bold; color: #0F172A; text-align: right; }
        .comp-sub { font-size: 8.5pt; color: #64748B; text-align: right; }
        .content { padding: 10px 0; }
        .invoice-title { font-size: 16pt; font-weight: bold; color: #0F172A; margin-bottom: 5px; }
        .details-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .details-table td { padding: 10px 8px; border-bottom: 1px solid #E2E8F0; }
        .details-table td.label { font-weight: bold; width: 35%; color: #475569; }
        .total-row { background-color: #F8FAFC; border-top: 2px solid #3B82F6; font-size: 12pt; font-weight: bold; }
        .total-val { color: #3B82F6; }
        .footer-note { margin-top: 60px; text-align: center; font-size: 8.5pt; color: #64748b; border-top: 1px solid #E2E8F0; padding-top: 15px; }
    </style>
    <table class="header-table">
        <tr>
            <td class="logo-text">FTrans</td>
            <td class="comp-title">FTRANS CAR RENTAL
                <div class="comp-sub">Kenyamanan Perjalanan Anda, Prioritas Kami</div>
            </td>
        </tr>
    </table>
    <div class="content">
        <div class="invoice-title">' . $pdf_title . '</div>
        <div style="margin-bottom: 20px; font-size: 10pt;">
            <span>No Invoice: <strong>#INV-' . str_pad($id_sewa, 5, '0', STR_PAD_LEFT) . '</strong></span> | 
            <span>Status Transaksi: <strong style="color: ' . $status_color . ';">' . $status_label . '</strong></span>
        </div>
        
        <table class="details-table">
            <tr><td class="label">Penyewa:</td><td>' . htmlspecialchars($rental['nama_user']) . ' (' . htmlspecialchars($rental['email_user']) . ')</td></tr>
            <tr><td class="label">Kendaraan Rented:</td><td>' . htmlspecialchars($rental['nama_kendaraan']) . ' (' . htmlspecialchars(ucwords($rental['nama_merk'] ?? '')) . ')</td></tr>
            <tr><td class="label">Tarif Sewa / Hari:</td><td>Rp ' . number_format($rental['harga_per_hari'], 0, ',', '.') . '</td></tr>
            <tr><td class="label">Periode Sewa:</td><td>' . date('d M Y H:i', $t_sewa) . ' s.d. ' . date('d M Y H:i', $t_kembali) . '</td></tr>
            <tr><td class="label">Durasi:</td><td>' . $diff_days . ' Hari</td></tr>
            <tr class="total-row"><td class="label" style="padding: 12px 8px;">Total Biaya:</td><td class="total-val" style="padding: 12px 8px;">Rp ' . number_format($rental['total_biaya'], 0, ',', '.') . '</td></tr>
        </table>
    </div>
    <div class="footer-note">
        &copy; ' . date('Y') . ' FTrans Car Rental. Dokumen Elektronik Resmi Sah.
    </div>
    ';
    
    $mpdf->WriteHTML($pdf_html);
    
    // ---------------------------------------------------------
    // 2. OUTPUT MPDF TO STRING VARIABLE ('S')
    // ---------------------------------------------------------
    $pdf_string = $mpdf->Output('', 'S');
    
    // ---------------------------------------------------------
    // 3. CONFIGURE PHPMailer WITH SMTP SETTINGS
    // ---------------------------------------------------------
    $mail = new PHPMailer(true);
    $mail->SMTPDebug  = 0;
    
    $mail->isSMTP();
    $mail->Host       = $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = $_ENV['SMTP_USER'] ?? '';
    $mail->Password   = $_ENV['SMTP_PASS'] ?? '';
    
    $port = intval($_ENV['SMTP_PORT'] ?? 465);
    $mail->Port       = $port;
    
    if ($port === 465) {
        $mail->SMTPSecure = 'ssl';
    } else {
        $mail->SMTPSecure = 'tls';
    }
    
    // Timeout and debug settings
    $mail->Timeout    = 10;
    $mail->CharSet    = 'UTF-8';
    
    // Sender and recipient
    $mail->setFrom($_ENV['SMTP_USER'] ?? 'noreply@ftrans.com', 'FTrans Car Rental');
    $mail->addAddress($rental['email_user'], $rental['nama_user']);
    
    // ---------------------------------------------------------
    // 4. ATTACH THE PDF STRING TO EMAIL
    // ---------------------------------------------------------
    $pdf_filename = ($email_type === 'invoice') ? 'Invoice_Transaksi.pdf' : 'Kuitansi_Pembayaran.pdf';
    $mail->addStringAttachment($pdf_string, $pdf_filename);
    
    // ---------------------------------------------------------
    // 5. BUILD EMAIL MESSAGE BODY & SEND
    // ---------------------------------------------------------
    $mail->isHTML(true);
    $mail->Subject = ($email_type === 'invoice') 
        ? 'Tagihan Pembayaran Penyewaan #' . str_pad($id_sewa, 5, '0', STR_PAD_LEFT) 
        : 'Konfirmasi Pembayaran Lunas #' . str_pad($id_sewa, 5, '0', STR_PAD_LEFT);
    
    if ($email_type === 'invoice') {
        $payment_url = "http://localhost/PemrogramanWeb_1/project_baru/bayar.php?id=" . $id_sewa;
        $email_content = '
        <div style="font-family: \'Segoe UI\', Helvetica, Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 12px; background-color: #ffffff;">
            <h2 style="color: #3b82f6; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; margin-top: 0;">Tagihan Pembayaran Penyewaan Kendaraan</h2>
            <p>Halo <strong>' . htmlspecialchars($rental['nama_user']) . '</strong>,</p>
            <p>Terima kasih telah melakukan pemesanan kendaraan di FTrans. Berikut rincian singkat tagihan Anda:</p>
            <ul>
                <li><strong>Nomor Invoice:</strong> #INV-' . str_pad($id_sewa, 5, '0', STR_PAD_LEFT) . '</li>
                <li><strong>Kendaraan:</strong> ' . htmlspecialchars($rental['nama_kendaraan']) . '</li>
                <li><strong>Periode Sewa:</strong> ' . date('d F Y', $t_sewa) . ' - ' . date('d F Y', $t_kembali) . ' (' . $diff_days . ' hari)</li>
                <li><strong>Total Tagihan:</strong> <strong style="color: #3b82f6;">Rp ' . number_format($rental['total_biaya'], 0, ',', '.') . '</strong></li>
            </ul>
            <p>Rincian invoice resmi telah kami lampirkan dalam format PDF (Invoice_Transaksi.pdf) pada email ini.</p>
            
            <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; padding: 20px; border-radius: 8px; margin: 20px 0; text-align: center;">
                <h4 style="margin-top: 0; color: #0F172A; font-weight: bold;">💳 Instruksi Pembayaran:</h4>
                <p style="margin-bottom: 5px;">Lakukan transfer pas ke rekening resmi kami berikut:</p>
                <p style="font-size: 18px; font-weight: bold; color: #3b82f6; margin: 5px 0;">BANK BCA: 123456789</p>
                <p style="margin: 0; font-size: 13px; color: #64748B;">Atas Nama: FTrans Car Rental</p>
                <p style="margin-top: 15px;"><a href="' . $payment_url . '" style="display: inline-block; background-color: #3b82f6; color: #ffffff; padding: 10px 20px; text-decoration: none; border-radius: 6px; font-weight: bold;">Bayar & Unggah Bukti</a></p>
            </div>
            
            <p>Salam hangat,<br><strong>Tim FTrans Car Rental</strong></p>
        </div>
        ';
    } else {
        $email_content = '
        <div style="font-family: \'Segoe UI\', Helvetica, Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 12px; background-color: #ffffff;">
            <h2 style="color: #16a34a; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; margin-top: 0;">Konfirmasi Pembayaran Lunas</h2>
            <p>Halo <strong>' . htmlspecialchars($rental['nama_user']) . '</strong>,</p>
            <p>Kabar gembira! Pembayaran Anda untuk transaksi sewa kendaraan <strong>#INV-' . str_pad($id_sewa, 5, '0', STR_PAD_LEFT) . '</strong> telah berhasil **diverifikasi dan dinyatakan LUNAS**.</p>
            <p>Status sewa Anda saat ini adalah: <strong style="color: #16a34a;">Sedang Disewa (Aktif)</strong>.</p>
            <ul>
                <li><strong>Kendaraan:</strong> ' . htmlspecialchars($rental['nama_kendaraan']) . '</li>
                <li><strong>Periode Sewa:</strong> ' . date('d F Y', $t_sewa) . ' - ' . date('d F Y', $t_kembali) . '</li>
                <li><strong>Total Biaya:</strong> Rp ' . number_format($rental['total_biaya'], 0, ',', '.') . ' (LUNAS)</li>
            </ul>
            <p>Kuitansi pembayaran resmi telah kami lampirkan dalam format PDF (Kuitansi_Pembayaran.pdf) pada email ini.</p>
            <p>Terima kasih telah mempercayakan perjalanan Anda bersama FTrans. Semoga perjalanan Anda menyenangkan, aman, dan nyaman.</p>
            <br>
            <p>Salam hangat,<br><strong>Tim FTrans Car Rental</strong></p>
        </div>
        ';
    }
    
    $mail->Body = $email_content;
    
    $mail->send();
    return true;
}
