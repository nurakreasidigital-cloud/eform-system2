<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Hanya super_admin yang bisa akses
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: dashboard.php');
    exit;
}

$message = '';
$messageType = '';

// Ambil data maintenance
$stmt = $pdo->query("SELECT * FROM settings WHERE setting_key = 'maintenance_mode'");
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

$isOn = $settings && $settings['setting_value'] === 'on';
$text = $settings['maintenance_text'] ?? 'Sistem sedang dalam pemeliharaan. Mohon tunggu beberapa saat.';
$endTime = $settings['maintenance_end_time'] ?? date('Y-m-d H:i:s', strtotime('+2 hours'));

// Update setting
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $newText = trim($_POST['maintenance_text'] ?? '');
    $newEndTime = $_POST['maintenance_end_time'] ?? '';
    $status = isset($_POST['maintenance_status']) ? 'on' : 'off';

    if ($newText && $newEndTime) {
        $stmt = $pdo->prepare("UPDATE settings SET 
            setting_value = ?, 
            maintenance_text = ?, 
            maintenance_end_time = ? 
            WHERE setting_key = 'maintenance_mode'");
        $stmt->execute([$status, $newText, $newEndTime]);
        
        logAction($_SESSION['user_id'], "Maintenance settings updated: Status=$status, EndTime=$newEndTime");
        $message = '✅ Pengaturan maintenance berhasil disimpan!';
        $messageType = 'success';
        
        // Refresh data
        $stmt = $pdo->query("SELECT * FROM settings WHERE setting_key = 'maintenance_mode'");
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        $isOn = $settings && $settings['setting_value'] === 'on';
        $text = $settings['maintenance_text'] ?? $text;
        $endTime = $settings['maintenance_end_time'] ?? $endTime;
    } else {
        $message = '⚠️ Semua field harus diisi!';
        $messageType = 'error';
    }
}

// Toggle cepat dari GET
if (isset($_GET['toggle'])) {
    $newStatus = $_GET['toggle'] === 'on' ? 'on' : 'off';
    $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'maintenance_mode'");
    $stmt->execute([$newStatus]);
    logAction($_SESSION['user_id'], "Maintenance toggled: " . strtoupper($newStatus));
    header('Location: maintenance_settings.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance Settings - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .container { max-width: 700px; margin: 0 auto; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 4px; color: #1A1A2E; font-size: 13px; }
        .form-group input, .form-group textarea, .form-group select {
            width: 100%; padding: 8px 12px; border: 1.5px solid #DDE3F0; border-radius: 8px; font-size: 14px; font-family: inherit;
        }
        .form-group input:focus, .form-group textarea:focus { border-color: #E53935; outline: none; }
        .form-group textarea { min-height: 80px; resize: vertical; }
        .alert { padding: 10px 14px; border-radius: 8px; margin-bottom: 14px; }
        .alert-success { background: #EEFAF4; color: #1A7A45; border: 1px solid #B6E8CF; }
        .alert-error { background: #FFE5E5; color: #C62828; border: 1px solid #FFB3B3; }
        .btn { display: inline-block; padding: 10px 24px; border-radius: 8px; font-weight: 700; cursor: pointer; border: none; font-size: 14px; transition: 0.2s; }
        .btn-primary { background: #E53935; color: #fff; }
        .btn-primary:hover { background: #c62828; }
        .btn-success { background: #2E7D32; color: #fff; }
        .btn-success:hover { background: #1B5E20; }
        .btn-danger { background: #C62828; color: #fff; }
        .btn-danger:hover { background: #8E0000; }
        .btn-secondary { background: #6B7A99; color: #fff; }
        .btn-secondary:hover { background: #5a6a89; }
        .status-box { padding: 14px 18px; border-radius: 8px; margin-bottom: 16px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; }
        .status-box.on { background: #FFE5E5; border: 1px solid #FFB3B3; }
        .status-box.off { background: #EEFAF4; border: 1px solid #B6E8CF; }
        .status-box .label { font-weight: 700; font-size: 16px; }
        .status-box .label.on { color: #C62828; }
        .status-box .label.off { color: #1A7A45; }
        .btn-row { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 6px; }
        .preview-box { background: #F8F9FC; border: 1px solid #DDE3F0; border-radius: 8px; padding: 16px; margin-top: 12px; }
        .preview-box .title { font-weight: 700; font-size: 14px; color: #1A1A2E; }
        .preview-box .time { color: #6B7A99; font-size: 12px; }
        .preview-box .text { margin: 8px 0; }
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
                        <h2>🔧 Maintenance Settings</h2>
                    </div>
                    <div class="topbar-right">
                        <span class="user-badge">👤 <?= htmlspecialchars($_SESSION['fullname']) ?> <span class="role-badge"><?= $_SESSION['role'] ?></span></span>
                    </div>
                </header>

                <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?>"><?= $message ?></div>
                <?php endif; ?>

                <!-- STATUS -->
                <div class="status-box <?= $isOn ? 'on' : 'off' ?>">
                    <span class="label <?= $isOn ? 'on' : 'off' ?>">
                        <?= $isOn ? '🔴 Maintenance AKTIF' : '🟢 Maintenance NONAKTIF' ?>
                    </span>
                    <div style="display:flex;gap:8px;">
                        <?php if ($isOn): ?>
                        <a href="?toggle=off" class="btn btn-success" onclick="return confirm('Nonaktifkan maintenance?')">🟢 Matikan</a>
                        <?php else: ?>
                        <a href="?toggle=on" class="btn btn-danger" onclick="return confirm('Aktifkan maintenance?')">🔴 Aktifkan</a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- FORM SETTINGS -->
                <div class="card">
                    <div class="card-header"><h3>⚙️ Pengaturan Maintenance</h3></div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="form-group">
                                <label>Status Maintenance</label>
                                <select name="maintenance_status" style="width:100%;padding:8px 12px;border:1.5px solid #DDE3F0;border-radius:8px;font-size:14px;">
                                    <option value="on" <?= $isOn ? 'selected' : '' ?>>🔴 Aktif</option>
                                    <option value="off" <?= !$isOn ? 'selected' : '' ?>>🟢 Nonaktif</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Pesan Maintenance <span style="color:#E53935;">*</span></label>
                                <textarea name="maintenance_text" rows="4" required><?= htmlspecialchars($text) ?></textarea>
                            </div>

                            <div class="form-group">
                                <label>Waktu Selesai <span style="color:#E53935;">*</span></label>
                                <input type="datetime-local" name="maintenance_end_time" value="<?= date('Y-m-d\TH:i', strtotime($endTime)) ?>" required>
                            </div>

                            <button type="submit" name="save_settings" class="btn btn-primary">💾 Simpan Pengaturan</button>
                        </form>
                    </div>
                </div>

                <!-- PREVIEW -->
                <div class="card">
                    <div class="card-header"><h3>👁 Preview Halaman Maintenance</h3></div>
                    <div class="card-body">
                        <div class="preview-box">
                            <div class="title">🔧 <?= htmlspecialchars($text) ?></div>
                            <div class="time">🕐 Diperkirakan selesai: <?= date('d/m/Y H:i', strtotime($endTime)) ?></div>
                            <div class="text"><?= nl2br(htmlspecialchars($text)) ?></div>
                            <div style="margin-top:8px;color:#6B7A99;font-size:11px;">E-Form System v1.0 &copy; 2026 PT. Nura Kreasi Digital</div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <script src="assets/js/main.js"></script>
</body>
</html>
