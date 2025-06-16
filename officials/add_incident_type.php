<?php
session_start();
require_once __DIR__ . '/includes/db.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['official', 'resident'])) {
    header("Location: authentication/loginform.php");
    exit();
}

$user_type = $_SESSION['user_type'];

$db = new Database();
$conn = $db->connect();

$message = "";
if (isset($_GET['delete_id']) && $user_type === 'official') {
    $delete_id = $_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
    if ($stmt->execute([$delete_id])) {
        $message = "Incident type deleted successfully.";
    } else {
        $message = "Failed to delete incident type.";
    }
}
if (isset($_POST['update_id'])) {
    $update_id = $_POST['update_id'];
    $category_name = $_POST['category_name'] ?? '';
    $priority_level = $_POST['priority_level'] ?? '';

    if (!empty($category_name) && in_array($priority_level, ['Low', 'Medium', 'High'])) {
        $stmt = $conn->prepare("UPDATE categories SET category_name = ?, priority_level = ? WHERE id = ?");
        if ($stmt->execute([$category_name, $priority_level, $update_id])) {
            $message = "Incident type updated successfully.";
        } else {
            $message = "Failed to update incident type.";
        }
    } else {
        $message = "All fields are required.";
    }
}

// Handle Add (Officials Only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['update_id']) && $user_type === 'official') {
    $category_name = $_POST['category_name'] ?? '';
    $priority_level = $_POST['priority_level'] ?? '';

    if (!empty($category_name) && in_array($priority_level, ['Low', 'Medium', 'High'])) {
        $stmt = $conn->prepare("INSERT INTO categories (category_name, priority_level) VALUES (?, ?)");
        if ($stmt->execute([$category_name, $priority_level])) {
            $message = "Incident type successfully added!";
        } else {
            $message = "Error: Failed to add incident type.";
        }
    } else {
        $message = "All fields are required.";
    }
}

// Fetch data for editing
$edit_id = $_GET['edit_id'] ?? null;
$edit_data = null;
if ($edit_id) {
    $stmt = $conn->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$edit_id]);
    $edit_data = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Incident Types</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: auto; padding: 20px; }
        input, select, button { width: 100%; padding: 10px; margin: 10px 0; }
        .message { margin-bottom: 20px; color: green; }
        .error { color: red; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ccc; padding: 10px; text-align: left; }
        th { background-color: #f4f4f4; }
        .action-buttons a { margin-right: 10px; text-decoration: none; color: blue; }
    </style>
</head>
<body>
    <h2><?php echo $edit_data ? 'Edit' : 'Add'; ?> Incident Type</h2>

    <?php if ($message): ?>
        <p class="message"><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>

    <?php if ($user_type === 'official' || $edit_data): ?>
    <form method="POST">
        <?php if ($edit_data): ?>
            <input type="hidden" name="update_id" value="<?php echo $edit_data['id']; ?>">
        <?php endif; ?>

        <label>Incident Type Name:</label>
        <input type="text" name="category_name" required value="<?php echo htmlspecialchars($edit_data['category_name'] ?? ''); ?>">

        <label>Priority Level:</label>
        <select name="priority_level" required>
            <option value="">Select Level</option>
            <option value="High" <?php if (($edit_data['priority_level'] ?? '') === 'High') echo 'selected'; ?>>High</option>
            <option value="Medium" <?php if (($edit_data['priority_level'] ?? '') === 'Medium') echo 'selected'; ?>>Medium</option>
            <option value="Low" <?php if (($edit_data['priority_level'] ?? '') === 'Low') echo 'selected'; ?>>Low</option>
        </select>

        <button type="submit"><?php echo $edit_data ? 'Update' : 'Add'; ?> Type</button>
    </form>
    <?php endif; ?>

    <hr>

    <h3>Existing Incident Types</h3>
    <table>
        <thead>
            <tr>
                <th>Incident Type</th>
                <th>Priority</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $stmt = $conn->query("SELECT * FROM categories ORDER BY category_name ASC");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['category_name']) . "</td>";
            echo "<td>" . htmlspecialchars($row['priority_level']) . "</td>";
            echo "<td class='action-buttons'>";
            echo "<a href='?edit_id={$row['id']}'>Edit</a>";
            if ($user_type === 'official') {
                echo "<a href='?delete_id={$row['id']}' onclick=\"return confirm('Are you sure you want to delete this item?');\">Delete</a>";
            }
            echo "</td>";
            echo "</tr>";
        }
        ?>
        </tbody>
    </table>

    <br>
    <button onclick="window.location.href='<?php echo $user_type === 'official' ? './officials/official_dashboard.php' : './residents/resident_dashboard.php'; ?>'">Back to Dashboard</button>
</body>
</html>
