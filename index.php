<?php
require_once 'config/database.php';
require_once 'config/auth.php';

$error = '';

// Cek maintenance SEBELUM login
if (isMaintenanceMode() && !isset($_SESSION['role'])) {
    // Belum login, tampilkan pesan di halaman login
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (login($username, $password)) {
        logAction($_SESSION['user_id'], 'Login');
        
        // SETELAH LOGIN, CEK MAINTENANCE!
        checkMaintenance();
        
        header('Location: dashboard');
        exit;
    } else {
        $error = 'Username atau password salah!';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= APP_NAME ?></title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #1A1A2E, #2D2D4E);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
        }
        .login-container {
            background: #fff;
            border-radius: 16px;
            padding: 40px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
        }
        .login-container h1 { font-size: 24px; color: #1A1A2E; margin-bottom: 8px; text-align: center; }
        .login-container p { color: #6B7A99; font-size: 14px; text-align: center; margin-bottom: 24px; }
        .field { margin-bottom: 16px; }
        .field label { display: block; font-size: 12px; font-weight: 700; color: #6B7A99; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; }
        .field input { width: 100%; padding: 12px 14px; border: 1.5px solid #DDE3F0; border-radius: 8px; font-size: 14px; outline: none; transition: 0.2s; }
        .field input:focus { border-color: #E53935; box-shadow: 0 0 0 3px rgba(229,57,53,0.1); }
        .btn { width: 100%; padding: 12px; background: #E53935; color: #fff; border: none; border-radius: 8px; font-size: 14px; font-weight: 700; cursor: pointer; transition: 0.2s; }
        .btn:hover { background: #c62828; transform: translateY(-1px); }
        .error { background: #FFE5E5; color: #CC0000; padding: 10px 14px; border-radius: 8px; font-size: 13px; margin-bottom: 16px; text-align: center; }
        .logo-box { text-align: center; font-size: 48px; margin-bottom: 8px; }
        .footer { margin-top: 20px; color: rgba(255,255,255,0.4); font-size: 11px; text-align: center; }
        .maintenance-banner {
            background: #FFE5E5;
            color: #C62828;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 13px;
            text-align: center;
            margin-bottom: 16px;
            border: 1px solid #FFB3B3;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo-box">📋</div>
        <h1><?= APP_NAME ?></h1>
        <p>Login untuk mengakses sistem</p>

        <?php if (isMaintenanceMode()): ?>
        <div class="maintenance-banner">
            🔧 Sistem sedang dalam pemeliharaan.<br>
            <small>Hanya Super Admin yang bisa login.</small>
        </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="field">
                <label>Username</label>
                <input type="text" name="username" placeholder="Masukkan username" required autofocus>
            </div>
            <div class="field">
                <label>Password</label>
                <input type="password" name="password" placeholder="Masukkan password" required>
            </div>
            <button type="submit" class="btn" style="margin-top:10px;">🔑 Login</button>
        </form>
    </div>

    <div class="footer">
        E-Form System v1.0 &copy; 2026 PT. Nura Kreasi Digital
    </div>
</body>
</html>
