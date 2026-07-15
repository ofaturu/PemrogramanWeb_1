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
    
    $harga_per_hari = intval($rental['harga_per_hari'] ?? 0);
    $original_total = $diff_days * $harga_per_hari;
    $sewa_cost = intval($rental['total_biaya'] ?? 0);
    $diskon = $original_total - $sewa_cost;
    if ($diskon < 0) $diskon = 0;
    $diskon_pct = ($original_total > 0) ? round(($diskon / $original_total) * 100) : 0;
    
    $denda = intval($rental['denda'] ?? 0);
    $total_bayar = $sewa_cost + $denda;

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
                <div style="font-size: 7.5pt; color: #64748B; text-align: right; font-weight: normal; margin-top: 3px;">
                    Jl. Premium Luxury No. 7, Surakarta &bull; Telp: +62 821 8888 9999 &bull; Email: support@ftrans.com
                </div>
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
            ' . ($diskon > 0 ? '
            <tr><td class="label">Subtotal Sewa:</td><td>Rp ' . number_format($original_total, 0, ',', '.') . '</td></tr>
            <tr><td class="label" style="color: #16A34A;">Diskon Member (' . $diskon_pct . '%):</td><td style="color: #16A34A; font-weight: bold;">- Rp ' . number_format($diskon, 0, ',', '.') . '</td></tr>
            ' : '') . '
            ' . ($denda > 0 ? '
            ' . ($diskon > 0 ? '<tr><td class="label">Total Sewa:</td><td>Rp ' . number_format($sewa_cost, 0, ',', '.') . '</td></tr>' : '') . '
            <tr><td class="label" style="color: #DC2626;">Denda Keterlambatan:</td><td style="color: #DC2626; font-weight: bold;">Rp ' . number_format($denda, 0, ',', '.') . '</td></tr>
            ' : '') . '
            <tr class="total-row"><td class="label" style="padding: 12px 8px;">Total Pembayaran:</td><td class="total-val" style="padding: 12px 8px;">Rp ' . number_format($total_bayar, 0, ',', '.') . '</td></tr>
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
    
    // Send a BCC to all admin users so they get notified of booking/receipts
    $admin_res = mysqli_query($mysqli, "SELECT email, nama FROM users WHERE role = 'admin'");
    if ($admin_res) {
        while ($admin = mysqli_fetch_assoc($admin_res)) {
            $mail->addBCC($admin['email'], $admin['nama']);
        }
    }
    
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
        $payment_url = "http://localhost/PemrogramanWeb_1/ftrans/bayar.php?id=" . $id_sewa;
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
        $status_rental = $rental['status'] ?? 'booking';
        $status_label_text = 'Sedang Disewa (Aktif)';
        $lateness_info = '';
        $review_cta = '';
        
        if ($status_rental === 'selesai') {
            $status_label_text = 'Selesai (Armada Dikembalikan)';
            
            // 1. Bukti Keterlambatan jika ada denda / keterlambatan
            if (intval($rental['denda'] ?? 0) > 0 && !empty($rental['tanggal_kembali_aktual'])) {
                $t_sched = strtotime($rental['tanggal_kembali']);
                $t_act = strtotime($rental['tanggal_kembali_aktual']);
                $late_sec = $t_act - $t_sched;
                $late_days = ceil($late_sec / 86400);
                if ($late_days <= 0) $late_days = 1;
                
                $lateness_info = '
                <div style="background-color: #fef2f2; border: 1px solid #fca5a5; padding: 15px; border-radius: 8px; margin: 15px 0;">
                    <h4 style="margin-top: 0; color: #dc2626; font-weight: bold; font-size: 14px;">⚠️ Detail Keterlambatan Pengembalian:</h4>
                    <table style="width: 100%; font-size: 12px; border-collapse: collapse;">
                        <tr>
                            <td style="padding: 4px 0; color: #475569; width: 45%;">Batas Waktu Kembali:</td>
                            <td style="padding: 4px 0; font-weight: bold; color: #1e293b;">' . date('d F Y, H:i', $t_sched) . '</td>
                        </tr>
                        <tr>
                            <td style="padding: 4px 0; color: #475569;">Kembali Aktual:</td>
                            <td style="padding: 4px 0; font-weight: bold; color: #dc2626;">' . date('d F Y, H:i', $t_act) . '</td>
                        </tr>
                        <tr>
                            <td style="padding: 4px 0; color: #475569;">Durasi Terlambat:</td>
                            <td style="padding: 4px 0; font-weight: bold; color: #dc2626;">' . $late_days . ' Hari</td>
                        </tr>
                        <tr>
                            <td style="padding: 4px 0; color: #475569; border-top: 1px dashed #fca5a5; margin-top: 5px;">Denda Keterlambatan:</td>
                            <td style="padding: 4px 0; font-weight: bold; color: #dc2626; border-top: 1px dashed #fca5a5; margin-top: 5px;">Rp ' . number_format($rental['denda'], 0, ',', '.') . '</td>
                        </tr>
                    </table>
                </div>
                ';
            }
            
            // 2. Link / CTA Ulasan
            $review_url = "http://localhost/PemrogramanWeb_1/ftrans/review.php?id=" . $id_sewa;
            $review_cta = '
            <div style="background-color: #f0fdf4; border: 1px solid #bbf7d0; padding: 20px; border-radius: 8px; margin: 20px 0; text-align: center;">
                <h4 style="margin-top: 0; color: #16a34a; font-weight: bold;">Bagaimana Pengalaman Perjalanan Anda?</h4>
                <p style="font-size: 13px; color: #475569; margin-bottom: 15px;">Kepuasan Anda sangat penting bagi kami. Silakan berikan rating bintang dan ulasan ulasan perjalanan Anda bersama armada premium kami.</p>
                <a href="' . $review_url . '" style="display: inline-block; background-color: #16a34a; color: #ffffff; padding: 10px 20px; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 14px;">Beri Ulasan Sekarang</a>
            </div>
            ';
        }
        
        $email_content = '
        <div style="font-family: \'Segoe UI\', Helvetica, Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 12px; background-color: #ffffff;">
            <h2 style="color: #16a34a; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; margin-top: 0;">' . ($status_rental === 'selesai' ? 'Konfirmasi Pengembalian & Pelunasan' : 'Konfirmasi Pembayaran Lunas') . '</h2>
            <p>Halo <strong>' . htmlspecialchars($rental['nama_user']) . '</strong>,</p>
            <p>' . ($status_rental === 'selesai' ? 'Terima kasih telah mengembalikan armada sewa. Transaksi Anda telah dinyatakan selesai dan lunas.' : 'Kabar gembira! Pembayaran Anda untuk transaksi sewa kendaraan <strong>#INV-' . str_pad($id_sewa, 5, '0', STR_PAD_LEFT) . '</strong> telah berhasil **diverifikasi dan dinyatakan LUNAS**.') . '</p>
            <p>Status sewa Anda saat ini adalah: <strong style="color: #16a34a;">' . $status_label_text . '</strong>.</p>
            
            ' . $lateness_info . '
            
            <ul>
                <li><strong>Kendaraan:</strong> ' . htmlspecialchars($rental['nama_kendaraan']) . '</li>
                <li><strong>Periode Sewa:</strong> ' . date('d F Y', $t_sewa) . ' - ' . date('d F Y', $t_kembali) . '</li>
                ' . ($diskon > 0 ? '<li><strong>Diskon Member (' . $diskon_pct . '%):</strong> - Rp ' . number_format($diskon, 0, ',', '.') . '</li>' : '') . '
                ' . ($denda > 0 ? '<li><strong>Denda Keterlambatan:</strong> <span style="color: #DC2626; font-weight: bold;">Rp ' . number_format($denda, 0, ',', '.') . '</span></li>' : '') . '
                <li><strong>Total Pembayaran:</strong> <strong style="color: #16a34a;">Rp ' . number_format($total_bayar, 0, ',', '.') . '</strong> (LUNAS)</li>
                ' . (!empty($rental['metode_pembayaran']) ? '<li><strong>Metode Pembayaran:</strong> ' . htmlspecialchars($rental['metode_pembayaran']) . '</li>' : '') . '
            </ul>
            
            ' . $review_cta . '
            
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

