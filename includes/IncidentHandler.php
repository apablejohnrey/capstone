<?php
require_once 'db.php';

class IncidentHandler {
    private $conn;

    public function __construct() {
        $db = new Database();
        $this->conn = $db->connect();
    }

    public function insertIncident($data, $files, $user_id) {
        try {
            $this->conn->beginTransaction();

            $stmt = $this->conn->prepare("INSERT INTO incident_reports (reporter_id, category_id, urgency_level, purok, landmark, latitude, longitude, incident_datetime, details)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $user_id,
                $data['category_id'],
                $data['urgency'],
                $data['purok'],
                $data['landmark'],
                $data['latitude'],
                $data['longitude'],
                 $data['incident_datetime'],
                $data['details']
            ]);
            $incident_id = $this->conn->lastInsertId();

            // Insert victims
            if (!empty($data['victim_name'])) {
                for ($i = 0; $i < count($data['victim_name']); $i++) {
                    $stmt = $this->conn->prepare("INSERT INTO incident_victims (incident_id, name, age, contact_number)
                                                  VALUES (?, ?, ?, ?)");
                    $stmt->execute([
                        $incident_id,
                        $data['victim_name'][$i],
                        $data['victim_age'][$i] ?? null,
                        $data['victim_contact'][$i] ?? null
                    ]);
                }
            }

            // Insert perpetrators
            if (!empty($data['perpetrator_name'])) {
                for ($i = 0; $i < count($data['perpetrator_name']); $i++) {
                    $stmt = $this->conn->prepare("INSERT INTO incident_perpetrators (incident_id, name, age, contact_number)
                                                  VALUES (?, ?, ?, ?)");
                    $stmt->execute([
                        $incident_id,
                        $data['perpetrator_name'][$i],
                        $data['perpetrator_age'][$i] ?? null,
                        $data['perpetrator_contact'][$i] ?? null
                    ]);
                }
            }

            // Insert witnesses
            if (!empty($data['witness_name'])) {
                for ($i = 0; $i < count($data['witness_name']); $i++) {
                    $stmt = $this->conn->prepare("INSERT INTO incident_witnesses (incident_id, name, contact_number)
                                                  VALUES (?, ?, ?)");
                    $stmt->execute([
                        $incident_id,
                        $data['witness_name'][$i],
                        $data['witness_contact'][$i] ?? null
                    ]);
                }
            }

            // Upload files
            $uploadDir = 'uploads/';
            foreach ($files['evidence']['tmp_name'] as $index => $tmpName) {
                $originalName = $files['evidence']['name'][$index];
                $fileType = $files['evidence']['type'][$index];
                $extension = pathinfo($originalName, PATHINFO_EXTENSION);
                if (preg_match('/video/i', $fileType)) {
                    $type = 'video';
                } elseif (preg_match('/image/i', $fileType)) {
                    $type = 'image';
                } else {
                    $type = 'unknown';
                }


                $newName = uniqid('evidence_', true) . '.' . $extension;
                $destination = $uploadDir . $newName;

                if (move_uploaded_file($tmpName, $destination)) {
                    $stmt = $this->conn->prepare("INSERT INTO incident_evidence (incident_id, file_path, file_type, uploaded_by)
                                                  VALUES (?, ?, ?, ?)");
                    $stmt->execute([
                        $incident_id,
                        $destination,
                        $type,
                        $user_id
                    ]);
                }
            }

            $this->conn->commit();
            return true;

        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Error: " . $e->getMessage());
            return false;
        }
    }
}
?>
