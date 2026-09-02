<?php
require_once '../config/database.php';
require_once 'config/auth.php';
if (!isLoggedIn()) { http_response_code(401); echo json_encode([]); exit; }
$form_key = isset($_GET['form_key']) ? $_GET['form_key'] : '';
if ($form_key) {
    $stmt = $pdo->prepare("SELECT s.*, f.form_name, f.icon FROM form_submissions s JOIN forms f ON s.form_id = f.id WHERE s.user_id = ? AND f.form_key = ? ORDER BY s.created_at DESC LIMIT 30");
    $stmt->execute([$_SESSION['user_id'], $form_key]);
} else {
    $stmt = $pdo->prepare("SELECT s.*, f.form_name, f.icon FROM form_submissions s JOIN forms f ON s.form_id = f.id WHERE s.user_id = ? ORDER BY s.created_at DESC LIMIT 30");
    $stmt->execute([$_SESSION['user_id']]);
}
header('Content-Type: application/json');
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
