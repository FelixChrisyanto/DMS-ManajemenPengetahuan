<?php
require_once 'includes/db.php';
header('Content-Type: text/plain');

try {
    echo "--- DEBUG DASHBOARD ---\n";
    
    // 1. Check Shipments table
    $stmt = $pdo->query("SELECT COUNT(*) FROM shipments");
    echo "Total Shipments (Raw): " . $stmt->fetchColumn() . "\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM shipments WHERE deleted_at IS NULL");
    echo "Total Shipments (Active): " . $stmt->fetchColumn() . "\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM shipments WHERE DATE(created_at) = CURDATE()");
    echo "Today's Shipments (CURDATE=" . date('Y-m-d') . "): " . $stmt->fetchColumn() . "\n";

    // 2. Check Documents table
    $stmt = $pdo->query("SELECT COUNT(*) FROM documents");
    echo "Total Documents: " . $stmt->fetchColumn() . "\n";

    // 3. Check Invoices table
    $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM invoices GROUP BY status");
    echo "Invoice Statuses:\n";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "- " . $row['status'] . ": " . $row['count'] . "\n";
    }

    // 4. Check Activity Feed Query
    echo "\nTesting Activity Feed Query...\n";
    $recentActivity = $pdo->query("(SELECT 'doc' as type, file_name as title, category as sub, created_at FROM documents)
                                   UNION ALL
                                   (SELECT 'payment' as type, 'Konfirmasi' as title, invoice_number as sub, updated_at as created_at FROM invoices WHERE status = 'pending')
                                   UNION ALL
                                   (SELECT 'shipment' as type, 'Pengiriman' as title, resi_number as sub, created_at FROM shipments WHERE deleted_at IS NULL)
                                   ORDER BY created_at DESC LIMIT 5")->fetchAll();
    
    if (empty($recentActivity)) {
        echo "Activity Feed is EMPTY.\n";
    } else {
        foreach ($recentActivity as $act) {
            echo "[{$act['type']}] {$act['title']} ({$act['created_at']})\n";
        }
    }

} catch (Exception $e) {
    echo "DEBUG FAILED: " . $e->getMessage() . "\n";
}
?>
