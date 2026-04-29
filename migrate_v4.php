<?php
require_once 'includes/db.php';
header('Content-Type: text/plain');

try {
    echo "Starting Migration: Adding 'pending' status to invoices...\n";
    
    // Check current column definition
    $check = $pdo->query("SHOW COLUMNS FROM invoices LIKE 'status'");
    $col = $check->fetch(PDO::FETCH_ASSOC);
    
    // Modify to include pending
    $pdo->exec("ALTER TABLE invoices MODIFY COLUMN status ENUM('paid', 'unpaid', 'pending') DEFAULT 'unpaid'");
    echo "Column 'status' updated successfully to include 'pending'.\n";

    echo "Migration finished successfully!\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
?>
