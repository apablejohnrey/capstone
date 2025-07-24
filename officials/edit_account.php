<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/encryption.php';
require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION)) session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'official') {
    header("Location: ../authentication/loginform.php");
    exit();
}

class AccountManager {
    private PDO $conn;
    public int $userId;
    public string $role;
    public string $table;

    public function __construct(PDO $conn, int $userId, string $role) {
        $this->conn = $conn;
        $this->userId = $userId;
        $this->role = $role;

        $tables = [
            'resident' => 'Residents',
            'official' => 'Barangay_Officials',
            'tanod' => 'Tanods'
        ];

        if (!array_key_exists($role, $tables)) {
            throw new Exception("Invalid role.");
        }

        $this->table = $tables[$role];
    }

    public function fetchAccount(): array {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE user_id = ?");
        $stmt->execute([$this->userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            throw new Exception("Account not found.");
        }

        return $result;
    }

    public function updateAccount(string $fname, string $lname, string $contact, Encryptor $encryptor, ?string $position = null): void {
        $encrypted = $encryptor->encrypt($contact);

        if ($this->role === 'resident') {
            $stmt = $this->conn->prepare("UPDATE Residents SET fname = ?, lname = ?, contact_number = ? WHERE user_id = ?");
            $stmt->execute([$fname, $lname, $encrypted, $this->userId]);
        } elseif ($this->role === 'official') {
            $stmt = $this->conn->prepare("UPDATE Barangay_Officials SET fname = ?, lname = ?, contact_number = ?, position = ? WHERE user_id = ?");
            $stmt->execute([$fname, $lname, $encrypted, $position, $this->userId]);
        } else {
            $stmt = $this->conn->prepare("UPDATE Tanods SET fname = ?, lname = ?, contact_number = ? WHERE user_id = ?");
            $stmt->execute([$fname, $lname, $encrypted, $this->userId]);
        }
    }

    public function changeRole(string $newRole, string $position = 'Secretary', Encryptor $encryptor): string {
        $account = $this->fetchAccount();
        $contact = $encryptor->decrypt($account['contact_number'] ?? '') ?: '';
        $encrypted = $encryptor->encrypt($contact);

        if ($newRole === $this->role) {
            if ($this->role === 'official' && isset($account['position']) && $account['position'] !== $position) {
                $stmt = $this->conn->prepare("UPDATE Barangay_Officials SET position = ? WHERE user_id = ?");
                $stmt->execute([$position, $this->userId]);
                return "Position updated successfully.";
            }
            return "No changes made.";
        }

        $stmt = $this->conn->prepare("DELETE FROM {$this->table} WHERE user_id = ?");
        $stmt->execute([$this->userId]);

        switch ($newRole) {
            case 'resident':
                $stmt = $this->conn->prepare("INSERT INTO Residents (user_id, fname, lname, contact_number, purok) VALUES (?, ?, ?, ?, 'Purok 1')");
                $stmt->execute([$this->userId, $account['fname'], $account['lname'], $encrypted]);
                break;
            case 'tanod':
                $stmt = $this->conn->prepare("INSERT INTO Tanods (user_id, fname, lname, contact_number, purok) VALUES (?, ?, ?, ?, 'Purok 1')");
                $stmt->execute([$this->userId, $account['fname'], $account['lname'], $encrypted]);
                break;
            case 'official':
                $stmt = $this->conn->prepare("INSERT INTO Barangay_Officials (user_id, fname, lname, contact_number, position, purok) VALUES (?, ?, ?, ?, ?, 'Purok 1')");
                $stmt->execute([$this->userId, $account['fname'], $account['lname'], $encrypted, $position]);
                break;
            default:
                throw new Exception("Invalid role.");
        }

        $stmt = $this->conn->prepare("UPDATE Users SET role = ? WHERE user_id = ?");
        $stmt->execute([$newRole, $this->userId]);

        return "Role changed successfully.";
    }
}

$db = new Database();
$conn = $db->connect();

$userId = $_GET['user_id'] ?? null;
$role = $_GET['role'] ?? null;

if (!$userId || !$role) die("Invalid request.");

$manager = new AccountManager($conn, (int)$userId, $role);
$encryptor = new Encryptor(ENCRYPTION_KEY, ENCRYPTION_IV);
$account = $manager->fetchAccount();
$account['contact_number'] = $encryptor->decrypt($account['contact_number'] ?? '') ?: '';
$currentPosition = $account['position'] ?? null;

if (isset($_POST['update'])) {
    $manager->updateAccount(trim($_POST['fname']), trim($_POST['lname']), trim($_POST['contact']), $encryptor, $_POST['position'] ?? null);
    header("Location: manage_accounts.php");
    exit();
}

if (isset($_POST['confirm_change'])) {
    $newRole = $_POST['modal_new_role'];
    $position = $_POST['modal_position'] ?? 'Secretary';
    $password = $_POST['modal_password'];

    $stmt = $conn->prepare("SELECT password FROM Users WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($password, $user['password'])) {
        $error = "Invalid password.";
    } else {
        $result = $manager->changeRole($newRole, $position, $encryptor);
        if (str_contains($result, "successfully")) {
            header("Location: manage_accounts.php");
            exit();
        } else {
            $error = $result;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Account</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <div class="card p-4">
        <h3>Edit <?= ucfirst($role) ?> Account</h3>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">First Name</label>
                    <input type="text" name="fname" class="form-control" value="<?= htmlspecialchars($account['fname']) ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Last Name</label>
                    <input type="text" name="lname" class="form-control" value="<?= htmlspecialchars($account['lname']) ?>" required>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Contact Number</label>
                <input type="text" name="contact" class="form-control" value="<?= htmlspecialchars($account['contact_number']) ?>" required>
            </div>
            <?php if ($role === 'official'): ?>
                <div class="mb-3">
                    <label class="form-label">Position</label>
                    <select name="position" class="form-select">
                        <option value="Secretary" <?= $currentPosition === 'Secretary' ? 'selected' : '' ?>>Secretary</option>
                        <option value="Chairperson" <?= $currentPosition === 'Chairperson' ? 'selected' : '' ?>>Chairperson</option>
                    </select>
                </div>
            <?php endif; ?>
            <button type="submit" name="update" class="btn btn-primary">Update</button>
            <a href="manage_accounts.php" class="btn btn-secondary">Cancel</a>
        </form>

        <hr>

        <form onsubmit="event.preventDefault(); openConfirmModal();">
            <div class="row g-3 align-items-end">
                <div class="col-md-6">
                    <label class="form-label">New Role</label>
                    <select name="new_role" id="new_role" class="form-select" onchange="togglePositionField()" required>
                        <option value="" disabled selected>-- Select Role --</option>
                        <?php foreach (['resident', 'official', 'tanod'] as $r): ?>
                            <?php if ($r !== $role): ?>
                                <option value="<?= $r ?>"><?= ucfirst($r) ?></option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 d-none" id="position_container">
                    <label class="form-label">Position (if Official)</label>
                    <select name="position" id="position" class="form-select">
                        <option value="Secretary">Secretary</option>
                        <option value="Chairperson">Chairperson</option>
                    </select>
                </div>
            </div>
            <div class="mt-3">
                <button type="submit" class="btn btn-danger">Change Role</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="passwordModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="post" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Role Change</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="modal_new_role" id="modal_new_role">
                <input type="hidden" name="modal_position" id="modal_position">
                <div class="mb-3">
                    <label class="form-label">Enter Your Password</label>
                    <input type="password" name="modal_password" class="form-control" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" name="confirm_change" class="btn btn-danger">Confirm</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function togglePositionField() {
        const role = document.getElementById("new_role").value;
        document.getElementById("position_container").classList.toggle("d-none", role !== "official");
    }

    function openConfirmModal() {
        const role = document.getElementById("new_role").value;
        const pos = document.getElementById("position").value;
        if (!role) return;
        document.getElementById("modal_new_role").value = role;
        document.getElementById("modal_position").value = pos;
        const modal = new bootstrap.Modal(document.getElementById("passwordModal"));
        modal.show();
    }
</script>
</body>
</html>
