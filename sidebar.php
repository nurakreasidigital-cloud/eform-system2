<?php
// sidebar.php - Sidebar Dinamis
$unreadCount = 0;
if (isset($_SESSION['user_id'])) {
    global $pdo;
    if ($pdo) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$_SESSION['user_id']]);
        $unreadCount = $stmt->fetchColumn();
    }
}

$current_page = basename($_SERVER['PHP_SELF']);
$is_surat = strpos($_SERVER['REQUEST_URI'], '/surat/') !== false;
$base = $is_surat ? '../' : '';

// Cek maintenance
$isMaintenance = false;
try {
    $stmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'maintenance_mode'");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $isMaintenance = ($row && $row['setting_value'] === 'on');
} catch (Exception $e) {
    $isMaintenance = false;
}
?>
<aside class="sidebar">
    <div class="sidebar-brand">
        <span class="brand-icon">📋</span>
        <span class="brand-text">E-Form</span>
    </div>
    <nav class="sidebar-nav">
        <a href="<?= $base ?>dashboard.php" class="<?= $current_page == 'dashboard.php' ? 'active' : '' ?>">
            📊 Dashboard
        </a>
        
        <?php if (hasRole('super_admin') || hasRole('admin')): ?>
        <a href="<?= $base ?>admin_users.php" class="<?= $current_page == 'admin_users.php' ? 'active' : '' ?>">
            👥 Manajemen User
        </a>
        <a href="<?= $base ?>forms.php" class="<?= $current_page == 'forms.php' ? 'active' : '' ?>">
            📝 Manajemen Form
        </a>
        <a href="<?= $base ?>surat/index.php" class="<?= $current_page == 'index.php' && $is_surat ? 'active' : '' ?>">
            📬 Agenda Surat
        </a>
        <a href="<?= $base ?>broadcast.php" class="<?= $current_page == 'broadcast.php' ? 'active' : '' ?>">
            📧 Broadcast Email
        </a>
        <a href="<?= $base ?>send_notification.php" class="<?= $current_page == 'send_notification.php' ? 'active' : '' ?>">
            📤 Kirim Notifikasi
        </a>
        <a href="<?= $base ?>admin.php" class="<?= $current_page == 'admin.php' ? 'active' : '' ?>">
            📋 Admin Panel
        </a>
        <?php endif; ?>

        <?php if (hasRole('super_admin')): ?>
        <a href="<?= $base ?>maintenance_settings.php" class="<?= $current_page == 'maintenance_settings.php' ? 'active' : '' ?>">
            🔧 Maintenance <?php if ($isMaintenance): ?><span style="color:#E53935;">(ON)</span><?php else: ?><span style="color:#2E7D32;">(OFF)</span><?php endif; ?>
        </a>
        <?php endif; ?>
        
        <a href="<?= $base ?>notifications.php" class="<?= $current_page == 'notifications.php' ? 'active' : '' ?>">
            🔔 Notifikasi
            <?php if ($unreadCount > 0): ?>
            <span class="badge-count"><?= $unreadCount ?></span>
            <?php endif; ?>
        </a>
        <a href="<?= $base ?>profile.php" class="<?= $current_page == 'profile.php' ? 'active' : '' ?>">
            ⚙️ Profile
        </a>
        <a href="<?= $base ?>logout.php" class="logout">🚪 Logout</a>
    </nav>
    <div class="sidebar-footer">
        <small><?= APP_NAME ?> v1.0</small>
    </div>
</aside>
