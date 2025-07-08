<?php
session_start();
require_once '../includes/db.php';

class LoginLogViewer {
    private PDO $conn;
    private int $limit = 20;

    public function __construct(PDO $db) {
        $this->conn = $db;
    }

    public function fetchLogs(int $page): array {
        $offset = ($page - 1) * $this->limit;
        $stmt = $this->conn->prepare("SELECT * FROM login_logs ORDER BY attempt_time DESC LIMIT :limit OFFSET :offset");
        $stmt->bindValue(':limit', $this->limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTotalPages(): int {
        $stmt = $this->conn->query("SELECT COUNT(*) FROM login_logs");
        $total = $stmt->fetchColumn();
        return ceil($total / $this->limit);
    }

    public function getLimit(): int {
        return $this->limit;
    }
}

// Auth check
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'official') {
    header("Location: ../authentication/loginform.php");
    exit;
}

// Page and DB logic
$page = max((int)($_GET['page'] ?? 1), 1);
$database = new Database();
$db = $database->connect();
$viewer = new LoginLogViewer($db);
$logs = $viewer->fetchLogs($page);
$totalPages = $viewer->getTotalPages();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login Logs</title>
    <link rel="stylesheet" href="../css/navofficial.css">
    <style>
        body {
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f9f9f9;
            display: flex;
        }

        main {
            flex: 1;
            padding: 30px;
            overflow-x: auto;
            margin-left: 250px;
        }

        h2 {
            text-align: center;
            margin-top: 0;
            color: #333;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 14px;
            background: white;
            box-shadow: 0 0 5px rgba(0,0,0,0.1);
        }

        th, td {
            border: 1px solid #ccc;
            padding: 10px 12px;
            text-align: left;
        }

        th {
            background-color: #2240c4;
            color: white;
        }

        tr:nth-child(even) {
            background-color: #f8f8f8;
        }

        .status-success { color: green; font-weight: bold; }
        .status-invalid_password { color: orange; font-weight: bold; }
        .status-no_user { color: red; font-weight: bold; }
        .status-locked { color: crimson; font-weight: bold; }

        .pagination {
            text-align: center;
            margin-top: 20px;
        }

        .pagination a {
            padding: 8px 16px;
            margin: 0 5px;
            background-color: #2240c4;
            color: white;
            text-decoration: none;
            border-radius: 4px;
        }

        .pagination a:hover {
            background-color: #1830a0;
        }
    </style>
</head>
<body>

<?php include 'navofficial.php'; ?>

<main>
    <h2>Login Attempt Logs (Newest First)</h2>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Username</th>
                <th>IP Address</th>
                <th>Status</th>
                <th>Attempt Time</th>
                <th>User Agent</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($logs): ?>
                <?php foreach ($logs as $i => $log): ?>
                    <tr>
                        <td><?= (($page - 1) * $viewer->getLimit()) + $i + 1 ?></td>
                        <td><?= htmlspecialchars($log['username'] ?: '-', ENT_QUOTES) ?></td>
                        <td><?= htmlspecialchars($log['ip_address']) ?></td>
                        <td class="status-<?= $log['status'] ?>"><?= ucfirst(str_replace('_', ' ', $log['status'])) ?></td>
                        <td><?= htmlspecialchars($log['attempt_time']) ?></td>
                        <td><?= htmlspecialchars($log['user_agent']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="6" style="text-align: center;">No logs found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?page=<?= $page - 1 ?>">← Previous</a>
        <?php endif; ?>
        <?php if ($page < $totalPages): ?>
            <a href="?page=<?= $page + 1 ?>">View More →</a>
        <?php endif; ?>
    </div>
</main>

</body>
</html>
