<?php
require_once '../config.php';
require_once 'db_connect.php';

header('Content-Type: application/json');

$token = $_POST['token'] ?? '';
$password = $_POST['password'] ?? '';

if (empty($token) || empty($password) || strlen($password) < 8) {
    echo json_encode(['success' => false, 'error' => 'Invalid data. Token is missing or password is too short.']);
    exit;
}

$token_hash = hash('sha256', $token);

try {
    $stmt = $pdo->prepare("SELECT id, password_reset_expires FROM users WHERE password_reset_token = ?");
    $stmt->execute([$token_hash]);
    $user = $stmt->fetch();

    if (!$user) {
        echo json_encode(['success' => false, 'error' => 'Invalid or expired token.']);
        exit;
    }

    $expiry_date = new DateTime($user['password_reset_expires']);
    if ($expiry_date < new DateTime()) {
        echo json_encode(['success' => false, 'error' => 'This token has expired. Please request a new one.']);
        exit;
    }
    
    // Token is valid, update the password
    $new_password_hash = password_hash($password, PASSWORD_DEFAULT);
    $update_stmt = $pdo->prepare("UPDATE users SET password = ?, password_reset_token = NULL, password_reset_expires = NULL WHERE id = ?");
    $update_stmt->execute([$new_password_hash, $user['id']]);

    echo json_encode(['success' => true, 'message' => 'Your password has been updated successfully. You will be redirected to login.']);

} catch (PDOException $e) {
    error_log("Perform Reset Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'A server error occurred.']);
}
?>
