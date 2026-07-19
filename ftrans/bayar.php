<?php
require_once 'config.php';

// Secure the page - user must be logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$id_sewa = intval($_GET['id'] ?? 0);
if ($id_sewa <= 0) {
    header('Location: sewa.php');
    exit;
}

// Fetch rental details
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
    header('Location: sewa.php');
    exit;
}

// Verify ownership (Non-admins can only see/pay their own bookings)
if (isset($_SESSION['user_role']) && $_SESSION['user_role'] !== 'admin' && $rental['id_user'] != $_SESSION['user_id']) {
    header('Location: sewa.php?error=unauthorized');
    exit;
}

$error = '';
$success = '';

// Check if Xendit Invoice Url is requested and not set
$xendit_api_key = $_ENV['XENDIT_SECRET_KEY'] ?? getenv('XENDIT_SECRET_KEY') ?? '';

// Force local simulation on localhost since Xendit callbacks require a public HTTPS domain
if ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1') {
    $xendit_api_key = '';
}
$is_simulation = empty($xendit_api_key);

// Fetch updated status and payment proof details
$status = $rental['status'] ?? 'booking';
$bukti = $rental['bukti_pembayaran'] ?? '';

// Handle Simulation Payment Success Trigger
if (isset($_GET['simulate_payment']) && $status === 'booking') {
    $dir = str_replace('\\', '/', dirname($_SERVER['PHP_SELF']));
    if ($dir === '/') $dir = '';
    $callback_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . $dir . '/xendit_callback.php';
    
    $payload = [
        'external_id' => 'sewa-' . $id_sewa . '-' . time(),
        'status' => 'PAID',
        'amount' => intval($rental['total_biaya']),
        'payment_method' => 'VA_SIMULATOR'
    ];
    
    // Pass callback token header for security verification check
    $callback_token = $_ENV['XENDIT_CALLBACK_TOKEN'] ?? getenv('XENDIT_CALLBACK_TOKEN') ?? '';
    $curl_headers = ['Content-Type: application/json'];
    if (!empty($callback_token)) {
        $curl_headers[] = 'x-callback-token: ' . $callback_token;
    }
    
    $ch = curl_init($callback_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $curl_headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $res = curl_exec($ch);
    curl_close($ch);
    
    // Refresh page with success state
    header("Location: bayar.php?id={$id_sewa}&xendit_status=success");
    exit;
}

// Generate Xendit Invoice if required
if ($status === 'booking' && empty($bukti) && empty($rental['xendit_invoice_url'])) {
    if (!$is_simulation) {
        $external_id = "sewa-" . $id_sewa . "-" . time();
        $payload = [
            'external_id' => $external_id,
            'amount' => intval($rental['total_biaya']),
            'payer_email' => $rental['email_user'],
            'description' => "Pembayaran Rental Kendaraan #INV-" . str_pad($id_sewa, 5, '0', STR_PAD_LEFT) . " - " . $rental['nama_kendaraan'],
            'success_redirect_url' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . str_replace('\\', '/', dirname($_SERVER['PHP_SELF'])) . "/bayar.php?id=" . $id_sewa . "&xendit_status=success"
        ];

        $ch = curl_init('https://api.xendit.co/v2/invoices');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode($xendit_api_key . ':')
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        
        if (!curl_errno($ch)) {
            $res_data = json_decode($response, true);
            if (isset($res_data['invoice_url'])) {
                $inv_url = $res_data['invoice_url'];
                $inv_id = $res_data['id'];
                
                // Save invoice details to db
                $upd_inv = mysqli_prepare($mysqli, "UPDATE penyewaan SET xendit_invoice_id = ?, xendit_invoice_url = ? WHERE id_sewa = ?");
                mysqli_stmt_bind_param($upd_inv, 'ssi', $inv_id, $inv_url, $id_sewa);
                mysqli_stmt_execute($upd_inv);
                mysqli_stmt_close($upd_inv);
                
                $rental['xendit_invoice_url'] = $inv_url;
                $rental['xendit_invoice_id'] = $inv_id;
            }
        }
        curl_close($ch);
    } else {
        // Fallback simulated payment url
        $rental['xendit_invoice_url'] = "bayar.php?id=" . $id_sewa . "&simulate_payment=1";
    }
}

// Check success status query string
if (isset($_GET['xendit_status']) && $_GET['xendit_status'] === 'success') {
    $success = 'Pembayaran Anda berhasil diproses melalui Xendit!';
}

// Handle upload receipt submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($rental['status'] !== 'booking') {
        header("Location: bayar.php?id=" . $id_sewa);
        exit;
    } elseif (isset($_FILES['bukti_pembayaran']) && $_FILES['bukti_pembayaran']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['bukti_pembayaran']['tmp_name'];
        $file_name = $_FILES['bukti_pembayaran']['name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        $allowed_exts = ['jpg', 'jpeg', 'png'];
        if (!in_array($file_ext, $allowed_exts)) {
            $error = 'Format file tidak diizinkan. Hanya JPG, JPEG, dan PNG.';
        } else {
            // Ensure uploads directory exists
            $upload_dir = __DIR__ . '/uploads';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Generate customized filename: bukti pembayaran_namapenyewa_idsewa
            $sanitized_name = strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $rental['nama_user']));
            $new_filename = 'bukti pembayaran_' . $sanitized_name . '_' . $id_sewa . '.' . $file_ext;
            if (move_uploaded_file($file_tmp, $upload_dir . '/' . $new_filename)) {
                // Update database
                $upd_stmt = mysqli_prepare($mysqli, "UPDATE penyewaan SET bukti_pembayaran = ?, waktu_bayar = NOW() WHERE id_sewa = ?");
                mysqli_stmt_bind_param($upd_stmt, 'si', $new_filename, $id_sewa);
                
                if (mysqli_stmt_execute($upd_stmt)) {
                    $success = 'Bukti pembayaran berhasil diunggah! Menunggu konfirmasi admin.';
                    // Refresh rental data
                    $rental['bukti_pembayaran'] = $new_filename;
                    $rental['waktu_bayar'] = date('Y-m-d H:i:s');
                } else {
                    $error = 'Gagal memperbarui database. Coba lagi.';
                }
                mysqli_stmt_close($upd_stmt);
            } else {
                $error = 'Gagal menyimpan file ke server.';
            }
        }
    } else {
        $error = 'Silakan pilih file bukti pembayaran terlebih dahulu.';
    }
}

