<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: loginform.php");
    exit();
}

require_once 'includes/IncidentHandler.php';

$handler = new IncidentHandler();
$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $success = $handler->insertIncident($_POST, $_FILES, $user_id);
    if ($success) {
    echo "<script>alert('Incident reported successfully!'); window.location.href = 'reportincident.php';</script>";
    exit();

    } else {
        echo "Failed to submit report. Please try again.";
    }
}
?>
