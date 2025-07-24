<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/refresh_user_role.php';

refreshUserRole(); 

if (!isset($_SESSION['user_id'])) {
    header("Location: ../authentication/loginform.php");
    exit();
}

if ($_SESSION['user_type'] !== 'tanod') {
    switch ($_SESSION['user_type']) {
        case 'resident':
            header("Location: ../residents/resident_dashboard.php");
            break;
        case 'official':
            header("Location: ../officials/official_dashboard.php");
            break;
        default:
            header("Location: ../authentication/loginform.php");
    }
    exit();
}

$db = new Database();
$conn = $db->connect();

// Get Tanod info using user_id
$stmt = $conn->prepare("SELECT fname, lname FROM Tanods WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$tanod = $stmt->fetch(PDO::FETCH_ASSOC);

// If tanod not found
if (!$tanod) {
    $tanodFullName = 'Unknown Tanod';
} else {
    $tanodFullName = htmlspecialchars($tanod['fname'] . ' ' . $tanod['lname']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Tanod Dashboard</title>
  <link rel="stylesheet" href="../css/official_dashboard.css" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

<div class="container-fluid">
  <div class="row">
    <div class="col-md-3 col-lg-2 p-0">
      <?php include '../officials/navofficial.php'; ?>
    </div>

    <div class="col-md-9 col-lg-10 main p-4">
      <h2 class="mb-4">Welcome, <?= $tanodFullName ?> (Tanod)</h2>

      <div class="row">
        <div class="col-md-6">
          <div class="card shadow-sm">
            <div class="card-body">
              <h5 class="card-title">Today's Patrol</h5>
              <p class="card-text">View your assigned patrol schedule for today.</p>
              <a href="my_schedule.php" class="btn btn-primary">View Schedule</a>
            </div>
          </div>
        </div>

        <div class="col-md-6 mt-3 mt-md-0">
          <div class="card shadow-sm">
            <div class="card-body">
              <h5 class="card-title">Report an Incident</h5>
              <p class="card-text">Quickly report an incident you witness during patrol.</p>
              <a href="../reportincident.php" class="btn btn-warning">Report Now</a>
            </div>
          </div>
        </div>
      </div>

      <div class="row mt-4">
        <div class="col-md-12">
          <div class="alert alert-info">
            <strong>Note:</strong> Your location may be used to monitor patrol activity.
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// Define polygon boundary
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
  [13.359511641988888, 123.7258757069265]
];

function isInsidePolygon(lat, lng) {
  let x = lng, y = lat;
  let inside = false;
  for (let i = 0, j = polygon.length - 1; i < polygon.length; j = i++) {
    let xi = polygon[i][1], yi = polygon[i][0];
    let xj = polygon[j][1], yj = polygon[j][0];
    let intersect = ((yi > y) !== (yj > y)) &&
                    (x < (xj - xi) * (y - yi) / ((yj - yi) + 0.0000001) + xi);
    if (intersect) inside = !inside;
  }
  return inside;
}

if ("geolocation" in navigator) {
  navigator.geolocation.watchPosition(position => {
    const lat = position.coords.latitude;
    const lng = position.coords.longitude;

    if (isInsidePolygon(lat, lng)) {
      fetch("update_location.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: `latitude=${lat}&longitude=${lng}`
      });
    } else {
      console.warn("Outside of patrol boundary. Location not updated.");
    }
  }, error => {
    console.error("Geolocation error:", error);
  }, {
    enableHighAccuracy: true,
    maximumAge: 0,
    timeout: 5000
  });
}
</script>

</body>
</html>
