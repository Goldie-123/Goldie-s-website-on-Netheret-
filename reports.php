<?php

session_start();
require_once 'config.php';

// Only staff and admins can access reports.
if (
    !isset($_SESSION['user_id']) ||
    !in_array($_SESSION['role'] ?? '', ['staff', 'admin'], true)
) {
    header('Location: login.php');
    exit;
}

// Ticket statistics.
$ticketStats = [
    'Open' => 0,
    'In Progress' => 0,
    'Completed' => 0
];

$sql = "SELECT status, COUNT(*) AS total
        FROM support_requests
        GROUP BY status";

$result = $conn->query($sql);

while ($row = $result->fetch_assoc()) {
    if (isset($ticketStats[$row['status']])) {
        $ticketStats[$row['status']] = (int)$row['total'];
    }
}

// Urgent tickets.
$sql = "SELECT COUNT(*) AS total
        FROM support_requests
        WHERE priority = 'High'
        AND status != 'Completed'";

$result = $conn->query($sql);

$urgentTickets = (int)$result->fetch_assoc()['total'];

// On-site job statistics.
$jobStats = [
    'Scheduled' => 0,
    'In Progress' => 0,
    'Completed' => 0,
    'Cancelled' => 0
];

$sql = "SELECT status, COUNT(*) AS total
        FROM onsite_jobs
        GROUP BY status";

$result = $conn->query($sql);

while ($row = $result->fetch_assoc()) {
    if (isset($jobStats[$row['status']])) {
        $jobStats[$row['status']] = (int)$row['total'];
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reports | Netheret IT Support Solutions</title>

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
    padding: 18px 25px;
    border-bottom: 2px solid #ffeb3d;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
}

header h2 {
    color: #ffeb3d;
    margin: 0;
}

header a {
    color: white;
    text-decoration: none;
    margin-left: 15px;
}

.container {
    display: flex;
    min-height: 100vh;
}

.sidebar {
    width: 230px;
    background: #000;
    padding: 25px 15px;
    flex-shrink: 0;
}

.sidebar a {
    display: block;
    color: white;
    text-decoration: none;
    padding: 13px;
    margin-bottom: 7px;
    border-radius: 5px;
}

.sidebar a:hover,
.sidebar a.active {
    background: #ffeb3d;
    color: #000;
}

.main-content {
    flex: 1;
    padding: 30px;
    min-width: 0;
}

.page-heading h1 {
    color: #ffeb3d;
    margin-top: 0;
}

.summary-cards {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 18px;
    margin-top: 25px;
}

.summary-card,
.report-section {
    background: #222;
    border: 1px solid #444;
    border-radius: 10px;
    padding: 22px;
    margin-bottom: 20px;
}

.summary-card h3 {
    color: #ccc;
    font-size: 15px;
}

.summary-card p {
    font-size: 30px;
    font-weight: bold;
    color: #ffeb3d;
    margin-bottom: 0;
}

.report-section h2 {
    color: #ffeb3d;
}

.report-filter label {
    display: block;
    margin: 12px 0 6px;
    color: #ffeb3d;
}

.report-filter input,
.report-filter select {
    padding: 10px;
    border-radius: 5px;
    border: 1px solid #777;
    max-width: 280px;
    width: 100%;
}

.report-button {
    display: inline-block;
    padding: 11px 18px;
    margin: 15px 8px 0 0;
    background: #ffeb3d;
    color: #000;
    border: none;
    border-radius: 5px;
    font-weight: bold;
    text-decoration: none;
    cursor: pointer;
}

.report-button:hover {
    background: #fff176;
}

.report-section table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
}

.report-section th,
.report-section td {
    padding: 12px;
    border-bottom: 1px solid #555;
    text-align: left;
}

.report-section th {
    color: #ffeb3d;
}

