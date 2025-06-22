<?php
require_once 'session_config.php';
require_once 'db_connect.php';
header('Content-Type: application/json');

if (!isset($_SESSION['loggedin']) || $_SESSION['account_level'] !== 'admin') {
    echo json_encode(['error' => 'Access Denied.', 'action' => 'redirect']);
    exit;
}

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_pending':
            $stmt = $pdo->prepare("SELECT id, email, reg_date FROM users WHERE account_level = 'pending_approval' AND is_verified = 1 ORDER BY reg_date ASC");
            $stmt->execute();
            echo json_encode($stmt->fetchAll());
            break;

        case 'get_upgrade_requests':
            $stmt = $pdo->prepare("SELECT id, email, reg_date FROM users WHERE account_level = 'pending_upgrade' ORDER BY reg_date ASC");
            $stmt->execute();
            echo json_encode($stmt->fetchAll());
            break;

        case 'update_level':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('Invalid request method.');
            
            $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
            $new_level = $_POST['new_level'] ?? '';
            
            $allowed_levels = ['free_user', 'paid_user', 'banned'];
            if (!$user_id || !in_array($new_level, $allowed_levels)) {
                throw new Exception('Invalid user ID or account level specified.');
            }

            $stmt = $pdo->prepare("UPDATE users SET account_level = ? WHERE id = ?");
            $stmt->execute([$new_level, $user_id]);
            echo json_encode(['success' => true]);
            break;

        default:
            throw new Exception('Invalid action specified.');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
