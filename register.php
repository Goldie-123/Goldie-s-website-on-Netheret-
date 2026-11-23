<?php
session_start();
require_once 'config.php';


$errors = [];
$username = '';
$email = '';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {


    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';


    // Username validation
    if ($username === '') {
        $errors[] = 'Please enter your username.';
    } elseif (strlen($username) < 3 || strlen($username) > 100) {
        $errors[] = 'Username must be between 3 and 100 characters.';
    }


    // Email validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
        $errors[] = 'Please enter a valid email address (maximum 100 characters).';
    }


    // Password validation
    if (strlen($password) < 8) {
        $errors[] = 'Password must contain at least 8 characters.';
    }


    // Check whether email already exists
    if (empty($errors)) {
        $stmt = $conn->prepare(
            "SELECT id FROM users WHERE email = ?"
        );
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();


        if ($result->num_rows > 0) {
            $errors[] = 'This email address is already registered.';
        }


        $stmt->close();
    }


    // Create account
    if (empty($errors)) {


        $hashedPassword = password_hash(
            $password,
            PASSWORD_DEFAULT
        );


        $stmt = $conn->prepare(
            "INSERT INTO users (username, email, password)
             VALUES (?, ?, ?)"
        );


        $stmt->bind_param(
            "sss",
            $username,
            $email,
            $hashedPassword
        );


        $stmt->execute();
        $stmt->close();


        header('Location: login.php?registered=1');
        exit;
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | Netheret IT Support Solutions</title>


    <style>
        * {
            box-sizing: border-box;
        }


        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #121212;
            color: white;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }


        .register-container {
            background: #1e1e1e;
            padding: 35px;
            width: 100%;
            max-width: 420px;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.4);
        }


        h1 {
            text-align: center;
            color: #ffeb3d;
            margin-bottom: 10px;
        }


        .subtitle {
            text-align: center;
            color: #bbb;
            margin-bottom: 25px;
        }


        label {
            display: block;
            margin: 15px 0 7px;
        }


        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #555;
            border-radius: 6px;
            background: #292929;
            color: white;
            font-size: 15px;
        }


        input:focus {
            outline: 2px solid #ffeb3d;
        }


        button {
            width: 100%;
            padding: 13px;
            margin-top: 25px;
            border: none;
            border-radius: 6px;
            background: #ffeb3d;
            color: #121212;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }


        button:hover {
            background: #f5d900;
        }


        .errors {
            background: #4a1717;
            color: #ffbaba;
            padding: 12px 12px 12px 30px;
            border-radius: 6px;
        }


        .login-link {
            text-align: center;
            margin-top: 20px;
            color: #bbb;
        }


        a {
            color: #ffeb3d;
            text-decoration: none;
        }
    </style>
</head>


<body>


<div class="register-container">


    <h1>Netheret IT Support Solutions</h1>
    <b class="subtitle">Create an account</b>


    <?php if (!empty($errors)): ?>
        <ul class="errors">
            <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>


    <form method="POST" action="register.php">


        <label for="username">Username</label>
        <input
            type="text"
            id="username"
            name="username"
            maxlength="100"
            value="<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>"
            required
        >


        <label for="email">Email Address</label>
        <input
            type="email"
            id="email"
            name="email"
            maxlength="100"
            value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>"
            required
        >


        <label for="password">Password</label>
        <input
            type="password"
            id="password"
            name="password"
            minlength="8"
            required
        >


        <button type="submit">Create Account</button>


    </form>


    <p class="login-link">
        Already have an account?
        <a href="login.php">Login here</a>
    </p>


</div>


</body>
</html>


