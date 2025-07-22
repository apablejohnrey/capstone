<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../classes/Semaphore.php';
require_once __DIR__ . '/../includes/sms_templates.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/encryption.php';

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'official') {
    echo "Unauthorized access.";
    exit();
}

$db = new Database();
$conn = $db->connect();

$stmt = $conn->prepare("SELECT position FROM Barangay_Officials WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$official = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$official || !in_array(strtolower($official['position']), ['secretary', 'chairperson'])) {
    echo "Unauthorized access.";
    exit();
}

$encryptor = new Encryptor(ENCRYPTION_KEY, ENCRYPTION_IV);
$semaphore = new Semaphore();

$type = $_POST['type'] ?? '';
$template = '';
$numbers = [];
$recipientName = '';

if (strtolower($official['position']) === 'secretary') {
    if ($type === 'hearing_notice' || $type === 'summon') {
        $residentId = $_POST['resident_id'] ?? '';
        $date = $_POST['date'] ?? '';

        $stmt = $conn->prepare("SELECT CONCAT(fname, ' ', lname) AS fullname, contact_number FROM residents WHERE resident_id = ?");
        $stmt->execute([$residentId]);
        $resident = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$resident) {
            echo "Resident not found.";
            exit();
        }

        $recipientName = $resident['fullname'];
        $template = sms_template($type, ['name' => $recipientName, 'date' => $date]);
        $numbers[] = $encryptor->decrypt($resident['contact_number']);

    } elseif ($type === 'custom') {
        $customName = trim($_POST['custom_name'] ?? '');
        $customNumber = trim($_POST['custom_number'] ?? '');
        $customMessage = trim($_POST['custom_message'] ?? '');

        if (empty($customName) || empty($customNumber) || empty($customMessage)) {
            echo "All custom fields are required.";
            exit();
        }

        $recipientName = $customName;
        $template = "Dear $customName, $customMessage";
        $numbers[] = $customNumber;

    } else {
        echo "Invalid SMS type.";
        exit();
    }

} elseif (strtolower($official['position']) === 'chairperson') {
    if ($type === 'alert') {
        $incident = $_POST['incident'] ?? '';
        $date = $_POST['date'] ?? '';
        $puroks = $_POST['puroks'] ?? [];

        if (empty($puroks)) {
            echo "No puroks selected.";
            exit();
        }

        $placeholders = implode(',', array_fill(0, count($puroks), '?'));
        $stmt = $conn->prepare("SELECT fname, lname, contact_number FROM residents WHERE purok IN ($placeholders)");
        $stmt->execute($puroks);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($results as $resident) {
            $numbers[] = $encryptor->decrypt($resident['contact_number']);
        }

        $recipientName = "Multiple Recipients";
        $template = sms_template('alert', ['incident' => $incident, 'date' => $date]);

    } else {
        echo "Invalid SMS type.";
        exit();
    }
}

// Send SMS
if (empty($numbers)) {
    echo "No recipient numbers found.";
    exit();
}

$success = $semaphore->send($numbers, $template);

// Logging
if ($success) {
    $stmt = $conn->prepare("INSERT INTO sms_logs (user_id, recipient_name, contact_number, message, type) VALUES (?, ?, ?, ?, ?)");
    foreach ($numbers as $number) {
        $stmt->execute([
            $_SESSION['user_id'],
            $recipientName,
            $number,
            $template,
            $type
        ]);
    }
    echo "SMS sent successfully.";
} else {
    echo "Failed to send SMS.";
}
