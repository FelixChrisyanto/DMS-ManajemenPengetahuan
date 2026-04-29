<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// includes/db.php
// Centralized SQLite database connection for PT Lintas Nusantara Ekspedisi DMS

$db_file = __DIR__ . '/../database.sqlite';
$schema_file = __DIR__ . '/../sqlite_schema.sql';

try {
    $first_time = !file_exists($db_file);
    
    $dsn = "sqlite:$db_file";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    $pdo = new PDO($dsn, null, null, $options);
    
    // Enable Foreign Keys in SQLite
    $pdo->exec("PRAGMA foreign_keys = ON;");

    // Auto-initialize if database file is new
    if ($first_time && file_exists($schema_file)) {
        $sql = file_get_contents($schema_file);
        $pdo->exec($sql);
    } else {
        // One-time patch for missing columns if database already exists
        try {
            $pdo->exec("ALTER TABLE documents ADD COLUMN reference_resi TEXT NULL");
        } catch (Exception $e) {}
    }

} catch (\PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
