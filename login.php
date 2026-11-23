<?php
declare(strict_types=1);

session_start();
require_once 'config.php';

// If already logged in, redirect according to role.
if (isset($_SESSION['user_id'])) {
    if (($_SESSION['role'] ?? '') === 'admin') {
        header('Location: manage_users.php');
    } elseif (($_SESSION['role'] ?? '') === 'staff') {
        header('Location: staff_dashboard.php');
    } else {
        header('Location: dashboard.php');
    }
    exit;
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validate email.
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif ($password === '') {
        $error = 'Please enter your password.';
    } else {

        // Find user in database.
        $stmt = $conn->prepare(
            "SELECT id, username, email, password, role
             FROM users
             WHERE email = ?
             LIMIT 1"
        );

        $stmt->bind_param("s", $email);
        $stmt->execute();

        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        $stmt->close();

        // Verify password.
        if ($user && password_verify($password, $user['password'])) {

            // Prevent session fixation.
            session_regenerate_id(true);

            // Store user information in session.
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];

            // Redirect according to role.
            if ($_SESSION['role'] === 'admin') {
                header('Location: manage_users.php');
            } elseif ($_SESSION['role'] === 'staff') {
                header('Location: staff_dashboard.php');
            } else {
                header('Location: dashboard.php');
            }
            exit;

        } else {
            $error = 'Incorrect email or password.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login | Netheret IT Support Solutions</title>

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

        .login-container {
            background: #1e1e1e;
            padding: 35px;
            width: 100%;
            max-width: 420px;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.4);
        }

        /* LOGO - MATCHES HOMEPAGE */
        .logo-heading {
            color: #ffeb3d;
            font-size: 20px;
            font-weight: bold;
            letter-spacing: 1px;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-bottom: 25px;
        }

        .logo-img {
            width: 35px;
            height: 35px;
            object-fit: contain;
        }

        .logo-text {
            text-align: left;
            font-size: 20px;
        }

        .logo-text span {
            display: block;
            color: #ffffff7d;
            font-size: 9px;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            margin-top: 2px;
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

        .error {
            background: #4a1717;
            color: #ffbaba;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 15px;
        }

        .success {
            background: #173d24;
            color: #06ab30;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 15px;
        }

        .register-link {
            text-align: center;
            margin-top: 20px;
            color: #bbb;
        }

        a {
            color: #ffeb3d;
            text-decoration: none;
        }

        @media (max-width: 450px) {
            .login-container {
                padding: 25px 20px;
            }

            .logo-text {
                font-size: 18px;
            }
        }
    </style>

</head>

<body>

<div class="login-container">

    <!-- HOMEPAGE STYLE LOGO -->
    <a href="welcome.html" class="logo-heading">

        <img src="Screenshot 2026-10-08 115035.png"
             alt="Netheret IT Logo"
             class="logo-img">

        <div class="logo-text">
            Netheret IT
            <span>SUPPORT SOLUTIONS</span>
        </div>

    </a>

    <p class="subtitle">
        Welcome Back! Please log in to your account.
    </p>

    <?php if (isset($_GET['registered'])): ?>
        <div class="success">
            Registration successful! You can now log in.
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="error">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="login.php">

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
            required
        >

        <button type="submit">Login</button>

    </form>

    <p class="register-link">
        Don't have an account?
        <a href="register.php">Register here</a>
    </p>

    <p class="register-link">
       Forgotten your password?
        <a href="forgotpassword.html">Reset password</a>
    </p>

    <p class="register-link">
        <a href="welcome.html">Back to Home</a>
    </p>

    

</div>

</body>
</html>
