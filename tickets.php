<?php
session_start();

// Check that the user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Only staff can access this page
if (($_SESSION['role'] ?? '') !== 'staff') {
    header('Location: dashboard.php');
    exit;
}

require_once 'config.php';

$staffId = (int) $_SESSION['user_id'];

$username = htmlspecialchars(
    $_SESSION['username'] ?? 'Staff',
    ENT_QUOTES,
    'UTF-8'
);

// Create a security token for the forms
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';

// Handle ticket assignment and status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $ticketId = filter_input(INPUT_POST, 'ticket_id', FILTER_VALIDATE_INT);
    $token = $_POST['csrf_token'] ?? '';

    if (
        !$ticketId ||
        !is_string($token) ||
        !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        $message = 'Invalid request. Please try again.';

    } elseif (isset($_POST['update_status'])) {

        // Handle status updates
        $newStatus = $_POST['status'] ?? '';
        $allowedStatuses = ['Open', 'In Progress', 'Completed'];

        if (
            !is_string($newStatus) ||
            !in_array($newStatus, $allowedStatuses, true)
        ) {
            $message = 'Invalid ticket status.';
        } else {
            $stmt = $conn->prepare("
                UPDATE support_requests
                SET status = ?
                WHERE id = ? AND staff_id = ?
            ");

            $stmt->bind_param('sii', $newStatus, $ticketId, $staffId);
            $stmt->execute();

            if ($stmt->affected_rows > 0) {
                $message = 'Ticket status updated successfully!';
            } else {
                $message = 'No changes made. Check that the ticket is assigned to you.';
            }

            $stmt->close();
        }

    } else {

        // Handle ticket assignment
        $stmt = $conn->prepare("
            UPDATE support_requests
            SET staff_id = ?
            WHERE id = ?
            AND (staff_id IS NULL OR staff_id = ?)
        ");

        $stmt->bind_param('iii', $staffId, $ticketId, $staffId);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            $message = 'Ticket successfully assigned to you!';
        } else {
            $message = 'This ticket may already be assigned to another staff member.';
        }

        $stmt->close();
    }
}

// Get support tickets, descriptions and usernames
$sql = "
    SELECT
        support_requests.id,
        support_requests.subject,
        support_requests.description,
        support_requests.priority,
        support_requests.status,
        support_requests.created_at,
        users.username AS customer_name,
        staff.username AS staff_name,
        support_requests.staff_id
    FROM support_requests
    INNER JOIN users
        ON support_requests.customer_id = users.id
    LEFT JOIN users AS staff
        ON support_requests.staff_id = staff.id
    ORDER BY support_requests.created_at DESC
";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Ticket Management | Netheret</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: #121212;
            color: white;
            min-height: 100vh;
        }

        .header {
            background: #000;
            padding: 25px 35px;
            border-bottom: 2px solid #ffeb3d;
        }

        .header h1 {
            color: #ffeb3d;
            margin-bottom: 8px;
        }

        .header p {
            color: #ddd;
        }

        .main-content {
            padding: 35px;
            overflow-x: auto;
        }

        .back-link {
            display: inline-block;
            color: #ffeb3d;
            margin-bottom: 25px;
            text-decoration: none;
        }

        .ticket-container {
            background: #1e1e1e;
            padding: 25px;
            border-radius: 10px;
        }

        .ticket-container h2 {
            color: #ffeb3d;
            margin-bottom: 20px;
        }

        .message {
            background: #333;
            color: #ffeb3d;
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 6px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1100px;
        }

        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #444;
        }

        th {
            color: #ffeb3d;
        }

        td {
            color: #ddd;
        }

        .priority {
            font-weight: bold;
        }

        .high {
            color: #ff6b6b;
        }

        .medium {
            color: #ff9800;
        }

        .low {
            color: #ffeb3d;
        }

        .status {
            color: #4caf50;
            font-weight: bold;
        }

        .assign-button,
        .update-button,
        .details-button {
            background: #ffeb3d;
            color: #121212;
            border: none;
            padding: 9px 12px;
            border-radius: 5px;
            font-weight: bold;
            cursor: pointer;
            margin-top: 5px;
        }

        .assign-button:hover,
        .update-button:hover,
        .details-button:hover {
            background: white;
        }

        .status-select {
            background: #333;
            color: white;
            border: 1px solid #ffeb3d;
            padding: 8px;
            border-radius: 5px;
            margin-bottom: 5px;
        }

        .description-row td {
            background: #292929;
            white-space: normal;
            line-height: 1.6;
        }

        .description-row strong {
            color: #ffeb3d;
        }

        .empty-message {
            padding: 20px 0;
            color: #ddd;
        }

        @media (max-width: 600px) {
            .header {
                padding: 20px;
            }

            .main-content {
                padding: 15px;
            }

            .ticket-container {
                padding: 15px;
            }
        }
    </style>
