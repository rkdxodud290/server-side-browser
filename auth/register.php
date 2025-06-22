<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once '../vendor/autoload.php';
require_once '../config.php';
require_once 'db_connect.php';

$email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
$password = $_POST['password'] ?? '';

if (!$email || empty($password) || strlen($password) < 8) {
    die("Invalid input. Email must be valid and password must be at least 8 characters.");
}

try {
    // Check if email already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        die("This email address is already registered.");
    }
    
    // Insert new user
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $verification_code = bin2hex(random_bytes(32));

    $stmt = $pdo->prepare("INSERT INTO users (email, password, verification_code) VALUES (?, ?, ?)");
    $stmt->execute([$email, $hashed_password, $verification_code]);
    
    // Send verification email
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USER;
    $mail->Password   = SMTP_PASS;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = SMTP_PORT;

    $mail->setFrom(APP_FROM_EMAIL, APP_NAME);
    $mail->addAddress($email);

    $verification_link = APP_BASE_URL . "/auth/verify.php?email=$email&code=$verification_code";

    $mail->isHTML(true);
    $mail->Subject = 'Verify Your Email for ' . APP_NAME;
    $mail->Body    = "Thank you for registering! Please click the following link to verify your email address:<br><br><a href='$verification_link'>$verification_link</a>";
    $mail->AltBody = "Thank you for registering! Please copy and paste this link into your browser to verify your email: $verification_link";

    $mail->send();
    header("Location: ../verify.html?email=" . urlencode($email));
    exit;

} catch (PDOException $e) {
    error_log("Registration DB Error: " . $e->getMessage());
    die("A database error occurred. Please try again later.");
} catch (Exception $e) {
    error_log("Mailer Error: {$mail->ErrorInfo}");
    die("Could not send verification email. Please check the email address and try again.");
}
?>
