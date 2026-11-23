<?php

session_start();
require_once 'config.php';

// Only administrators can manage users.
if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'admin'
) {
    header('Location: login.php');
    exit;
}

// Create a security token.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';

// Change a user's role.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        exit('Invalid security token. Please refresh the page.');
    }

    $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    $newRole = $_POST['role'] ?? '';

    if (!$userId || !in_array($newRole, ['customer', 'staff'], true)) {
        $message = 'Invalid user or role.';
    } else {
        $sql = "UPDATE users
                SET role = ?
                WHERE id = ?
                AND role IN ('customer', 'staff')";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param('si', $newRole, $userId);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            $message = 'User role updated successfully.';
        } else {
            $message = 'No role change was made.';
        }

        $stmt->close();
    }
}

// Load registered users.
$sql = "SELECT id, username, email, role, createdat
        FROM users
        ORDER BY createdat DESC";

$result = $conn->query($sql);

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Manage Users | Netheret</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #121212;
    color: white;
}

header {
    background: #000;
    padding: 20px;
    border-bottom: 2px solid #ffeb3d;
}

header h2 {
    color: #ffeb3d;
    margin: 0;
}

main {
    padding: 30px;
}

h1 {
    color: #ffeb3d;
}

.user-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 25px;
    background: #222;
}

.user-table th,
.user-table td {
    padding: 14px;
    border-bottom: 1px solid #555;
    text-align: left;
}

.user-table th {
    color: #ffeb3d;
}

select {
    padding: 8px;
    border-radius: 5px;
}

button {
    padding: 9px 14px;
    background: #ffeb3d;
    color: #000;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-weight: bold;
}

button:hover {
    background: #fff176;
}

.message {
    background: #292929;
    border-left: 4px solid #ffeb3d;
    padding: 12px;
    margin: 20px 0;
}

.back-link {
    color: #ffeb3d;
    text-decoration: none;
}

@media(max-width: 700px) {
    main {
        padding: 15px;
    }

    .table-container {
        overflow-x: auto;
    }

    .user-table {
        min-width: 650px;
    }
}
</style>
</head>

<body>

<header>
    <h2>Netheret IT Support Solutions</h2>
</header>

<main>

    <h1>Manage Users</h1>

    <p>View registered users and manage their account roles.</p>

    <a href="staff_dashboard.php" class="back-link">
        Back to Dashboard
    </a>

    <?php if ($message !== ''): ?>
        <div class="message">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <div class="table-container">

        <table class="user-table">

            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Current Role</th>
                    <th>Change Role</th>
                </tr>
            </thead>

            <tbody>

                <?php while ($user = $result->fetch_assoc()): ?>

                    <tr>
                        <td>
                            <?php echo (int)$user['id']; ?>
                        </td>

                        <td>
                            <?php echo htmlspecialchars($user['username']); ?>
                        </td>

                        <td>
                            <?php echo htmlspecialchars($user['email']); ?>
                        </td>

                        <td>
                            <?php echo htmlspecialchars($user['role']); ?>
                        </td>

                        <td>

                            <?php if ($user['role'] !== 'admin'): ?>

                                <form method="POST" action="manage_users.php">

                                    <input type="hidden"
                                           name="csrf_token"
                                           value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                                    <input type="hidden"
                                           name="user_id"
                                           value="<?php echo (int)$user['id']; ?>">

                                    <select name="role" required>

                                        <option value="customer"
                                            <?php echo $user['role'] === 'customer' ? 'selected' : ''; ?>>
                                            Customer
                                        </option>

                                        <option value="staff"
                                            <?php echo $user['role'] === 'staff' ? 'selected' : ''; ?>>
                                            Staff
                                        </option>

                                    </select>

                                    <button type="submit">
                                        Update
                                    </button>

                                </form>

                            <?php else: ?>

                                Administrator account

                            <?php endif; ?>

                        </td>
                    </tr>

                <?php endwhile; ?>

            </tbody>

        </table>

    </div>

</main>

</body>
</html>
