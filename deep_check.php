<?php
require_once 'includes/db.php';
header('Content-Type: text/plain');

echo "========== DEEP DATA INSPECTION ==========\n\n";

// 1. Check Train Schedules
$schedules = $pdo->query("SELECT ts.*, r.origin, r.destination FROM train_schedules ts JOIN routes r ON ts.route_id = r.id")->fetchAll(PDO::FETCH_ASSOC);

foreach($schedules as $s) {
    echo "TRAIN: [ID: {$s['id']}] {$s['train_name']} ({$s['origin']} - {$s['destination']})\n";
    echo "CAPACITY: {$s['capacity_kg']} KG | DB STATUS: {$s['status']}\n";
    
    // Check actual shipments for this ID
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_count, SUM(weight_kg) as total_weight FROM shipments WHERE train_id = ?");
    $stmt->execute([$s['id']]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "ACTUAL SHIPMENTS IN DB: {$stats['total_count']} items\n";
    echo "TOTAL WEIGHT IN DB: " . ($stats['total_weight'] ?: 0) . " KG\n";
    
    // Check active shipments
    $stmt = $pdo->prepare("SELECT id, resi_number, weight_kg, status FROM shipments WHERE train_id = ? AND LOWER(TRIM(status)) != 'arrived'");
    $stmt->execute([$s['id']]);
    $active_shipments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $active_weight = 0;
    if ($active_shipments) {
        echo "ACTIVE SHIPMENTS (Non-Arrived):\n";
        foreach($active_shipments as $sh) {
            echo "  - ID: {$sh['id']} | RESI: {$sh['resi_number']} | WEIGHT: {$sh['weight_kg']} | STATUS: [{$sh['status']}]\n";
            $active_weight += $sh['weight_kg'];
        }
    } else {
        echo "NO ACTIVE SHIPMENTS.\n";
    }
    echo "CALCULATED ACTIVE WEIGHT: $active_weight KG\n";
    
    // Status Logic check
    $expected_status = ($active_weight >= $s['capacity_kg']) ? 'full' : 'available';
    echo "EXPECTED STATUS: " . strtoupper($expected_status) . "\n";
    if ($expected_status != $s['status']) {
        echo "!!! STATUS MISMATCH DETECTED !!!\n";
    }
    echo "------------------------------------------\n\n";
}

echo "========== END OF INSPECTION ==========\n";
?>
