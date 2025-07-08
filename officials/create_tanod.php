<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'official') {
    header("Location: ../authentication/loginform.php");
    exit();
}

class TanodController {
    private $conn;

    public function __construct(PDO $db) {
        $this->conn = $db;
    }

    public function usernameExists($username): bool {
        $stmt = $this->conn->prepare("SELECT user_id FROM Users WHERE username = ?");
        $stmt->execute([$username]);
        return $stmt->rowCount() > 0;
    }

    public function createUser($username, $password): int {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->conn->prepare("INSERT INTO Users (username, password, status) VALUES (?, ?, 'Active')");
        $stmt->execute([$username, $hashed]);
        return $this->conn->lastInsertId();
    }

    public function createTanod($user_id, $name, $contact) {
        $stmt = $this->conn->prepare("INSERT INTO Tanods (user_id, name, contact_number) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $name, $contact]);
    }

    public function handleCreation($name, $contact, $username, $password) {
        if ($this->usernameExists($username)) {
            return ['error' => 'Username already exists.'];
        }

        $user_id = $this->createUser($username, $password);
        $this->createTanod($user_id, $name, $contact);

        return ['success' => 'Tanod account created successfully.'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $contact = trim($_POST['contact_number']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (!$name || !$contact || !$username || !$password) {
        header("Location: create_tanodform.php?error=Please fill in all fields.");
        exit();
    }

    $db = new Database();
    $conn = $db->connect();
    $controller = new TanodController($conn);

    $result = $controller->handleCreation($name, $contact, $username, $password);

    if (isset($result['error'])) {
        header("Location: create_tanodform.php?error=" . urlencode($result['error']));
    } else {
        header("Location: create_tanodform.php?success=" . urlencode($result['success']));
    }
    exit();
}

header("Location: create_tanodform.php");
exit();
