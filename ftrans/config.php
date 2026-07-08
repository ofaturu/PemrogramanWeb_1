<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load environment variables from .env
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        // Skip comments and empty lines
        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            // Strip matching single/double quotes
            $value = trim($value, '"\'');
            
            if (!array_key_exists($key, $_SERVER) && !array_key_exists($key, $_ENV)) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
}

$databaseHost     = 'localhost';
$databaseName     = 'ftrans';
$databaseUsername = 'root';
$databasePassword = '';

$mysqli = mysqli_connect($databaseHost, $databaseUsername, $databasePassword, $databaseName);

if (!$mysqli) {
    die("Koneksi gagal: " . mysqli_connect_error());
}

mysqli_set_charset($mysqli, 'utf8');

// Buat tabel landing_settings secara otomatis jika belum ada
mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `landing_settings` (
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text DEFAULT NULL,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Data migration: Tambah kolom no_hp di tabel users jika belum ada
$check_no_hp = mysqli_query($mysqli, "SHOW COLUMNS FROM `users` LIKE 'no_hp'");
if ($check_no_hp && mysqli_num_rows($check_no_hp) === 0) {
    mysqli_query($mysqli, "ALTER TABLE `users` ADD `no_hp` VARCHAR(20) DEFAULT NULL");
}

// Data migration: Tambah kolom xendit_invoice_id di tabel penyewaan jika belum ada
$check_xendit_id = mysqli_query($mysqli, "SHOW COLUMNS FROM `penyewaan` LIKE 'xendit_invoice_id'");
if ($check_xendit_id && mysqli_num_rows($check_xendit_id) === 0) {
    mysqli_query($mysqli, "ALTER TABLE `penyewaan` ADD `xendit_invoice_id` VARCHAR(255) DEFAULT NULL");
}

// Data migration: Tambah kolom xendit_invoice_url di tabel penyewaan jika belum ada
$check_xendit_url = mysqli_query($mysqli, "SHOW COLUMNS FROM `penyewaan` LIKE 'xendit_invoice_url'");
if ($check_xendit_url && mysqli_num_rows($check_xendit_url) === 0) {
    mysqli_query($mysqli, "ALTER TABLE `penyewaan` ADD `xendit_invoice_url` VARCHAR(500) DEFAULT NULL");
}

// Data migration: Buat tabel notifications jika belum ada
mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NULL,
  `title` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `is_read` TINYINT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Data migration: Buat tabel maintenance jika belum ada
mysqli_query($mysqli, "CREATE TABLE IF NOT EXISTS `maintenance` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `kode_unik_kendaraan` INT NOT NULL,
  `deskripsi` VARCHAR(255) NOT NULL,
  `biaya` INT DEFAULT 0,
  `tanggal_mulai` DATE NOT NULL,
  `tanggal_selesai` DATE NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Isi data default jika tabel masih kosong
$check_empty = mysqli_query($mysqli, "SELECT COUNT(*) as count FROM landing_settings");
if ($check_empty) {
    $row = mysqli_fetch_assoc($check_empty);
    if ($row['count'] == 0) {
        mysqli_query($mysqli, "INSERT INTO `landing_settings` (`setting_key`, `setting_value`) VALUES
        ('hero_title', 'Eksplorasi Perjalanan Kelas Dunia Bersama Kami.'),
        ('hero_subtitle', 'Nikmati kenyamanan berkendara terbaik dengan armada mobil mewah dan pelayanan VIP yang dirancang khusus untuk memenuhi standar eksklusivitas Anda.'),
        ('hero_image', 'https://images.unsplash.com/photo-1614162692292-7ac56d7f7f1e?auto=format&fit=crop&w=1000&q=80')");
    }
}

// Middleware: Paksa user melengkapi nomor HP jika kosong
$secure_pages = ['dashboard.php', 'sewa.php', 'users.php', 'bayar.php', 'analytics.php', 'calendar.php', 'maintenance.php'];
if (isset($_SESSION['user_id']) && $_SESSION['user_role'] === 'user' && in_array(basename($_SERVER['PHP_SELF']), $secure_pages)) {
    $stmt = mysqli_prepare($mysqli, "SELECT no_hp FROM users WHERE id = ?");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $_SESSION['user_id']);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $no_hp);
        mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);

        if (empty($no_hp)) {
            header('Location: profile.php?incomplete=1');
            exit;
        }
    }
}

// Fungsi pembantu untuk membuat notifikasi
function add_notification($user_id, $title, $message) {
    global $mysqli;
    $stmt = mysqli_prepare($mysqli, "INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");
    if ($stmt) {
        // If $user_id is 0 or empty string, insert NULL (for Admin notifications)
        $uid = ($user_id > 0) ? intval($user_id) : null;
        mysqli_stmt_bind_param($stmt, 'iss', $uid, $title, $message);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

// Fungsi pembantu untuk mendapatkan tier membership dan diskon loyalitas
function getUserMembership($id_user, $mysqli) {
    // Hitung total sewa selesai (sukses) oleh user
    $q = mysqli_query($mysqli, "SELECT COUNT(*) as count FROM penyewaan WHERE id_user = " . intval($id_user) . " AND status = 'selesai'");
    $row = mysqli_fetch_assoc($q);
    $count = $row['count'] ?? 0;

    // Ambil data tier eksplisit dari database
    $q2 = mysqli_query($mysqli, "SELECT membership_tier FROM users WHERE id = " . intval($id_user));
    $row2 = mysqli_fetch_assoc($q2);
    $db_tier = strtolower(trim($row2['membership_tier'] ?? 'basic'));
    if (empty($db_tier)) $db_tier = 'basic';

    // Kalkulasi upgrade tier otomatis berdasarkan jumlah sewa
    $auto_tier = 'basic';
    if ($count >= 10) {
        $auto_tier = 'gold';
    } elseif ($count >= 6) {
        $auto_tier = 'silver';
    } elseif ($count >= 3) {
        $auto_tier = 'bronze';
    }

    // Tentukan tier akhir (ambil yang tertinggi)
    $tiers = ['basic' => 0, 'bronze' => 1, 'silver' => 2, 'gold' => 3];
    $final_tier = ($tiers[$db_tier] > $tiers[$auto_tier]) ? $db_tier : $auto_tier;

    // Tentukan diskon
    $discounts = ['basic' => 0.0, 'bronze' => 0.05, 'silver' => 0.10, 'gold' => 0.15];
    $discount = $discounts[$final_tier] ?? 0.0;

    return [
        'tier' => $final_tier,
        'discount' => $discount,
        'completed_rentals' => $count
    ];
}

// Sinkronkan data membership user ke session saat masuk halaman
if (isset($_SESSION['user_id'])) {
    $membership = getUserMembership($_SESSION['user_id'], $mysqli);
    $_SESSION['user_tier'] = $membership['tier'];
    $_SESSION['user_discount'] = $membership['discount'];
}
?>
