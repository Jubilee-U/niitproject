<?php

declare(strict_types=1);

// PATH NOTE: /public/student/ is one level under /public, so /includes is two up.
require_once __DIR__ . '/../../includes/student_bootstrap.php'; // $pdo, $currentUser (role=student enforced)

/*
 * Forced password change: if the account still has the temporary password
 * (must_change_password = 1), send them to change-password.php before showing
 * anything else. This enforces the change deferred back at the auth step.
 */
if ((int) $currentUser['must_change_password'] === 1) {
    header('Location: change-password.php');
    exit;
}

$flash = null;
if (!empty($_SESSION['flash'])) {
    $flash = (string) $_SESSION['flash'];
    unset($_SESSION['flash']);
}

if (!function_exists('e')) {
    function e(?string $v): string { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
}

function truncate(?string $t, int $limit = 160): string
{
    $t = trim((string) $t);
    if ($t === '') return '';
    return mb_strlen($t) <= $limit ? $t : mb_substr($t, 0, $limit - 1) . '…';
}

$statusDisplay = [
    'pending'  => ['label' => 'Pending',  'class' => 'badge-pending'],
    'accepted' => ['label' => 'Accepted', 'class' => 'badge-accepted'],
    'declined' => ['label' => 'Declined', 'class' => 'badge-declined'],
];

// Linked applicant record (name + status) via applicant_id.
$applicant = null;
if ($currentUser['applicant_id'] !== null) {
    $stmt = $pdo->prepare('SELECT full_name, status FROM applicants WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => (int) $currentUser['applicant_id']]);
    $applicant = $stmt->fetch() ?: null;
}
$displayName = (string) ($applicant['full_name'] ?? $currentUser['username']);
$statusKey   = (string) ($applicant['status'] ?? 'accepted');
$statusBadge = $statusDisplay[$statusKey] ?? ['label' => ucfirst($statusKey), 'class' => 'badge-unknown'];

// Latest 3 announcements for the preview.
$announcements = $pdo->query(
    'SELECT title, body, created_at FROM announcements ORDER BY created_at DESC, id DESC LIMIT 3'
)->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard — Student</title>
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
            <a href="dashboard.php" class="active">Dashboard</a>
            <a href="profile.php">Profile</a>
            <a href="subjects.php">Subjects</a>
            <a href="announcements.php">Announcements</a>
           <a href="../logout.php">Log out</a>
        </nav>
    </div> -->

    <main>
        <?php if ($flash !== null): ?>
            <p class="flash"><?= e($flash) ?></p>
        <?php endif; ?>

        <div class="card">
            <h1>Welcome, <?= e($displayName) ?>!</h1>
            <p style="margin:.25rem 0 0;">
                Admission status:
                <span class="badge <?= e($statusBadge['class']) ?>"><?= e($statusBadge['label']) ?></span>
            </p>
        </div>

        <div class="card">
            <h2>Recent announcements</h2>
            <?php if (count($announcements) === 0): ?>
                <p class="muted">No announcements yet.</p>
            <?php else: ?>
                <?php foreach ($announcements as $a): ?>
                    <?php $ts = strtotime((string) $a['created_at']); ?>
                    <div class="ann">
                        <div class="t"><?= e($a['title']) ?></div>
                        <div class="d"><?= $ts !== false ? e(date('M j, Y', $ts)) : '' ?></div>
                        <div><?= e(truncate($a['body'])) ?></div>
                    </div>
                <?php endforeach; ?>
                <p style="margin:.75rem 0 0;"><a href="announcements.php">View all announcements →</a></p>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2>Quick links</h2>
            <div class="quicklinks">
                <a href="profile.php">My profile</a>
                <a href="subjects.php">Subjects</a>
                <a href="announcements.php">Announcements</a>
                <a href="change-password.php">Change password</a>
            </div>
        </div>
    </main>
</body>
</html>
