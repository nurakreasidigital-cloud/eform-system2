<?php
header('Content-Type: application/json');
require_once '../config/database.php';

require_once '../phpmailer/src/Exception.php';
require_once '../phpmailer/src/PHPMailer.php';
require_once '../phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$input = json_decode(file_get_contents('php://input'), true);
$userInput = trim($input['input'] ?? '');

if (empty($userInput)) {
    echo json_encode(['success' => false, 'message' => 'Username atau email tidak boleh kosong.']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
$stmt->execute([$userInput, $userInput]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'User tidak ditemukan.']);
    exit;
}

// ===== KONFIGURASI SMTP GMAIL =====
$adminEmail = 'nurakreasidigital@gmail.com';
$adminName = 'Admin E-Form';
$smtpHost = 'smtp.gmail.com';
$smtpUser = 'nurakreasidigital@gmail.com';
$smtpPass = 'wuzm vmek kmcq sgkt';

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host = $smtpHost;
    $mail->SMTPAuth = true;
    $mail->Username = $smtpUser;
    $mail->Password = $smtpPass;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    $mail->setFrom($smtpUser, 'E-Form System');
    $mail->addAddress($adminEmail, $adminName);

    $subject = '🔑 Permintaan Reset Password - E-Form System';
    $body = "
    <html>
    <head><style>
        body { font-family: Arial, sans-serif; color: #1A1A2E; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #DDE3F0; border-radius: 10px; }
        .header { background: #1A1A2E; color: #fff; padding: 15px; border-radius: 10px 10px 0 0; text-align: center; }
        .content { padding: 20px; }
        .info { background: #F8F9FC; padding: 10px 14px; border-radius: 8px; border-left: 4px solid #E53935; }
        .footer { text-align: center; color: #6B7A99; font-size: 11px; padding: 10px; border-top: 1px solid #DDE3F0; margin-top: 20px; }
    </style></head>
    <body>
        <div class='container'>
            <div class='header'><h2>📋 E-Form System</h2></div>
            <div class='content'>
                <h3>🔑 Permintaan Reset Password</h3>
                <p>Ada user yang meminta reset password:</p>
                <div class='info'>
                    <p><strong>Nama:</strong> {$user['fullname']}</p>
                    <p><strong>Username:</strong> {$user['username']}</p>
                    <p><strong>Email:</strong> {$user['email']}</p>
                    <p><strong>IP:</strong> {$_SERVER['REMOTE_ADDR'] ?? 'unknown'}</p>
                    <p><strong>Waktu:</strong> " . date('d/m/Y H:i:s') . "</p>
                </div>
                <p style='margin-top:16px;'>
                    <strong>Langkah selanjutnya:</strong>
                    <ol>
                        <li>Hubungi user untuk verifikasi identitas</li>
                        <li>Reset password user melalui <strong>Manajemen User</strong></li>
                        <li>Kirim password baru ke user</li>
                    </ol>
                </p>
            </div>
            <div class='footer'>
                E-Form System v1.0 &copy; 2026 PT. Nura Kreasi Digital<br>
                <small>Email ini dikirim otomatis oleh sistem.</small>
            </div>
        </div>
    </body>
    </html>
    ";

    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body = $body;
    $mail->AltBody = strip_tags(str_replace(['<br>', '</p>'], "\n", $body));
    $mail->send();

    $stmt = $pdo->prepare("INSERT INTO user_logs (user_id, action, ip_address) VALUES (?, 'Forgot Password Request (Email sent)', ?)");
    $stmt->execute([$user['id'], $_SERVER['REMOTE_ADDR'] ?? 'unknown']);

    echo json_encode([
        'success' => true,
        'message' => 'Permintaan reset password telah dikirim ke admin via email. Admin akan menghubungi Anda.'
    ]);

} catch (Exception $e) {
    $stmt = $pdo->prepare("INSERT INTO user_logs (user_id, action, ip_address) VALUES (?, 'Forgot Password Request (Email failed: ' . \$mail->ErrorInfo . ')', ?)");
    $stmt->execute([$user['id'], $_SERVER['REMOTE_ADDR'] ?? 'unknown']);

    echo json_encode([
        'success' => true,
        'message' => 'Permintaan reset password telah dicatat. Admin akan menghubungi Anda.'
    ]);
}
?>
