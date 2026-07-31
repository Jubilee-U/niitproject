<?php

declare(strict_types=1);

// PATH NOTE: /public/admin/dashboard.php is one level under /public, so /includes is two up.
require_once __DIR__ . '/../../includes/admin_bootstrap.php'; // $pdo, $currentUser (admin-only)

if (!function_exists('e')) {
    function e(?string $v): string { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
}

// Five simple COUNT queries — fine at this scale. All are static SQL (no user
// input), so plain query() is safe here.
$totalApplicants = (int) $pdo->query('SELECT COUNT(*) FROM applicants')->fetchColumn();
$accepted        = (int) $pdo->query("SELECT COUNT(*) FROM applicants WHERE status = 'accepted'")->fetchColumn();
$declined        = (int) $pdo->query("SELECT COUNT(*) FROM applicants WHERE status = 'declined'")->fetchColumn();
$pending         = (int) $pdo->query("SELECT COUNT(*) FROM applicants WHERE status = 'pending'")->fetchColumn();
$totalTeachers   = (int) $pdo->query('SELECT COUNT(*) FROM teachers')->fetchColumn();

// Each card: label, number, link to the relevant (filtered) view, accent colour.
$cards = [
    ['label' => 'Total Applicants',     'value' => $totalApplicants, 'href' => 'applicants/index.php',                 'accent' => '#2563eb'],
    ['label' => 'Accepted Students',    'value' => $accepted,        'href' => 'applicants/index.php?status=accepted', 'accent' => '#16a34a'],
    ['label' => 'Declined Students',    'value' => $declined,        'href' => 'applicants/index.php?status=declined', 'accent' => '#dc2626'],
    ['label' => 'Pending Applications', 'value' => $pending,         'href' => 'applicants/index.php?status=pending',  'accent' => '#d97706'],
    ['label' => 'Total Teachers',       'value' => $totalTeachers,   'href' => 'teachers/index.php',                   'accent' => '#7c3aed'],
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard — Admin</title>
    <link rel="stylesheet" href="/niitproject/public/assets/style.css">
    <style>
        body { font-family: system-ui, sans-serif; margin: 0; color: #222; background: #f4f5f7; }
        .topbar { background: #1e293b; color: #fff; padding: .8rem 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: .5rem; }
        .topbar .brand { font-weight: 700; }
        .topbar nav a { color: #cbd5e1; text-decoration: none; margin-left: 1rem; font-size: .9rem; }
        .topbar nav a:hover, .topbar nav a.active { color: #fff; }
        main { max-width: 900px; margin: 1.5rem auto; padding: 0 1rem; }
        h1 { font-size: 1.4rem; margin: 0 0 .25rem; }
        h2 { font-size: 1rem; margin: 0 0 .75rem; color: #444; }
        .signed-in { color: #888; font-size: .85rem; margin: 0 0 1.25rem; }
        a { color: #2563eb; text-decoration: none; }
        a:hover { text-decoration: underline; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(165px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
        .stat { background: #fff; border: 1px solid #e5e5e5; border-left: 4px solid #2563eb; border-radius: 8px;
            padding: 1.2rem 1.25rem; text-decoration: none; color: inherit; display: block; transition: box-shadow .15s, transform .15s; }
        .stat:hover { box-shadow: 0 3px 10px rgba(0,0,0,.09); transform: translateY(-1px); text-decoration: none; }
        .stat .label { color: #667085; font-size: .85rem; }
        .stat .value { font-size: 2.1rem; font-weight: 700; line-height: 1.1; margin-top: .3rem; }
        .card { background: #fff; border: 1px solid #e5e5e5; border-radius: 8px; padding: 1.1rem 1.5rem; }
        .navlinks { display: flex; gap: 1rem; flex-wrap: wrap; }
        .navlinks a { background: #eef2ff; color: #1e3a8a; padding: .55rem 1rem; border-radius: 6px; }
        .navlinks a:hover { background: #e0e7ff; text-decoration: none; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../includes/admin_header.php'; ?>
    
    <!-- <div class="topbar"> -->
        <!-- <span class="brand">Admin Portal</span> -->
        <!-- <nav>
            <a href="dashboard.php" class="active">Dashboard</a>
            <a href="applicants/index.php">Applicants</a>
            <a href="teachers/index.php">Teachers</a>
            <a href="subjects/index.php">Subjects</a>
            <a href="announcements/index.php">Announcements</a>
            <a href="../logout.php">Log out</a>
        </nav> -->
    <!-- </div> -->

    <main>
        <h1>Dashboard</h1>
        <p class="signed-in">Signed in as <?= e($currentUser['username']) ?></p>

        <div class="stats">
            <?php foreach ($cards as $c): ?>
                <a class="stat" href="<?= e($c['href']) ?>" style="border-left-color: <?= e($c['accent']) ?>;">
                    <div class="label"><?= e($c['label']) ?></div>
                    <div class="value"><?= (int) $c['value'] ?></div>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="card">
            <h2>Manage</h2>
            <div class="navlinks">
                <a href="applicants/index.php">Applicants</a>
                <a href="teachers/index.php">Teachers</a>
                <a href="subjects/index.php">Subjects</a>
                <a href="announcements/index.php">Announcements</a>
            </div>
        </div>
    </main>
</body>
</html>
