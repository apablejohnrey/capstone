<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: loginform.php");
    exit();
}

require_once '../includes/db.php';
require_once '../classes/IncidentView.php';

$incident_id = $_GET['incident_id'] ?? $_GET['id'] ?? null;

if (!$incident_id) {
    echo "Missing incident ID.";
    exit();
}

$db = new Database();
$conn = $db->connect();

$incidentViewer = new IncidentView($conn);

$incident = $incidentViewer->getIncidentDetails($incident_id);
$victims = $incidentViewer->getVictims($incident_id);
$perpetrators = $incidentViewer->getPerpetrators($incident_id);
$witnesses = $incidentViewer->getWitnesses($incident_id);
$evidence = $incidentViewer->getEvidence($incident_id);

if (!$incident) {
    echo "Incident not found.";
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>View Incident</title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <style>
        #map { height: 300px; margin-bottom: 20px; }
    </style>
</head>
<body>
<div class="container mt-5">
    <h2>Incident Details</h2>
    <p><strong>Category:</strong> <?= htmlspecialchars($incident['category_name']) ?></p>
    <p><strong>Reported by:</strong> <?= htmlspecialchars($incident['username']) ?></p>
    <p><strong>Date & Time:</strong> <?= $incident['incident_datetime'] ?></p>
    <p><strong>Urgency:</strong> <?= $incident['urgency_level'] ?></p>
    <p><strong>Purok:</strong> <?= $incident['purok'] ?></p>
    <p><strong>Landmark:</strong> <?= $incident['landmark'] ?></p>
    <p><strong>Details:</strong> <?= nl2br(htmlspecialchars($incident['details'])) ?></p>

    <!-- ✅ Map -->
    <h4>Location</h4>
    <div id="map"></div>

    <h4>Victims</h4>
    <ul>
        <?php foreach ($victims as $v): ?>
            <li><?= htmlspecialchars($v['name']) ?> (<?= $v['age'] ?> y/o)</li>
        <?php endforeach; ?>
    </ul>

    <h4>Perpetrators</h4>
    <ul>
        <?php foreach ($perpetrators as $p): ?>
            <li><?= htmlspecialchars($p['name']) ?> (<?= $p['age'] ?> y/o)</li>
        <?php endforeach; ?>
    </ul>

    <h4>Witnesses</h4>
    <ul>
        <?php foreach ($witnesses as $w): ?>
            <li><?= htmlspecialchars($w['name']) ?> - <?= $w['contact_number'] ?></li>
        <?php endforeach; ?>
    </ul>

    <h4>Evidence</h4>
    <div class="row">
        <?php foreach ($evidence as $e): ?>
            <div class="col-md-4 mb-3">
                <?php if (strtolower($e['file_type']) === 'image'): ?>
                    <img src="../<?= htmlspecialchars($e['file_path']) ?>" class="img-fluid border">
                <?php elseif (strtolower($e['file_type']) === 'video'): ?>
                    <video class="w-100 border" controls>
                        <source src="../<?= htmlspecialchars($e['file_path']) ?>" type="video/mp4">
                        Your browser does not support the video tag.
                    </video>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ✅ Map script -->
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script>
    const latitude = <?= $incident['latitude'] ?>;
    const longitude = <?= $incident['longitude'] ?>;

    const map = L.map('map').setView([latitude, longitude], 17);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19
    }).addTo(map);

    L.marker([latitude, longitude]).addTo(map)
        .bindPopup("Incident Location")
        .openPopup();
</script>
</body>
</html>
