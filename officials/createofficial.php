<?php
require_once '../includes/db.php';
require_once '../includes/encryption.php';
require_once '../includes/config.php';

class OfficialCreator {
    private $conn;
    private $encryptor;

    public function __construct($db) {
        $this->conn = $db;
        $this->encryptor = new Encryptor(ENCRYPTION_KEY, ENCRYPTION_IV);
    }

    public function createOfficial($username, $password, $name, $position, $contact_number) {
        try {
            $this->conn->beginTransaction();

            $stmt = $this->conn->prepare("INSERT INTO Users (username, password, role, status) VALUES (?, ?, 'official', 'Active')");
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            $stmt->execute([$username, $hashedPassword]);

            $user_id = $this->conn->lastInsertId();

            $encryptedContact = $this->encryptor->encrypt($contact_number);
            $stmt = $this->conn->prepare("INSERT INTO Barangay_Officials (user_id, name, position, contact_number, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$user_id, $name, $position, $encryptedContact]);

            $this->conn->commit();
            return true;

        } catch (Exception $e) {
            $this->conn->rollBack();
            return "Error: " . $e->getMessage();
        }
    }
}

if (isset($_POST['create_official'])) {
    $db = new Database();
    $conn = $db->connect();

    $creator = new OfficialCreator($conn);

    $username = $_POST['username'];
    $password = $_POST['password'];
    $name = $_POST['name'];
    $position = $_POST['position']; 
    $contact_number = $_POST['contact_number'];

    $result = $creator->createOfficial($username, $password, $name, $position, $contact_number);

    if ($result === true) {
        echo "Official account created successfully.";
    } else {
        echo $result;
    }
}
?>
