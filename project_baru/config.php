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

/**
 * Helper function to simulate sending emails by saving HTML files in project root,
 * and attempting real PHP mail() transmission.
 */
function ftrans_send_email($to_email, $to_name, $subject, $body_html) {
    // 1. Create a beautiful simulated layout wrapper
    $email_template = '
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <title>' . htmlspecialchars($subject) . '</title>
        <style>
            body { font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; background-color: #f1f5f9; color: #1e293b; margin: 0; padding: 20px; }
            .container { max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); border: 1px solid #e2e8f0; overflow: hidden; }
            .header { background-color: #3b82f6; padding: 24px; color: #ffffff; text-align: center; }
            .header h1 { margin: 0; font-size: 24px; font-weight: 700; }
            .content { padding: 32px; line-height: 1.6; }
            .footer { background-color: #f8fafc; padding: 20px; text-align: center; font-size: 12px; color: #64748b; border-top: 1px solid #e2e8f0; }
            .simulated-info { background-color: #fef3c7; border: 1px solid #fcd34d; color: #92400e; padding: 12px 16px; border-radius: 8px; font-size: 13px; margin: 20px; line-height: 1.4; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="simulated-info">
                <strong>💡 LOG NOTIFIKASI EMAIL LOKAL (SIMULASI):</strong><br>
                <strong>Kepada:</strong> ' . htmlspecialchars($to_name) . ' (' . htmlspecialchars($to_email) . ')<br>
                <strong>Subjek:</strong> ' . htmlspecialchars($subject) . '<br>
                <strong>Waktu:</strong> ' . date('d F Y, H:i:s') . '
            </div>
            <div class="header">
                <h1>FTrans Car Rental</h1>
            </div>
            <div class="content">
                ' . $body_html . '
            </div>
            <div class="footer">
                &copy; ' . date('Y') . ' FTrans Car Rental Management. All rights reserved.
            </div>
        </div>
    </body>
    </html>
    ';

    // 2. Save email log inside project directory "emails/"
    $dir = __DIR__ . '/emails';
    if (!file_exists($dir)) {
        mkdir($dir, 0777, true);
    }
    
    // Sanitize subject for filename
    $safe_subject = preg_replace('/[^a-zA-Z0-9_-]/', '_', strtolower($subject));
    $filename = $dir . '/email_' . time() . '_' . $safe_subject . '.html';
    file_put_contents($filename, $email_template);

    // 3. Attempt real PHP mail sending (if SMTP config is ready)
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: FTrans Car Rental <noreply@localhost>" . "\r\n";
    
    @mail($to_email, $subject, $email_template, $headers);
    
    return true;
}
?>
