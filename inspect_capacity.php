<?php
require_once 'includes/db.php';
header('Content-Type: text/plain');

echo "--- TRAIN SCHEDULES ---\n";
$schedules = $pdo->query("SELECT id, train_name, capacity_kg, status FROM train_schedules")->fetchAll(PDO::FETCH_ASSOC);
foreach($schedules as $s) {
    echo "ID: {$s['id']} | Name: {$s['train_name']} | Cap: {$s['capacity_kg']} | Status: {$s['status']}\n";
    
    $stmt = $pdo->prepare("SELECT id, resi_number, weight_kg, status FROM shipments WHERE train_id = ?");
    $stmt->execute([$s['id']]);
    $shipments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach($shipments as $sh) {
        echo "  -> Shipment ID: {$sh['id']} | Resi: {$sh['resi_number']} | Weight: {$sh['weight_kg']} | Status: {$sh['status']}\n";
    }
    
    $stmt = $pdo->prepare("SELECT SUM(weight_kg) FROM shipments WHERE train_id = ? AND status != 'arrived'");
    $stmt->execute([$s['id']]);
    $sum = $stmt->fetchColumn();
    echo "  TOTAL ACTIVE WEIGHT: " . ($sum ?: 0) . " KG\n\n";
}
?>
