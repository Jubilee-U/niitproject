<?php

declare(strict_types=1);

/*
 * Teachers — list view.
 *
 * PATH NOTE: this file is at /public/admin/teachers/, which is one level
 * deeper than /public/admin/. So the bootstrap is THREE directories up
 * (../../../), not the two you use from /public/admin/*.php.
 */
require_once __DIR__ . '/../../../includes/admin_bootstrap.php';
// $pdo and $currentUser are available; admin-only access already enforced;
// the session is started (so $_SESSION and the CSRF helpers work).

/*
 * Flash message: a one-shot notice stored in the session by whichever page
 * did the work (create / edit / delete), then shown and cleared here on the
 * next request. This is what lets a redirect still display "Teacher added"
 * without passing anything in the URL.
 */
$flash = null;
if (!empty($_SESSION['flash'])) {
    $flash = (string) $_SESSION['flash'];
    unset($_SESSION['flash']);
}

// All teachers, alphabetical.
$teachers = $pdo->query(
    'SELECT id, full_name, email, phone FROM teachers ORDER BY full_name ASC'
)->fetchAll();

// One CSRF token, reused by every delete form on this page.
$csrfToken = generateCsrfToken();

// Short output-escaping helper (guarded so it's safe if this ever gets included
// alongside another file that defines it).
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
    <title>Teachers — Admin</title>
    <link rel="stylesheet" href="/niitproject/public/assets/style.css">
    <!-- <style>
        body { font-family: system-ui, sans-serif; margin: 2rem; color: #222; background: #fafafa; }
        main { max-width: 820px; margin: 0 auto; }
        h1 { font-size: 1.4rem; margin: 0; }
        .header-row { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; }
        a { color: #2563eb; text-decoration: none; }
        a:hover { text-decoration: underline; }
        table { width: 100%; border-collapse: collapse; background: #fff; }
        th, td { text-align: left; padding: .6rem .7rem; border-bottom: 1px solid #e5e5e5; }
        th { background: #f0f2f5; font-size: .78rem; text-transform: uppercase; letter-spacing: .03em; color: #555; }
        .actions { white-space: nowrap; }
        .actions a { margin-right: .6rem; }
        .btn { display: inline-block; background: #2563eb; color: #fff; padding: .5rem .9rem; border-radius: 5px; }
        .btn:hover { text-decoration: none; background: #1d4ed8; }
        form.inline { display: inline; margin: 0; }
        button.link-danger { background: none; border: 0; color: #b91c1c; cursor: pointer; padding: 0; font: inherit; text-decoration: underline; }
        button.link-danger:hover { color: #7f1414; }
        .flash { background: #e7f6ec; border: 1px solid #b7e0c4; color: #1c7a3f; padding: .6rem .8rem; border-radius: 5px; margin-bottom: 1rem; }
        .muted { color: #666; }
    </style> -->
</head>
<body>
    <?php include __DIR__ . '/../../../includes/admin_header.php'; ?>

    <main>
        <div class="header-row">
            <h1>Teachers</h1>
            <a class="btn" href="create.php">Add Teacher</a>
        </div>

        <?php if ($flash !== null): ?>
            <p class="flash"><?= e($flash) ?></p>
        <?php endif; ?>

        <?php if (count($teachers) === 0): ?>
            <p class="muted">No teachers yet. <a href="create.php">Add the first one</a>.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th class="actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($teachers as $t): ?>
                    <tr>
                        <td><?= e($t['full_name']) ?></td>
                        <td><?= e($t['email']) ?></td>
                        <td><?= e($t['phone']) ?></td>
                        <td class="actions">
                            <a href="edit.php?id=<?= (int) $t['id'] ?>">Edit</a>

                            <?php
                            /*
                             * Delete is a POST form with a CSRF token — deliberately NOT a
                             * plain <a href="delete.php?id=5"> link. Two reasons:
                             *
                             *  1. GET should never change state. A delete link is a GET, and
                             *     GETs get fired without intent: browser prefetch, link
                             *     scanners, search crawlers, or a simple misclick could wipe
                             *     rows. POST is the correct verb for a destructive action.
                             *
                             *  2. CSRF protection. Any external page could embed
                             *     <img src="https://yoursite/.../delete.php?id=5"> and the
                             *     browser would send it with the admin's session cookie
                             *     attached — silently deleting the row (cross-site request
                             *     forgery). Requiring a POST that carries a secret,
                             *     per-session token that an attacker's page can't read means
                             *     deletion only happens from a real button press on our page.
                             *
                             * The JS confirm() is just a courtesy against misclicks; the POST
                             * + token is the actual security control.
                             */
                            ?>
                            <form class="inline" method="post" action="delete.php"
                                  onsubmit="return confirm('Delete <?= e($t['full_name']) ?>?');">
                                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
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
