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
    echo "<p style='color:red;'>Access denied. Only the Captain can view this page.</p>";
    exit();
}


class PatrolSchedule {
    private PDO $conn;
    public function __construct(PDO $conn) {
        $this->conn = $conn;
    }

    public function getScheduleById($id): ?array {
        $stmt = $this->conn->prepare("SELECT * FROM Patrol_Schedule WHERE schedule_id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getTanods(): array {
        $stmt = $this->conn->query("SELECT tanod_id, name FROM Tanods ORDER BY name ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateSchedule($id, $tanod_id, $area, $date, $from, $to, $status): bool {
        if (strtotime($from) >= strtotime($to)) {
            return false;
        }

        $stmt = $this->conn->prepare("UPDATE Patrol_Schedule SET tanod_id = ?, area = ?, patrol_date = ?, time_from = ?, time_to = ?, status = ? WHERE schedule_id = ?");
        return $stmt->execute([$tanod_id, $area, $date, $from, $to, $status, $id]);
    }

    public function notifyTanod($tanod_id, $message) {
        $stmt = $this->conn->prepare("INSERT INTO notifications (user_id, message, created_at) VALUES (
            (SELECT user_id FROM Tanods WHERE tanod_id = ?), ?, NOW())");
        $stmt->execute([$tanod_id, $message]);
    }
}

$scheduleObj = new PatrolSchedule($conn);

// ✅ Get schedule ID
$schedule_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$schedule = $scheduleObj->getScheduleById($schedule_id);

if (!$schedule) {
    $_SESSION['error'] = "Schedule not found.";
    header("Location: schedule.php");
    exit();
}

// ✅ Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tanod_id = $_POST['tanod_id'];
    $area = $_POST['area'];
    $date = $_POST['patrol_date'];
    $from = $_POST['time_from'];
    $to = $_POST['time_to'];
    $status = $_POST['status'];

    if ($scheduleObj->updateSchedule($schedule_id, $tanod_id, $area, $date, $from, $to, $status)) {
        $scheduleObj->notifyTanod($tanod_id, "Your patrol schedule has been updated for $date.");
        $_SESSION['success'] = "Schedule updated successfully.";
        header("Location: schedule.php");
        exit();
    } else {
        $error = "Time To must be greater than Time From.";
    }
}

$tanods = $scheduleObj->getTanods();
$areas = ['Purok 1', 'Purok 2', 'Purok 3', 'Purok 4', 'Purok 5', 'Purok 6', 'Purok 7'];
$statuses = ['Scheduled', 'Completed', 'Missed'];
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Patrol Schedule</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">
    <div class="container">
        <h2>Edit Patrol Schedule</h2>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>

        <form method="post" class="card p-4 shadow-sm">
            <div class="mb-3">
                <label for="tanod_id" class="form-label">Tanod</label>
                <select name="tanod_id" id="tanod_id" class="form-select" required>
                    <option value="">-- Select Tanod --</option>
                    <?php foreach ($tanods as $t): ?>
                        <option value="<?= $t['tanod_id'] ?>" <?= $t['tanod_id'] == $schedule['tanod_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($t['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label for="area" class="form-label">Area</label>
                <select name="area" id="area" class="form-select" required>
                    <?php foreach ($areas as $a): ?>
                        <option value="<?= $a ?>" <?= $schedule['area'] == $a ? 'selected' : '' ?>><?= $a ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label for="patrol_date" class="form-label">Date</label>
                <input type="date" name="patrol_date" id="patrol_date" value="<?= $schedule['patrol_date'] ?>" class="form-control" required>
            </div>

            <div class="row mb-3">
                <div class="col">
                    <label for="time_from" class="form-label">Time From</label>
                    <input type="time" name="time_from" id="time_from" value="<?= $schedule['time_from'] ?>" class="form-control" required>
                </div>
                <div class="col">
                    <label for="time_to" class="form-label">Time To</label>
                    <input type="time" name="time_to" id="time_to" value="<?= $schedule['time_to'] ?>" class="form-control" required>
                </div>
            </div>

            <div class="mb-3">
                <label for="status" class="form-label">Status</label>
                <select name="status" id="status" class="form-select">
                    <?php foreach ($statuses as $s): ?>
                        <option value="<?= $s ?>" <?= $schedule['status'] == $s ? 'selected' : '' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="d-flex justify-content-between">
                <a href="schedule.php" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Update Schedule</button>
            </div>
        </form>
    </div>
</body>
</html>
