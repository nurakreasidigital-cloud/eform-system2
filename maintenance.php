<?php
require_once 'config/database.php';

// Ambil data maintenance
$stmt = $pdo->query("SELECT * FROM settings WHERE setting_key = 'maintenance_mode'");
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

$isOn = $settings && $settings['setting_value'] === 'on';
$text = $settings['maintenance_text'] ?? 'Sistem sedang dalam pemeliharaan. Mohon tunggu beberapa saat.';
$endTime = $settings['maintenance_end_time'] ?? date('Y-m-d H:i:s', strtotime('+2 hours'));

// Jika maintenance off, redirect ke dashboard
if (!$isOn) {
    header('Location: dashboard.php');
    exit;
}

// Cek session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Jika user super_admin, bisa akses
if (isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin') {
    // Super admin bisa lanjut
    // Tapi tetap tampilkan halaman maintenance dengan link ke dashboard
    $showPage = true;
    $isSuperAdmin = true;
} else {
    $showPage = true;
    $isSuperAdmin = false;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance - <?= APP_NAME ?></title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #1A1A2E, #2D2D4E);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            flex-direction: column;
        }
        .maintenance-box {
            text-align: center;
            padding: 40px;
            max-width: 550px;
            background: rgba(255,255,255,0.05);
            border-radius: 20px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.08);
        }
        .maintenance-box .icon { font-size: 72px; margin-bottom: 16px; }
        .maintenance-box h1 { font-size: 26px; margin-bottom: 10px; font-weight: 700; }
        .maintenance-box .text { color: #8899BB; font-size: 16px; line-height: 1.7; margin-bottom: 16px; }
        .maintenance-box .time { font-size: 14px; color: #6B7A99; }
        .maintenance-box .countdown {
            font-size: 32px;
            font-weight: 800;
            color: #F5A623;
            margin: 16px 0;
            font-variant-numeric: tabular-nums;
        }
        .maintenance-box .countdown span { display: inline-block; min-width: 50px; }
        .footer { margin-top: 30px; color: rgba(255,255,255,0.25); font-size: 12px; text-align: center; }
        .progress-bar {
            width: 100%;
            height: 4px;
            background: rgba(255,255,255,0.1);
            border-radius: 4px;
            margin: 16px 0 8px;
            overflow: hidden;
        }
        .progress-bar .fill {
            height: 100%;
            background: linear-gradient(90deg, #F5A623, #E53935);
            border-radius: 4px;
            transition: width 1s ease;
            width: 100%;
        }
        .refresh-btn {
            margin-top: 20px;
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.12);
            color: #8899BB;
            padding: 8px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 13px;
            transition: 0.2s;
        }
        .refresh-btn:hover {
            background: rgba(255,255,255,0.15);
            color: #fff;
        }
        .admin-link {
            margin-top: 16px;
            color: #6B7A99;
            font-size: 12px;
        }
        .admin-link a {
            color: #F5A623;
            text-decoration: none;
        }
        .admin-link a:hover { text-decoration: underline; }
        .bypass-info {
            background: rgba(245,166,35,0.1);
            border: 1px solid rgba(245,166,35,0.2);
            border-radius: 8px;
            padding: 10px 14px;
            margin-bottom: 16px;
            font-size: 13px;
            color: #F5A623;
        }
        @media (max-width: 480px) {
            .maintenance-box { padding: 24px; margin: 0 12px; }
            .maintenance-box .icon { font-size: 48px; }
            .maintenance-box h1 { font-size: 20px; }
            .maintenance-box .countdown { font-size: 24px; }
        }
    </style>
</head>
<body>
    <div class="maintenance-box">
        <div class="icon">🔧</div>
        <h1>Sedang dalam Pemeliharaan</h1>
        <div class="text"><?= nl2br(htmlspecialchars($text)) ?></div>

        <?php if ($isSuperAdmin): ?>
        <div class="bypass-info">
            👑 Anda adalah <strong>Super Admin</strong>. Anda tetap bisa mengakses sistem.<br>
            <a href="dashboard.php" style="color:#F5A623;">→ Kembali ke Dashboard</a>
        </div>
        <?php endif; ?>

        <div class="countdown" id="countdown">
            <span id="hours">--</span>:<span id="minutes">--</span>:<span id="seconds">--</span>
        </div>

        <div class="progress-bar">
            <div class="fill" id="progressFill"></div>
        </div>

        <div class="time">🕐 Diperkirakan selesai: <span id="endTimeText"><?= date('d/m/Y H:i', strtotime($endTime)) ?></span></div>

        <button class="refresh-btn" onclick="location.reload()">🔄 Refresh</button>
    </div>

    <div class="footer">
        E-Form System v1.0 &copy; 2026 PT. Nura Kreasi Digital
    </div>

    <script>
    var endTime = new Date('<?= date('Y-m-d\TH:i:s', strtotime($endTime)) ?>').getTime();

    function updateCountdown() {
        var now = new Date().getTime();
        var distance = endTime - now;

        if (distance <= 0) {
            document.getElementById('countdown').innerHTML = '✅ Selesai!';
            document.getElementById('progressFill').style.width = '100%';
            return;
        }

        var hours = Math.floor(distance / (1000 * 60 * 60));
        var minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
        var seconds = Math.floor((distance % (1000 * 60)) / 1000);

        document.getElementById('hours').textContent = String(hours).padStart(2, '0');
        document.getElementById('minutes').textContent = String(minutes).padStart(2, '0');
        document.getElementById('seconds').textContent = String(seconds).padStart(2, '0');

        var total = 4 * 60 * 60 * 1000;
        var progress = Math.min(100, ((total - distance) / total) * 100);
        document.getElementById('progressFill').style.width = Math.max(0, 100 - progress) + '%';
    }

    updateCountdown();
    setInterval(updateCountdown, 1000);
    </script>
</body>
</html>
