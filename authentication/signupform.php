<?php
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Resident Signup</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>

<form action="signup.php" method="POST">

<h2>REGISTER YOUR ACCOUNT <br> <span>Enter your details here</span></h2>

    <div class="form-group">
        <label>Username:</label>
        <input type="text" name="username" required>
    </div>

    <div class="form-group">
        <label>Password:</label>
        <input type="password" name="password" required>
    </div>

    <div class="form-group">
        <label>First Name:</label>
        <input type="text" name="fname" required>
    </div>
    
    <div class="form-group">
        <label>Last Name:</label>
        <input type="text" name="lname" required>
    </div>

    <div class="form-group">
        <label>Contact Number:</label>
        <input type="text" name="contact_number">
    </div>

    <div class="form-group">
        <label>Purok:</label>
        <select name="purok" required>
            <option value="Purok 1">Purok 1</option>
            <option value="Purok 2">Purok 2</option>
            <option value="Purok 3">Purok 3</option>
            <option value="Purok 4">Purok 4</option>
            <option value="Purok 5">Purok 5</option>
            <option value="Purok 6">Purok 6</option>
            <option value="Purok 7">Purok 7</option>
        </select>
    </div>

    <button type="submit" name="signup">Signup</button>

    <p>Already a member? <a href="loginform.php">Login Now</a></p>

</form>

</body>
</html>
