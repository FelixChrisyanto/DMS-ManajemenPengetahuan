<?php 
require_once 'includes/db.php';

$message = "";

// Handle Delete
if (isset($_GET['delete'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM train_schedules WHERE id = ?");
        $stmt->execute([$_GET['delete']]);
        header("Location: schedules.php?msg=deleted");
        exit;
    } catch (Exception $e) {
        $message = "Gagal menghapus: Jadwal mungkin sudah terkait dengan data pengiriman.";
    }
}

// Handle POST actions (Create & Edit)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['train_name'])) {
    try {
        if (isset($_POST['schedule_id']) && !empty($_POST['schedule_id'])) {
            // Edit
            $stmt = $pdo->prepare("UPDATE train_schedules SET train_name = ?, route_id = ?, departure_time = ?, arrival_time = ?, capacity_kg = ?, status = ? WHERE id = ?");
            $stmt->execute([$_POST['train_name'], $_POST['route_id'], $_POST['departure'], $_POST['arrival'], $_POST['capacity'], $_POST['status'], $_POST['schedule_id']]);
            $message = "Jadwal Kereta berhasil diperbarui!";
        } else {
            // Create
            $stmt = $pdo->prepare("INSERT INTO train_schedules (train_name, route_id, departure_time, arrival_time, capacity_kg) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$_POST['train_name'], $_POST['route_id'], $_POST['departure'], $_POST['arrival'], $_POST['capacity']]);
            $message = "Jadwal Kereta berhasil ditambahkan!";
        }
    } catch (Exception $e) {
        $message = "Gagal: " . $e->getMessage();
    }
}

include 'includes/head.php'; 
include 'includes/sidebar.php'; 

$schedules = $pdo->query("SELECT ts.*, r.origin, r.destination,
                             COALESCE(SUM(CASE WHEN LOWER(TRIM(s.status)) != 'arrived' AND s.deleted_at IS NULL THEN s.weight_kg ELSE 0 END), 0) as used_kg 
                         FROM train_schedules ts 
                         JOIN routes r ON ts.route_id = r.id 
                         LEFT JOIN shipments s ON ts.id = s.train_id 
                         GROUP BY ts.id 
                         ORDER BY ts.departure_time")->fetchAll();
$routes = $pdo->query("SELECT * FROM routes")->fetchAll();
?>

<div class="content-wrapper">
    <?php include 'includes/header.php'; ?>

    <div class="container">
        <div style="margin-bottom: 2rem; display: flex; align-items: center; justify-content: space-between;">
            <div>
                <h1 style="font-size: 1.75rem; font-weight: 800; color: var(--primary);">Jadwal Operasional Kereta</h1>
                <p style="color: var(--text-muted); margin-top: 0.25rem;">Manajemen keberangkatan dan kapasitas angkut logistik.</p>
            </div>
            <button class="btn btn-primary" onclick="openAddModal()">
                <i class="fas fa-plus"></i> Tambah Jadwal Baru
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
                Jadwal berhasil dihapus!
            </div>
        <?php endif; ?>

        <div class="card" style="padding: 0; overflow: hidden;">
            <table class="file-table" style="width: 100%;">
                <thead style="background: #f8fafc;">
                    <tr>
                        <th style="padding: 1rem 1.5rem;">Nama Kereta</th>
                        <th>Rute</th>
                        <th>Berangkat</th>
                        <th>Tiba</th>
                        <th>Kapasitas</th>
                        <th>Status</th>
                        <th style="text-align: right; padding-right: 1.5rem;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($schedules)): ?>
                        <tr><td colspan="7" style="text-align: center; padding: 2rem; color: var(--text-muted);">Belum ada jadwal tersedia.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($schedules as $s): ?>
                    <tr class="file-row">
                        <td style="padding: 1.25rem 1.5rem;">
                            <div style="font-weight: 700; color: var(--primary);"><?php echo $s['train_name']; ?></div>
                            <div style="font-size: 0.7rem; color: var(--text-muted);">KA Logistik</div>
                        </td>
                        <td>
                            <div style="font-size: 0.875rem; font-weight: 500;"><?php echo $s['origin']; ?> <i class="fas fa-arrow-right" style="font-size: 0.7rem; margin: 0 0.25rem;"></i> <?php echo $s['destination']; ?></div>
                        </td>
                        <td><?php echo date('H:i', strtotime($s['departure_time'])); ?></td>
                        <td><?php echo date('H:i', strtotime($s['arrival_time'])); ?></td>
                        <td>
                            <div style="font-size: 0.875rem; font-weight: 700;"><?php echo number_format($s['capacity_kg']); ?> KG</div>
                            <div style="font-size: 0.7rem; color: var(--text-muted);">Terisi: <?php echo number_format($s['used_kg']); ?> KG</div>
                            <div style="width: 100%; height: 6px; background: #f1f5f9; border-radius: 999px; margin-top: 6px; overflow: hidden; border: 1px solid #e2e8f0;">
                                <?php if ($s['used_kg'] > 0): ?>
                                    <div style="width: <?php echo max(2, ($s['used_kg'] / $s['capacity_kg']) * 100); ?>%; height: 100%; background: <?php echo ($s['used_kg'] >= $s['capacity_kg']) ? '#ef4444' : 'var(--primary)'; ?>; border-radius: 999px;"></div>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <?php 
                                // Real-time status calculation for display
                                $isFull = ($s['used_kg'] >= $s['capacity_kg']);
                                $statusLabel = $isFull ? 'FULL' : 'AVAILABLE';
                                $statusBg = $isFull ? 'rgba(239, 68, 68, 0.1)' : 'rgba(16, 185, 129, 0.1)';
                                $statusColor = $isFull ? '#991b1b' : '#166534';
                            ?>
                            <span class="status-badge" style="background: <?php echo $statusBg; ?>; color: <?php echo $statusColor; ?>;">
                                <?php echo $statusLabel; ?>
                            </span>
                        </td>
                        <td style="text-align: right; padding-right: 1.5rem;">
                            <div class="file-actions" style="justify-content: flex-end;">
                                <button class="action-btn" onclick='openEditModal(<?php echo json_encode($s); ?>)' title="Edit"><i class="fas fa-pen"></i></button>
                                <a href="schedules.php?delete=<?php echo $s['id']; ?>" class="action-btn" style="color: #ef4444;" title="Hapus" onclick="return confirm('Apakah Anda yakin ingin menghapus jadwal ini?')"><i class="fas fa-trash"></i></a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Add/Edit Schedule -->
<div id="modalSchedule" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center;">
    <div class="card" style="width: 500px; padding: 2rem;">
        <h3 id="modalTitle" style="margin-bottom: 1.5rem;">Tambah Jadwal Kereta</h3>
        <form method="POST">
            <input type="hidden" name="schedule_id" id="schedule_id">
            <div style="margin-bottom: 1rem;">
                <label style="display: block; font-size: 0.875rem; margin-bottom: 0.5rem; font-weight: 600;">Nama Kereta</label>
                <input type="text" name="train_name" id="train_name" placeholder="Contoh: KA BIMA LOGISTIK" required style="width: 100%; padding: 0.6rem; border: 1px solid var(--border-color); border-radius: 6px;">
            </div>
            <div style="margin-bottom: 1rem;">
                <label style="display: block; font-size: 0.875rem; margin-bottom: 0.5rem; font-weight: 600;">Pilih Rute</label>
                <select name="route_id" id="route_id" required style="width: 100%; padding: 0.6rem; border: 1px solid var(--border-color); border-radius: 6px; background: white;">
                    <?php foreach($routes as $r): ?>
                        <option value="<?php echo $r['id']; ?>"><?php echo $r['origin']; ?> - <?php echo $r['destination']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="grid grid-cols-2" style="gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label style="display: block; font-size: 0.875rem; margin-bottom: 0.5rem; font-weight: 600;">Waktu Berangkat</label>
                    <input type="time" name="departure" id="departure" required style="width: 100%; padding: 0.6rem; border: 1px solid var(--border-color); border-radius: 6px;">
                </div>
                <div>
                    <label style="display: block; font-size: 0.875rem; margin-bottom: 0.5rem; font-weight: 600;">Waktu Tiba</label>
                    <input type="time" name="arrival" id="arrival" required style="width: 100%; padding: 0.6rem; border: 1px solid var(--border-color); border-radius: 6px;">
                </div>
            </div>
            <div class="grid grid-cols-2" style="gap: 1rem; margin-bottom: 1.5rem;">
                <div>
                    <label style="display: block; font-size: 0.875rem; margin-bottom: 0.5rem; font-weight: 600;">Kapasitas (KG)</label>
                    <input type="number" name="capacity" id="capacity" placeholder="5000" required style="width: 100%; padding: 0.6rem; border: 1px solid var(--border-color); border-radius: 6px;">
                </div>
                <div>
                    <label style="display: block; font-size: 0.875rem; margin-bottom: 0.5rem; font-weight: 600;">Status</label>
                    <select name="status" id="status" required style="width: 100%; padding: 0.6rem; border: 1px solid var(--border-color); border-radius: 6px; background: white;">
                        <option value="available">AVAILABLE</option>
                        <option value="full">FULL</option>
                    </select>
                </div>
            </div>
            <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                <button type="button" class="btn btn-outline" onclick="closeModal()">Batal</button>
                <button type="submit" class="btn btn-primary" id="btnSubmit">Simpan Jadwal</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openAddModal() {
        document.getElementById('modalTitle').innerText = 'Tambah Jadwal Kereta';
        document.getElementById('schedule_id').value = '';
        document.getElementById('train_name').value = '';
        document.getElementById('departure').value = '';
        document.getElementById('arrival').value = '';
        document.getElementById('capacity').value = '';
        document.getElementById('status').value = 'available';
        document.getElementById('btnSubmit').innerText = 'Simpan Jadwal';
        document.getElementById('modalSchedule').style.display = 'flex';
    }

    function openEditModal(data) {
        document.getElementById('modalTitle').innerText = 'Edit Jadwal Kereta';
        document.getElementById('schedule_id').value = data.id;
        document.getElementById('train_name').value = data.train_name;
        document.getElementById('route_id').value = data.route_id;
        document.getElementById('departure').value = data.departure_time;
        document.getElementById('arrival').value = data.arrival_time;
        document.getElementById('capacity').value = data.capacity_kg;
        document.getElementById('status').value = data.status;
        document.getElementById('btnSubmit').innerText = 'Update Jadwal';
        document.getElementById('modalSchedule').style.display = 'flex';
    }

    function closeModal() {
        document.getElementById('modalSchedule').style.display = 'none';
    }
</script>

<?php include 'includes/footer.php'; ?>
