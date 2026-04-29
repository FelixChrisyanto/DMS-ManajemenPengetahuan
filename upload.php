<?php 
require_once 'includes/db.php';
include 'includes/head.php'; 
include 'includes/sidebar.php'; 

$message = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['document'])) {
    try {
        $file = $_FILES['document'];
        $shipmentId = $_POST['shipment_id'] ?: null;
        $category = $_POST['category'];
        
        $targetDir = "uploads/";
        $fileName = time() . "_" . basename($file["name"]);
        $targetPath = $targetDir . $fileName;

        if (move_uploaded_file($file["tmp_name"], $targetPath)) {
            $referenceResi = null;
            if ($shipmentId) {
                $stmt = $pdo->prepare("SELECT resi_number FROM shipments WHERE id = ?");
                $stmt->execute([$shipmentId]);
                $referenceResi = $stmt->fetchColumn();
            }

            $stmt = $pdo->prepare("INSERT INTO documents (shipment_id, reference_resi, file_name, file_path, category, uploaded_by) VALUES (?, ?, ?, ?, ?, ?)");
            // For now, uploaded_by is 1 (Admin)
            $stmt->execute([$shipmentId, $referenceResi, basename($file["name"]), $targetPath, $category, 1]);

            // NEW: Smart Link for Payment Proofs
            if ($category == 'payment_proof' && !empty($_POST['invoice_id'])) {
                $stmt = $pdo->prepare("UPDATE invoices SET payment_proof = ?, status = 'pending' WHERE id = ?");
                $stmt->execute([$targetPath, $_POST['invoice_id']]);
                $message = "File berhasil diarsipkan dan status Invoice diperbarui menjadi PENDING.";
            } else {
                $message = "File berhasil diunggah!";
            }
        } else {
            $message = "Gagal mengunggah file fisik.";
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
    }
}

$shipments = $pdo->query("SELECT id, resi_number FROM shipments WHERE deleted_at IS NULL")->fetchAll();
$unpaidInvoices = $pdo->query("SELECT id, invoice_number FROM invoices WHERE status = 'unpaid'")->fetchAll();
?>

<div class="content-wrapper">
    <?php include 'includes/header.php'; ?>

    <div class="container" style="max-width: 900px;">
        <div style="margin-bottom: 2rem;">
            <h1 style="font-size: 1.75rem; font-weight: 800; color: var(--primary);">Unggah Dokumen Baru</h1>
            <p style="color: var(--text-muted); margin-top: 0.25rem;">Tambahkan dokumen ke sistem pengarsipan PT Lintas Nusantara Ekspedisi.</p>
        </div>

        <?php if($message): ?>
            <div style="padding: 1rem; background: #eff6ff; color: #1e40af; border-radius: 8px; margin-bottom: 1.5rem; border: 1px solid #bfdbfe;">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <form method="POST" enctype="multipart/form-data">
                <div style="margin-bottom: 2rem;">
                    <label style="display: block; font-size: 0.875rem; font-weight: 600; margin-bottom: 1rem; color: var(--primary);">Pilih Berkas</label>
                    <div style="border: 2px dashed var(--primary-light); background: rgba(59, 130, 246, 0.05); border-radius: var(--radius-lg); padding: 3rem; text-align: center; position: relative; cursor: pointer;">
                        <i class="fas fa-cloud-arrow-up" style="font-size: 2.5rem; color: var(--primary); margin-bottom: 1rem;"></i>
                        <h4 style="margin-bottom: 0.5rem;">Klik atau Tarik file ke sini</h4>
                        <p style="font-size: 0.8125rem; color: var(--text-muted);">Mendukung PDF, JPG, PNG, DOCX (Maks 10MB)</p>
                        <input type="file" name="document" required style="position: absolute; inset: 0; opacity: 0; cursor: pointer;">
                    </div>
                </div>

                <div class="grid grid-cols-2" style="gap: 1.5rem; margin-bottom: 1.5rem;">
                    <div>
                        <label style="display: block; font-size: 0.875rem; font-weight: 600; margin-bottom: 0.5rem;">Kategori Dokumen</label>
                        <select name="category" id="category_select" required style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; background: white;">
                            <option value="surat_jalan">Surat Jalan</option>
                            <option value="resi">Resi Pengiriman</option>
                            <option value="invoice">Invoice / Tagihan</option>
                            <option value="payment_proof">Bukti Pembayaran</option>
                            <option value="bast">BAST (Serah Terima)</option>
                            <option value="manifest">Manifest Muatan</option>
                            <option value="operational">Laporan Operasional</option>
                        </select>
                    </div>
                    <div id="shipment_link_container">
                        <label style="display: block; font-size: 0.875rem; font-weight: 600; margin-bottom: 0.5rem;">Tautkan ke Pengiriman (Resi)</label>
                        <select name="shipment_id" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; background: white;">
                            <option value="">-- Tanpa Tautan (Umum) --</option>
                            <?php foreach($shipments as $s): ?>
                                <option value="<?php echo $s['id']; ?>"><?php echo $s['resi_number']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- NEW: Dynamic Invoice Link (Hidden by default) -->
                <div id="invoice_link_container" style="display: none; margin-bottom: 1.5rem;">
                    <label style="display: block; font-size: 0.875rem; font-weight: 600; margin-bottom: 0.5rem; color: var(--accent);">Tautkan ke Invoice Tagihan</label>
                    <select name="invoice_id" style="width: 100%; padding: 0.75rem; border: 1px solid var(--accent); border-radius: 8px; background: white;">
                        <option value="">-- Pilih No. Invoice --</option>
                        <?php foreach($unpaidInvoices as $inv): ?>
                            <option value="<?php echo $inv['id']; ?>"><?php echo $inv['invoice_number']; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.5rem;">Memilih invoice akan otomatis mengubah status tagihan menjadi 'Pending Verification'.</p>
                </div>

                <div style="display: flex; gap: 1rem; justify-content: flex-end; padding-top: 1.5rem; border-top: 1px solid var(--border-color);">
                    <button type="button" class="btn btn-outline">Batal</button>
                    <button type="submit" class="btn btn-primary" style="min-width: 150px;">Unggah Sekarang</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.getElementById('category_select').addEventListener('change', function() {
        const shipmentContainer = document.getElementById('shipment_link_container');
        const invoiceContainer = document.getElementById('invoice_link_container');
        
        if (this.value === 'payment_proof') {
            shipmentContainer.style.display = 'none';
            invoiceContainer.style.display = 'block';
        } else {
            shipmentContainer.style.display = 'block';
            invoiceContainer.style.display = 'none';
        }
    });
</script>

<?php include 'includes/footer.php'; ?>
