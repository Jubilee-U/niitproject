<?php

declare(strict_types=1);

/**
 * Public entry point (web root) + database health check.
 *
 * Point your web server's document root at this /public directory so that
 * /config, /vendor, and .env stay outside the web-accessible path.
 */

$appName = 'School Admission Management System';

$dbConnected   = false;
$statusMessage = '';

try {
    // Loads env vars, validates them, and creates the $pdo instance.
    require_once __DIR__ . '/../config/database.php';

    // Simple connectivity test: confirms we can actually execute a statement.
    $pdo->query('SELECT 1');

    $dbConnected   = true;
    $statusMessage = 'Setup complete — database connected';
} catch (\Throwable $e) {
    // Log the real error server-side; never expose details to the browser.
    error_log('Setup check failed: ' . $e->getMessage());
    http_response_code(500);

    $dbConnected   = false;
    $statusMessage = 'Setup incomplete — could not connect to the database. '
        . 'Check your .env settings and confirm the database server is running.';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></title>
</head>
<body>
    <main>
        <h1><?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></h1>
        <p><?= htmlspecialchars($statusMessage, ENT_QUOTES, 'UTF-8') ?></p>
    </main>
</body>
</html>
