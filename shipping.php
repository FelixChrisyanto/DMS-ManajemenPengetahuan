<?php 
require_once 'includes/db.php';
require_once 'includes/wa_helper.php';
require_once 'includes/generate_pdf.php';

// Database connection is now MySQL. Migration should be done via schema.sql import.

$message = "";

// Handle message from redirect
if (isset($_GET['msg'])) {
    $message = $_GET['msg'];
}

// Handle Delete
if (isset($_GET['delete'])) {
    try {
        $pdo->beginTransaction();
        // Get train_id before delete for status sync
        $stmt = $pdo->prepare("SELECT train_id FROM shipments WHERE id = ?");
        $stmt->execute([$_GET['delete']]);
        $trainId = $stmt->fetchColumn();

        // Delete related invoice first
        $stmt = $pdo->prepare("DELETE FROM invoices WHERE shipment_id = ?");
        $stmt->execute([$_GET['delete']]);
        
        // Update related documents to NULL shipment_id (Permanent Archive)
        $stmt = $pdo->prepare("UPDATE documents SET shipment_id = NULL WHERE shipment_id = ?");
        $stmt->execute([$_GET['delete']]);

        // Get train_id before sync
        $stmt = $pdo->prepare("SELECT train_id FROM shipments WHERE id = ?");
        $stmt->execute([$_GET['delete']]);
        $trainIdBeforeDelete = $stmt->fetchColumn();

        // Soft Delete shipment
        $stmt = $pdo->prepare("UPDATE shipments SET deleted_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$_GET['delete']]);
        
        // Sync Train Status using captured trainId
        if ($trainIdBeforeDelete) {
            $stmt = $pdo->prepare("UPDATE train_schedules
                                SET status = CASE WHEN (SELECT COALESCE(SUM(weight_kg), 0) FROM shipments WHERE train_id = train_schedules.id AND LOWER(TRIM(status)) != 'arrived' AND deleted_at IS NULL) >= capacity_kg THEN 'full' ELSE 'available' END
                                WHERE id = ?");
            $stmt->execute([$trainIdBeforeDelete]);
        }

        $pdo->commit();
        header("Location: shipping.php?msg=deleted");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "Gagal menghapus: " . $e->getMessage();
    }
}

// Handle POST actions (Create & Edit)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['resi_number'])) {
    try {
        $pdo->beginTransaction();
        
        if (isset($_POST['shipment_id']) && !empty($_POST['shipment_id'])) {
            // EDIT ACTION
            $newStatus = strtolower(trim($_POST['status'] ?? ''));
            $isChangingToArrived = ($newStatus == 'arrived');

            if (!$isChangingToArrived) {
                // Check Capacity only if NOT changing to arrived
                $stmt = $pdo->prepare("SELECT capacity_kg FROM train_schedules WHERE id = ?");
                $stmt->execute([$_POST['train_id']]);
                $capacity = $stmt->fetchColumn();

                $stmt = $pdo->prepare("SELECT COALESCE(SUM(weight_kg), 0) FROM shipments WHERE train_id = ? AND LOWER(TRIM(status)) != 'arrived' AND deleted_at IS NULL AND id != ?");
                $stmt->execute([$_POST['train_id'], $_POST['shipment_id']]);
                $currentWeight = $stmt->fetchColumn();

                if ($currentWeight + $_POST['weight'] > $capacity) {
                    throw new Exception("Kapasitas kereta tidak mencukupi! Tersisa " . ($capacity - $currentWeight) . " KG.");
                }
            }

            // Handle Photo Update (optional if new photo uploaded)
            $photoPath = $_POST['existing_photo'] ?? null;
            if (isset($_FILES['goods_photo']) && $_FILES['goods_photo']['error'] == 0) {
                $ext = pathinfo($_FILES['goods_photo']['name'], PATHINFO_EXTENSION);
                $fileName = "goods_" . time() . "_" . trim($_POST['resi_number']) . "." . $ext;
                $targetPath = "uploads/goods/" . $fileName;
                if (move_uploaded_file($_FILES['goods_photo']['tmp_name'], $targetPath)) {
                    $photoPath = $targetPath;
                }
            }

            $stmt = $pdo->prepare("UPDATE shipments SET resi_number = ?, sender_name = ?, sender_phone = ?, receiver_name = ?, receiver_phone = ?, goods_photo = ?, weight_kg = ? , route_id = ?, train_id = ?, status = ? WHERE id = ?");
            $stmt->execute([
                trim($_POST['resi_number']), 
                $_POST['sender'], 
                $_POST['sender_phone'],
                $_POST['receiver'], 
                $_POST['receiver_phone'],
                $photoPath,
                $_POST['weight'], 
                $_POST['route_id'], 
                $_POST['train_id'],
                $_POST['status'],
                $_POST['shipment_id']
            ]);

            // Sync Shipment Items
            $pdo->prepare("DELETE FROM shipment_items WHERE shipment_id = ?")->execute([$_POST['shipment_id']]);
            if (isset($_POST['items']) && is_array($_POST['items'])) {
                foreach ($_POST['items'] as $index => $itemName) {
                    if (!empty($itemName)) {
                        $qty = $_POST['item_qtys'][$index] ?? 1;
                        $unit = $_POST['item_units'][$index] ?? 'Koli';
                        $weight = $_POST['item_weights'][$index] ?? 0;
                        $pdo->prepare("INSERT INTO shipment_items (shipment_id, item_name, quantity, unit, weight_kg) VALUES (?, ?, ?, ?, ?)")
                            ->execute([$_POST['shipment_id'], $itemName, $qty, $unit, $weight]);
                    }
                }
            }

            // Sync Invoice Amount
            $stmt = $pdo->prepare("SELECT price_per_kg FROM routes WHERE id = ?");
            $stmt->execute([$_POST['route_id']]);
            $pricePerKg = $stmt->fetchColumn();
            $totalAmount = $pricePerKg * $_POST['weight'];

            $stmt = $pdo->prepare("UPDATE invoices SET amount = ? WHERE shipment_id = ?");
            $stmt->execute([$totalAmount, $_POST['shipment_id']]);

            // Sync Train Status
            $stmt = $pdo->prepare("UPDATE train_schedules
                                SET status = CASE WHEN (SELECT COALESCE(SUM(weight_kg), 0) FROM shipments WHERE train_id = train_schedules.id AND LOWER(TRIM(status)) != 'arrived' AND deleted_at IS NULL) >= capacity_kg THEN 'full' ELSE 'available' END
                                WHERE id = ?");
            $stmt->execute([$_POST['train_id']]);

            // Automatic WhatsApp Notification via Fonnte
            if (isset($_POST['shipment_id']) && in_array($newStatus, ['shipped', 'transit', 'arrived'])) {
                // Construct item list for WA message
                $waItems = [];
                if (isset($_POST['items'])) {
                    foreach ($_POST['items'] as $idx => $name) {
                        $waItems[] = ($_POST['item_qtys'][$idx] ?? 1) . " " . ($_POST['item_units'][$idx] ?? 'Koli') . " " . $name;
                    }
                }
                $itemListWA = !empty($waItems) ? "(" . implode(", ", $waItems) . ")" : "";

                // Generate PDF (Resi for Shipped/Transit, POD for Arrived)
                if ($newStatus == 'arrived') {
                    $pdfPath = generatePODPDF($_POST['shipment_id'], $pdo);
                    $docLabel = "Surat Terima Barang (POD)";
                    $statusMsg = "sudah sampai di stasiun tujuan dan TELAH DITERIMA";
                } else {
                    $pdfPath = generateResiPDF($_POST['shipment_id'], $pdo);
                    $docLabel = "Resi Digital";
                    $statusMsg = ($newStatus == 'transit') ? "sedang dalam proses transit di stasiun perantara" : "sedang dalam perjalanan";
                }

                $pdfUrl = $pdfPath ? "http://" . $_SERVER['HTTP_HOST'] . "/" . $pdfPath : null;

                // Message for Receiver
                $messageWA = "Halo " . $_POST['receiver'] . ", paket Anda " . $itemListWA . " dengan Resi " . $_POST['resi_number'] . " " . $statusMsg . ".\n\nBerikut terlampir " . $docLabel . " Anda.";
                sendWA($_POST['receiver_phone'], $messageWA, $pdfUrl);

                // Notification for Sender
                $messageSender = "Halo " . $_POST['sender'] . ", paket Anda " . $itemListWA . " dengan Resi " . $_POST['resi_number'] . " " . $statusMsg . ".\n\nBerikut terlampir " . $docLabel . " sebagai bukti transaksi selesai.";
                sendWA($_POST['sender_phone'], $messageSender, $pdfUrl);
            }

            // Remove the premature redirects
        } else {
            // CREATE ACTION
            $newStatus = strtolower(trim($_POST['status'] ?? 'waiting'));
            $isChangingToArrived = ($newStatus == 'arrived');

            if (!$isChangingToArrived) {
                // Check Capacity first
                $stmt = $pdo->prepare("SELECT capacity_kg FROM train_schedules WHERE id = ?");
                $stmt->execute([$_POST['train_id']]);
                $capacity = $stmt->fetchColumn();

                $stmt = $pdo->prepare("SELECT COALESCE(SUM(weight_kg), 0) FROM shipments WHERE train_id = ? AND LOWER(TRIM(status)) != 'arrived' AND deleted_at IS NULL");
                $stmt->execute([$_POST['train_id']]);
                $currentWeight = $stmt->fetchColumn();

                if ($currentWeight + $_POST['weight'] > $capacity) {
                    throw new Exception("Kapasitas kereta tidak mencukupi! Tersisa " . ($capacity - $currentWeight) . " KG.");
                }
            }

            // Auto-Generate Resi Number
            $datePrefix = "LNE-" . date('Ymd') . "-";
            $stmt = $pdo->prepare("SELECT resi_number FROM shipments WHERE resi_number LIKE ? ORDER BY resi_number DESC LIMIT 1");
            $stmt->execute([$datePrefix . '%']);
            $lastResi = $stmt->fetchColumn();

            if ($lastResi) {
                $lastNum = (int)substr($lastResi, -4);
                $nextNum = str_pad($lastNum + 1, 4, '0', STR_PAD_LEFT);
            } else {
                $nextNum = '0001';
            }
            $resiNumber = $datePrefix . $nextNum;

            // Handle Photo Upload
            $photoPath = null;
            if (isset($_FILES['goods_photo']) && $_FILES['goods_photo']['error'] == 0) {
                $ext = pathinfo($_FILES['goods_photo']['name'], PATHINFO_EXTENSION);
                $fileName = "goods_" . time() . "_" . $resiNumber . "." . $ext;
                $targetPath = "uploads/goods/" . $fileName;
                if (move_uploaded_file($_FILES['goods_photo']['tmp_name'], $targetPath)) {
                    $photoPath = $targetPath;
                }
            }

            $stmt = $pdo->prepare("INSERT INTO shipments (resi_number, sender_name, sender_phone, receiver_name, receiver_phone, goods_photo, weight_kg, route_id, train_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $resiNumber, 
                $_POST['sender'], 
                $_POST['sender_phone'],
                $_POST['receiver'], 
                $_POST['receiver_phone'],
                $photoPath,
                $_POST['weight'], 
                $_POST['route_id'], 
                $_POST['train_id']
            ]);
            $shipmentId = $pdo->lastInsertId();

            // Insert Shipment Items
            if (isset($_POST['items']) && is_array($_POST['items'])) {
                foreach ($_POST['items'] as $index => $itemName) {
                    if (!empty($itemName)) {
                        $qty = $_POST['item_qtys'][$index] ?? 1;
                        $unit = $_POST['item_units'][$index] ?? 'Koli';
                        $weight = $_POST['item_weights'][$index] ?? 0;
                        $pdo->prepare("INSERT INTO shipment_items (shipment_id, item_name, quantity, unit, weight_kg) VALUES (?, ?, ?, ?, ?)")
                            ->execute([$shipmentId, $itemName, $qty, $unit, $weight]);
                    }
                }
            }

            // Automatic WhatsApp Notification for Creation disabled (User request)
            // PDF Resi will be generated later upon status update

            $stmt = $pdo->prepare("SELECT price_per_kg FROM routes WHERE id = ?");
            $stmt->execute([$_POST['route_id']]);
            $pricePerKg = $stmt->fetchColumn();
            $totalAmount = $pricePerKg * $_POST['weight'];
            
            $invoiceNum = "INV-" . time() . "-" . $resiNumber;
            $dueDate = date('Y-m-d', strtotime('+7 days'));

            $stmt = $pdo->prepare("INSERT INTO invoices (shipment_id, invoice_number, amount, due_date) VALUES (?, ?, ?, ?)");
            $stmt->execute([$shipmentId, $invoiceNum, $totalAmount, $dueDate]);
        }

        // Sync Train Status Logic
        $affectedTrainId = $_POST['train_id'] ?? $trainId; 
        if (!$affectedTrainId && isset($_POST['shipment_id'])) {
            $stmt = $pdo->prepare("SELECT train_id FROM shipments WHERE id = ?");
            $stmt->execute([$_POST['shipment_id']]);
            $affectedTrainId = $stmt->fetchColumn();
        }

        if ($affectedTrainId) {
            $stmt = $pdo->prepare("UPDATE train_schedules
                                SET status = CASE WHEN (SELECT COALESCE(SUM(weight_kg), 0) FROM shipments WHERE train_id = train_schedules.id AND LOWER(TRIM(status)) != 'arrived' AND deleted_at IS NULL) >= capacity_kg THEN 'full' ELSE 'available' END
                                WHERE id = ?");
            $stmt->execute([$affectedTrainId]);
        }

        $message = (isset($_POST['shipment_id']) && !empty($_POST['shipment_id'])) ? "Pengiriman & Invoice berhasil diperbarui!" : "Pengiriman berhasil dicatat & Invoice otomatis dibuat!";

        $pdo->commit();
        header("Location: shipping.php?msg=" . urlencode($message));
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "Gagal: " . $e->getMessage();
    }
}

include 'includes/head.php'; 
include 'includes/sidebar.php'; 

// Fetch Data
$stmt = $pdo->query("SELECT s.*, r.origin, r.destination, ts.train_name, ts.capacity_kg,
                    (SELECT COALESCE(SUM(weight_kg), 0) FROM shipments s2 WHERE s2.train_id = s.train_id AND LOWER(TRIM(s2.status)) != 'arrived' AND s2.deleted_at IS NULL) as used_kg,
                    (SELECT GROUP_CONCAT(CONCAT(quantity, ' ', unit, ' ', item_name) SEPARATOR ', ') FROM shipment_items WHERE shipment_id = s.id) as item_list
                    FROM shipments s 
                    JOIN routes r ON s.route_id = r.id 
                    LEFT JOIN train_schedules ts ON s.train_id = ts.id 
                    WHERE s.deleted_at IS NULL 
                    ORDER BY s.created_at DESC");
$shipments = $stmt->fetchAll();
$routes = $pdo->query("SELECT * FROM routes")->fetchAll();
$schedules = $pdo->query("SELECT ts.*, r.origin, r.destination, 
                             COALESCE(SUM(CASE WHEN LOWER(TRIM(s.status)) != 'arrived' AND s.deleted_at IS NULL THEN s.weight_kg ELSE 0 END), 0) as used_kg 
                         FROM train_schedules ts 
                         JOIN routes r ON ts.route_id = r.id 
                         LEFT JOIN shipments s ON ts.id = s.train_id 
                         GROUP BY ts.id")->fetchAll();
?>

<div class="content-wrapper">
    <?php include 'includes/header.php'; ?>

    <div class="container">
        <div style="margin-bottom: 2rem; display: flex; align-items: center; justify-content: space-between;">
            <div>
                <h1 style="font-size: 1.75rem; font-weight: 800; color: var(--primary);">Manajemen Pengiriman</h1>
                <p style="color: var(--text-muted); margin-top: 0.25rem;">Pencatatan muatan logistik dan integrasi penagihan otomatis.</p>
            </div>
            <button class="btn btn-primary" onclick="openAddModal()">
                <i class="fas fa-plus"></i> Input Pengiriman Baru
            </button>
        </div>

        <?php if($message): ?>
            <div style="padding: 1rem; background: <?php echo strpos($message, 'Gagal') !== false ? '#fee2e2' : '#dcfce7'; ?>; 
                        color: <?php echo strpos($message, 'Gagal') !== false ? '#991b1b' : '#166534'; ?>; 
                        border-radius: 8px; margin-bottom: 1.5rem; border: 1px solid <?php echo strpos($message, 'Gagal') !== false ? '#fecaca' : '#bbf7d0'; ?>;">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if(isset($_GET['msg']) && $_GET['msg'] == 'deleted'): ?>
            <div style="padding: 1rem; background: #dcfce7; color: #166534; border-radius: 8px; margin-bottom: 1.5rem; border: 1px solid #bbf7d0;">
                Data pengiriman berhasil dihapus!
            </div>
        <?php endif; ?>

        <div class="card" style="padding: 0; overflow: hidden;">
            <table class="file-table" style="width: 100%;">
                <thead style="background: #f8fafc;">
                    <tr>
                        <th style="padding: 1rem 1.5rem;">No. Resi</th>
                        <th>Pengirim / Penerima</th>
                        <th>Rute & Kereta</th>
                        <th>Barang / Berat</th>
                        <th>Status</th>
                        <th style="text-align: right; padding-right: 1.5rem;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($shipments)): ?>
                        <tr><td colspan="6" style="text-align: center; padding: 2rem; color: var(--text-muted);">Belum ada data pengiriman.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($shipments as $s): ?>
                    <tr class="file-row">
                        <td style="padding: 1.25rem 1.5rem;">
                            <div style="font-weight: 700; color: var(--primary); font-size: 1rem;"><?php echo $s['resi_number']; ?></div>
                            <div style="font-size: 0.7rem; color: var(--text-muted);">Terdaftar: <?php echo date('d M Y', strtotime($s['created_at'])); ?></div>
                        </td>
                        <td>
                            <div style="font-size: 0.875rem;"><strong>S:</strong> <?php echo $s['sender_name']; ?></div>
                            <div style="font-size: 0.875rem;"><strong>R:</strong> <?php echo $s['receiver_name']; ?></div>
                        </td>
                        <td>
                            <div style="font-size: 0.875rem; font-weight: 600;"><?php echo $s['origin']; ?> - <?php echo $s['destination']; ?></div>
                            <div style="font-size: 0.75rem; color: var(--primary);"><?php echo $s['train_name'] ?: 'N/A'; ?></div>
                        </td>
                        <td>
                            <div style="font-size: 0.875rem; font-weight: 600; color: #1e293b;">
                                <?php echo $s['item_list'] ?: 'Tanpa Detail Barang'; ?>
                            </div>
                            <div style="font-size: 0.875rem; font-weight: 700; color: var(--primary); margin-top: 0.25rem;"><?php echo number_format($s['weight_kg']); ?> KG</div>
                        </td>
                        <td>
                            <?php 
                                $status = strtoupper($s['status'] ?? 'WAITING');
                                $statusBg = 'rgba(100, 116, 139, 0.1)';
                                $statusColor = '#64748b';

                                if ($status == 'SHIPPED') {
                                    $statusBg = 'rgba(245, 158, 11, 0.1)';
                                    $statusColor = '#b45309';
                                } elseif ($status == 'TRANSIT') {
                                    $statusBg = 'rgba(139, 92, 246, 0.1)';
                                    $statusColor = '#6d28d9';
                                } elseif ($status == 'ARRIVED') {
                                    $statusBg = 'rgba(16, 185, 129, 0.1)';
                                    $statusColor = '#166534';
                                } elseif ($status == 'WAITING') {
                                    $statusBg = 'rgba(59, 130, 246, 0.1)';
                                    $statusColor = '#1d4ed8';
                                }
                            ?>
                            <span class="status-badge" style="background: <?php echo $statusBg; ?>; color: <?php echo $statusColor; ?>;">
                                <?php echo $status; ?>
                            </span>
                        </td>
                        <td style="text-align: right; padding-right: 1.5rem;">
                            <div class="file-actions" style="justify-content: flex-end; gap: 0.5rem;">
                                <a href="resi_print.php?id=<?php echo $s['id']; ?>" class="action-btn" title="Cetak Resi" style="color: var(--primary);"><i class="fas fa-receipt"></i></a>
                                <a href="surat_jalan_print.php?id=<?php echo $s['id']; ?>" class="action-btn" title="Cetak Surat Jalan" style="color: var(--info);"><i class="fas fa-file-contract"></i></a>
                                <a href="manifest_print.php?id=<?php echo $s['id']; ?>" class="action-btn" title="Cetak Manifest Internal" style="color: #64748b;"><i class="fas fa-truck-ramp-box"></i></a>
                                <?php if ($s['status'] == 'arrived'): ?>
                                    <a href="pod_print.php?id=<?php echo $s['id']; ?>" class="action-btn" title="Cetak Surat Terima (POD)" style="color: #10b981;"><i class="fas fa-file-signature"></i></a>
                                <?php endif; ?>
                                <a href="tracking.php?resi=<?php echo $s['resi_number']; ?>" class="action-btn" title="Track"><i class="fas fa-location-dot"></i></a>
                                <button class="action-btn" title="Edit" onclick='openEditModal(<?php echo json_encode($s); ?>)'><i class="fas fa-pen"></i></button>
                                <a href="shipping.php?delete=<?php echo $s['id']; ?>" class="action-btn" style="color: #ef4444;" title="Hapus" onclick="return confirm('Menghapus data pengiriman juga akan menghapus Invoice terkait. Lanjutkan?')"><i class="fas fa-trash"></i></a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Add/Edit Shipment -->
<div id="modalShipment" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center;">
    <div class="card" style="width: 850px; padding: 2.5rem; max-height: 95vh; overflow-y: auto;">
        <h3 id="modalTitle" style="margin-bottom: 1.5rem;">Input Pengiriman Logistik Baru</h3>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="shipment_id" id="shipment_id">
            <div class="grid grid-cols-2" style="gap: 1.5rem; margin-bottom: 1.5rem;">
                <div>
                    <label style="display: block; font-size: 0.875rem; margin-bottom: 0.5rem; font-weight: 600;">Nomor Resi</label>
                    <input type="text" name="resi_number" id="resi_number" placeholder="AUTO-GENERATED" style="width: 100%; padding: 0.6rem; border: 1px solid var(--border-color); border-radius: 6px; background: #f8fafc;" readonly>
                </div>
            </div>

            <div style="margin-bottom: 1.5rem; background: #f8fafc; padding: 1.5rem; border-radius: 8px; border: 1px dashed #cbd5e1;">
                <label style="display: block; font-size: 0.875rem; margin-bottom: 1rem; font-weight: 700; color: var(--primary);">
                    <i class="fas fa-boxes-stacked"></i> Rincian Daftar Barang
                </label>
                <div id="itemsContainer">
                    <!-- Dynamic Rows Here -->
                </div>
                <button type="button" onclick="addItemRow()" class="btn" style="background: white; border: 1px solid var(--primary); color: var(--primary); font-size: 0.8rem; margin-top: 1rem;">
                    <i class="fas fa-plus-circle"></i> Tambah Baris Barang
                </button>
            </div>

            <div class="grid grid-cols-2" style="gap: 2rem; margin-bottom: 1.5rem;">
                <div>
                    <label style="display: block; font-size: 0.875rem; margin-bottom: 0.5rem; font-weight: 600;">Nama & WhatsApp Pengirim</label>
                    <div style="display: flex; gap: 0.5rem;">
                        <input type="text" name="sender" id="sender_name" placeholder="Nama Pengirim" required style="width: 60%; padding: 0.6rem; border: 1px solid var(--border-color); border-radius: 6px;">
                        <input type="text" name="sender_phone" id="sender_phone" placeholder="628xxx" required style="width: 40%; padding: 0.6rem; border: 1px solid var(--border-color); border-radius: 6px;">
                    </div>
                </div>
                <div>
                    <label style="display: block; font-size: 0.875rem; margin-bottom: 0.5rem; font-weight: 600;">Nama & WhatsApp Penerima</label>
                    <div style="display: flex; gap: 0.5rem;">
                        <input type="text" name="receiver" id="receiver_name" placeholder="Nama Penerima" required style="width: 60%; padding: 0.6rem; border: 1px solid var(--border-color); border-radius: 6px;">
                        <input type="text" name="receiver_phone" id="receiver_phone" placeholder="628xxx" required style="width: 40%; padding: 0.6rem; border: 1px solid var(--border-color); border-radius: 6px;">
                    </div>
                </div>
            </div>

            <div style="margin-bottom: 1.5rem;">
                <label style="display: block; font-size: 0.875rem; margin-bottom: 0.5rem; font-weight: 600;">Foto Bukti Barang</label>
                <input type="file" name="goods_photo" id="goods_photo" accept="image/*" style="width: 100%; padding: 0.6rem; border: 1px solid var(--border-color); border-radius: 6px; background: white;">
                <input type="hidden" name="existing_photo" id="existing_photo">
                <div id="photoPreview" style="margin-top: 0.5rem; display: none;">
                    <span style="font-size: 0.75rem; color: var(--text-muted);">Foto saat ini:</span><br>
                    <img id="currentPhoto" src="" style="height: 60px; border-radius: 4px; margin-top: 5px;">
                </div>
            </div>

            <div class="grid grid-cols-2" style="gap: 1.5rem; margin-bottom: 1.5rem;">
                <div>
                    <label style="display: block; font-size: 0.875rem; margin-bottom: 0.5rem; font-weight: 600;">Total Berat (KG)</label>
                    <input type="text" id="weight_display" placeholder="Otomatis Terisi" style="width: 100%; padding: 0.6rem; border: 1px solid var(--border-color); border-radius: 6px; background: #f8fafc;" readonly>
                    <input type="hidden" name="weight" id="weight_kg">
                </div>
                <div style="grid-column: span 1;">
                    <label style="display: block; font-size: 0.875rem; margin-bottom: 0.5rem; font-weight: 600;">Pilih Rute & Jadwal Kereta</label>
                    <select name="train_id" id="train_select" required style="width: 100%; padding: 0.6rem; border: 1px solid var(--border-color); border-radius: 6px; background: white;">
                        <?php foreach($schedules as $sch): ?>
                            <?php 
                                $available = $sch['capacity_kg'] - $sch['used_kg']; 
                                $isFull = $available <= 0;
                            ?>
                            <option value="<?php echo $sch['id']; ?>" 
                                    data-route="<?php echo $sch['route_id']; ?>"
                                    data-available="<?php echo $available; ?>"
                                    class="train-option"
                                    data-full="<?php echo $isFull ? '1' : '0'; ?>">
                                <?php echo $sch['train_name']; ?> 
                                (Sisa: <?php echo number_format($available); ?> KG)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="route_id" id="route_id_hidden">
                </div>
            </div>

            <div id="statusControl" style="display: none; margin-bottom: 1.5rem;">
                <label style="display: block; font-size: 0.875rem; margin-bottom: 0.5rem; font-weight: 600;">Update Status Pengiriman</label>
                <select name="status" id="status" required style="width: 100%; padding: 0.6rem; border: 1px solid var(--border-color); border-radius: 6px; background: white;">
                    <option value="waiting">WAITING</option>
                    <option value="shipped">SHIPPED</option>
                    <option value="transit">TRANSIT</option>
                    <option value="arrived">ARRIVED</option>
                </select>
            </div>

            <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
                <button type="button" class="btn btn-outline" onclick="closeModal()">Batal</button>
                <button type="submit" class="btn btn-primary" id="btnSubmit" style="padding-left: 2rem; padding-right: 2rem;">Simpan Pengiriman</button>
            </div>
        </form>
    </div>
</div>

<script>
    const trainSelect = document.getElementById('train_select');
    const routeHidden = document.getElementById('route_id_hidden');

    trainSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        routeHidden.value = selectedOption.getAttribute('data-route');
    });

    function calculateTotalWeight() {
        const rows = document.querySelectorAll('#itemsContainer > div');
        let grandTotal = 0;
        rows.forEach(row => {
            const qty = parseFloat(row.querySelector('input[name="item_qtys[]"]').value) || 0;
            const weightInput = row.querySelector('input[name="item_weights[]"]').value;
            // Handle comma for calculation
            const unitWeight = parseFloat(weightInput.replace(',', '.')) || 0;
            
            grandTotal += (qty * unitWeight);
        });
        
        // Use 2 decimal places for accuracy
        grandTotal = Math.round(grandTotal * 100) / 100;
        
        document.getElementById('weight_kg').value = grandTotal;
        // Display with comma for Indonesian format
        document.getElementById('weight_display').value = grandTotal.toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
    }

    function addItemRow(name = '', qty = 1, unit = 'Koli', weight = 0) {
        const container = document.getElementById('itemsContainer');
        const row = document.createElement('div');
        row.style.display = 'flex';
        row.style.gap = '8px';
        row.style.marginBottom = '8px';
        row.style.alignItems = 'center';
        
        const units = ['Koli', 'Dus', 'Pcs', 'Pallet', 'Pack', 'Box', 'Karung'];
        let options = '';
        units.forEach(u => {
            options += `<option value="${u}" ${u === unit ? 'selected' : ''}>${u}</option>`;
        });

        row.innerHTML = `
            <div style="flex: 2;">
                <input type="text" name="items[]" value="${name}" placeholder="Nama Barang" required style="width: 100%; padding: 0.5rem; border: 1px solid var(--border-color); border-radius: 6px; font-size: 0.825rem;">
            </div>
            <div style="width: 60px;">
                <input type="number" name="item_qtys[]" value="${qty}" min="1" required oninput="calculateTotalWeight()" style="width: 100%; padding: 0.5rem; border: 1px solid var(--border-color); border-radius: 6px; font-size: 0.825rem; text-align: center;">
            </div>
            <div style="width: 80px;">
                <select name="item_units[]" style="width: 100%; padding: 0.5rem; border: 1px solid var(--border-color); border-radius: 6px; font-size: 0.75rem; background: #fff;">
                    ${options}
                </select>
            </div>
            <div style="width: 80px;">
                <input type="number" name="item_weights[]" value="${weight}" step="0.01" min="0" required oninput="calculateTotalWeight()" placeholder="Berat" style="width: 100%; padding: 0.5rem; border: 1px solid var(--border-color); border-radius: 6px; font-size: 0.825rem; text-align: center;">
            </div>
            <div style="width: 25px; text-align: center;">
                <button type="button" onclick="this.parentElement.parentElement.remove(); calculateTotalWeight();" style="color: #ef4444; border: none; background: none; cursor: pointer; font-size: 1rem;">
                    <i class="fas fa-times-circle"></i>
                </button>
            </div>
        `;
        container.appendChild(row);
        calculateTotalWeight();
    }

    function openAddModal() {
        document.getElementById('modalTitle').innerText = 'Input Pengiriman Logistik Baru';
        document.getElementById('shipment_id').value = '';
        
        const resiInput = document.getElementById('resi_number');
        resiInput.value = '';
        resiInput.placeholder = 'AUTO-GENERATED';
        resiInput.readOnly = true;
        resiInput.style.background = '#f8fafc';

        document.getElementById('itemsContainer').innerHTML = '';
        addItemRow(); // Default one row
        
        document.getElementById('sender_name').value = '';
        document.getElementById('sender_phone').value = '';
        document.getElementById('receiver_name').value = '';
        document.getElementById('receiver_phone').value = '';
        document.getElementById('weight_kg').value = '';
        document.getElementById('existing_photo').value = '';
        document.getElementById('photoPreview').style.display = 'none';
        document.getElementById('statusControl').style.display = 'none';
        document.getElementById('btnSubmit').innerText = 'Simpan & Buat Invoice';
        document.getElementById('weight_display').value = '';
        document.getElementById('modalShipment').style.display = 'flex';
        // Trigger change for initial value
        trainSelect.dispatchEvent(new Event('change'));
    }

    function openEditModal(data) {
        document.getElementById('modalTitle').innerText = 'Edit Data Pengiriman';
        document.getElementById('shipment_id').value = data.id;
        
        const resiInput = document.getElementById('resi_number');
        resiInput.value = data.resi_number;
        resiInput.readOnly = false;
        resiInput.style.background = 'white';
        
        // Fetch items for edit
        fetch(`get_shipment_items.php?id=${data.id}`)
            .then(res => res.json())
            .then(items => {
                const container = document.getElementById('itemsContainer');
                container.innerHTML = '';
                if (items.length === 0) {
                    addItemRow();
                } else {
                    items.forEach(item => addItemRow(item.item_name, item.quantity, item.unit, item.weight_kg));
                }
            });
        document.getElementById('sender_name').value = data.sender_name;
        document.getElementById('sender_phone').value = data.sender_phone;
        document.getElementById('receiver_name').value = data.receiver_name;
        document.getElementById('receiver_phone').value = data.receiver_phone;

        if (data.goods_photo) {
            document.getElementById('existing_photo').value = data.goods_photo;
            document.getElementById('currentPhoto').src = data.goods_photo;
            document.getElementById('photoPreview').style.display = 'block';
        } else {
            document.getElementById('existing_photo').value = '';
            document.getElementById('photoPreview').style.display = 'none';
        }
        
        const weight = parseFloat(data.weight_kg);
        document.getElementById('weight_kg').value = weight;
        document.getElementById('weight_display').value = weight.toLocaleString('id-ID');
        
        document.getElementById('train_select').value = data.train_id;
        document.getElementById('status').value = data.status;
        document.getElementById('statusControl').style.display = 'block';
        document.getElementById('btnSubmit').innerText = 'Update Pengiriman';
        document.getElementById('modalShipment').style.display = 'flex';
        
        // Disable full trains except if they were already selected
        Array.from(trainSelect.options).forEach(opt => {
            if (opt.getAttribute('data-full') === '1' && opt.value != data.train_id) {
                opt.disabled = true;
                opt.style.color = 'red';
            } else {
                opt.disabled = false;
                opt.style.color = '';
            }
        });

        // Trigger change for updated value
        trainSelect.dispatchEvent(new Event('change'));
    }

    function closeModal() {
        document.getElementById('modalShipment').style.display = 'none';
    }

    // Input Formatter for Thousands Separator
    const weightDisplay = document.getElementById('weight_display');
    const weightHidden = document.getElementById('weight_kg');

    weightDisplay.addEventListener('input', function(e) {
        // Remove non-digits
        let value = this.value.replace(/[^0-9]/g, '');
        
        if (value) {
            // Update hidden numeric value
            weightHidden.value = value;
            // Update display with formatting
            this.value = parseInt(value).toLocaleString('id-ID');
        } else {
            weightHidden.value = '';
            this.value = '';
        }
    });
</script>

<?php include 'includes/footer.php'; ?>
