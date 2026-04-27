<?php
// admin/users.php
header("Content-Type: application/json");
session_start();
require_once '../config/db.php';
require_once 'functions.php';
require_once '../utils/search_sort.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? 'list';

try {
    if ($action === 'list') {
        $username = isset($_GET['username']) ? trim($_GET['username']) : '';

        $stmt = $pdo->query("SELECT id, username, email, role, is_verified, timeout_until FROM users WHERE role != 'admin'");
        $users = $stmt->fetchAll();

        if ($username !== '') {
            $users = rz_linear_search_field($users, 'username', $username);
        }

        $users = rz_merge_sort($users, 'username', 'asc', 'string');
        echo json_encode(array_values($users));

    } elseif ($action === 'timeout') {
        $data = json_decode(file_get_contents("php://input"), true);
        if (!isset($data['id'])) {
            throw new Exception("Missing user ID");
        }

        $target_id = $data['id'];

        $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
        $stmt->execute([$target_id]);
        $target_username = $stmt->fetchColumn();

        $stmt = $pdo->prepare("UPDATE users SET timeout_until = DATE_ADD(NOW(), INTERVAL 24 HOUR) WHERE id = ?");
        $stmt->execute([$target_id]);

        $stmt = $pdo->prepare("SELECT timeout_until FROM users WHERE id = ?");
        $stmt->execute([$target_id]);
        $new_timeout = $stmt->fetchColumn();

        logAdminAction($pdo, $user_id, "Timeout user $target_username (ID: $target_id) until $new_timeout (24 hours)");

        echo json_encode(['message' => "User suspended for 24 hours (until $new_timeout)"]);

    } elseif ($action === 'delete') {
        $data = json_decode(file_get_contents("php://input"), true);
        $target_id = $data['id'];

        $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
        $stmt->execute([$target_id]);
        $target_username = $stmt->fetchColumn();

        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$target_id]);

        logAdminAction($pdo, $user_id, "Deleted user $target_username (ID: $target_id)");

        echo json_encode(['message' => 'User deleted']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
