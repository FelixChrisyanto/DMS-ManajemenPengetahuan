<?php
require_once 'includes/db.php';

try {
    // Create shipment_items table
    $sql = "CREATE TABLE IF NOT EXISTS shipment_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        shipment_id INT NOT NULL,
        item_name VARCHAR(255) NOT NULL,
        quantity INT DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (shipment_id) REFERENCES shipments(id) ON DELETE CASCADE
    )";
    $pdo->exec($sql);
    
    echo "Sukses: Tabel shipment_items berhasil dibuat.";
} catch (PDOException $e) {
    echo "Gagal: " . $e->getMessage();
}
?>
