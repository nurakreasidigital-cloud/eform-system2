<?php
require_once 'config/database.php';
require_once 'config/auth.php';
requireAdmin();

if (isset($_GET['read']) && isset($_GET['id'])) {
    $id = (int)$_GET['read'];
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
    $stmt->execute([$id]);
    header('Location: admin.php');
    exit;
}

if (isset($_GET['read_all'])) {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE is_read = 0");
    $stmt->execute();
    header('Location: admin.php');
    exit;
}

$notifications = $pdo->prepare("SELECT * FROM notifications WHERE is_read = 0 ORDER BY created_at DESC LIMIT 20");
$notifications->execute();
$notifList = $notifications->fetchAll(PDO::FETCH_ASSOC);
$unreadCount = count($notifList);

$submissions = $pdo->query("
    SELECT s.*, u.username, u.fullname, f.form_name, f.icon 
    FROM form_submissions s 
    JOIN users u ON s.user_id = u.id 
    JOIN forms f ON s.form_id = f.id 
    ORDER BY s.created_at DESC LIMIT 30
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .admin-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-bottom: 20px; }
        .admin-stat { background: #fff; border-radius: 10px; padding: 14px 16px; border: 1px solid #DDE3F0; text-align: center; }
        .admin-stat .num { font-size: 28px; font-weight: 800; }
        .admin-stat .label { font-size: 11px; color: #6B7A99; font-weight: 600; text-transform: uppercase; }
        .submission-card { background: #fff; border: 1px solid #DDE3F0; border-radius: 10px; padding: 16px 18px; margin-bottom: 12px; transition: 0.2s; }
        .submission-card:hover { border-color: #C0C8E0; }
        .btn-sm { padding: 4px 12px; font-size: 11px; border-radius: 4px; border: none; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-primary { background: #E53935; color: #fff; }
        .btn-primary:hover { background: #c62828; }
        .status-badge { display: inline-block; padding: 2px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .badge-submitted { background: #EEF4FF; color: #2D5BE3; }
        .badge-approved { background: #EEFAF4; color: #1A7A45; }
        .badge-rejected { background: #FFE5E5; color: #C62828; }
        .badge-draft { background: #EEF0F6; color: #6B7A99; }
        .badge-locked { background: #FFFBEE; color: #B07A00; }
        .notif-badge { background: #E53935; color: #fff; padding: 2px 6px; border-radius: 50%; font-size: 10px; font-weight: 700; }
    </style>
</head>
<body>
<?php include "sidebar.php"; ?>
    <div class="app">

        <main class="main">
            <header class="topbar">
                <div class="topbar-left"><h2>📋 Admin Panel</h2></div>
                <div class="topbar-right">
                    <span class="user-badge">👤 <?= htmlspecialchars($_SESSION['fullname']) ?> <span class="role-badge"><?= $_SESSION['role'] ?></span></span>
                </div>
            </header>

            <div class="admin-stats">
                <div class="admin-stat pending"><div class="num"><?= $submissions ? count(array_filter($submissions, fn($s) => $s['status'] === 'submitted')) : 0 ?></div><div class="label">Menunggu</div></div>
                <div class="admin-stat approved"><div class="num"><?= $submissions ? count(array_filter($submissions, fn($s) => $s['status'] === 'approved')) : 0 ?></div><div class="label">Diterima</div></div>
                <div class="admin-stat rejected"><div class="num"><?= $submissions ? count(array_filter($submissions, fn($s) => $s['status'] === 'rejected')) : 0 ?></div><div class="label">Ditolak</div></div>
                <div class="admin-stat total"><div class="num"><?= count($submissions) ?></div><div class="label">Total</div></div>
            </div>

            <div id="submissionList">
                <?php if (empty($submissions)): ?>
                <div style="text-align:center;padding:40px 20px;color:#6B7A99;">
                    <div style="font-size:48px;">📂</div>
                    <p>Belum ada submission</p>
                </div>
                <?php else: ?>
                <?php foreach ($submissions as $s): 
                    $statusClass = 'badge-' . $s['status'];
                    $statusLabel = ['draft'=>'📝 Draf','locked'=>'🔒 Terkunci','submitted'=>'📤 Terkirim','approved'=>'✅ Diterima','rejected'=>'❌ Ditolak'][$s['status']] ?? $s['status'];
                ?>
                <div class="submission-card">
                    <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:8px;">
                        <div>
                            <strong><?= htmlspecialchars($s['fullname'] ?? $s['username']) ?></strong>
                            <span style="color:#6B7A99;font-size:13px;"><?= $s['icon'] ?> <?= htmlspecialchars($s['form_name']) ?></span>
                        </div>
                        <span class="status-badge <?= $statusClass ?>"><?= $statusLabel ?></span>
                    </div>
                    <div style="font-size:12px;color:#6B7A99;margin:4px 0;">
                        📅 <?= date('d/m/Y H:i', strtotime($s['created_at'])) ?> 
                        🆔 #<?= $s['id'] ?>
                    </div>
                    <?php if ($s['pdf_path']): ?>
                    <a href="<?= $s['pdf_path'] ?>" class="btn btn-sm btn-primary" target="_blank" style="margin-top:4px;">📄 Lihat PDF</a>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>
    <script src="assets/js/main.js"></script>
    <!-- FOOTER -->
    <footer style="text-align:center;padding:16px 0 8px;border-top:1px solid #DDE3F0;margin-top:20px;font-size:11px;color:#6B7A99;">
        E-Form System v1.0 &copy; 2026 PT. Nura Kreasi Digital
    </footer>
</body>
</html>
