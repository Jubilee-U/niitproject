<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/student_bootstrap.php'; // $pdo, $currentUser

// Same forced-password-change guard as dashboard.php.
if ((int) $currentUser['must_change_password'] === 1) {
    header('Location: change-password.php');
    exit;
}

if (!function_exists('e')) {
    function e(?string $v): string { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
}

$announcements = $pdo->query(
    'SELECT title, body, created_at FROM announcements ORDER BY created_at DESC, id DESC'
)->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Announcements — Student</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 0; color: #222; background: #f4f5f7; }
        .topbar { background: #1e293b; color: #fff; padding: .8rem 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: .5rem; }
        .topbar .brand { font-weight: 700; }
        .topbar nav a { color: #cbd5e1; text-decoration: none; margin-left: 1rem; font-size: .9rem; }
        .topbar nav a:hover, .topbar nav a.active { color: #fff; }
        main { max-width: 760px; margin: 1.5rem auto; padding: 0 1rem; }
        h1 { font-size: 1.4rem; margin: 0 0 1rem; }
        .card { background: #fff; border: 1px solid #e5e5e5; border-radius: 8px; padding: 1.1rem 1.5rem; margin-bottom: 1rem; }
        .card h2 { font-size: 1.05rem; margin: 0 0 .25rem; }
        .card .date { color: #999; font-size: .8rem; margin: 0 0 .6rem; }
        .card .body { font-size: .95rem; line-height: 1.55; }
        .muted { color: #888; }
        a { color: #2563eb; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="topbar">
        <span class="brand">Student Portal</span>
        <nav>
            <a href="dashboard.php">Dashboard</a>
            <a href="profile.php">Profile</a>
            <a href="subjects.php">Subjects</a>
            <a href="announcements.php" class="active">Announcements</a>
            <a href="/logout.php">Log out</a>
        </nav>
    </div>

    <main>
        <h1>Announcements</h1>

        <?php if (count($announcements) === 0): ?>
            <div class="card"><p class="muted" style="margin:0;">No announcements yet.</p></div>
        <?php else: ?>
            <?php foreach ($announcements as $a): ?>
                <?php $ts = strtotime((string) $a['created_at']); ?>
                <div class="card">
                    <h2><?= e($a['title']) ?></h2>
                    <p class="date"><?= $ts !== false ? e(date('M j, Y', $ts)) : '' ?></p>
                    <div class="body"><?= nl2br(e($a['body'])) ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>
</body>
</html>
