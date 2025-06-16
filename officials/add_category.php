<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'official') {
    header("Location: authentication/loginform.php");
    exit();
}

$db = new Database();
$conn = $db->connect();

$msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category_name = trim($_POST['category_name'] ?? '');
    $priority_level = $_POST['default_urgency'] ?? '';

    if ($category_name !== '' && in_array($priority_level, ['Low', 'Medium', 'High'])) {
        $stmt = $conn->prepare("INSERT INTO categories (category_name, default_urgency) VALUES (?, ?)");
        if ($stmt->execute([$category_name, $default_urgency])) {
            $msg = "<p style='color:green;'>Incident type added successfully!</p>";
        } else {
            $msg = "<p style='color:red;'>Failed to add incident type.</p>";
        }
    } else {
        $msg = "<p style='color:red;'>Please fill out all fields correctly.</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Add Incident Category</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
  <div class="container mt-5">
    <h2 class="mb-4">Add Incident Type with Level</h2>

    <?php echo $msg; ?>

    <form method="POST" class="card p-4 shadow-sm bg-white">
      <div class="mb-3">
        <label for="category_name" class="form-label">Incident Type Name</label>
        <input type="text" class="form-control" id="category_name" name="category_name" required>
      </div>

      <div class="mb-3">
        <label for="priority_level" class="form-label">Urgency Level</label>
        <select class="form-select" id="priority_level" name="priority_level" required>
          <option value="">Select Level</option>
          <option value="High">High</option>
          <option value="Medium">Medium</option>
          <option value="Low">Low</option>
        </select>
      </div>

      <button type="submit" class="btn btn-primary">Add Type</button>
      <a href="official_dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </form>

    <hr class="my-4">

    <h4>Existing Incident Types</h4>
    <ul class="list-group">
      <?php
      $stmt = $conn->query("SELECT category_name, default_urgency FROM categories ORDER BY category_name ASC");
      while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
          echo "<li class='list-group-item d-flex justify-content-between align-items-center'>
                  {$row['category_name']}
                  <span class='badge bg-info'>{$row['default_urgency']}</span>
                </li>";
      }
      ?>
    </ul>
  </div>
</body>
</html>
