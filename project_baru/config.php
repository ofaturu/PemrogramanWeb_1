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
?>