<?php
require_once 'includes/db.php';

if (!isset($_GET['id'])) {
    die("ID Pengiriman tidak ditemukan.");
}

$id = $_GET['id'];

// Fetch detailed data
$stmt = $pdo->prepare("SELECT s.*, r.origin, r.destination, ts.train_name, ts.departure_time, ts.arrival_time 
                        FROM shipments s 
                        JOIN routes r ON s.route_id = r.id 
                        LEFT JOIN train_schedules ts ON s.train_id = ts.id
                        WHERE s.id = ?");
$stmt->execute([$id]);
$s = $stmt->fetch();

if (!$s) {
    die("Data pengiriman tidak valid.");
}

// Fetch Items Summary
$stmtItems = $pdo->prepare("SELECT GROUP_CONCAT(CONCAT(quantity, ' ', unit, ' ', item_name) SEPARATOR ', ') as item_list FROM shipment_items WHERE shipment_id = ?");
$stmtItems->execute([$id]);
$itemList = $stmtItems->fetchColumn();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Manifest Muatan - <?php echo $s['resi_number']; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #0f172a; --border: #cbd5e1; }
        body { font-family: 'Inter', sans-serif; padding: 30px; font-size: 13px; color: #334155; }
        .container { border: 2px solid var(--primary); padding: 20px; }
        .header { display: flex; justify-content: space-between; border-bottom: 2px solid var(--primary); padding-bottom: 15px; margin-bottom: 20px; }
        .title { text-align: center; margin-bottom: 30px; }
        .title h1 { margin: 0; font-size: 18px; text-transform: uppercase; }
        
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid var(--border); padding: 8px; text-align: left; }
        th { background: #f1f5f9; }

        .sign-grid { display: grid; grid-template-cols: 1fr 1fr; gap: 40px; margin-top: 50px; text-align: center; }
        .sign-box { height: 100px; border-bottom: 1px solid var(--primary); margin-bottom: 5px; }
        
        .no-print { position: fixed; top: 10px; right: 10px; background: #0f172a; color: white; padding: 8px 15px; border-radius: 4px; text-decoration: none; cursor: pointer; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <a onclick="window.print()" class="no-print">Cetak Manifest</a>
    
    <div class="container">
        <div class="header">
            <div style="display: flex; align-items: center; gap: 15px;">
                <img src="img/logo.png" alt="Logo" style="height: 50px; width: auto;">
                <div>
                    <div style="font-weight: 800; font-size: 16px;">PT LINTAS NUSANTARA EKSPEDISI</div>
                    <div>Logistics & Railway Express Service</div>
                </div>
            </div>
            <div style="text-align: right;">
                <div>Nomor Manifest: MNF/<?php echo date('Ymd', strtotime($s['created_at'])); ?>/<?php echo $s['id']; ?></div>
                <div>Tanggal: <?php echo date('d/m/Y'); ?></div>
            </div>
        </div>

        <div class="title">
            <h1>MANIFEST MUATAN & SERAH TERIMA ANTAR STASIUN</h1>
        </div>

        <table>
            <tr>
                <th colspan="2">Informasi Perjalanan</th>
                <th colspan="2">Informasi Barang</th>
            </tr>
            <tr>
                <td>Stasiun Asal</td>
                <td><strong><?php echo strtoupper($s['origin']); ?></strong></td>
                <td>Nomor Resi</td>
                <td><strong><?php echo $s['resi_number']; ?></strong></td>
            </tr>
            <tr>
                <td>Stasiun Tujuan</td>
                <td><strong><?php echo strtoupper($s['destination']); ?></strong></td>
                <td>Jenis Barang</td>
                <td><?php echo $itemList ?: 'Tanpa Detail Barang'; ?></td>
            </tr>
            <tr>
                <td>Kereta Api</td>
                <td><?php echo $s['train_name']; ?></td>
                <td>Berat (KG)</td>
                <td><?php echo number_format($s['weight_kg']); ?> KG</td>
            </tr>
            <tr>
                <td>Estimasi Berangkat</td>
                <td><?php echo $s['departure_time']; ?></td>
                <td>Pengirim</td>
                <td><?php echo $s['sender_name']; ?></td>
            </tr>
        </table>

        <p style="font-style: italic;">Dokumen ini digunakan sebagai bukti serah terima tanggung jawab operasional antara petugas stasiun asal dan petugas stasiun tujuan.</p>

        <div class="sign-grid">
            <div>
                <div><strong>STASIUN ASAL (<?php echo $s['origin']; ?>)</strong></div>
                <div style="font-size: 11px; margin-bottom: 40px;">Diserahkan oleh,</div>
                <div class="sign-box"></div>
                <div>( ........................................... )</div>
                <div style="font-size: 10px;">Petugas Operasional</div>
            </div>
            <div>
                <div><strong>STASIUN TUJUAN (<?php echo $s['destination']; ?>)</strong></div>
                <div style="font-size: 11px; margin-bottom: 40px;">Diterima oleh,</div>
                <div class="sign-box"></div>
                <div>( ........................................... )</div>
                <div style="font-size: 10px;">Petugas Operasional</div>
            </div>
        </div>
        
        <div style="margin-top: 30px; border-top: 1px dashed #cbd5e1; padding-top: 10px; font-size: 10px; color: #64748b;">
            * Putih: Arsip Kantor Pusat | Kuning: Stasiun Asal | Merah: Stasiun Tujuan
        </div>
    </div>
</body>
</html>
