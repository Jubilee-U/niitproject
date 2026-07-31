<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/student_bootstrap.php'; // $pdo, $currentUser

/*
 * This is the ONE student page that must not redirect on must_change_password —
 * it's the destination of that redirect. We do read the flag: when it's set we
 * show a "you must change your password" notice and no escape link; when it's
 * not, the student is here voluntarily and gets a normal back link.
 */
$mustChange = (int) $currentUser['must_change_password'] === 1;

if (!function_exists('e')) {
    function e(?string $v): string { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF first.
    $token = $_POST['csrf_token'] ?? null;
    if (!verifyCsrfToken(is_string($token) ? $token : null)) {
        $errors['csrf'] = 'Your session has expired. Please try again.';
    }

    $current = (string) ($_POST['current_password'] ?? '');
    $new     = (string) ($_POST['new_password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');

    // Fetch the stored hash (getCurrentUser doesn't expose password_hash).
    $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => (int) $currentUser['id']]);
    $row = $stmt->fetch();
    $currentHash = $row ? (string) $row['password_hash'] : '';

    // current_password: required + must verify.
    if ($current === '') {
        $errors['current_password'] = 'Please enter your current password.';
    } elseif ($currentHash === '' || !password_verify($current, $currentHash)) {
        $errors['current_password'] = 'Current password is incorrect.';
    }

    // new_password: required + minimum length.
    if ($new === '') {
        $errors['new_password'] = 'Please enter a new password.';
    } elseif (strlen($new) < 8) {
        $errors['new_password'] = 'New password must be at least 8 characters.';
    } elseif ($current !== '' && $new === $current) {
        $errors['new_password'] = 'New password must be different from your current password.';
    }

    // confirm_password: must match.
    if ($confirm === '') {
        $errors['confirm_password'] = 'Please confirm your new password.';
    } elseif ($new !== '' && $confirm !== $new) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }

    if ($errors === []) {
        $newHash = password_hash($new, PASSWORD_DEFAULT);
        $upd = $pdo->prepare(
            'UPDATE users SET password_hash = :h, must_change_password = 0 WHERE id = :id'
        );
        $upd->execute([':h' => $newHash, ':id' => (int) $currentUser['id']]);

        $_SESSION['flash'] = 'Password changed successfully.';
        header('Location: dashboard.php');
        exit;
    }
}

$csrfToken = generateCsrfToken();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Change Password — Student</title>
    <link rel="stylesheet" href="/niitproject/public/assets/style.css">
    <!-- <style>
        body { font-family: system-ui, sans-serif; margin: 0; color: #222; background: #f4f5f7; }
        main { max-width: 460px; margin: 2.5rem auto; padding: 0 1rem; }
        h1 { font-size: 1.3rem; }
        a { color: #2563eb; text-decoration: none; }
        a:hover { text-decoration: underline; }
        .card { background: #fff; border: 1px solid #e5e5e5; border-radius: 8px; padding: 1.5rem; }
        label { display: block; margin-bottom: 1rem; font-size: .9rem; color: #333; }
        input[type=password] { width: 100%; box-sizing: border-box; padding: .5rem .6rem; margin-top: .25rem;
            border: 1px solid #ccc; border-radius: 5px; font-size: 1rem; }
        input.invalid { border-color: #dc2626; box-shadow: 0 0 0 2px rgba(220,38,38,.12); }
        .field-error { color: #b91c1c; font-size: .8rem; margin: -0.6rem 0 1rem; }
        .btn { background: #2563eb; color: #fff; border: 0; padding: .55rem 1rem; border-radius: 5px; font-size: 1rem; cursor: pointer; }
        .btn:hover { background: #1d4ed8; }
        .errors { background: #fdecec; border: 1px solid #f3b7b7; color: #a12020; padding: .6rem .8rem; border-radius: 5px; margin-bottom: 1rem; }
        .notice { background: #fff7ed; border: 1px solid #fed7aa; color: #9a3412; padding: .7rem .9rem; border-radius: 6px; margin-bottom: 1rem; font-size: .9rem; }
    </style> -->
</head>
<body>
    <?php $navActive = 'dashboard'; include __DIR__ . '/../../includes/student_header.php'; ?>
    <main>
        <h1>Change Password</h1>

        <?php if ($mustChange): ?>
            <div class="notice">
                For your security, you must change your temporary password before continuing.
            </div>
        <?php endif; ?>

        <?php if (isset($errors['csrf'])): ?>
            <div class="errors"><?= e($errors['csrf']) ?></div>
        <?php endif; ?>

        <div class="card">
            <form method="post" action="change-password.php" novalidate>
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                <label>Current password
                    <input type="password" name="current_password"
                           class="<?= isset($errors['current_password']) ? 'invalid' : '' ?>" autofocus>
                </label>
                <?php if (isset($errors['current_password'])): ?><p class="field-error"><?= e($errors['current_password']) ?></p><?php endif; ?>

                <label>New password <span class="muted">(min 8 characters)</span>
                    <input type="password" name="new_password"
                           class="<?= isset($errors['new_password']) ? 'invalid' : '' ?>">
                </label>
                <?php if (isset($errors['new_password'])): ?><p class="field-error"><?= e($errors['new_password']) ?></p><?php endif; ?>

                <label>Confirm new password
                    <input type="password" name="confirm_password"
                           class="<?= isset($errors['confirm_password']) ? 'invalid' : '' ?>">
                </label>
                <?php if (isset($errors['confirm_password'])): ?><p class="field-error"><?= e($errors['confirm_password']) ?></p><?php endif; ?>

                <div style="margin-top:.5rem; display:flex; gap:1rem; align-items:center;">
                    <button type="submit" class="btn">Update password</button>
                    <?php if (!$mustChange): ?>
                        <a href="dashboard.php">Back to dashboard</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </main>
</body>
</html>
