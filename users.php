<?php 
require_once 'includes/db.php';

$message = "";

// Handle Delete
if (isset($_GET['delete'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$_GET['delete']]);
        header("Location: users.php?msg=deleted");
        exit;
    } catch (Exception $e) {
        $message = "Gagal menghapus: User mungkin memiliki riwayat aktivitas di sistem.";
    }
}

// Handle POST actions (Create & Edit)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['username'])) {
    try {
        if (isset($_POST['user_id']) && !empty($_POST['user_id'])) {
            // EDIT ACTION
            if (!empty($_POST['password'])) {
                $stmt = $pdo->prepare("UPDATE users SET username = ?, password = ?, name = ?, role = ? WHERE id = ?");
                $stmt->execute([
                    $_POST['username'], 
                    password_hash($_POST['password'], PASSWORD_DEFAULT), 
                    $_POST['name'], 
                    $_POST['role'],
                    $_POST['user_id']
                ]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET username = ?, name = ?, role = ? WHERE id = ?");
                $stmt->execute([
                    $_POST['username'], 
                    $_POST['name'], 
                    $_POST['role'],
                    $_POST['user_id']
                ]);
            }
            $message = "Data pengguna berhasil diperbarui!";
        } else {
            // CREATE ACTION
            $stmt = $pdo->prepare("INSERT INTO users (username, password, name, role) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $_POST['username'], 
                password_hash($_POST['password'], PASSWORD_DEFAULT), 
                $_POST['name'], 
                $_POST['role']
            ]);
            $message = "User baru berhasil ditambahkan!";
        }
    } catch (Exception $e) {
        $message = "Gagal: " . $e->getMessage();
    }
}

$users = $pdo->query("SELECT * FROM users ORDER BY role, name")->fetchAll();

include 'includes/head.php'; 
include 'includes/sidebar.php'; 
?>

<div class="content-wrapper">
    <?php include 'includes/header.php'; ?>

    <div class="container">
        <div style="margin-bottom: 2rem; display: flex; align-items: center; justify-content: space-between;">
            <div>
                <h1 style="font-size: 1.75rem; font-weight: 800; color: var(--primary);">Manajemen Pengguna</h1>
                <p style="color: var(--text-muted); margin-top: 0.25rem;">Pengaturan hak akses dan peran (Admin, Staff, Finance).</p>
            </div>
            <button class="btn btn-primary" onclick="openAddModal()">
                <i class="fas fa-user-plus"></i> Tambah User Baru
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
                Pengguna berhasil dihapus!
            </div>
        <?php endif; ?>

        <div class="card" style="padding: 0; overflow: hidden;">
            <table class="file-table" style="width: 100%;">
                <thead style="background: #f8fafc;">
                    <tr>
                        <th style="padding: 1rem 1.5rem;">Nama Lengkap</th>
                        <th>Username</th>
                        <th>Peran (Role)</th>
                        <th>Terdaftar</th>
                        <th style="text-align: right; padding-right: 1.5rem;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr class="file-row">
                        <td style="padding: 1.25rem 1.5rem;">
                            <div style="display: flex; align-items: center; gap: 0.75rem;">
                                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($u['name']); ?>&background=random" style="width: 32px; height: 32px; border-radius: 50%;">
                                <div style="font-weight: 600;"><?php echo $u['name']; ?></div>
                            </div>
                        </td>
                        <td><?php echo $u['username']; ?></td>
                        <td>
                            <span class="status-badge" style="background: <?php echo $u['role'] == 'admin' ? '#fee2e2' : ($u['role'] == 'finance' ? '#e0f2fe' : '#f1f5f9'); ?>; 
                                                            color: <?php echo $u['role'] == 'admin' ? '#991b1b' : ($u['role'] == 'finance' ? '#0369a1' : '#475569'); ?>;">
                                <?php echo strtoupper($u['role']); ?>
                            </span>
                        </td>
                        <td style="font-size: 0.8125rem; color: var(--text-muted);"><?php echo date('d M Y', strtotime($u['created_at'])); ?></td>
                        <td style="text-align: right; padding-right: 1.5rem;">
                            <div class="file-actions" style="justify-content: flex-end;">
                                <button class="action-btn" title="Edit" onclick='openEditModal(<?php echo json_encode(["id"=>$u['id'], "name"=>$u['name'], "username"=>$u['username'], "role"=>$u['role']]); ?>)'><i class="fas fa-pen"></i></button>
                                <a href="users.php?delete=<?php echo $u['id']; ?>" class="action-btn" style="color: #ef4444;" title="Hapus" onclick="return confirm('Apakah Anda yakin ingin menghapus pengguna ini?')"><i class="fas fa-trash"></i></a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Add/Edit User -->
