<?php
// admin/functions.php

function logAdminAction($pdo, $admin_id, $action)
{
    try {
        $stmt = $pdo->prepare("INSERT INTO admin_logs (admin_id, action, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$admin_id, $action]);
    } catch (Exception $e) {
        // Silently fail logging or handle error
    }
}
?>