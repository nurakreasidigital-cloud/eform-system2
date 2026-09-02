<?php
require_once 'config/database.php';
require_once 'config/auth.php';

if (!isLoggedIn()) {
    http_response_code(401);
    exit;
}

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

if ($user_id > 0) {
    $stmt = $pdo->prepare("SELECT form_id FROM user_form_access WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $result = $stmt->fetchAll(PDO::FETCH_COLUMN);
    header('Content-Type: application/json');
    echo json_encode($result);
} else {
    echo json_encode([]);
}
?>