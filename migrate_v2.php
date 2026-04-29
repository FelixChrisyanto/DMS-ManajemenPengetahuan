<?php
require_once 'includes/db.php';
header('Content-Type: text/plain');

try {
    echo "Starting Migration: Adding reference_resi to documents table...\n";
    
    // Check if column exists first
    $check = $pdo->query("SHOW COLUMNS FROM documents LIKE 'reference_resi'");
    if ($check->rowCount() == 0) {
        $pdo->exec("ALTER TABLE documents ADD COLUMN reference_resi VARCHAR(20) AFTER shipment_id");
        echo "Column 'reference_resi' added successfully.\n";
    } else {
        echo "Column 'reference_resi' already exists.\n";
    }

    echo "Syncing existing data...\n";
    $sync = $pdo->exec("UPDATE documents d 
                        JOIN shipments s ON d.shipment_id = s.id 
                        SET d.reference_resi = s.resi_number 
                        WHERE d.reference_resi IS NULL");
    echo "Data sync complete. Rows updated: $sync\n";

    echo "Migration finished successfully!\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
?>
