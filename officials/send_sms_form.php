<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
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

// Fetch residents
$residentStmt = $conn->query("SELECT resident_id, fname, lname, contact_number FROM residents");
$residents = [];
while ($row = $residentStmt->fetch(PDO::FETCH_ASSOC)) {
    $row['fullname'] = $row['fname'] . ' ' . $row['lname'];
    $row['contact_number'] = $encryptor->decrypt($row['contact_number']);
    $residents[] = $row;
}

// Fetch puroks
$puroks = $conn->query("SELECT DISTINCT purok FROM residents")->fetchAll(PDO::FETCH_COLUMN);

// Fetch SMS logs
$logStmt = $conn->prepare("SELECT recipient_name, contact_number, message, sent_at, type FROM sms_logs WHERE user_id = ? ORDER BY sent_at DESC LIMIT 20");
$logStmt->execute([$_SESSION['user_id']]);
$smsLogs = $logStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Send SMS</title>
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.css" rel="stylesheet">
    <style>

        h2, h3 {
            margin-top: 0;
        }
        label {
            display: block;
            margin-top: 10px;
        }
        input, select, textarea {
            width: 100%;
            max-width: 400px;
            padding: 8px;
            margin-bottom: 10px;
        }
        button {
            padding: 8px 15px;
            margin-right: 10px;
        }
        table {
            width: 100%;
            max-width: 1000px;
            border-collapse: collapse;
            margin-top: 30px;
        }
        th, td {
            padding: 8px;
            border: 1px solid #ccc;
            text-align: left;
            font-size: 14px;
        }
        th {
            background: #f2f2f2;
        }
        .log-section {
            margin-top: 40px;
        }
    </style>
</head>
<body>

<?php require_once __DIR__ . '/../officials/navofficial.php'; ?>

<div class="main-content-wrapper">
    <h2>Send SMS</h2>

    <form method="POST" action="send_sms.php">
        <label for="type">SMS Type:</label>
        <select name="type" id="type" onchange="updateForm()" required>
            <?php if (strtolower($official['position']) === 'secretary'): ?>
                <option value="hearing_notice">Hearing Notice</option>
                <option value="summon">Summon</option>
                <option value="custom">Custom</option>
            <?php elseif (strtolower($official['position']) === 'chairperson'): ?>
                <option value="alert">Emergency Alert</option>
            <?php endif; ?>
        </select>

        <div id="formFields"></div>

        <div style="margin-top: 15px;">
            <button type="submit">Send SMS</button>
            <button type="button" onclick="cancelForm()">Cancel</button>
        </div>
    </form>

    <!-- SMS Logs -->
    <div class="log-section">
        <h3>SMS Logs</h3>
        <?php if (count($smsLogs) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Recipient Name</th>
                    <th>Contact Number</th>
                    <th>Message</th>
                    <th>Type</th>
                    <th>Sent At</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($smsLogs as $log): ?>
                    <tr>
                        <td><?= htmlspecialchars($log['recipient_name']) ?></td>
                        <td><?= htmlspecialchars($log['contact_number']) ?></td>
                        <td><?= htmlspecialchars($log['message']) ?></td>
                        <td><?= ucfirst($log['type']) ?></td>
                        <td><?= date('M d, Y h:i A', strtotime($log['sent_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
            <p>No SMS logs found.</p>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<script>
const residents = <?= json_encode($residents) ?>;
const puroks = <?= json_encode($puroks) ?>;

function updateForm() {
    const type = document.getElementById('type').value;
    const container = document.getElementById('formFields');
    container.innerHTML = '';

    if (type === 'hearing_notice' || type === 'summon') {
        const label = document.createElement('label');
        label.textContent = "Select Resident:";
        container.appendChild(label);

        const select = document.createElement('select');
        select.name = 'resident_id';
        select.id = 'residentSelect';
        select.required = true;

        residents.forEach(r => {
            const opt = document.createElement('option');
            opt.value = r.resident_id;
            opt.textContent = `${r.fullname} (${r.contact_number})`;
            select.appendChild(opt);
        });

        container.appendChild(select);

        const dateLabel = document.createElement('label');
        dateLabel.textContent = "Date:";
        const date = document.createElement('input');
        date.type = 'date';
        date.name = 'date';
        date.required = true;

        container.appendChild(dateLabel);
        container.appendChild(date);

        setTimeout(() => new TomSelect('#residentSelect'), 10);

    } else if (type === 'custom') {
        const nameLabel = document.createElement('label');
        nameLabel.textContent = 'Recipient Name:';
        const nameInput = document.createElement('input');
        nameInput.type = 'text';
        nameInput.name = 'custom_name';
        nameInput.required = true;

        const numberLabel = document.createElement('label');
        numberLabel.textContent = 'Mobile Number:';
        const numberInput = document.createElement('input');
        numberInput.type = 'text';
        numberInput.name = 'custom_number';
        numberInput.required = true;
        numberInput.placeholder = 'e.g. 09171234567';

        const messageLabel = document.createElement('label');
        messageLabel.textContent = 'Message:';
        const textarea = document.createElement('textarea');
        textarea.name = 'custom_message';
        textarea.required = true;
        textarea.placeholder = 'Enter custom message...';

        container.appendChild(nameLabel);
        container.appendChild(nameInput);
        container.appendChild(numberLabel);
        container.appendChild(numberInput);
        container.appendChild(messageLabel);
        container.appendChild(textarea);

    } else if (type === 'alert') {
        const purokLabel = document.createElement('label');
        purokLabel.textContent = "Select Puroks:";
        const select = document.createElement('select');
        select.name = 'puroks[]';
        select.id = 'purokSelect';
        select.multiple = true;
        select.required = true;

        puroks.forEach(p => {
            const opt = document.createElement('option');
            opt.value = p;
            opt.textContent = p;
            select.appendChild(opt);
        });

        container.appendChild(purokLabel);
        container.appendChild(select);

        const incidentLabel = document.createElement('label');
        incidentLabel.textContent = "Incident Type:";
        const incidentInput = document.createElement('input');
        incidentInput.name = 'incident';
        incidentInput.required = true;

        const dateLabel = document.createElement('label');
        dateLabel.textContent = "Date:";
        const dateInput = document.createElement('input');
        dateInput.type = 'date';
        dateInput.name = 'date';
        dateInput.required = true;

        container.appendChild(incidentLabel);
        container.appendChild(incidentInput);
        container.appendChild(dateLabel);
        container.appendChild(dateInput);

        setTimeout(() => new TomSelect('#purokSelect'), 10);
    }
}

function cancelForm() {
    if (confirm('Are you sure you want to cancel and leave this page?')) {
        window.location.href = 'official_dashboard.php';
    }
}

updateForm();
</script>

</body>
</html>
