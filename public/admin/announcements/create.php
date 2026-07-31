<?php

declare(strict_types=1);

// PATH NOTE: three levels up to reach /includes (see index.php).
require_once __DIR__ . '/../../../includes/admin_bootstrap.php';

$errors = [];
$values = ['title' => '', 'body' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF first.
    $token = $_POST['csrf_token'] ?? null;
    if (!verifyCsrfToken(is_string($token) ? $token : null)) {
        $errors['csrf'] = 'Your session has expired. Please try again.';
    }

    $values['title'] = trim((string) ($_POST['title'] ?? ''));
    $values['body']  = trim((string) ($_POST['body'] ?? ''));

    // Both fields are required.
    if ($values['title'] === '') {
        $errors['title'] = 'Title is required.';
    }
    if ($values['body'] === '') {
        $errors['body'] = 'Body is required.';
    }

    if ($errors === []) {
        // created_by is stamped from the logged-in admin ($currentUser is
        // provided by admin_bootstrap.php). The column is nullable in the
        // schema, but we always have a user here since the page is admin-only.
        $stmt = $pdo->prepare(
            'INSERT INTO announcements (title, body, created_by, created_at)
             VALUES (:title, :body, :created_by, NOW())'
        );
        $stmt->execute([
            ':title'      => $values['title'],
            ':body'       => $values['body'],
            ':created_by' => (int) $currentUser['id'],
        ]);

        $_SESSION['flash'] = 'Announcement posted.';
        header('Location: index.php');
        exit;
    }
    // Fall through on error: re-render with submitted values.
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
    <title>Post Announcement — Admin</title>
    <link rel="stylesheet" href="/niitproject/public/assets/style.css">
            <!-- <style>
                body { font-family: system-ui, sans-serif; margin: 2rem; color: #222; background: #fafafa; }
                main { max-width: 560px; margin: 0 auto; }
                h1 { font-size: 1.3rem; }
                a { color: #2563eb; text-decoration: none; }
                a:hover { text-decoration: underline; }
                label { display: block; margin-bottom: 1rem; font-size: .9rem; color: #333; }
                .req { color: #b91c1c; }
                input[type=text], textarea { width: 100%; box-sizing: border-box; padding: .5rem .6rem;
                    margin-top: .25rem; border: 1px solid #ccc; border-radius: 5px; font-size: 1rem; }
                textarea { min-height: 8rem; resize: vertical; font-family: inherit; }
                input.invalid, textarea.invalid { border-color: #dc2626; box-shadow: 0 0 0 2px rgba(220,38,38,.12); }
                .field-error { color: #b91c1c; font-size: .8rem; margin: -0.6rem 0 1rem; }
                .btn { background: #2563eb; color: #fff; border: 0; padding: .55rem 1rem; border-radius: 5px;
                    font-size: 1rem; cursor: pointer; }
                .btn:hover { background: #1d4ed8; }
                .errors { background: #fdecec; border: 1px solid #f3b7b7; color: #a12020; padding: .6rem .8rem;
                    border-radius: 5px; margin-bottom: 1rem; }
                .form-actions { margin-top: .5rem; display: flex; gap: 1rem; align-items: center; }
            </style> -->
</head>
<body>
    <?php include __DIR__ . '/../../../includes/admin_header.php'; ?>

    <main>
        <h1>Post Announcement</h1>

        <?php if (isset($errors['csrf'])): ?>
            <div class="errors"><?= e($errors['csrf']) ?></div>
        <?php endif; ?>

        <form method="post" action="create.php">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

            <label>Title <span class="req">*</span>
                <input type="text" name="title" required
                       value="<?= e($values['title']) ?>"
                       class="<?= isset($errors['title']) ? 'invalid' : '' ?>" autofocus>
            </label>
            <?php if (isset($errors['title'])): ?>
                <p class="field-error"><?= e($errors['title']) ?></p>
            <?php endif; ?>

            <label>Body <span class="req">*</span>
                <textarea name="body" required
                          class="<?= isset($errors['body']) ? 'invalid' : '' ?>"><?= e($values['body']) ?></textarea>
            </label>
            <?php if (isset($errors['body'])): ?>
                <p class="field-error"><?= e($errors['body']) ?></p>
            <?php endif; ?>

            <div class="form-actions">
                <button type="submit" class="btn">Post</button>
                <a href="index.php">Cancel</a>
            </div>
        </form>
    </main>
</body>
</html>
