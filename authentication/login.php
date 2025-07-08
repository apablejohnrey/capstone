<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/CsrfToken.php';

class UserLogin {
    private $conn;
    private $ip;
    private $userAgent;

    public function __construct($db) {
        $this->conn = $db;
        $this->ip = $_SERVER['REMOTE_ADDR'];
        $this->userAgent = $_SERVER['HTTP_USER_AGENT'];
    }

    public function login($username, $password) {
        if ($this->isIpBlocked()) {
            return "Too many failed attempts. Your IP is temporarily blocked.";
        }

        $stmt = $this->conn->prepare("SELECT * FROM Users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            if ($user['lockout_time'] && strtotime($user['lockout_time']) > time()) {
                $this->logAttempt($username, 'locked');
                return "Account is locked. Try again in " . ceil((strtotime($user['lockout_time']) - time()) / 60) . " minute(s).";
            }

            if (password_verify($password, $user['password'])) {
                $this->resetLoginAttempts($user['user_id']);
                $this->logAttempt($username, 'success');

                session_start();
                session_regenerate_id(true); 
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
                $this->logAttempt($username, 'invalid_password');
                $this->handleIpBlocking($username);

                $remaining = max(0, 4 - $user['failed_attempts'] - 1);
                return "Invalid username or password. You have $remaining attempt(s) remaining.";
            }
        } else {
            usleep(500000); 
            $this->logAttempt($username, 'no_user');
            $this->handleIpBlocking($username);
            $remaining = rand(1, 3);
            return "Invalid username or password. You have $remaining attempt(s) remaining.";
        }
    }

    private function getUserRole($user_id) {
        $stmt = $this->conn->prepare("SELECT resident_id FROM Residents WHERE user_id = ?");
        $stmt->execute([$user_id]);
        if ($stmt->fetch()) return 'Resident';

        $stmt = $this->conn->prepare("SELECT official_id FROM Barangay_Officials WHERE user_id = ?");
        $stmt->execute([$user_id]);
        if ($stmt->fetch()) return 'Official';

        $stmt = $this->conn->prepare("SELECT tanod_id FROM Tanods WHERE user_id = ?");
        $stmt->execute([$user_id]);
        if ($stmt->fetch()) return 'Tanod';

        return 'Unknown';
    }

    private function resetLoginAttempts($user_id) {
        $stmt = $this->conn->prepare("UPDATE Users SET failed_attempts = 0, lockout_time = NULL WHERE user_id = ?");
        $stmt->execute([$user_id]);
    }

    private function incrementFailedAttempts($user) {
        $attempts = $user['failed_attempts'] + 1;
        if ($attempts >= 5) {
            $lockoutTime = date("Y-m-d H:i:s", strtotime("+1 minute"));
            $stmt = $this->conn->prepare("UPDATE Users SET failed_attempts = ?, lockout_time = ? WHERE user_id = ?");
            $stmt->execute([$attempts, $lockoutTime, $user['user_id']]);
        } else {
            $stmt = $this->conn->prepare("UPDATE Users SET failed_attempts = ? WHERE user_id = ?");
            $stmt->execute([$attempts, $user['user_id']]);
        }
    }

    private function logAttempt($username, $status) {
        $stmt = $this->conn->prepare("INSERT INTO login_logs (username, ip_address, status, attempt_time, user_agent)
                                      VALUES (?, ?, ?, NOW(), ?)");
        $stmt->execute([$username, $this->ip, $status, $this->userAgent]);
    }

    private function isIpBlocked() {
        $stmt = $this->conn->prepare("SELECT block_until FROM blocked_ips WHERE ip_address = ? AND block_until > NOW()");
        $stmt->execute([$this->ip]);
        return $stmt->fetch() !== false;
    }

    private function handleIpBlocking($username) {
        $stmt = $this->conn->prepare("SELECT COUNT(*) FROM login_logs 
                                      WHERE ip_address = ? AND status IN ('invalid_password', 'no_user') 
                                      AND attempt_time > NOW() - INTERVAL 10 MINUTE");
        $stmt->execute([$this->ip]);
        $failCount = $stmt->fetchColumn();

        if ($failCount >= 10) {
            $blockUntil = date("Y-m-d H:i:s", strtotime("+5 minutes"));
            $stmt = $this->conn->prepare("INSERT INTO blocked_ips (ip_address, block_until) 
                                          VALUES (?, ?) 
                                          ON DUPLICATE KEY UPDATE block_until = VALUES(block_until)");
            $stmt->execute([$this->ip, $blockUntil]);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    if (!isset($_POST['csrf_token']) || !CsrfToken::validate($_POST['csrf_token'])) {
        echo 'Invalid CSRF token.';
        exit;
    }

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
