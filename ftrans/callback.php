<?php
require_once 'config.php';

// Handle any error returned directly by Google in the query string
if (isset($_GET['error'])) {
    header('Location: login.php?error=' . urlencode($_GET['error_description'] ?? $_GET['error']));
    exit;
}

$code = $_GET['code'] ?? '';
if (empty($code)) {
    header('Location: login.php?error=no_code');
    exit;
}

$google_client_id = $_ENV['GOOGLE_CLIENT_ID'] ?? getenv('GOOGLE_CLIENT_ID');
$google_client_secret = $_ENV['GOOGLE_CLIENT_SECRET'] ?? getenv('GOOGLE_CLIENT_SECRET');
$google_redirect_url = $_ENV['GOOGLE_REDIRECT_URL'] ?? getenv('GOOGLE_REDIRECT_URL');

if (empty($google_client_id) || empty($google_client_secret) || empty($google_redirect_url)) {
    header('Location: login.php?error=' . urlencode('Konfigurasi Google Client ID, Secret, atau Redirect URL di file .env belum diisi.'));
    exit;
}

// 1. Exchange authorization code for access token
$token_url = 'https://oauth2.googleapis.com/token';
$post_fields = [
    'code'          => $code,
    'client_id'     => $google_client_id,
    'client_secret' => $google_client_secret,
    'redirect_uri'  => $google_redirect_url,
    'grant_type'    => 'authorization_code'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $token_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_fields));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
// Disable SSL verification temporarily for local development configurations where certificate bundles are often missing/invalid
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
$response = curl_exec($ch);

if (curl_errno($ch)) {
    $error_msg = curl_error($ch);
    curl_close($ch);
    header('Location: login.php?error=' . urlencode('cURL Error: ' . $error_msg));
    exit;
}
curl_close($ch);

$token_data = json_decode($response, true);
if (empty($token_data['access_token'])) {
    header('Location: login.php?error=google_auth');
    exit;
}

$access_token = $token_data['access_token'];

// 2. Fetch user information using the access token
$userinfo_url = 'https://www.googleapis.com/oauth2/v3/userinfo';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $userinfo_url);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $access_token
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);

if (curl_errno($ch)) {
    $error_msg = curl_error($ch);
    curl_close($ch);
    header('Location: login.php?error=' . urlencode('cURL Error: ' . $error_msg));
    exit;
}
curl_close($ch);

$user_info = json_decode($response, true);
if (empty($user_info['email'])) {
    header('Location: login.php?error=google_auth');
    exit;
}

$email = trim($user_info['email']);
$name = trim($user_info['name'] ?? $user_info['given_name'] ?? 'User Google');

// 3. Find or register the user in the database
$stmt = mysqli_prepare($mysqli, "SELECT id, nama, role FROM users WHERE email = ?");
mysqli_stmt_bind_param($stmt, 's', $email);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if ($user) {
    // User already exists, log them in
    $_SESSION['user_id']   = $user['id'];
    $_SESSION['user_nama'] = $user['nama'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['logged_in_via_google'] = true;
} else {
    // User does not exist, auto-register them
    $random_password = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    $insert_stmt = mysqli_prepare($mysqli, "INSERT INTO users (nama, email, password, is_verified) VALUES (?, ?, ?, 1)");
    if ($insert_stmt) {
        mysqli_stmt_bind_param($insert_stmt, 'sss', $name, $email, $random_password);
        if (mysqli_stmt_execute($insert_stmt)) {
            $user_id = mysqli_insert_id($mysqli);
            mysqli_stmt_close($insert_stmt);
            
            $_SESSION['user_id']   = $user_id;
            $_SESSION['user_nama'] = $name;
            $_SESSION['user_role'] = 'user'; // default role is 'user' for new accounts
            $_SESSION['logged_in_via_google'] = true;
        } else {
            mysqli_stmt_close($insert_stmt);
            header('Location: login.php?error=db_error');
            exit;
        }
    } else {
        header('Location: login.php?error=db_error');
        exit;
    }
}

// Redirect to the secured dashboard
header('Location: dashboard.php');
exit;