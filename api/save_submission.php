<?php
require_once '../config/database.php';
require_once '../config/auth.php';
if (!isLoggedIn()) { http_response_code(401); echo json_encode(['success' => false]); exit; }
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) { echo json_encode(['success' => false]); exit; }
$form_key = $input['form_key'] ?? '';
$form_data = $input['data'] ?? [];
$status = $input['status'] ?? 'draft';
$submission_id = isset($input['submission_id']) ? (int)$input['submission_id'] : 0;
$stmt = $pdo->prepare("SELECT id FROM forms WHERE form_key = ?");
$stmt->execute([$form_key]);
$form = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$form) { echo json_encode(['success' => false]); exit; }
if ($submission_id > 0) {
    $stmt = $pdo->prepare("UPDATE form_submissions SET form_data = ?, status = ?, updated_at = NOW() WHERE id = ? AND user_id = ?");
    $stmt->execute([json_encode($form_data), $status, $submission_id, $_SESSION['user_id']]);
} else {
    $stmt = $pdo->prepare("INSERT INTO form_submissions (user_id, form_id, form_data, status, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$_SESSION['user_id'], $form['id'], json_encode($form_data), $status]);
    $submission_id = $pdo->lastInsertId();
}
echo json_encode(['success' => true, 'submission_id' => $submission_id]);
?>
