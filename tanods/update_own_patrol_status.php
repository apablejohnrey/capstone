<?php
session_start();
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../includes/db.php';

// Session check
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'tanod') {
    header("Location: ../authentication/loginform.php");
    exit();
}

// Polygon boundary
function pointInPolygon($lat, $lng, $polygon) {
    $inside = false;
    $j = count($polygon) - 1;
    for ($i = 0; $i < count($polygon); $i++) {
        $xi = $polygon[$i][0]; $yi = $polygon[$i][1];
        $xj = $polygon[$j][0]; $yj = $polygon[$j][1];
        $intersect = (($yi > $lng) != ($yj > $lng)) &&
                     ($lat < ($xj - $xi) * ($lng - $yi) / (($yj - $yi) + 0.0000001) + $xi);
        if ($intersect) $inside = !$inside;
        $j = $i;
    }
    return $inside;
}

// Polygon boundary (replace with actual)
$barangayBoundary = [
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
    [13.359511641988888, 123.7258757069265] // closed loop
];


$db = new Database();
$conn = $db->connect();

// Get today's patrol schedules
$stmt = $conn->prepare("
    SELECT ps.*, t.tanod_id 
    FROM Patrol_Schedule ps
    JOIN Tanods t ON ps.tanod_id = t.tanod_id
    WHERE t.user_id = ? AND ps.patrol_date = CURDATE()
    ORDER BY ps.time_from
");
$stmt->execute([$_SESSION['user_id']]);
$schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_status'], $_POST['schedule_id'], $_POST['latitude'], $_POST['longitude'])) {
    $newStatus = $_POST['new_status'];
    $scheduleId = (int) $_POST['schedule_id'];
    $latitude = floatval($_POST['latitude']);
    $longitude = floatval($_POST['longitude']);

    $isInside = pointInPolygon($latitude, $longitude, $barangayBoundary);

    $stmt = $conn->prepare("
        SELECT ps.*, t.tanod_id 
        FROM Patrol_Schedule ps
        JOIN Tanods t ON ps.tanod_id = t.tanod_id
        WHERE ps.schedule_id = ? AND t.user_id = ?
    ");
    $stmt->execute([$scheduleId, $_SESSION['user_id']]);
    $schedule = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$schedule) {
        header("Location: update_own_patrol_status.php?success=0");
        exit();
    }

    $now = new DateTime();
    $from = new DateTime($schedule['time_from']);
    $to = new DateTime($schedule['time_to']);

    if (($newStatus === 'on duty' && (!$isInside || $now < $from || $now > $to)) ||
        ($newStatus === 'completed' && (!$isInside || $now < $to))) {
        header("Location: update_own_patrol_status.php?locked=1");
        exit();
    }

    $stmt = $conn->prepare("UPDATE Patrol_Schedule SET status = ? WHERE schedule_id = ?");
    $stmt->execute([$newStatus, $scheduleId]);

    $loc = $conn->prepare("INSERT INTO tanod_location (tanod_id, latitude, longitude) VALUES (?, ?, ?)");
    $loc->execute([$schedule['tanod_id'], $latitude, $longitude]);

    $msg = "Tanod updated status to <strong>$newStatus</strong> in area <strong>" . htmlspecialchars($schedule['area']) . "</strong>.";
    $officials = $conn->query("SELECT user_id FROM Barangay_Officials")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($officials as $official) {
        $notify = $conn->prepare("INSERT INTO Notifications (user_id, message) VALUES (?, ?)");
        $notify->execute([$official['user_id'], $msg]);
    }

    header("Location: update_own_patrol_status.php?success=1");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Update Patrol Status</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        body { background: #f4f6f9; padding: 2rem; font-family: 'Segoe UI', sans-serif; }
        .container { max-width: 900px; }
        #map { height: 300px; margin-bottom: 20px; }
    </style>
</head>
<body>
<div class="container">
    <h3 class="mb-4">Update My Patrol Status</h3>

    <div id="map"></div>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">Status updated successfully.</div>
    <?php elseif (isset($_GET['locked'])): ?>
        <div class="alert alert-danger">Invalid attempt: You are outside the allowed time or outside the barangay.</div>
    <?php endif; ?>

    <?php if ($schedules): ?>
        <?php foreach ($schedules as $schedule): ?>
            <form method="post" class="card p-3 shadow-sm mb-3" onsubmit="return attachCoords(this)">
                <input type="hidden" name="schedule_id" value="<?= $schedule['schedule_id'] ?>">
                <input type="hidden" name="latitude">
                <input type="hidden" name="longitude">

                <p><strong>Area:</strong> <?= htmlspecialchars($schedule['area']) ?></p>
                <p><strong>Time:</strong> <?= htmlspecialchars($schedule['time_from']) ?> - <?= htmlspecialchars($schedule['time_to']) ?></p>
                <p><strong>Current Status:</strong> <?= ucfirst($schedule['status']) ?></p>

                <div class="mb-3">
                    <label class="form-label">Update Status</label>
                    <select name="new_status" class="form-select" required>
                        <option value="on duty" <?= $schedule['status'] === 'on duty' ? 'selected' : '' ?>>On Duty</option>
                        <option value="completed" <?= $schedule['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary">Save</button>
            </form>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="alert alert-info">No patrol schedule today.</div>
    <?php endif; ?>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    let currentLat = null, currentLng = null;
   const polygon = [
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
    [13.359511641988888, 123.7258757069265] // closed polygon
];

    const map = L.map('map').setView([13.3568, 123.7232], 16);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '' }).addTo(map);
    const boundary = L.polygon(polygon, { color: 'green' }).addTo(map);
    const marker = L.marker([0, 0]).addTo(map);

    navigator.geolocation.watchPosition(pos => {
        currentLat = pos.coords.latitude;
        currentLng = pos.coords.longitude;
        marker.setLatLng([currentLat, currentLng]);
        map.setView([currentLat, currentLng], 16);

        const inside = isInsidePolygon(currentLat, currentLng, polygon);
        boundary.setStyle({ color: inside ? 'green' : 'red' });
    });

    function isInsidePolygon(lat, lng, poly) {
        let inside = false, j = poly.length - 1;
        for (let i = 0; i < poly.length; i++) {
            let xi = poly[i][0], yi = poly[i][1];
            let xj = poly[j][0], yj = poly[j][1];
            let intersect = ((yi > lng) !== (yj > lng)) && (lat < (xj - xi) * (lng - yi) / ((yj - yi) + 0.0000001) + xi);
            if (intersect) inside = !inside;
            j = i;
        }
        return inside;
    }

    function attachCoords(form) {
        if (currentLat && currentLng) {
            form.latitude.value = currentLat;
            form.longitude.value = currentLng;
            return true;
        } else {
            alert("Location not yet loaded. Please wait...");
            return false;
        }
    }
</script>
</body>
</html>
