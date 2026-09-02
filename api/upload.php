<?php
// api/upload.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';
require_once '../config/auth.php';

// Cek login
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$form_type = $input['form_type'] ?? '';
$form_data = $input['data'] ?? [];
$photos = $input['photos'] ?? [];
$pdf_base64 = $input['pdf'] ?? '';

if (empty($form_type) || empty($pdf_base64)) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Get form ID
$stmt = $pdo->prepare("SELECT id FROM forms WHERE form_key = ?");
$stmt->execute([$form_type]);
$form = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$form) {
    echo json_encode(['success' => false, 'message' => 'Form not found']);
    exit;
}

// Save PDF
$upload_dir = '../uploads/pdfs/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

$filename = $form_type . '_' . date('Ymd_His') . '_' . uniqid() . '.pdf';
$filepath = $upload_dir . $filename;

// Decode base64 and save
$pdf_data = base64_decode(preg_replace('#^data:application/pdf;base64,#', '', $pdf_base64));
file_put_contents($filepath, $pdf_data);

// Save to database
$stmt = $pdo->prepare("INSERT INTO form_submissions 
    (user_id, form_id, pdf_path, form_data, status, submitted_at) 
    VALUES (?, ?, ?, ?, 'submitted', NOW())");

$stmt->execute([
    $_SESSION['user_id'],
    $form['id'],
    'uploads/pdfs/' . $filename,
    json_encode($form_data)
]);

$submission_id = $pdo->lastInsertId();

// Log activity
logAction($_SESSION['user_id'], "Submit form: $form_type (ID: $submission_id)");

// Create notification for admin
$stmt = $pdo->prepare("INSERT INTO notifications (user_id, submission_id, title, message, type) 
                       SELECT id, ?, 'New Form Submission', ?, 'submitted' 
                       FROM users WHERE role = 'admin'");
$stmt->execute([$submission_id, "Form $form_type submitted by " . $_SESSION['fullname']]);

echo json_encode([
    'success' => true,
    'submission_id' => $submission_id,
    'message' => 'Form submitted successfully'
]);
?>