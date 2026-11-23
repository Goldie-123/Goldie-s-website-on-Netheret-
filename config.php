<?php
declare(strict_types=1);

// Netheret IT Support Solutions database settings

$dbHost = '127.0.0.1';
$dbName = 'netheret1';
$dbUser = 'root';
$dbPassword = '';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli(
        $dbHost,
        $dbUser,
        $dbPassword,
        $dbName
    );

    $conn->set_charset('utf8mb4');

} catch (mysqli_sql_exception $exception) {

    error_log('Netheret IT database connection: ' . $exception->getMessage());

    http_response_code(503);

    exit('Database unavailable. Please check XAMPP and your database name.');
}

