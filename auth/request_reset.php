<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once '../vendor/autoload.php';
require_once '../config.php';
require_once 'db_connect.php';

header('Content-Type: application/json');

$email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);

if (!$email) {
    echo json_encode(['success' => false, 'error' => 'Invalid email address provided.']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        // User exists, generate token
        $token = bin2hex(random_bytes(32));
        $token_hash = hash('sha256', $token);
        $expiry_date = (new DateTime())
            ->add(new DateInterval('PT' . PASSWORD_RESET_EXPIRY_MINUTES . 'M'))
            ->format('Y-m-d H:i:s');

        $update_stmt = $pdo->prepare("UPDATE users SET password_reset_token = ?, password_reset_expires = ? WHERE id = ?");
        $update_stmt->execute([$token_hash, $expiry_date, $user['id']]);

        // Send the email
        $reset_link = APP_BASE_URL . "/reset_password.html?token=$token";
        
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        $mail->setFrom(APP_FROM_EMAIL, APP_NAME);
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = 'Password Reset Request for ' . APP_NAME;
        $mail->Body = "You requested a password reset. Click the link below to set a new password. This link is valid for " . PASSWORD_RESET_EXPIRY_MINUTES . " minutes:<br><br><a href='$reset_link'>$reset_link</a>";
        $mail->send();
    }
    
    // Always return a success message to prevent user enumeration
    echo json_encode(['success' => true, 'message' => 'If an account with that email exists, we have sent instructions to reset your password.']);

} catch (Exception $e) {
    error_log("Password Reset Request Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'An error occurred while trying to send the email.']);
}
?>
