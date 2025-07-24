<?php
class IncidentReport
{
    private PDO $conn;

    public function __construct(PDO $db)
    {
        $this->conn = $db;
    }

    public function getAllIncidents($filters = []): array
{
    $conditions = [];
    $params = [];

    if (!empty($filters['category'])) {
        $conditions[] = 'ir.category_id = ?';
        $params[] = $filters['category'];
    }

    if (!empty($filters['status'])) {
        $conditions[] = 'ir.status = ?';
        $params[] = $filters['status'];
    }

    if (!empty($filters['urgency'])) {
        $conditions[] = 'ir.urgency_level = ?';
        $params[] = $filters['urgency'];
    }

    $whereClause = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

    $query = "
        SELECT 
            ir.incident_id,
            c.category_name,
            ir.urgency_level,
            ir.details,
            ir.latitude,
            ir.longitude,
            ir.purok,
            ir.landmark,
            ir.reported_datetime,
            ir.status,
            ir.verified_datetime,

            COALESCE(
                CONCAT(bo.fname, ' ', bo.lname),
                CONCAT(t2.fname, ' ', t2.lname),
                u2.username
            ) AS verified_by,

            CASE 
                WHEN bo.user_id IS NOT NULL THEN 'Official'
                WHEN t2.user_id IS NOT NULL THEN 'Tanod'
                ELSE 'User'
            END AS verifier_type,

            COALESCE(
                CONCAT(r.fname, ' ', r.lname),
                CONCAT(bo2.fname, ' ', bo2.lname),
                CONCAT(t.fname, ' ', t.lname),
                u.username
            ) AS reporter_name,

            (
                SELECT COUNT(*) 
                FROM incident_evidence ie 
                WHERE ie.incident_id = ir.incident_id
            ) AS evidence_count

        FROM incident_reports ir
        JOIN categories c ON ir.category_id = c.category_id
        JOIN users u ON ir.reporter_id = u.user_id

        LEFT JOIN residents r ON r.user_id = u.user_id
        LEFT JOIN barangay_officials bo2 ON bo2.user_id = u.user_id
        LEFT JOIN tanods t ON t.user_id = u.user_id

        LEFT JOIN barangay_officials bo ON ir.verified_by = bo.user_id
        LEFT JOIN tanods t2 ON ir.verified_by = t2.user_id
        LEFT JOIN users u2 ON ir.verified_by = u2.user_id

        $whereClause

        ORDER BY 
            FIELD(ir.urgency_level, 'High', 'Medium', 'Low'),
            ir.reported_datetime DESC
    ";

    $stmt = $this->conn->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


    public function getCategories(): array
    {
        $stmt = $this->conn->query("SELECT category_id, category_name FROM categories");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
