<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'official') {
    header("Location: ../authentication/loginform.php");
    exit();
}

$db = new Database();
$conn = $db->connect();

$stmt = $conn->prepare("SELECT position FROM Barangay_Officials WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$position = $stmt->fetchColumn();
if (!$position || strtolower($position) !== 'chairperson') {
    echo "<p style='color:red;'>Access denied. Only the Chairperson can view this page.</p>";
    exit();
}

class PatrolLocationMonitor {
    private PDO $conn;
    public function __construct(PDO $conn) { $this->conn = $conn; }

    public function getLatestLocations(?string $start = null, ?string $end = null): array {
        $sql = "
            SELECT t.tanod_id, t.fname, t.lname, l.latitude, l.longitude, l.timestamp
            FROM Tanods t
            JOIN (
                SELECT tanod_id, latitude, longitude, MAX(timestamp) as timestamp
                FROM tanod_location
                GROUP BY tanod_id
            ) l ON t.tanod_id = l.tanod_id
            " . ($start && $end ? "WHERE timestamp BETWEEN :start AND :end" : "") . "
        ";
        $stmt = $this->conn->prepare($sql);
        if ($start && $end) {
            $stmt->bindParam(':start', $start);
            $stmt->bindParam(':end', $end);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getLogs(?string $start = null, ?string $end = null): array {
        $sql = "
            SELECT t.fname, t.lname, l.latitude, l.longitude, l.timestamp
            FROM tanod_location l
            JOIN Tanods t ON t.tanod_id = l.tanod_id
            " . ($start && $end ? "WHERE l.timestamp BETWEEN :start AND :end" : "") . "
            ORDER BY l.timestamp DESC
        ";
        $stmt = $this->conn->prepare($sql);
        if ($start && $end) {
            $stmt->bindParam(':start', $start);
            $stmt->bindParam(':end', $end);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

$monitor = new PatrolLocationMonitor($conn);
$start = $_GET['start'] ?? date('Y-m-d\TH:i');
$end   = $_GET['end']   ?? date('Y-m-d\TH:i');

$locations = $monitor->getLatestLocations($start, $end);
$logs = $monitor->getLogs($start, $end);

$barangayBoundary = [
    ['lat'=>13.3560,'lng'=>123.7215],
    ['lat'=>13.3555,'lng'=>123.7242],
    ['lat'=>13.3580,'lng'=>123.7250],
    ['lat'=>13.3585,'lng'=>123.7220],
    ['lat'=>13.3560,'lng'=>123.7215],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Monitor Patrol Locations</title>
<link rel="stylesheet" href="../css/navofficial.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css"/>
<style>
  body { margin:0; font-family:'Segoe UI',sans-serif; display:flex; min-height:100vh; overflow-x:hidden; }
  .main-content-wrapper { margin-left:250px; width:calc(100% - 250px); padding:30px; transition: margin 0.3s, width 0.3s; }
  @media (max-width:768px) { .main-content-wrapper { margin-left:0; width:100%; } }
  #map { height:400px; border-radius:8px; }
  .table-container { max-height:300px; overflow-y:auto; }
  #sidebarToggle { display:none; position:fixed; top:15px; left:15px; background:#2C3E50; color:#fff; border:none; padding:10px; font-size:18px; z-index:1100; }
  @media (max-width:768px) { #sidebarToggle { display:block; } .sidebar { transform:translateX(-100%); } .sidebar.open { transform:translateX(0); } }
</style>
</head>
<body>

<?php include 'navofficial.php'; ?>
<button id="sidebarToggle"><i class="fas fa-bars"></i></button>

<div class="main-content-wrapper">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-map-marker-alt me-2"></i>Monitor Patrol Locations</h2>
    <a href="schedule.php" class="btn btn-outline-primary"><i class="fas fa-calendar-alt me-1"></i>Back to Schedule</a>
  </div>

  <form method="get" class="row g-3 mb-3">
    <div class="col-md-3">
      <label>From:</label>
      <input type="datetime-local" name="start" class="form-control" value="<?=htmlspecialchars($start)?>">
    </div>
    <div class="col-md-3">
      <label>To:</label>
      <input type="datetime-local" name="end" class="form-control" value="<?=htmlspecialchars($end)?>">
    </div>
    <div class="col-md-3 d-flex gap-2">
      <button type="submit" class="btn btn-primary flex-fill"><i class="fas fa-filter me-1"></i>Filter</button>
      <a href="monitor_patrols.php" class="btn btn-outline-secondary flex-fill"><i class="fas fa-sync-alt me-1"></i>Reset</a>
    </div>
    <div class="col-md-3 text-end">
      <button type="button" class="btn btn-success" onclick="location.reload();"><i class="fas fa-redo me-1"></i>Refresh Map</button>
    </div>
  </form>

  <?php if ($locations): ?>
    <div id="map" class="mb-4"></div>
  <?php else: ?>
    <div class="alert alert-warning">No patrol data available for this time range.</div>
  <?php endif; ?>

  <div class="table-container">
    <h5><i class="fas fa-list me-2"></i>Patrol Logs</h5>
    <?php if ($logs): ?>
      <table class="table table-striped table-sm">
        <thead><tr><th>Tanod</th><th>Lat</th><th>Lng</th><th>Timestamp</th></tr></thead>
        <tbody>
          <?php foreach ($logs as $log): ?>
          <tr>
            <td><?=htmlspecialchars($log['fname'].' '.$log['lname'])?></td>
            <td><?=$log['latitude']?></td>
            <td><?=$log['longitude']?></td>
            <td><?=date('M d, Y h:i A', strtotime($log['timestamp']))?></td>
          </tr>
          <?php endforeach;?>
        </tbody>
      </table>
    <?php else: ?>
      <div class="alert alert-info">No patrol logs found.</div>
    <?php endif; ?>
  </div>
</div>

<script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
<script>
  document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.getElementById('sidebarToggle'),
          sidebar = document.getElementById('sidebar');
    toggle.addEventListener('click', () => sidebar.classList.toggle('open'));

    <?php if ($locations): ?>
    const map = L.map('map').setView([<?=$locations[0]['latitude']?>, <?=$locations[0]['longitude']?>], 15);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{ attribution:'© OpenStreetMap'}).addTo(map);
    L.polygon(<?=json_encode(array_map(fn($pt)=>[$pt['lat'],$pt['lng']], $barangayBoundary))?>,{color:'blue',fillOpacity:0.1}).addTo(map);

    <?php foreach($locations as $loc): ?>
      L.marker([<?=$loc['latitude']?>,<?=$loc['longitude']?>])
        .addTo(map)
        .bindPopup(`<?=htmlspecialchars($loc['fname'].' '.$loc['lname'])?><br><?=date('M d, Y h:i A',strtotime($loc['timestamp']))?>`);
    <?php endforeach; ?>
    <?php endif; ?>
  });
</script>
</body>
</html>
