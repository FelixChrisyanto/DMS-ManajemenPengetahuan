<?php
require_once 'includes/db.php';

if (!isset($_GET['id'])) {
    die("ID Pengiriman tidak ditemukan.");
}

$id = $_GET['id'];

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
    <title>Proof of Delivery - <?php echo $s['resi_number']; ?></title>
    <style>
        body { font-family: 'Inter', sans-serif; padding: 40px; color: #1e293b; }
        .pod-card { border: 1px solid #e2e8f0; padding: 30px; border-radius: 12px; max-width: 600px; margin: 0 auto; position: relative; }
        .header { text-align: center; margin-bottom: 30px; }
        .stamp { position: absolute; top: 100px; right: 50px; border: 3px solid #10b981; color: #10b981; padding: 10px; border-radius: 5px; transform: rotate(-15deg); font-weight: 800; font-size: 24px; opacity: 0.6; }
        .detail-row { display: flex; justify-content: space-between; margin-bottom: 15px; border-bottom: 1px solid #f1f5f9; padding-bottom: 5px; }
        .label { color: #64748b; font-size: 12px; }
        .value { font-weight: 700; }
        .footer { margin-top: 40px; text-align: center; font-size: 12px; color: #94a3b8; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="pod-card">
        <div class="stamp">DELIVERED</div>
        <div class="header">
            <h2 style="margin: 0; color: #1e3a8a;">SURAT TERIMA BARANG</h2>
            <p style="margin: 5px 0 0; font-size: 14px;">PT Lintas Nusantara Ekspedisi</p>
        </div>

        <div style="margin: 20px 0; padding: 15px; background: #f8fafc; border-radius: 8px; text-align: center;">
            <div style="font-size: 12px; color: #64748b;">Nomor Resi</div>
            <div style="font-size: 20px; font-weight: 800;"><?php echo $s['resi_number']; ?></div>
        </div>

        <div class="detail-row"><span class="label">Nama Pengirim</span><span class="value"><?php echo $s['sender_name']; ?></span></div>
        <div class="detail-row"><span class="label">Nama Penerima</span><span class="value"><?php echo $s['receiver_name']; ?></span></div>
        <div class="detail-row"><span class="label">Kota Asal</span><span class="value"><?php echo $s['origin']; ?></span></div>
        <div class="detail-row"><span class="label">Kota Tujuan</span><span class="value"><?php echo $s['destination']; ?></span></div>
        <div class="detail-row"><span class="label">Jenis Barang</span><span class="value"><?php echo $s['goods_type']; ?></span></div>
        <div class="detail-row"><span class="label">Berat</span><span class="value"><?php echo number_format($s['weight_kg']); ?> KG</span></div>
        
        <div style="margin-top: 30px;">
            <p style="font-size: 14px;">Dengan ini dinyatakan bahwa barang tersebut di atas telah diterima dalam keadaan baik oleh:</p>
            <div style="margin-top: 10px; border-bottom: 2px solid #e2e8f0; width: 200px; padding-bottom: 5px; font-weight: 700;">
                <?php echo $s['receiver_name']; ?>
            </div>
            <span style="font-size: 12px; color: #64748b;">Tanda Tangan Penerima</span>
        </div>

        <div class="footer">
            Terima kasih telah menggunakan jasa PT Lintas Nusantara Ekspedisi.<br>
            Dokumen ini dihasilkan secara digital pada <?php echo date('d M Y H:i'); ?>
        </div>
    </div>
    
    <div style="text-align: center; margin-top: 20px;" class="no-print">
        <button onclick="window.print()" style="padding: 10px 20px; background: #1e3a8a; color: white; border: none; border-radius: 5px; cursor: pointer;">Cetak Surat Terima</button>
        <a href="shipping.php" style="margin-left: 10px; color: #64748b; text-decoration: none;">Kembali</a>
    </div>
</body>
</html>
