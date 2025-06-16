
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>

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
    <?php
   
    $alert = '';
    if (isset($_GET['timeout'])) {
        $alert = "You have been logged out due to inactivity.";
    } elseif (isset($_GET['error'])) {
        $alert = htmlspecialchars($_GET['error'], ENT_QUOTES);
    }

    if ($alert) {
        echo "<script>window.onload = function() { alert('$alert'); };</script>";
    }
    ?>


    <form action="login.php" method="POST">
        <h2>LOGIN TO YOUR ACCOUNT <span>Enter your details here</span></h2>

        <div class="form-group">
            <label for="username">Username:</label>
            <input type="text" id="username" name="username" required>
        </div>

        <div class="form-group">
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required>
        </div>

        <button type="submit" name="login">Login</button>

        <p>Don't have an account? <a href="signupform.php">Sign up here</a></p>
    </form>

</body>
</html>
