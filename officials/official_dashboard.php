<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/refresh_user_role.php';

refreshUserRole(); 

if (!isset($_SESSION['user_id'])) {
    header("Location: ../authentication/loginform.php");
    exit();
}

if ($_SESSION['user_type'] !== 'official') {
    switch ($_SESSION['user_type']) {
        case 'resident':
            header("Location: ../residents/resident_dashboard.php");
            break;
        case 'tanod':
            header("Location: ../tanods/tanod_dashboard.php");
            break;
        default:
            header("Location: ../authentication/loginform.php");
    }
    exit();
}

$db = new Database();
$conn = $db->connect();

$user_id = $_SESSION['user_id'];
$selectedYear = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Filter variables
$selectedPurok = $_GET['purok'] ?? '';
$selectedStatus = $_GET['status'] ?? '';
$selectedUrgency = $_GET['urgency'] ?? '';

// Unread notifications
$notificationCountStmt = $conn->prepare("SELECT COUNT(*) FROM Notifications WHERE user_id = ? AND is_read = 0");
$notificationCountStmt->execute([$user_id]);
$unreadNotifications = $notificationCountStmt->fetchColumn();

// Filters condition
$filterSQL = "WHERE YEAR(ir.incident_datetime) = :year";
$params = ['year' => $selectedYear];

if ($selectedPurok) {
    $filterSQL .= " AND ir.purok = :purok";
    $params['purok'] = $selectedPurok;
}
if ($selectedStatus) {
    $filterSQL .= " AND ir.status = :status";
    $params['status'] = $selectedStatus;
}
if ($selectedUrgency) {
    $filterSQL .= " AND ir.urgency_level = :urgency";
    $params['urgency'] = $selectedUrgency;
}

$total = $conn->query("SELECT COUNT(*) FROM incident_reports")->fetchColumn();
$resolved = $conn->query("SELECT COUNT(*) FROM incident_reports WHERE status = 'resolved'")->fetchColumn();
$pending = $conn->query("SELECT COUNT(*) FROM incident_reports WHERE status IN ('open', 'in progress')")->fetchColumn();
$urgent = $conn->query("SELECT COUNT(*) FROM incident_reports WHERE urgency_level = 'High' AND status != 'resolved'")->fetchColumn();

$incidentsPerMonth = array_fill(1, 12, 0);
$stmt = $conn->prepare("SELECT MONTH(incident_datetime) AS month, COUNT(*) AS total FROM incident_reports WHERE YEAR(incident_datetime) = ? GROUP BY MONTH(incident_datetime)");
$stmt->execute([$selectedYear]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $incidentsPerMonth[(int)$row['month']] = (int)$row['total'];
}

// Category Chart
$categoryLabels = [];
$categoryCounts = [];
$stmt = $conn->prepare("
    SELECT c.category_name, COUNT(*) as total 
    FROM incident_reports ir
    JOIN categories c ON ir.category_id = c.category_id
    $filterSQL
    GROUP BY c.category_name
");
$stmt->execute($params);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $categoryLabels[] = $row['category_name'];
    $categoryCounts[] = $row['total'];
}

