<?php 
require_once 'includes/db.php';

// Handle Soft Delete
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("UPDATE documents SET deleted_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    header("Location: explorer.php?msg=deleted");
    exit;
}

// Handle Restore
if (isset($_GET['restore'])) {
    $stmt = $pdo->prepare("UPDATE documents SET deleted_at = NULL WHERE id = ?");
    $stmt->execute([$_GET['restore']]);
    header("Location: explorer.php?msg=restored");
    exit;
}

include 'includes/head.php'; 
include 'includes/sidebar.php'; 

// Search and Filter logic
$folder = $_GET['folder'] ?? 'all';
$search = $_GET['search'] ?? '';
$isTrash = ($folder === 'trash');

$sql = "SELECT d.*, COALESCE(s.resi_number, d.reference_resi) as display_resi FROM documents d 
        LEFT JOIN shipments s ON d.shipment_id = s.id 
        WHERE ";

if ($isTrash) {
    $sql .= "d.deleted_at IS NOT NULL ";
} else {
    $sql .= "d.deleted_at IS NULL ";
    if ($folder !== 'all') {
        $sql .= "AND d.category = " . $pdo->quote($folder) . " ";
    }
}

if (!empty($search)) {
    $sql .= "AND (d.file_name LIKE :search OR d.reference_resi LIKE :search OR s.resi_number LIKE :search) ";
}

$stmt = $pdo->prepare($sql);
if (!empty($search)) {
    $stmt->bindValue(':search', "%$search%");
}
$stmt->execute();
$documents = $stmt->fetchAll();

// Counts for sidebar
$counts = $pdo->query("SELECT category, COUNT(*) as total FROM documents WHERE deleted_at IS NULL GROUP BY category")->fetchAll(PDO::FETCH_KEY_PAIR);
$trashCount = $pdo->query("SELECT COUNT(*) FROM documents WHERE deleted_at IS NOT NULL")->fetchColumn();
?>

