<?php
// config/database.php
define('DB_HOST', 'localhost');
define('DB_NAME', 'eformdb');
define('DB_USER', 'eformuser');
define('DB_PASS', 'password123');

define('APP_NAME', 'E-Form System');
define('APP_URL', 'https://eform.nurakreasidigital.my.id');
define('SESSION_TIMEOUT', 3600);

error_reporting(E_ALL);
ini_set('display_errors', 0);
date_default_timezone_set('Asia/Jakarta');

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>
