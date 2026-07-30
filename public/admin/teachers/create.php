<?php

declare(strict_types=1);

// PATH NOTE: three levels up to reach /includes (see index.php).
require_once __DIR__ . '/../../../includes/admin_bootstrap.php';

/*
 * Errors are keyed by field name ('full_name', 'email', 'phone') so each
 * message can be shown right next to the input it belongs to. 'csrf' is a
 * general error rendered as a banner at the top.
 */
$errors = [];
// Holds submitted values so the form can be repopulated after a failed submit.
$values = ['full_name' => '', 'email' => '', 'phone' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check before doing anything with the input.
    $token = $_POST['csrf_token'] ?? null;
    if (!verifyCsrfToken(is_string($token) ? $token : null)) {
        $errors['csrf'] = 'Your session has expired. Please try again.';
    }

    $values['full_name'] = trim((string) ($_POST['full_name'] ?? ''));
    $values['email']     = trim((string) ($_POST['email'] ?? ''));
    $values['phone']     = trim((string) ($_POST['phone'] ?? ''));

    // full_name: required.
    if ($values['full_name'] === '') {
        $errors['full_name'] = 'Full name is required.';
    }

    // email: optional, but must be a valid address if provided.
    if ($values['email'] !== '' && !filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address, or leave it blank.';
    }

    // phone: optional. If provided it must (a) contain ONLY digits, spaces, and
    // the characters + - ( ) — this whitelist rejects letters while allowing
    // formats like "+234 902 671 5579" — and (b) include at least 7 actual
    // digits, so values like "()" or "12" that pass the whitelist are still
    // rejected as too short to be a real phone number.
    if ($values['phone'] !== '') {
        if (!preg_match('/^[0-9\s()+-]+$/', $values['phone'])) {
            $errors['phone'] = 'Phone may contain only digits, spaces, and + - ( ) — no letters.';
        } elseif (preg_match_all('/\d/', $values['phone']) < 7) {
            // preg_match_all counts the digit characters, which is the same as
            // stripping all formatting and measuring what's left.
            $errors['phone'] = 'Phone must include at least 7 digits.';
        }
    }

    // Only insert once every rule passes.
    if ($errors === []) {
        $stmt = $pdo->prepare(
            'INSERT INTO teachers (full_name, email, phone, created_at)
             VALUES (:full_name, :email, :phone, NOW())'
        );
        $stmt->execute([
            ':full_name' => $values['full_name'],
            ':email'     => $values['email'] !== '' ? $values['email'] : null,
            ':phone'     => $values['phone'] !== '' ? $values['phone'] : null,
        ]);

        $_SESSION['flash'] = 'Teacher added.';
        header('Location: index.php');
        exit;
    }
    // Fall through on error: re-render the form with the submitted values.
}

$csrfToken = generateCsrfToken();

if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Add Teacher — Admin</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 2rem; color: #222; background: #fafafa; }
        main { max-width: 520px; margin: 0 auto; }
        h1 { font-size: 1.3rem; }
        a { color: #2563eb; text-decoration: none; }
        a:hover { text-decoration: underline; }
        label { display: block; margin-bottom: 1rem; font-size: .9rem; color: #333; }
        .req { color: #b91c1c; }
        input[type=text], input[type=email] { width: 100%; box-sizing: border-box; padding: .5rem .6rem;
            margin-top: .25rem; border: 1px solid #ccc; border-radius: 5px; font-size: 1rem; }
        input.invalid { border-color: #dc2626; box-shadow: 0 0 0 2px rgba(220,38,38,.12); }
        .field-error { color: #b91c1c; font-size: .8rem; margin: -0.6rem 0 1rem; }
        .btn { background: #2563eb; color: #fff; border: 0; padding: .55rem 1rem; border-radius: 5px;
            font-size: 1rem; cursor: pointer; }
        .btn:hover { background: #1d4ed8; }
        .errors { background: #fdecec; border: 1px solid #f3b7b7; color: #a12020; padding: .6rem .8rem;
            border-radius: 5px; margin-bottom: 1rem; }
        .form-actions { margin-top: .5rem; display: flex; gap: 1rem; align-items: center; }
    </style>
</head>
<body>
    <main>
        <h1>Add Teacher</h1>

        <?php if (isset($errors['csrf'])): ?>
            <div class="errors"><?= e($errors['csrf']) ?></div>
        <?php endif; ?>

        <form method="post" action="create.php">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

            <label>Full name <span class="req">*</span>
                <input type="text" name="full_name" required
                       value="<?= e($values['full_name']) ?>"
                       class="<?= isset($errors['full_name']) ? 'invalid' : '' ?>" autofocus>
            </label>
            <?php if (isset($errors['full_name'])): ?>
                <p class="field-error"><?= e($errors['full_name']) ?></p>
            <?php endif; ?>

            <label>Email
                <input type="email" name="email"
                       value="<?= e($values['email']) ?>"
                       class="<?= isset($errors['email']) ? 'invalid' : '' ?>">
            </label>
            <?php if (isset($errors['email'])): ?>
                <p class="field-error"><?= e($errors['email']) ?></p>
            <?php endif; ?>

            <label>Phone
                <input type="text" name="phone"
                       value="<?= e($values['phone']) ?>"
                       class="<?= isset($errors['phone']) ? 'invalid' : '' ?>">
            </label>
            <?php if (isset($errors['phone'])): ?>
                <p class="field-error"><?= e($errors['phone']) ?></p>
            <?php endif; ?>

            <div class="form-actions">
                <button type="submit" class="btn">Save</button>
                <a href="index.php">Cancel</a>
            </div>
        </form>
    </main>
</body>
</html>
