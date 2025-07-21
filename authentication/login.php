<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/CsrfToken.php';

class UserLogin {
    private PDO $conn;
    private string $ip;
    private string $userAgent;

    private const MAX_ATTEMPTS = 5; 

    public function __construct($db) {
        $this->conn = $db;
        $this->ip = $_SERVER['REMOTE_ADDR'] === '::1' ? '127.0.0.1' : $_SERVER['REMOTE_ADDR'];
        $this->userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    }

    public function login($username, $password): ?string {
        $stmt = $this->conn->prepare("SELECT * FROM Users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            if ($user['status'] !== 'Active') {
                $this->logAttempt($username, 'inactive_account');
                return "Your account is inactive. Please contact the barangay office.";
            }
            if (!empty($user['lockout_time']) && strtotime($user['lockout_time']) > time()) {
                $this->logAttempt($username, 'locked');
                $remaining = ceil((strtotime($user['lockout_time']) - time()) / 60);
                return "Account is locked. Try again in $remaining minute(s).";
            }
            if (password_verify($password, $user['password'])) {
                $this->resetLoginAttempts($user['user_id']);
                $this->logAttempt($username, 'success');

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
                }
                return "User role not found.";
            } else {
                $this->incrementFailedAttempts($user);
                $this->logAttempt($username, 'invalid_password');

                $latestAttempts = $this->getUserFailedAttempts($user['user_id']);
                if ($latestAttempts >= self::MAX_ATTEMPTS) {
                    $lockoutTime = $this->getUserLockoutTime($user['user_id']);
                    if ($lockoutTime && strtotime($lockoutTime) > time()) {
                        $remaining = ceil((strtotime($lockoutTime) - time()) / 60);
                        return "Your account is locked due to too many failed attempts. Please try again in $remaining minute(s).";
                    } else {
                        $this->resetLoginAttempts($user['user_id']);
                        return "Invalid username or password. You have 0 attempt(s) remaining.";
                    }
                }

                $remaining = max(0, self::MAX_ATTEMPTS - $latestAttempts);
                return "Invalid username or password. You have $remaining attempt(s) remaining.";
            }
        } else {
            usleep(500000);
            $this->logAttempt($username, 'no_user');
            return "Invalid username or password. You have " . self::MAX_ATTEMPTS . " attempt(s) remaining.";
        }
    }

    private function getUserRole($user_id): string {
        foreach ([
            ['Residents', 'resident_id', 'Resident'],
            ['Barangay_Officials', 'official_id', 'Official'],
            ['Tanods', 'tanod_id', 'Tanod']
        ] as [$table, $col, $role]) {
            $stmt = $this->conn->prepare("SELECT $col FROM $table WHERE user_id = ?");
            $stmt->execute([$user_id]);
            if ($stmt->fetch()) return $role;
        }
        return 'Unknown';
    }

    private function resetLoginAttempts($user_id): void {
        $stmt = $this->conn->prepare("UPDATE Users SET failed_attempts = 0, lockout_time = NULL WHERE user_id = ?");
        $stmt->execute([$user_id]);
    }

    private function incrementFailedAttempts($user): void {
        $attempts = $user['failed_attempts'] + 1;
        if ($attempts >= self::MAX_ATTEMPTS) {
            $lockoutTime = date("Y-m-d H:i:s", strtotime("+1 minute"));
            $stmt = $this->conn->prepare("UPDATE Users SET failed_attempts = ?, lockout_time = ? WHERE user_id = ?");
            $stmt->execute([$attempts, $lockoutTime, $user['user_id']]);
        } else {
            $stmt = $this->conn->prepare("UPDATE Users SET failed_attempts = ? WHERE user_id = ?");
            $stmt->execute([$attempts, $user['user_id']]);
        }
    }

    private function getUserFailedAttempts(int $user_id): int {
        $stmt = $this->conn->prepare("SELECT failed_attempts FROM Users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        return (int)$stmt->fetchColumn();
    }

    private function getUserLockoutTime(int $user_id): ?string {
        $stmt = $this->conn->prepare("SELECT lockout_time FROM Users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $lockout_time = $stmt->fetchColumn();
        return $lockout_time ?: null;
    }

    private function logAttempt($username, $status): void {
        $stmt = $this->conn->prepare("INSERT INTO login_logs (username, ip_address, status, attempt_time, user_agent)
                                      VALUES (?, ?, ?, NOW(), ?)");
        $stmt->execute([$username, $this->ip, $status, $this->userAgent]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    if (!isset($_POST['csrf_token']) || !CsrfToken::validate($_POST['csrf_token'])) {
        exit('Invalid CSRF token.');
    }
    if (empty($_POST['g-recaptcha-response'])) {
        header("Location: loginform.php?error=" . urlencode("Please complete the CAPTCHA."));
        exit();
    }

    $captcha = $_POST['g-recaptcha-response'];
    $secretKey = '6LejkH8rAAAAAPOsBSKM-iFcogx-u0uqBmtUE91i';
    $ip = $_SERVER['REMOTE_ADDR'];

    $verifyResponse = file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret=$secretKey&response=$captcha&remoteip=$ip");
    $responseData = json_decode($verifyResponse);

    if (!$responseData->success) {
        header("Location: loginform.php?error=" . urlencode("CAPTCHA verification failed."));
        exit();
    }

    $db = (new Database())->connect();
    $login = new UserLogin($db);

    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $result = $login->login($username, $password);
    if ($result !== null) {
        header("Location: loginform.php?error=" . urlencode($result));
        exit();
    }
}
?>
