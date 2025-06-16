<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: loginform.php");
    exit();
}

require_once '../classes/history.php';
$incidentHandler = new ReportHistory();
$reports = $incidentHandler->getUserIncidentReports($_SESSION['user_id']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Incident History</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
    <h2 class="mb-4">My Incident Report History</h2>

    <?php if (count($reports) > 0): ?>
        <table class="table table-bordered table-hover">
            <thead class="table-light">
                <tr>
                    <th>Date & Time</th>
                    <th>Category</th>
                    <th>Urgency</th>
                    <th>Location</th>
                    <th>Landmark</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($reports as $report): ?>
                <tr>
                    <td><?= htmlspecialchars($report['incident_datetime']) ?></td>
                    <td><?= htmlspecialchars($report['category_name']) ?></td>
                    <td><?= htmlspecialchars($report['urgency']) ?></td>
                    <td>Purok <?= htmlspecialchars($report['purok']) ?></td>
                    <td><?= htmlspecialchars($report['landmark']) ?></td>
                    <td><?= htmlspecialchars($report['status'] ?? 'Pending') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="alert alert-info">You haven't submitted any reports yet.</div>
    <?php endif; ?>
</div>
</body>
</html>
