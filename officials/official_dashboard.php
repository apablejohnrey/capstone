<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'official') {
    header("Location: ../authentication/loginform.php"); 
    exit();
}

$db = new Database();
$conn = $db->connect();

$stmt = $conn->prepare("SELECT name, position FROM Barangay_Officials WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$official = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$official) {
    echo "<p style='color:red;'>Official details not found. Please contact the system administrator.</p>";
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Official Dashboard - Incident Monitoring</title>
  <link rel="stylesheet" href="../css/official_dashboard.css" /> 
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
  <div class="container">
      <?php
      $navPath = 'navofficial.php'; 
      if (file_exists($navPath)) {
          include $navPath;
      } else {
          echo "<p style='color:red; text-align:center;'>Navigation file not found.</p>";
      }
      ?>
    </aside>

    <main class="main">
      <section class="intro">
        <h1>Monitor and Manage Incidents in Barangay Panal</h1>
        <p>Review incoming incident reports, assign priorities, and oversee barangay response actions efficiently.</p>
        <a href="monitor_incidents.php" class="btn btn-success">View Reports</a>
      </section>

      <section class="announcement-area">
        <div class="card announcement">
          <h2>Official Notice</h2>
          <p>Please ensure reports are reviewed within 24 hours. Coordinate with responders for urgent incidents.</p>
        </div>
        <div class="card days">
          <h2>Office Hours</h2>
          <ul>
            <li>Mon–Fri: 8:00 AM – 5:00 PM</li>
            <li>Weekends: Emergency coordination only</li>
          </ul>
        </div>
      </section>
    </main>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
