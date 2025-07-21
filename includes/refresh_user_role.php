<?php
if (!isset($_SESSION)) session_start();

require_once __DIR__ . '/../includes/db.php';

function refreshUserRole(): void {
    if (!isset($_SESSION['user_id'])) return;

    $db = new Database();
    $conn = $db->connect();

    $stmt = $conn->prepare("SELECT role FROM Users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && isset($user['role'])) {
        $_SESSION['user_type'] = $user['role'];
    }
}
