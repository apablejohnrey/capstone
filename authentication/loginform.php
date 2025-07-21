<?php
require_once '../includes/CsrfToken.php';
$csrfToken = CsrfToken::generate();

$alert = '';
if (isset($_GET['timeout'])) {
    $alert = "You have been logged out due to inactivity.";
} elseif (isset($_GET['error'])) {
    $alert = htmlspecialchars($_GET['error'], ENT_QUOTES);
}
?>
<?php if (isset($_GET['message']) && $_GET['message'] === 'RoleChanged'): ?>
    <div class="alert alert-warning">Your access role was changed. Please log in again.</div>
<?php endif; ?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <style>
        body, h2, form {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
        }

        body {
            background-image: url('../img/sign6.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-color: #ced6e4;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        form {
            background-color: rgba(255, 255, 255, 0.85); 
            padding: 20px 35px;
            border-radius: 8px;
            box-shadow: 0 3px 5px rgba(0, 0, 0, 0.1);
            width: 85%;
            max-width: 380px; 
            display: flex;
            flex-wrap: wrap;
            gap: 10px; 
            box-sizing: border-box;
            margin-left: 31%;
        }

        h2 {
            width: 100%;
            text-align: center;
            color: #333;
            font-size: 16px; 
            font-weight: bold;
            margin-top: 2%;
            margin-bottom: 10px;
        }

        h2 span {
            display: block;
            font-size: 12px; 
            font-weight: normal;
            color: #777;
            margin-top: 3px;
        }

        .form-group {
            flex: 1 1 100%;
            min-width: 150px; 
        }

        .form-group label {
            display: block;
            font-weight: bold;
            margin-bottom: 4px;
            color: #333;
            font-size: 11px; 
        }

        .form-group input[type="text"],
        .form-group input[type="password"] {
            width: 100%;
            padding: 6px; 
            margin-top: 4px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 12px; 
            box-sizing: border-box;
        }

        input:focus {
            border-color: #2240c4;
            outline: none;
        }

        button[type="submit"] {
            background-color: #2240c4;
            color: white;
            padding: 6px 15px; 
            margin-top: 8px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px; 
            align-self: center;
            display: block;
            margin-left: auto;
            margin-right: auto;
        }

        button[type="submit"]:hover {
            background-color: #2240c4;
        }

        p {
            margin-top: 8px;
            text-align: center;
            width: 100%;
            font-size: 10px; 
        }

        p a {
            color: #2240c4;
            text-decoration: none;
            font-weight: bold;
        }

        p a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <?php if ($alert): ?>
    <script>
    window.onload = function() {
        alert(<?= json_encode($alert) ?>);
    };
    </script>
    <?php endif; ?>
  
    <form action="login.php" method="POST">
        <h2>LOGIN TO YOUR ACCOUNT <span>Enter your details here</span></h2>
      <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
      
        <div class="form-group">
            <label for="username">Username:</label>
           <input type="text" id="username" name="username" autocomplete="off" required
            value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username'], ENT_QUOTES, 'UTF-8') : ''; ?>">
        </div>

        <div class="form-group">
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" autocomplete="off" required>
        </div>
        <div class="g-recaptcha" data-sitekey="6LejkH8rAAAAALyaBfv1bG1ZkMXz7TiQjqdQxzwr"></div> 

        <button type="submit" name="login">Login</button>

        <p>Don't have an account? <a href="signupform.php">Sign up here</a></p>
    </form>

    <script>
    document.querySelector("form").addEventListener("submit", function(e) {
    const username = document.getElementById("username").value.trim();
    const password = document.getElementById("password").value;

    if (username === "" || password === "") {
        alert("Username and password cannot be empty.");
        e.preventDefault();
        return;    }

    if (password.length < 8) {
        alert("Password must be at least 8 characters.");
        e.preventDefault();
        return;
    }
});
</script>

</body>
</html>
