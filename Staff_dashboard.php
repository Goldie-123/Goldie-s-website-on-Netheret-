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

$username = htmlspecialchars(
    $_SESSION['username'] ?? 'Staff',
    ENT_QUOTES,
    'UTF-8'
);

$staffId = (int) $_SESSION['user_id'];

require_once 'config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function escapeHtml($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$colourMode = 'light';
$openTickets = 0;
$myTickets = 0;
$urgentTickets = 0;
$newMessages = 0;
$recentTickets = [];

$weeklyCounts = [
    'Open' => 0,
    'In Progress' => 0,
    'Completed' => 0
];

$weeklyTotal = 0;
$databaseError = false;

try {

    // Load saved colour mode
    $stmt = $conn->prepare("
        SELECT colour_mode
        FROM user_accessibility
        WHERE user_id = ?
    ");

    $stmt->bind_param('i', $staffId);
    $stmt->execute();

    $preference = $stmt->get_result()->fetch_assoc();

    if ($preference && in_array($preference['colour_mode'], ['light', 'dark'], true)) {
        $colourMode = $preference['colour_mode'];
    }

    $stmt->close();

    // Count all open tickets
    $result = $conn->query("
        SELECT COUNT(*) AS total
        FROM support_requests
        WHERE status = 'Open'
    ");

    $openTickets = (int) $result->fetch_assoc()['total'];

    // Count my unfinished tickets
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM support_requests
        WHERE staff_id = ?
        AND status != 'Completed'
    ");

    $stmt->bind_param('i', $staffId);
    $stmt->execute();
    $myTickets = (int) $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    // Count my urgent tickets
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM support_requests
        WHERE staff_id = ?
        AND priority = 'High'
        AND status != 'Completed'
    ");

    $stmt->bind_param('i', $staffId);
    $stmt->execute();
    $urgentTickets = (int) $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    // Count unread customer messages
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM ticket_messages
        INNER JOIN support_requests
            ON ticket_messages.ticket_id = support_requests.id
        INNER JOIN users
            ON ticket_messages.sender_id = users.id
        WHERE support_requests.staff_id = ?
        AND users.role = 'customer'
        AND ticket_messages.staff_read = 0
    ");

    $stmt->bind_param('i', $staffId);
    $stmt->execute();
    $newMessages = (int) $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    // Get five most recent assigned tickets
    $stmt = $conn->prepare("
        SELECT
            support_requests.id,
            support_requests.subject,
            support_requests.priority,
            support_requests.status,
            support_requests.created_at,
            users.username AS customer_name
        FROM support_requests
        INNER JOIN users
            ON support_requests.customer_id = users.id
        WHERE support_requests.staff_id = ?
        ORDER BY support_requests.created_at DESC
        LIMIT 5
    ");

    $stmt->bind_param('i', $staffId);
    $stmt->execute();
    $recentTickets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Get this week's assigned tickets
    $stmt = $conn->prepare("
        SELECT status, COUNT(*) AS total
        FROM support_requests
        WHERE staff_id = ?
        AND YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)
        GROUP BY status
    ");

    $stmt->bind_param('i', $staffId);
    $stmt->execute();
    $weeklyResults = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($weeklyResults as $row) {
        $status = $row['status'];
        $total = (int) $row['total'];

        if (array_key_exists($status, $weeklyCounts)) {
            $weeklyCounts[$status] = $total;
        }

        $weeklyTotal += $total;
    }

} catch (Throwable $e) {
    error_log('Staff dashboard error: ' . $e->getMessage());
    $databaseError = true;
}

// Calculate percentages
$openPercent = $weeklyTotal > 0
    ? round(($weeklyCounts['Open'] / $weeklyTotal) * 100)
    : 0;

$progressPercent = $weeklyTotal > 0
    ? round(($weeklyCounts['In Progress'] / $weeklyTotal) * 100)
    : 0;

$completedPercent = $weeklyTotal > 0
    ? round(($weeklyCounts['Completed'] / $weeklyTotal) * 100)
    : 0;

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Staff Dashboard | Netheret IT Support Solutions</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            color: #222222;
            min-height: 100vh;
        }

        h1, h2 {
            color: #222222;
            margin-bottom: 15px;
        }

        p {
            color: #444444;
            line-height: 1.6;
        }

        .dashboard {
            display: flex;
            min-height: 100vh;
        }

        /* SIDEBAR */
        .sidebar {
            width: 250px;
            min-width: 250px;
            background: #ffffff;
            padding: 25px 18px;
            border-right: 2px solid #ffeb3d;
            display: flex;
            flex-direction: column;
        }

        .sidebar h2 {
            color: #222222;
            margin-bottom: 5px;
        }

        .sidebar > p {
            color: #666666;
            font-size: 13px;
        }

        .profile {
            margin: 35px 0 25px;
            padding: 15px;
            background: #f5f5f5;
            border-radius: 8px;
            border-left: 3px solid #ffeb3d;
        }

        .profile h3 {
            margin-bottom: 5px;
            overflow-wrap: anywhere;
            color: #222222;
        }

        .profile p {
            color: #666666;
            font-size: 13px;
        }

        .sidebar nav {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .sidebar nav a {
            color: #222222;
            text-decoration: none;
            padding: 12px;
            border-radius: 5px;
            transition: 0.2s;
        }

        .sidebar nav a:hover,
        .sidebar nav a.active {
            background: #ffeb3d;
            color: #000000;
        }

        .message-count {
            display: inline-block;
            background: #ffeb3d;
            color: #000000;
            padding: 3px 7px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
            margin-left: 5px;
        }

        .sidebar .logout {
            margin-top: auto;
            text-align: center;
        }

        /* LOGOUT */
        .logout {
            display: inline-block;
            background: #ffeb3d;
            color: #222222;
            padding: 12px 25px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            transition: 0.2s;
        }

        .logout:hover {
            background: #f5d900;
        }

        /* MAIN CONTENT */
        .main-content {
            flex: 1;
            padding: 35px;
            min-width: 0;
        }

        .top-header {
            background: #ffffff;
            padding: 30px;
            border-radius: 10px;
            border-left: 5px solid #ffeb3d;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }

        .top-header h1 {
            color: #222222;
        }

        /* ERROR MESSAGE */
        .error-message {
            background: #ffe5e5;
            border-left: 4px solid #cc3333;
            padding: 15px;
            border-radius: 6px;
            margin-top: 20px;
        }

        .error-message p {
            color: #7a1717;
        }

        /* STATISTICS CARDS */
        .ticket-cards {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 20px;
            margin-top: 25px;
        }

        .ticket-card {
            background: #ffffff;
            padding: 25px;
            border-radius: 10px;
            border-bottom: 3px solid #ffeb3d;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            transition: transform 0.2s;
        }

        .ticket-card:hover {
            transform: translateY(-4px);
        }

        .ticket-card h3 {
            color: #444444;
            margin-bottom: 15px;
        }

        .ticket-number {
            color: #222222;
            font-size: 32px;
            font-weight: bold;
        }

        /* RECENT TICKETS */
        .recent-tickets {
            background: #ffffff;
            padding: 25px;
            margin-top: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }

        .recent-tickets h2 {
            margin-bottom: 20px;
        }

        .ticket-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #dddddd;
            color: #222222;
            gap: 15px;
        }

        .ticket-row:last-child {
            border-bottom: none;
        }

        .ticket-info {
            flex: 1;
            min-width: 0;
        }

        .ticket-info strong {
            overflow-wrap: anywhere;
            color: #222222;
        }

        .ticket-info p {
            font-size: 13px;
            color: #666666;
            margin-top: 5px;
        }

        /* PRIORITY AND STATUS */
        .priority, .status {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 5px;
            font-weight: bold;
            font-size: 12px;
            white-space: nowrap;
        }

        .high {
            background: #ffb3b3;
            color: #6b0000;
        }

        .medium {
            background: #ffe0b2;
            color: #713900;
        }

        .low {
            background: #fff3a6;
            color: #554900;
        }

        .open {
            background: #c8e6c9;
            color: #155724;
        }

        .in-progress {
            background: #bbdefb;
            color: #0d47a1;
        }

        .completed {
            background: #e0e0e0;
            color: #333333;
        }

        /* VIEW BUTTON */
        .ticket-link {
            color: #665900;
            text-decoration: none;
            font-weight: bold;
            font-size: 13px;
            white-space: nowrap;
        }

        .ticket-link:hover {
            text-decoration: underline;
        }

        /* WEEKLY PERFORMANCE */
        .performance {
            background: #ffffff;
            padding: 25px;
            margin-top: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }

        .performance-item {
            margin-top: 20px;
        }

        .progress-bar {
            width: 100%;
            height: 14px;
            background: #e0e0e0;
            border-radius: 10px;
            overflow: hidden;
            margin-top: 8px;
        }

        .progress {
            height: 100%;
            border-radius: 10px;
            transition: width 0.5s;
        }

        .complete {
            background: #d9c800;
        }

        .unfinished {
            background: #ff9800;
        }

        .new {
            background: #4caf50;
        }

        /* ACCESSIBILITY */
        a:focus-visible {
            outline: 3px solid #222222;
            outline-offset: 3px;
        }

        /* DARK MODE */
        body.dark-mode {
            background: #121212;
            color: #ffffff;
        }

        body.dark-mode h1,
        body.dark-mode h2,
        body.dark-mode .sidebar h2,
        body.dark-mode .profile h3,
        body.dark-mode .ticket-card h3,
        body.dark-mode .ticket-number,
        body.dark-mode .ticket-info strong,
        body.dark-mode .ticket-row {
            color: #ffffff;
        }

        body.dark-mode p,
        body.dark-mode .sidebar > p,
        body.dark-mode .profile p,
        body.dark-mode .ticket-info p {
            color: #cccccc;
        }

        body.dark-mode .sidebar,
        body.dark-mode .top-header,
        body.dark-mode .ticket-card,
        body.dark-mode .recent-tickets,
        body.dark-mode .performance {
            background: #1e1e1e;
        }

        body.dark-mode .sidebar {
            border-right-color: #ffeb3d;
        }

        body.dark-mode .profile {
            background: #292929;
        }

        body.dark-mode .sidebar nav a {
            color: #ffffff;
        }

        body.dark-mode .sidebar nav a:hover,
        body.dark-mode .sidebar nav a.active {
            background: #ffeb3d;
            color: #000000;
        }

        body.dark-mode .ticket-row {
            border-bottom-color: #444444;
        }

        body.dark-mode .progress-bar {
            background: #444444;
        }

        body.dark-mode .ticket-link {
            color: #ffeb3d;
        }

        body.dark-mode a:focus-visible {
            outline-color: #ffeb3d;
        }

        /* RESPONSIVE DESIGN */
        @media (max-width: 1000px) {
            .main-content {
                padding: 25px;
            }

            .ticket-cards {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 850px) {
            .dashboard {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                min-width: 0;
                border-right: none;
                border-bottom: 2px solid #ffeb3d;
            }

            .sidebar .logout {
                margin-top: 20px;
            }

            .ticket-cards {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (max-width: 600px) {
            .sidebar nav {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .main-content {
                padding: 15px;
            }

            .top-header {
                padding: 20px;
            }

            .top-header h1 {
                font-size: 23px;
            }

            .ticket-cards {
                grid-template-columns: 1fr;
            }

            .ticket-card {
                padding: 20px;
            }

            .ticket-row {
                font-size: 13px;
                flex-wrap: wrap;
                align-items: flex-start;
            }

            .ticket-info {
                flex-basis: 100%;
            }

            .recent-tickets,
            .performance {
                padding: 18px;
            }
        }
    </style>
</head>

<body class="<?php echo $colourMode === 'dark' ? 'dark-mode' : 'light-mode'; ?>">

<div class="dashboard">

    <aside class="sidebar">

        <h2>Netheret</h2>
        <p>IT Support Solutions</p>

        <div class="profile">
            <h3><?php echo $username; ?></h3>
            <p>Staff Account</p>
        </div>

        <nav>
            <a href="staff_dashboard.php" class="active">Staff Dashboard</a>
            <a href="tickets.php">Ticket Management</a>
            <a href="jobs.php">Jobs</a>
            <a href="knowledge.html">Knowledge Base</a>

            <a href="messages.php">
                Messages
                <?php if ($newMessages > 0): ?>
                    <span class="message-count">
                        <?php echo $newMessages; ?>
                    </span>
                <?php endif; ?>
            </a>

            <a href="reports.php">Reports</a>
            <a href="contactus.html">Contact Us</a>
            <a href="settings.php">Account Settings</a>
        </nav>

        <a href="logout.php" class="logout">Log Out</a>

    </aside>

    <main class="main-content">

        <div class="top-header">
            <h1>Welcome to this page, <?php echo $username; ?>!</h1>
            <p>Here is your progress for this week so far.</p>
        </div>

        <?php if ($databaseError): ?>
            <div class="error-message">
                <p>
                    Some dashboard information could not be loaded.
                    Please try again later or contact your administrator.
                </p>
            </div>
        <?php endif; ?>

        <div class="ticket-cards">

            <div class="ticket-card">
                <h3>Open Tickets</h3>
                <p class="ticket-number"><?php echo $openTickets; ?></p>
            </div>

            <div class="ticket-card">
                <h3>My Unfinished Tickets</h3>
                <p class="ticket-number"><?php echo $myTickets; ?></p>
            </div>

            <div class="ticket-card">
                <h3>My Urgent Tickets</h3>
                <p class="ticket-number"><?php echo $urgentTickets; ?></p>
            </div>

        </div>

        <div class="recent-tickets">

            <h2>Recent Tickets</h2>

            <?php if (empty($recentTickets)): ?>

                <p>You currently have no assigned tickets.</p>

            <?php else: ?>

                <?php foreach ($recentTickets as $ticket): ?>

                    <?php
                    $priorityClass = match ($ticket['priority']) {
                        'High' => 'high',
                        'Medium' => 'medium',
                        'Low' => 'low',
                        default => 'low'
                    };

                    $statusClass = match ($ticket['status']) {
                        'Open' => 'open',
                        'In Progress' => 'in-progress',
                        'Completed' => 'completed',
                        default => ''
                    };

                    $createdDate = !empty($ticket['created_at'])
                        ? date('d M Y', strtotime($ticket['created_at']))
                        : 'Unknown';
                    ?>

                    <div class="ticket-row">

                        <div class="ticket-info">

                            <strong>
                                #<?php echo (int) $ticket['id']; ?> -
                                <?php echo escapeHtml($ticket['subject']); ?>
                            </strong>

                            <p>
                                Customer:
                                <?php echo escapeHtml($ticket['customer_name']); ?>
                            </p>

                            <p>
                                Created:
                                <?php echo escapeHtml($createdDate); ?>
                            </p>

                        </div>

                        <span class="priority <?php echo $priorityClass; ?>">
                            <?php echo escapeHtml($ticket['priority']); ?>
                        </span>

                        <span class="status <?php echo $statusClass; ?>">
                            <?php echo escapeHtml($ticket['status']); ?>
                        </span>

                        <a
                            class="ticket-link"
                            href="tickets.php?id=<?php echo (int) $ticket['id']; ?>"
                        >
                            View
                        </a>

                    </div>

                <?php endforeach; ?>

            <?php endif; ?>

        </div>

        <div class="performance">

            <h2>This Week's Performance</h2>

            <p>
                Tickets assigned to you this week, grouped by their current status.
            </p>

            <div class="performance-item">

                <p>
                    Open - <?php echo $weeklyCounts['Open']; ?>
                    tickets (<?php echo $openPercent; ?>%)
                </p>

                <div class="progress-bar">
                    <div
                        class="progress new"
                        style="width: <?php echo $openPercent; ?>%;"
                    ></div>
                </div>

            </div>

            <div class="performance-item">

                <p>
                    In Progress - <?php echo $weeklyCounts['In Progress']; ?>
                    tickets (<?php echo $progressPercent; ?>%)
                </p>

                <div class="progress-bar">
                    <div
                        class="progress unfinished"
                        style="width: <?php echo $progressPercent; ?>%;"
                    ></div>
                </div>

            </div>

            <div class="performance-item">

                <p>
                    Completed - <?php echo $weeklyCounts['Completed']; ?>
                    tickets (<?php echo $completedPercent; ?>%)
                </p>

                <div class="progress-bar">
                    <div
                        class="progress complete"
                        style="width: <?php echo $completedPercent; ?>%;"
                    ></div>
                </div>

            </div>

            <?php if ($weeklyTotal === 0): ?>
                <p style="margin-top: 20px;">
                    No tickets were assigned to you this week yet.
                </p>
            <?php endif; ?>

        </div>

    </main>

</div>

</body>
</html>
