<?php
// admin/logs.php
header("Content-Type: application/json");
session_start();
require_once '../config/db.php';
require_once '../utils/search_sort.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

try {
    $stmt = $pdo->query("SELECT al.*, u.username FROM admin_logs al JOIN users u ON al.admin_id = u.id");
    $logs = $stmt->fetchAll();
    $logs = rz_merge_sort($logs, 'created_at', 'desc', 'datetime');
    echo json_encode(array_values($logs));
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
