<?php
require_once 'includes/db.php';

if (!isset($_GET['id'])) {
    die("Invoice ID tidak ditemukan.");
}

$id = $_GET['id'];

// Fetch detailed data
$stmt = $pdo->prepare("SELECT i.*, s.resi_number, s.sender_name, s.receiver_name, s.goods_type, s.weight_kg, 
                        r.origin, r.destination, s.created_at as ship_date
                        FROM invoices i 
                        JOIN shipments s ON i.shipment_id = s.id 
                        JOIN routes r ON s.route_id = r.id
                        WHERE i.id = ?");
$stmt->execute([$id]);
$invoice = $stmt->fetch();

if (!$invoice) {
    die("Data invoice tidak valid.");
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Invoice - <?php echo $invoice['invoice_number']; ?></title>
    <style>
        body { font-family: 'Inter', sans-serif; color: #1e293b; margin: 0; padding: 40px; line-height: 1.5; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #e2e8f0; padding-bottom: 20px; margin-bottom: 30px; }
        .logo-section h1 { margin: 0; font-size: 24px; color: #2563eb; }
        .logo-section p { margin: 5px 0 0; font-size: 12px; color: #64748b; }
        .invoice-title { text-align: right; }
        .invoice-title h2 { margin: 0; font-size: 32px; text-transform: uppercase; color: #94a3b8; }
        .invoice-title p { margin: 5px 0 0; font-weight: 600; }
        
        .info-grid { display: grid; grid-template-cols: 1fr 1fr; gap: 40px; margin-bottom: 40px; }
        .info-box h3 { font-size: 12px; text-transform: uppercase; color: #64748b; border-bottom: 1px solid #e2e8f0; padding-bottom: 5px; margin-bottom: 10px; }
        .info-box p { margin: 2px 0; font-size: 14px; }
        
        table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        th { background: #f8fafc; text-align: left; padding: 12px; font-size: 12px; text-transform: uppercase; color: #64748b; border-bottom: 2px solid #e2e8f0; }
        td { padding: 12px; border-bottom: 1px solid #e2e8f0; font-size: 14px; }
        
        .total-section { display: flex; justify-content: flex-end; }
        .total-box { width: 250px; }
        .total-row { display: flex; justify-content: space-between; padding: 10px 0; }
        .total-row.grand-total { border-top: 2px solid #2563eb; margin-top: 10px; font-weight: 800; font-size: 18px; color: #2563eb; }
        
        .footer { margin-top: 50px; font-size: 11px; color: #94a3b8; text-align: center; }
        
        @media print {
            body { padding: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="header">
        <div class="logo-section">
            <h1>PT Lintas Nusantara Ekspedisi</h1>
            <p>Logistik & Transportasi Kereta Api Cepat</p>
            <p>Jl. Jenderal Sudirman No. 123, Jakarta Selatan</p>
        </div>
        <div class="invoice-title">
            <h2>Invoice</h2>
            <p># <?php echo $invoice['invoice_number']; ?></p>
            <p style="font-weight: 400; font-size: 12px;">Tanggal: <?php echo date('d M Y', strtotime($invoice['created_at'])); ?></p>
        </div>
    </div>

    <div class="info-grid">
        <div class="info-box">
            <h3>Penerima Tagihan</h3>
            <p><strong><?php echo $invoice['sender_name']; ?></strong></p>
            <p>Pengirim Barang - Layanan Logistik KA</p>
        </div>
        <div class="info-box">
            <h3>Informasi Pengiriman</h3>
            <p>No. Resi: <strong><?php echo $invoice['resi_number']; ?></strong></p>
            <p>Rute: <?php echo $invoice['origin']; ?> - <?php echo $invoice['destination']; ?></p>
            <p>Jatuh Tempo: <?php echo date('d M Y', strtotime($invoice['due_date'])); ?></p>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Deskripsi Layanan</th>
                <th>Berat (KG)</th>
                <th style="text-align: right;">Total</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <strong>Pengiriman Barang (<?php echo $invoice['goods_type']; ?>)</strong><br>
                    <small style="color: #64748b;">Layanan ekspedisi kereta api lintas nusantara</small>
                </td>
                <td><?php echo number_format($invoice['weight_kg'], 2); ?> KG</td>
                <td style="text-align: right;">Rp <?php echo number_format($invoice['amount']); ?></td>
            </tr>
        </tbody>
    </table>

    <div class="total-section">
        <div class="total-box">
            <div class="total-row">
                <span>Subtotal</span>
                <span>Rp <?php echo number_format($invoice['amount']); ?></span>
            </div>
            <div class="total-row">
                <span>Pajak (0%)</span>
                <span>Rp 0</span>
            </div>
            <div class="total-row grand-total">
                <span>Total</span>
                <span>Rp <?php echo number_format($invoice['amount']); ?></span>
            </div>
        </div>
    </div>

    <div style="margin-top: 40px;">
        <p style="font-size: 13px;"><strong>Metode Pembayaran:</strong></p>
        <p style="font-size: 12px; color: #475569;">
            Bank Mandiri: 123-00-9876543-1<br>
            A.N. PT Lintas Nusantara Ekspedisi
        </p>
    </div>

    <div class="footer">
        <p>Terima kasih atas kepercayaan Anda menggunakan layanan PT Lintas Nusantara Ekspedisi.</p>
        <p>Invoice ini dihasilkan secara otomatis oleh sistem DMS Logistik.</p>
    </div>

    <div class="no-print" style="position: fixed; bottom: 20px; right: 20px;">
        <button onclick="window.print()" style="padding: 10px 20px; background: #2563eb; color: white; border: none; border-radius: 5px; cursor: pointer;">Cetak Ulang</button>
    </div>
</body>
</html>