/**
 * Sends an email notification to all admin users when there is a maintenance expenditure.
 */
function send_expense_notification_email($maintenance_id) {
    global $mysqli;
    
    // Fetch maintenance and vehicle details
    $stmt = mysqli_prepare($mysqli, "
        SELECT m.*, k.nama_kendaraan, k.warna 
        FROM maintenance m
        JOIN kendaraan k ON m.kode_unik_kendaraan = k.kode_unik_kendaraan
        WHERE m.id = ?
    ");
    mysqli_stmt_bind_param($stmt, 'i', $maintenance_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $m = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);
    
    if (!$m) {
        return false;
    }
    
    // Fetch all admin emails
    $admin_res = mysqli_query($mysqli, "SELECT email, nama FROM users WHERE role = 'admin'");
    $admins = mysqli_fetch_all($admin_res, MYSQLI_ASSOC);
    
    if (empty($admins)) {
        return false;
    }
    
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
    
    $mail->Timeout    = 10;
    $mail->CharSet    = 'UTF-8';
    
    $mail->setFrom($_ENV['SMTP_USER'] ?? 'noreply@ftrans.com', 'FTrans System Notification');
    
    // Add all admin email addresses
    foreach ($admins as $admin) {
        $mail->addAddress($admin['email'], $admin['nama']);
    }
    
    $mail->isHTML(true);
    $mail->Subject = 'Pemberitahuan Pengeluaran Servis/Perawatan #' . $maintenance_id;
    
    $mail->Body = '
    <div style="font-family: \'Segoe UI\', Helvetica, Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 12px; background-color: #ffffff;">
        <h2 style="color: #dc2626; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; margin-top: 0;">Laporan Pengeluaran Baru</h2>
        <p>Halo Admin,</p>
        <p>Sistem mencatat adanya pengeluaran baru untuk pemeliharaan/servis unit kendaraan. Berikut adalah detail lengkap pengeluaran:</p>
        <table style="width: 100%; border-collapse: collapse; margin: 15px 0;">
            <tr>
                <td style="padding: 8px; border-bottom: 1px solid #e2e8f0; font-weight: bold; width: 40%;">ID Perawatan:</td>
                <td style="padding: 8px; border-bottom: 1px solid #e2e8f0;">#MNT-' . str_pad($m['id'], 5, '0', STR_PAD_LEFT) . '</td>
            </tr>
            <tr>
                <td style="padding: 8px; border-bottom: 1px solid #e2e8f0; font-weight: bold;">Kendaraan:</td>
                <td style="padding: 8px; border-bottom: 1px solid #e2e8f0;">' . htmlspecialchars($m['nama_kendaraan']) . ' (' . htmlspecialchars($m['warna']) . ')</td>
            </tr>
            <tr>
                <td style="padding: 8px; border-bottom: 1px solid #e2e8f0; font-weight: bold;">Kode Kendaraan:</td>
                <td style="padding: 8px; border-bottom: 1px solid #e2e8f0;">' . htmlspecialchars($m['kode_unik_kendaraan']) . '</td>
            </tr>
            <tr>
                <td style="padding: 8px; border-bottom: 1px solid #e2e8f0; font-weight: bold;">Deskripsi Servis:</td>
                <td style="padding: 8px; border-bottom: 1px solid #e2e8f0;">' . htmlspecialchars($m['deskripsi']) . '</td>
            </tr>
            <tr>
                <td style="padding: 8px; border-bottom: 1px solid #e2e8f0; font-weight: bold;">Tanggal Masuk:</td>
                <td style="padding: 8px; border-bottom: 1px solid #e2e8f0;">' . date('d F Y', strtotime($m['tanggal_mulai'])) . '</td>
            </tr>
            <tr>
                <td style="padding: 8px; border-bottom: 1px solid #e2e8f0; font-weight: bold;">Tanggal Selesai:</td>
                <td style="padding: 8px; border-bottom: 1px solid #e2e8f0;">' . date('d F Y', strtotime($m['tanggal_selesai'])) . '</td>
            </tr>
            <tr style="background-color: #fef2f2;">
                <td style="padding: 8px; font-weight: bold; color: #dc2626;">Biaya Pengeluaran:</td>
                <td style="padding: 8px; font-weight: bold; color: #dc2626;">Rp ' . number_format($m['biaya'], 0, ',', '.') . '</td>
            </tr>
        </table>
        <p>Pencatatan ini telah dimasukkan ke dalam histori pembukuan dan bagan analitik keuangan.</p>
        <br>
        <p>Salam hangat,<br><strong>Sistem Pemantauan FTrans</strong></p>
    </div>
    ';
    
    $mail->send();
    return true;
}

/**
 * Sends a verification OTP email to a user who just registered.
 */
function send_verification_otp_email($email, $nama, $otp) {
    $mail = new PHPMailer(true);
    $mail->SMTPDebug  = 0;
    $mail->isSMTP();
    $mail->Host       = $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = $_ENV['SMTP_USER'] ?? '';
    $mail->Password   = $_ENV['SMTP_PASS'] ?? '';
    $port             = intval($_ENV['SMTP_PORT'] ?? 465);
    $mail->Port       = $port;
    if ($port === 465) {
        $mail->SMTPSecure = 'ssl';
    } else {
        $mail->SMTPSecure = 'tls';
    }
    $mail->Timeout    = 10;
    $mail->CharSet    = 'UTF-8';
    
    $mail->setFrom($_ENV['SMTP_USER'] ?? 'noreply@ftrans.com', 'FTrans Car Rental');
    $mail->addAddress($email, $nama);
    $mail->isHTML(true);
    $mail->Subject = 'Kode Verifikasi OTP Pendaftaran Akun FTrans';
    
    $mail->Body = '
    <div style="font-family: \'Segoe UI\', Helvetica, Arial, sans-serif; max-width: 500px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 12px; background-color: #ffffff;">
        <h2 style="color: #3b82f6; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; margin-top: 0; text-align: center;">Verifikasi Akun FTrans</h2>
        <p>Halo <strong>' . htmlspecialchars($nama) . '</strong>,</p>
        <p>Terima kasih telah mendaftar di FTrans. Silakan gunakan kode OTP di bawah ini untuk memverifikasi akun Anda:</p>
        <div style="text-align: center; margin: 30px 0;">
            <span style="font-size: 32px; font-weight: bold; letter-spacing: 6px; color: #3b82f6; background-color: #f1f5f9; padding: 12px 24px; border-radius: 8px; border: 1px dashed #3b82f6;">' . htmlspecialchars($otp) . '</span>
        </div>
        <p style="color: #dc2626; font-size: 13px; text-align: center;">Kode OTP ini berlaku selama 15 menit. Jangan bagikan kode ini kepada siapa pun.</p>
        <hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0;">
        <p style="font-size: 12px; color: #64748b; text-align: center;">Jika Anda tidak merasa melakukan pendaftaran ini, abaikan email ini.</p>
        <p style="font-size: 12px; color: #64748b; text-align: center; margin-top: 5px;">&copy; ' . date('Y') . ' FTrans Car Rental.</p>
    </div>
    ';
    
    $mail->send();
    return true;
}

/**
 * Sends a password reset OTP email to a user requesting reset.
 */
function send_reset_otp_email($email, $nama, $otp) {
    $mail = new PHPMailer(true);
    $mail->SMTPDebug  = 0;
    $mail->isSMTP();
    $mail->Host       = $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = $_ENV['SMTP_USER'] ?? '';
    $mail->Password   = $_ENV['SMTP_PASS'] ?? '';
    $port             = intval($_ENV['SMTP_PORT'] ?? 465);
    $mail->Port       = $port;
    if ($port === 465) {
        $mail->SMTPSecure = 'ssl';
    } else {
        $mail->SMTPSecure = 'tls';
    }
    $mail->Timeout    = 10;
    $mail->CharSet    = 'UTF-8';
    
    $mail->setFrom($_ENV['SMTP_USER'] ?? 'noreply@ftrans.com', 'FTrans Car Rental');
    $mail->addAddress($email, $nama);
    $mail->isHTML(true);
    $mail->Subject = 'Kode OTP Reset Password FTrans';
    
    $mail->Body = '
    <div style="font-family: \'Segoe UI\', Helvetica, Arial, sans-serif; max-width: 500px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 12px; background-color: #ffffff;">
        <h2 style="color: #dc2626; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; margin-top: 0; text-align: center;">Reset Password FTrans</h2>
        <p>Halo <strong>' . htmlspecialchars($nama) . '</strong>,</p>
        <p>Kami menerima permintaan untuk mengatur ulang kata sandi akun Anda. Silakan gunakan kode OTP di bawah ini untuk melanjutkan:</p>
        <div style="text-align: center; margin: 30px 0;">
            <span style="font-size: 32px; font-weight: bold; letter-spacing: 6px; color: #dc2626; background-color: #fef2f2; padding: 12px 24px; border-radius: 8px; border: 1px dashed #dc2626;">' . htmlspecialchars($otp) . '</span>
        </div>
        <p style="color: #dc2626; font-size: 13px; text-align: center;">Kode OTP ini berlaku selama 15 menit. Jangan bagikan kode ini kepada siapa pun.</p>
        <hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0;">
        <p style="font-size: 12px; color: #64748b; text-align: center;">Jika Anda tidak merasa meminta reset password, abaikan email ini dan segera amankan akun Anda.</p>
        <p style="font-size: 12px; color: #64748b; text-align: center; margin-top: 5px;">&copy; ' . date('Y') . ' FTrans Car Rental.</p>
    </div>
    ';
    
    $mail->send();
    return true;
}
