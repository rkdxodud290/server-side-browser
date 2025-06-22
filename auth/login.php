<?php
// 1. Initialization
require_once '../config.php';
require_once 'session_config.php'; 
require_once 'db_connect.php'; 

// 2. Input Validation
$email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
$password = $_POST['password'] ?? '';

// Generic error redirect
function redirect_with_error($error_code) {
    header('Location: ../login.html?error=' . $error_code);
    exit;
}

if (!$email || empty($password)) {
    redirect_with_error('invalid_input');
}

// 3. Business Logic
try {
    $stmt = $pdo->prepare("SELECT id, password, is_verified, account_level FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        // Password is correct, check account status
        if ($user['is_verified'] == 0) redirect_with_error('not_verified');
        if ($user['account_level'] === 'pending_approval') redirect_with_error('pending_approval');
        if ($user['account_level'] === 'banned') redirect_with_error('banned');
        
        // 4. Success: Setup Session and Redirect
        regenerate_session(); // Secure the session
        $_SESSION['loggedin'] = true;
        $_SESSION['id'] = $user['id'];
        $_SESSION['email'] = $email;
        $_SESSION['account_level'] = $user['account_level'];
        
        header("Location: ../index.php");
        exit;

    } else {
        // User not found or password incorrect
        redirect_with_error('auth_failed');
    }

} catch (PDOException $e) {
    // Log the real error, show a generic one
    error_log("Login DB Error: " . $e->getMessage()); 
    redirect_with_error('server_error');
}
?>
