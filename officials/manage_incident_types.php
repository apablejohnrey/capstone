<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../classes/IncidentCategoryManager.php';

$db = new Database();
$conn = $db->connect();

$user_id = $_SESSION['user_id'] ?? null;
$user_type = $_SESSION['user_type'] ?? null;

if (!$user_id || $user_type !== 'official') {
    header('Location: ../index.php');
    exit;
}

$manager = new IncidentCategoryManager($conn);

$predefined_types = [
    ['name' => 'Fire', 'urgency' => 'High'],
    ['name' => 'Flood', 'urgency' => 'High'],
    ['name' => 'Medical Emergency', 'urgency' => 'High'],
    ['name' => 'Road Accident', 'urgency' => 'High'],
    ['name' => 'Theft', 'urgency' => 'Medium'],
    ['name' => 'Vandalism', 'urgency' => 'Low'],
    ['name' => 'Missing Person', 'urgency' => 'High'],
    ['name' => 'Domestic Violence', 'urgency' => 'High'],
    ['name' => 'Power Outage', 'urgency' => 'Medium'],
    ['name' => 'Earthquake', 'urgency' => 'High'],
    ['name' => 'Landslide', 'urgency' => 'High'],
    ['name' => 'Public Disturbance', 'urgency' => 'Medium'],
    ['name' => 'Animal Attack', 'urgency' => 'Medium'],
    ['name' => 'Structural Collapse', 'urgency' => 'High'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'add':
            $selected_name = trim($_POST['category_name'] ?? '');
            $custom_name = trim($_POST['custom_category_name'] ?? '');
            $urgency = $_POST['priority_level'] ?? '';

            $name = $custom_name !== '' ? $custom_name : $selected_name;

            if ($name === '') {
                $manager->error = "Incident type name is required.";
            } elseif (!in_array($urgency, ['Low', 'Medium', 'High'])) {
                $manager->error = "Invalid priority level.";
            } else {
                $manager->addCategory($name, $urgency);
            }
            break;

        case 'update':
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['category_name'] ?? '');
            $urgency = $_POST['priority_level'] ?? '';

            if ($id <= 0) {
                $manager->error = "Invalid category ID.";
            } elseif ($name === '') {
                $manager->error = "Incident type name is required.";
            } elseif (!in_array($urgency, ['Low', 'Medium', 'High'])) {
                $manager->error = "Invalid priority level.";
            } else {
                $manager->updateCategory($id, $name, $urgency);
            }
            break;

        case 'delete':
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                $manager->error = "Invalid category ID.";
            } else {
                $manager->deleteCategory($id);
            }
            break;
    }
}

$categories = $manager->getAllCategories();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Incident Types</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container py-4">
    <h2>Manage Incident Types</h2>
    <br>

    <?php if ($manager->message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($manager->message) ?></div>
    <?php endif; ?>
    <?php if ($manager->error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($manager->error) ?></div>
    <?php endif; ?>

    <button id="showAddFormBtn" class="btn btn-primary mb-4">Add New Incident Type</button>

    <form id="addForm" method="POST" class="mb-4" style="display:none;">
        <input type="hidden" name="action" value="add">

        <div class="mb-3">
            <label for="category_name" class="form-label">Select Predefined Incident Type (optional)</label>
            <select name="category_name" id="category_name" class="form-select">
                <option value="">-- Select Incident Type --</option>
                <?php foreach ($predefined_types as $type): ?>
                    <option value="<?= htmlspecialchars($type['name']) ?>"><?= htmlspecialchars($type['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label for="custom_category_name" class="form-label">Or Enter Custom Incident Type</label>
            <input type="text" name="custom_category_name" id="custom_category_name" class="form-control" placeholder="e.g., Gas Leak">
        </div>

        <div class="mb-3">
            <label for="priority_level" class="form-label">Priority Level</label>
            <select name="priority_level" id="priority_level" class="form-select" required>
                <option value="">-- Select Priority --</option>
                <option value="High">High</option>
                <option value="Medium">Medium</option>
                <option value="Low">Low</option>
            </select>
        </div>

        <button type="submit" class="btn btn-success">Add Incident Type</button>
    </form>

    <script>
        document.getElementById('showAddFormBtn').addEventListener('click', function () {
            document.getElementById('addForm').style.display = 'block';
            this.style.display = 'none';
        });

        const typeUrgencyMap = {
            <?php foreach ($predefined_types as $type): ?>
                <?= json_encode($type['name']) ?>: <?= json_encode($type['urgency']) ?>,
            <?php endforeach; ?>
        };

        document.getElementById('category_name').addEventListener('change', function () {
            const urgency = typeUrgencyMap[this.value] || '';
            if (urgency) {
                document.getElementById('priority_level').value = urgency;
            }
        });
    </script>

    <table class="table table-bordered table-striped">
        <thead>
            <tr>
                <th>Incident Type</th>
                <th>Priority Level</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($categories as $cat): ?>
            <tr>
                <form method="POST" class="d-flex gap-2">
                    <td>
                        <input type="text" name="category_name" class="form-control" value="<?= htmlspecialchars($cat['category_name']) ?>" required>
                    </td>
                    <td>
                        <select name="priority_level" class="form-select" required>
                            <option value="High" <?= $cat['default_urgency'] === 'High' ? 'selected' : '' ?>>High</option>
                            <option value="Medium" <?= $cat['default_urgency'] === 'Medium' ? 'selected' : '' ?>>Medium</option>
                            <option value="Low" <?= $cat['default_urgency'] === 'Low' ? 'selected' : '' ?>>Low</option>
                        </select>
                    </td>
                    <td>
                        <input type="hidden" name="id" value="<?= $cat['category_id'] ?>">
                        <button type="submit" name="action" value="update" class="btn btn-sm btn-primary">Update</button>
                        <button type="submit" name="action" value="delete" class="btn btn-sm btn-danger" onclick="return confirm('Delete this incident type?');">Delete</button>
                    </td>
                </form>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <a href="official_dashboard.php" class="btn btn-secondary mt-3">Back to Dashboard</a>
</body>
</html>
