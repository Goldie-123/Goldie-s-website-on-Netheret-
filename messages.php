<?php
session_start();

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    header('Location: login.php');
    exit;
}

if (!in_array($_SESSION['role'], ['customer', 'staff'], true)) {
    header('Location: dashboard.php');
    exit;
}

require_once 'config.php';

$userId = (int) $_SESSION['user_id'];
$role = $_SESSION['role'];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$selectedTicket = null;

// Get tickets belonging to this customer or assigned to this staff member.
if ($role === 'customer') {
    $stmt = $conn->prepare(
        "SELECT id, subject, status, priority
         FROM support_requests
         WHERE customer_id = ?
         ORDER BY created_at DESC"
    );
} else {
    $stmt = $conn->prepare(
        "SELECT id, subject, status, priority
         FROM support_requests
         WHERE staff_id = ?
         ORDER BY created_at DESC"
    );
}

$stmt->bind_param("i", $userId);
$stmt->execute();
$tickets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Work out which ticket the user has selected.
$ticketId = (int) ($_GET['ticket_id'] ?? $_POST['ticket_id'] ?? 0);

// Check that the selected ticket belongs to the customer
// or is assigned to the logged-in staff member.
if ($ticketId > 0) {
    if ($role === 'customer') {
        $stmt = $conn->prepare(
            "SELECT id, subject, status, priority
             FROM support_requests
             WHERE id = ? AND customer_id = ?"
        );
    } else {
        $stmt = $conn->prepare(
            "SELECT id, subject, status, priority
             FROM support_requests
             WHERE id = ? AND staff_id = ?"
        );
    }

    $stmt->bind_param("ii", $ticketId, $userId);
    $stmt->execute();
    $selectedTicket = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Mark customer messages as read when assigned staff opens the conversation.
if ($selectedTicket && $role === 'staff') {
    $stmt = $conn->prepare(
        "UPDATE ticket_messages
         SET staff_read = 1
         WHERE ticket_id = ?
         AND staff_read = 0
         AND sender_id <> ?"
    );

    $stmt->bind_param("ii", $ticketId, $userId);
    $stmt->execute();
    $stmt->close();
}

// Process a new message.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals(
        $_SESSION['csrf_token'],
        $_POST['csrf_token'] ?? ''
    )) {
        $error = 'Security check failed. Please refresh the page.';
    } elseif (!$selectedTicket) {
        $error = 'Please select a valid support ticket.';
    } else {
        $message = trim($_POST['message'] ?? '');

        if ($message === '') {
            $error = 'Please type a message before sending.';
        } elseif (strlen($message) > 2000) {
            $error = 'Your message must be 2,000 characters or fewer.';
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO ticket_messages
                 (ticket_id, sender_id, message)
                 VALUES (?, ?, ?)"
            );

            $stmt->bind_param("iis", $ticketId, $userId, $message);
            $stmt->execute();
            $stmt->close();

            header("Location: messages.php?ticket_id=" . $ticketId);
            exit;
        }
    }
}

// Load the conversation for the selected ticket.
$messages = [];

if ($selectedTicket) {
    $stmt = $conn->prepare(
        "SELECT ticket_messages.message,
                ticket_messages.created_at,
                ticket_messages.sender_id,
                users.username,
                users.role
         FROM ticket_messages
         JOIN users ON ticket_messages.sender_id = users.id
         WHERE ticket_messages.ticket_id = ?
         ORDER BY ticket_messages.created_at ASC,
                  ticket_messages.id ASC"
    );

    $stmt->bind_param("i", $ticketId);
    $stmt->execute();
    $messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Netheret Messages</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    padding: 20px;
    background: #121212;
    color: white;
    font-family: Arial, sans-serif;
}

h1, h2 {
    color: #ffeb3d;
}

.page {
    max-width: 950px;
    margin: 0 auto;
}

.ticket-list {
    background: #1e1e1e;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.ticket-list a {
    display: block;
    color: white;
    background: #292929;
    padding: 12px;
    margin: 8px 0;
    border-radius: 5px;
    text-decoration: none;
}

.ticket-list a:hover {
    background: #3a3a3a;
}

.ticket-list a.selected {
    border-left: 4px solid #ffeb3d;
}

.chat {
    background: #1e1e1e;
    border-radius: 8px;
    padding: 20px;
}

