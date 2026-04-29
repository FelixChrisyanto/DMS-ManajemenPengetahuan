<?php 
require_once 'includes/db.php';

$message = "";

// Handle Delete
if (isset($_GET['delete'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM routes WHERE id = ?");
        $stmt->execute([$_GET['delete']]);
        header("Location: routes.php?msg=deleted");
        exit;
    } catch (Exception $e) {
        $message = "Gagal menghapus: Rute mungkin sedang digunakan dalam jadwal kereta.";
    }
}

// Handle POST actions (Create & Edit)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['origin'])) {
    try {
        if (isset($_POST['route_id']) && !empty($_POST['route_id'])) {
            // Edit
            $stmt = $pdo->prepare("UPDATE routes SET origin = ?, destination = ?, price_per_kg = ? WHERE id = ?");
            $stmt->execute([$_POST['origin'], $_POST['destination'], $_POST['price'], $_POST['route_id']]);
            $message = "Rute berhasil diperbarui!";
        } else {
            // Create
            $stmt = $pdo->prepare("INSERT INTO routes (origin, destination, price_per_kg) VALUES (?, ?, ?)");
            $stmt->execute([$_POST['origin'], $_POST['destination'], $_POST['price']]);
            $message = "Rute berhasil ditambahkan!";
        }
    } catch (Exception $e) {
        $message = "Gagal: " . $e->getMessage();
    }
}

$routes = $pdo->query("SELECT * FROM routes ORDER BY origin")->fetchAll();

include 'includes/head.php'; 
include 'includes/sidebar.php'; 
?>

<div class="content-wrapper">
    <?php include 'includes/header.php'; ?>

    <div class="container">
        <div style="margin-bottom: 2rem; display: flex; align-items: center; justify-content: space-between;">
            <div>
                <h1 style="font-size: 1.75rem; font-weight: 800; color: var(--primary);">Master Data Rute & Tarif</h1>
                <p style="color: var(--text-muted); margin-top: 0.25rem;">Pengaturan tarif pengiriman berdasarkan berat (KG) dan tujuan.</p>
            </div>
            <button class="btn btn-primary" onclick="openAddModal()">
                <i class="fas fa-plus"></i> Tambah Rute Baru
            </button>
        </div>

        <?php if($message): ?>
            <div style="padding: 1rem; background: <?php echo strpos($message, 'Gagal') !== false ? '#fee2e2' : '#dcfce7'; ?>; 
                        color: <?php echo strpos($message, 'Gagal') !== false ? '#991b1b' : '#166534'; ?>; 
                        border-radius: 8px; margin-bottom: 1.5rem; border: 1px solid <?php echo strpos($message, 'Gagal') !== false ? '#fecaca' : '#bbf7d0'; ?>;">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if(isset($_GET['msg']) && $_GET['msg'] == 'deleted'): ?>
            <div style="padding: 1rem; background: #dcfce7; color: #166534; border-radius: 8px; margin-bottom: 1.5rem; border: 1px solid #bbf7d0;">
                Rute berhasil dihapus!
            </div>
        <?php endif; ?>

        <div class="card" style="padding: 0; overflow: hidden;">
            <table class="file-table" style="width: 100%;">
                <thead style="background: #f8fafc;">
                    <tr>
                        <th style="padding: 1rem 1.5rem;">Kota Asal</th>
                        <th>Kota Tujuan</th>
                        <th>Tarif Dasar (Per KG)</th>
                        <th>Estimasi</th>
                        <th style="text-align: right; padding-right: 1.5rem;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($routes)): ?>
                        <tr><td colspan="5" style="text-align: center; padding: 2rem; color: var(--text-muted);">Belum ada rute tersedia.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($routes as $r): ?>
                    <tr class="file-row">
                        <td style="padding: 1.25rem 1.5rem; font-weight: 600;"><?php echo $r['origin']; ?></td>
                        <td><?php echo $r['destination']; ?></td>
                        <td><span style="font-weight: 700; color: var(--success);">Rp <?php echo number_format($r['price_per_kg']); ?></span></td>
                        <td>1-2 Hari</td>
                        <td style="text-align: right; padding-right: 1.5rem;">
                            <div class="file-actions" style="justify-content: flex-end;">
                                <button class="action-btn" onclick='openEditModal(<?php echo json_encode($r); ?>)' title="Edit"><i class="fas fa-pen"></i></button>
                                <a href="routes.php?delete=<?php echo $r['id']; ?>" class="action-btn" style="color: #ef4444;" title="Hapus" onclick="return confirm('Apakah Anda yakin ingin menghapus rute ini?')"><i class="fas fa-trash"></i></a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Add/Edit Route -->
<div id="modalRoute" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center;">
    <div class="card" style="width: 400px; padding: 2rem;">
        <h3 id="modalTitle" style="margin-bottom: 1.5rem;">Tambah Rute Baru</h3>
        <form method="POST">
            <input type="hidden" name="route_id" id="route_id">
            <div style="margin-bottom: 1rem;">
                <label style="display: block; font-size: 0.875rem; margin-bottom: 0.5rem; font-weight: 600;">Kota Asal</label>
                <input type="text" name="origin" id="origin" required style="width: 100%; padding: 0.6rem; border: 1px solid var(--border-color); border-radius: 6px;">
            </div>
            <div style="margin-bottom: 1rem;">
                <label style="display: block; font-size: 0.875rem; margin-bottom: 0.5rem; font-weight: 600;">Kota Tujuan</label>
                <input type="text" name="destination" id="destination" required style="width: 100%; padding: 0.6rem; border: 1px solid var(--border-color); border-radius: 6px;">
            </div>
            <div style="margin-bottom: 1.5rem;">
                <label style="display: block; font-size: 0.875rem; margin-bottom: 0.5rem; font-weight: 600;">Tarif Per KG (Rp)</label>
                <input type="number" name="price" id="price" required style="width: 100%; padding: 0.6rem; border: 1px solid var(--border-color); border-radius: 6px;">
            </div>
            <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                <button type="button" class="btn btn-outline" onclick="closeModal()">Batal</button>
                <button type="submit" class="btn btn-primary" id="btnSubmit">Simpan Rute</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openAddModal() {
        document.getElementById('modalTitle').innerText = 'Tambah Rute Baru';
        document.getElementById('route_id').value = '';
        document.getElementById('origin').value = '';
        document.getElementById('destination').value = '';
        document.getElementById('price').value = '';
        document.getElementById('btnSubmit').innerText = 'Simpan Rute';
        document.getElementById('modalRoute').style.display = 'flex';
    }

    function openEditModal(data) {
        document.getElementById('modalTitle').innerText = 'Edit Rute & Tarif';
        document.getElementById('route_id').value = data.id;
        document.getElementById('origin').value = data.origin;
        document.getElementById('destination').value = data.destination;
        document.getElementById('price').value = data.price_per_kg;
        document.getElementById('btnSubmit').innerText = 'Update Rute';
        document.getElementById('modalRoute').style.display = 'flex';
    }

    function closeModal() {
        document.getElementById('modalRoute').style.display = 'none';
    }
</script>

<?php include 'includes/footer.php'; ?>
