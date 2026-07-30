<?php

declare(strict_types=1);

// PATH NOTE: three levels up to reach /includes (see index.php).
require_once __DIR__ . '/../../../includes/admin_bootstrap.php';

/*
 * POST-only, CSRF-verified deletion. See teachers/index.php for the full
 * reasoning on why a destructive action must not be a GET link.
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// Verify CSRF before touching the database.
$token = $_POST['csrf_token'] ?? null;
if (!verifyCsrfToken(is_string($token) ? $token : null)) {
    $_SESSION['flash'] = 'Security check failed. Please try again.';
    header('Location: index.php');
    exit;
}

// Validate the id.
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if ($id === false || $id === null) {
    $_SESSION['flash'] = 'Invalid subject id.';
    header('Location: index.php');
    exit;
}

// Delete via prepared statement.
$stmt = $pdo->prepare('DELETE FROM subjects WHERE id = :id');
$stmt->execute([':id' => $id]);

$_SESSION['flash'] = $stmt->rowCount() > 0
    ? 'Subject deleted.'
    : 'No subject found with that id.';

header('Location: index.php');
exit;
