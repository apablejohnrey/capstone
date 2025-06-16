<?php
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION)) session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'official') {
    header("Location: authentication/loginform.php");
    exit();
}

$db = new Database();
$conn = $db->connect();

$stmt = $conn->prepare("SELECT name, position FROM Barangay_Officials WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$official = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$official) {
    echo "<p style='color:red;'>Official details not found.</p>";
    exit();
}

$position = strtolower($official['position']);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Manage Accounts</title>
    <link rel="stylesheet" href="../css/navofficial.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f0f2f5;
            margin: 0;
            padding: 0;
        }
        .container {
            width: 90%;
            max-width: 1000px;
            margin: 50px auto;
            margin-left: 50px;
        }
        .user-card {
            background: #fff;
            padding: 20px;
            margin-bottom: 20px;
           
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        }
        .user-card h2 {
            margin-top: 0;
        }
        .btn {
            display: inline-block;
            margin-top: 10px;
            padding: 10px 15px;
            background-color: #007BFF;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            transition: background 0.3s;
        }
        .btn:hover {
            background-color: #0056b3;
        }
        .welcome {
            margin: 20px auto;
            text-align: center;
        }
    </style>
</head>
<body>

<div class="container-fluid">
        <div class="col-md-3"><?php include 'navofficial.php'; ?></div>


        <div class="user-card">
            <h2>Residents</h2>
            <p>Manage resident account information, including contact details and purok assignment.</p>
            <a href="manageresidents.php" class="btn">Manage Residents</a>
        </div>

        <div class="user-card">
            <h2>Barangay Officials</h2>
            <p>Manage officials like Secretary and Chairperson with position and contact details.</p>
            <a href="manageofficials.php" class="btn">Manage Officials</a>
        </div>

        <div class="user-card">
            <h2>Tanods</h2>
            <p>Manage tanods including assigned areas and contact numbers.</p>
            <a href="managetanods.php" class="btn">Manage Tanods</a>
        </div>
</div>

</body>
</html>
