<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';  // provides $pdo
require_once __DIR__ . '/../includes/auth.php';

// Must run before any output — it may set/refresh the session cookie.
startSecureSession();

// Already signed in? Skip the form and go straight to the right dashboard.
$existing = getCurrentUser($pdo);
if ($existing !== null) {
    header('Location: ' . ($existing['role'] === 'admin'
        ? 'admin/dashboard.php'
        : 'student/dashboard.php'));
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ── Verify CSRF token BEFORE looking at credentials ───────────────────
    // This confirms the POST originated from our own form, not from a page an
    // attacker made the victim's browser submit ("login CSRF" — e.g. silently
    // signing a victim into an attacker-controlled account). If the token is
    // missing or wrong, we never even reach the credential check.
    $submitted = $_POST['csrf_token'] ?? null;

    if (!verifyCsrfToken(is_string($submitted) ? $submitted : null)) {
        $error = 'Your session has expired. Please reload and try again.';
    } else {
        $email    = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if (attemptLogin($pdo, $email, $password)) {
            // Re-read the (now authenticated) user to route by role.
            $user = getCurrentUser($pdo);
            header('Location: ' . ($user !== null && $user['role'] === 'admin'
                ? 'admin/dashboard.php'
                : 'student/dashboard.php'));
            exit; // always exit after a redirect so no page body is emitted
        }

        // Deliberately generic: we don't say whether the email or the
        // password was wrong, which would let someone probe for valid emails.
        $error = 'Invalid email or password.';
    }
}

// Token to embed in the form for this render.
$csrfToken = generateCsrfToken();

// Safely reflect the previously typed email back into the field on error.
$emailValue = htmlspecialchars((string) ($_POST['email'] ?? ''), ENT_QUOTES, 'UTF-8');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in — School Admission System</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f4f5f7; margin: 0; }
        main { max-width: 22rem; margin: 4rem auto; background: #fff; padding: 2rem;
               border-radius: 8px; box-shadow: 0 1px 4px rgba(0,0,0,.08); }
        h1 { font-size: 1.25rem; margin: 0 0 1.25rem; }
        label { display: block; font-size: .85rem; margin-bottom: 1rem; color: #333; }
        input { width: 100%; box-sizing: border-box; padding: .55rem .6rem; margin-top: .3rem;
                border: 1px solid #ccc; border-radius: 5px; font-size: 1rem; }
        button { width: 100%; padding: .6rem; border: 0; border-radius: 5px;
                 background: #2563eb; color: #fff; font-size: 1rem; cursor: pointer; }
        button:hover { background: #1d4ed8; }
        .error { background: #fdecec; color: #a12020; border: 1px solid #f3b7b7;
                 padding: .6rem .75rem; border-radius: 5px; font-size: .85rem; margin-bottom: 1rem; }
    </style>
</head>
<body>
    <main>
        <h1>Sign in</h1>

        <?php if ($error !== ''): ?>
            <p class="error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>

        <form method="post" autocomplete="on">
            <!-- CSRF token: verified server-side on submit (see top of file). -->
            <input type="hidden" name="csrf_token"
                   value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

            <label>Email
                <input type="email" name="email" required autofocus value="<?= $emailValue ?>">
            </label>

            <label>Password
                <input type="password" name="password" required>
            </label>

            <button type="submit">Sign in</button>
        </form>
    </main>
</body>
</html>
