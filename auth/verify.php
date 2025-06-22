<?php
require_once '../config.php';
require_once 'db_connect.php'; 

// Sanitize inputs
$email = filter_input(INPUT_GET, 'email', FILTER_VALIDATE_EMAIL);
$code = $_GET['code'] ?? '';

// Use a generic message to prevent leaking information
$generic_error_message = "<!DOCTYPE html><html><body style='font-family: sans-serif; text-align: center; padding-top: 5em;'><h2>Invalid or expired verification link.</h2><p><a href='../login.html'>Back to Login</a></p></body></html>";

if (!$email || empty($code)) {
    die($generic_error_message);
}

try {
    // Prepare and execute the query using PDO
    $stmt = $pdo->prepare("SELECT id, verification_code, is_verified FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        if ($user['is_verified']) {
            die("<!DOCTYPE html><html><body style='font-family: sans-serif; text-align: center; padding-top: 5em;'><h2>This account has already been verified and is awaiting admin approval.</h2><p><a href='../login.html'>Back to Login</a></p></body></html>");
        }

        // Use hash_equals for timing-attack-safe string comparison
        if ($user['verification_code'] && hash_equals($user['verification_code'], $code)) {
            // Verification successful, update the user and nullify the code
            $update_stmt = $pdo->prepare("UPDATE users SET is_verified = 1, verification_code = NULL WHERE id = ?");
            $update_stmt->execute([$user['id']]);

            die("<!DOCTYPE html><html><body style='font-family: sans-serif; text-align: center; padding-top: 5em;'><h2>Email verified successfully!</h2><p>Your account is now awaiting admin approval. You will be notified when it's active.</p><p><a href='../login.html'>Back to Login</a></p></body></html>");
        } else {
            // Code does not match
            die($generic_error_message);
        }
    } else {
        // No user found with that email
        die($generic_error_message);
    }
} catch (PDOException $e) {
    error_log("Verification Error: " . $e->getMessage());
    die("<!DOCTYPE html><html><body style='font-family: sans-serif; text-align: center; padding-top: 5em;'><h2>A server error occurred.</h2><p>Please try again later.</p></body></html>");
}
?>
