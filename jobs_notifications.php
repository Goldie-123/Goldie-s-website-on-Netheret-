<?php
session_start();
require_once 'config.php';

// Only logged-in customers can view notifications.
if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'customer'
) {
    header('Location: login.php');
    exit;
}

$customerId = (int) $_SESSION['user_id'];

// Load only notifications belonging to this customer.
$stmt = $conn->prepare("
    SELECT
        job_notifications.id,
        job_notifications.message,
        job_notifications.is_read,
        job_notifications.created_at,
        onsite_jobs.job_title
    FROM job_notifications
    INNER JOIN onsite_jobs
        ON job_notifications.job_id = onsite_jobs.id
    WHERE job_notifications.customer_id = ?
    ORDER BY job_notifications.created_at DESC
");

$stmt->bind_param('i', $customerId);
$stmt->execute();

$notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Mark this customer's notifications as read.
$updateStmt = $conn->prepare("
    UPDATE job_notifications
    SET is_read = 1
    WHERE customer_id = ?
");

$updateStmt->bind_param('i', $customerId);
$updateStmt->execute();
$updateStmt->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Job Notifications | Netheret IT</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 30px;
            background: #121212;
            color: white;
            font-family: Arial, sans-serif;
        }

        .container {
            max-width: 900px;
            margin: auto;
        }

        h1 {
            color: #ffeb3d;
        }

        .back {
            display: inline-block;
            margin-bottom: 25px;
            color: #ffeb3d;
            text-decoration: none;
        }

        .notification {
            background: #202020;
            border-left: 5px solid #ffeb3d;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 18px;
        }

        .notification h2 {
            color: #ffeb3d;
            font-size: 19px;
        }

        .notification p {
            color: #ddd;
            line-height: 1.6;
        }

        .date {
            color: #aaa;
            font-size: 13px;
        }

        .empty {
            background: #202020;
            padding: 20px;
            border-radius: 8px;
            color: #ccc;
        }

        @media (max-width: 600px) {
            body {
                padding: 15px;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <a class="back" href="dashboard.php">
        &larr; Back to Customer Dashboard
    </a>

    <h1>My Job Notifications</h1>

    <p>Keep up to date with changes to your on-site support jobs.</p>

    <?php if (empty($notifications)): ?>

        <div class="empty">
            You do not have any job notifications yet.
        </div>

    <?php else: ?>

        <?php foreach ($notifications as $notification): ?>

            <div class="notification">

                <h2>
                    <?php
                    echo htmlspecialchars(
                        $notification['job_title'],
                        ENT_QUOTES,
                        'UTF-8'
                    );
                    ?>
                </h2>

                <p>
                    <?php
                    echo htmlspecialchars(
                        $notification['message'],
                        ENT_QUOTES,
                        'UTF-8'
                    );
                    ?>
                </p>

                <span class="date">
                    <?php
                    echo htmlspecialchars(
                        date(
                            'd/m/Y H:i',
                            strtotime($notification['created_at'])
                        ),
                        ENT_QUOTES,
                        'UTF-8'
                    );
                    ?>
                </span>

            </div>

        <?php endforeach; ?>

    <?php endif; ?>

</div>

</body>
</html>
