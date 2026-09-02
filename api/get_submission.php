<?php
require_once '../config/database.php';
require_once 'config/auth.php';
if (!isLoggedIn()) { http_response_code(401); echo json_encode(null); exit; }
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM form_submissions WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $_SESSION['user_id']]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    header('Content-Type: application/json');
    echo json_encode($data);
} else { echo json_encode(null); }
?>
