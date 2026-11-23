<?php
session_start();
require_once 'config.php';

// Only logged-in customers can access this page
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header('Location: login.php');
    exit;
}

$customerId = (int) $_SESSION['user_id'];

// Get this customer's jobs and assigned staff member
$stmt = $conn->prepare("
    SELECT
        onsite_jobs.job_title,
        onsite_jobs.job_description,
        onsite_jobs.scheduled_date,
        onsite_jobs.scheduled_time,
        onsite_jobs.status,
        staff.username AS staff_name
    FROM onsite_jobs
    INNER JOIN users AS staff
        ON onsite_jobs.staff_id = staff.id
    WHERE onsite_jobs.customer_id = ?
    ORDER BY onsite_jobs.scheduled_date DESC,
             onsite_jobs.scheduled_time DESC
");

$stmt->bind_param('i', $customerId);
$stmt->execute();
$jobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My On-site Jobs | Netheret IT Support</title>

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
            max-width: 1000px;
            margin: auto;
        }

        h1 {
            color: #ffeb3d;
        }

        .job {
            background: #202020;
            border: 1px solid #444;
            border-left: 5px solid #ffeb3d;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .job h2 {
            color: #ffeb3d;
            margin-top: 0;
        }

        .status {
            display: inline-block;
            padding: 7px 12px;
            background: #333;
            border-radius: 5px;
            color: #ffeb3d;
            font-weight: bold;
        }

        .back {
            display: inline-block;
            margin-bottom: 25px;
            color: #ffeb3d;
            text-decoration: none;
        }

        .back:hover {
            text-decoration: underline;
        }

        .empty {
            background: #202020;
            padding: 20px;
            border-radius: 8px;
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

    <a class="back" href="dashboard.php">← Back to Dashboard</a>

    <h1>My On-site Jobs</h1>

    <p>View your scheduled appointments and check their progress below.</p>

    <?php if (empty($jobs)): ?>

        <div class="empty">
            You do not have any on-site jobs scheduled yet.
        </div>

    <?php else: ?>

        <?php foreach ($jobs as $job): ?>

            <div class="job">

                <h2>
                    <?php echo htmlspecialchars($job['job_title']); ?>
                </h2>

                <p>
                    <strong>Description:</strong><br>
                    <?php echo nl2br(htmlspecialchars($job['job_description'])); ?>
                </p>

                <p>
                    <strong>Assigned staff member:</strong>
                    <?php echo htmlspecialchars($job['staff_name']); ?>
                </p>

                <p>
                    <strong>Appointment date:</strong>
                    <?php echo htmlspecialchars($job['scheduled_date']); ?>
                </p>

                <p>
                    <strong>Appointment time:</strong>
                    <?php echo htmlspecialchars(substr($job['scheduled_time'], 0, 5)); ?>
                </p>

                <p>
                    <strong>Job status:</strong>
                    <span class="status">
                        <?php echo htmlspecialchars($job['status']); ?>
                    </span>
                </p>

            </div>

        <?php endforeach; ?>

    <?php endif; ?>

</div>

</body>
</html>