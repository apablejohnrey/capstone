<?php
session_start();
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'tanod') {
    header("Location: ../authentication/loginform.php");
    exit();
}

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
    [13.359511641988888, 123.7258757069265]
];

$db = new Database();
$conn = $db->connect();

$stmt = $conn->prepare("
    SELECT ps.*, t.tanod_id 
    FROM Patrol_Schedule ps
    JOIN Tanods t ON ps.tanod_id = t.tanod_id
    WHERE t.user_id = ? AND ps.patrol_date = CURDATE()
    ORDER BY ps.time_from
");
$stmt->execute([$_SESSION['user_id']]);
$schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Patrol Status Update</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', sans-serif;
            padding: 1rem;
        }
        #map {
            height: 280px;
            border-radius: 10px;
            margin-bottom: 1rem;
        }
        .status-form {
            background: white;
            border-left: 5px solid #0d6efd;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.08);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        .btn-block {
            width: 100%;
        }
        @media (max-width: 576px) {
            h2 { font-size: 1.4rem; }
            #map { height: 240px; }
        }
    </style>
</head>
<body>
<div class="container">
    <a href="my_schedule.php" class="btn btn-outline-dark mb-3">← Back to My Schedule</a>
    <h2 class="text-primary mb-3">📍 Patrol Status Update</h2>

    <div id="map"></div>
    <p class="mb-4">📌 <strong>Your Coordinates:</strong> <span id="coords">Detecting...</span></p>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">✅ Status updated successfully.</div>
    <?php elseif (isset($_GET['locked'])): ?>
        <div class="alert alert-danger">⛔ Location or time invalid for this status update.</div>
    <?php endif; ?>

    <?php if ($schedules): ?>
        <?php foreach ($schedules as $schedule): ?>
            <form method="post" class="status-form" onsubmit="return attachCoords(this)">
                <input type="hidden" name="schedule_id" value="<?= $schedule['schedule_id'] ?>">
                <input type="hidden" name="latitude">
                <input type="hidden" name="longitude">

                <h5 class="text-success"><?= htmlspecialchars($schedule['area']) ?></h5>
                <p><strong>🕐 Time:</strong> <?= $schedule['time_from'] ?> - <?= $schedule['time_to'] ?></p>
                <p><strong>🔄 Current Status:</strong> <?= ucfirst($schedule['status']) ?></p>

                <div class="mb-3">
                    <label for="new_status" class="form-label">Update to:</label>
                    <select name="new_status" class="form-select" required>
                        <option value="on duty" <?= $schedule['status'] === 'on duty' ? 'selected' : '' ?>>On Duty</option>
                        <option value="completed" <?= $schedule['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary btn-block">✅ Submit Update</button>
            </form>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="alert alert-info">📅 You have no patrol schedule for today.</div>
    <?php endif; ?>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    let currentLat = null, currentLng = null;
    const polygon = <?= json_encode($barangayBoundary) ?>;

    const map = L.map('map').setView([13.3568, 123.7232], 16);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
    const boundary = L.polygon(polygon, { color: 'green' }).addTo(map);
    const marker = L.marker([13.3568, 123.7232]).addTo(map);

    function updateMap(lat, lng) {
        currentLat = lat;
        currentLng = lng;
        marker.setLatLng([lat, lng]);
        map.setView([lat, lng]);
        const inside = isInsidePolygon(lat, lng, polygon);
        boundary.setStyle({ color: inside ? 'green' : 'red' });
        document.getElementById('coords').textContent = `${lat.toFixed(5)}, ${lng.toFixed(5)}`;
    }

    function isInsidePolygon(lat, lng, poly) {
        let inside = false, j = poly.length - 1;
        for (let i = 0; i < poly.length; i++) {
            let xi = poly[i][0], yi = poly[i][1];
            let xj = poly[j][0], yj = poly[j][1];
            let intersect = ((yi > lng) !== (yj > lng)) &&
                (lat < (xj - xi) * (lng - yi) / ((yj - yi) + 0.0000001) + xi);
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
            alert("📍 Please wait until your location is detected.");
            return false;
        }
    }

    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(pos => {
            updateMap(pos.coords.latitude, pos.coords.longitude);
        }, err => {
            alert("⚠️ Please allow location access.");
        });

        navigator.geolocation.watchPosition(pos => {
            updateMap(pos.coords.latitude, pos.coords.longitude);
        });
    } else {
        alert("Geolocation not supported on this device.");
    }
</script>
</body>
</html>
