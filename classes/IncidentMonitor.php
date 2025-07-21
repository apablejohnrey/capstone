<?php
class IncidentReport
{
    private PDO $conn;

    public function __construct(PDO $db)
    {
        $this->conn = $db;
    }

    public function getAllIncidents(): array
{
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
            COALESCE(bo.name, t2.name) AS verified_by,
            CASE 
                WHEN bo.name IS NOT NULL THEN 'Official'
                WHEN t2.name IS NOT NULL THEN 'Tanod'
                ELSE NULL
            END AS verifier_type,

            COALESCE(CONCAT(r.fname, ' ', r.lname), bo2.name, t.name) AS reporter_name,

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

        ORDER BY 
            FIELD(ir.urgency_level, 'High', 'Medium', 'Low'),
            ir.reported_datetime DESC
    ";

    $stmt = $this->conn->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

}
?>
