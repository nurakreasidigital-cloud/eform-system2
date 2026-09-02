<?php
require_once '../config/database.php';
require_once '../config/auth.php';
requireAdmin();

// Statistik
$totalMasuk = $pdo->query("SELECT COUNT(*) FROM surat_masuk")->fetchColumn();
$totalKeluar = $pdo->query("SELECT COUNT(*) FROM surat_keluar")->fetchColumn();
$totalDisposisi = $pdo->query("SELECT COUNT(*) FROM disposisi")->fetchColumn();
$disposisiPending = $pdo->query("SELECT COUNT(*) FROM disposisi WHERE status = 'pending'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agenda Surat - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 14px; margin-bottom: 20px; }
        .stat-card { background: #fff; border-radius: 10px; padding: 16px; border: 1px solid #DDE3F0; text-align: center; }
        .stat-card .num { font-size: 28px; font-weight: 800; color: #1A1A2E; }
        .stat-card .label { font-size: 11px; color: #6B7A99; font-weight: 600; text-transform: uppercase; }
        .stat-card.pending .num { color: #F5A623; }
        .menu-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; margin-top: 16px; }
        .menu-card { background: #fff; border: 1px solid #DDE3F0; border-radius: 10px; padding: 24px 20px; text-align: center; transition: 0.2s; text-decoration: none; color: #1A1A2E; }
        .menu-card:hover { border-color: #C0C8E0; box-shadow: 0 4px 12px rgba(0,0,0,0.06); transform: translateY(-2px); }
        .menu-card .icon { font-size: 36px; }
        .menu-card .name { font-weight: 700; font-size: 15px; margin-top: 8px; }
        .menu-card .desc { font-size: 12px; color: #6B7A99; margin-top: 4px; }
    </style>
</head>
<body>
<?php include "../sidebar.php"; ?>
    <div class="app">

        <main class="main">
            <header class="topbar">
                <div class="topbar-left"><h2>📬 Agenda Surat</h2></div>
                <div class="topbar-right">
                    <span class="user-badge">👤 <?= htmlspecialchars($_SESSION['fullname']) ?> <span class="role-badge"><?= $_SESSION['role'] ?></span></span>
                </div>
            </header>

            <div class="stats-grid">
                <div class="stat-card"><div class="num"><?= $totalMasuk ?></div><div class="label">📥 Surat Masuk</div></div>
                <div class="stat-card"><div class="num"><?= $totalKeluar ?></div><div class="label">📤 Surat Keluar</div></div>
                <div class="stat-card"><div class="num"><?= $totalDisposisi ?></div><div class="label">📋 Disposisi</div></div>
                <div class="stat-card pending"><div class="num"><?= $disposisiPending ?></div><div class="label">⏳ Pending</div></div>
            </div>

            <div class="card">
                <div class="card-header"><h3>📋 Menu Agenda Surat</h3></div>
                <div class="card-body">
                    <div class="menu-grid">
                        <a href="masuk.php" class="menu-card">
                            <div class="icon">📥</div>
                            <div class="name">Surat Masuk</div>
                            <div class="desc">Tambah & daftar surat masuk</div>
                        </a>
                        <a href="keluar.php" class="menu-card">
                            <div class="icon">📤</div>
                            <div class="name">Surat Keluar</div>
                            <div class="desc">Tambah & daftar surat keluar</div>
                        </a>
                        <a href="disposisi.php" class="menu-card">
                            <div class="icon">📋</div>
                            <div class="name">Disposisi</div>
                            <div class="desc">Tambah & daftar disposisi</div>
                        </a>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <script src="../assets/js/main.js"></script>
</body>
</html>
