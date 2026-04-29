<?php 
require_once 'includes/db.php';
include 'includes/head.php'; 
include 'includes/sidebar.php'; 

// Fetch Report Overviews
$totalShipments = $pdo->query("SELECT COUNT(*) FROM shipments")->fetchColumn();
$totalInvoices = $pdo->query("SELECT SUM(amount) FROM invoices WHERE status = 'paid'")->fetchColumn();
$pendingInvoices = $pdo->query("SELECT SUM(amount) FROM invoices WHERE status = 'unpaid'")->fetchColumn();

// Document Flow Stats
$docStats = $pdo->query("SELECT 
    SUM(CASE WHEN category IN ('resi', 'surat_jalan', 'invoice') THEN 1 ELSE 0 END) as total_keluar,
    SUM(CASE WHEN category IN ('payment_proof', 'bast', 'manifest') THEN 1 ELSE 0 END) as total_masuk,
    COUNT(*) as total_all
    FROM documents WHERE deleted_at IS NULL")->fetch();

// Fetch Latest Shipments for Table
$reportData = $pdo->query("SELECT s.*, r.origin, r.destination, i.amount, i.status as pay_status 
                          FROM shipments s 
                          JOIN routes r ON s.route_id = r.id 
                          LEFT JOIN invoices i ON s.id = i.shipment_id 
                          ORDER BY s.created_at DESC LIMIT 10")->fetchAll();
?>

<div class="content-wrapper">
    <?php include 'includes/header.php'; ?>

    <div class="container">
        <div style="margin-bottom: 2rem; display: flex; align-items: center; justify-content: space-between;">
            <div>
                <h1 style="font-size: 1.75rem; font-weight: 800; color: var(--primary);">Laporan & Analitik</h1>
                <p style="color: var(--text-muted); margin-top: 0.25rem;">Ringkasan performa operasional dan rekonsiliasi keuangan.</p>
            </div>
            <div style="display: flex; gap: 0.75rem;">
                <button class="btn btn-outline" onclick="alert('Exporting to PDF...')"><i class="fas fa-file-pdf"></i> Export PDF</button>
                <button class="btn btn-primary" onclick="alert('Exporting to Excel...')"><i class="fas fa-file-excel"></i> Export Excel</button>
            </div>
        </div>

        <div class="grid grid-cols-4" style="margin-bottom: 2.5rem;">
            <div class="card" style="border-top: 4px solid var(--primary);">
                <div style="color: var(--text-muted); font-size: 0.8125rem; font-weight: 600; text-transform: uppercase;">Volume Pengiriman</div>
                <div style="font-size: 1.75rem; font-weight: 800; margin: 0.5rem 0;"><?php echo number_format($totalShipments); ?> <span style="font-size: 0.875rem; color: var(--text-muted); font-weight: 400;">Resi</span></div>
                <div style="font-size: 0.75rem; color: var(--success);"><i class="fas fa-trending-up"></i> Performa Stabil</div>
            </div>
            <div class="card" style="border-top: 4px solid var(--accent);">
                <div style="color: var(--text-muted); font-size: 0.8125rem; font-weight: 600; text-transform: uppercase;">Aliran Dokumen Keluar</div>
                <div style="font-size: 1.75rem; font-weight: 800; margin: 0.5rem 0;"><?php echo number_format($docStats['total_keluar']); ?> <span style="font-size: 0.875rem; color: var(--text-muted); font-weight: 400;">File</span></div>
                <div style="font-size: 0.75rem; color: var(--text-muted);">Internal Generated</div>
            </div>
            <div class="card" style="border-top: 4px solid var(--primary-light);">
                <div style="color: var(--text-muted); font-size: 0.8125rem; font-weight: 600; text-transform: uppercase;">Aliran Dokumen Masuk</div>
                <div style="font-size: 1.75rem; font-weight: 800; margin: 0.5rem 0;"><?php echo number_format($docStats['total_masuk']); ?> <span style="font-size: 0.875rem; color: var(--text-muted); font-weight: 400;">File</span></div>
                <div style="font-size: 0.75rem; color: var(--text-muted);">External Verified</div>
            </div>
            <div class="card" style="border-top: 4px solid var(--success);">
                <div style="color: var(--text-muted); font-size: 0.8125rem; font-weight: 600; text-transform: uppercase;">Revenue Terkumpul</div>
                <div style="font-size: 1.75rem; font-weight: 800; margin: 0.5rem 0;">Rp <?php echo number_format($totalInvoices ?: 0); ?></div>
                <div style="font-size: 0.75rem; color: var(--text-muted);">Clean Revenue</div>
            </div>
        </div>

        <!-- Main Report Table -->
        <div class="card" style="padding: 1.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h3 class="card-title">Ringkasan Operasional Terkini</h3>
                <div style="display: flex; gap: 0.5rem;">
                    <input type="date" class="form-control" style="font-size: 0.75rem; padding: 0.4rem;">
                    <span style="align-self: center;">s/d</span>
                    <input type="date" class="form-control" style="font-size: 0.75rem; padding: 0.4rem;">
                </div>
            </div>

            <table class="file-table" style="width: 100%;">
                <thead style="background: #f8fafc;">
                    <tr>
                        <th style="padding: 0.75rem 1rem;">Tanggal</th>
                        <th>No. Resi</th>
                        <th>Rute</th>
                        <th>Tonase (KG)</th>
                        <th>Nilai Invoice</th>
                        <th>Status Bayar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reportData as $row): ?>
                    <tr class="file-row">
                        <td style="padding: 1rem; font-size: 0.8125rem;"><?php echo date('d/m/Y', strtotime($row['created_at'])); ?></td>
                        <td style="font-weight: 700; color: var(--primary);"><?php echo $row['resi_number']; ?></td>
                        <td style="font-size: 0.8125rem;"><?php echo $row['origin']; ?> - <?php echo $row['destination']; ?></td>
                        <td style="font-weight: 600;"><?php echo number_format($row['weight_kg']); ?> KG</td>
                        <td style="font-weight: 700;">Rp <?php echo number_format($row['amount'] ?? 0); ?></td>
                        <td>
                            <span class="status-badge" style="background: <?php echo $row['pay_status'] == 'paid' ? 'rgba(16, 185, 129, 0.1)' : 'rgba(239, 68, 68, 0.1)'; ?>; 
                                                            color: <?php echo $row['pay_status'] == 'paid' ? '#166534' : '#b91c1c'; ?>;">
                                <?php echo strtoupper($row['pay_status'] ?? 'BELUM TERBIT'); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
