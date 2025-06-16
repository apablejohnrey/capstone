<?php
require_once __DIR__ . '/../includes/SessionManager.php';

$session = new SessionManager();
$session->destroy();

// Decide redirect type based on how logout was triggered
if (isset($_GET['timeout'])) {
    header("Location: loginform.php?timeout=1");
} else {
    header("Location: loginform.php?logged_out=1");
}
exit();
