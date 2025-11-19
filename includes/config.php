<?php
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "db_shoeverse";

$conn = mysqli_connect($host, $user, $pass, $dbname);

if (!$conn) {
    die("Database Connection Failed: " . mysqli_connect_error());
}

if (!defined('MAILTRAP_HOST')) define('MAILTRAP_HOST', 'sandbox.smtp.mailtrap.io');
if (!defined('MAILTRAP_PORT')) define('MAILTRAP_PORT', 2525);
if (!defined('MAILTRAP_USER')) define('MAILTRAP_USER', 'c98ac91d457458');
if (!defined('MAILTRAP_PASS')) define('MAILTRAP_PASS', '82be2d3933a83d');
if (!defined('MAIL_FROM')) define('MAIL_FROM', 'no-reply@shoeverse.local');

if (!defined('MAIL_FROM_NAME')) define('MAIL_FROM_NAME', 'Shoeverse');

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}
?>