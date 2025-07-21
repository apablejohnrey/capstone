<?php
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION)) session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['official', 'tanod'])) {
    header("Location: ../authentication/loginform.php");
    exit();
}

$db = new Database();
$conn = $db->connect();

$name = '';
$position = '';
$profilePhoto = '../img/default.png'; // fallback

if ($_SESSION['user_type'] === 'official') {
    $stmt = $conn->prepare("SELECT name, position FROM Barangay_Officials WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $official = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$official) {
        echo "<p style='color:red;'>Official details not found.</p>";
        exit();
    }

    $name = $official['name'];
    $position = strtolower($official['position']);

    if ($position === 'secretary') {
        $profilePhoto = '../img/secretary.png';
    } elseif ($position === 'chairperson' || $position === 'captain') {
        $profilePhoto = '../img/captain.png';
    }

} elseif ($_SESSION['user_type'] === 'tanod') {
    $stmt = $conn->prepare("SELECT name FROM Tanods WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $tanod = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tanod) {
        echo "<p style='color:red;'>Tanod details not found.</p>";
        exit();
    }

    $name = $tanod['name'];
    $position = 'tanod';
    $profilePhoto = '../img/tanod.png';
}
?>

<!-- SIDEBAR STYLE -->
<style>
body {
  margin: 0;
  font-family: 'Segoe UI', sans-serif;
  display: flex;
  min-height: 100vh;
  overflow: hidden;
}

.sidebar {
  width: 250px;
  background: #2C3E50;
  color: white;
  flex-shrink: 0;
  height: 100vh;
  overflow-y: auto;
  position: fixed;
  top: 0;
  left: 0;
  padding: 20px;
  z-index: 1000;
}

.sidebar a {
  display: block;
  color: white;
  text-decoration: none;
  padding: 10px;
  margin: 5px 0;
  border-radius: 4px;
}

.sidebar a:hover {
  background: #34495e;
}

.sidebar .logo img {
  max-width: 100%;
  display: block;
  margin: 0 auto 10px;
}

.sidebar .profile {
  text-align: center;
  margin-bottom: 20px;
}

.sidebar .profile img {
  width: 70px;
  height: 70px;
  border-radius: 50%;
  object-fit: cover;
  background: #ccc;
}

.sidebar .profile h3 {
  margin: 10px 0 5px;
  font-size: 1.1rem;
}

.sidebar .badge {
  background: #16A085;
  padding: 5px 10px;
  border-radius: 20px;
  display: inline-block;
  font-size: 0.85rem;
}

/* Toggle Button */
#sidebarToggle {
  display: none;
  position: fixed;
  top: 15px;
  left: 15px;
  background: #2C3E50;
  color: white;
  border: none;
  padding: 10px 15px;
  font-size: 18px;
  cursor: pointer;
  z-index: 1100;
}

@media (max-width: 768px) {
  .sidebar {
    transform: translateX(-100%);
    transition: transform 0.3s ease;
  }

  .sidebar.open {
    transform: translateX(0);
  }

  #sidebarToggle {
    display: block;
  }
}

/* Adjust page content wrapper */
.main-content-wrapper {
  margin-left: 250px;
  width: calc(100% - 250px);
  overflow-y: auto;
  max-height: 100vh;
}

@media (max-width: 768px) {
  .main-content-wrapper {
    margin-left: 0;
    width: 100%;
  }
}
</style>

<!-- TOGGLE BUTTON -->
<button id="sidebarToggle">&#9776;</button>

<!-- SIDEBAR STRUCTURE -->
<div class="sidebar" id="sidebar">
    <div class="logo">
        <img src="../img/logos.png" alt="Logo">
    </div>

    <div class="profile">
        <img src="<?= htmlspecialchars($profilePhoto) ?>" alt="Profile">
        <h3><?= htmlspecialchars($name) ?></h3>
        <div class="badge"><?= ucfirst(htmlspecialchars($position)) ?></div>
    </div>

    <nav>
        <?php if ($_SESSION['user_type'] === 'official'): ?>
            <a href="official_dashboard.php">Dashboard</a>
            <a href="monitor_incidents.php">Monitor Incidents</a>
            <a href="manage_accounts.php">Manage Accounts</a>
            <?php if ($position === 'secretary'): ?>
                <a href="documents.php">Documents</a>
            <?php elseif ($position === 'chairperson' || $position === 'captain'): ?>
                <a href="schedule.php">Patrol Schedules</a>
                <a href="sms_alerts.php">SMS Alerts</a>
            <?php endif; ?>
            <a href="manage_incident_types.php">Manage Incident Types</a>
        <?php elseif ($_SESSION['user_type'] === 'tanod'): ?>
            <a href="../tanods/tanod_dashboard.php">Dashboard</a>
            <a href="../officials/monitor_incidents.php">Monitor Incidents</a>
            <a href="../tanods/my_schedule.php">My Schedule</a>
        <?php endif; ?>
        <a href="account.php">Account</a>
        <a href="../authentication/logout.php" style="color:#e74c3c;">Log Out</a>
    </nav>
</div>

<!-- SIDEBAR TOGGLE SCRIPT -->
<script>
const toggleBtn = document.getElementById('sidebarToggle');
const sidebar = document.getElementById('sidebar');

toggleBtn.addEventListener('click', () => {
  sidebar.classList.toggle('open');
});
</script>
