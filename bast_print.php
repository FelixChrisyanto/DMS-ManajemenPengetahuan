<?php
require_once 'includes/db.php';

if (!isset($_GET['id'])) {
    die("ID Pengiriman tidak ditemukan.");
}

$id = $_GET['id'];

// Fetch detailed data
$stmt = $pdo->prepare("SELECT s.*, r.origin, r.destination, ts.train_name FROM shipments s 
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
    <title>BAST - <?php echo $s['resi_number']; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #1e3a8a; --text: #1e293b; --muted: #64748b; --border: #e2e8f0; }
        body { font-family: 'Inter', sans-serif; color: var(--text); margin: 0; padding: 40px; background: #f8fafc; line-height: 1.6; }
        .bast-container { background: white; max-width: 800px; margin: 0 auto; padding: 50px; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); position: relative; border-top: 10px solid var(--primary); }
        
        .header { text-align: center; margin-bottom: 40px; border-bottom: 2px solid var(--border); padding-bottom: 20px; }
        .header h1 { margin: 0; font-size: 24px; color: var(--primary); text-transform: uppercase; letter-spacing: 1px; }
        .header p { margin: 5px 0 0; color: var(--muted); font-size: 14px; }
        
        .doc-title { text-align: center; margin-bottom: 30px; }
        .doc-title h2 { margin: 0; font-size: 20px; text-decoration: underline; }
        .doc-title p { margin: 5px 0 0; font-weight: 600; }
        
        .content-text { margin-bottom: 30px; font-size: 14px; }
        
        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .info-table td { padding: 10px; border: 1px solid var(--border); font-size: 14px; }
        .info-table td.label { width: 30%; background: #f8fafc; font-weight: 600; color: var(--muted); }
        
        .photo-section { margin-bottom: 40px; text-align: center; }
        .photo-box { border: 2px dashed var(--border); padding: 15px; border-radius: 8px; display: inline-block; }
        .photo-box img { max-width: 100%; max-height: 250px; border-radius: 4px; }
        .photo-label { display: block; font-size: 12px; color: var(--muted); margin-bottom: 10px; }

        .signature-grid { display: grid; grid-template-cols: 1fr 1fr; gap: 50px; margin-top: 60px; text-align: center; }
        .sig-box { display: flex; flex-direction: column; align-items: center; }
        .sig-line { width: 200px; border-top: 1px solid var(--text); margin-top: 80px; padding-top: 10px; font-weight: 700; }
        .sig-label { font-size: 12px; color: var(--muted); }

        .toolbar { position: fixed; top: 20px; right: 20px; display: flex; gap: 10px; }
        .btn-tool { padding: 10px 20px; border-radius: 6px; border: none; background: var(--primary); color: white; font-weight: 600; cursor: pointer; text-decoration: none; display: flex; align-items: center; gap: 8px; box-shadow: 0 4px 12px rgba(30, 58, 138, 0.2); }
        
        @media print {
            body { background: white; padding: 0; }
            .bast-container { box-shadow: none; border: 1px solid #eee; margin: 0; width: 100%; }
            .toolbar { display: none; }
        }
    </style>
</head>
<body>
    <div class="toolbar no-print">
        <button onclick="window.print()" class="btn-tool"><i class="fas fa-print"></i> Cetak Dokumen</button>
        <a href="shipping.php" class="btn-tool" style="background: #64748b;"><i class="fas fa-arrow-left"></i> Kembali</a>
    </div>

    <div class="bast-container">
        <div class="header">
            <img src="img/logo.png" alt="Logo" style="height: 70px; width: auto; margin-bottom: 10px;">
            <h1>PT Lintas Nusantara Ekspedisi</h1>
            <p>Logistik & Transportasi Kereta Api Cepat</p>
            <p>Stasiun Tujuan: <?php echo $s['destination']; ?></p>
        </div>

        <div class="doc-title">
            <h2>BERITA ACARA SERAH TERIMA (BAST)</h2>
            <p>Nomor: BAST/<?php echo date('Ymd'); ?>/<?php echo $s['resi_number']; ?></p>
        </div>

        <div class="content-text">
            Pada hari ini, <strong><?php echo date('l, d F Y'); ?></strong>, telah dilakukan serah terima barang kiriman dari PT Lintas Nusantara Ekspedisi kepada pihak penerima dengan rincian sebagai berikut:
        </div>

        <table class="info-table">
            <tr>
                <td class="label">Nomor Resi</td>
                <td><strong><?php echo $s['resi_number']; ?></strong></td>
            </tr>
            <tr>
                <td class="label">Nama Pengirim</td>
                <td><?php echo $s['sender_name']; ?> (<?php echo $s['sender_phone']; ?>)</td>
            </tr>
            <tr>
                <td class="label">Nama Penerima</td>
                <td><?php echo $s['receiver_name']; ?> (<?php echo $s['receiver_phone']; ?>)</td>
            </tr>
            <tr>
                <td class="label">Jenis Barang</td>
                <td><?php echo $itemList ?: 'Tanpa Detail Barang'; ?></td>
            </tr>
            <tr>
                <td class="label">Berat Barang</td>
                <td><?php echo number_format($s['weight_kg'], 2); ?> KG</td>
            </tr>
            <tr>
                <td class="label">Kereta Api</td>
                <td><?php echo $s['train_name']; ?> (<?php echo $s['origin']; ?> - <?php echo $s['destination']; ?>)</td>
            </tr>
        </table>

        <?php if ($s['goods_photo']): ?>
        <div class="photo-section">
            <span class="photo-label">Lampiran: Foto Bukti Barang Saat Input</span>
            <div class="photo-box">
                <img src="<?php echo $s['goods_photo']; ?>" alt="Foto Barang">
            </div>
        </div>
        <?php endif; ?>

        <div class="content-text">
            Penerima menyatakan telah memeriksa dan menerima barang tersebut dalam kondisi baik dan lengkap sesuai dengan manifest pengiriman. Dengan ditandatanganinya berita acara ini, maka tanggung jawab atas barang tersebut telah beralih kepada pihak penerima.
        </div>

        <div class="signature-grid">
            <div class="sig-box">
                <span class="sig-label">Diserahkan Oleh,</span>
                <div class="sig-line">Petugas Stasiun</div>
                <span class="sig-label">PT Lintas Nusantara Ekspedisi</span>
            </div>
            <div class="sig-box">
                <span class="sig-label">Diterima Oleh,</span>
                <div class="sig-line"><?php echo $s['receiver_name']; ?></div>
                <span class="sig-label">Penerima Barang</span>
            </div>
        </div>

        <div style="margin-top: 50px; font-size: 10px; color: var(--muted); text-align: center; border-top: 1px solid var(--border); padding-top: 20px;">
            Dokumen ini sah dan dihasilkan secara otomatis melalui Digital Management System (DMS) Logistik.
        </div>
    </div>
</body>
</html>