// Calculate rental duration
$t_sewa = strtotime($rental['tanggal_sewa']);
$t_kembali = strtotime($rental['tanggal_kembali']);
$diff_days = ceil(($t_kembali - $t_sewa) / 86400);
if ($diff_days <= 0) $diff_days = 1;

$harga_per_hari = intval($rental['harga_per_hari'] ?? 0);
$original_total = $diff_days * $harga_per_hari;
$sewa_cost = intval($rental['total_biaya'] ?? 0);
$diskon = $original_total - $sewa_cost;
if ($diskon < 0) $diskon = 0;
$diskon_pct = ($original_total > 0) ? round(($diskon / $original_total) * 100) : 0;
$denda = intval($rental['denda'] ?? 0);
$total_pembayaran = $sewa_cost + $denda;

// Define active page
$activePage = 'sewa';
?>
<!DOCTYPE html>
<html lang="id">
<?php
$title = 'Pembayaran Invoice #' . str_pad($id_sewa, 5, '0', STR_PAD_LEFT) . ' — FTrans';
include 'partials/head.php';
?>
<body>
  <?php include 'partials/sidebar.php'; ?>
  <div class="wrapper d-flex flex-column min-vh-100">
    <?php 
    $pageTitle = 'Pembayaran';
    include 'partials/header.php'; 
    ?>
    <div class="body flex-grow-1">
      <div class="container-lg px-4">
        
        <div class="mb-4">
          <a href="sewa.php" class="btn btn-outline-secondary btn-sm"><i class="fa fa-arrow-left me-1"></i> Kembali ke Data Penyewaan</a>
        </div>

        <div class="row g-4">
          <!-- Left side: Invoice Details -->
          <div class="col-lg-7">
            <div class="card shadow-sm border-0 mb-4" style="border-radius: 12px;">
              <div class="card-header bg-primary text-white py-3" style="border-top-left-radius: 12px; border-top-right-radius: 12px;">
                <h5 class="mb-0 fw-bold"><i class="fa fa-file-invoice me-2"></i>Rincian Tagihan (Invoice)</h5>
              </div>
              <div class="card-body p-4">
                
                <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
                   <div>
                     <h6 class="text-muted small mb-1">Nomor Invoice</h6>
                     <h5 class="fw-bold mb-0 d-flex align-items-center gap-2">
                       #INV-<?= str_pad($id_sewa, 5, '0', STR_PAD_LEFT) ?>
                       <?php if ($status === 'sedang_disewa' || $status === 'selesai'): ?>
                         <a href="export.php?target=sewa&format=pdf&id=<?= urlencode($id_sewa) ?>" class="btn btn-outline-success btn-sm py-0.5 px-2.5 fs-7 d-inline-flex align-items-center gap-1" target="_blank">
                           <i class="fa fa-print"></i> Cetak Struk
                         </a>
                       <?php endif; ?>
                     </h5>
                   </div>
                  <div>
                    <h6 class="text-muted small mb-1">Status Transaksi</h6>
                    <?php
                    $status = $rental['status'] ?? 'booking';
                    $bukti = $rental['bukti_pembayaran'] ?? '';
                    if ($status === 'booking') {
                        if (empty($bukti)) {
                            echo '<span class="badge bg-danger bg-opacity-10 text-danger px-3 py-2 fw-semibold fs-7 border border-danger border-opacity-25">Belum Dibayar</span>';
                        } else {
                            echo '<span class="badge bg-warning bg-opacity-10 text-warning px-3 py-2 fw-semibold fs-7 border border-warning border-opacity-25">Sedang Diproses</span>';
                        }
                    } elseif ($status === 'sedang_disewa') {
                        echo '<span class="badge bg-success bg-opacity-10 text-success px-3 py-2 fw-semibold fs-7 border border-success border-opacity-25">Berhasil</span>';
                    } elseif ($status === 'selesai') {
                        echo '<span class="badge bg-secondary bg-opacity-10 text-secondary px-3 py-2 fw-semibold fs-7 border border-secondary border-opacity-25">Selesai</span>';
                    } elseif ($status === 'dibatalkan') {
                        echo '<span class="badge bg-dark bg-opacity-10 text-dark px-3 py-2 fw-semibold fs-7 border border-dark border-opacity-25">Dibatalkan</span>';
                    }
                    ?>
                  </div>
                </div>

                <div class="row g-3">
                  <div class="col-sm-6">
                    <span class="text-muted small d-block">Nama Penyewa</span>
                    <span class="fw-bold text-body-emphasis"><?= htmlspecialchars($rental['nama_user']) ?></span>
                  </div>
                  <div class="col-sm-6">
                    <span class="text-muted small d-block">Alamat Email</span>
                    <span class="text-body-emphasis"><?= htmlspecialchars($rental['email_user']) ?></span>
                  </div>
                  <div class="col-sm-6 mt-3">
                    <span class="text-muted small d-block">Nama Kendaraan</span>
                    <span class="fw-bold text-body-emphasis"><?= htmlspecialchars($rental['nama_kendaraan']) ?> (<?= htmlspecialchars(ucwords($rental['nama_merk'] ?? '')) ?>)</span>
                  </div>
                  <div class="col-sm-6 mt-3">
                    <span class="text-muted small d-block">Tarif Sewa / Hari</span>
                    <span class="fw-bold text-body-emphasis">Rp <?= number_format($rental['harga_per_hari'], 0, ',', '.') ?></span>
                  </div>
                  <div class="col-12 mt-3 border-top pt-3">
                    <span class="text-muted small d-block">Periode Penyewaan</span>
                    <span class="text-body-emphasis fw-semibold">
                      <i class="fa fa-calendar-alt me-1 text-primary"></i> 
                      <?= date('d F Y, H:i', $t_sewa) ?> s.d. <?= date('d F Y, H:i', $t_kembali) ?> 
                      <span class="badge bg-info bg-opacity-10 text-info ms-2"><?= $diff_days ?> Hari</span>
                    </span>
                  </div>
                </div>

                <div class="mt-4 bg-body-secondary p-3 rounded border border-secondary border-opacity-10">
                  <?php if ($diskon > 0): ?>
                    <div class="d-flex justify-content-between align-items-center mb-1 small text-muted">
                      <span>Harga Sewa Awal</span>
                      <span>Rp <?= number_format($original_total, 0, ',', '.') ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-1 text-success small">
                      <span>Diskon Member (<?= $diskon_pct ?>%)</span>
                      <span class="fw-semibold">- Rp <?= number_format($diskon, 0, ',', '.') ?></span>
                    </div>
                  <?php endif; ?>
                  <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="text-secondary fw-semibold">Biaya Sewa</span>
                    <span class="text-body fw-bold">Rp <?= number_format($sewa_cost, 0, ',', '.') ?></span>
                  </div>
                  <?php if ($denda > 0): ?>
                    <div class="d-flex justify-content-between align-items-center mb-1 text-danger">
                      <span class="fw-semibold">Denda Keterlambatan</span>
                      <span class="fw-bold">Rp <?= number_format($denda, 0, ',', '.') ?></span>
                    </div>
                  <?php endif; ?>
                  <hr class="my-2 border-secondary border-opacity-25">
                  <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold text-body">Total Pembayaran</h5>
                    <h4 class="mb-0 fw-bold text-primary">Rp <?= number_format($total_pembayaran, 0, ',', '.') ?></h4>
                  </div>
                </div>

              </div>
            </div>
          </div>

          <!-- Right side: Payment Method & Upload Proof -->
          <div class="col-lg-5">
            <?php if ($error): ?>
                <div class="alert alert-danger border-0 bg-danger bg-opacity-10 text-danger p-3 mb-4" style="border-radius: 8px;">
                    <i class="fa fa-exclamation-triangle me-2"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <!-- Modal Notifikasi Sukses -->
                <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
                  <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
                      <div class="modal-header bg-success text-white py-3 border-0">
                        <h5 class="modal-title fw-bold" id="successModalLabel">
                          <i class="fa fa-check-circle me-2"></i>Transaksi Berhasil
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-coreui-dismiss="modal" aria-label="Close"></button>
                      </div>
                      <div class="modal-body p-4 text-center">
                        <div class="mb-3 text-success">
                          <i class="fa fa-check-circle fa-4x animate__animated animate__bounceIn" style="font-size: 5rem;"></i>
                        </div>
                        <h5 class="fw-bold mb-2">Terima Kasih!</h5>
                        <p class="text-muted mb-4"><?= htmlspecialchars($success) ?></p>
                        <button type="button" class="btn btn-success px-5 py-2.5 fw-bold text-white rounded-pill shadow-sm" data-coreui-dismiss="modal">
                          Tutup
                        </button>
                      </div>
                    </div>
                  </div>
                </div>

                <script>
                document.addEventListener("DOMContentLoaded", function() {
                    const modalEl = document.getElementById('successModal');
                    const modalObj = (window.coreui && coreui.Modal) ? new coreui.Modal(modalEl) : (window.bootstrap && bootstrap.Modal) ? new bootstrap.Modal(modalEl) : null;
                    if (modalObj) modalObj.show();
                });
                </script>

                <div class="alert alert-success border-0 bg-success bg-opacity-10 text-success p-3 mb-4" style="border-radius: 8px;">
                    <i class="fa fa-check-circle me-2"></i> <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <div class="card shadow-sm border-0 mb-4" style="border-radius: 12px;">
              <div class="card-header bg-dark text-white py-3" style="border-top-left-radius: 12px; border-top-right-radius: 12px;">
                <h5 class="mb-0 fw-bold"><i class="fa fa-credit-card me-2"></i>Konfirmasi Pembayaran</h5>
              </div>
              <div class="card-body p-4">
                
                <?php if ($status === 'booking' && empty($bukti)): ?>
                    
                    <!-- Xendit Payment Option -->
                    <div class="mb-4">
                      <h6 class="fw-bold mb-3"><i class="fa fa-bolt text-warning me-1"></i>PEMBAYARAN INSTAN:</h6>
                      <?php if ($is_simulation): ?>
                        <div class="alert alert-info border-0 bg-info bg-opacity-10 text-info py-2.5 px-3 mb-3 small">
                          <i class="fa fa-info-circle me-1"></i> <strong>Mode Simulasi:</strong> Klik tombol pembayaran di bawah untuk mensimulasikan alur pembayaran otomatis Xendit pada server lokal Anda.
                        </div>
                      <?php endif; ?>
                      <a href="<?= htmlspecialchars($rental['xendit_invoice_url'] ?? '') ?>" class="btn btn-success w-100 py-3 fw-bold text-white shadow-sm mb-3">
                        <i class="fa fa-credit-card me-2"></i> Bayar Sekarang via Xendit
                      </a>
                      <div class="text-center text-muted small">Mendukung E-Wallet, QRIS, Virtual Account, Kartu Kredit, dll.</div>
                    </div>

                    <div class="d-flex align-items-center my-4">
                      <hr class="flex-grow-1 text-body-secondary">
                      <span class="mx-3 text-muted small fw-bold">ATAU TRANSFER MANUAL</span>
                      <hr class="flex-grow-1 text-body-secondary">
                    </div>

                    <!-- Form for uploading transfer receipt -->
                    <div class="mb-4">
                      <ul class="nav nav-pills mb-3 justify-content-center" id="pills-tab" role="tablist">
                        <li class="nav-item" role="presentation">
                          <button class="nav-link active" id="pills-transfer-tab" data-coreui-toggle="pill" data-coreui-target="#pills-transfer" type="button" role="tab" aria-controls="pills-transfer" aria-selected="true">
                            <i class="fa fa-university me-1"></i> Transfer Bank
                          </button>
                        </li>
                        <li class="nav-item" role="presentation">
                          <button class="nav-link" id="pills-qris-tab" data-coreui-toggle="pill" data-coreui-target="#pills-qris" type="button" role="tab" aria-controls="pills-qris" aria-selected="false">
                            <i class="fa fa-qrcode me-1"></i> QRIS GPN
                          </button>
                        </li>
                      </ul>
                      
                      <div class="tab-content" id="pills-tabContent">
                        <!-- Tab 1: Bank Transfer -->
                        <div class="tab-pane fade show active" id="pills-transfer" role="tabpanel" aria-labelledby="pills-transfer-tab">
                          <div class="p-3 border rounded mb-2 d-flex align-items-center bg-body-tertiary">
                            <i class="fa fa-university fa-2x me-3 text-secondary"></i>
                            <div>
                              <div class="small text-muted fw-bold">BANK BCA</div>
                              <div class="fs-5 fw-bold text-primary">123456789</div>
                              <div class="small text-muted">a.n. FTrans Car Rental</div>
                            </div>
                          </div>
                          <div class="alert alert-warning border-0 bg-warning bg-opacity-10 text-warning py-2 px-3 small mt-2">
                            <i class="fa fa-info-circle me-1"></i> Transfer tepat sesuai nominal tagihan Anda, lalu unggah bukti transfer di bawah ini.
                          </div>
                        </div>
                        
                        <!-- Tab 2: QRIS GPN -->
                        <div class="tab-pane fade" id="pills-qris" role="tabpanel" aria-labelledby="pills-qris-tab">
                          <?php
                          // Cek beberapa kemungkinan nama file gambar QRIS
                          $qris_image_path = 'assets/img/qrcode.jpg';
                          if (!file_exists($qris_image_path)) {
                              $qris_image_path = 'uploads/qrcode.jpg';
                          }
                          if (!file_exists($qris_image_path)) {
                              $qris_image_path = 'assets/img/qris.png';
                          }
                          if (!file_exists($qris_image_path)) {
                              $qris_image_path = 'uploads/qris.png';
                          }
                          if (file_exists($qris_image_path)): ?>
                              <div class="text-center">
                                  <img src="<?= $qris_image_path ?>" alt="QRIS Code" class="img-fluid rounded border shadow-sm mx-auto d-block" style="max-height: 380px; object-fit: contain;">
                              </div>
                          <?php else: ?>
                              <!-- SVG Fallback QRIS Card -->
                              <div class="qris-card border p-3 rounded bg-white text-center shadow-sm position-relative overflow-hidden" style="max-width: 320px; margin: 0 auto; border-color: #e2e8f0;">
                                  <div class="d-flex justify-content-between align-items-center mb-2">
                                      <div style="font-weight: 800; font-size: 1.1rem; color: #1e293b; letter-spacing: -0.5px;">QRIS</div>
                                      <div style="font-weight: 700; font-size: 0.8rem; color: #e11d48;">GPN</div>
                                  </div>
                                  
                                  <div class="my-2">
                                      <h6 class="fw-bold text-dark mb-0" style="letter-spacing: 0.5px; font-size: 0.85rem;">OFATURU, DIGITAL & KREATIF</h6>
                                      <div class="text-muted" style="font-size: 0.7rem; font-weight: 600;">NMID: ID1026483072538</div>
                                      <div class="text-muted" style="font-size: 0.7rem; font-weight: 600;">A01</div>
                                  </div>
                                  
                                  <div class="p-2 bg-white d-inline-block rounded border my-2" style="border-color: #cbd5e1;">
                                      <svg width="180" height="180" viewBox="0 0 100 100" style="display: block;">
                                          <rect x="0" y="0" width="100" height="100" fill="#ffffff" />
                                          <rect x="5" y="5" width="25" height="25" fill="#000000" />
                                          <rect x="10" y="10" width="15" height="15" fill="#ffffff" />
                                          <rect x="13" y="13" width="9" height="9" fill="#000000" />
                                          <rect x="70" y="5" width="25" height="25" fill="#000000" />
                                          <rect x="75" y="10" width="15" height="15" fill="#ffffff" />
                                          <rect x="78" y="13" width="9" height="9" fill="#000000" />
                                          <rect x="5" y="70" width="25" height="25" fill="#000000" />
                                          <rect x="10" y="75" width="15" height="15" fill="#ffffff" />
                                          <rect x="13" y="78" width="9" height="9" fill="#000000" />
                                          <rect x="35" y="5" width="5" height="10" fill="#000000" />
                                          <rect x="45" y="5" width="10" height="5" fill="#000000" />
                                          <rect x="60" y="10" width="5" height="5" fill="#000000" />
                                          <rect x="35" y="20" width="15" height="5" fill="#000000" />
                                          <rect x="55" y="20" width="10" height="10" fill="#000000" />
                                          <rect x="5" y="35" width="10" height="5" fill="#000000" />
                                          <rect x="20" y="35" width="5" height="10" fill="#000000" />
                                          <rect x="30" y="30" width="5" height="5" fill="#000000" />
                                          <rect x="40" y="35" width="10" height="5" fill="#000000" />
                                          <rect x="55" y="35" width="5" height="15" fill="#000000" />
                                          <rect x="65" y="30" width="10" height="5" fill="#000000" />
                                          <rect x="80" y="35" width="15" height="5" fill="#000000" />
                                          <rect x="10" y="50" width="15" height="5" fill="#000000" />
                                          <rect x="30" y="45" width="10" height="10" fill="#000000" />
                                          <rect x="45" y="50" width="5" height="5" fill="#000000" />
                                          <rect x="65" y="45" width="5" height="15" fill="#000000" />
                                          <rect x="75" y="50" width="10" height="5" fill="#000000" />
                                          <rect x="35" y="60" width="5" height="15" fill="#000000" />
                                          <rect x="45" y="65" width="15" height="5" fill="#000000" />
                                          <rect x="70" y="60" width="20" height="5" fill="#000000" />
                                          <rect x="35" y="80" width="15" height="5" fill="#000000" />
                                          <rect x="55" y="75" width="5" height="15" fill="#000000" />
                                          <rect x="65" y="80" width="10" height="10" fill="#000000" />
                                          <rect x="80" y="75" width="5" height="5" fill="#000000" />
                                          <rect x="90" y="80" width="5" height="15" fill="#000000" />
                                      </svg>
                                  </div>
                                  
                                  <div style="font-size: 0.65rem; font-weight: 700; color: #475569;" class="mt-1">
                                      SATU QRIS UNTUK SEMUA
                                  </div>
                                  <div style="font-size: 0.55rem; color: #64748b;">
                                      Cek aplikasi penyelenggara di: www.aspi-qris.id
                                  </div>
                              </div>
                          <?php endif; ?>
                          <div class="alert alert-warning border-0 bg-warning bg-opacity-10 text-warning py-2 px-3 small mt-3">
                            <i class="fa fa-info-circle me-1"></i> Scan QRIS di atas menggunakan aplikasi dompet digital Anda (Gopay, OVO, Dana, LinkAja, Mobile Banking), lalu unggah bukti suksesnya di bawah ini.
                          </div>
                        </div>
                      </div>
                    </div>

                    <form method="POST" action="" enctype="multipart/form-data" class="row g-3 needs-validation" novalidate>
                      <div class="col-12">
                        <label class="form-label fw-bold" for="bukti_pembayaran">Unggah Bukti Pembayaran (Transfer/QRIS) *</label>
                        <input type="file" class="form-control" id="bukti_pembayaran" name="bukti_pembayaran" accept="image/*" required>
                        <div class="invalid-feedback">Wajib mengunggah bukti pembayaran untuk verifikasi.</div>
                        <div class="form-text text-muted">Harap unggah tangkapan layar (screenshot) bukti transfer atau bukti scan QRIS resmi. Format: JPG, JPEG, PNG.</div>
                      </div>
                      <div class="col-12 mt-4">
                        <button type="submit" class="btn btn-outline-secondary w-100 py-2.5 fw-semibold"><i class="fa fa-upload me-2"></i>Kirim Bukti Pembayaran</button>
                      </div>
                    </form>

                <?php elseif ($status === 'sedang_disewa' || $status === 'selesai'): ?>
                    <!-- Paid / Completed status (either through Xendit or manual confirmation) -->
                    <div class="text-center py-4">
                      <div class="mb-3 text-success">
                        <i class="fa fa-check-circle fa-3x animate__animated animate__bounceIn"></i>
                      </div>
                      <h6 class="fw-bold text-success mb-2">Pembayaran Berhasil / Lunas</h6>
                      <p class="text-muted small mb-0">Pembayaran Anda telah diverifikasi dan dikonfirmasi.</p>
                      
                      <?php if (!empty($bukti)): ?>
                          <div class="border rounded p-2 mt-3 bg-body-tertiary">
                            <div class="small text-muted mb-2 text-start fw-bold">Pratinjau Bukti Pembayaran:</div>
                            <img src="uploads/<?= htmlspecialchars($bukti) ?>" alt="Bukti Transfer" class="img-fluid rounded border shadow-sm" style="max-height: 250px;">
                          </div>
                      <?php else: ?>
                          <div class="alert alert-success border-0 bg-success bg-opacity-10 text-success py-2.5 px-3 mt-3 small text-start">
                            <i class="fa fa-info-circle me-1"></i> Pembayaran diselesaikan secara otomatis melalui Xendit.
                          </div>
                      <?php endif; ?>
                    </div>

                <?php elseif (!empty($bukti) && $status === 'booking'): ?>
                    <!-- Manual receipt uploaded, waiting admin verification -->
                    <div class="text-center py-2">
                      <div class="mb-3 text-warning">
                        <i class="fa fa-clock fa-3x animate__animated animate__pulse animate__infinite"></i>
                      </div>
                      <h6 class="fw-bold text-warning mb-2">Menunggu Verifikasi Admin</h6>
                      <p class="text-muted small mb-4">Bukti transfer Anda telah kami terima pada tanggal <strong><?= date('d M Y, H:i', strtotime($rental['waktu_bayar'])) ?></strong> dan sedang diverifikasi oleh admin.</p>
                      
                      <div class="border rounded p-2 bg-body-tertiary">
                        <div class="small text-muted mb-2 text-start fw-bold">Pratinjau Bukti Pembayaran:</div>
                        <img src="uploads/<?= htmlspecialchars($bukti) ?>" alt="Bukti Transfer" class="img-fluid rounded border shadow-sm" style="max-height: 250px;">
                      </div>
                    </div>

                <?php else: ?>
                    <!-- Bookings cancelled or other default cases -->
                    <div class="text-center py-4 text-muted">
                      <i class="fa fa-ban fa-3x mb-3 text-danger"></i>
                      <h6 class="fw-bold text-danger">Transaksi Dibatalkan</h6>
                      <p class="small mb-0">Transaksi penyewaan ini telah dibatalkan atau kedaluwarsa sehingga pembayaran tidak dapat diproses.</p>
                    </div>
                <?php endif; ?>

              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
    <?php include 'partials/footer.php'; ?>
  </div>
</body>
</html>
