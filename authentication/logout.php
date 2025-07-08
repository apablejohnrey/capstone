<?php
require_once __DIR__ . '/../includes/SessionManager.php';

$session = new SessionManager();
$session->destroy();

if (isset($_GET['timeout'])) {
    header("Location: loginform.php?timeout=1");
} else {
    header("Location: loginform.php?logged_out=1");
}
exit();
