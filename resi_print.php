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
        
        $fileName = "Resi_Pengiriman_" . $resi . ".html";
        $category = 'resi';
        
        // Check if already archived
        $check = $pdo->prepare("SELECT id FROM documents WHERE shipment_id = ? AND category = ? AND deleted_at IS NULL");
        $check->execute([$id, $category]);
        
        if (!$check->fetch()) {
            $stmt = $pdo->prepare("INSERT INTO documents (shipment_id, file_name, file_path, category, uploaded_by) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$id, $fileName, "resi_print.php?id=" . $id, $category, 1]);
            echo "<script>alert('Resi berhasil diarsipkan ke Penjelajah Dokumen!');</script>";
        } else {
            echo "<script>alert('Resi ini sudah ada di arsip.');</script>";
        }
    } catch (Exception $e) {
        echo "<script>alert('Gagal mengarsipkan: " . $e->getMessage() . "');</script>";
    }
}

// Fetch detailed data
$stmt = $pdo->prepare("SELECT s.*, r.origin, r.destination FROM shipments s 
                        JOIN routes r ON s.route_id = r.id 
                        WHERE s.id = ?");
$stmt->execute([$id]);
$s = $stmt->fetch();

if (!$s) {
    die("Data pengiriman tidak valid.");
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Resi Pengiriman - <?php echo $s['resi_number']; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #1e3a8a; --accent: #f97316; --text: #1e293b; --muted: #64748b; --border: #e2e8f0; }
        body { font-family: 'Inter', sans-serif; color: var(--text); margin: 0; padding: 20px; background: #f1f5f9; display: flex; justify-content: center; }
        .resi-card { background: white; width: 450px; padding: 30px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); border-top: 8px solid var(--primary); position: relative; }
        
        .header { text-align: center; margin-bottom: 25px; border-bottom: 1px dashed var(--border); padding-bottom: 15px; }
        .header h1 { margin: 0; font-size: 18px; color: var(--primary); text-transform: uppercase; font-weight: 800; }
        .header p { margin: 5px 0 0; font-size: 11px; color: var(--muted); }
        
        .resi-no { text-align: center; background: #f8fafc; padding: 10px; border-radius: 8px; margin-bottom: 25px; }
        .resi-no span { display: block; font-size: 10px; color: var(--muted); text-transform: uppercase; letter-spacing: 0.1em; }
        .resi-no strong { font-size: 20px; color: var(--text); letter-spacing: 1px; }
        
        .info-row { display: flex; justify-content: space-between; margin-bottom: 15px; font-size: 13px; }
        .info-row .label { color: var(--muted); }
        .info-row .value { font-weight: 600; text-align: right; }
        
        .route-visual { display: flex; align-items: center; justify-content: center; gap: 15px; margin: 25px 0; padding: 15px; background: rgba(30, 58, 138, 0.03); border-radius: 8px; }
        .route-visual .city { font-weight: 700; font-size: 14px; color: var(--primary); }
        .route-visual .arrow { color: var(--muted); font-size: 12px; }
        
        .qr-section { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px dashed var(--border); }
        .qr-code { width: 120px; height: 120px; background: #eee; margin: 0 auto 10px; display: flex; align-items: center; justify-content: center; border-radius: 8px; overflow: hidden; }
        .qr-section p { font-size: 11px; color: var(--muted); }
        
        .footer-note { font-size: 10px; color: var(--muted); text-align: center; margin-top: 25px; font-style: italic; }
        
        .toolbar { position: fixed; top: 20px; right: 20px; display: flex; flex-direction: column; gap: 10px; }
        .btn-tool { padding: 10px 20px; border-radius: 999px; border: none; background: white; color: var(--text); font-weight: 600; cursor: pointer; box-shadow: 0 4px 12px rgba(0,0,0,0.1); display: flex; align-items: center; gap: 8px; font-size: 13px; transition: all 0.2s; text-decoration: none; }
        .btn-tool:hover { transform: translateY(-2px); }
        .btn-primary { background: var(--primary); color: white; }
        .btn-success { background: #10b981; color: white; }
        
        @media print {
            body { background: white; padding: 0; }
            .resi-card { box-shadow: none; border: 1px solid #eee; margin: 0; }
            .toolbar { display: none; }
        }

        /* Hide toolbar if in iframe (preview mode) */
        body.is-preview .toolbar { display: none; }
        body.is-preview { padding: 10px; background: #f1f5f9; display: flex; justify-content: center; }
        body.is-preview .resi-card { box-shadow: none; border: 1px solid var(--border); margin: 0; }
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
        <button onclick="window.print()" class="btn-tool btn-primary"><i class="fas fa-print"></i> Cetak Resi</button>
        <form method="POST">
            <button type="submit" name="archive" class="btn-tool btn-success"><i class="fas fa-box-archive"></i> Simpan ke Arsip</button>
        </form>
        <a href="shipping.php" class="btn-tool"><i class="fas fa-arrow-left"></i> Kembali</a>
    </div>

    <div class="resi-card">
        <div class="header">
            <h1>PT Lintas Nusantara Ekspedisi</h1>
            <p>Bukti Tanda Terima Pengiriman Barang (Resi)</p>
        </div>

        <div class="resi-no">
            <span>Nomor Resi / Waybill</span>
            <strong><?php echo $s['resi_number']; ?></strong>
        </div>

        <div class="info-row">
            <div class="label">Tanggal Diterima</div>
            <div class="value"><?php echo date('d M Y, H:i', strtotime($s['created_at'])); ?></div>
        </div>
        <div class="info-row">
            <div class="label">Pengirim</div>
            <div class="value"><?php echo $s['sender_name']; ?></div>
        </div>
        <div class="info-row">
            <div class="label">Penerima</div>
            <div class="value"><?php echo $s['receiver_name']; ?></div>
        </div>
        
        <div class="route-visual">
            <div class="city"><?php echo strtoupper($s['origin']); ?></div>
            <div class="arrow"><i class="fas fa-train"></i></div>
            <div class="city"><?php echo strtoupper($s['destination']); ?></div>
        </div>

        <div class="info-row">
            <div class="label">Jenis Barang</div>
            <div class="value"><?php echo $s['goods_type']; ?></div>
        </div>
        <div class="info-row">
            <div class="label">Total Berat</div>
            <div class="value"><?php echo number_format($s['weight_kg'], 2); ?> KG</div>
        </div>
        <div class="info-row">
            <div class="label">Status</div>
            <div class="value" style="color: var(--primary);"><?php echo strtoupper($s['status']); ?></div>
        </div>

        <div class="qr-section">
            <div class="qr-code">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=<?php echo urlencode('http://localhost/DMS/tracking.php?resi=' . $s['resi_number']); ?>" alt="QR Code Tracking">
            </div>
            <p>Scan untuk lacak kiriman secara real-time</p>
        </div>

        <div class="footer-note">
            * Simpan resi ini sebagai bukti pengambilan barang di stasiun tujuan.
            Layanan pengaduan: 1500-XXX atau via website resmi kami.
        </div>
    </div>
</body>
</html>
