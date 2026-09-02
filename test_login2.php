<?php
require_once 'config/database.php';
require_once 'config/auth.php';

$username = 'admin';
$password = 'admin123';

echo "=== TEST LOGIN ===\n";
echo "Username: $username\n";
echo "Password: $password\n\n";

$stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

echo "User ditemukan: " . ($user ? '✅ YA' : '❌ TIDAK') . "\n";

if ($user) {
    echo "Username: " . $user['username'] . "\n";
    echo "Fullname: " . $user['fullname'] . "\n";
    echo "Role: " . $user['role'] . "\n";
    echo "Password hash: " . substr($user['password'], 0, 30) . "...\n";
    echo "Password verify: " . (password_verify($password, $user['password']) ? '✅ BENAR' : '❌ SALAH') . "\n";
    echo "\n";
    echo "Mencoba login() function...\n";
    if (login($username, $password)) {
        echo "✅ Login BERHASIL!\n";
        echo "Session user_id: " . $_SESSION['user_id'] . "\n";
        echo "Session fullname: " . $_SESSION['fullname'] . "\n";
    } else {
        echo "❌ Login GAGAL!\n";
    }
}
?>
