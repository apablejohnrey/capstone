
<?php
require_once '../includes/db.php';

class UserLogin {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function login($username, $password) {
        $stmt = $this->conn->prepare("SELECT * FROM Users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            
            if ($user['lockout_time'] && strtotime($user['lockout_time']) > time()) {
                $remaining = ceil((strtotime($user['lockout_time']) - time()) / 60);
                return "Account is locked. Try again in $remaining minute(s).";
            }

            if (password_verify($password, $user['password'])) {
                
                $this->resetLoginAttempts($user['user_id']);

                session_start();
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];

                $role = $this->getUserRole($user['user_id']);

                if ($role !== 'Unknown') {
                    $_SESSION['user_type'] = strtolower($role);

                    switch ($role) {
                        case 'Resident':
                            header("Location: ../residents/resident_dashboard.php");
                            break;
                        case 'Official':
                            header("Location: ../officials/official_dashboard.php");
                            break;
                        case 'Tanod':
                            header("Location: ../tanods/tanod_dashboard.php");
                            break;
                    }
                    exit();
                } else {
                    return "User role not found.";
                }
            } else {
                
                $this->incrementFailedAttempts($user);
                $remaining = 4 - $user['failed_attempts']; 
                return "Incorrect password. " . ($remaining > 0 ? "$remaining attempt(s) left." : "Account locked for 1 minute.");
            }
        } else {
            return "Username not found.";
        }
    }

    private function getUserRole($user_id) {
        $stmt = $this->conn->prepare("SELECT resident_id FROM Residents WHERE user_id = ?");
        $stmt->execute([$user_id]);
        if ($stmt->fetch()) {
            return 'Resident';
        }

        $stmt = $this->conn->prepare("SELECT official_id FROM Barangay_Officials WHERE user_id = ?");
        $stmt->execute([$user_id]);
        if ($stmt->fetch()) {
            return 'Official';
        }

        $stmt = $this->conn->prepare("SELECT tanod_id FROM Tanods WHERE user_id = ?");
        $stmt->execute([$user_id]);
        if ($stmt->fetch()) {
            return 'Tanod';
        }

        return 'Unknown';
    }

    private function resetLoginAttempts($user_id) {
        $stmt = $this->conn->prepare("UPDATE Users SET failed_attempts = 0, lockout_time = NULL WHERE user_id = ?");
        $stmt->execute([$user_id]);
    }

    private function incrementFailedAttempts($user) {
        $attempts = $user['failed_attempts'] + 1;

        if ($attempts >= 5) {
            $lockoutMinutes = 1;
            $lockoutTime = date("Y-m-d H:i:s", strtotime("+$lockoutMinutes minutes"));
            $stmt = $this->conn->prepare("UPDATE Users SET failed_attempts = ?, lockout_time = ? WHERE user_id = ?");
            $stmt->execute([$attempts, $lockoutTime, $user['user_id']]);
        } else {
            $stmt = $this->conn->prepare("UPDATE Users SET failed_attempts = ? WHERE user_id = ?");
            $stmt->execute([$attempts, $user['user_id']]);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $database = new Database();
    $db = $database->connect();
    $login = new UserLogin($db);

    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $result = $login->login($username, $password);

    if ($result !== null && $result !== true) {
        header("Location: loginform.php?error=" . urlencode($result));
        exit();
    }
}
?>
