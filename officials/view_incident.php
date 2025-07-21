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

// ✅ Fetch notes (verification + resolution)
$stmt = $conn->prepare("
    SELECT ivn.notes, ivn.created_at, u.username 
    FROM incident_verification_notes ivn
    JOIN users u ON ivn.verified_by = u.user_id
    WHERE ivn.incident_id = ?
    ORDER BY ivn.created_at ASC
");
$stmt->execute([$incident_id]);
$allNotes = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$incident) {
    echo "Incident not found.";
    exit();
}

$reporterName = (!empty($incident['fname']) || !empty($incident['lname']))
    ? htmlspecialchars($incident['fname'] . ' ' . $incident['lname'])
    : htmlspecialchars($incident['username']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Incident</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <style>
        #map {
            height: 300px;
            margin-bottom: 20px;
            border: 1px solid #ccc;
            border-radius: 6px;
        }
    </style>
</head>
<body>
<div class="container mt-4 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">Incident Details</h2>
        <a href="monitor_incidents.php" class="btn btn-secondary">← Back to Monitor</a>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <p><strong>Category:</strong> <?= htmlspecialchars($incident['category_name']) ?></p>
            <p><strong>Reported by:</strong> <?= $reporterName ?></p>
            <p><strong>Date & Time:</strong> <?= htmlspecialchars($incident['incident_datetime']) ?></p>
            <p><strong>Urgency:</strong> <?= htmlspecialchars($incident['urgency_level']) ?></p>
            <p><strong>Purok:</strong> <?= htmlspecialchars($incident['purok']) ?></p>
            <p><strong>Landmark:</strong> <?= htmlspecialchars($incident['landmark']) ?: 'N/A' ?></p>
            <p><strong>Details:</strong><br><?= nl2br(htmlspecialchars($incident['details'])) ?></p>
        </div>
    </div>

    <!-- Map -->
    <div class="mb-4">
        <h5>Location</h5>
        <div id="map"></div>
    </div>

    <!-- Victims -->
    <div class="mb-4">
        <h5>Victims</h5>
        <?php if (count($victims) > 0): ?>
            <ul class="list-group">
                <?php foreach ($victims as $v): ?>
                    <li class="list-group-item"><?= htmlspecialchars($v['name']) ?> (<?= $v['age'] ?> y/o)</li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="text-muted">No victims listed.</p>
        <?php endif; ?>
    </div>

    <!-- Perpetrators -->
    <div class="mb-4">
        <h5>Perpetrators</h5>
        <?php if (count($perpetrators) > 0): ?>
            <ul class="list-group">
                <?php foreach ($perpetrators as $p): ?>
                    <li class="list-group-item"><?= htmlspecialchars($p['name']) ?> (<?= $p['age'] ?> y/o)</li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="text-muted">No perpetrators listed.</p>
        <?php endif; ?>
    </div>

    <!-- Witnesses -->
    <div class="mb-4">
        <h5>Witnesses</h5>
        <?php if (count($witnesses) > 0): ?>
            <ul class="list-group">
                <?php foreach ($witnesses as $w): ?>
                    <li class="list-group-item"><?= htmlspecialchars($w['name']) ?> – <?= htmlspecialchars($w['contact_number']) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="text-muted">No witnesses listed.</p>
        <?php endif; ?>
    </div>

    <!-- Evidence -->
    <div class="mb-4">
        <h5>Evidence</h5>
        <?php if (count($evidence) > 0): ?>
            <div class="row">
                <?php foreach ($evidence as $e): ?>
                    <div class="col-md-4 mb-3">
                        <?php if (strtolower($e['file_type']) === 'image'): ?>
                            <img src="../<?= htmlspecialchars($e['file_path']) ?>" class="img-fluid rounded border">
                        <?php elseif (strtolower($e['file_type']) === 'video'): ?>
                            <video class="w-100 border rounded" controls>
                                <source src="../<?= htmlspecialchars($e['file_path']) ?>" type="video/mp4">
                                Your browser does not support the video tag.
                            </video>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-muted">No evidence uploaded.</p>
        <?php endif; ?>
    </div>

    <!-- All Notes (Verification + Resolution) -->
    <div class="mb-5">
        <h5>Verification & Resolution Notes</h5>
        <?php if (count($allNotes) > 0): ?>
            <div class="list-group">
                <?php foreach ($allNotes as $note): ?>
                    <?php
                        $isResolution = stripos($note['notes'], '[RESOLUTION]') === 0;
                        $displayNote = $isResolution ? substr($note['notes'], 12) : $note['notes'];
                    ?>
                    <div class="list-group-item <?= $isResolution ? 'bg-light text-success' : '' ?>">
                        <strong><?= htmlspecialchars($note['username']) ?></strong>
                        <small class="text-muted">on <?= date('M d, Y h:i A', strtotime($note['created_at'])) ?></small>
                        <p class="mb-0"><?= nl2br(htmlspecialchars(trim($displayNote))) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-muted">No notes available.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Map Script -->
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script>
    const latitude = <?= $incident['latitude'] ?>;
    const longitude = <?= $incident['longitude'] ?>;

    const map = L.map('map').setView([latitude, longitude], 17);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19
    }).addTo(map);

    L.marker([latitude, longitude])
        .addTo(map)
        .bindPopup("Incident Location")
        .openPopup();
</script>
</body>
</html>
