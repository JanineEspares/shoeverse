<?php
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "db_shoeverse";

$conn = mysqli_connect($host, $user, $pass, $dbname);

if (!$conn) {
    die("Database Connection Failed: " . mysqli_connect_error());
}

// Mailtrap / SMTP settings (set these to your Mailtrap SMTP credentials)
// You can leave as-is for development and use the fallback PHP mail() if PHPMailer is not installed.
if (!defined('MAILTRAP_HOST')) define('MAILTRAP_HOST', 'sandbox.smtp.mailtrap.io');
if (!defined('MAILTRAP_PORT')) define('MAILTRAP_PORT', 2525);
if (!defined('MAILTRAP_USER')) define('MAILTRAP_USER', 'c98ac91d457458');
if (!defined('MAILTRAP_PASS')) define('MAILTRAP_PASS', '82be2d3933a83d');
if (!defined('MAIL_FROM')) define('MAIL_FROM', 'no-reply@shoeverse.local');

// Optional: human-friendly from name
if (!defined('MAIL_FROM_NAME')) define('MAIL_FROM_NAME', 'Shoeverse');

// If Composer's autoload exists, load it so PHPMailer (installed via Composer) is available.
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}
?>