<?php
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION)) session_start();

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
    echo "<p style='color:red;'>Official details not found.</p>";
    exit();
}
$position = strtolower($official['position']);
?>
<link rel="stylesheet" href="../css/navofficial.css">
<aside class="sidebar">
    <div class="logo">
        <img src="../img/logos.png" alt="Logo"> 
    </div>
    <div class="profile">
        <img src="" alt="Profile Pic"> 
        <h3><?php echo htmlspecialchars($official['name']); ?></h3>
        <span class="badge"><?php echo htmlspecialchars($official['position']); ?></span>
    </div>
    <nav class="nav">
        <a href="official_dashboard.php">Dashboard</a>
        <a href="monitor_incidents.php">Monitor Incident</a>
        <a href="manage_accounts.php">Manage Accounts</a>
        <?php if ($position === 'secretary'): ?>
            <a href="documents.php">Documents</a>
        <?php elseif ($position === 'chairperson' || $position === 'captain'): ?>
            <a href="schedule.php">Patrol Schedules</a>
            <a href="../monitor_patrol.php">Monitor Patrols</a>
            <a href="sms_alerts.php">SMS Alerts & Notifications</a>
        <?php endif; ?>
        <a href="manage_incident_types.php">Manage Incident Type</a>
        <a href="account.php">Account</a>
    </nav>

    <a href="../authentication/logout.php" class="logout">
        <i class="fas fa-sign-out-alt"></i> Log Out
    </a>
</aside>
