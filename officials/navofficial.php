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
} elseif ($_SESSION['user_type'] === 'tanod') {
    $stmt = $conn->prepare("SELECT name FROM Tanods WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $tanod = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tanod) {
        echo "<p style='color:red;'>Tanod details not found.</p>";
        exit();
    }

    $name = $tanod['name'];
    $position = 'Tanod';
}
?>
<link rel="stylesheet" href="../css/navofficial.css">
<aside class="sidebar">
    <div class="logo">
        <img src="../img/logos.png" alt="Logo"> 
    </div>
    <div class="profile">
        <img src="" alt="Profile Pic"> 
        <h3><?php echo htmlspecialchars($name); ?></h3>
        <span class="badge"><?php echo ucfirst($position); ?></span>
    </div>
    <nav class="nav">
        <?php if ($_SESSION['user_type'] === 'official'): ?>
            <a href="official_dashboard.php">Dashboard</a>
            <a href="monitor_incidents.php">Monitor Incident</a>
            <a href="manage_accounts.php">Manage Accounts</a>

            <?php if ($position === 'secretary'): ?>
                <a href="documents.php">Documents</a>
            <?php elseif ($position === 'chairperson' || $position === 'captain'): ?>
                <a href="schedule.php">Patrol Schedules</a>
                <a href="sms_alerts.php">SMS Alerts & Notifications</a>
            <?php endif; ?>

            <a href="manage_incident_types.php">Manage Incident Type</a>
        <?php elseif ($_SESSION['user_type'] === 'tanod'): ?>
             <a href="../tanods/tanod_dashboard.php">Dashboard</a>
            <a href="../officials/monitor_incidents.php">Monitor Incidents</a>
            <a href="../tanods/my_schedule.php">My Schedule</a>
        <?php endif; ?>

        <a href="account.php">Account</a>
    </nav>

    <a href="../authentication/logout.php" class="logout">
        <i class="fas fa-sign-out-alt"></i> Log Out
    </a>
</aside>
