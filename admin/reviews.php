<?php
// admin/reviews.php
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
$action = $_GET['action'] ?? ($_POST['action'] ?? 'list');

try {
    if ($action === 'list_reported') {
        $sql = "SELECT rr.*, r.review_text, r.book_id, u.username as reported_by_username,
                       b.title as book_title, reviewer.username as reviewer_username
                FROM review_reports rr
                JOIN reviews r ON rr.review_id = r.id
                JOIN users u ON rr.reported_by = u.id
                JOIN books b ON r.book_id = b.id
                JOIN users reviewer ON r.user_id = reviewer.id";
        $stmt = $pdo->query($sql);
        $reports = $stmt->fetchAll();
        $reports = rz_merge_sort($reports, 'created_at', 'desc', 'datetime');
        echo json_encode(array_values($reports));
    } elseif ($action === 'approve') {
        $data = json_decode(file_get_contents("php://input"), true);
        $review_id = $data['review_id'];

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("DELETE FROM review_reports WHERE review_id = ?");
        $stmt->execute([$review_id]);

        $stmt = $pdo->prepare("UPDATE reviews SET is_reported = 0 WHERE id = ?");
        $stmt->execute([$review_id]);

        $pdo->commit();

        $stmt = $pdo->prepare("SELECT u.username, b.title FROM reviews r JOIN users u ON r.user_id = u.id JOIN books b ON r.book_id = b.id WHERE r.id = ?");
        $stmt->execute([$review_id]);
        $info = $stmt->fetch();
        $detail = $info ? "Review by " . $info['username'] . " on " . $info['title'] : "ID $review_id";

        logAdminAction($pdo, $user_id, "Approved review: $detail (ID: $review_id)");

        echo json_encode(['message' => 'Report cleared']);
    } elseif ($action === 'delete') {
        $data = json_decode(file_get_contents("php://input"), true);
        $review_id = $data['review_id'];

        $stmt = $pdo->prepare("SELECT u.username, b.title FROM reviews r JOIN users u ON r.user_id = u.id JOIN books b ON r.book_id = b.id WHERE r.id = ?");
        $stmt->execute([$review_id]);
        $info = $stmt->fetch();
        $detail = $info ? "Review by " . $info['username'] . " on " . $info['title'] : "ID $review_id";

        $stmt = $pdo->prepare("DELETE FROM reviews WHERE id = ?");
        $stmt->execute([$review_id]);

        logAdminAction($pdo, $user_id, "Deleted review: $detail (ID: $review_id)");

        echo json_encode(['message' => 'Review deleted']);
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
