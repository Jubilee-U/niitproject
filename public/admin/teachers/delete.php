<?php

declare(strict_types=1);

// PATH NOTE: three levels up to reach /includes (see index.php).
require_once __DIR__ . '/../../../includes/admin_bootstrap.php';
// Admin-only enforced; $pdo available; session started.

/*
 * This endpoint has NO HTML and only ever deletes on a POST. See the long note
 * in index.php for the full reasoning, in short:
 *   - GET must not change state (prefetchers/crawlers/misclicks fire GETs), so
 *     we reject anything that isn't POST.
 *   - We verify a per-session CSRF token so a cross-site auto-submitting form
 *     can't delete rows using the admin's logged-in session.
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// CSRF check first — before we touch the database.
$token = $_POST['csrf_token'] ?? null;
if (!verifyCsrfToken(is_string($token) ? $token : null)) {
    $_SESSION['flash'] = 'Security check failed. Please try again.';
    header('Location: index.php');
    exit;
}

// Validate the id.
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if ($id === false || $id === null) {
    $_SESSION['flash'] = 'Invalid teacher id.';
    header('Location: index.php');
    exit;
}

// Delete via prepared statement.
$stmt = $pdo->prepare('DELETE FROM teachers WHERE id = :id');
$stmt->execute([':id' => $id]);

// rowCount() tells us whether a row actually matched, for accurate feedback.
$_SESSION['flash'] = $stmt->rowCount() > 0
    ? 'Teacher deleted.'
    : 'No teacher found with that id.';

header('Location: index.php');
exit;
