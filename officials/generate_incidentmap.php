<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['official', 'tanod'])) {
    header("Location: ../authentication/loginform.php");
    exit();
}

$db = new Database();
$conn = $db->connect();

// Get categories
$categoryQuery = "SELECT category_id, category_name FROM categories";
$categoryStmt = $conn->query($categoryQuery);
$categories = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);

// Assign colors to categories
$availableColors = ['red', 'blue', 'green', 'orange', 'purple', 'brown', 'gray', 'pink', 'cyan', 'magenta'];
$categoryColors = [];
foreach ($categories as $i => $cat) {
    $categoryColors[$cat['category_name']] = $availableColors[$i % count($availableColors)];
}

// Get incident reports
$query = "
    SELECT 
        ir.incident_id,
        c.category_name,
        ir.details,
        ir.latitude,
        ir.longitude,
        ir.reported_datetime,
        ir.status,
        COALESCE(CONCAT(bo.fname, ' ', bo.lname), CONCAT(t.fname, ' ', t.lname)) AS verified_by
    FROM incident_reports ir
    JOIN categories c ON ir.category_id = c.category_id
    LEFT JOIN barangay_officials bo ON ir.verified_by = bo.user_id
    LEFT JOIN tanods t ON ir.verified_by = t.user_id
    ORDER BY ir.reported_datetime DESC
";

$stmt = $conn->query($query);
$incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Incident Map</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/leaflet.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <style>
        body {
            margin: 0;
            font-family: 'Segoe UI', sans-serif;
            display: flex;
            min-height: 100vh;
            overflow: hidden;
        }
        .main-content-wrapper {
            margin-left: 250px;
            padding: 20px;
            width: calc(100% - 250px);
            overflow-y: auto;
            max-height: 100vh;
        }
        #map {
            height: 500px;
            width: 100%;
            border: 1px solid #ccc;
            border-radius: 8px;
        }
        @media (max-width: 768px) {
            .main-content-wrapper {
                margin-left: 0;
                width: 100%;
            }
        }
    </style>
</head>
<body>

<?php include 'navofficial.php'; ?>

<div class="main-content-wrapper">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold">Incident Map</h2>
        <a href="monitor_incidents.php" class="btn btn-secondary">← Back to Incidents</a>
    </div>

    <div class="mb-3">
        <label for="categoryFilter" class="form-label">Filter by Category:</label>
        <select id="categoryFilter" class="form-select" style="max-width: 300px;">
            <option value="all">All Categories</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= htmlspecialchars($cat['category_name']) ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div id="map" class="mb-4"></div>

    <div>
        <h5>Legend</h5>
        <?php foreach ($categoryColors as $cat => $color): ?>
            <div style="display: flex; align-items: center; margin-bottom: 4px;">
                <span style="width:15px; height:15px; background-color:<?= $color ?>; margin-right:8px; border-radius:3px;"></span>
                <?= htmlspecialchars($cat) ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/leaflet.js"></script>
<script>
    const panalPolygon = [
        [13.359511641988888, 123.7258757069265],
        [13.35865726300932, 123.72162640442582],
        [13.35839532113907, 123.7202963323312],
        [13.357235934181006, 123.71698493902386],
        [13.353839090961301, 123.71847973395307],
        [13.353843379962683, 123.71997827897565],
        [13.354692794818547, 123.72146159488028],
        [13.355530795218783, 123.72233668539201],
        [13.355707363910255, 123.7221922473936],
        [13.358519757703732, 123.72545987370904],
        [13.359568838836907, 123.72595639741871],
        [13.359511641988888, 123.7258757069265]
    ];

    const map = L.map('map').setView([13.35667, 123.72167], 17);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '' }).addTo(map);

    // Draw the barangay polygon
    const boundary = L.polygon(panalPolygon, { color: 'green', fillOpacity: 0.05 }).addTo(map);

    // Mask outside area
    const worldBounds = [
        [[-90, -180], [-90, 180], [90, 180], [90, -180], [-90, -180]]
    ];
    const maskedArea = L.polygon([...worldBounds, panalPolygon], {
        color: 'none',
        fillColor: '#888',
        fillOpacity: 0.4,
        interactive: false
    }).addTo(map);

    const categoryColors = <?= json_encode($categoryColors) ?>;
    const allIncidents = <?= json_encode($incidents) ?>;
    let markers = [];

    function isInsidePolygon(lat, lng, polygon) {
        let inside = false;
        let j = polygon.length - 1;
        for (let i = 0; i < polygon.length; i++) {
            let xi = polygon[i][1], yi = polygon[i][0];
            let xj = polygon[j][1], yj = polygon[j][0];
            let intersect = ((yi > lat) !== (yj > lat)) &&
                (lng < (xj - xi) * (lat - yi) / ((yj - yi) + 0.0000001) + xi);
            if (intersect) inside = !inside;
            j = i;
        }
        return inside;
    }

    function renderMarkers(filteredCategory) {
        markers.forEach(marker => map.removeLayer(marker));
        markers = [];

        allIncidents.forEach(incident => {
            if (incident.latitude && incident.longitude) {
                const category = incident.category_name;
                const lat = parseFloat(incident.latitude);
                const lng = parseFloat(incident.longitude);

                if ((filteredCategory === 'all' || category === filteredCategory) && isInsidePolygon(lat, lng, panalPolygon)) {
                    const color = categoryColors[category] || 'black';
                    const marker = L.circleMarker([lat, lng], {
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
        renderMarkers(this.value);
    });
</script>

</body>
</html>
