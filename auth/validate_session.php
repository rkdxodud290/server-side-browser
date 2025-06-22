<?php
require_once 'session_config.php';
header('Content-Type: application/json');

$session_id = filter_input(INPUT_GET, 'sessionId', FILTER_SANITIZE_STRING);

if (empty($session_id)) {
    http_response_code(400);
    echo json_encode(['error' => 'Session ID is missing.']);
    exit;
}

if (session_id() !== $session_id) {
    session_destroy();
    session_id($session_id);
    session_start();
}


if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    echo json_encode([
        'status' => 'authenticated',
        'userId' => $_SESSION['id'],
        'email' => $_SESSION['email'],
        'accountLevel' => $_SESSION['account_level']
    ]);
} else {
    http_response_code(401);
    echo json_encode(['error' => 'Session not authenticated.']);
}
?>