<div id="modalUser" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center;">
    <div class="card" style="width: 450px; padding: 2.5rem;">
        <h3 id="modalTitle" style="margin-bottom: 1.5rem;">Tambah Pengguna Baru</h3>
        <form method="POST">
            <input type="hidden" name="user_id" id="user_id">
            <div style="margin-bottom: 1rem;">
                <label style="display: block; font-size: 0.875rem; margin-bottom: 0.5rem; font-weight: 600;">Nama Lengkap</label>
                <input type="text" name="name" id="name" required style="width: 100%; padding: 0.7rem; border: 1px solid var(--border-color); border-radius: 8px;">
            </div>
            <div style="margin-bottom: 1rem;">
                <label style="display: block; font-size: 0.875rem; margin-bottom: 0.5rem; font-weight: 600;">Username</label>
                <input type="text" name="username" id="username" required style="width: 100%; padding: 0.7rem; border: 1px solid var(--border-color); border-radius: 8px;">
            </div>
            <div style="margin-bottom: 1rem;">
                <label style="display: block; font-size: 0.875rem; margin-bottom: 0.5rem; font-weight: 600;">Password</label>
                <input type="password" name="password" id="password" placeholder="Kosongkan jika tidak ingin mengubah" style="width: 100%; padding: 0.7rem; border: 1px solid var(--border-color); border-radius: 8px;">
                <small id="passwordNote" style="font-size: 0.7rem; color: var(--text-muted); display: none;">* Kosongkan jika tidak me-reset password.</small>
            </div>
            <div style="margin-bottom: 2rem;">
                <label style="display: block; font-size: 0.875rem; margin-bottom: 0.5rem; font-weight: 600;">Peran (Role)</label>
                <select name="role" id="role" required style="width: 100%; padding: 0.7rem; border: 1px solid var(--border-color); border-radius: 8px; background: white;">
                    <option value="staff">Staff Operasional</option>
                    <option value="finance">Bagian Keuangan</option>
                    <option value="admin">Administrator</option>
                </select>
            </div>
            <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                <button type="button" class="btn btn-outline" onclick="closeModal()">Batal</button>
                <button type="submit" class="btn btn-primary" id="btnSubmit">Simpan User</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openAddModal() {
        document.getElementById('modalTitle').innerText = 'Tambah Pengguna Baru';
        document.getElementById('user_id').value = '';
        document.getElementById('name').value = '';
        document.getElementById('username').value = '';
        document.getElementById('password').value = '';
        document.getElementById('password').required = true;
        document.getElementById('passwordNote').style.display = 'none';
        document.getElementById('btnSubmit').innerText = 'Simpan User';
        document.getElementById('modalUser').style.display = 'flex';
    }

    function openEditModal(data) {
        document.getElementById('modalTitle').innerText = 'Edit Data Pengguna';
        document.getElementById('user_id').value = data.id;
        document.getElementById('name').value = data.name;
        document.getElementById('username').value = data.username;
        document.getElementById('role').value = data.role;
        document.getElementById('password').value = '';
        document.getElementById('password').required = false;
        document.getElementById('passwordNote').style.display = 'block';
        document.getElementById('btnSubmit').innerText = 'Update User';
        document.getElementById('modalUser').style.display = 'flex';
    }

    function closeModal() {
        document.getElementById('modalUser').style.display = 'none';
    }
</script>

<?php include 'includes/footer.php'; ?>
