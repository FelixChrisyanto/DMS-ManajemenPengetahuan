<?php
require_once 'includes/db.php';
header('Content-Type: text/plain');

try {
    echo "Starting Migration: Adding deleted_at to shipments table...\n";
    
    // Check if column exists first
    $check = $pdo->query("SHOW COLUMNS FROM shipments LIKE 'deleted_at'");
    if ($check->rowCount() == 0) {
        $pdo->exec("ALTER TABLE shipments ADD COLUMN deleted_at TIMESTAMP NULL");
        echo "Column 'deleted_at' added successfully.\n";
    } else {
        echo "Column 'deleted_at' already exists.\n";
    }

    echo "Migration finished successfully!\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
?>
