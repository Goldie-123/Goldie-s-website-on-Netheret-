
<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if (($_SESSION['role'] ?? '') !== 'staff') {
    header('Location: dashboard.php');
    exit;
}

require_once 'config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$staffId = (int) $_SESSION['user_id'];

$message = '';
$error = '';

$username = $_SESSION['username'] ?? '';
$email = '';
$largeText = 0;
$highContrast = 0;
$reduceMotion = 0;
$colourMode = 'dark';

try {

    // Get account information
    $stmt = $conn->prepare("
        SELECT username, email
        FROM users
        WHERE id = ?
    ");

    $stmt->bind_param('i', $staffId);
    $stmt->execute();

    $account = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($account) {
        $username = $account['username'];
        $email = $account['email'];
    }

    // Get saved preferences
    $stmt = $conn->prepare("
        SELECT large_text, high_contrast, reduce_motion, colour_mode
        FROM user_accessibility
        WHERE user_id = ?
    ");

    $stmt->bind_param('i', $staffId);
    $stmt->execute();

    $preferences = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($preferences) {
        $largeText = (int) $preferences['large_text'];
        $highContrast = (int) $preferences['high_contrast'];
        $reduceMotion = (int) $preferences['reduce_motion'];
        $colourMode = $preferences['colour_mode'];
    }

    // Save preferences
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        $largeText = isset($_POST['large_text']) ? 1 : 0;
        $highContrast = isset($_POST['high_contrast']) ? 1 : 0;
        $reduceMotion = isset($_POST['reduce_motion']) ? 1 : 0;

        $selectedMode = $_POST['colour_mode'] ?? 'dark';

        if (!in_array($selectedMode, ['light', 'dark'], true)) {
            throw new RuntimeException('Please select a valid colour mode.');
        }

        $colourMode = $selectedMode;

        $stmt = $conn->prepare("
            INSERT INTO user_accessibility
                (user_id, large_text, high_contrast, reduce_motion, colour_mode)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                large_text = VALUES(large_text),
                high_contrast = VALUES(high_contrast),
                reduce_motion = VALUES(reduce_motion),
                colour_mode = VALUES(colour_mode)
        ");

        $stmt->bind_param(
            'iiiis',
            $staffId,
            $largeText,
            $highContrast,
            $reduceMotion,
            $colourMode
        );

        $stmt->execute();
        $stmt->close();

        $message = 'Your settings have been saved successfully.';
    }

} catch (Throwable $e) {

    error_log('Settings page error: ' . $e->getMessage());

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $error = 'Your settings could not be saved. Please try again.';
    } else {
        $error = 'Your settings could not be loaded. Please try again.';
    }
}

function escapeHtml($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Settings - Netheret IT Support Solutions</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 30px 20px;
            background: <?php echo $colourMode === 'dark' ? '#121212' : '#f5f5f5'; ?>;
            color: <?php echo $colourMode === 'dark' ? '#ffffff' : '#222222'; ?>;
        }

        .container {
            max-width: 750px;
            margin: auto;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        h1, h2 {
            color: #ffeb3d;
        }

        section {
            margin-bottom: 20px;
            padding: 20px;
            background: <?php echo $colourMode === 'dark' ? '#1e1e1e' : '#ffffff'; ?>;
            border: 1px solid <?php echo $colourMode === 'dark' ? '#444444' : '#dddddd'; ?>;
            border-radius: 8px;
        }

        label {
            display: block;
            margin: 12px 0 6px;
        }

        input[type="text"],
        input[type="email"],
        select {
            width: 100%;
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #777;
            margin-top: 5px;
        }

        input[type="checkbox"] {
            margin-right: 8px;
        }

        button, .dashboard-link {
            display: inline-block;
            background: #ffeb3d;
            color: #121212;
            padding: 11px 18px;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            cursor: pointer;
        }

        button:hover, .dashboard-link:hover {
            background: #f5d900;
        }

        .dashboard-link {
            font-size: 14px;
        }

        .success {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .account-detail {
            padding: 10px;
            background: <?php echo $colourMode === 'dark' ? '#292929' : '#eeeeee'; ?>;
            border-radius: 5px;
            overflow-wrap: anywhere;
        }

        button:focus-visible,
        a:focus-visible,
        select:focus-visible {
            outline: 3px solid #ffeb3d;
            outline-offset: 3px;
        }
    </style>
</head>

<body>

<div class="container">

    <header>
        <h1>My Settings</h1>

        <a href="staff_dashboard.php" class="dashboard-link">
            Back to Dashboard
        </a>
    </header>

    <?php if ($message !== ''): ?>
        <div class="success">
            <?php echo escapeHtml($message); ?>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="error">
            <?php echo escapeHtml($error); ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="settings.php">

        <section>
            <h2>General Settings</h2>

            <label>Username:</label>
            <p class="account-detail">
                <?php echo escapeHtml($username); ?>
            </p>

            <label>Email:</label>
            <p class="account-detail">
                <?php echo escapeHtml($email); ?>
            </p>
        </section>

        <section>
            <h2>Accessibility Preferences</h2>

            <label>
                <input
                    type="checkbox"
                    name="large_text"
                    value="1"
                    <?php echo $largeText ? 'checked' : ''; ?>
                >
                Larger text
            </label>

            <label>
                <input
                    type="checkbox"
                    name="high_contrast"
                    value="1"
                    <?php echo $highContrast ? 'checked' : ''; ?>
                >
                High contrast
            </label>

            <label>
                <input
                    type="checkbox"
                    name="reduce_motion"
                    value="1"
                    <?php echo $reduceMotion ? 'checked' : ''; ?>
                >
                Reduce animations
            </label>
        </section>

        <section>
            <h2>Colour Mode</h2>

            <label for="colour_mode">
                Choose your colour mode:
            </label>

            <select id="colour_mode" name="colour_mode">
                <option value="dark"
                    <?php echo $colourMode === 'dark' ? 'selected' : ''; ?>>
                    Dark Mode
                </option>

                <option value="light"
                    <?php echo $colourMode === 'light' ? 'selected' : ''; ?>>
                    Light Mode
                </option>
            </select>
        </section>

        <button type="submit">Save Settings</button>

    </form>

</div>

</body>
</html>