$recentStmt = $conn->query("SELECT incident_id, purok, urgency_level, status, incident_datetime FROM incident_reports ORDER BY incident_datetime DESC LIMIT 5");
$recentReports = $recentStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Official Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    body, html { overflow-x: hidden; font-family: 'Poppins', sans-serif; background: #f8f9fa; }
    .main { padding: 20px; width: 100%; }
    .summary-card { cursor: pointer; text-align: center; background: white; border-radius: 10px; padding: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); flex: 1 1 200px; }
    .summary-card:hover { background: #f1f1f1; }
    .summary-card i { font-size: 2rem; }
    .summary-card p { margin: 0; font-weight: bold; font-size: 1.5rem; }
    .summary-cards { display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 20px; }
    .chart-box { height: 420px; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); display: flex; align-items: center; justify-content: center; }
    canvas { width: 100% !important; height: 100% !important; max-height: 350px; }
    .filters { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 20px; }
    .notification { background: #fff3cd; padding: 15px; border-left: 5px solid #ffc107; border-radius: 5px; margin-bottom: 20px; }
    .recent-card { background: white; padding: 15px; border-radius: 10px; margin-bottom: 15px; }
  </style>
</head>
<body>
<?php include 'navofficial.php'; ?>
<div class="main-content-wrapper">
  <div class="notification">
    🔔 <strong>Unread Notifications:</strong> <?= $unreadNotifications ?> 
  </div>

  <form method="GET" class="filters">
    <select name="year" class="form-select w-auto">
      <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
        <option value="<?= $y ?>" <?= ($selectedYear == $y ? 'selected' : '') ?>><?= $y ?></option>
      <?php endfor; ?>
    </select>
    <select name="purok" class="form-select w-auto">
      <option value="">All Puroks</option>
      <?php foreach (['Purok 1','Purok 2','Purok 3','Purok 4','Purok 5','Purok 6','Purok 7'] as $purok): ?>
        <option value="<?= $purok ?>" <?= ($selectedPurok == $purok ? 'selected' : '') ?>><?= $purok ?></option>
      <?php endforeach; ?>
    </select>
    <select name="status" class="form-select w-auto">
      <option value="">All Status</option>
      <?php foreach (['open','in progress','resolved'] as $status): ?>
        <option value="<?= $status ?>" <?= ($selectedStatus == $status ? 'selected' : '') ?>><?= ucfirst($status) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="urgency" class="form-select w-auto">
      <option value="">All Urgency</option>
      <?php foreach (['High','Medium','Low'] as $urgency): ?>
        <option value="<?= $urgency ?>" <?= ($selectedUrgency == $urgency ? 'selected' : '') ?>><?= $urgency ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-primary">Filter</button>
  </form>

  <div class="summary-cards">
    <div class="summary-card"><i class="fas fa-clipboard-list"></i><h4>Total Reports</h4><p><?= $total ?></p></div>
    <div class="summary-card"><i class="fas fa-check-circle"></i><h4>Resolved</h4><p><?= $resolved ?></p></div>
    <div class="summary-card"><i class="fas fa-hourglass-half"></i><h4>Pending</h4><p><?= $pending ?></p></div>
    <div class="summary-card"><i class="fas fa-exclamation-triangle"></i><h4>Urgent</h4><p><?= $urgent ?></p></div>
  </div>

  <div class="row g-4 mb-4">
    <div class="col-lg-6 col-md-12"><div class="chart-box"><canvas id="monthlyChart"></canvas></div></div>
    <div class="col-lg-6 col-md-12"><div class="chart-box"><canvas id="categoryChart"></canvas></div></div>
  </div>

  <h5>📌 Recent Incident Reports</h5>
  <?php foreach ($recentReports as $report): ?>
    <div class="recent-card">
      <strong>ID #<?= $report['incident_id'] ?></strong> | <?= $report['purok'] ?> | <span class="badge bg-danger"><?= $report['urgency_level'] ?></span>
      <br>Status: <span class="text-uppercase"><?= $report['status'] ?></span> | <small><?= date('M d, Y h:i A', strtotime($report['incident_datetime'])) ?></small>
    </div>
  <?php endforeach; ?>
</main>

<script>
new Chart(document.getElementById('monthlyChart'), {
  type: 'bar',
  data: {
    labels: ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],
    datasets: [{
      label: 'Incidents in <?= $selectedYear ?>',
      data: <?= json_encode(array_values($incidentsPerMonth)) ?>,
      backgroundColor: '#2C3E50'
    }]
  },
  options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
});

new Chart(document.getElementById('categoryChart'), {
  type: 'pie',
  data: {
    labels: <?= json_encode($categoryLabels) ?>,
    datasets: [{ data: <?= json_encode($categoryCounts) ?>, backgroundColor: ['#1abc9c','#e74c3c','#f39c12','#3498db','#9b59b6','#34495e'] }]
  },
  options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
});
</script>
</body>
</html>