</head>

<body>

    <header class="header">
        <h1>Ticket Management</h1>
        <p>Welcome, <?php echo $username; ?>. Manage customer support requests here.</p>
    </header>

    <main class="main-content">

        <a href="staff_dashboard.php" class="back-link">
            &larr; Back to Staff Dashboard
        </a>

        <section class="ticket-container">

            <h2>Customer Support Tickets</h2>

            <?php if ($message !== ''): ?>
                <div class="message" role="status">
                    <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <?php if ($result->num_rows > 0): ?>

                <table>
                    <thead>
                        <tr>
                            <th>Ticket ID</th>
                            <th>Subject</th>
                            <th>Customer</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Assigned Staff</th>
                            <th>Date Created</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php while ($ticket = $result->fetch_assoc()): ?>

                            <tr>
                                <td>
                                    #<?php echo (int) $ticket['id']; ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($ticket['subject'], ENT_QUOTES, 'UTF-8'); ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($ticket['customer_name'], ENT_QUOTES, 'UTF-8'); ?>
                                </td>

                                <td>
                                    <span class="priority <?php echo strtolower($ticket['priority']); ?>">
                                        <?php echo htmlspecialchars($ticket['priority'], ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="status">
                                        <?php echo htmlspecialchars($ticket['status'], ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>

                                <td>
                                    <?php
                                    echo $ticket['staff_name']
                                        ? htmlspecialchars($ticket['staff_name'], ENT_QUOTES, 'UTF-8')
                                        : 'Unassigned';
                                    ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($ticket['created_at'], ENT_QUOTES, 'UTF-8'); ?>
                                </td>

                                <td>

                                    <details>
                                        <summary class="details-button">
                                            View Details
                                        </summary>

                                        <p style="margin-top: 10px; white-space: normal;">
                                            <?php echo nl2br(htmlspecialchars($ticket['description'], ENT_QUOTES, 'UTF-8')); ?>
                                        </p>
                                    </details>

                                    <a
                                        href="ticket_details.php?id=<?php echo (int) $ticket['id']; ?>"
                                        class="details-button"
                                        style="display: inline-block; text-decoration: none;"
                                    >
                                        Open Full Ticket
                                    </a>

                                    <?php if ($ticket['staff_id'] === null): ?>

                                        <form method="POST" action="tickets.php">

                                            <input
                                                type="hidden"
                                                name="ticket_id"
                                                value="<?php echo (int) $ticket['id']; ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>"
                                            >

                                            <button type="submit" class="assign-button">
                                                Assign to Me
                                            </button>

                                        </form>

                                    <?php elseif ((int) $ticket['staff_id'] === $staffId): ?>

                                        <form method="POST" action="tickets.php">

                                            <input
                                                type="hidden"
                                                name="ticket_id"
                                                value="<?php echo (int) $ticket['id']; ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>"
                                            >

                                            <label for="status-<?php echo (int) $ticket['id']; ?>">
                                                Update status:
                                            </label>

                                            <select
                                                name="status"
                                                id="status-<?php echo (int) $ticket['id']; ?>"
                                                class="status-select"
                                                required
                                            >
                                                <option value="Open"
                                                    <?php echo $ticket['status'] === 'Open' ? 'selected' : ''; ?>>
                                                    Open
                                                </option>

                                                <option value="In Progress"
                                                    <?php echo $ticket['status'] === 'In Progress' ? 'selected' : ''; ?>>
                                                    In Progress
                                                </option>

                                                <option value="Completed"
                                                    <?php echo $ticket['status'] === 'Completed' ? 'selected' : ''; ?>>
                                                    Completed
                                                </option>
                                            </select>

                                            <button
                                                type="submit"
                                                name="update_status"
                                                value="1"
                                                class="update-button"
                                            >
                                                Update Status
                                            </button>

                                        </form>

                                    <?php else: ?>

                                        Already Assigned

                                    <?php endif; ?>

                                </td>
                            </tr>

                        <?php endwhile; ?>

                    </tbody>
                </table>

            <?php else: ?>

                <p class="empty-message">
                    There are currently no support tickets to display.
                </p>

            <?php endif; ?>

        </section>

    </main>

</body>
</html>
