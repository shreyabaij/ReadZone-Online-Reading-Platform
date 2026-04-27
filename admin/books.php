<?php
// admin/books.php
ini_set('display_errors', 0);
header("Content-Type: application/json");
session_start();
require_once '../config/db.php';
require_once 'functions.php'; // logging recorded in functions.php for reuse

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$user_id = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'POST') {
        // Handle JSON or Form Data
        $inputJSON = file_get_contents('php://input');
        $input = json_decode($inputJSON, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($input)) {
            // It's a JSON request from api call
            $action = $input['action'] ?? 'add';
            $_POST = array_merge($_POST, $input); // Merging into POST for compatibility
        } else {
            $action = $_POST['action'] ?? 'add';
        }

        if ($action === 'add') {
            if (!isset($_POST['title'], $_FILES['book_file'], $_FILES['cover_file'])) {
                throw new Exception("Missing fields or files");
            }

            $title = $_POST['title'];
            $author = $_POST['author'] ?? 'Unknown';
            $desc = $_POST['description'] ?? '';
            $categories = $_POST['categories'] ?? '';

            // uploading book file
            $bookPath = '../uploads/books/' . basename($_FILES['book_file']['name']);
            move_uploaded_file($_FILES['book_file']['tmp_name'], $bookPath);
            $dbBookPath = 'uploads/books/' . basename($_FILES['book_file']['name']);

            // uploading cover file
            $coverPath = '../uploads/covers/' . basename($_FILES['cover_file']['name']);
            move_uploaded_file($_FILES['cover_file']['tmp_name'], $coverPath);
            $dbCoverPath = 'uploads/covers/' . basename($_FILES['cover_file']['name']);

            $stmt = $pdo->prepare("INSERT INTO books (title, description, author, categories, book_path, cover_path, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$title, $desc, $author, $categories, $dbBookPath, $dbCoverPath]);

            logAdminAction($pdo, $user_id, "Added book: $title");
            echo json_encode(['message' => 'Book added successfully']);
        } elseif ($action === 'update') {
            if (!isset($_POST['id']))
                throw new Exception("Book ID required");
            $id = $_POST['id'];
            $title = $_POST['title'];
            $author = $_POST['author'];
            $desc = $_POST['description'];
            $categories = $_POST['categories'];

            // build query params
            $params = [$title, $author, $desc, $categories];
            $sql = "UPDATE books SET title = ?, author = ?, description = ?, categories = ?";

            // Optional Files for update only if provided
            if (isset($_FILES['book_file']) && $_FILES['book_file']['error'] === UPLOAD_ERR_OK) {
                $bookPath = '../uploads/books/' . basename($_FILES['book_file']['name']);
                move_uploaded_file($_FILES['book_file']['tmp_name'], $bookPath);
                $sql .= ", book_path = ?";
                $params[] = 'uploads/books/' . basename($_FILES['book_file']['name']);
            }

            if (isset($_FILES['cover_file']) && $_FILES['cover_file']['error'] === UPLOAD_ERR_OK) {
                $coverPath = '../uploads/covers/' . basename($_FILES['cover_file']['name']);
                move_uploaded_file($_FILES['cover_file']['tmp_name'], $coverPath);
                $sql .= ", cover_path = ?";
                $params[] = 'uploads/covers/' . basename($_FILES['cover_file']['name']);
            }

            $sql .= " WHERE id = ?";
            $params[] = $id;

            $stmt = $pdo->prepare("SELECT title FROM books WHERE id = ?");
            $stmt->execute([$id]);
            $book_title = $stmt->fetchColumn();

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            logAdminAction($pdo, $user_id, "Updated book: $book_title (ID: $id)");

            echo json_encode(['message' => 'Book updated']);
        } elseif ($action === 'delete') {
            if (!isset($_POST['id'])) {
                throw new Exception("Book ID required");
            }
            $id = $_POST['id'];

            $stmt = $pdo->prepare("SELECT title FROM books WHERE id = ?");
            $stmt->execute([$id]);
            $book_title = $stmt->fetchColumn();

            $stmt = $pdo->prepare("DELETE FROM books WHERE id = ?");
            $stmt->execute([$id]);
            logAdminAction($pdo, $user_id, "Deleted book: $book_title (ID: $id)");

            echo json_encode(['message' => 'Book deleted']);
        }
    } elseif ($method === 'DELETE') {
        // RESTful delete
        $id = $_GET['id'];
        $stmt = $pdo->prepare("SELECT title FROM books WHERE id = ?");
        $stmt->execute([$id]);
        $book_title = $stmt->fetchColumn();

        $stmt = $pdo->prepare("DELETE FROM books WHERE id = ?");
        $stmt->execute([$id]);
        logAdminAction($pdo, $user_id, "Deleted book: $book_title (ID: $id)");

        echo json_encode(['message' => 'Book deleted']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
