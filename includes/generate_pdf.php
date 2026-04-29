<?php
// includes/generate_pdf.php
require_once 'dompdf/dompdf/autoload.inc.php';
use Dompdf\Dompdf;
use Dompdf\Options;

function generateResiPDF($id, $pdo) {
    // Fetch data
    $stmt = $pdo->prepare("SELECT s.*, r.origin, r.destination FROM shipments s 
                            JOIN routes r ON s.route_id = r.id 
                            WHERE s.id = ?");
    $stmt->execute([$id]);
    $s = $stmt->fetch();

    if (!$s) return null;

    // Fetch Items
    $stmtItems = $pdo->prepare("SELECT * FROM shipment_items WHERE shipment_id = ?");
    $stmtItems->execute([$id]);
    $items = $stmtItems->fetchAll();

    // Prepare Logo Base64
    $logoPath = 'img/logo.png';
    $logoData = '';
    if (file_exists($logoPath)) {
        $logoData = 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
    }

    // Setup Dompdf options
    $options = new Options();
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'Helvetica');
    $dompdf = new Dompdf($options);

    // Prepare Items Table HTML
    $itemsHtml = '<table style="width: 100%; border-collapse: collapse; margin: 15px 0; font-size: 11px;">
                    <thead style="background: #f1f5f9;">
                        <tr>
                            <th style="border: 1px solid #e2e8f0; padding: 5px; text-align: left;">Nama Barang</th>
                            <th style="border: 1px solid #e2e8f0; padding: 5px; text-align: center; width: 60px;">Jumlah</th>
                        </tr>
                    </thead>
                    <tbody>';
    foreach ($items as $item) {
        $itemsHtml .= '<tr>
                        <td style="border: 1px solid #e2e8f0; padding: 5px;">' . $item['item_name'] . '</td>
                        <td style="border: 1px solid #e2e8f0; padding: 5px; text-align: center;">' . $item['quantity'] . ' ' . $item['unit'] . '</td>
                       </tr>';
    }
    if (empty($items)) {
        $itemsHtml .= '<tr><td colspan="2" style="border: 1px solid #e2e8f0; padding: 5px; text-align: center;">Tidak ada detail barang</td></tr>';
    }
    $itemsHtml .= '</tbody></table>';

    $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=" . urlencode("http://localhost/tracking.php?resi=" . $s['resi_number']);
    
    $html = '
    <html>
    <head>
        <style>
            body { font-family: Helvetica, Arial, sans-serif; color: #1e293b; margin: 0; padding: 0; }
            .resi-card { width: 100%; border: 1px solid #e2e8f0; border-top: 8px solid #1e3a8a; padding: 20px; }
            .header { text-align: center; border-bottom: 1px dashed #e2e8f0; padding-bottom: 10px; margin-bottom: 15px; }
            .header h1 { margin: 0; font-size: 18px; color: #1e3a8a; }
            .resi-no { text-align: center; background: #f8fafc; padding: 10px; margin-bottom: 20px; }
            .resi-no span { font-size: 10px; color: #64748b; display: block; }
            .resi-no strong { font-size: 20px; }
            .info-row { margin-bottom: 5px; font-size: 11px; clear: both; }
            .label { color: #64748b; float: left; width: 40%; }
            .value { font-weight: bold; float: right; width: 55%; text-align: right; }
            .route { text-align: center; margin: 15px 0; background: #f1f5f9; padding: 8px; font-weight: bold; font-size: 12px; }
            .qr-section { text-align: center; margin-top: 15px; border-top: 1px dashed #e2e8f0; padding-top: 10px; }
            .footer-note { font-size: 8px; color: #64748b; text-align: center; margin-top: 10px; }
        </style>
    </head>
    <body>
        <div class="resi-card">
            <div class="header">
                ' . ($logoData ? '<img src="' . $logoData . '" style="height: 40px; margin-bottom: 10px;">' : '') . '
                <h1>PT LINTAS NUSANTARA EKSPEDISI</h1>
                <p style="font-size: 10px; margin: 5px 0;">Bukti Tanda Terima Pengiriman Barang (Resi)</p>
            </div>
            <div class="resi-no">
                <span>NOMOR RESI / WAYBILL</span>
                <strong>' . $s['resi_number'] . '</strong>
            </div>
            <div class="info-row"><div class="label">Tanggal</div><div class="value">' . date('d M Y, H:i', strtotime($s['created_at'])) . '</div></div>
            <div class="info-row"><div class="label">Pengirim</div><div class="value">' . $s['sender_name'] . '</div></div>
            <div class="info-row"><div class="label">Penerima</div><div class="value">' . $s['receiver_name'] . '</div></div>
            <div class="route">' . strtoupper($s['origin']) . ' &rarr; ' . strtoupper($s['destination']) . '</div>
            
            <p style="font-size: 11px; font-weight: bold; margin-bottom: 5px;">Rincian Barang:</p>
            ' . $itemsHtml . '

            <div class="info-row"><div class="label">Total Berat</div><div class="value">' . number_format($s['weight_kg'], 2) . ' KG</div></div>
            <div class="info-row"><div class="label">Status</div><div class="value">' . strtoupper($s['status']) . '</div></div>
            <div class="qr-section">
                <img src="' . $qrUrl . '" width="80" height="80">
                <p style="font-size: 9px; margin-top: 5px;">Scan untuk lacak kiriman secara real-time</p>
            </div>
            <div class="footer-note">
                * Simpan resi ini sebagai bukti pengambilan barang di stasiun tujuan.
            </div>
        </div>
    </body>
    </html>';

    $dompdf->loadHtml($html);
    $dompdf->setPaper('A5', 'portrait');
    $dompdf->render();

    // Save to file
    $pdfDir = '../uploads/pdf/';
    if (!is_dir($pdfDir)) mkdir($pdfDir, 0777, true);
    
    $fileName = 'Resi_' . $s['resi_number'] . '_' . time() . '.pdf';
    $filePath = $pdfDir . $fileName;
    file_put_contents($filePath, $dompdf->output());

    return 'uploads/pdf/' . $fileName;
}

function generatePODPDF($id, $pdo) {
    // Fetch data
    $stmt = $pdo->prepare("SELECT s.*, r.origin, r.destination FROM shipments s 
                            JOIN routes r ON s.route_id = r.id 
                            WHERE s.id = ?");
    $stmt->execute([$id]);
    $s = $stmt->fetch();

    if (!$s) return null;

    // Fetch Items
    $stmtItems = $pdo->prepare("SELECT * FROM shipment_items WHERE shipment_id = ?");
    $stmtItems->execute([$id]);
    $items = $stmtItems->fetchAll();

    // Prepare Logo Base64
    $logoPath = 'img/logo.png';
    $logoData = '';
    if (file_exists($logoPath)) {
        $logoData = 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
    }

    $options = new Options();
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'Helvetica');
    $dompdf = new Dompdf($options);

    $itemsHtml = '<table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
                    <thead style="background: #f1f5f9;">
                        <tr>
                            <th style="border: 1px solid #e2e8f0; padding: 10px; text-align: left;">Nama Barang</th>
                            <th style="border: 1px solid #e2e8f0; padding: 10px; text-align: center; width: 100px;">Jumlah</th>
                        </tr>
                    </thead>
                    <tbody>';
    foreach ($items as $item) {
        $itemsHtml .= '<tr>
                        <td style="border: 1px solid #e2e8f0; padding: 10px;">' . $item['item_name'] . '</td>
                        <td style="border: 1px solid #e2e8f0; padding: 10px; text-align: center;">' . $item['quantity'] . ' ' . $item['unit'] . '</td>
                       </tr>';
    }
    $itemsHtml .= '</tbody></table>';

    $html = '
    <html>
    <head>
        <style>
            body { font-family: Helvetica, Arial, sans-serif; color: #1e293b; padding: 30px; }
            .pod-card { border: 1px solid #e2e8f0; border-radius: 12px; padding: 40px; position: relative; }
            .header { text-align: center; border-bottom: 2px solid #1e3a8a; padding-bottom: 20px; margin-bottom: 30px; }
            .header h1 { margin: 0; color: #1e3a8a; font-size: 24px; text-transform: uppercase; }
            .stamp { position: absolute; top: 80px; right: 40px; border: 4px solid #10b981; color: #10b981; padding: 10px 20px; font-weight: bold; font-size: 24px; transform: rotate(15deg); border-radius: 8px; opacity: 0.6; }
            .info-table { width: 100%; margin-bottom: 20px; }
            .info-table td { padding: 8px 0; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
            .label { color: #64748b; width: 30%; }
            .value { font-weight: bold; text-align: right; }
            .signature-section { margin-top: 50px; }
            .signature-box { width: 250px; border-bottom: 2px solid #1e293b; margin-top: 80px; font-weight: bold; }
            .footer { text-align: center; font-size: 10px; color: #94a3b8; margin-top: 40px; }
        </style>
    </head>
    <body>
        <div class="pod-card">
            <div class="stamp">DELIVERED</div>
            <div class="header">
                ' . ($logoData ? '<img src="' . $logoData . '" style="height: 50px; margin-bottom: 10px;">' : '') . '
                <h1>SURAT TERIMA BARANG</h1>
                <p>PT Lintas Nusantara Ekspedisi</p>
            </div>
            
            <div style="text-align: center; margin-bottom: 30px;">
                <span style="color: #64748b; font-size: 12px;">Nomor Resi:</span><br>
                <strong style="font-size: 20px;">' . $s['resi_number'] . '</strong>
            </div>

            <table class="info-table">
                <tr><td class="label">Nama Pengirim</td><td class="value">' . $s['sender_name'] . '</td></tr>
                <tr><td class="label">Nama Penerima</td><td class="value">' . $s['receiver_name'] . '</td></tr>
                <tr><td class="label">Kota Asal</td><td class="value">' . strtoupper($s['origin']) . '</td></tr>
                <tr><td class="label">Kota Tujuan</td><td class="value">' . strtoupper($s['destination']) . '</td></tr>
                <tr><td class="label">Total Berat</td><td class="value">' . number_format($s['weight_kg'], 2) . ' KG</td></tr>
            </table>

            <p style="font-size: 14px; font-weight: bold; margin-top: 20px;">Rincian Barang yang Diterima:</p>
            ' . $itemsHtml . '

            <p style="font-size: 14px; margin-top: 30px;">Dengan ini dinyatakan bahwa barang tersebut di atas telah diterima dalam keadaan baik oleh:</p>
            
            <div class="signature-section">
                <div class="signature-box">' . $s['receiver_name'] . '</div>
                <p style="font-size: 12px; color: #64748b;">Tanda Tangan Penerima</p>
            </div>

            <div class="footer">
                Terima kasih telah menggunakan jasa PT Lintas Nusantara Ekspedisi.<br>
                Dokumen ini dihasilkan secara digital pada ' . date('d M Y H:i') . '
            </div>
        </div>
    </body>
    </html>';

    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    $pdfDir = '../uploads/pdf/';
    if (!is_dir($pdfDir)) mkdir($pdfDir, 0777, true);
    
    $fileName = 'POD_' . $s['resi_number'] . '_' . time() . '.pdf';
    $filePath = $pdfDir . $fileName;
    file_put_contents($filePath, $dompdf->output());

    return 'uploads/pdf/' . $fileName;
}
