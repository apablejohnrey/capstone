<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'official') {
    header("Location: ../authentication/loginform.php");
    exit();
}

$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Create Tanod</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
  <h2>Create Tanod Account</h2>

  <?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
  <?php elseif ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST" action="create_tanod.php">
    <div class="mb-3">
      <label>Full Name</label>
      <input type="text" class="form-control" name="name" required>
    </div>

    <div class="mb-3">
      <label>Contact Number</label>
      <input type="text" class="form-control" name="contact_number" required>
    </div>

    <div class="mb-3">
      <label>Username</label>
      <input type="text" class="form-control" name="username" required>
    </div>

    <div class="mb-3">
      <label>Password</label>
      <input type="password" class="form-control" name="password" required>
    </div>

    <button type="submit" class="btn btn-primary">Create Tanod</button>
    <a href="official_dashboard.php" class="btn btn-secondary">Cancel</a>
  </form>
</div>
</body>
</html>
