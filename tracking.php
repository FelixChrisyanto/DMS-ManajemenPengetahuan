<?php 
require_once 'includes/db.php';
include 'includes/head.php'; 
include 'includes/sidebar.php'; 

$resi = isset($_GET['resi']) ? trim($_GET['resi']) : '';
$shipment = null;
$history = [];

if (!empty($resi)) {
    $stmt = $pdo->prepare("SELECT s.*, r.origin, r.destination, ts.train_name, ts.departure_time, ts.arrival_time 
                           FROM shipments s 
                           JOIN routes r ON s.route_id = r.id 
                           LEFT JOIN train_schedules ts ON s.train_id = ts.id 
                           WHERE TRIM(s.resi_number) = ?");
    $stmt->execute([$resi]);
    $shipment = $stmt->fetch();

    if ($shipment) {
        // Simulated tracking history based on status
        $history = [
            ['status' => 'waiting', 'label' => 'Barang diterima di Gudang PT Lintas Nusantara', 'time' => $shipment['created_at'], 'icon' => 'fa-warehouse'],
        ];
        
        if ($shipment['status'] !== 'waiting') {
            $history[] = ['status' => 'shipped', 'label' => 'Diproses untuk pengangkatan Ke Kereta', 'time' => date('Y-m-d H:i:s', strtotime($shipment['created_at'] . ' +2 hours')), 'icon' => 'fa-dolly'];
            $history[] = ['status' => 'shipped', 'label' => 'Barang diberangkatkan dengan ' . $shipment['train_name'], 'time' => date('Y-m-d H:i:s', strtotime($shipment['created_at'] . ' +4 hours')), 'icon' => 'fa-train'];
        }
        
        if ($shipment['status'] === 'transit' || $shipment['status'] === 'arrived') {
            $history[] = ['status' => 'transit', 'label' => 'Dalam perjalanan menuju ' . $shipment['destination'], 'time' => date('Y-m-d H:i:s', strtotime($shipment['created_at'] . ' +8 hours')), 'icon' => 'fa-clock-rotate-left'];
        }

        if ($shipment['status'] === 'arrived') {
            $history[] = ['status' => 'arrived', 'label' => 'Tiba di Stasiun Tujuan. Siap diantar/diambil.', 'time' => date('Y-m-d H:i:s', strtotime($shipment['created_at'] . ' +12 hours')), 'icon' => 'fa-check-circle'];
        }
        
        $history = array_reverse($history); // Newest first

        // Fetch Related Documents
        $stmt_docs = $pdo->prepare("SELECT * FROM documents WHERE shipment_id = ? AND deleted_at IS NULL");
        $stmt_docs->execute([$shipment['id']]);
        $documents = $stmt_docs->fetchAll();
    }
}
?>

<div class="content-wrapper">
    <?php include 'includes/header.php'; ?>

    <div class="container" style="max-width: 800px;">
        <div style="margin-bottom: 2rem; text-align: center;">
            <h1 style="font-size: 2rem; font-weight: 800; color: var(--primary);">Lacak Pengiriman</h1>
            <p style="color: var(--text-muted); margin-top: 0.5rem;">Cek status pengiriman barang Anda secara real-time.</p>
        </div>

        <div class="card" style="margin-bottom: 2rem;">
            <form action="tracking.php" method="GET" style="display: flex; gap: 1rem;">
                <input type="text" name="resi" value="<?php echo htmlspecialchars($resi); ?>" placeholder="Masukkan Nomor Resi (contoh: LNE-...)" 
                       style="flex: 1; padding: 0.875rem; border: 2px solid var(--primary-light); border-radius: 999px; outline: none; font-size: 1rem; font-weight: 600;">
                <button type="submit" class="btn btn-primary" style="padding: 0 2rem; border-radius: 999px;">
                    <i class="fas fa-magnifying-glass"></i> Cari
                </button>
            </form>
        </div>

        <?php if ($resi && !$shipment): ?>
            <div class="card" style="text-align: center; border: 2px dashed #fee2e2; background: #fff1f2;">
                <i class="fas fa-circle-exclamation" style="font-size: 3rem; color: #ef4444; margin-bottom: 1rem;"></i>
                <h3 style="color: #991b1b;">Nomor Resi Tidak Ditemukan</h3>
                <p style="color: #b91c1c;">Mohon periksa kembali nomor resi yang Anda masukkan.</p>
            </div>
        <?php elseif ($shipment): ?>
            <div style="display: flex; flex-direction: column; gap: 2rem;">
                <!-- Result Summary -->
                <div class="card" style="padding: 0; overflow: hidden; border-left: 6px solid var(--primary);">
                    <div style="display: flex; gap: 2rem; align-items: center; padding: 2rem;">
                        <div style="flex: 1;">
                            <span style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted);">Status Saat Ini</span>
                            <h2 style="color: var(--primary); font-weight: 800; text-transform: uppercase; margin-top: 0.25rem;">
                                <?php echo $shipment['status']; ?>
                            </h2>
                        </div>
                        <div style="height: 40px; width: 1px; background: #e2e8f0;"></div>
                        <div style="flex: 1;">
                            <span style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted);">Rute</span>
                            <div style="font-weight: 700; margin-top: 0.25rem;">
                                <?php echo $shipment['origin']; ?> <i class="fas fa-arrow-right" style="font-size: 0.7rem;"></i> <?php echo $shipment['destination']; ?>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($shipment['goods_photo']): ?>
                    <div style="background: #f8fafc; padding: 1.5rem 2rem; border-top: 1px solid #e2e8f0; display: flex; align-items: center; gap: 1.5rem;">
                        <div style="width: 80px; height: 80px; border-radius: 8px; overflow: hidden; border: 2px solid white; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
                            <img src="<?php echo $shipment['goods_photo']; ?>" style="width: 100%; height: 100%; object-fit: cover;">
                        </div>
                        <div>
                            <div style="font-weight: 700; font-size: 0.9rem;">Foto Bukti Barang</div>
                            <div style="font-size: 0.75rem; color: var(--text-muted);">Foto diambil saat pendaftaran di stasiun asal.</div>
                            <button onclick="openPreview('<?php echo $shipment['goods_photo']; ?>', 'Foto Barang')" style="background: none; border: none; color: var(--primary); font-size: 0.75rem; font-weight: 700; cursor: pointer; padding: 0; margin-top: 0.25rem;">Lihat Ukuran Penuh <i class="fas fa-external-link-alt" style="font-size: 0.6rem;"></i></button>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if ($shipment['status'] === 'arrived'): ?>
                <div class="card" style="background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%); color: white; border: none; text-align: center; padding: 2.5rem;">
                    <i class="fas fa-box-open" style="font-size: 3.5rem; margin-bottom: 1rem; opacity: 0.9;"></i>
                    <h2 style="margin-bottom: 0.5rem; font-weight: 800;">Barang Siap Diambil!</h2>
                    <p style="opacity: 0.9; margin-bottom: 2rem; font-size: 0.95rem;">Silakan tunjukkan halaman ini atau nomor resi kepada petugas di stasiun <strong><?php echo $shipment['destination']; ?></strong>.</p>
                    <a href="bast_print.php?id=<?php echo $shipment['id']; ?>" class="btn" style="background: white; color: #1e3a8a; padding: 1rem 2.5rem; border-radius: 999px; font-weight: 800; font-size: 1rem; text-decoration: none; display: inline-flex; align-items: center; gap: 0.75rem; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.2);">
                        <i class="fas fa-file-signature"></i> Ambil Barang & Cetak BAST
                    </a>
                </div>
                <?php endif; ?>

                <!-- Related Documents Section -->
                <div class="card" style="padding: 1.5rem 2rem; background: #fdfdfd;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.75rem;">
                        <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--text-main);"><i class="fas fa-file-invoice" style="margin-right: 0.5rem; color: var(--primary);"></i> Arsip Dokumen Terkait</h3>
                        <span style="font-size: 0.75rem; color: var(--text-muted);"><?php echo count($documents); ?> Berkas Tersedia</span>
                    </div>

                    <?php if (empty($documents)): ?>
                        <div style="padding: 1rem; text-align: center; color: var(--text-muted); border: 2px dashed #f1f5f9; border-radius: 8px;">
                            <p style="font-size: 0.875rem;">Belum ada dokumen fisik yang diunggah untuk pengiriman ini.</p>
                        </div>
                    <?php else: ?>
                        <div class="grid grid-cols-2" style="gap: 1rem;">
                            <?php foreach ($documents as $doc): ?>
                                <div style="display: flex; align-items: center; gap: 1rem; padding: 0.75rem; border: 1px solid #f1f5f9; border-radius: 10px; background: white; transition: all 0.2s; cursor: default;">
                                    <div style="width: 40px; height: 40px; background: rgba(30, 58, 138, 0.05); color: var(--primary); border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                                        <i class="fas <?php 
                                            echo strpos($doc['file_name'], '.pdf') !== false ? 'fa-file-pdf' : 'fa-file-image'; 
                                        ?>"></i>
                                    </div>
                                    <div style="flex: 1; overflow: hidden;">
                                        <div style="font-size: 0.8125rem; font-weight: 700; color: var(--text-main); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo $doc['file_name']; ?>">
                                            <?php echo $doc['file_name']; ?>
                                        </div>
                                        <div style="font-size: 0.65rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600; margin-top: 0.15rem;">
                                            <?php echo str_replace('_', ' ', $doc['category']); ?>
                                        </div>
                                    </div>
                                    <button type="button" onclick="openPreview('<?php echo addslashes($doc['file_path']); ?>', '<?php echo addslashes($doc['file_name']); ?>')" 
                                            style="background: none; border: none; color: var(--primary); font-size: 1rem; cursor: pointer; padding: 0.5rem;" title="Pratinjau">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <a href="<?php echo $doc['file_path']; ?>" download style="color: var(--text-muted); font-size: 1rem;" title="Download">
                                        <i class="fas fa-circle-arrow-down"></i>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Timeline -->
                <div class="card" style="padding: 2.5rem;">
                    <h3 style="margin-bottom: 2rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 1rem;">Riwayat Perjalanan</h3>
                    <div style="display: flex; flex-direction: column; gap: 0;">
                        <?php foreach($history as $index => $h): ?>
                        <div style="display: flex; gap: 1.5rem; position: relative; padding-bottom: 2rem;">
                            <?php if ($index < count($history) - 1): ?>
                                <div style="position: absolute; left: 17px; top: 35px; bottom: -5px; width: 2px; background: #e2e8f0;"></div>
                            <?php endif; ?>
                            
                            <div style="width: 36px; height: 36px; border-radius: 50%; background: <?php echo $index === 0 ? 'var(--primary)' : '#f1f5f9'; ?>; color: <?php echo $index === 0 ? 'white' : '#94a3b8'; ?>; display: flex; align-items: center; justify-content: center; z-index: 1;">
                                <i class="fas <?php echo $h['icon']; ?>" style="font-size: 0.875rem;"></i>
                            </div>
                            
                            <div style="flex: 1;">
                                <div style="font-weight: 700; color: <?php echo $index === 0 ? 'var(--text-main)' : 'var(--text-muted)'; ?>; font-size: 0.9375rem;">
                                    <?php echo $h['label']; ?>
                                </div>
                                <div style="font-size: 0.75rem; color: #94a3b8; margin-top: 0.25rem;">
                                    <?php echo date('d M Y, H:i', strtotime($h['time'])); ?> WIB
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
    /* Modal Styles */
    #previewModal {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.8);
        z-index: 10000;
        align-items: center;
        justify-content: center;
        padding: 2rem;
        backdrop-filter: blur(4px);
    }
    .modal-content {
        background: white;
        width: 100%;
        max-width: 1000px;
        height: 90vh;
        border-radius: 12px;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
    }
    .modal-header {
        padding: 1rem 1.5rem;
        background: white;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
</style>

<!-- Preview Modal -->
<div id="previewModal">
    <div class="modal-content">
        <div class="modal-header">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <i class="fas fa-file-lines" style="color: var(--primary); font-size: 1.25rem;"></i>
                <h3 id="previewTitle" style="font-size: 1rem; margin: 0;">Preview Dokumen</h3>
            </div>
            <button onclick="closePreview()" class="btn btn-outline" style="padding: 0.25rem 0.5rem; border: none; font-size: 1.25rem;">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div id="previewBody" style="flex: 1; background: #f1f5f9; display: flex; align-items: center; justify-content: center; overflow: hidden;">
            <iframe id="previewFrame" src="" style="width: 100%; height: 100%; border: none;"></iframe>
            <div id="noPreview" style="display: none; text-align: center; padding: 3rem;">
                <i class="fas fa-file-circle-exclamation" style="font-size: 4rem; color: var(--muted); margin-bottom: 1rem;"></i>
                <h4>Preview Tidak Tersedia</h4>
                <p style="color: var(--text-muted);">Dokumen ini (Word/Excel) harus diunduh untuk dapat dilihat.</p>
                <a id="downloadFallback" href="#" class="btn btn-primary" style="margin-top: 1rem;">Unduh Sekarang</a>
            </div>
        </div>
    </div>
</div>

<script>
    function openPreview(url, name) {
        console.log("Opening preview for:", url, name);
        const modal = document.getElementById('previewModal');
        const frame = document.getElementById('previewFrame');
        const noPreview = document.getElementById('noPreview');
        const title = document.getElementById('previewTitle');
        const downloadBtn = document.getElementById('downloadFallback');

        if (!modal || !frame) return;

        title.innerText = "Pratinjau: " + name;
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';

        // Check file extension
        const ext = name.split('.').pop().toLowerCase();
        if (['docx', 'xlsx', 'doc', 'xls'].includes(ext)) {
            frame.style.display = 'none';
            noPreview.style.display = 'block';
            downloadBtn.href = url;
        } else {
            frame.style.display = 'block';
            noPreview.style.display = 'none';
            frame.src = url;
        }
    }

    function closePreview() {
        const modal = document.getElementById('previewModal');
        const frame = document.getElementById('previewFrame');
        modal.style.display = 'none';
        frame.src = '';
        document.body.style.overflow = '';
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === "Escape") closePreview();
    });
</script>

<?php include 'includes/footer.php'; ?>
