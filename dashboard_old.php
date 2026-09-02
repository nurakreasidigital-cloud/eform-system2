<?php
require_once 'config/database.php';
require_once 'config/auth.php';
requireLogin();

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$totalForms = $pdo->query("SELECT COUNT(*) FROM forms")->fetchColumn();
$activeForms = $pdo->query("SELECT COUNT(*) FROM forms WHERE is_active = 1")->fetchColumn();
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

$stmt = $pdo->prepare("SELECT s.*, f.form_name, f.icon FROM form_submissions s 
                       JOIN forms f ON s.form_id = f.id 
                       WHERE s.user_id = ? 
                       ORDER BY s.created_at DESC LIMIT 10");
$stmt->execute([$_SESSION['user_id']]);
$submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="app">
        <aside class="sidebar">
            <div class="sidebar-brand"><span class="brand-icon">📋</span><span class="brand-text">E-Form</span></div>
            <nav class="sidebar-nav">
                <a href="dashboard.php" class="active">📊 Dashboard</a>
                <?php if (hasRole('admin')): ?>
                <a href="admin.php">👥 Admin Panel</a>
                <?php endif; ?>
                <a href="forms.php">📝 Manajemen Form</a>
                <a href="profile.php">⚙️ Profile</a>
                <a href="logout.php" class="logout">🚪 Logout</a>
            </nav>
            <div class="sidebar-footer"><small><?= APP_NAME ?> v1.0</small></div>
        </aside>

        <main class="main">
            <header class="topbar">
                <div class="topbar-left"><h2>Dashboard</h2></div>
                <div class="topbar-right">
                    <span class="user-badge">👤 <?= htmlspecialchars($_SESSION['fullname']) ?> <span class="role-badge"><?= $_SESSION['role'] ?></span></span>
                </div>
            </header>

            <div class="stats-grid">
                <div class="stat-card"><div class="stat-icon">📝</div><div class="stat-info"><div class="stat-number"><?= $totalForms ?></div><div class="stat-label">Total Forms</div></div></div>
                <div class="stat-card success"><div class="stat-icon">✅</div><div class="stat-info"><div class="stat-number"><?= $activeForms ?></div><div class="stat-label">Active Forms</div></div></div>
                <div class="stat-card warning"><div class="stat-icon">⛔</div><div class="stat-info"><div class="stat-number"><?= $totalForms - $activeForms ?></div><div class="stat-label">Inactive</div></div></div>
                <div class="stat-card info"><div class="stat-icon">👥</div><div class="stat-info"><div class="stat-number"><?= $totalUsers ?></div><div class="stat-label">Users</div></div></div>
            </div>

            <div class="card">
                <div class="card-header"><h3>🚀 Quick Access</h3></div>
                <div class="card-body">
                    <div class="form-grid">
                        <?php
                        $forms = $pdo->query("SELECT * FROM forms WHERE is_active = 1 ORDER BY form_name")->fetchAll(PDO::FETCH_ASSOC);
                        if (empty($forms)): ?>
                            <p style="color:#6B7A99;">Belum ada form aktif.</p>
                        <?php else: ?>
                        <?php foreach ($forms as $form): 
                            $file = $form['form_key'] . '.php';
                            $exists = file_exists($file);
                        ?>
                        <div class="form-card <?= $exists ? 'active' : 'inactive' ?>">
                            <div class="form-card-icon"><?= $form['icon'] ?></div>
                            <div class="form-card-info">
                                <h4><?= htmlspecialchars($form['form_name']) ?></h4>
                                <p><?= htmlspecialchars($form['description']) ?></p>
                            </div>
                            <?php if ($exists): ?>
                                <a href="<?= $file ?>" class="btn btn-primary">Buka Form</a>
                            <?php else: ?>
                                <span class="btn btn-disabled">Tidak Tersedia</span>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3>📋 Riwayat Submission</h3></div>
                <div class="card-body">
                    <?php if (empty($submissions)): ?>
                        <p style="text-align:center;color:#6B7A99;padding:20px;">Belum ada submission</p>
                    <?php else: ?>
                    <table class="table">
                        <thead><tr><th>Form</th><th>Tgl Kirim</th><th>Status</th><th>Action</th></tr></thead>
                        <tbody>
                        <?php foreach ($submissions as $s): 
                            $statusClass = ['draft'=>'draft','locked'=>'locked','submitted'=>'submitted','approved'=>'approved','rejected'=>'rejected'][$s['status']] ?? 'draft';
                            $statusLabel = ['draft'=>'📝 Draf','locked'=>'🔒 Terkunci','submitted'=>'📤 Terkirim','approved'=>'✅ Diterima','rejected'=>'❌ Ditolak'][$s['status']] ?? $s['status'];
                        ?>
                        <tr>
                            <td><?= $s['icon'] ?> <?= htmlspecialchars($s['form_name']) ?></td>
                            <td><?= $s['submitted_at'] ? date('d/m/Y H:i', strtotime($s['submitted_at'])) : '-' ?></td>
                            <td><span class="status-badge <?= $statusClass ?>"><?= $statusLabel ?></span></td>
                            <td>
                                <?php if ($s['pdf_path']): ?>
                                <a href="<?= $s['pdf_path'] ?>" class="btn btn-sm btn-outline" target="_blank">📄</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    <script src="assets/js/main.js"></script>
</body>
</html>
