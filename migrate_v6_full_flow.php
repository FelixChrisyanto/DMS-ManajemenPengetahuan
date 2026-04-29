<?php
require_once 'includes/db.php';

try {
    echo "Starting Migration v6 (Photos & Phone Numbers)...\n";
    
    // Add columns to shipments table
    $sql = "ALTER TABLE shipments 
            ADD COLUMN sender_phone VARCHAR(20) NULL AFTER sender_name,
            ADD COLUMN receiver_phone VARCHAR(20) NULL AFTER receiver_name,
            ADD COLUMN goods_photo VARCHAR(255) NULL AFTER goods_type";
    
    $pdo->exec($sql);
    
    echo "SUCCESS: Shipments table updated with new columns.\n";
} catch (PDOException $e) {
    // If columns already exist, ignore error
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "INFO: Columns already exist.\n";
    } else {
        echo "ERROR: " . $e->getMessage() . "\n";
    }
}
?>
