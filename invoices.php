<?php 
require_once 'includes/db.php';

$message = "";

// Handle Delete
if (isset($_GET['delete'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM invoices WHERE id = ?");
        $stmt->execute([$_GET['delete']]);
        header("Location: invoices.php?msg=deleted");
        exit;
    } catch (Exception $e) {
        $message = "Gagal menghapus: " . $e->getMessage();
    }
}

// Handle Status Toggle
// Handle Verification Approving
if (isset($_GET['verify'])) {
    $stmt = $pdo->prepare("UPDATE invoices SET status = 'paid' WHERE id = ?");
    $stmt->execute([$_GET['verify']]);
    header("Location: invoices.php?msg=paid");
    exit;
}

// Handle Verification Rejection
if (isset($_GET['reject'])) {
    $stmt = $pdo->prepare("UPDATE invoices SET status = 'unpaid', payment_proof = NULL WHERE id = ?");
    $stmt->execute([$_GET['reject']]);
    header("Location: invoices.php?msg=rejected");
    exit;
}

if (isset($_GET['paid'])) {
    $stmt = $pdo->prepare("UPDATE invoices SET status = 'paid' WHERE id = ?");
    $stmt->execute([$_GET['paid']]);
    header("Location: invoices.php?msg=paid");
    exit;
}

if (isset($_GET['unpaid'])) {
    $stmt = $pdo->prepare("UPDATE invoices SET status = 'unpaid' WHERE id = ?");
    $stmt->execute([$_GET['unpaid']]);
    header("Location: invoices.php?msg=unpaid");
    exit;
}

// Handle Proof Upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['payment_proof'])) {
    try {
        $file = $_FILES['payment_proof'];
        $invoiceId = $_POST['invoice_id'];
        
        $targetDir = "uploads/payments/";
        if (!file_exists($targetDir)) mkdir($targetDir, 0777, true);
        
        $fileName = time() . "_proof_" . basename($file["name"]);
        $targetPath = $targetDir . $fileName;

        if (move_uploaded_file($file["tmp_name"], $targetPath)) {
            $stmt = $pdo->prepare("UPDATE invoices SET payment_proof = ?, status = 'pending' WHERE id = ?");
            $stmt->execute([$targetPath, $invoiceId]);
            $message = "Bukti pembayaran berhasil diunggah! Menunggu verifikasi admin.";
        } else {
            $message = "Gagal mengunggah file fisik.";
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
    }
}

// Fetch Invoices with Shipment details
$where = "";
$params = [];
if (isset($_GET['status'])) {
    $where = " WHERE i.status = ?";
    $params = [$_GET['status']];
}

$stmt = $pdo->prepare("SELECT i.*, s.resi_number, s.sender_name, s.receiver_name, s.weight_kg 
                        FROM invoices i 
                        JOIN shipments s ON i.shipment_id = s.id 
                        $where
                        ORDER BY i.created_at DESC");
$stmt->execute($params);
$invoices = $stmt->fetchAll();

include 'includes/head.php'; 
include 'includes/sidebar.php'; 
?>

<div class="content-wrapper">
    <?php include 'includes/header.php'; ?>

    <div class="container">
        <div style="margin-bottom: 2rem; display: flex; align-items: center; justify-content: space-between;">
            <div>
                <h1 style="font-size: 1.75rem; font-weight: 800; color: var(--primary);">Manajemen Penagihan (Invoice)</h1>
                <p style="color: var(--text-muted); margin-top: 0.25rem;">Monitoring status pembayaran dan penagihan pelanggan.</p>
            </div>
            <div style="display: flex; gap: 0.75rem;">
                <button class="btn btn-outline"><i class="fas fa-file-excel"></i> Export Excel</button>
            </div>
        </div>

        <?php if($message): ?>
            <div style="padding: 1rem; background: #dcfce7; color: #166534; border-radius: 8px; margin-bottom: 1.5rem; border: 1px solid #bbf7d0;">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if(isset($_GET['msg'])): ?>
            <div style="padding: 1rem; background: #dcfce7; color: #166534; border-radius: 8px; margin-bottom: 1.5rem; border: 1px solid #bbf7d0;">
                <?php 
                    if($_GET['msg'] == 'paid') echo "Invoice berhasil diverifikasi dan ditandai lunas.";
                    if($_GET['msg'] == 'unpaid') echo "Status invoice diubah ke belum lunas.";
                    if($_GET['msg'] == 'rejected') echo "Bukti pembayaran ditolak. Status kembali ke belum lunas.";
                    if($_GET['msg'] == 'deleted') echo "Invoice berhasil dihapus.";
                ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-3" style="margin-bottom: 2rem; gap: 1.5rem;">
            <div class="card" style="background: var(--primary); color: white; border: none;">
                <div style="font-size: 0.875rem; opacity: 0.8; margin-bottom: 0.5rem;">Total Tagihan Pending</div>
                <div style="font-size: 1.75rem; font-weight: 800;">
                    Rp <?php 
                        $unpaid = array_filter($invoices, function($i) { return $i['status'] == 'unpaid'; });
                        echo number_format(array_sum(array_column($unpaid, 'amount')));
                    ?>
                </div>
            </div>
            <div class="card">
                <div style="font-size: 0.875rem; color: var(--text-muted); margin-bottom: 0.5rem;">Invoice Terbit (Bulan Ini)</div>
                <div style="font-size: 1.75rem; font-weight: 800; color: var(--text-main);"><?php echo count($invoices); ?></div>
            </div>
            <div class="card">
                <div style="font-size: 0.875rem; color: var(--text-muted); margin-bottom: 0.5rem;">Menunggu Konfirmasi</div>
                <div style="font-size: 1.75rem; font-weight: 800; color: #f59e0b;">
                    <?php 
                        $pending = array_filter($invoices, function($i) { return $i['status'] == 'pending'; });
                        echo count($pending);
                    ?>
                </div>
            </div>
        </div>

        <div class="card" style="padding: 0; overflow: hidden;">
            <table class="file-table" style="width: 100%;">
                <thead style="background: #f8fafc;">
                    <tr>
                        <th style="padding: 1rem 1.5rem;">No. Invoice</th>
                        <th>Resi & Pelanggan</th>
                        <th>Jumlah Tagihan</th>
                        <th>Jatuh Tempo</th>
                        <th>Status</th>
                        <th style="text-align: right; padding-right: 1.5rem;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invoices as $i): ?>
                    <tr class="file-row">
                        <td style="padding: 1.25rem 1.5rem;">
                            <div style="font-weight: 700; color: var(--primary);"><?php echo $i['invoice_number']; ?></div>
                            <div style="font-size: 0.7rem; color: var(--text-muted);">Tgl: <?php echo date('d/m/Y', strtotime($i['created_at'])); ?></div>
                        </td>
                        <td>
                            <div style="font-size: 0.875rem; font-weight: 600;"><?php echo $i['resi_number']; ?></div>
                            <div style="font-size: 0.75rem; color: var(--text-muted);"><?php echo $i['sender_name']; ?></div>
                        </td>
                        <td>
                            <div style="font-size: 0.875rem; font-weight: 700; color: var(--text-main);">Rp <?php echo number_format($i['amount']); ?></div>
                            <div style="font-size: 0.7rem; color: var(--text-muted); text-decoration: underline;"><?php echo $i['weight_kg']; ?> KG x Tarif</div>
                        </td>
                        <td>
                            <div style="font-size: 0.8125rem; color: <?php echo (strtotime($i['due_date']) < time() && $i['status'] == 'unpaid') ? 'var(--danger)' : 'var(--text-main)'; ?>;">
                                <?php echo date('d M Y', strtotime($i['due_date'])); ?>
                            </div>
                        </td>
                        <td>
                            <?php 
                                $status = $i['status'];
                                $bg = 'rgba(249, 115, 22, 0.1)';
                                $color = '#9a3412';
                                if ($status == 'paid') { $bg = 'rgba(16, 185, 129, 0.1)'; $color = '#166534'; }
                                if ($status == 'pending') { $bg = 'rgba(245, 158, 11, 0.1)'; $color = '#b45309'; }
                            ?>
                            <span class="status-badge" style="background: <?php echo $bg; ?>; color: <?php echo $color; ?>;">
                                <?php echo ($status == 'pending') ? 'PENDING VERIFY' : strtoupper($status); ?>
                            </span>
                        </td>
                        <td style="text-align: right; padding-right: 1.5rem;">
                            <div class="file-actions" style="justify-content: flex-end;">
                                <a href="invoice_print.php?id=<?php echo $i['id']; ?>" target="_blank" class="action-btn" title="Cetak PDF"><i class="fas fa-file-pdf"></i></a>
                                
                                <?php if($i['status'] == 'unpaid'): ?>
                                    <button class="action-btn" title="Upload Bukti" onclick="openUploadModal(<?php echo $i['id']; ?>, '<?php echo $i['invoice_number']; ?>')"><i class="fas fa-cloud-arrow-up"></i></button>
                                    <a href="invoices.php?paid=<?php echo $i['id']; ?>" class="action-btn" title="Tandai Lunas Manual" style="color: var(--success);"><i class="fas fa-check-circle"></i></a>
                                <?php elseif($i['status'] == 'pending'): ?>
                                    <a href="invoices.php?verify=<?php echo $i['id']; ?>" class="action-btn" title="Verifikasi Lunas" style="color: var(--success);"><i class="fas fa-check-double"></i></a>
                                    <a href="invoices.php?reject=<?php echo $i['id']; ?>" class="action-btn" title="Tolak Bukti" style="color: var(--danger);" onclick="return confirm('Tolak bukti pembayaran ini?')"><i class="fas fa-times-circle"></i></a>
                                    <?php if($i['payment_proof']): ?>
                                        <a href="<?php echo $i['payment_proof']; ?>" target="_blank" class="action-btn" title="Cek Bukti" style="color: var(--primary);"><i class="fas fa-image"></i></a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <a href="payment_print.php?id=<?php echo $i['id']; ?>" target="_blank" class="action-btn" title="Cetak Kuitansi" style="color: var(--success);"><i class="fas fa-receipt"></i></a>
                                    <a href="invoices.php?unpaid=<?php echo $i['id']; ?>" class="action-btn" title="Batalkan Lunas" style="color: var(--warning);"><i class="fas fa-undo"></i></a>
                                    <?php if($i['payment_proof']): ?>
                                        <a href="<?php echo $i['payment_proof']; ?>" target="_blank" class="action-btn" title="Lihat Bukti" style="color: var(--primary);"><i class="fas fa-image"></i></a>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <button class="action-btn" title="Kirim Email" onclick="alert('Fitur pengiriman email sedang dalam pengembangan (Memerlukan Konfigurasi SMTP).')"><i class="fas fa-envelope"></i></button>
                                <a href="invoices.php?delete=<?php echo $i['id']; ?>" class="action-btn" style="color: #ef4444;" title="Hapus" onclick="return confirm('Apakah Anda yakin ingin menghapus invoice ini?')"><i class="fas fa-trash"></i></a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Upload Bukti -->
<div id="modalUpload" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center;">
    <div class="card" style="width: 400px; padding: 2.5rem;">
        <h3 style="margin-bottom: 1rem;">Upload Bukti Bayar</h3>
        <p id="uploadInfo" style="font-size: 0.875rem; color: var(--text-muted); margin-bottom: 1.5rem;"></p>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="invoice_id" id="modal_invoice_id">
            <div style="margin-bottom: 1.5rem;">
                <label style="display: block; font-size: 0.875rem; font-weight: 600; margin-bottom: 0.5rem;">Pilih File Gambar/PDF</label>
                <input type="file" name="payment_proof" required style="width: 100%; padding: 0.5rem; border: 1px solid var(--border-color); border-radius: 8px;">
            </div>
            <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                <button type="button" class="btn btn-outline" onclick="closeModal()">Batal</button>
                <button type="submit" class="btn btn-primary">Unggah & Lunasi</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openUploadModal(id, num) {
        document.getElementById('modal_invoice_id').value = id;
        document.getElementById('uploadInfo').innerText = "Unggah bukti untuk " + num;
        document.getElementById('modalUpload').style.display = 'flex';
    }

    function closeModal() {
        document.getElementById('modalUpload').style.display = 'none';
    }
</script>

<?php include 'includes/footer.php'; ?>
