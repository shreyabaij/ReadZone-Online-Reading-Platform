<?php
// reader/progress.php
header("Content-Type: application/json");
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['book_id'], $data['last_page'], $data['progress_percentage'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing fields']);
    exit;
}

$book_id = $data['book_id'];
$last_page = $data['last_page'];
$progress = $data['progress_percentage'];
$completed = ($progress >= 100) ? 1 : 0; // Simple logic

try {
    // Check if record exists
    $stmt = $pdo->prepare("SELECT id FROM saved_books WHERE user_id = ? AND book_id = ?");
    $stmt->execute([$user_id, $book_id]);

    if ($stmt->fetch()) {
        // Exists, update it
        $stmt = $pdo->prepare("UPDATE saved_books SET last_page = ?, progress_percentage = ?, completed = ? WHERE user_id = ? AND book_id = ?");
        $stmt->execute([$last_page, $progress, $completed, $user_id, $book_id]);
    } else {
        // New record
        $stmt = $pdo->prepare("INSERT INTO saved_books (user_id, book_id, last_page, progress_percentage, completed) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $book_id, $last_page, $progress, $completed]);
    }

    echo json_encode(['message' => 'Progress updated']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
