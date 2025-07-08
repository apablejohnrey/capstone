<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'tanod') {
    header("Location: ../authentication/loginform.php");
    exit();
}

$db = new Database();
$conn = $db->connect();

$stmt = $conn->prepare("SELECT name FROM Tanods WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$tanod = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tanod) {
    echo "<p style='color:red;'>Tanod profile not found.</p>";
    exit();
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
      <h2 class="mb-4">Welcome, <?= htmlspecialchars($tanod['name']) ?> (Tanod)</h2>

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
if ("geolocation" in navigator) {
  navigator.geolocation.watchPosition(position => {
    fetch("update_location.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: `latitude=${position.coords.latitude}&longitude=${position.coords.longitude}`
    });
  });
}
</script>

</body>
</html>
