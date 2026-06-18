<?php
require_once 'api_helper.php';

if (isset($_GET['nim'])) {
    $nim = (int)$_GET['nim'];
    // Kirim data DELETE via JSON Body ke API
    $_SESSION['flash'] = callAPI('DELETE', $api_url, ["nim" => $nim]);
} else {
    $_SESSION['flash'] = ["status" => "error", "message" => "NIM tidak ditentukan untuk dihapus."];
}

header("Location: index.php");
exit;
?>
