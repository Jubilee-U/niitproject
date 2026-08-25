<?php

declare(strict_types=1);

/*
 * PUBLIC "reset password" page — no login. Reached from the emailed link with
 * ?token=. Untrusted input; CSRF-protected. The token is validated by hashing
 * the submitted value and matching it against a non-expired stored hash.
 */

require_once __DIR__ . '/../config/database.php'; // $pdo
require_once __DIR__ . '/../includes/auth.php';   // session + CSRF helpers

startSecureSession();

if (!function_exists('e')) {
    function e(?string $v): string { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
}

// The token arrives in the URL on first load and in a hidden field on submit.
$token = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? (string) ($_POST['token'] ?? '')
    : (string) ($_GET['token'] ?? '');

// Find a matching, unexpired reset row by comparing hashes (never the raw token).
$reset = false;
if ($token !== '') {
    $stmt = $pdo->prepare(
        'SELECT id, user_id
           FROM password_resets
          WHERE token_hash = :hash AND expires_at > NOW()
          LIMIT 1'
    );
    $stmt->execute([':hash' => hash('sha256', $token)]);
    $reset = $stmt->fetch();
}
$valid = ($reset !== false);

$errors = [];

if ($valid && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF first.
    $csrf = $_POST['csrf_token'] ?? null;
    if (!verifyCsrfToken(is_string($csrf) ? $csrf : null)) {
        $errors['csrf'] = 'Your session has expired. Please try again.';
    }

    $new     = (string) ($_POST['new_password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');

    if ($new === '') {
        $errors['new_password'] = 'Please enter a new password.';
    } elseif (strlen($new) < 8) {
        $errors['new_password'] = 'New password must be at least 8 characters.';
    }

    if ($confirm === '') {
        $errors['confirm_password'] = 'Please confirm your new password.';
    } elseif ($new !== '' && $confirm !== $new) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }

    if ($errors === []) {
        $newHash = password_hash($new, PASSWORD_DEFAULT);
        try {
            // Update the password and consume the token atomically.
            $pdo->beginTransaction();

            $upd = $pdo->prepare(
                'UPDATE users SET password_hash = :hash, must_change_password = 0 WHERE id = :uid'
            );
            $upd->execute([':hash' => $newHash, ':uid' => (int) $reset['user_id']]);

            // Single-use: remove the reset row so the link can't be reused.
            $delr = $pdo->prepare('DELETE FROM password_resets WHERE id = :id');
            $delr->execute([':id' => (int) $reset['id']]);

            $pdo->commit();

            $_SESSION['flash'] = 'Password reset successful. Please log in.';
            // Relative redirect so it works from a subfolder or the domain root.
            header('Location: login.php');
            exit;
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('reset-password.php failed: ' . $ex->getMessage());
            $errors['general'] = 'Something went wrong while resetting your password. Please try again.';
        }
    }
}

$csrfToken = generateCsrfToken();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset Password — Bright House College</title>
        <link rel="stylesheet" href="/niitproject/public/assets/style.css">

</head>
<body class="auth">
<?php include __DIR__ . '/../includes/public_header.php'; ?>
    <main>
        <h1>Reset password</h1>

        <?php if (!$valid): ?>

            <div class="errors">
                This reset link is invalid or has expired. Please request a new one.
            </div>
            <p style="margin-top:1rem;"><a href="forgot-password.php">Request a new reset link</a></p>

        <?php else: ?>

            <?php if (isset($errors['csrf'])): ?>
                <div class="errors"><?= e($errors['csrf']) ?></div>
            <?php endif; ?>
            <?php if (isset($errors['general'])): ?>
                <div class="errors"><?= e($errors['general']) ?></div>
            <?php endif; ?>

            <p>Choose a new password for your account.</p>

            <form method="post" action="reset-password.php" novalidate>
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="token" value="<?= e($token) ?>">

                <label>New password <span class="muted">(min 8 characters)</span>
                    <input type="password" name="new_password" autofocus
                           class="<?= isset($errors['new_password']) ? 'invalid' : '' ?>">
                </label>
                <?php if (isset($errors['new_password'])): ?><p class="field-error"><?= e($errors['new_password']) ?></p><?php endif; ?>

                <label>Confirm new password
                    <input type="password" name="confirm_password"
                           class="<?= isset($errors['confirm_password']) ? 'invalid' : '' ?>">
                </label>
                <?php if (isset($errors['confirm_password'])): ?><p class="field-error"><?= e($errors['confirm_password']) ?></p><?php endif; ?>

                <div style="margin-top:.5rem;">
                    <button type="submit" class="btn">Reset password</button>
                </div>
            </form>

        <?php endif; ?>
    </main>
</body>
</html>
