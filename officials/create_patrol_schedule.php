<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'official') {
    header("Location: ../authentication/loginform.php");
    exit();
}

$db = new Database();
$conn = $db->connect();

// Check if user is Chairperson
$stmt = $conn->prepare("SELECT position FROM Barangay_Officials WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$official = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$official || strtolower($official['position']) !== 'chairperson') {
    http_response_code(403);
    echo "<p style='color:red;'>Access Denied. Only the Barangay Captain can access this page.</p>";
    exit();
}

class PatrolScheduler {
    private PDO $conn;

    public function __construct(PDO $db) {
        $this->conn = $db;
    }

    public function getTanods(): array {
        $stmt = $this->conn->query("SELECT tanod_id, fname, lname FROM Tanods ORDER BY fname ASC, lname ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createSchedule($tanodId, $area, $date, $from, $to): string {
        // Prevent overlapping schedules
        $stmt = $this->conn->prepare("
            SELECT * FROM Patrol_Schedule 
            WHERE tanod_id = ? AND patrol_date = ? 
            AND ((time_from <= ? AND time_to >= ?) OR (time_from <= ? AND time_to >= ?))
        ");
        $stmt->execute([$tanodId, $date, $from, $from, $to, $to]);
        if ($stmt->fetch()) {
            return "Conflict: This tanod already has a schedule during this time.";
        }

        $insert = $this->conn->prepare("
            INSERT INTO Patrol_Schedule (tanod_id, area, patrol_date, time_from, time_to) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $insert->execute([$tanodId, $area, $date, $from, $to]);

        return "Patrol schedule created successfully.";
    }
}

$scheduler = new PatrolScheduler($conn);
$tanods = $scheduler->getTanods();
$message = "";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tanodId = $_POST['tanod_id'] ?? null;
    $area = $_POST['area'] ?? '';
    $patrolDate = $_POST['patrol_date'] ?? '';
    $timeFrom = $_POST['time_from'] ?? '';
    $timeTo = $_POST['time_to'] ?? '';

    if ($tanodId && $area && $patrolDate && $timeFrom && $timeTo) {
        $message = $scheduler->createSchedule($tanodId, $area, $patrolDate, $timeFrom, $timeTo);
    } else {
        $message = "All fields are required.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Patrol Schedule</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/navofficial.css">
    
    <style>
        body {
            background-color: #f5f5f5;
        }

        .main-content-wrapper {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 250px;
            background-color: #343a40;
        }

        .content {
            flex-grow: 1;
            padding: 20px;
        }

        .card {
            max-width: 100%;
        }

        .btn-back {
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <!-- Bootstrap JS and dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <div class="main-content-wrapper">
        <!-- Sidebar -->
        <div class="sidebar">
            <?php include 'navofficial.php'; ?>
        </div>

        <!-- Main Content -->
        <div class="content container-fluid">
            <h2 class="mb-4">Create Patrol Schedule</h2>

            <a href="schedule.php" class="btn btn-secondary btn-back">← Back to Schedules</a>

            <?php if ($message): ?>
                <div class="alert alert-info"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <form method="POST" class="card shadow p-4 bg-light">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="tanod_id" class="form-label">Select Tanod</label>
                        <select name="tanod_id" id="tanod_id" class="form-select" required>
                            <option value="">-- Choose Tanod --</option>
                            <?php foreach ($tanods as $tanod): ?>
                                <option value="<?= $tanod['tanod_id'] ?>">
                                    <?= htmlspecialchars($tanod['fname'] . ' ' . $tanod['lname']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="area" class="form-label">Area</label>
                        <select name="area" id="area" class="form-select" required>
                            <option value="">-- Select Area --</option>
                            <?php
                            $areas = ['Purok 1', 'Purok 2', 'Purok 3', 'Purok 4', 'Purok 5', 'Purok 6', 'Purok 7'];
                            foreach ($areas as $area):
                            ?>
                                <option value="<?= $area ?>"><?= $area ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="patrol_date" class="form-label">Date</label>
                        <input type="date" name="patrol_date" id="patrol_date" class="form-control" required min="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="time_from" class="form-label">Time From</label>
                        <input type="time" name="time_from" id="time_from" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label for="time_to" class="form-label">Time To</label>
                        <input type="time" name="time_to" id="time_to" class="form-control" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">Create Schedule</button>
            </form>
        </div>
    </div>
</body>
</html>
