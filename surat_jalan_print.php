<?php
require_once 'includes/db.php';

if (!isset($_GET['id'])) {
    die("ID Pengiriman tidak ditemukan.");
}

$id = $_GET['id'];

// Handle Archiving Action
if (isset($_POST['archive'])) {
    try {
        $stmt = $pdo->prepare("SELECT resi_number FROM shipments WHERE id = ?");
        $stmt->execute([$id]);
        $resi = $stmt->fetchColumn();
        
        $fileName = "Surat_Jalan_" . $resi . ".html";
        $category = 'surat_jalan';
        
        // Check if already archived to avoid duplicates
        $check = $pdo->prepare("SELECT id FROM documents WHERE shipment_id = ? AND category = ? AND deleted_at IS NULL");
        $check->execute([$id, $category]);
        
        if (!$check->fetch()) {
            $stmt = $pdo->prepare("INSERT INTO documents (shipment_id, file_name, file_path, category, uploaded_by) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$id, $fileName, "surat_jalan_print.php?id=" . $id, $category, 1]);
            echo "<script>alert('Dokumen berhasil diarsipkan ke Penjelajah Dokumen!');</script>";
        } else {
            echo "<script>alert('Dokumen ini sudah ada di arsip.');</script>";
        }
    } catch (Exception $e) {
        echo "<script>alert('Gagal mengarsipkan: " . $e->getMessage() . "');</script>";
    }
}

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

// Fetch Items
$stmtItems = $pdo->prepare("SELECT * FROM shipment_items WHERE shipment_id = ?");
$stmtItems->execute([$id]);
$items = $stmtItems->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Surat Jalan - <?php echo $s['resi_number']; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #1e3a8a; --text: #1e293b; --muted: #64748b; --border: #e2e8f0; }
        body { font-family: 'Inter', sans-serif; color: var(--text); margin: 0; padding: 40px; line-height: 1.5; background: #f8fafc; }
        .doc-container { background: white; max-width: 900px; margin: 0 auto; padding: 50px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); border-radius: 8px; position: relative; }
        
        .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid var(--primary); padding-bottom: 20px; margin-bottom: 30px; }
        .logo-section h1 { margin: 0; font-size: 22px; color: var(--primary); font-weight: 800; text-transform: uppercase; letter-spacing: -0.5px; }
        .logo-section p { margin: 4px 0 0; font-size: 12px; color: var(--muted); }
        
        .title-section { text-align: right; }
        .title-section h2 { margin: 0; font-size: 28px; color: var(--muted); font-weight: 300; text-transform: uppercase; }
        .title-section .doc-no { font-weight: 700; font-size: 14px; margin-top: 5px; color: var(--primary); }
        
        .info-grid { display: grid; grid-template-cols: 1fr 1fr; gap: 40px; margin-bottom: 40px; }
        .info-box h3 { font-size: 11px; text-transform: uppercase; color: var(--muted); border-bottom: 1px solid var(--border); padding-bottom: 5px; margin-bottom: 10px; letter-spacing: 0.1em; }
        .info-box p { margin: 3px 0; font-size: 13.5px; }
        .label { color: var(--muted); width: 100px; display: inline-block; font-size: 12px; }
        
        .cargo-table { width: 100%; border-collapse: collapse; margin-bottom: 40px; }
        .cargo-table th { background: #f1f5f9; text-align: left; padding: 12px; font-size: 11px; text-transform: uppercase; color: var(--muted); border: 1px solid var(--border); }
        .cargo-table td { padding: 12px; border: 1px solid var(--border); font-size: 14px; }
        
        .rail-info { background: #f8fafc; padding: 20px; border-radius: 8px; border: 1px solid var(--border); margin-bottom: 40px; display: flex; justify-content: space-between; gap: 20px; }
        .rail-item { flex: 1; }
        .rail-item span { display: block; font-size: 10px; color: var(--muted); text-transform: uppercase; margin-bottom: 4px; }
        .rail-item strong { font-size: 13px; color: var(--primary); }
        
        .signature-grid { display: grid; grid-template-cols: 1fr 1fr 1fr; gap: 20px; text-align: center; margin-top: 60px; }
        .sig-box { height: 120px; display: flex; flex-direction: column; justify-content: space-between; }
        .sig-box p { margin: 0; font-size: 12px; color: var(--muted); }
        .sig-box .name { font-weight: 700; color: var(--text); border-top: 1px solid var(--text); padding-top: 5px; margin: 0 20px; }
        
        .toolbar { position: fixed; top: 20px; right: 20px; display: flex; flex-direction: column; gap: 10px; }
        .btn-tool { padding: 10px 20px; border-radius: 999px; border: none; background: white; color: var(--text); font-weight: 600; cursor: pointer; box-shadow: 0 4px 12px rgba(0,0,0,0.1); display: flex; align-items: center; gap: 8px; font-size: 13px; transition: all 0.2s; text-decoration: none; }
        .btn-tool:hover { transform: translateY(-2px); background: var(--border); }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: #1e3a8a; filter: brightness(1.2); }
        .btn-success { background: #10b981; color: white; }
        
        @media print {
            body { padding: 0; background: white; }
            .doc-container { box-shadow: none; border: none; padding: 0; }
            .toolbar { display: none; }
        }

        /* Hide toolbar if in iframe (preview mode) */
        body.is-preview .toolbar { display: none; }
        body.is-preview { padding: 20px; background: #f1f5f9; }
        body.is-preview .doc-container { margin: 0 auto; box-shadow: none; border: 1px solid var(--border); }
    </style>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            if (window.self !== window.top) {
                document.body.classList.add('is-preview');
            }
        });
    </script>
</head>
<body>
    <div class="toolbar no-print">
        <button onclick="window.print()" class="btn-tool btn-primary"><i class="fas fa-print"></i> Cetak Dokumen</button>
        <form method="POST">
            <button type="submit" name="archive" class="btn-tool btn-success"><i class="fas fa-box-archive"></i> Simpan ke Arsip</button>
        </form>
        <a href="shipping.php" class="btn-tool"><i class="fas fa-arrow-left"></i> Kembali</a>
    </div>

    <div class="doc-container">
        <div class="header">
            <div class="logo-section" style="display: flex; align-items: center; gap: 15px;">
                <img src="img/logo.png" alt="Logo" style="height: 60px; width: auto;">
                <div>
                    <h1>PT Lintas Nusantara Ekspedisi</h1>
                    <p>Logistik & Transportasi Kereta Api Cepat Seluruh Indonesia</p>
                    <p>Gedung Kargo Lt. 3, Stasiun Gambir, Jakarta Pusat</p>
                </div>
            </div>
            <div class="title-section">
                <h2>Surat Jalan</h2>
                <div class="doc-no">NO: SJ/<?php echo date('Ym', strtotime($s['created_at'])); ?>/<?php echo str_replace('LNE-', '', $s['resi_number']); ?></div>
            </div>
        </div>

        <div class="info-grid">
            <div class="info-box">
                <h3>Informasi Pengirim</h3>
                <p><span class="label">Nama:</span> <strong><?php echo $s['sender_name']; ?></strong></p>
                <p><span class="label">Alamat:</span> Gudang Kargo <?php echo $s['origin']; ?></p>
                <p><span class="label">Lokasi:</span> <?php echo $s['origin']; ?></p>
            </div>
            <div class="info-box">
                <h3>Informasi Penerima</h3>
                <p><span class="label">Nama:</span> <strong><?php echo $s['receiver_name']; ?></strong></p>
                <p><span class="label">Alamat:</span> Area Industri <?php echo $s['destination']; ?></p>
                <p><span class="label">Tujuan:</span> <?php echo $s['destination']; ?></p>
            </div>
        </div>

        <table class="cargo-table">
            <thead>
                <tr>
                    <th>Deskripsi Barang</th>
                    <th>Jumlah (Koli)</th>
                    <th>Berat Satuan</th>
                    <th>Total Berat</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $totalQty = 0;
                $totalW = 0;
                foreach ($items as $item): 
                    $totalQty += $item['quantity'];
                    $totalW += $item['weight_kg'];
                ?>
                <tr>
                    <td>
                        <strong><?php echo $item['item_name']; ?></strong><br>
                        <small style="color: var(--muted);">Layanan Ekspres Rail-Logistik</small>
                    </td>
                    <td><?php echo $item['quantity']; ?> <?php echo $item['unit']; ?></td>
                    <td><?php echo number_format($item['weight_kg'] / max(1, $item['quantity']), 2); ?> KG</td>
                    <td><strong><?php echo number_format($item['weight_kg'], 2); ?> KG</strong></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot style="background: #f8fafc; font-weight: bold;">
                <tr>
                    <td style="text-align: right; border: 1px solid var(--border); padding: 12px;">TOTAL</td>
                    <td style="border: 1px solid var(--border); padding: 12px;"><?php echo $totalQty; ?> Item</td>
                    <td style="border: 1px solid var(--border); padding: 12px;">-</td>
                    <td style="border: 1px solid var(--border); padding: 12px; color: var(--primary); font-size: 16px;"><?php echo number_format($totalW, 2); ?> KG</td>
                </tr>
            </tfoot>
        </table>

        <div class="rail-info">
            <div class="rail-item">
                <span>Moda Transportasi</span>
                <strong>Kereta Api Logistik</strong>
            </div>
            <div class="rail-item">
                <span>Nama Kereta / Gerbong</span>
                <strong><?php echo $s['train_name'] ?: 'KA-GENERAL'; ?> / GB-201</strong>
            </div>
            <div class="rail-item">
                <span>Estimasi Tiba</span>
                <strong><?php echo date('d M Y', strtotime($s['created_at'] . ' +1 day')); ?></strong>
            </div>
        </div>

        <div style="margin-bottom: 40px;">
            <p style="font-size: 11px; text-transform: uppercase; color: var(--muted); margin-bottom: 8px;">Catatan Pengiriman:</p>
            <p style="font-size: 12px; border: 1px dashed var(--border); padding: 10px; border-radius: 4px;">
                Barang dalam kondisi baik saat dimuat. Wajib menyertakan asuransi untuk barang bernilai tinggi.
                Pastikan segel kontainer tidak rusak saat diterima.
            </p>
        </div>

        <div class="signature-grid">
            <div class="sig-box">
                <p>Dilepas Oleh,</p>
                <div class="name">Pengirim</div>
            </div>
            <div class="sig-box">
                <p>Petugas Logistik,</p>
                <div class="name">PT Lintas Nusantara</div>
            </div>
            <div class="sig-box">
                <p>Diterima Oleh,</p>
                <div class="name">Penerima</div>
            </div>
        </div>

        <div style="margin-top: 50px; font-size: 10px; color: var(--muted); text-align: center; border-top: 1px solid var(--border); padding-top: 10px;">
            Dokumen ini sah dihasilkan oleh Sistem Manajemen Dokumen (DMS) - Dicetak pada <?php echo date('d/m/Y H:i'); ?>
        </div>
    </div>
</body>
</html>
