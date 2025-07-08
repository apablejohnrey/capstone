<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'official') {
    header("Location: ../authentication/loginform.php");
    exit();
}

// Verify Chairperson
$db = new Database();
$conn = $db->connect();
$stmt = $conn->prepare("SELECT position FROM Barangay_Officials WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$position = $stmt->fetchColumn();
if (!$position || strtolower($position) !== 'chairperson') {
    echo "<p style='color:red;'>Access denied. Only the Captain can view this page.</p>";
    exit();
}

class PatrolManager {
    private PDO $conn;
    public function __construct(PDO $conn) {
        $this->conn = $conn;
    }

    public function getTanods(): array {
        $stmt = $this->conn->query("SELECT tanod_id, name FROM Tanods ORDER BY name ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSchedules(array $filter = []): array {
        $sql = "SELECT ps.*, t.name AS tanod_name 
                FROM Patrol_Schedule ps
                JOIN Tanods t ON ps.tanod_id = t.tanod_id";
        $clauses = [];
        $params = [];

        if (!empty($filter['tanod_id'])) {
            $clauses[] = "ps.tanod_id = ?";
            $params[] = $filter['tanod_id'];
        }

        if (!empty($filter['patrol_date'])) {
            $clauses[] = "ps.patrol_date = ?";
            $params[] = $filter['patrol_date'];
        }

        if ($clauses) {
            $sql .= " WHERE " . implode(" AND ", $clauses);
        }

        $sql .= " ORDER BY ps.patrol_date DESC, ps.time_from ASC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function deleteSchedule(int $id): void {
        $stmt = $this->conn->prepare("DELETE FROM Patrol_Schedule WHERE schedule_id = ?");
        $stmt->execute([$id]);
    }
}

$manager = new PatrolManager($conn);

// ✅ Delete action
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $manager->deleteSchedule((int)$_GET['delete']);
    $_SESSION['success'] = "Patrol schedule deleted successfully.";
    header("Location: schedule.php");
    exit();
}

// ✅ Filters
$filters = [
    'tanod_id' => $_GET['tanod_id'] ?? '',
    'patrol_date' => $_GET['patrol_date'] ?? ''
];

$schedules = $manager->getSchedules($filters);
$tanods = $manager->getTanods();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Patrol Schedules</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f4f6f9;
        }
        .action-buttons a {
            margin-right: 6px;
        }
    </style>
</head>
<body>
<?php include 'navofficial.php'; ?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-calendar-check me-2"></i>Manage Patrol Schedules</h2>
        <div>
            <a href="create_patrol_schedule.php" class="btn btn-success"><i class="fas fa-plus"></i> Create Schedule</a>
            <a href="monitor_patrols.php" class="btn btn-secondary"><i class="fas fa-eye"></i> Monitor Patrols</a>
        </div>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>

    <!-- 🔍 Filter Form -->
    <form method="get" class="row g-3 mb-4">
        <div class="col-md-4">
            <label for="tanod_id" class="form-label">Filter by Tanod</label>
            <select name="tanod_id" id="tanod_id" class="form-select">
                <option value="">-- All Tanods --</option>
                <?php foreach ($tanods as $tanod): ?>
                    <option value="<?= $tanod['tanod_id'] ?>" <?= $filters['tanod_id'] == $tanod['tanod_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($tanod['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label for="patrol_date" class="form-label">Filter by Date</label>
            <input type="date" name="patrol_date" id="patrol_date" class="form-control" value="<?= htmlspecialchars($filters['patrol_date']) ?>">
        </div>
        <div class="col-md-4 d-flex align-items-end">
            <button type="submit" class="btn btn-primary me-2"><i class="fas fa-filter"></i> Filter</button>
            <a href="schedule.php" class="btn btn-outline-secondary">Reset</a>
        </div>
    </form>

    <!-- 📋 Schedule Table -->
    <?php if (count($schedules)): ?>
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>Tanod</th>
                        <th>Area</th>
                        <th>Date</th>
                        <th>Time From</th>
                        <th>Time To</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($schedules as $sched): ?>
                        <tr>
                            <td><?= htmlspecialchars($sched['tanod_name']) ?></td>
                            <td><?= htmlspecialchars($sched['area']) ?></td>
                            <td><?= htmlspecialchars($sched['patrol_date']) ?></td>
                            <td><?= date("h:i A", strtotime($sched['time_from'])) ?></td>
                            <td><?= date("h:i A", strtotime($sched['time_to'])) ?></td>
                            <td>
                                <span class="badge 
                                    <?= $sched['status'] === 'Completed' ? 'bg-success' : 
                                       ($sched['status'] === 'Missed' ? 'bg-danger' : 'bg-warning') ?>">
                                    <?= $sched['status'] ?>
                                </span>
                            </td>
                            <td class="action-buttons">
                                <a href="edit_schedule.php?id=<?= $sched['schedule_id'] ?>" class="btn btn-sm btn-info" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="schedule.php?delete=<?= $sched['schedule_id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this patrol schedule?')" title="Delete">
                                    <i class="fas fa-trash-alt"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert alert-info">No patrol schedules found.</div>
    <?php endif; ?>
</div>
</body>
</html>
