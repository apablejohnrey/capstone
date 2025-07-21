<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['official', 'tanod'])) {
    header("Location: ../authentication/loginform.php");
    exit();
}

$db = new Database();
$conn = $db->connect();

$incident_id = $_GET['id'] ?? 0;
$incident_id = (int)$incident_id;

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $notes = $_POST['notes'];
    $user_id = $_SESSION['user_id'];

    // Update incident
    $stmt = $conn->prepare("UPDATE incident_reports SET verified_by = ?, verified_datetime = NOW(), status = 'in progress' WHERE incident_id = ?");
    $stmt->execute([$user_id, $incident_id]);

    // Save the verification note
    $stmt = $conn->prepare("INSERT INTO incident_verification_notes (incident_id, verified_by, notes) VALUES (?, ?, ?)");
    $stmt->execute([$incident_id, $user_id, $notes]);

    header("Location: verify.php?verified=success");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Verify Incident</title>
</head>
<body>
    <h2>Verify Incident #<?= htmlspecialchars($incident_id) ?></h2>

    <form method="post">
        <label for="notes">Verification Notes:</label><br>
        <textarea name="notes" id="notes" rows="5" cols="50" required></textarea><br><br>
        <button type="submit">Submit Verification</button>
        <a href="monitor_incidents.php">Cancel</a>
    </form>
</body>
</html>
