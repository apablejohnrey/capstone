<?php
session_start();
date_default_timezone_set('Asia/Manila'); 
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'tanod') {
    header("Location: ../authentication/loginform.php");
    exit();
}

$db = new Database();
$conn = $db->connect();


$stmt = $conn->prepare("SELECT tanod_id, name FROM Tanods WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$tanod = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tanod) {
    echo "Tanod not found.";
    exit();
}

$stmt = $conn->prepare("SELECT * FROM Patrol_Schedule WHERE tanod_id = ? ORDER BY patrol_date DESC, time_from");
$stmt->execute([$tanod['tanod_id']]);
$allSchedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

$today = date('Y-m-d');
$schedulesToday = [];
$schedulesUpcoming = [];
$schedulesPast = [];
$isOnDuty = false;

foreach ($allSchedules as $sched) {
    if ($sched['patrol_date'] === $today) {
        $schedulesToday[] = $sched;
        if ($sched['status'] === 'on duty') {
            $isOnDuty = true;
        }
    } elseif ($sched['patrol_date'] > $today) {
        $schedulesUpcoming[] = $sched;
    } else {
        $schedulesPast[] = $sched;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Patrol Schedule</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../css/navofficial.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .main-content {
            margin-left: 250px;
            padding: 30px;
        }
        .table th, .table td {
            vertical-align: middle;
            text-align: center;
        }
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
        }
        .status-on-duty {
            background-color: #0d6efd;
            color: white;
        }
        .status-off-duty {
            background-color: #6c757d;
            color: white;
        }
        .status-completed {
            background-color: #198754;
            color: white;
        }
    </style>
</head>
<body>

<?php include '../officials/navofficial.php'; ?>

<div class="main-content">
    <h2 class="mb-4"><i class="fas fa-calendar-alt me-2"></i>My Patrol Schedule</h2>

    <!-- Today -->
    <h5 class="mb-2 text-primary"><i class="fas fa-clock me-1"></i> Today's Schedule</h5>
    <?php if (count($schedulesToday)): ?>
        <div class="card shadow mb-4">
            <div class="card-body">
                <table class="table table-bordered table-striped">
                    <thead class="table-dark">
                        <tr>
                            <th>Date</th>
                            <th>Area</th>
                            <th>Time From</th>
                            <th>Time To</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($schedulesToday as $sched): ?>
                            <tr>
                                <td><?= $sched['patrol_date'] ?></td>
                                <td><?= htmlspecialchars($sched['area']) ?></td>
                                <td><?= date("h:i A", strtotime($sched['time_from'])) ?></td>
                                <td><?= date("h:i A", strtotime($sched['time_to'])) ?></td>
                                <td>
                                    <span class="status-badge status-<?= str_replace(' ', '-', strtolower($sched['status'])) ?>">
                                        <?= ucfirst($sched['status']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <a href="update_own_patrol_status.php" class="btn btn-success mt-3">
                    <i class="fas fa-edit"></i> Update Patrol Status
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-info shadow-sm mb-4">
            <i class="fas fa-info-circle me-2"></i>No patrol scheduled for today.
        </div>
    <?php endif; ?>

    <!-- Upcoming -->
    <h5 class="mb-2 text-success"><i class="fas fa-calendar-plus me-1"></i> Upcoming Patrols</h5>
    <?php if (count($schedulesUpcoming)): ?>
        <div class="card shadow mb-4">
            <div class="card-body">
                <table class="table table-bordered table-striped">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Area</th>
                            <th>Time From</th>
                            <th>Time To</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($schedulesUpcoming as $sched): ?>
                            <tr>
                                <td><?= $sched['patrol_date'] ?></td>
                                <td><?= htmlspecialchars($sched['area']) ?></td>
                                <td><?= date("h:i A", strtotime($sched['time_from'])) ?></td>
                                <td><?= date("h:i A", strtotime($sched['time_to'])) ?></td>
                                <td>
                                    <span class="status-badge status-<?= str_replace(' ', '-', strtolower($sched['status'])) ?>">
                                        <?= ucfirst($sched['status']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php else: ?>
        <p>No upcoming patrols.</p>
    <?php endif; ?>


    <h5 class="mb-2 text-muted"><i class="fas fa-history me-1"></i> Past Patrols</h5>
    <?php if (count($schedulesPast)): ?>
        <div class="card shadow">
            <div class="card-body">
                <table class="table table-bordered table-striped">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Area</th>
                            <th>Time From</th>
                            <th>Time To</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($schedulesPast as $sched): ?>
                            <tr>
                                <td><?= $sched['patrol_date'] ?></td>
                                <td><?= htmlspecialchars($sched['area']) ?></td>
                                <td><?= date("h:i A", strtotime($sched['time_from'])) ?></td>
                                <td><?= date("h:i A", strtotime($sched['time_to'])) ?></td>
                                <td>
                                    <span class="status-badge status-<?= str_replace(' ', '-', strtolower($sched['status'])) ?>">
                                        <?= ucfirst($sched['status']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php else: ?>
        <p>No past patrols.</p>
    <?php endif; ?>
</div>

<?php if ($isOnDuty): ?>
<script>
if ("geolocation" in navigator) {
    navigator.geolocation.watchPosition(position => {
        fetch("update_location.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: `latitude=${position.coords.latitude}&longitude=${position.coords.longitude}`
        });
    }, error => {
        console.warn("Location access denied.");
    });
}
</script>
<?php else: ?>
<script>
console.log("GPS tracking disabled — not on duty.");
</script>
<?php endif; ?>

</body>
</html>
