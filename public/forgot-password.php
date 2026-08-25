<?php

declare(strict_types=1);

/*
 * PUBLIC "forgot password" page — no login. Untrusted input; CSRF-protected.
 * Never reveals whether an email is registered: a valid submission always shows
 * the same generic confirmation, whether or not a matching user exists.
 */

require_once __DIR__ . '/../config/database.php';            // $pdo
require_once __DIR__ . '/../includes/auth.php';              // session + CSRF helpers
require_once __DIR__ . '/../includes/mailer.php';            // sendEmail()
require_once __DIR__ . '/../templates/password_reset.php';   // renderPasswordResetEmail()

startSecureSession();

if (!function_exists('e')) {
    function e(?string $v): string { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
}

$errors = [];
$values = ['email' => ''];
$sent   = false; // set once a valid submission has been processed

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF first.
    $token = $_POST['csrf_token'] ?? null;
    if (!verifyCsrfToken(is_string($token) ? $token : null)) {
        $errors['csrf'] = 'Your session has expired. Please reload the page and try again.';
    }

    $values['email'] = trim((string) ($_POST['email'] ?? ''));

    // Input validation (empty/format) is fine to report — it doesn't reveal
    // whether the address is registered.
    if ($values['email'] === '') {
        $errors['email'] = 'Email is required.';
    } elseif (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    }

    if ($errors === []) {
        // Look up the account (with a display name for the email greeting).
        $stmt = $pdo->prepare(
            'SELECT u.id, u.email, u.username, u.applicant_id,
                    a.full_name,
                    a.email          AS applicant_email,
                    a.guardian_email AS guardian_email
               FROM users u
               LEFT JOIN applicants a ON a.id = u.applicant_id
              WHERE u.email = :email
              LIMIT 1'
        );
        $stmt->execute([':email' => $values['email']]);
        $user = $stmt->fetch();

        if ($user !== false) {
            /*
             * Send to the REAL contact address, not u.email — for students
             * u.email is the synthetic @students.yourschool.local login
             * identifier, not a mailbox. Prefer the applicant's own email, then
             * the guardian's. Only for accounts with NO linked applicant
             * (applicant_id IS NULL, e.g. admins) do we fall back to u.email,
             * since there it's a real address the admin chose.
             */
            $applicantEmail = trim((string) ($user['applicant_email'] ?? ''));
            $guardianEmail  = trim((string) ($user['guardian_email'] ?? ''));

            if ($applicantEmail !== '') {
                $notifyEmail = $applicantEmail;
            } elseif ($guardianEmail !== '') {
                $notifyEmail = $guardianEmail;
            } elseif ($user['applicant_id'] === null) {
                $notifyEmail = (string) $user['email']; // admin account: u.email is real
            } else {
                $notifyEmail = ''; // linked applicant but no contact email on file
            }

            // Only ever one valid token per user: clear any earlier reset rows.
            $del = $pdo->prepare('DELETE FROM password_resets WHERE user_id = :uid');
            $del->execute([':uid' => (int) $user['id']]);

            /*
             * Generate a 256-bit random token and store only its SHA-256 hash.
             *
             * Why hash('sha256', ...) here rather than password_hash(): a reset
             * token is 32 random bytes, so it already has far more entropy than
             * any guessable password — a fast hash is perfectly safe against
             * brute force. More importantly, SHA-256 is deterministic, so we can
             * look the row up directly with WHERE token_hash = ? on the reset
             * page. bcrypt/password_hash() salts every hash, which would force us
             * to load candidate rows and verify() each one. Hashing (rather than
             * storing the raw token) still means a DB leak exposes no usable
             * tokens.
             */
            $plainToken = bin2hex(random_bytes(32));
            $tokenHash  = hash('sha256', $plainToken);
            $expiresAt  = (new DateTimeImmutable('+1 hour'))->format('Y-m-d H:i:s');

            $ins = $pdo->prepare(
                'INSERT INTO password_resets (user_id, token_hash, expires_at)
                 VALUES (:uid, :hash, :expires)'
            );
            $ins->execute([
                ':uid'     => (int) $user['id'],
                ':hash'    => $tokenHash,
                ':expires' => $expiresAt,
            ]);

            // Build an absolute reset link that works whether the app is served
            // from the domain root or a subfolder (derived from this request's
            // own path). HTTP_HOST is client-supplied; a configured APP_URL in
            // .env would be more robust for production.
            $scheme = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
                || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443) ? 'https' : 'http';
            $host    = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
            $baseDir = rtrim(str_replace('\\', '/', dirname((string) $_SERVER['SCRIPT_NAME'])), '/');
            $resetUrl = $scheme . '://' . $host . $baseDir . '/reset-password.php?token=' . urlencode($plainToken);

            $displayName = trim((string) ($user['full_name'] ?? '')) !== ''
                ? (string) $user['full_name']
                : (string) $user['username'];

            if ($notifyEmail !== '') {
                $htmlBody = renderPasswordResetEmail($displayName, $resetUrl);
                sendEmail(
                    $pdo,
                    $notifyEmail,
                    $displayName,
                    'Reset your password — Bright House College',
                    $htmlBody,
                    null,
                    'password_reset'
                );
            }
        }

        // Same message every time — presence/absence of the account is never leaked.
        $sent = true;
    }
}

$csrfToken = generateCsrfToken();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Forgot Password — Bright House College</title>
    <link rel="stylesheet" href="/niitproject/public/assets/style.css">
</head>
<body class="auth">
<?php include __DIR__ . '/../includes/public_header.php'; ?>
    <main>
        <h1>Forgot password</h1>

        <?php if ($sent): ?>

            <p class="flash">If an account exists with that email, a password reset link has been sent.</p>
            <p style="margin-top:1rem;"><a href="login.php">Back to sign in</a></p>

        <?php else: ?>

            <?php if (isset($errors['csrf'])): ?>
                <div class="errors"><?= e($errors['csrf']) ?></div>
            <?php endif; ?>

            <p>Enter the email address for your account and we'll send you a link to reset your password.</p>

            <form method="post" action="forgot-password.php" novalidate>
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                <label>Email <span class="req">*</span>
                    <input type="email" name="email" value="<?= e($values['email']) ?>" autofocus
                           class="<?= isset($errors['email']) ? 'invalid' : '' ?>">
                </label>
                <?php if (isset($errors['email'])): ?><p class="field-error"><?= e($errors['email']) ?></p><?php endif; ?>

                <div style="margin-top:.5rem; display:flex; gap:1rem; align-items:center;">
                    <button type="submit" class="btn">Send reset link</button>
                    <a href="login.php">Back to sign in</a>
                </div>
            </form>

        <?php endif; ?>
    </main>
</body>
</html>
