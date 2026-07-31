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
    <link rel="stylesheet" href="/niitproject/public/assets/style.css">
  <style>
        body { font-family: system-ui, sans-serif; margin: 0; color: #222; background: #f4f5f7; }
        .topbar { background: #1e293b; color: #fff; padding: .8rem 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: .5rem; }
        .topbar .brand { font-weight: 700; }
        .topbar nav a { color: #cbd5e1; text-decoration: none; margin-left: 1rem; font-size: .9rem; }
        .topbar nav a:hover, .topbar nav a.active { color: #fff; }
        main { max-width: 760px; margin: 1.5rem auto; padding: 0 1rem; }
        h1 { font-size: 1.4rem; margin: 0 0 .25rem; }
        h2 { font-size: 1rem; margin: 0 0 .75rem; color: #444; }
        a { color: #2563eb; text-decoration: none; }
        a:hover { text-decoration: underline; }
        .card { background: #fff; border: 1px solid #e5e5e5; border-radius: 8px; padding: 1.25rem 1.5rem; margin-bottom: 1.25rem; }
        .flash { background: #e7f6ec; border: 1px solid #b7e0c4; color: #1c7a3f; padding: .6rem .8rem; border-radius: 5px; margin-bottom: 1rem; }
        .muted { color: #888; }
        .badge { display: inline-flex; align-items: center; gap: .4rem; padding: .2rem .6rem; border-radius: 999px; font-size: .82rem; font-weight: 600; }
        .badge::before { content: ''; width: .5rem; height: .5rem; border-radius: 50%; background: currentColor; }
        .badge-accepted { background: #dcfce7; color: #166534; }
        .badge-pending  { background: #fef9c3; color: #854d0e; }
        .badge-declined { background: #fee2e2; color: #991b1b; }
        .badge-unknown  { background: #eee; color: #555; }
        .ann { padding: .6rem 0; border-bottom: 1px solid #f0f0f0; }
        .ann:last-child { border-bottom: 0; }
        .ann .t { font-weight: 600; }
        .ann .d { color: #999; font-size: .8rem; }
        .quicklinks { display: flex; gap: 1rem; flex-wrap: wrap; }
        .quicklinks a { background: #eef2ff; color: #1e3a8a; padding: .55rem 1rem; border-radius: 6px; }
        .quicklinks a:hover { background: #e0e7ff; text-decoration: none; }
    </style>
</head>
<body>
    <?php $navActive = 'dashboard'; include __DIR__ . '/../../includes/student_header.php'; ?>
    <!-- <div class="topbar">
        <span class="brand">Student Portal</span>
        <nav>
            <a href="dashboard.php">Dashboard</a>
            <a href="profile.php">Profile</a>
            <a href="subjects.php">Subjects</a>
            <a href="announcements.php" class="active">Announcements</a>
            <a href="../logout.php">Log out</a>
        </nav>
    </div> -->

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
