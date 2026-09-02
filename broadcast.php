<?php
require_once 'config/database.php';
require_once 'config/auth.php';
requireAdmin();

require_once 'phpmailer/src/Exception.php';
require_once 'phpmailer/src/PHPMailer.php';
require_once 'phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$message = '';
$messageType = '';

$users = $pdo->query("SELECT id, username, fullname, email, role FROM users WHERE is_active = 1 ORDER BY role, username")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_broadcast'])) {
    $subject = trim($_POST['subject'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $target = $_POST['target'] ?? 'all';
    $selected_users = $_POST['selected_users'] ?? [];

    if (empty($subject) || empty($content)) {
        $message = '⚠️ Subject dan konten email harus diisi!';
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
            $smtpHost = 'smtp.gmail.com';
            $smtpUser = 'nurakreasidigital@gmail.com';
            $smtpPass = 'wuzm vmek kmcq sgkt';

            $successCount = 0;
            $failCount = 0;

            foreach ($targetUsers as $user) {
                try {
                    $mail = new PHPMailer(true);
                    $mail->isSMTP();
                    $mail->Host = $smtpHost;
                    $mail->SMTPAuth = true;
                    $mail->Username = $smtpUser;
                    $mail->Password = $smtpPass;
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = 587;
                    $mail->setFrom($smtpUser, 'E-Form System');
                    $mail->addAddress($user['email'], $user['fullname']);
                    $mail->isHTML(true);
                    $mail->Subject = $subject;
                    $mail->Body = "
                    <html>
                    <head><style>
                        body { font-family: Arial, sans-serif; color: #1A1A2E; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #DDE3F0; border-radius: 10px; }
                        .header { background: #1A1A2E; color: #fff; padding: 15px; border-radius: 10px 10px 0 0; text-align: center; }
                        .content { padding: 20px; }
                        .footer { text-align: center; color: #6B7A99; font-size: 11px; padding: 10px; border-top: 1px solid #DDE3F0; margin-top: 20px; }
                    </style></head>
                    <body>
                        <div class='container'>
                            <div class='header'><h2>📋 E-Form System</h2></div>
                            <div class='content'>
                                <p>Halo <strong>" . htmlspecialchars($user['fullname']) . "</strong>,</p>
                                <div style='padding:10px 14px;background:#F8F9FC;border-radius:8px;border-left:4px solid #E53935;'>
                                    " . nl2br(htmlspecialchars($content)) . "
                                </div>
                            </div>
                            <div class='footer'>
                                E-Form System v1.0 &copy; 2026 PT. Nura Kreasi Digital<br>
                                <small>Email ini dikirim otomatis oleh sistem.</small>
                            </div>
                        </div>
                    </body>
                    </html>
                    ";
                    $mail->AltBody = strip_tags($content);
                    $mail->send();
                    $successCount++;
                } catch (Exception $e) {
                    $failCount++;
                }
            }

            $message = "✅ Email berhasil dikirim ke <strong>{$successCount}</strong> user!";
            if ($failCount > 0) {
                $message .= " ❌ Gagal: {$failCount} user.";
            }
            $messageType = $successCount > 0 ? 'success' : 'error';

            logAction($_SESSION['user_id'], "Broadcast email: $subject (Target: $target, Success: $successCount, Fail: $failCount)");
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Broadcast Email - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .container { max-width: 700px; margin: 0 auto; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 4px; color: #1A1A2E; font-size: 13px; }
        .form-group input, .form-group textarea, .form-group select {
            width: 100%; padding: 8px 12px; border: 1.5px solid #DDE3F0; border-radius: 8px; font-size: 14px; font-family: inherit;
        }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus {
            border-color: #E53935; outline: none;
        }
        .form-group textarea { min-height: 150px; resize: vertical; }
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
    </style>
</head>
<body>
    <div class="app">
        <?php include "sidebar.php"; ?>
        <main class="main">
            <div class="container">
                <header class="topbar">
                    <div class="topbar-left">
                        <button class="hamburger" onclick="toggleSidebar()">☰</button>
                        <h2>📧 Broadcast Email</h2>
                    </div>
                    <div class="topbar-right">
                        <span class="user-badge">👤 <?= htmlspecialchars($_SESSION['fullname']) ?> <span class="role-badge"><?= $_SESSION['role'] ?></span></span>
                    </div>
                </header>

                <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?>"><?= $message ?></div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header"><h3>📧 Kirim Email ke User</h3></div>
                    <div class="card-body">
                        <form method="POST">
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
                                <div class="selected-info">💡 Centang user yang ingin dikirimi email.</div>
                            </div>

                            <div class="form-group">
                                <label>Subject Email <span style="color:#E53935;">*</span></label>
                                <input type="text" name="subject" placeholder="Masukkan subject email" required>
                            </div>

                            <div class="form-group">
                                <label>Konten Email <span style="color:#E53935;">*</span></label>
                                <textarea name="content" placeholder="Tulis pesan email di sini..." required></textarea>
                            </div>

                            <button type="submit" name="send_broadcast" class="btn btn-primary" onclick="return confirm('Kirim email ke semua user terpilih?')">
                                📧 Kirim Email
                            </button>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h3>📋 Daftar User</h3></div>
                    <div class="card-body">
                        <table class="submission-table">
                            <thead>
                                <tr><th>Username</th><th>Nama</th><th>Email</th><th>Role</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $u): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($u['username']) ?></strong></td>
                                    <td><?= htmlspecialchars($u['fullname']) ?></td>
                                    <td><?= htmlspecialchars($u['email']) ?></td>
                                    <td><span class="role-badge <?= $u['role'] ?>"><?= strtoupper(str_replace('_', ' ', $u['role'])) ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
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
</body>
</html>
