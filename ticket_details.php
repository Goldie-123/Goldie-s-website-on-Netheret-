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

$staffId = (int) $_SESSION['user_id'];
$ticketId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$ticketId || $ticketId < 1) {
    exit('Invalid ticket ID.');
}

$stmt = $conn->prepare("
    SELECT
        support_requests.id,
        support_requests.subject,
        support_requests.description,
        support_requests.priority,
        support_requests.status,
        support_requests.created_at,
        users.username AS customer_name,
        users.email AS customer_email
    FROM support_requests
    INNER JOIN users
        ON support_requests.customer_id = users.id
    WHERE support_requests.id = ?
    AND support_requests.staff_id = ?
");

$stmt->bind_param('ii', $ticketId, $staffId);
$stmt->execute();

$ticket = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$ticket) {
    http_response_code(404);
    exit('Ticket not found or you do not have permission to view it.');
}

function escape($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Ticket Details | Netheret</title>

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
            max-width: 900px;
            margin: 40px auto;
            padding: 20px;
        }

        .back-link {
            display: inline-block;
            color: #ffeb3d;
            text-decoration: none;
            margin-bottom: 25px;
        }

        .ticket-card {
            background: #1e1e1e;
            padding: 30px;
            border-radius: 10px;
        }

        .ticket-card h2 {
            color: #ffeb3d;
            margin-bottom: 20px;
        }

        .ticket-info {
            margin-bottom: 20px;
            line-height: 1.7;
        }

        .ticket-info strong,
        .description strong {
            color: #ffeb3d;
        }

        .description {
            background: #292929;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
            line-height: 1.7;
            overflow-wrap: anywhere;
        }

        .description p {
            margin-top: 10px;
        }

        .messages-button {
            display: inline-block;
            background: #ffeb3d;
            color: #121212;
            padding: 12px 18px;
            border-radius: 5px;
            font-weight: bold;
            text-decoration: none;
            margin-top: 20px;
        }

        .messages-button:hover {
            background: white;
        }

        @media (max-width: 600px) {
            .header {
                padding: 20px;
            }

            .ticket-card {
                padding: 20px;
            }
        }
    </style>
</head>

<body>

    <header class="header">
        <h1>Ticket Details</h1>
        <p>View complete customer support request information.</p>
    </header>

    <main class="main-content">

        <a href="tickets.php" class="back-link">
            &larr; Back to Ticket Management
        </a>

        <section class="ticket-card">

            <h2>
                Ticket #<?php echo (int) $ticket['id']; ?>
            </h2>

            <div class="ticket-info">

                <p>
                    <strong>Subject:</strong>
                    <?php echo escape($ticket['subject']); ?>
                </p>

                <p>
                    <strong>Customer:</strong>
                    <?php echo escape($ticket['customer_name']); ?>
                </p>

                <p>
                    <strong>Email:</strong>
                    <?php echo escape($ticket['customer_email']); ?>
                </p>

                <p>
                    <strong>Priority:</strong>
                    <?php echo escape($ticket['priority']); ?>
                </p>

                <p>
                    <strong>Status:</strong>
                    <?php echo escape($ticket['status']); ?>
                </p>

                <p>
                    <strong>Date Created:</strong>
                    <?php echo escape($ticket['created_at']); ?>
                </p>

            </div>

            <div class="description">

                <strong>Ticket Description</strong>

                <p>
                    <?php echo nl2br(escape($ticket['description'])); ?>
                </p>

            </div>

            <a
                href="messages.php?ticket_id=<?php echo (int) $ticket['id']; ?>"
                class="messages-button"
            >
                Open Ticket Messages
            </a>

        </section>

    </main>

</body>
</html>
