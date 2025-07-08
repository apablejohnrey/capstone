<?php
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION)) session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'tanod') {
    header("Location: ../authentication/loginform.php");
    exit();
}

class TanodInfo {
    private $conn;

    public function __construct(PDO $db) {
        $this->conn = $db;
    }

    public function getTanodDetails($user_id) {
        $stmt = $this->conn->prepare("SELECT name FROM Tanods WHERE user_id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

$db = new Database();
$conn = $db->connect();
$tanod = (new TanodInfo($conn))->getTanodDetails($_SESSION['user_id']);

if (!$tanod) {
    echo "<p style='color:red;'>Tanod details not found.</p>";
    exit();
}
?>

<link rel="stylesheet" href="../css/navofficial.css">
<aside class="sidebar">
    <div class="logo">
        <img src="../img/logos.png" alt="Logo"> 
    </div>
    <div class="profile">
        <img src="" alt="Profile Pic"> 
        <h3><?= htmlspecialchars($tanod['name']) ?></h3>
        <span class="badge">Tanod</span>
    </div>

    <nav class="nav">
        <a href="tanod_dashboard.php">Dashboard</a>
        <a href="my_schedule.php">My Patrol Schedule</a>
        <a href="reportincident.php">Report Incident</a>
        <a href="../officials/monitor_incidents.php">Monitor Incident</a>
        <a href="account.php">Account</a>
    </nav>

    <a href="../authentication/logout.php" class="logout">
        <i class="fas fa-sign-out-alt"></i> Log Out
    </a>
</aside>
