<?php
class AccountStatusManager {
    private PDO $conn;

    public function __construct(PDO $conn) {
        $this->conn = $conn;
    }

    public function toggleStatus(int $userId, string $password, int $changerId): bool {
        // Fetch actual password
        $stmt = $this->conn->prepare("SELECT password, status FROM Users WHERE user_id = ?");
        $stmt->execute([$changerId]);
        $changer = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$changer || !password_verify($password, $changer['password'])) {
            return false;
        }

        $stmt = $this->conn->prepare("SELECT status FROM Users WHERE user_id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) return false;

        $newStatus = $user['status'] === 'Active' ? 'Inactive' : 'Active';

        $update = $this->conn->prepare("UPDATE Users SET status = ? WHERE user_id = ?");
        return $update->execute([$newStatus, $userId]);
    }
}
