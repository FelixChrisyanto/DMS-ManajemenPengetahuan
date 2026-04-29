<?php
require_once 'includes/db.php';

try {
    echo "Starting Migration v5 (BAST & Manifest Muatan)...\n";
    
    // Update ENUM category in documents table
    $sql = "ALTER TABLE documents MODIFY COLUMN category ENUM('surat_jalan', 'resi', 'invoice', 'payment_proof', 'operational', 'bast', 'manifest') NOT NULL";
    $pdo->exec($sql);
    
    echo "SUCCESS: Database schema updated.\n";
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>
