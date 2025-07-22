<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'official') {
    header("Location: ../authentication/loginform.php");
    exit();
}

// Verify Chairperson
$db = new Database();
$conn = $db->connect();
$stmt = $conn->prepare("SELECT position FROM Barangay_Officials WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$position = $stmt->fetchColumn();
if (!$position || strtolower($position) !== 'chairperson') {
    echo "<p style='color:red;'>Access denied. Only the Captain can view this page.</p>";
    exit();
}


class PatrolLocationMonitor {
    private PDO $conn;
    public function __construct(PDO $conn) {
        $this->conn = $conn;
    }
    public function getLatestLocations(): array {
        $sql = "
            SELECT t.tanod_id, t.name, l.latitude, l.longitude, l.timestamp
            FROM Tanods t
            JOIN (
                SELECT tanod_id, latitude, longitude, timestamp
                FROM tanod_location tl1
                WHERE timestamp = (
                    SELECT MAX(timestamp)
                    FROM tanod_location tl2
                    WHERE tl1.tanod_id = tl2.tanod_id
                )
            ) l ON t.tanod_id = l.tanod_id
        ";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

$monitor = new PatrolLocationMonitor($conn);
$locations = $monitor->getLatestLocations();

// 🧭 Define barangay boundary polygon from GeoJSON
$barangayBoundary = [
    ['lat'=>13.3560,'lng'=>123.7215],
    ['lat'=>13.3555,'lng'=>123.7242],
    ['lat'=>13.3580,'lng'=>123.7250],
    ['lat'=>13.3585,'lng'=>123.7220],
    ['lat'=>13.3560,'lng'=>123.7215],
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Monitor Patrol Locations</title>
    <link rel="stylesheet" href="../css/navofficial.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css"/>
    <style>
        .main-content-wrapper { margin-left:250px; padding:30px; }
        #map { height: 600px; width: 100%; border: 2px solid #ccc; border-radius: 8px; }
    </style>
</head>
<body>
    <?php include 'navofficial.php'; ?>
    <div class="main-content-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-map-marker-alt me-2"></i>Monitor Patrol Locations</h2>
            <a href="schedule.php" class="btn btn-secondary">
                <i class="fas fa-calendar-alt me-1"></i> Back to Schedule
            </a>
        </div>

        <?php if (count($locations) > 0): ?>
            <div id="map"></div>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>No patrol location data yet.
            </div>
        <?php endif; ?>
    </div>

    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        <?php if (count($locations) > 0): ?>
        // Center map at first tanod location
        const map = L.map('map').setView([
            <?= $locations[0]['latitude'] ?>, <?= $locations[0]['longitude'] ?>
        ], 15);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        // 🍃 Add barangay boundary polygon
        const barangayCoords = [
            <?php foreach ($barangayBoundary as $pt): ?>
            [<?= $pt['lat'] ?>, <?= $pt['lng'] ?>],
            <?php endforeach; ?>
        ];
        L.polygon(barangayCoords, {color: 'blue', weight: 2, fillOpacity: 0.1})
            .addTo(map).bindPopup("Barangay Boundary");

        // 🌍 Add tanod markers
        <?php foreach ($locations as $loc): ?>
        L.marker([<?= $loc['latitude'] ?>, <?= $loc['longitude'] ?>])
         .addTo(map)
         .bindPopup(`
             <strong><?= htmlspecialchars($loc['name']) ?></strong><br>
             <?= date('M d, Y h:i A', strtotime($loc['timestamp'])) ?>
         `);
        <?php endforeach; ?>
        <?php endif; ?>
    });
    </script>
</body>
</html>