<div class="content-wrapper">
    <?php include 'includes/header.php'; ?>

    <div class="explorer-container" style="display: flex; height: calc(100vh - 64px);">
        <!-- Sidebar Tree -->
        <aside style="width: 250px; background: white; border-right: 1px solid var(--border-color); padding: 1.5rem 1rem;">
            <h4 style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: 1.5rem;">KATEGORI ARSIP</h4>
            
            <nav style="display: flex; flex-direction: column; gap: 0.25rem;">
                <a href="explorer.php?folder=all" class="tree-link <?php echo $folder === 'all' ? 'active' : ''; ?>">
                    <i class="fas fa-layer-group"></i> <span>Semua Berkas</span>
                </a>
                <a href="explorer.php?folder=surat_jalan" class="tree-link <?php echo $folder === 'surat_jalan' ? 'active' : ''; ?>">
                    <i class="fas fa-folder"></i> <span>Surat Jalan</span>
                    <span class="count-badge"><?php echo $counts['surat_jalan'] ?? 0; ?></span>
                </a>
                <a href="explorer.php?folder=resi" class="tree-link <?php echo $folder === 'resi' ? 'active' : ''; ?>">
                    <i class="fas fa-folder"></i> <span>Resi Pengiriman</span>
                    <span class="count-badge"><?php echo $counts['resi'] ?? 0; ?></span>
                </a>
                <a href="explorer.php?folder=invoice" class="tree-link <?php echo $folder === 'invoice' ? 'active' : ''; ?>">
                    <i class="fas fa-folder"></i> <span>Invoice / Tagihan</span>
                    <span class="count-badge"><?php echo $counts['invoice'] ?? 0; ?></span>
                </a>
                <a href="explorer.php?folder=bast" class="tree-link <?php echo $folder === 'bast' ? 'active' : ''; ?>">
                    <i class="fas fa-file-signature"></i> <span>BAST</span>
                    <span class="count-badge"><?php echo $counts['bast'] ?? 0; ?></span>
                </a>
                <a href="explorer.php?folder=manifest" class="tree-link <?php echo $folder === 'manifest' ? 'active' : ''; ?>">
                    <i class="fas fa-clipboard-list"></i> <span>Manifest</span>
                    <span class="count-badge"><?php echo $counts['manifest'] ?? 0; ?></span>
                </a>
                
                <div style="margin-top: 2rem; border-top: 1px solid #f1f5f9; padding-top: 1rem;">
                    <a href="explorer.php?folder=trash" class="tree-link <?php echo $isTrash ? 'active' : ''; ?>" style="color: #ef4444;">
                        <i class="fas fa-trash-can"></i> <span>Sampah</span>
                        <span class="count-badge" style="background: #fee2e2; color: #ef4444;"><?php echo $trashCount; ?></span>
                    </a>
                </div>
            </nav>
        </aside>

        <!-- Main Content -->
        <main style="flex: 1; display: flex; flex-direction: column; background: #f8fafc;">
            <div style="padding: 1.5rem 2rem; background: white; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <h2 style="font-size: 1.25rem; font-weight: 700; color: var(--primary);">
                        <?php echo $isTrash ? 'Folder Sampah' : 'Penjelajah Dokumen'; ?>
                    </h2>
                    <form action="explorer.php" method="GET" style="position: relative; width: 300px;">
                        <input type="hidden" name="folder" value="<?php echo $folder; ?>">
                        <i class="fas fa-search" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 0.875rem;"></i>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Cari nama atau nomor resi..." 
                               style="width: 100%; padding: 0.5rem 1rem 0.5rem 2.5rem; border-radius: 999px; border: 1px solid var(--border-color); outline: none; font-size: 0.875rem;">
                    </form>
                </div>
                <div style="display: flex; gap: 0.75rem;">
                    <a href="upload.php" class="btn btn-primary" style="font-size: 0.8125rem;"><i class="fas fa-plus"></i> Unggah</a>
                </div>
            </div>

            <div style="flex: 1; padding: 1.5rem; overflow-y: auto;">
                <div class="card" style="padding: 0; overflow: hidden; border: none; box-shadow: var(--shadow-sm);">
                    <table class="file-table" style="width: 100%; background: white;">
                        <thead style="background: #f8fafc;">
                            <tr>
                                <th style="padding: 1rem 1.5rem;">Nama Berkas</th>
                                <th>Kategori</th>
                                <th>Terkait Resi</th>
                                <th>Tgl Unggah</th>
                                <th style="text-align: right; padding-right: 1.5rem;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($documents)): ?>
                                <tr><td colspan="5" style="text-align: center; padding: 4rem; color: var(--text-muted);">Tidak ada berkas ditemukan.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($documents as $d): ?>
                            <tr class="file-row">
                                <td style="padding: 1rem 1.5rem;">
                                    <div style="display: flex; align-items: center; gap: 1rem;">
                                        <div class="file-icon" style="background: <?php echo $d['category'] == 'invoice' ? '#e0f2fe' : '#fef3c7'; ?>; color: <?php echo $d['category'] == 'invoice' ? '#0369a1' : '#d97706'; ?>; width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                            <i class="fas <?php echo strpos($d['file_name'], '.pdf') !== false ? 'fa-file-pdf' : 'fa-file-image'; ?>"></i>
                                        </div>
                                        <div>
                                            <div style="font-weight: 600; font-size: 0.875rem;"><?php echo $d['file_name']; ?></div>
                                            <div style="font-size: 0.7rem; color: var(--text-muted);">V<?php echo $d['version']; ?> | Admin Logistik</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php 
                                        $isMasuk = in_array($d['category'], ['payment_proof', 'bast', 'manifest']);
                                        $isKeluar = in_array($d['category'], ['surat_jalan', 'resi', 'invoice']);
                                        $flowLabel = $isMasuk ? 'MASUK' : ($isKeluar ? 'KELUAR' : 'INTERNAL');
                                        $flowColor = $isMasuk ? 'var(--primary)' : ($isKeluar ? 'var(--accent)' : 'var(--text-muted)');
                                    ?>
                                    <div style="display: flex; flex-direction: column; gap: 4px;">
                                        <span style="font-size: 0.6rem; font-weight: 800; color: <?php echo $flowColor; ?>; letter-spacing: 0.05em;">[<?php echo $flowLabel; ?>]</span>
                                        <span style="font-size: 0.75rem; text-transform: uppercase; font-weight: 600; color: var(--text-muted);"><?php echo str_replace('_', ' ', $d['category']); ?></span>
                                    </div>
                                </td>
                                <td><span style="font-weight: 700; color: var(--primary); font-size: 0.875rem;"><?php echo $d['display_resi'] ?: '-'; ?></span></td>
                                <td style="font-size: 0.8125rem;"><?php echo date('d/m/Y', strtotime($d['created_at'])); ?></td>
                                <td style="text-align: right; padding-right: 1.5rem;">
                                    <div class="file-actions" style="justify-content: flex-end;">
                                        <?php if ($isTrash): ?>
                                            <a href="explorer.php?restore=<?php echo $d['id']; ?>" class="action-btn" title="Pulihkan"><i class="fas fa-undo"></i></a>
                                            <button type="button" class="action-btn" title="Hapus Permanen" style="color: #ef4444;"><i class="fas fa-trash"></i></button>
                                        <?php else: ?>
                                            <button type="button" onclick="openPreview('<?php echo addslashes($d['file_path']); ?>', '<?php echo addslashes($d['file_name']); ?>')" class="action-btn" title="Preview" style="color: var(--primary);"><i class="fas fa-eye"></i></button>
                                            <a href="<?php echo $d['file_path']; ?>" download class="action-btn" title="Download"><i class="fas fa-download"></i></a>
                                            <a href="explorer.php?delete=<?php echo $d['id']; ?>" class="action-btn" title="Hapus" style="color: #ef4444;"><i class="fas fa-trash"></i></a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</div>

<style>
    .tree-link {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.75rem 1rem;
        border-radius: 8px;
        color: var(--text-main);
        text-decoration: none;
        font-size: 0.875rem;
        font-weight: 500;
        transition: all 0.2s;
        justify-content: space-between;
    }
    .tree-link:hover { background: #f1f5f9; }
    .tree-link.active { background: rgba(30, 58, 138, 0.05); color: var(--primary); font-weight: 700; }
    .count-badge { font-size: 0.7rem; background: #f1f5f9; padding: 2px 8px; border-radius: 999px; color: var(--text-muted); }
    .file-actions { opacity: 1 !important; } /* Force visible for now */

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
        document.body.style.overflow = 'hidden'; // Prevent scroll

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
        document.body.style.overflow = ''; // Restore scroll
    }

    // Close on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === "Escape") closePreview();
    });
</script>
