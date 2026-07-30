<?php

declare(strict_types=1);

// PATH NOTE: three levels up to reach /includes (see index.php).
require_once __DIR__ . '/../../../includes/admin_bootstrap.php';

// Resolve the announcement id from GET (initial load) or POST (submit).
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($id === false || $id === null) {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
}
if ($id === false || $id === null) {
    $_SESSION['flash'] = 'Invalid announcement id.';
    header('Location: index.php');
    exit;
}

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
        // Update title and body only. created_by is deliberately NOT in this
        // statement, so the original poster is preserved on every edit.
        $stmt = $pdo->prepare(
            'UPDATE announcements
                SET title = :title, body = :body
              WHERE id = :id'
        );
        $stmt->execute([
            ':title' => $values['title'],
            ':body'  => $values['body'],
            ':id'    => $id,
        ]);

        $_SESSION['flash'] = 'Announcement updated.';
        header('Location: index.php');
        exit;
    }
    // Validation failed: fall through and re-render with submitted values.
} else {
    // GET: load the current row to pre-fill the form.
    $stmt = $pdo->prepare('SELECT title, body FROM announcements WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();

    if ($row === false) {
        $_SESSION['flash'] = 'Announcement not found.';
        header('Location: index.php');
        exit;
    }

    $values = [
        'title' => (string) $row['title'],
        'body'  => (string) $row['body'],
    ];
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
    <title>Edit Announcement — Admin</title>
    <style>
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
    </style>
</head>
<body>
    <main>
        <h1>Edit Announcement</h1>

        <?php if (isset($errors['csrf'])): ?>
            <div class="errors"><?= e($errors['csrf']) ?></div>
        <?php endif; ?>

        <!-- Posts back to this same page, carrying the id in a hidden field. -->
        <form method="post" action="edit.php?id=<?= (int) $id ?>">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="id" value="<?= (int) $id ?>">

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
                <button type="submit" class="btn">Update</button>
                <a href="index.php">Cancel</a>
            </div>
        </form>
    </main>
</body>
</html>
