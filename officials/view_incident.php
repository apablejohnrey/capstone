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

// Fetch notes with full name fallback
$stmt = $conn->prepare("
    SELECT 
        ivn.notes, 
        ivn.created_at, 
        COALESCE(CONCAT(bo.fname, ' ', bo.lname), CONCAT(t.fname, ' ', t.lname), u.username) AS verifier_name
    FROM incident_verification_notes ivn
    JOIN users u ON ivn.verified_by = u.user_id
    LEFT JOIN barangay_officials bo ON bo.user_id = u.user_id
    LEFT JOIN tanods t ON t.user_id = u.user_id
    WHERE ivn.incident_id = ?
    ORDER BY ivn.created_at ASC
");
$stmt->execute([$incident_id]);
$allNotes = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$incident) {
    echo "Incident not found.";
    exit();
}

$fname = $incident['fname'] ?? '';
$lname = $incident['lname'] ?? '';
$reporterName = trim($fname . ' ' . $lname) ?: htmlspecialchars($incident['username']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Incident</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <style>
        #map {
            height: 300px;
            border-radius: 6px;
            border: 1px solid #ccc;
        }
    </style>
</head>
<body>
<div class="container mt-4 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0"><i class="bi bi-flag-fill text-danger"></i> Incident Details</h2>
        <a href="monitor_incidents.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back to Monitor</a>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <p><strong>Category:</strong> <?= htmlspecialchars($incident['category_name']) ?></p>
            <p><strong>Reported by:</strong> <?= htmlspecialchars($reporterName) ?></p>
            <p><strong>Date & Time:</strong> <?= htmlspecialchars($incident['incident_datetime']) ?></p>
            <p><strong>Urgency:</strong> <?= htmlspecialchars($incident['urgency_level']) ?></p>
            <p><strong>Purok:</strong> <?= htmlspecialchars($incident['purok']) ?></p>
            <p><strong>Landmark:</strong> <?= htmlspecialchars($incident['landmark']) ?: 'N/A' ?></p>
            <p><strong>Details:</strong><br><?= nl2br(htmlspecialchars($incident['details'])) ?></p>
        </div>
    </div>

    <h5><i class="bi bi-geo-alt-fill text-primary"></i> Location</h5>
    <div id="map" class="mb-4"></div>

    <?php
    function displayListSection($title, $items, $emptyText, $formatter) {
        echo "<h5><i class='bi bi-person-lines-fill'></i> $title</h5>";
        if (count($items) > 0) {
            echo "<ul class='list-group mb-4'>";
            foreach ($items as $item) {
                echo "<li class='list-group-item'>{$formatter($item)}</li>";
            }
            echo "</ul>";
        } else {
            echo "<p class='text-muted'>$emptyText</p>";
        }
    }

    displayListSection('Victims', $victims, 'No victims listed.', fn($v) => htmlspecialchars($v['name']) . " ({$v['age']} y/o)");
    displayListSection('Perpetrators', $perpetrators, 'No perpetrators listed.', fn($p) => htmlspecialchars($p['name']) . " ({$p['age']} y/o)");
    displayListSection('Witnesses', $witnesses, 'No witnesses listed.', fn($w) => htmlspecialchars($w['name']) . " – " . htmlspecialchars($w['contact_number']));
    ?>

    <h5><i class="bi bi-file-earmark-image-fill"></i> Evidence</h5>
    <?php if (count($evidence) > 0): ?>
        <div class="row mb-4">
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

    <h5 class="mb-3"><i class="bi bi-chat-dots-fill text-success"></i> Verification & Resolution Notes</h5>
    <?php if (count($allNotes) > 0): ?>
        <?php foreach ($allNotes as $note): ?>
            <?php
                $isResolution = stripos($note['notes'], '[RESOLUTION]') === 0;
                $displayNote = $isResolution ? substr($note['notes'], 12) : $note['notes'];
            ?>
            <div class="card shadow-sm border-<?= $isResolution ? 'success' : 'primary' ?> mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-person-circle fs-4 me-2 text-<?= $isResolution ? 'success' : 'primary' ?>"></i>
                            <h6 class="mb-0"><?= htmlspecialchars($note['verifier_name']) ?></h6>
                        </div>
                        <small class="text-muted">
                            <?= date('M d, Y h:i A', strtotime($note['created_at'])) ?>
                        </small>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><strong><?= $isResolution ? 'Resolution Note:' : 'Verification Note:' ?></strong></label>
                        <div class="form-control bg-light" style="white-space: pre-wrap; min-height: 80px;">
                            <?= htmlspecialchars(trim($displayNote)) ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p class="text-muted">No notes available.</p>
    <?php endif; ?>
</div>

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
