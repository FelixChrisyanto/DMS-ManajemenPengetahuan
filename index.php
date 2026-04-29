<?php 
require_once 'includes/db.php';
include 'includes/head.php'; 
include 'includes/sidebar.php'; 

// Fetch Counts for Dashboard
try {
    // Total Documents (non-deleted - we don't have soft delete for docs yet)
    $stmt = $pdo->query("SELECT COUNT(*) FROM documents");
    $totalDocs = $stmt->fetchColumn();

    // Today's Shipments
    $stmt = $pdo->query("SELECT COUNT(*) FROM shipments WHERE date(created_at) = date('now') AND deleted_at IS NULL");
    $todayShipments = $stmt->fetchColumn();

    // Active Trains (Simple count from train_schedules)
    $stmt = $pdo->query("SELECT COUNT(*) FROM train_schedules WHERE LOWER(status) = 'available'");
    $activeTrains = $stmt->fetchColumn();

    // Unpaid Invoices
    $stmt = $pdo->query("SELECT COUNT(*) FROM invoices WHERE status = 'unpaid'");
    $unpaidInvoices = $stmt->fetchColumn();

    // Pending Verification (Data Masuk Real-time)
    $stmt = $pdo->query("SELECT COUNT(*) FROM invoices WHERE status = 'pending'");
    $pendingInvoices = $stmt->fetchColumn();

    // Fetch Recent Activity Feed
    $recentActivity = $pdo->query("(SELECT 'doc' as type, file_name as title, category as sub, created_at, reference_resi as resi FROM documents WHERE deleted_at IS NULL)
                                   UNION ALL
                                   (SELECT 'payment' as type, 'Konfirmasi Pembayaran' as title, invoice_number as sub, created_at, NULL as resi FROM invoices WHERE status = 'pending')
                                   UNION ALL
                                   (SELECT 'shipment' as type, 'Pengiriman Baru' as title, resi_number as sub, created_at, resi_number as resi FROM shipments WHERE deleted_at IS NULL)
                                   ORDER BY created_at DESC LIMIT 5")->fetchAll();

    // Fetch Chart Data: Total Weight per Week for Current Month
    $stmt = $pdo->query("SELECT 
                            (CAST(strftime('%d', created_at) AS INTEGER) - 1) / 7 + 1 AS week_number,
                            SUM(weight_kg) as total_weight
                        FROM shipments
                        WHERE strftime('%m', created_at) = strftime('%m', 'now')
                          AND strftime('%Y', created_at) = strftime('%Y', 'now')
                          AND deleted_at IS NULL
                        GROUP BY week_number
                        ORDER BY week_number");
    $chartData = [0, 0, 0, 0];
    while ($row = $stmt->fetch()) {
        $idx = (int)$row['week_number'] - 1;
        if ($idx >= 0 && $idx < 4) {
            $chartData[$idx] = (float)$row['total_weight'];
        }
    }

} catch (Exception $e) {
    $totalDocs = $todayShipments = $activeTrains = $unpaidInvoices = $pendingInvoices = 0;
    $recentActivity = [];
    $chartData = [0, 0, 0, 0];
}

function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    if ($diff->d > 0) return $diff->d . ' hri lalu';
    if ($diff->h > 0) return $diff->h . ' jam lalu';
    if ($diff->i > 0) return $diff->i . ' mnt lalu';
    return 'Baru saja';
}
?>

<div class="content-wrapper">
    <?php include 'includes/header.php'; ?>

    <div class="container">
        <!-- Header Section -->
        <div style="margin-bottom: 2rem; display: flex; align-items: center; justify-content: space-between;">
            <div>
                <h1 style="font-size: 1.75rem; font-weight: 800; color: var(--primary);">Ringkasan Operasional</h1>
                <p style="color: var(--text-muted); margin-top: 0.25rem;">PT Lintas Nusantara Ekspedisi - Monitoring Logistik Kereta Api</p>
            </div>
            <div style="display: flex; gap: 1rem;">
                <a href="reports.php" class="btn btn-outline" style="padding: 0.75rem 1.25rem; font-weight: 600; border-radius: 999px;">
                    <i class="fas fa-file-export"></i> Laporan
                </a>
                <a href="upload.php" class="btn btn-primary" style="padding: 0.75rem 1.25rem; font-weight: 600; border-radius: 999px;">
                    <i class="fas fa-plus"></i> Unggah Dokumen Baru
                </a>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-4" style="margin-bottom: 2.5rem;">
            <a href="schedules.php" class="card" style="text-decoration: none; transition: transform 0.2s; cursor: pointer;" onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform='none'">
                <div style="display: flex; gap: 1rem; align-items: start;">
                    <div style="background: rgba(30, 58, 138, 0.1); color: var(--primary); width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; flex-shrink: 0;">
                        <i class="fas fa-train"></i>
                    </div>
                    <div>
                        <div style="font-size: 0.875rem; color: var(--text-muted); font-weight: 500;">Jadwal Aktif</div>
                        <div style="font-size: 1.5rem; font-weight: 700; margin-top: 0.25rem; color: var(--text-main);"><?php echo number_format($activeTrains); ?></div>
                        <div style="font-size: 0.75rem; color: var(--success); margin-top: 0.5rem;">Kapasitas Tersedia</div>
                    </div>
                </div>
            </a>

            <a href="shipping.php" class="card" style="text-decoration: none; transition: transform 0.2s; cursor: pointer;" onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform='none'">
                <div style="display: flex; gap: 1rem; align-items: start;">
                    <div style="background: rgba(249, 115, 22, 0.1); color: var(--accent); width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; flex-shrink: 0;">
                        <i class="fas fa-box-open"></i>
                    </div>
                    <div>
                        <div style="font-size: 0.875rem; color: var(--text-muted); font-weight: 500;">Muatan Hari Ini</div>
                        <div style="font-size: 1.5rem; font-weight: 700; margin-top: 0.25rem; color: var(--text-main);"><?php echo number_format($todayShipments); ?></div>
                        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.5rem;">Resi Pengiriman</div>
                    </div>
                </div>
            </a>

            <a href="invoices.php" class="card" style="text-decoration: none; transition: transform 0.2s; cursor: pointer;" onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform='none'">
                <div style="display: flex; gap: 1rem; align-items: start;">
                    <div style="background: rgba(239, 68, 68, 0.1); color: var(--danger); width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; flex-shrink: 0;">
                        <i class="fas fa-receipt"></i>
                    </div>
                    <div>
                        <div style="font-size: 0.875rem; color: var(--text-muted); font-weight: 500;">Invoice Belum Lunas</div>
                        <div style="font-size: 1.5rem; font-weight: 700; margin-top: 0.25rem; color: var(--text-main);"><?php echo number_format($unpaidInvoices); ?></div>
                        <div style="font-size: 0.75rem; color: var(--danger); margin-top: 0.5rem;">Membutuhkan Follow-up</div>
                    </div>
                </div>
            </a>

            <a href="invoices.php?status=pending" class="card" style="text-decoration: none; transition: transform 0.2s; cursor: pointer;" onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform='none'">
                <div style="display: flex; gap: 1rem; align-items: start;">
                    <div style="background: rgba(245, 158, 11, 0.1); color: #f59e0b; width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; flex-shrink: 0;">
                        <i class="fas fa-clock-rotate-left"></i>
                    </div>
                    <div>
                        <div style="font-size: 0.875rem; color: var(--text-muted); font-weight: 500;">Perlu Verifikasi</div>
                        <div style="font-size: 1.5rem; font-weight: 700; margin-top: 0.25rem; color: var(--text-main);"><?php echo number_format($pendingInvoices); ?></div>
                        <div style="font-size: 0.75rem; color: #f59e0b; margin-top: 0.5rem;">Dokumen Masuk</div>
                    </div>
                </div>
            </a>
        </div>

        <!-- Charts & Activity Row -->
        <div class="grid grid-cols-3">
            <!-- Activity Chart -->
            <div class="card" style="grid-column: span 2;">
                <div class="card-header">
                    <h3 class="card-title">Volume Pengiriman Kereta API (Bulan Ini)</h3>
                    <div style="display: flex; gap: 0.5rem;">
                        <span style="font-size: 0.75rem; color: var(--text-muted);">Real-time Data</span>
                    </div>
                </div>
                <div style="height: 320px; width: 100%;">
                    <canvas id="activityChart"></canvas>
                </div>
            </div>

            <!-- Real-time Activity Feed -->
            <div class="card">
                <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                    <h3 class="card-title">Aktivitas Dokumen Real-time</h3>
                    <span style="font-size: 0.7rem; color: var(--success); font-weight: 700;"><i class="fas fa-circle" style="font-size: 0.5rem; margin-right: 0.25rem;"></i> LIVE</span>
                </div>
                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    <?php if (empty($recentActivity)): ?>
                        <p style="text-align: center; font-size: 0.875rem; color: var(--text-muted); padding: 1rem;">Belum ada aktivitas hari ini.</p>
                    <?php endif; ?>
                    
                    <?php foreach ($recentActivity as $act): ?>
                        <?php 
                            $icon = 'fa-file-alt'; $color = '#3b82f6'; $bg = '#eff6ff';
                            $link = 'explorer.php?resi=' . ($act['resi'] ?? '');
                            if ($act['type'] == 'payment') { 
                                $icon = 'fa-receipt'; $color = '#f59e0b'; $bg = '#fffbeb'; 
                                $link = 'invoices.php?status=pending';
                            }
                            if ($act['type'] == 'shipment') { 
                                $icon = 'fa-truck-fast'; $color = '#10b981'; $bg = '#ecfdf5'; 
                                $link = 'tracking.php?resi=' . ($act['resi'] ?? '');
                            }
                        ?>
                        <div onclick="window.location.href='<?php echo $link; ?>'" style="display: flex; gap: 1rem; align-items: center; padding: 0.75rem; border-radius: 10px; transition: background 0.2s; cursor: pointer;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
                            <div style="width: 40px; height: 40px; border-radius: 10px; background: <?php echo $bg; ?>; color: <?php echo $color; ?>; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                <i class="fas <?php echo $icon; ?>"></i>
                            </div>
                            <div style="flex-grow: 1; min-width: 0;">
                                <div style="font-size: 0.8125rem; font-weight: 700; color: var(--text-main); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo $act['title']; ?></div>
                                <div style="font-size: 0.75rem; color: var(--text-muted);"><?php echo $act['sub']; ?></div>
                            </div>
                            <div style="font-size: 0.7rem; color: var(--text-muted); font-weight: 500; white-space: nowrap;">
                                <?php echo time_elapsed_string($act['created_at']); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <a href="explorer.php" style="display: block; text-align: center; margin-top: 1.5rem; font-size: 0.8125rem; color: var(--primary); font-weight: 600; text-decoration: none;">Lihat Semua Dokumen <i class="fas fa-arrow-right" style="font-size: 0.7rem; margin-left: 0.25rem;"></i></a>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('activityChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Minggu 1', 'Minggu 2', 'Minggu 3', 'Minggu 4'],
                datasets: [{
                    label: 'Total Tonase (KG)',
                    data: <?php echo json_encode($chartData); ?>,
                    backgroundColor: '#1e3a8a',
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: '#f1f5f9' },
                        ticks: { color: '#94a3b8' }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { color: '#94a3b8' }
                    }
                }
            }
        });
    });
</script>

<?php include 'includes/footer.php'; ?>
