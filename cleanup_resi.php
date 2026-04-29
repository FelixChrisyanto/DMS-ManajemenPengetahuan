<?php
require_once 'includes/db.php';

try {
    // Trim spaces and remove tabs (\t)
    $stmt = $pdo->prepare("UPDATE shipments SET resi_number = REPLACE(TRIM(resi_number), '\t', '')");
    $stmt->execute();
    $affected = $stmt->rowCount();
    
    echo "Pembersihan selesai (Agresif). Berhasil memperbarui $affected baris data resi.\n";
    
    // Also cleanup existing invoices to be safe
    $stmt2 = $pdo->prepare("UPDATE invoices SET invoice_number = TRIM(invoice_number)");
    $stmt2->execute();
    
} catch (Exception $e) {
    echo "Gagal membersihkan data: " . $e->getMessage();
}
?>
