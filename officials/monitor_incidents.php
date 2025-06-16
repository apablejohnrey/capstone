<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
include 'IncidentMonitor.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['official', 'tanod'])) {
    header("Location: ../authentication/loginform.php");
    exit();
}

$db = new Database();
$conn = $db->connect();
$incidentReport = new IncidentReport($conn);
$incidents = $incidentReport->getAllIncidents();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Monitor Incidents</title>
    <link rel="stylesheet" href="../css/navofficial.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <style>
        .action-btns a {
            margin-right: 8px;
        }
        .table td, .table th {
            vertical-align: middle;
        }
        .leaflet-container {
            height: 200px;
            width: 100%;
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <div class="col-md-3">
            <?php include 'navofficial.php'; ?>
        </div>

        <div class="col-md-9 p-3">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2>Monitor Incidents</h2>
                <a href="../reportincident.php" class="btn btn-primary">
                    <i class="fas fa-plus-circle"></i> Report Incident
                </a>
                <a href="generate_incidentmap.php" class="btn btn-secondary">
                    <i class="fas fa-map-marker-alt"></i> View Incident Map
                </a>
            </div>

            <?php if (count($incidents) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-bordered">
                        <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Type</th>
                            <th>Description</th>
                            <th>Reported By</th>
                            <th>Evidence</th>
                            <th>Map</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Verified By</th>
                            <th>Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($incidents as $index => $incident): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td>
                                    <?= htmlspecialchars($incident['category_name']) ?><br>
                                    <span class="badge 
                                        <?= $incident['urgency_level'] === 'High' ? 'bg-danger' : 
                                            ($incident['urgency_level'] === 'Medium' ? 'bg-warning' : 'bg-success') ?>">
                                        <?= htmlspecialchars($incident['urgency_level']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($incident['details']) ?></td>
                                <td><?= htmlspecialchars($incident['reporter_name']) ?></td>
                                <td><?= $incident['evidence_count'] ?> file(s)</td>
                                <td>
                                    <div id="map_<?= $incident['incident_id'] ?>" class="leaflet-container"></div>
                                    <p><strong>Landmark:</strong> <?= $incident['landmark'] ?: 'N/A' ?></p>
                                </td>
                                <td><?= date('M d, Y h:i A', strtotime($incident['reported_datetime'])) ?></td>
                                <td>
                                    <span class="badge 
                                        <?= $incident['status'] === 'resolved' ? 'bg-success' : 
                                            ($incident['status'] === 'in progress' ? 'bg-warning' : 'bg-secondary') ?>">
                                        <?= ucfirst($incident['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?= $incident['verified_by'] 
                                        ? htmlspecialchars($incident['verified_by']) . ' (' . $incident['verifier_type'] . ')' 
                                        : '—' ?>
                                </td>
                                <td class="action-btns text-center">
                                <a href="view_incident.php?id=<?= $incident['incident_id'] ?>" class="text-info me-2" title="View">
                                    <i class="fas fa-eye fa-lg"></i>
                                </a>
                                <?php if ($_SESSION['user_type'] === 'official' || $_SESSION['user_type'] === 'tanod'): ?>
                                    <a href="verify_incident.php?id=<?= $incident['incident_id'] ?>" class="text-success me-2" title="Verify">
                                        <i class="fas fa-check-circle fa-lg"></i>
                                    </a>
                                <?php endif; ?>
                                <a href="delete_incident.php?id=<?= $incident['incident_id'] ?>" class="text-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this incident?')">
                                    <i class="fas fa-trash fa-lg"></i>
                                </a>
                            </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">No incidents reported yet.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        <?php foreach ($incidents as $incident): ?>
        var map<?= $incident['incident_id'] ?> = L.map('map_<?= $incident['incident_id'] ?>').setView([<?= $incident['latitude'] ?>, <?= $incident['longitude'] ?>], 15);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map<?= $incident['incident_id'] ?>);

        var marker = L.marker([<?= $incident['latitude'] ?>, <?= $incident['longitude'] ?>]).addTo(map<?= $incident['incident_id'] ?>)
            .bindPopup('<strong><?= htmlspecialchars($incident['category_name']) ?></strong>')
            .openPopup();
        <?php endforeach; ?>
    });
</script>
</body>
</html>
