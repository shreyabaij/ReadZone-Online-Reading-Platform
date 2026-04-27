<?php
// reader/reviews.php
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
try {
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        $book_id = isset($_GET['book_id']) ? $_GET['book_id'] : null;
        if ($book_id) {
            $stmt = $pdo->prepare("SELECT r.*, u.username FROM reviews r JOIN users u ON r.user_id = u.id WHERE r.book_id = ?");
            $stmt->execute([$book_id]);
            $reviews = $stmt->fetchAll();
            $reviews = rz_merge_sort($reviews, 'created_at', 'desc', 'datetime');
            echo json_encode(array_values($reviews));
        } else {
            echo json_encode([]);
        }

    } elseif ($method === 'POST') {
        $action = isset($_GET['action']) ? $_GET['action'] : ($data['action'] ?? 'add');

        if ($action === 'add') {
            if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
                http_response_code(403);
                echo json_encode(['error' => 'Administrators cannot post reviews.']);
                exit;
            }

            if (!isset($data['book_id'], $data['review_text'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing fields']);
                exit;
            }

            $stmt = $pdo->prepare("INSERT INTO reviews (user_id, book_id, review_text, is_reported, created_at) VALUES (?, ?, ?, 0, NOW())");
            $stmt->execute([$user_id, $data['book_id'], $data['review_text']]);

            echo json_encode(['message' => 'Review added']);

        } elseif ($action === 'report') {
            if (!isset($data['review_id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Review ID required']);
                exit;
            }

            $review_id = $data['review_id'];
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO review_reports (review_id, reported_by, created_at) VALUES (?, ?, NOW())");
            $stmt->execute([$review_id, $user_id]);

            $stmt = $pdo->prepare("UPDATE reviews SET is_reported = 1 WHERE id = ?");
            $stmt->execute([$review_id]);

            $pdo->commit();
            echo json_encode(['message' => 'Review reported']);
        }
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
