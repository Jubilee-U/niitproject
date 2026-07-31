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

// LEFT JOIN so subjects with no assigned teacher still appear.
$subjects = $pdo->query(
    'SELECT s.name, s.description, t.full_name AS teacher_name
       FROM subjects s
       LEFT JOIN teachers t ON t.id = s.teacher_id
      ORDER BY s.name ASC'
)->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Subjects — Student</title>
    <link rel="stylesheet" href="/niitproject/public/assets/style.css">
    <style>
        body { font-family: system-ui, sans-serif; margin: 0; color: #222; background: #f4f5f7; }
        .topbar { background: #1e293b; color: #fff; padding: .8rem 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: .5rem; }
        .topbar .brand { font-weight: 700; }
        .topbar nav a { color: #cbd5e1; text-decoration: none; margin-left: 1rem; font-size: .9rem; }
        .topbar nav a:hover, .topbar nav a.active { color: #fff; }
        main { max-width: 760px; margin: 1.5rem auto; padding: 0 1rem; }
        h1 { font-size: 1.4rem; margin: 0 0 1rem; }
        .card { background: #fff; border: 1px solid #e5e5e5; border-radius: 8px; padding: 0; overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: .7rem .9rem; border-bottom: 1px solid #eee; vertical-align: top; font-size: .95rem; }
        th { background: #f0f2f5; font-size: .78rem; text-transform: uppercase; letter-spacing: .03em; color: #555; }
        tr:last-child td { border-bottom: 0; }
        .muted { color: #999; }
    </style>
</head>
<body>
    <?php $navActive = 'dashboard'; include __DIR__ . '/../../includes/student_header.php'; ?>
    <!-- <div class="topbar">
        <span class="brand">Student Portal</span>
        <nav>
            <a href="dashboard.php">Dashboard</a>
            <a href="profile.php">Profile</a>
            <a href="subjects.php" class="active">Subjects</a>
            <a href="announcements.php">Announcements</a>
            <a href="../logout.php">Log out</a>
        </nav>
    </div> -->

    <main>
        <h1>Subjects</h1>

        <?php if (count($subjects) === 0): ?>
            <div class="card" style="padding:1.1rem 1.5rem;"><p class="muted" style="margin:0;">No subjects have been added yet.</p></div>
        <?php else: ?>
            <div class="card">
                <table>
                    <thead>
                        <tr><th>Subject</th><th>Teacher</th><th>Description</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($subjects as $s): ?>
                        <tr>
                            <td><?= e($s['name']) ?></td>
                            <td>
                                <?php if ($s['teacher_name'] === null): ?>
                                    <span class="muted">— Unassigned —</span>
                                <?php else: ?>
                                    <?= e($s['teacher_name']) ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php $d = trim((string) $s['description']); ?>
                                <?= $d === '' ? '<span class="muted">—</span>' : e($d) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
