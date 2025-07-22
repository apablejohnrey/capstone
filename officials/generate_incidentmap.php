<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['official', 'tanod'])) {
    header("Location: ../authentication/loginform.php");
    exit();
}

$db = new Database();
$conn = $db->connect();

$categoryQuery = "SELECT category_id, category_name FROM categories";
$categoryStmt = $conn->query($categoryQuery);
$categories = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);

$availableColors = ['red', 'blue', 'green', 'orange', 'purple', 'brown', 'gray', 'pink', 'cyan', 'magenta'];
$categoryColors = [];
$i = 0;
foreach ($categories as $cat) {
    $categoryColors[$cat['category_name']] = $availableColors[$i % count($availableColors)];
    $i++;
}

$query = "
    SELECT 
        ir.incident_id,
        c.category_name,
        ir.details,
        ir.latitude,
        ir.longitude,
        ir.reported_datetime,
        ir.status,
        COALESCE(bo.name, t.name) AS verified_by
    FROM incident_reports ir
    JOIN categories c ON ir.category_id = c.category_id
    LEFT JOIN barangay_officials bo ON ir.verified_by = bo.user_id
    LEFT JOIN tanods t ON ir.verified_by = t.user_id
    ORDER BY 
        ir.reported_datetime DESC
";

$stmt = $conn->query($query);
$incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Incident Map</title>
    <link rel="stylesheet" href="../css/navofficial.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/leaflet.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <style>
        #map { height: 500px; width: 100%; }
    </style>
</head>
<body>
<div class="main-content-wrapper">
    <div class="row">
        <div class="col-md-3"><?php include 'navofficial.php'; ?></div>
        <div class="col-md-9 p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2>Incident Map</h2>
                <a href="monitor_incidents.php" class="btn btn-primary">
                    <i class="fas fa-arrow-left"></i> Back to Incidents
                </a>
            </div>

            <!-- Filter -->
            <div class="mb-3">
                <label for="categoryFilter" class="form-label">Filter by Category:</label>
                <select id="categoryFilter" class="form-select">
                    <option value="all">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat['category_name']) ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div id="map"></div>

            <!-- Legend -->
            <div class="mt-4">
                <h4>Legend</h4>
                <?php foreach ($categoryColors as $cat => $color): ?>
                    <span style="display:inline-block;width:15px;height:15px;background-color:<?= $color ?>;margin-right:5px;"></span>
                    <?= htmlspecialchars($cat) ?><br>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/leaflet.js"></script>
<script>
    const map = L.map('map').setView([13.3549, 123.7204], 15);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: ''
    }).addTo(map);

    const categoryColors = <?= json_encode($categoryColors) ?>;
    const allIncidents = <?= json_encode($incidents) ?>;
    let markers = [];

    function renderMarkers(filteredCategory) {
        // Clear existing markers
        markers.forEach(marker => map.removeLayer(marker));
        markers = [];

        allIncidents.forEach(incident => {
            if (incident.latitude && incident.longitude) {
                const category = incident.category_name;
                if (filteredCategory === 'all' || category === filteredCategory) {
                    const color = categoryColors[category] || 'black';
                    const marker = L.circleMarker([incident.latitude, incident.longitude], {
                        color: color,
                        fillColor: color,
                        fillOpacity: 0.8,
                        radius: 10
                    }).addTo(map);

                    marker.bindPopup(`
                        <strong>Category:</strong> ${category}<br>
                        <strong>Details:</strong> ${incident.details}<br>
                        <strong>Status:</strong> ${incident.status.charAt(0).toUpperCase() + incident.status.slice(1)}<br>
                        <strong>Verified By:</strong> ${incident.verified_by || 'Pending'}<br>
                        <strong>Reported On:</strong> ${new Date(incident.reported_datetime).toLocaleString()}
                    `);
                    markers.push(marker);
                }
            }
        });
    }
    renderMarkers('all');

    document.getElementById('categoryFilter').addEventListener('change', function () {
        const selectedCategory = this.value;
        renderMarkers(selectedCategory);
    });
</script>
</body>
</html>
