<?php
// reader/saved_books.php
header("Content-Type: application/json");
session_start();
require_once '../config/db.php';
require_once '../utils/search_sort.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $sql = "SELECT sb.*, b.title, b.author, b.cover_path, b.book_path
                FROM saved_books sb
                JOIN books b ON sb.book_id = b.id
                WHERE sb.user_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id]);
        $saved = $stmt->fetchAll();

        $saved = rz_merge_sort($saved, 'title', 'asc', 'string');

        header("Cache-Control: no-cache, must-revalidate");
        echo json_encode(array_values($saved));
    } elseif ($method === 'POST') {
        $input = file_get_contents("php://input");
        $data = json_decode($input, true);
        $action = $data['action'] ?? ($_GET['action'] ?? 'add');

        if ($action === 'add') {
            if (!isset($data['book_id'])) {
                throw new Exception("book_id required");
            }
            $book_id = $data['book_id'];

            $sql = "INSERT IGNORE INTO saved_books (user_id, book_id, last_page, progress_percentage, completed)
                     VALUES (?, ?, 1, 0, 0)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$user_id, $book_id]);

            echo json_encode(['message' => 'Book saved to library']);
        } elseif ($action === 'remove') {
            if (!isset($data['book_id'])) {
                throw new Exception("book_id required");
            }
            $book_id = $data['book_id'];

            $stmt = $pdo->prepare("DELETE FROM saved_books WHERE user_id = ? AND book_id = ?");
            $stmt->execute([$user_id, $book_id]);

            $stmt = $pdo->prepare("DELETE FROM list_books WHERE book_id = ? AND list_id IN (SELECT id FROM custom_lists WHERE user_id = ?)");
            $stmt->execute([$book_id, $user_id]);

            echo json_encode(['message' => 'Book permanently removed from library']);
        }
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
