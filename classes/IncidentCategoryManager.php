<?php
class IncidentCategoryManager {
    private PDO $conn;
    public string $message = "";
    public string $error = "";

    public function __construct(PDO $conn) {
        $this->conn = $conn;
    }

    public function addCategory(string $name, string $urgency): void {
        $stmt = $this->conn->prepare("SELECT COUNT(*) FROM categories WHERE category_name = ?");
        $stmt->execute([$name]);

        if ($stmt->fetchColumn() > 0) {
            $this->error = "Incident type already exists.";
            return;
        }

        $stmt = $this->conn->prepare("INSERT INTO categories (category_name, default_urgency) VALUES (?, ?)");
        if ($stmt->execute([$name, $urgency])) {
            $this->message = "Incident type added successfully.";
        } else {
            $this->error = "Failed to add incident type.";
        }
    }

    public function updateCategory(int $id, string $name, string $urgency): void {
        $stmt = $this->conn->prepare("UPDATE categories SET category_name = ?, default_urgency = ? WHERE category_id = ?");
        if ($stmt->execute([$name, $urgency, $id])) {
            $this->message = "Incident type updated successfully.";
        } else {
            $this->error = "Failed to update incident type.";
        }
    }

    public function deleteCategory(int $id): void {
        $stmt = $this->conn->prepare("DELETE FROM categories WHERE category_id = ?");
        if ($stmt->execute([$id])) {
            $this->message = "Incident type deleted successfully.";
        } else {
            $this->error = "Failed to delete incident type.";
        }
    }

    public function getAllCategories(): array {
        $stmt = $this->conn->query("SELECT category_id, category_name, default_urgency FROM categories ORDER BY category_name ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
