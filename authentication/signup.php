<?php
require_once '../includes/db.php';
require_once '../includes/encryption.php';
require_once '../includes/config.php';

class ResidentSignup {
    private $conn;
    private $encryptor;

    public function __construct($db) {
        $this->conn = $db;
        $this->encryptor = new Encryptor(ENCRYPTION_KEY, ENCRYPTION_IV);
    }

    public function registerResident($username, $password, $fname, $lname, $contact_number, $purok) {
        try {
            $this->conn->beginTransaction();

            $stmt = $this->conn->prepare("INSERT INTO Users (username, password, role, status) VALUES (?, ?, 'resident', 'Active')");
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            $stmt->execute([$username, $hashedPassword]);

            $user_id = $this->conn->lastInsertId();

            $encryptedContact = $this->encryptor->encrypt($contact_number);
            $stmt = $this->conn->prepare("INSERT INTO Residents (user_id, fname, lname, contact_number, purok) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $fname, $lname, $encryptedContact, $purok]);

            $this->conn->commit();
            return true;

        } catch (Exception $e) {
            $this->conn->rollBack();
            return "Error: " . $e->getMessage();
        }
    }
}

if (isset($_POST['signup'])) {
    $database = new Database();
    $db = $database->connect();

    $signup = new ResidentSignup($db);

    $username = $_POST['username'];
    $password = $_POST['password'];
    $fname = $_POST['fname'];
    $lname = $_POST['lname'];
    $contact_number = $_POST['contact_number'];
    $purok = $_POST['purok'];

    $result = $signup->registerResident($username, $password, $fname, $lname, $contact_number, $purok);

    if ($result === true) {
        header("Location: loginform.php?signup=success");
        exit();
    } else {
        echo $result;
    }
}
?>
