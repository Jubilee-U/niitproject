<?php

declare(strict_types=1);

// PATH NOTE: three levels up to reach /includes (see index.php).
require_once __DIR__ . '/../../../includes/admin_bootstrap.php';

// Resolve the subject id from GET (initial load) or POST (submit).
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($id === false || $id === null) {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
}
if ($id === false || $id === null) {
    $_SESSION['flash'] = 'Invalid subject id.';
    header('Location: index.php');
    exit;
}

// Teachers for the dropdown + the valid-id set used to validate the choice.
$teachers = $pdo->query('SELECT id, full_name FROM teachers ORDER BY full_name ASC')->fetchAll();
$validTeacherIds = array_map(static fn(array $t): int => (int) $t['id'], $teachers);

$errors = [];
$values = ['name' => '', 'description' => '', 'teacher_id' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF first.
    $token = $_POST['csrf_token'] ?? null;
    if (!verifyCsrfToken(is_string($token) ? $token : null)) {
        $errors['csrf'] = 'Your session has expired. Please try again.';
    }

    $values['name']        = trim((string) ($_POST['name'] ?? ''));
    $values['description'] = trim((string) ($_POST['description'] ?? ''));
    $values['teacher_id']  = trim((string) ($_POST['teacher_id'] ?? ''));

    // name: required.
    if ($values['name'] === '') {
        $errors['name'] = 'Subject name is required.';
    }

    // teacher_id: optional; empty = Unassigned (NULL). If set, must be a real
    // teacher (validated against the loaded ids, not trusted from the client).
    $teacherId = null;
    if ($values['teacher_id'] !== '') {
        if (ctype_digit($values['teacher_id']) && in_array((int) $values['teacher_id'], $validTeacherIds, true)) {
            $teacherId = (int) $values['teacher_id'];
        } else {
            $errors['teacher_id'] = 'Please choose a teacher from the list, or leave it unassigned.';
        }
    }

    if ($errors === []) {
        $stmt = $pdo->prepare(
            'UPDATE subjects
                SET name = :name, description = :description, teacher_id = :teacher_id
              WHERE id = :id'
        );
        $stmt->execute([
            ':name'        => $values['name'],
            ':description' => $values['description'] !== '' ? $values['description'] : null,
            ':teacher_id'  => $teacherId,
            ':id'          => $id,
        ]);

        $_SESSION['flash'] = 'Subject updated.';
        header('Location: index.php');
        exit;
    }
    // Validation failed: fall through and re-render with submitted values.
} else {
    // GET: load the current row to pre-fill the form.
    $stmt = $pdo->prepare('SELECT name, description, teacher_id FROM subjects WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();

    if ($row === false) {
        $_SESSION['flash'] = 'Subject not found.';
        header('Location: index.php');
        exit;
    }

    $values = [
        'name'        => (string) $row['name'],
        'description' => (string) ($row['description'] ?? ''),
        // Cast the stored teacher_id to a string (or '' for NULL) so it matches
        // the option values when we pre-select the dropdown below.
        'teacher_id'  => $row['teacher_id'] !== null ? (string) $row['teacher_id'] : '',
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
    <title>Edit Subject — Admin</title>
    <link rel="stylesheet" href="/niitproject/public/assets/style.css">
    <!-- <style>
        body { font-family: system-ui, sans-serif; margin: 2rem; color: #222; background: #fafafa; }
        main { max-width: 520px; margin: 0 auto; }
        h1 { font-size: 1.3rem; }
        a { color: #2563eb; text-decoration: none; }
        a:hover { text-decoration: underline; }
        label { display: block; margin-bottom: 1rem; font-size: .9rem; color: #333; }
        .req { color: #b91c1c; }
        input[type=text], textarea, select { width: 100%; box-sizing: border-box; padding: .5rem .6rem;
            margin-top: .25rem; border: 1px solid #ccc; border-radius: 5px; font-size: 1rem; }
        textarea { min-height: 6rem; resize: vertical; font-family: inherit; }
        input.invalid, textarea.invalid, select.invalid { border-color: #dc2626; box-shadow: 0 0 0 2px rgba(220,38,38,.12); }
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
        <h1>Edit Subject</h1>

        <?php if (isset($errors['csrf'])): ?>
            <div class="errors"><?= e($errors['csrf']) ?></div>
        <?php endif; ?>

        <!-- Posts back to this same page, carrying the id in a hidden field. -->
        <form method="post" action="edit.php?id=<?= (int) $id ?>">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="id" value="<?= (int) $id ?>">

            <label>Name <span class="req">*</span>
                <input type="text" name="name" required
                       value="<?= e($values['name']) ?>"
                       class="<?= isset($errors['name']) ? 'invalid' : '' ?>" autofocus>
            </label>
            <?php if (isset($errors['name'])): ?>
                <p class="field-error"><?= e($errors['name']) ?></p>
            <?php endif; ?>

            <label>Description
                <textarea name="description"><?= e($values['description']) ?></textarea>
            </label>

            <label>Teacher
                <select name="teacher_id" class="<?= isset($errors['teacher_id']) ? 'invalid' : '' ?>">
                    <option value="" <?= $values['teacher_id'] === '' ? 'selected' : '' ?>>— Unassigned —</option>
                    <?php foreach ($teachers as $t): ?>
                        <option value="<?= (int) $t['id'] ?>"
                            <?= $values['teacher_id'] === (string) $t['id'] ? 'selected' : '' ?>>
                            <?= e($t['full_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <?php if (isset($errors['teacher_id'])): ?>
                <p class="field-error"><?= e($errors['teacher_id']) ?></p>
            <?php endif; ?>

            <div class="form-actions">
                <button type="submit" class="btn">Update</button>
                <a href="index.php">Cancel</a>
            </div>
        </form>
    </main>
</body>
</html>
