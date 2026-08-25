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
        ? '/niitproject/public/admin/dashboard.php'
        : '/niitproject/public/student/dashboard.php'   ));
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
                ? '/niitproject/public/admin/dashboard.php'
                : '/niitproject/public/student/dashboard.php'));
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
    <link rel="stylesheet" href="/niitproject/public/assets/style.css">
</head>
<body class="auth">
<?php include __DIR__ . '/../includes/public_header.php'; ?>
    <main>
        <div class="auth-icon">🏫</div>
        <h1>Sign in</h1>

        <?php if ($error !== ''): ?>
            <p class="error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>

        <form method="post" autocomplete="on">
            <!-- CSRF token: verified server-side on submit (see top of file). -->
            <input type="hidden" name="csrf_token"
                   value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

            <label>Email
                <span class="input-icon-wrap">
                    <span class="input-icon">✉</span>
                    <input type="email" name="email" required autofocus value="<?= $emailValue ?>">
                </span>
            </label>

            <label>Password
                <span class="input-icon-wrap has-toggle">
                    <span class="input-icon">🔒</span>
                    <input type="password" name="password" id="password" required>
                    <button type="button" class="pw-toggle" id="pwToggle" aria-label="Show password">👁</button>
                </span>
            </label>

            <button type="submit" class="btn btn-block">Sign in</button>
        </form>
    </main>
    <script>
        // Show/hide password: display-only, toggles the input type. type="button"
        // means this never submits the form or touches login/CSRF logic.
        (function () {
            var toggle = document.getElementById('pwToggle');
            var pw = document.getElementById('password');
            if (toggle && pw) {
                toggle.addEventListener('click', function () {
                    var reveal = pw.type === 'password';
                    pw.type = reveal ? 'text' : 'password';
                    toggle.setAttribute('aria-label', reveal ? 'Hide password' : 'Show password');
                });
            }
        })();
    </script>
</body>
</html>
