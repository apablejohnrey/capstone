<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'tanod') {
    http_response_code(403);
    echo "Unauthorized";
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['latitude'], $_POST['longitude'])) {
    $latitude = floatval($_POST['latitude']);
    $longitude = floatval($_POST['longitude']);

    $db = new Database();
    $conn = $db->connect();

    // Get tanod_id
    $stmt = $conn->prepare("SELECT tanod_id FROM Tanods WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $tanod = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tanod) {
        http_response_code(404);
        echo "Tanod not found.";
        exit();
    }

    // Check if ON DUTY today
    $statusStmt = $conn->prepare("SELECT status FROM Patrol_Schedule WHERE tanod_id = ? AND patrol_date = CURDATE() AND status = 'on duty'");
    $statusStmt->execute([$tanod['tanod_id']]);
    $onDuty = $statusStmt->fetch();

    if (!$onDuty) {
        http_response_code(403);
        echo "Not on duty — tracking not allowed.";
        exit();
    }


    $barangayPolygon = [
        [13.3560, 123.7215],
        [13.3555, 123.7242],
        [13.3580, 123.7250],
        [13.3585, 123.7220],
        [13.3560, 123.7215]
    ];

    function pointInPolygon($lat, $lng, $polygon) {
        $inside = false;
        $j = count($polygon) - 1;
        for ($i = 0; $i < count($polygon); $i++) {
            $xi = $polygon[$i][0]; $yi = $polygon[$i][1];
            $xj = $polygon[$j][0]; $yj = $polygon[$j][1];

            $intersect = (($yi > $lng) != ($yj > $lng)) &&
                         ($lat < ($xj - $xi) * ($lng - $yi) / ($yj - $yi + 0.0000001) + $xi);
            if ($intersect) $inside = !$inside;
            $j = $i;
        }
        return $inside;
    }

    if (!pointInPolygon($latitude, $longitude, $barangayPolygon)) {
        http_response_code(403);
        echo "Location outside barangay — not saved.";
        exit();
    }

    $insert = $conn->prepare("INSERT INTO tanod_location (tanod_id, latitude, longitude) VALUES (?, ?, ?)");
    $insert->execute([$tanod['tanod_id'], $latitude, $longitude]);

    echo "Location updated.";
} else {
    http_response_code(400);
    echo "Invalid request.";
}
?>
