<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// includes/db.php
// MySQL database connection for PT Lintas Nusantara Ekspedisi DMS
// Optimized for hosting compatibility (e.g., InfinityFree)

$db_host = 'localhost';
$db_name = 'dms_logistik';
$db_user = 'root';
$db_pass = ''; // Leave empty for Laragon default

try {
    $dsn = "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
    
} catch (\PDOException $e) {
    // If the database doesn't exist, try to connect without dbname first to create it (optional)
    // For now, just die with a clear message.
    die("Connection failed: " . $e->getMessage() . ". Make sure to import schema.sql into your MySQL database.");
}
?>
