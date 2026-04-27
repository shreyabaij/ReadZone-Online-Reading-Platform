<?php
// reader/books.php
header("Content-Type: application/json");
require_once '../config/db.php';
require_once '../utils/search_sort.php';

$title = isset($_GET['title']) ? trim($_GET['title']) : '';
$author = isset($_GET['author']) ? trim($_GET['author']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';

try {
    $stmt = $pdo->query("SELECT id, title, description, author, categories, cover_path, book_path, created_at FROM books");
    $books = $stmt->fetchAll();

    if (isset($_GET['id'])) {
        $book = rz_linear_search_exact($books, 'id', $_GET['id']);
        $books = $book ? [$book] : [];
    } else {
        if ($title !== '') {
            $books = rz_linear_search_multi($books, ['title', 'author'], $title);
        }

        if ($author !== '') {
            $books = rz_linear_search_field($books, 'author', $author);
        }

        if ($category !== '') {
            $books = rz_linear_search_field($books, 'categories', $category);
        }
    }

    $books = rz_merge_sort($books, 'created_at', 'desc', 'datetime');

    echo json_encode(['books' => array_values($books)]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch books: ' . $e->getMessage()]);
}
?>
