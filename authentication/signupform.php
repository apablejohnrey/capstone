<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Resident Signup</title>
  <link rel="stylesheet" href="../css/style.css">
  <style>
    .error { color: red; font-size: 13px; margin-top: 5px; }
  </style>
</head>
<body>

<form action="signup.php" method="POST" onsubmit="return validateForm()">
  <h2>REGISTER YOUR ACCOUNT <br> <span>Enter your details here</span></h2>

  <div class="form-group">
    <label>Username:</label>
    <input type="text" name="username" required>
  </div>

  <div class="form-group">
    <label>Email:</label>
    <input type="email" name="email" required>
  </div>

  <div class="form-group">
    <label>Password:</label>
    <input type="password" id="password" name="password" required>
    <input type="checkbox" onclick="togglePassword()"> Show Password
    <div id="passwordError" class="error"></div>
  </div>

  <div class="form-group">
    <label>Confirm Password:</label>
    <input type="password" id="confirm_password" name="confirm_password" required>
    <div id="confirmError" class="error"></div>
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
    <input type="number" name="contact_number">
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

<script>
function togglePassword() {
  const pwd = document.getElementById("password");
  const confirm = document.getElementById("confirm_password");
  pwd.type = pwd.type === "password" ? "text" : "password";
  confirm.type = confirm.type === "password" ? "text" : "password";
}

function validateForm() {
  const password = document.getElementById("password").value;
  const confirm = document.getElementById("confirm_password").value;
  const passwordError = document.getElementById("passwordError");
  const confirmError = document.getElementById("confirmError");

  passwordError.textContent = "";
  confirmError.textContent = "";

  const validPassword = /^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d]{8,}$/.test(password);

  if (!validPassword) {
    passwordError.textContent = "Password must be at least 8 characters long and contain letters and numbers.";
    return false;
  }

  if (password !== confirm) {
    confirmError.textContent = "Passwords do not match.";
    return false;
  }

  return true;
}
</script>

</body>
</html>
