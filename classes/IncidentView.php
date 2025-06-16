<?php
class IncidentView {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getIncidentDetails($incident_id) {
        $sql = "
            SELECT ir.*, c.category_name, u.username 
            FROM incident_reports ir
            JOIN categories c ON ir.category_id = c.category_id
            JOIN users u ON ir.reporter_id = u.user_id
            WHERE ir.incident_id = ?
        ";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$incident_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getVictims($incident_id) {
        return $this->fetchPeople('incident_victims', $incident_id);
    }

    public function getPerpetrators($incident_id) {
        return $this->fetchPeople('incident_perpetrators', $incident_id);
    }

    public function getWitnesses($incident_id) {
        return $this->fetchPeople('incident_witnesses', $incident_id);
    }

    public function getEvidence($incident_id) {
        $sql = "SELECT * FROM incident_evidence WHERE incident_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$incident_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function fetchPeople($table, $incident_id) {
        $sql = "SELECT * FROM $table WHERE incident_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$incident_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
