<?php

declare(strict_types=1);

/*
 * Applicants — admin list view.
 *
 * PATH NOTE: /public/admin/applicants/ is one level deeper than /public/admin/,
 * so the bootstrap is THREE directories up (../../../).
 */
require_once __DIR__ . '/../../../includes/admin_bootstrap.php';

// One-shot flash (e.g. set by accept.php / decline.php).
$flash = null;
if (!empty($_SESSION['flash'])) {
    $flash = (string) $_SESSION['flash'];
    unset($_SESSION['flash']);
}

if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

$statusDisplay = [
    'pending'  => ['label' => 'Pending',  'class' => 'badge-pending'],
    'accepted' => ['label' => 'Accepted', 'class' => 'badge-accepted'],
    'declined' => ['label' => 'Declined', 'class' => 'badge-declined'],
];

/*
 * Filter + search come in via GET (read-only, bookmarkable). Both are validated
 * and bound into the prepared statement — never concatenated into SQL.
 */
$allowedStatuses = ['pending', 'accepted', 'declined'];
$statusFilter = (string) ($_GET['status'] ?? '');
if ($statusFilter !== '' && !in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = ''; // unknown value → treat as "All"
}
$q = trim((string) ($_GET['q'] ?? ''));

$conditions = [];
$params     = [];

if ($statusFilter !== '') {
    $conditions[]        = 'status = :status';
    $params[':status']   = $statusFilter;
}
if ($q !== '') {
    // Escape LIKE wildcards so a % or _ in the search box is treated literally.
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
    // Native prepares can't reuse a named placeholder, so bind two names.
    $conditions[]       = '(full_name LIKE :q_name OR reference_no LIKE :q_ref)';
    $params[':q_name']  = $like;
    $params[':q_ref']   = $like;
}

$where = $conditions !== [] ? ('WHERE ' . implode(' AND ', $conditions)) : '';

$stmt = $pdo->prepare(
    "SELECT id, reference_no, full_name, program_applied, status, applied_at
       FROM applicants
       {$where}
      ORDER BY applied_at DESC, id DESC"
);
$stmt->execute($params);
$applicants = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Applicants — Admin</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 2rem; color: #222; background: #fafafa; }
        main { max-width: 900px; margin: 0 auto; }
        h1 { font-size: 1.4rem; margin: 0 0 1rem; }
        a { color: #2563eb; text-decoration: none; }
        a:hover { text-decoration: underline; }
        table { width: 100%; border-collapse: collapse; background: #fff; }
        th, td { text-align: left; padding: .6rem .7rem; border-bottom: 1px solid #e5e5e5; vertical-align: middle; }
        th { background: #f0f2f5; font-size: .78rem; text-transform: uppercase; letter-spacing: .03em; color: #555; }
        .mono { font-family: ui-monospace, monospace; font-size: .9rem; }
        .flash { background: #e7f6ec; border: 1px solid #b7e0c4; color: #1c7a3f; padding: .6rem .8rem; border-radius: 5px; margin-bottom: 1rem; }
        .muted { color: #888; }
        .filters { display: flex; flex-wrap: wrap; gap: .6rem; align-items: end; margin-bottom: 1rem; }
        .filters label { font-size: .78rem; color: #555; display: block; }
        .filters select, .filters input[type=text] { padding: .45rem .55rem; border: 1px solid #ccc; border-radius: 5px; font-size: .95rem; }
        .filters input[type=text] { min-width: 15rem; }
        .btn { background: #2563eb; color: #fff; border: 0; padding: .5rem .9rem; border-radius: 5px; font-size: .95rem; cursor: pointer; }
        .btn:hover { background: #1d4ed8; }
        .badge { display: inline-flex; align-items: center; gap: .4rem; padding: .2rem .6rem; border-radius: 999px; font-size: .82rem; font-weight: 600; }
        .badge::before { content: ''; width: .5rem; height: .5rem; border-radius: 50%; background: currentColor; }
        .badge-pending  { background: #fef9c3; color: #854d0e; }
        .badge-accepted { background: #dcfce7; color: #166534; }
        .badge-declined { background: #fee2e2; color: #991b1b; }
        .badge-unknown  { background: #eee; color: #555; }
    </style>
</head>
<body>
    <main>
        <h1>Applicants</h1>

        <?php if ($flash !== null): ?>
            <p class="flash"><?= e($flash) ?></p>
        <?php endif; ?>

        <!-- Filter + search (GET). -->
        <form class="filters" method="get" action="index.php">
            <div>
                <label for="status">Status</label>
                <select name="status" id="status">
                    <option value="" <?= $statusFilter === '' ? 'selected' : '' ?>>All</option>
                    <?php foreach ($allowedStatuses as $s): ?>
                        <option value="<?= e($s) ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= e($statusDisplay[$s]['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="q">Search name or reference</label>
                <input type="text" name="q" id="q" value="<?= e($q) ?>" placeholder="e.g. Jane or APP-<?= e(date('Y')) ?>-...">
            </div>
            <button type="submit" class="btn">Filter</button>
            <?php if ($statusFilter !== '' || $q !== ''): ?>
                <a href="index.php" style="align-self:center;">Clear</a>
            <?php endif; ?>
        </form>

        <?php if (count($applicants) === 0): ?>
            <p class="muted">No applicants match your criteria.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Name</th>
                        <th>Class level</th>
                        <th>Status</th>
                        <th>Applied</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($applicants as $a): ?>
                    <?php
                        $key   = (string) $a['status'];
                        $badge = $statusDisplay[$key] ?? ['label' => ucfirst($key), 'class' => 'badge-unknown'];
                        $ts    = strtotime((string) $a['applied_at']);
                    ?>
                    <tr>
                        <td class="mono"><?= e($a['reference_no']) ?></td>
                        <td><?= e($a['full_name']) ?></td>
                        <td><?= e($a['program_applied']) ?></td>
                        <td><span class="badge <?= e($badge['class']) ?>"><?= e($badge['label']) ?></span></td>
                        <td class="muted"><?= $ts !== false ? e(date('M j, Y', $ts)) : '—' ?></td>
                        <td><a href="view.php?id=<?= (int) $a['id'] ?>">View</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </main>
</body>
</html>
