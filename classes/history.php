<?php
require_once __DIR__ . '/../includes/db.php';


class ReportHistory {
    private $conn;

    public function __construct() {
        $db = new Database();
        $this->conn = $db->connect();
    }

    public function getUserIncidentReports($user_id) {
        $stmt = $this->conn->prepare("
            SELECT ir.*, c.category_name 
            FROM incident_reports ir
            JOIN categories c ON ir.category_id = c.category_id
            WHERE ir.reporter_id = :user_id
            ORDER BY ir.incident_datetime DESC
        ");
        $stmt->execute(['user_id' => $user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function __destruct() {
        $this->conn = null;
    }
}