@media (max-width: 900px) {
    .summary-cards {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 600px) {
    .container {
        flex-direction: column;
    }

    .sidebar {
        width: 100%;
    }

    .summary-cards {
        grid-template-columns: 1fr;
    }

    .main-content {
        padding: 18px;
    }

    .report-section {
        overflow-x: auto;
    }
}
</style>
</head>

<body>

<header>
    <h2>Netheret IT Support Solutions</h2>

    <div>
        <a href="settings.php">Account</a>
        <a href="messages.php">Notifications / Messages</a>
    </div>
</header>

<div class="container">

    <nav class="sidebar">
        <a href="staff_dashboard.php">Dashboard</a>
        <a href="tickets.php">Tickets</a>
        <a href="jobs.php">Jobs</a>
        <a href="knowledge.html">Knowledge Base</a>
        <a href="messages.php">Messages</a>
        <a href="reports.php" class="active">Reports</a>
        <a href="contactus.html">Contact Us</a>
        <a href="settings.php">Settings</a>
        <a href="logout.php">Log Out</a>
    </nav>

    <main class="main-content">

        <div class="page-heading">
            <h1>Reports</h1>
            <p>View and analyse support requests and on-site jobs.</p>
        </div>

        <!-- Date filters -->
        <section class="report-section report-filter">
            <h2>Filter Reports</h2>

            <form method="GET" action="reports.php">

                <label for="start_date">Start Date</label>
                <input type="date" id="start_date" name="start_date"
                value="<?php echo htmlspecialchars($_GET['start_date'] ?? ''); ?>">

                <label for="end_date">End Date</label>
                <input type="date" id="end_date" name="end_date"
                value="<?php echo htmlspecialchars($_GET['end_date'] ?? ''); ?>">

                <br>

                <button type="submit" class="report-button">
                    Generate Report
                </button>

                <a href="reports.php" class="report-button">
                    Reset Filters
                </a>

            </form>
        </section>

        <!-- Ticket summary cards -->
        <h2>Ticket Summary</h2>

        <section class="summary-cards">

            <div class="summary-card">
                <h3>Open Tickets</h3>
                <p><?php echo $ticketStats['Open']; ?></p>
            </div>

            <div class="summary-card">
                <h3>In Progress</h3>
                <p><?php echo $ticketStats['In Progress']; ?></p>
            </div>

            <div class="summary-card">
                <h3>Completed Tickets</h3>
                <p><?php echo $ticketStats['Completed']; ?></p>
            </div>

            <div class="summary-card">
                <h3>Urgent Tickets</h3>
                <p><?php echo $urgentTickets; ?></p>
            </div>

            <div class="summary-card">
                <h3>Scheduled Jobs</h3>
                <p><?php echo $jobStats['Scheduled']; ?></p>
            </div>

            <div class="summary-card">
                <h3>Jobs In Progress</h3>
                <p><?php echo $jobStats['In Progress']; ?></p>
            </div>

            <div class="summary-card">
                <h3>Completed Jobs</h3>
                <p><?php echo $jobStats['Completed']; ?></p>
            </div>

            <div class="summary-card">
                <h3>Cancelled Jobs</h3>
                <p><?php echo $jobStats['Cancelled']; ?></p>
            </div>

        </section>

        <!-- Weekly performance -->
        <section class="report-section">
            <h2>Weekly Performance</h2>
            <p>Review ticket performance and progress during the week.</p>

            <p>Open Tickets: <?php echo $ticketStats['Open']; ?></p>
            <p>In Progress: <?php echo $ticketStats['In Progress']; ?></p>
            <p>Completed: <?php echo $ticketStats['Completed']; ?></p>
        </section>

        <!-- Common IT issues -->
        <section class="report-section">
            <h2>Common IT Issues</h2>

            <table>
                <thead>
                    <tr>
                        <th>Issue Category</th>
                        <th>Description</th>
                    </tr>
                </thead>

                <tbody>
                    <tr>
                        <td>Network</td>
                        <td>Wi-Fi and internet connection problems</td>
                    </tr>

                    <tr>
                        <td>Printer</td>
                        <td>Printer connection and printing problems</td>
                    </tr>

                    <tr>
                        <td>Hardware</td>
                        <td>Computer and equipment problems</td>
                    </tr>

                    <tr>
                        <td>Microsoft 365</td>
                        <td>Email and software problems</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <!-- Export -->
        <section class="report-section">
            <h2>Export Report</h2>
            <p>Export your report for record keeping.</p>

            <button class="report-button" type="button"
            onclick="window.print()">
                Print / Save as PDF
            </button>
        </section>

    </main>

</div>

</body>
</html>


