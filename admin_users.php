<?php
require_once 'config/database.php';
require_once 'config/auth.php';

if (!hasRole('super_admin') && !hasRole('admin')) {
    header('Location: dashboard');
    exit;
}

$message = '';
$messageType = '';

if (isset($_GET['delete']) && hasRole('super_admin')) {
    $id = (int)$_GET['delete'];
    if ($id != $_SESSION['user_id']) {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role != 'super_admin'");
        $stmt->execute([$id]);
        logAction($_SESSION['user_id'], "Delete user ID: $id");
        $message = 'User berhasil dihapus!';
        $messageType = 'success';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $username = trim($_POST['username'] ?? '');
    $fullname = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? 'user';
    $password = $_POST['password'] ?? '';
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $form_access = $_POST['form_access'] ?? [];
    
    if (!hasRole('super_admin') && $role === 'super_admin') {
        $role = 'admin';
    }
    
    if ($id > 0) {
        $sql = "UPDATE users SET fullname = ?, email = ?, role = ?, is_active = ? WHERE id = ?";
        $params = [$fullname, $email, $role, $is_active, $id];
        if (!empty($password)) {
            $sql = "UPDATE users SET fullname = ?, email = ?, password = ?, role = ?, is_active = ? WHERE id = ?";
            $params = [$fullname, $email, password_hash($password, PASSWORD_DEFAULT), $role, $is_active, $id];
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        logAction($_SESSION['user_id'], "Update user ID: $id");
        $message = 'User berhasil diupdate!';
        $messageType = 'success';
    } else {
        $stmt = $pdo->prepare("INSERT INTO users (username, password, fullname, email, role, is_active) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $fullname, $email, $role, $is_active]);
        $id = $pdo->lastInsertId();
        logAction($_SESSION['user_id'], "Add user: $username");
        $message = 'User berhasil ditambahkan!';
        $messageType = 'success';
    }
    
    if ($id > 0) {
        $stmt = $pdo->prepare("DELETE FROM user_form_access WHERE user_id = ?");
        $stmt->execute([$id]);
        foreach ($form_access as $form_id) {
            $stmt = $pdo->prepare("INSERT INTO user_form_access (user_id, form_id) VALUES (?, ?)");
            $stmt->execute([$id, $form_id]);
        }
    }
}

$users = $pdo->query("SELECT * FROM users ORDER BY role, username")->fetchAll(PDO::FETCH_ASSOC);
$forms = $pdo->query("SELECT * FROM forms ORDER BY form_name")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen User - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .user-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .user-table th { text-align: left; padding: 8px 10px; background: #F8F9FC; color: #6B7A99; font-weight: 700; font-size: 11px; text-transform: uppercase; border-bottom: 2px solid #DDE3F0; }
        .user-table td { padding: 8px 10px; border-bottom: 1px solid #EEF0F6; vertical-align: middle; }
        .role-badge { display: inline-block; padding: 2px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .role-badge.super_admin { background: #FFD700; color: #1A1A2E; }
        .role-badge.admin { background: #2D5BE3; color: #fff; }
        .role-badge.user { background: #6B7A99; color: #fff; }
        .btn-sm { padding: 4px 12px; font-size: 11px; border-radius: 4px; border: none; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-warning { background: #F5A623; color: #fff; }
        .btn-warning:hover { background: #d4911a; }
        .btn-danger { background: #C62828; color: #fff; }
        .btn-danger:hover { background: #8E0000; }
        .btn-primary { background: #E53935; color: #fff; }
        .btn-primary:hover { background: #c62828; }
        .btn-secondary { background: #6B7A99; color: #fff; }
        .btn-secondary:hover { background: #5a6a89; }
        .form-group { margin-bottom: 12px; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 3px; color: #1A1A2E; font-size: 13px; }
        .form-group input, .form-group select { width: 100%; padding: 8px 12px; border: 1.5px solid #DDE3F0; border-radius: 6px; font-size: 14px; font-family: inherit; }
        .form-group input:focus, .form-group select:focus { border-color: #E53935; outline: none; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .form-check { display: flex; gap: 10px; flex-wrap: wrap; }
        .form-check label { display: flex; align-items: center; gap: 4px; font-size: 12px; font-weight: 400; cursor: pointer; }
        .form-check input[type="checkbox"] { width: 16px; height: 16px; cursor: pointer; }
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.45); z-index: 9998; align-items: center; justify-content: center; }
        .modal-overlay.show { display: flex; }
        .modal-box { background: #fff; border-radius: 14px; padding: 24px; width: 100%; max-width: 550px; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
        .modal-box h3 { font-size: 16px; margin-bottom: 6px; color: #1A1A2E; }
        .modal-box .close-modal { float: right; background: none; border: none; font-size: 20px; cursor: pointer; color: #6B7A99; }
        .modal-box .close-modal:hover { color: #1A1A2E; }
        .alert { padding: 10px 14px; border-radius: 8px; margin-bottom: 14px; }
        .alert-success { background: #EEFAF4; color: #1A7A45; border: 1px solid #B6E8CF; }
        .btn-row { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 10px; }
        .req { color: #E53935; }
        .card-header-flex { display: flex; justify-content: space-between; align-items: center; padding: 14px 20px; border-bottom: 1px solid #DDE3F0; }
        .card-header-flex h3 { font-size: 15px; font-weight: 700; color: #1A1A2E; margin: 0; }
    </style>
</head>
<body>
<?php include "sidebar.php"; ?>
    <div class="app">

        <main class="main">
            <header class="topbar">
                <div class="topbar-left"><h2>👥 Manajemen User</h2></div>
                    <button class="hamburger" onclick="toggleSidebar()">☰</button>
                <div class="topbar-right">
                    <span class="user-badge">👤 <?= htmlspecialchars($_SESSION['fullname']) ?> <span class="role-badge <?= $_SESSION['role'] ?>"><?= strtoupper($_SESSION['role']) ?></span></span>
                </div>
            </header>

            <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header-flex">
                    <h3>📋 Daftar User</h3>
                    <button class="btn btn-primary btn-sm" onclick="openModal()">+ Tambah User</button>
                </div>
                <div class="card-body">
                    <table class="user-table">
                        <thead>
                            <tr><th>Username</th><th>Nama</th><th>Email</th><th>Role</th><th>Status</th><th>Aksi</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($users as $u): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($u['username']) ?></strong></td>
                            <td><?= htmlspecialchars($u['fullname']) ?></td>
                            <td><?= htmlspecialchars($u['email']) ?></td>
                            <td><span class="role-badge <?= $u['role'] ?>"><?= strtoupper(str_replace('_', ' ', $u['role'])) ?></span></td>
                            <td><?= $u['is_active'] ? '✅ Aktif' : '⛔ Nonaktif' ?></td>
                            <td>
                                <button class="btn btn-sm btn-warning" onclick="editUser(<?= $u['id'] ?>)">✏️ Edit</button>
                                <?php if (hasRole('super_admin') && $u['id'] != $_SESSION['user_id'] && $u['role'] != 'super_admin'): ?>
                                <a href="?delete=<?= $u['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Hapus user ini?')">🗑 Hapus</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
    <!-- FOOTER -->
    <footer style="text-align:center;padding:20px 0 10px;border-top:1px solid #DDE3F0;margin-top:20px;font-size:11px;color:#6B7A99;">
        E-Form System v1.0 &copy; 2026 PT. Nura Kreasi Digital
    </footer>
        </main>
    </div>

    <div class="modal-overlay" id="userModal">
        <div class="modal-box">
            <button class="close-modal" onclick="closeModal()">✕</button>
            <h3 id="modalTitle">Tambah User</h3>
            <form method="POST" id="userForm">
                <input type="hidden" name="id" id="userId" value="0">
                
                <div class="form-group">
                    <label>Username <span class="req">*</span></label>
                    <input type="text" name="username" id="username" placeholder="Username" required>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label>Password <span class="req" id="passReq">*</span></label>
                        <input type="password" name="password" id="password" placeholder="Password">
                    </div>
                    <div class="form-group">
                        <label>Role <span class="req">*</span></label>
                        <select name="role" id="role">
                            <?php if (hasRole('super_admin')): ?>
                            <option value="super_admin">Super Admin</option>
                            <?php endif; ?>
                            <option value="admin">Admin</option>
                            <option value="user" selected>User</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Nama Lengkap <span class="req">*</span></label>
                    <input type="text" name="fullname" id="fullname" placeholder="Nama lengkap" required>
                </div>
                <div class="form-group">
                    <label>Email <span class="req">*</span></label>
                    <input type="email" name="email" id="email" placeholder="Email" required>
                </div>
                <div class="form-group">
                    <label>Akses Form</label>
                    <div class="form-check" id="formAccessContainer">
                        <?php foreach ($forms as $f): ?>
                        <label>
                            <input type="checkbox" name="form_access[]" value="<?= $f['id'] ?>">
                            <?= $f['icon'] ?> <?= htmlspecialchars($f['form_name']) ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="form-group">
                    <label style="display:flex;align-items:center;gap:8px;">
                        <input type="checkbox" name="is_active" value="1" checked> Aktif
                    </label>
                </div>
                <div class="btn-row">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Batal</button>
                    <button type="submit" class="btn btn-primary">💾 Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function openModal() {
        document.getElementById('modalTitle').textContent = 'Tambah User';
        document.getElementById('userId').value = 0;
        document.getElementById('username').value = '';
        document.getElementById('password').value = '';
        document.getElementById('password').required = true;
        document.getElementById('passReq').textContent = '*';
        document.getElementById('fullname').value = '';
        document.getElementById('email').value = '';
        document.getElementById('role').value = 'user';
        document.querySelectorAll('#formAccessContainer input[type="checkbox"]').forEach(function(cb) { cb.checked = false; });
        document.querySelector('input[name="is_active"]').checked = true;
        document.getElementById('userModal').classList.add('show');
    }

    function editUser(id) {
        console.log('Edit user ID:', id);
        
        fetch('get_user_data.php?user_id=' + id)
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('HTTP error ' + response.status);
                }
                return response.json();
            })
            .then(function(data) {
                console.log('Data received:', data);
                
                if (data && data.id) {
                    document.getElementById('modalTitle').textContent = 'Edit User';
                    document.getElementById('userId').value = data.id;
                    document.getElementById('username').value = data.username || '';
                    document.getElementById('fullname').value = data.fullname || '';
                    document.getElementById('email').value = data.email || '';
                    document.getElementById('role').value = data.role || 'user';
                    document.getElementById('password').value = '';
                    document.getElementById('password').required = false;
                    document.getElementById('passReq').textContent = '(kosongkan jika tidak diubah)';
                    document.querySelector('input[name="is_active"]').checked = data.is_active == 1;
                    
                    // Reset form access
                    document.querySelectorAll('#formAccessContainer input[type="checkbox"]').forEach(function(cb) {
                        cb.checked = false;
                    });
                    
                    // Set form access
                    if (data.form_access && data.form_access.length > 0) {
                        data.form_access.forEach(function(formId) {
                            document.querySelectorAll('#formAccessContainer input[type="checkbox"]').forEach(function(cb) {
                                if (parseInt(cb.value) === formId) {
                                    cb.checked = true;
                                }
                            });
                        });
                    }
                    
                    document.getElementById('userModal').classList.add('show');
                } else {
                    alert('Gagal memuat data user: Data tidak valid');
                }
            })
            .catch(function(error) {
                console.error('Error:', error);
                alert('Gagal memuat data user: ' + error.message + '. Coba refresh halaman.');
            });
    }

    function closeModal() {
        document.getElementById('userModal').classList.remove('show');
    }

    document.querySelectorAll('.modal-overlay').forEach(function(el) {
        el.addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });
    });
    </script>
<script src="assets/js/main.js"></script>
</body>
</html>