.message {
    background: #333;
    padding: 12px;
    border-radius: 10px;
    margin: 12px 0;
    max-width: 75%;
    overflow-wrap: anywhere;
}

.message.own {
    background: #665e00;
    margin-left: auto;
}

.message strong {
    color: #ffeb3d;
}

.message-time {
    display: block;
    color: #ccc;
    font-size: 12px;
    margin-top: 8px;
}

textarea {
    width: 100%;
    min-height: 90px;
    padding: 12px;
    margin-top: 15px;
    border-radius: 5px;
    border: 1px solid #777;
    background: #292929;
    color: white;
    font: inherit;
    resize: vertical;
}

button {
    background: #ffeb3d;
    color: #121212;
    border: none;
    padding: 12px 22px;
    margin-top: 10px;
    border-radius: 5px;
    font-weight: bold;
    cursor: pointer;
}

button:hover {
    background: #f5d900;
}

.error {
    color: #ff8a80;
    margin: 12px 0;
}

.back-link {
    display: inline-block;
    color: #ffeb3d;
    margin-top: 20px;
}

@media (max-width: 600px) {
    body {
        padding: 12px;
    }

    .message {
        max-width: 90%;
    }
}
</style>
</head>

<body>

<div class="page">

<h1>Netheret Messages</h1>

<p>
    Welcome, <?= htmlspecialchars($_SESSION['username'] ?? 'User', ENT_QUOTES, 'UTF-8') ?>.
    Select a support ticket to view its conversation.
</p>

<div class="ticket-list">
    <h2>Your Support Tickets</h2>

    <?php if (empty($tickets)): ?>

        <p>No support tickets are available yet.</p>

    <?php else: ?>

        <?php foreach ($tickets as $ticket): ?>
            <a
                href="messages.php?ticket_id=<?= (int) $ticket['id'] ?>"
                class="<?= $ticketId === (int) $ticket['id'] ? 'selected' : '' ?>"
            >
                #<?= (int) $ticket['id'] ?> -
                <?= htmlspecialchars($ticket['subject'], ENT_QUOTES, 'UTF-8') ?>
                (<?= htmlspecialchars($ticket['status'], ENT_QUOTES, 'UTF-8') ?>)
            </a>
        <?php endforeach; ?>

    <?php endif; ?>
</div>

<?php if ($selectedTicket): ?>

    <div class="chat">

        <h2>
            Conversation for Ticket #<?= (int) $selectedTicket['id'] ?>
        </h2>

        <p>
            <?= htmlspecialchars($selectedTicket['subject'], ENT_QUOTES, 'UTF-8') ?>
        </p>

        <?php if (empty($messages)): ?>

            <p>No messages yet. You can start the conversation below.</p>

        <?php else: ?>

            <?php foreach ($messages as $msg): ?>

                <div class="message <?= (int) $msg['sender_id'] === $userId ? 'own' : '' ?>">

                    <strong>
                        <?= htmlspecialchars($msg['username'], ENT_QUOTES, 'UTF-8') ?>
                        (<?= htmlspecialchars(ucfirst($msg['role']), ENT_QUOTES, 'UTF-8') ?>)
                    </strong>

                    <p><?= nl2br(htmlspecialchars($msg['message'], ENT_QUOTES, 'UTF-8')) ?></p>

                    <span class="message-time">
                        <?= htmlspecialchars(date('d M Y, H:i', strtotime($msg['created_at'])), ENT_QUOTES, 'UTF-8') ?>
                    </span>

                </div>

            <?php endforeach; ?>

        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <p class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>

        <form method="POST" action="messages.php?ticket_id=<?= (int) $ticketId ?>">

            <input type="hidden" name="ticket_id" value="<?= (int) $ticketId ?>">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>"
            >

            <label for="message">Write your message:</label>

            <textarea
                id="message"
                name="message"
                maxlength="2000"
                placeholder="Type your reply here..."
                required
            ></textarea>

            <button type="submit">Send Message</button>

        </form>

    </div>

<?php elseif ($ticketId > 0): ?>

    <p class="error">That ticket could not be found or you do not have permission to view it.</p>

<?php endif; ?>

<a class="back-link"
   href="<?= $role === 'staff' ? 'staff_dashboard.php' : 'dashboard.php' ?>">
    Back to Dashboard
</a>

</div>

</body>
</html>
