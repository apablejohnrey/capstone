<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../classes/AccountStatusManager.php';

if (!isset($_SESSION)) session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'official') {
    http_response_code(403);
    exit('Unauthorized');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = new Database();
        $conn = $db->connect();

        $targetUserId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
        $password = $_POST['password'] ?? '';

        if ($targetUserId <= 0 || empty($password)) {
            http_response_code(400);
            echo 'Invalid input';
            exit;
        }

        $manager = new AccountStatusManager($conn);
        $result = $manager->toggleStatus($targetUserId, $password, $_SESSION['user_id']);

        echo $result ? 'success' : 'failed';
    } catch (Exception $e) {
        error_log("Error in toggle_status.php: " . $e->getMessage());
        http_response_code(500);
        echo 'error';
    }
}
