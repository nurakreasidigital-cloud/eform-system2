<?php
require_once 'config/database.php';
require_once 'config/auth.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

if ($user_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid user ID']);
    exit;
}

// Ambil data user
$stmt = $pdo->prepare("SELECT id, username, fullname, email, role, is_active FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    http_response_code(404);
    echo json_encode(['error' => 'User not found']);
    exit;
}

// Ambil form access
$stmt = $pdo->prepare("SELECT form_id FROM user_form_access WHERE user_id = ?");
$stmt->execute([$user_id]);
$user['form_access'] = $stmt->fetchAll(PDO::FETCH_COLUMN);

header('Content-Type: application/json');
echo json_encode($user);
?>
