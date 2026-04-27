<?php
// reader/lists.php
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
$data = json_decode(file_get_contents("php://input"), true);
$action = $_GET['action'] ?? ($data['action'] ?? 'list');

try {
    if ($action === 'create_list') {
        if (!isset($data['title'])) {
            throw new Exception("Title required");
        }
        $stmt = $pdo->prepare("INSERT INTO custom_lists (user_id, title) VALUES (?, ?)");
        $stmt->execute([$user_id, $data['title']]);
        echo json_encode(['message' => 'List created', 'id' => $pdo->lastInsertId()]);

    } elseif ($action === 'delete_list') {
        if (!isset($data['list_id'])) {
            throw new Exception("List ID required");
        }
        $stmt = $pdo->prepare("DELETE FROM custom_lists WHERE id = ? AND user_id = ?");
        $stmt->execute([$data['list_id'], $user_id]);

        $stmt = $pdo->prepare("DELETE FROM list_books WHERE list_id = ?");
        $stmt->execute([$data['list_id']]);

        echo json_encode(['message' => 'List deleted']);

    } elseif ($action === 'add_book') {
        if (!isset($data['list_id'], $data['book_id'])) {
            throw new Exception("Missing list_id or book_id");
        }
        $stmt = $pdo->prepare("SELECT id FROM custom_lists WHERE id = ? AND user_id = ?");
        $stmt->execute([$data['list_id'], $user_id]);
        if (!$stmt->fetch()) {
            throw new Exception("List not found");
        }

        $stmt = $pdo->prepare("INSERT INTO list_books (list_id, book_id) VALUES (?, ?)");
        $stmt->execute([$data['list_id'], $data['book_id']]);
        echo json_encode(['message' => 'Book added to list']);

    } elseif ($action === 'remove_book') {
        if (!isset($data['list_id'], $data['book_id'])) {
            throw new Exception("Missing list_id or book_id");
        }
        $stmt = $pdo->prepare("SELECT id FROM custom_lists WHERE id = ? AND user_id = ?");
        $stmt->execute([$data['list_id'], $user_id]);
        if (!$stmt->fetch()) {
            throw new Exception("List not found");
        }

        $stmt = $pdo->prepare("DELETE FROM list_books WHERE list_id = ? AND book_id = ?");
        $stmt->execute([$data['list_id'], $data['book_id']]);
        echo json_encode(['message' => 'Book removed from list']);

    } elseif ($action === 'get_lists') {
        $stmt = $pdo->prepare("SELECT * FROM custom_lists WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $lists = $stmt->fetchAll();
        $lists = rz_merge_sort($lists, 'title', 'asc', 'string');

        foreach ($lists as &$list) {
            $stmt = $pdo->prepare("SELECT b.* FROM list_books lb JOIN books b ON lb.book_id = b.id WHERE lb.list_id = ?");
            $stmt->execute([$list['id']]);
            $books = $stmt->fetchAll();
            $list['books'] = rz_merge_sort($books, 'title', 'asc', 'string');
        }
        unset($list);

        echo json_encode(array_values($lists));
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
