<?php
require_once 'includes/db.php';

if (!isset($_GET['id'])) {
    die("ID Pinvoicing tidak ditemukan.");
}

$id = $_GET['id'];

// Handle Archiving Action
if (isset($_POST['archive'])) {
    try {
        $stmt = $pdo->prepare("SELECT invoice_number FROM invoices WHERE id = ?");
        $stmt->execute([$id]);
        $invNum = $stmt->fetchColumn();
        
        $fileName = "Kuitansi_Bayar_" . $invNum . ".html";
        $category = 'payment_proof';
        
        // Check if already archived
        $check = $pdo->prepare("SELECT id FROM documents WHERE shipment_id = (SELECT shipment_id FROM invoices WHERE id = ?) AND category = ? AND deleted_at IS NULL");
        $check->execute([$id, $category]);
        
        if (!$check->fetch()) {
            $stmt = $pdo->prepare("INSERT INTO documents (shipment_id, file_name, file_path, category, uploaded_by) 
                                   SELECT shipment_id, ?, ?, ?, 1 FROM invoices WHERE id = ?");
            $stmt->execute([$fileName, "payment_print.php?id=" . $id, $category, $id]);
            echo "<script>alert('Kuitansi berhasil diarsipkan ke Penjelajah Dokumen!');</script>";
        } else {
            echo "<script>alert('Kuitansi ini sudah ada di arsip.');</script>";
        }
    } catch (Exception $e) {
        echo "<script>alert('Gagal mengarsipkan: " . $e->getMessage() . "');</script>";
    }
}

// Fetch detailed data
$stmt = $pdo->prepare("SELECT i.*, s.resi_number, s.sender_name, s.receiver_name, s.goods_type, s.weight_kg 
                        FROM invoices i 
                        JOIN shipments s ON i.shipment_id = s.id 
                        WHERE i.id = ?");
$stmt->execute([$id]);
$i = $stmt->fetch();

if (!$i) {
    die("Data invoice tidak valid.");
}

if ($i['status'] != 'paid') {
    die("Kuitansi hanya tersedia untuk invoice yang sudah lunas.");
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kuitansi Pembayaran - <?php echo $i['invoice_number']; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #1e3a8a; --success: #10b981; --text: #1e293b; --muted: #64748b; --border: #e2e8f0; }
        body { font-family: 'Inter', sans-serif; color: var(--text); margin: 0; padding: 40px; background: #f8fafc; }
        .receipt-container { background: white; max-width: 800px; margin: 0 auto; padding: 40px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); position: relative; border: 1px solid var(--border); }
        
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; border-bottom: 2px solid var(--success); padding-bottom: 20px; }
        .logo-section h1 { font-size: 20px; color: var(--primary); margin: 0; text-transform: uppercase; }
        .logo-section p { font-size: 11px; color: var(--muted); margin: 5px 0 0; }
        
        .badge-paid { background: #dcfce7; color: #166534; padding: 10px 20px; border-radius: 999px; font-weight: 800; font-size: 16px; text-transform: uppercase; letter-spacing: 2px; transform: rotate(-10deg); border: 2px solid #166534; }
        
        .title-section { text-align: center; margin-bottom: 40px; }
        .title-section h2 { margin: 0; font-size: 24px; text-transform: uppercase; letter-spacing: 3px; font-weight: 800; }
        .title-section p { font-size: 14px; color: var(--muted); margin-top: 5px; }
        
        .data-row { display: flex; border-bottom: 1px dotted var(--border); padding: 15px 0; font-size: 14px; }
        .data-row .label { width: 220px; color: var(--muted); font-weight: 500; }
        .data-row .value { flex: 1; font-weight: 600; }
        .data-row .amount { font-size: 20px; color: var(--primary); font-weight: 800; }
        
        .footer { margin-top: 50px; display: flex; justify-content: space-between; align-items: flex-end; }
        .terbilang { font-style: italic; font-size: 12px; color: var(--muted); background: #f8fafc; padding: 15px; border-radius: 6px; flex: 1; margin-right: 40px; }
        .signature { text-align: center; width: 200px; }
        .signature p { font-size: 11px; color: var(--muted); margin-bottom: 60px; }
        .signature strong { display: block; border-bottom: 1px solid var(--text); padding-bottom: 5px; font-size: 14px; }
        
        .toolbar { position: fixed; top: 20px; right: 20px; display: flex; flex-direction: column; gap: 10px; }
        .btn-tool { padding: 10px 20px; border-radius: 999px; border: none; background: white; color: var(--text); font-weight: 600; cursor: pointer; box-shadow: 0 4px 12px rgba(0,0,0,0.1); display: flex; align-items: center; gap: 8px; font-size: 13px; transition: all 0.2s; text-decoration: none; }
        .btn-tool:hover { transform: translateY(-2px); }
        .btn-primary { background: var(--primary); color: white; }
        .btn-success { background: var(--success); color: white; }
        
        @media print {
            body { background: white; padding: 0; }
            .receipt-container { box-shadow: none; border: 2px solid #eee; margin: 0; }
            .toolbar { display: none; }
        }

        /* Hide toolbar if in iframe (preview mode) */
        body.is-preview .toolbar { display: none; }
        body.is-preview { padding: 20px; background: #f1f5f9; }
        body.is-preview .receipt-container { margin: 0 auto; box-shadow: none; border: 1px solid var(--border); }
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
        <button onclick="window.print()" class="btn-tool btn-primary"><i class="fas fa-print"></i> Cetak Kuitansi</button>
        <form method="POST">
            <button type="submit" name="archive" class="btn-tool btn-success"><i class="fas fa-box-archive"></i> Simpan ke Arsip</button>
        </form>
        <a href="invoices.php" class="btn-tool"><i class="fas fa-arrow-left"></i> Kembali</a>
    </div>

    <div class="receipt-container">
        <div class="header">
            <div class="logo-section">
                <h1>PT Lintas Nusantara Ekspedisi</h1>
                <p>Railway Logistics & Document Management System</p>
                <p>Finance Division - Jakarta Central</p>
            </div>
            <div class="badge-paid">LUNAS / PAID</div>
        </div>

        <div class="title-section">
            <h2>Kuitansi Pembayaran</h2>
            <p>Bukti Pembayaran Sah Sistem DMS</p>
        </div>

        <div class="data-row">
            <div class="label">Nomor Kuitansi</div>
            <div class="value">REC/<?php echo date('Y', strtotime($i['created_at'])); ?>/<?php echo $i['id']; ?></div>
        </div>
        <div class="data-row">
            <div class="label">Sudah Terima Dari</div>
            <div class="value"><?php echo strtoupper($i['sender_name']); ?></div>
        </div>
        <div class="data-row">
            <div class="label">Banyaknya Uang</div>
            <div class="value amount">Rp <?php echo number_format($i['amount']); ?></div>
        </div>
        <div class="data-row">
            <div class="label">Untuk Pembayaran</div>
            <div class="value">
                Biaya Pengiriman Logistik No. Resi <?php echo $i['resi_number']; ?><br>
                <small style="color: var(--muted); font-weight: 400;">Berdasarkan Invoice: <?php echo $i['invoice_number']; ?></small>
            </div>
        </div>
        <div class="data-row">
            <div class="label">Metode Pembayaran</div>
            <div class="value">Transfer Bank (Mandiri/BCA)</div>
        </div>

        <div class="footer">
            <div class="terbilang">
                <strong>Catatan:</strong><br>
                Kuitansi ini adalah bukti pembayaran yang sah dan dihasilkan secara otomatis oleh sistem. Harap simpan sebagai bukti administrasi Anda.
            </div>
            <div class="signature">
                <p>Jakarta, <?php echo date('d M Y'); ?></p>
                <p>Kasir / Finance Official,</p>
                <strong>DMS FINANCE DEPT</strong>
            </div>
        </div>
    </div>
</body>
</html>
