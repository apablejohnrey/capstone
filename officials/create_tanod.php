<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/encryption.php';


if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'official') {
    header("Location: ../authentication/loginform.php");
    exit();
}

class TanodController {
    private $conn;
    private $encryptor;

    public function __construct(PDO $db) {
        $this->conn = $db;
        $this->encryptor = new Encryptor(ENCRYPTION_KEY, ENCRYPTION_IV);
    }

    public function usernameExists($username): bool {
        $stmt = $this->conn->prepare("SELECT user_id FROM Users WHERE username = ?");
        $stmt->execute([$username]);
        return $stmt->rowCount() > 0;
    }

    public function createUser($username, $password): int {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->conn->prepare("INSERT INTO Users (username, password, role, status) VALUES (?, ?, 'tanod', 'Active')");
        $stmt->execute([$username, $hashed]);
        return $this->conn->lastInsertId();
    }

    public function createTanod($user_id, $fname, $lname, $email, $contact, $purok) {
        $encryptedContact = $this->encryptor->encrypt($contact);
        $stmt = $this->conn->prepare("INSERT INTO Tanods (user_id, fname, lname, email, contact_number, purok, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$user_id, $fname, $lname, $email, $encryptedContact, $purok]);
    }

    public function handleCreation($fname, $lname, $email, $contact, $purok, $username, $password) {
        if ($this->usernameExists($username)) {
            return ['error' => 'Username already exists.'];
        }

        $user_id = $this->createUser($username, $password);
        $this->createTanod($user_id, $fname, $lname, $email, $contact, $purok);

        return ['success' => 'Tanod account created successfully.'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fname = trim($_POST['fname']);
    $lname = trim($_POST['lname']);
    $email = trim($_POST['email']);
    $contact = trim($_POST['contact_number']);
    $purok = $_POST['purok'];
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (!$fname || !$lname || !$email || !$contact || !$purok || !$username || !$password) {
        header("Location: create_tanodform.php?error=" . urlencode("Please fill in all fields."));
        exit();
    }

    $db = new Database();
    $conn = $db->connect();
    $controller = new TanodController($conn);

    $result = $controller->handleCreation($fname, $lname, $email, $contact, $purok, $username, $password);

    if (isset($result['error'])) {
        header("Location: create_tanodform.php?error=" . urlencode($result['error']));
    } else {
        header("Location: create_tanodform.php?success=" . urlencode($result['success']));
    }
    exit();
}
