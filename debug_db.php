<?php
require_once 'includes/db.php';
$resi = $_GET['resi'] ?? 'LNE-001';
echo "Searching for: '[$resi]'\n";
$stmt = $pdo->prepare("SELECT * FROM shipments WHERE resi_number LIKE ?");
$stmt->execute(["%$resi%"]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Found " . count($rows) . " rows:\n";
foreach($rows as $row) {
    echo "ID: " . $row['id'] . " | Resi: '[" . $row['resi_number'] . "]' | TrainID: " . $row['train_id'] . " | RouteID: " . $row['route_id'] . "\n";
}
?>
