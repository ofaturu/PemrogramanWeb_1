<?php
require_once 'api_helper.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = [
        "nim" => (int)$_POST['nim'],
        "nama" => $_POST['nama'],
        "id_prodi" => (int)$_POST['id_prodi']
    ];

    $_SESSION['flash'] = callAPI('POST', $api_url, $payload);
}

header("Location: index.php");
exit;
?>
