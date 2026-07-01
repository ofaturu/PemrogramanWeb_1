<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
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
?>


<!-- tes fatchur-->