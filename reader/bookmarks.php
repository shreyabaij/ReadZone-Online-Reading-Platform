<?php
// reader/bookmarks.php
header("Content-Type: application/json");
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $book_id = isset($_GET['book_id']) ? $_GET['book_id'] : null;

        $sql = "SELECT * FROM bookmarks WHERE user_id = ?";
        $params = [$user_id];

        if ($book_id) {
            $sql .= " AND book_id = ?";
            $params[] = $book_id;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        echo json_encode($stmt->fetchAll());

    } elseif ($method === 'POST') {
        $data = json_decode(file_get_contents("php://input"), true);
        if (!isset($data['book_id'], $data['page_number'], $data['title'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing fields']);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO bookmarks (user_id, book_id, page_number, title, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$user_id, $data['book_id'], $data['page_number'], $data['title']]); // title can be bookmark name/note

        echo json_encode(['message' => 'Bookmark added', 'id' => $pdo->lastInsertId()]);

    } elseif ($method === 'DELETE') {
        $id = isset($_GET['id']) ? $_GET['id'] : null;
        if (!$id) {
            $data = json_decode(file_get_contents("php://input"), true);
            $id = $data['id'] ?? null;
        }

        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID required']);
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM bookmarks WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $user_id]);

        echo json_encode(['message' => 'Bookmark deleted']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>