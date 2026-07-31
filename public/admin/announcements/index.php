<?php

declare(strict_types=1);

/*
 * Announcements — list view.
 *
 * PATH NOTE: /public/admin/announcements/ is one level deeper than
 * /public/admin/, so the bootstrap is THREE directories up (../../../) —
 * same as teachers/ and subjects/.
 */
require_once __DIR__ . '/../../../includes/admin_bootstrap.php';

// One-shot flash message set by create / edit / delete.
$flash = null;
if (!empty($_SESSION['flash'])) {
    $flash = (string) $_SESSION['flash'];
    unset($_SESSION['flash']);
}

// Newest first.
$announcements = $pdo->query(
    'SELECT id, title, body, created_at
       FROM announcements
      ORDER BY created_at DESC, id DESC'
)->fetchAll();

$csrfToken = generateCsrfToken();

if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

// Shorten long bodies for the table (multibyte-safe).
function truncate(?string $text, int $limit = 90): string
{
    $text = trim((string) $text);
    if ($text === '') {
        return '';
    }
    return mb_strlen($text) <= $limit ? $text : mb_substr($text, 0, $limit - 1) . '…';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Announcements — Admin</title>
    <link rel="stylesheet" href="/niitproject/public/assets/style.css">
    <!-- <style>
        body { font-family: system-ui, sans-serif; margin: 2rem; color: #222; background: #fafafa; }
        main { max-width: 820px; margin: 0 auto; }
        h1 { font-size: 1.4rem; margin: 0; }
        .header-row { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; }
        a { color: #2563eb; text-decoration: none; }
        a:hover { text-decoration: underline; }
        table { width: 100%; border-collapse: collapse; background: #fff; }
        th, td { text-align: left; padding: .6rem .7rem; border-bottom: 1px solid #e5e5e5; vertical-align: top; }
        th { background: #f0f2f5; font-size: .78rem; text-transform: uppercase; letter-spacing: .03em; color: #555; }
        .actions { white-space: nowrap; }
        .actions a { margin-right: .6rem; }
        .nowrap { white-space: nowrap; }
        .btn { display: inline-block; background: #2563eb; color: #fff; padding: .5rem .9rem; border-radius: 5px; }
        .btn:hover { text-decoration: none; background: #1d4ed8; }
        form.inline { display: inline; margin: 0; }
        button.link-danger { background: none; border: 0; color: #b91c1c; cursor: pointer; padding: 0; font: inherit; text-decoration: underline; }
        button.link-danger:hover { color: #7f1414; }
        .flash { background: #e7f6ec; border: 1px solid #b7e0c4; color: #1c7a3f; padding: .6rem .8rem; border-radius: 5px; margin-bottom: 1rem; }
        .muted { color: #888; }
    </style> -->
</head>
<body>
    <?php include __DIR__ . '/../../../includes/admin_header.php'; ?>
    <main>
        <div class="header-row">
            <h1>Announcements</h1>
            <a class="btn" href="create.php">Post Announcement</a>
        </div>

        <?php if ($flash !== null): ?>
            <p class="flash"><?= e($flash) ?></p>
        <?php endif; ?>

        <?php if (count($announcements) === 0): ?>
            <p class="muted">No announcements yet. <a href="create.php">Post the first one</a>.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Posted</th>
                        <th>Body</th>
                        <th class="actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($announcements as $a): ?>
                    <tr>
                        <td><?= e($a['title']) ?></td>
                        <td class="nowrap">
                            <?php $ts = strtotime((string) $a['created_at']); ?>
                            <?= $ts !== false ? e(date('M j, Y', $ts)) : '<span class="muted">—</span>' ?>
                        </td>
                        <td>
                            <?php $preview = truncate($a['body']); ?>
                            <?php if ($preview === ''): ?>
                                <span class="muted">—</span>
                            <?php else: ?>
                                <?= e($preview) ?>
                            <?php endif; ?>
                        </td>
                        <td class="actions">
                            <a href="edit.php?id=<?= (int) $a['id'] ?>">Edit</a>

                            <?php /* Delete is POST + CSRF, never a GET link — see teachers/index.php for the full reasoning. */ ?>
                            <form class="inline" method="post" action="delete.php"
                                  onsubmit="return confirm('Delete this announcement?');">
                                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                                <button type="submit" class="link-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </main>
</body>
</html>
