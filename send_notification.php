<?php
require_once 'config/database.php';
require_once 'config/auth.php';
requireAdmin();

$message = '';
$messageType = '';

$users = $pdo->query("SELECT id, username, fullname, email, role FROM users WHERE is_active = 1 ORDER BY role, username")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_notification'])) {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $type = $_POST['type'] ?? 'info';
    $link = trim($_POST['link'] ?? '');
    $target = $_POST['target'] ?? 'all';
    $selected_users = $_POST['selected_users'] ?? [];

    if (empty($title) || empty($content)) {
        $message = '⚠️ Judul dan pesan harus diisi!';
        $messageType = 'error';
    } else {
        $targetUsers = [];
        if ($target === 'all') {
            $targetUsers = $users;
        } elseif ($target === 'admin') {
            $targetUsers = array_filter($users, function($u) {
                return in_array($u['role'], ['super_admin', 'admin']);
            });
        } elseif ($target === 'user') {
            $targetUsers = array_filter($users, function($u) {
                return $u['role'] === 'user';
            });
        } elseif ($target === 'selected' && !empty($selected_users)) {
            $targetUsers = array_filter($users, function($u) use ($selected_users) {
                return in_array($u['id'], $selected_users);
            });
        }

        if (empty($targetUsers)) {
            $message = '⚠️ Tidak ada user yang dipilih!';
            $messageType = 'error';
        } else {
            $successCount = 0;
            foreach ($targetUsers as $user) {
                $stmt = $pdo->prepare("INSERT INTO notifications (user_id, sender_id, title, message, type, link, is_read, created_at) VALUES (?, ?, ?, ?, ?, ?, 0, NOW())");
                $stmt->execute([
                    $user['id'],
                    $_SESSION['user_id'],
                    $title,
                    $content,
                    $type,
                    $link
                ]);
                $successCount++;
            }

            logAction($_SESSION['user_id'], "Send notification: $title (Target: $target, Count: $successCount)");
            $message = "✅ Notifikasi berhasil dikirim ke <strong>{$successCount}</strong> user!";
            $messageType = 'success';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kirim Notifikasi - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .send-container { max-width: 650px; margin: 0 auto; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 4px; color: #1A1A2E; font-size: 13px; }
        .form-group input, .form-group textarea, .form-group select {
            width: 100%; padding: 8px 12px; border: 1.5px solid #DDE3F0; border-radius: 8px; font-size: 14px; font-family: inherit;
        }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus {
            border-color: #E53935; outline: none;
        }
        .form-group textarea { min-height: 100px; resize: vertical; }
        .alert { padding: 10px 14px; border-radius: 8px; margin-bottom: 14px; }
        .alert-success { background: #EEFAF4; color: #1A7A45; border: 1px solid #B6E8CF; }
        .alert-error { background: #FFE5E5; color: #C62828; border: 1px solid #FFB3B3; }
        .btn { display: inline-block; padding: 10px 24px; border-radius: 8px; font-weight: 700; cursor: pointer; border: none; font-size: 14px; transition: 0.2s; }
        .btn-primary { background: #E53935; color: #fff; }
        .btn-primary:hover { background: #c62828; }
        .btn-secondary { background: #6B7A99; color: #fff; }
        .btn-secondary:hover { background: #5a6a89; }
        .user-list { max-height: 200px; overflow-y: auto; border: 1px solid #DDE3F0; border-radius: 8px; padding: 8px 12px; background: #FAFBFD; }
        .user-list label { display: block; padding: 4px 0; font-size: 13px; cursor: pointer; }
        .user-list label:hover { background: #F0F2F8; }
        .user-list input[type="checkbox"] { margin-right: 8px; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .selected-info { font-size: 12px; color: #6B7A99; margin-top: 4px; }
        .role-badge { display: inline-block; padding: 2px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .role-badge.super_admin { background: #FFD700; color: #1A1A2E; }
        .role-badge.admin { background: #2D5BE3; color: #fff; }
        .role-badge.user { background: #6B7A99; color: #fff; }
        .preview-box { background: #F8F9FC; border: 1px solid #DDE3F0; border-radius: 8px; padding: 12px 14px; font-size: 13px; color: #1A1A2E; }
        .preview-box .title { font-weight: 700; font-size: 14px; }
        .preview-box .time { color: #6B7A99; font-size: 11px; }
    </style>
</head>
<body>
<?php include "sidebar.php"; ?>
    <div class="app">

        <main class="main">
            <div class="send-container">
                <header class="topbar">
                    <div class="topbar-left"><h2>📤 Kirim Notifikasi</h2></div>
                    <div class="topbar-right">
                        <span class="user-badge">👤 <?= htmlspecialchars($_SESSION['fullname']) ?> <span class="role-badge"><?= $_SESSION['role'] ?></span></span>
                    </div>
                </header>

                <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?>"><?= $message ?></div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header"><h3>📤 Kirim Notifikasi ke User</h3></div>
                    <div class="card-body">
                        <form method="POST" onsubmit="return confirm('Kirim notifikasi ini?')">
                            <div class="form-group">
                                <label>Target Penerima</label>
                                <select name="target" id="targetSelect" onchange="toggleUserList()" style="width:100%;padding:8px 12px;border:1.5px solid #DDE3F0;border-radius:8px;font-size:14px;">
                                    <option value="all">📬 Semua User</option>
                                    <option value="admin">👑 Admin & Super Admin</option>
                                    <option value="user">👤 User Biasa</option>
                                    <option value="selected">📋 Pilih Manual</option>
                                </select>
                            </div>

                            <div class="form-group" id="userListContainer" style="display:none;">
                                <label>Pilih User:</label>
                                <div class="user-list">
                                    <?php foreach ($users as $u): ?>
                                    <label>
                                        <input type="checkbox" name="selected_users[]" value="<?= $u['id'] ?>">
                                        <?= htmlspecialchars($u['fullname']) ?> (<?= htmlspecialchars($u['username']) ?>) - <?= strtoupper($u['role']) ?>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                                <div class="selected-info">💡 Centang user yang ingin dikirimi notifikasi.</div>
                            </div>

                            <div class="form-group">
                                <label>Jenis Notifikasi</label>
                                <select name="type" style="width:100%;padding:8px 12px;border:1.5px solid #DDE3F0;border-radius:8px;font-size:14px;">
                                    <option value="info">ℹ️ Informasi</option>
                                    <option value="success">✅ Sukses</option>
                                    <option value="warning">⚠️ Peringatan</option>
                                    <option value="submitted">📤 Submission</option>
                                    <option value="approved">✅ Disetujui</option>
                                    <option value="rejected">❌ Ditolak</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Judul Notifikasi <span style="color:#E53935;">*</span></label>
                                <input type="text" name="title" placeholder="Contoh: Surat Tugas Baru" required>
                            </div>

                            <div class="form-group">
                                <label>Pesan <span style="color:#E53935;">*</span></label>
                                <textarea name="content" placeholder="Tulis pesan notifikasi..." required></textarea>
                            </div>

                            <div class="form-group">
                                <label>Link (opsional, misal: form_st.php)</label>
                                <input type="text" name="link" placeholder="Contoh: form_st.php atau https://...">
                                <div style="font-size:11px;color:#6B7A99;margin-top:4px;">💡 User akan melihat tombol "Lihat Detail" jika diisi</div>
                            </div>

                            <button type="submit" name="send_notification" class="btn btn-primary">📤 Kirim Notifikasi</button>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h3>📋 Preview Notifikasi</h3></div>
                    <div class="card-body">
                        <div class="preview-box">
                            <div class="title">📌 Judul Notifikasi</div>
                            <div style="margin:4px 0;">Pesan notifikasi akan muncul di sini...</div>
                            <div class="time">🕐 Sekarang • Dari: <?= htmlspecialchars($_SESSION['fullname']) ?></div>
                            <div style="margin-top:6px;"><a href="#" style="color:#2D5BE3;text-decoration:none;">🔗 Lihat Detail →</a></div>
                        </div>
                        <div style="font-size:11px;color:#6B7A99;margin-top:6px;">💡 Preview akan sesuai dengan isian di atas</div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
    function toggleUserList() {
        var val = document.getElementById('targetSelect').value;
        var container = document.getElementById('userListContainer');
        container.style.display = val === 'selected' ? 'block' : 'none';
    }
    </script>
    <script src="assets/js/main.js"></script>
    <!-- FOOTER -->
    <footer style="text-align:center;padding:16px 0 8px;border-top:1px solid #DDE3F0;margin-top:20px;font-size:11px;color:#6B7A99;">
        E-Form System v1.0 &copy; 2026 PT. Nura Kreasi Digital
    </footer>
</body>
</html>
