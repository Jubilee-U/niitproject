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

function fieldValue(?string $v): string
{
    $v = trim((string) $v);
    return $v === '' ? '<span class="muted">—</span>' : e($v);
}

$statusDisplay = [
    'pending'  => ['label' => 'Pending',  'class' => 'badge-pending'],
    'accepted' => ['label' => 'Accepted', 'class' => 'badge-accepted'],
    'declined' => ['label' => 'Declined', 'class' => 'badge-declined'],
];

// Load the student's own applicant record.
$applicant = null;
if ($currentUser['applicant_id'] !== null) {
    $stmt = $pdo->prepare('SELECT * FROM applicants WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => (int) $currentUser['applicant_id']]);
    $applicant = $stmt->fetch() ?: null;
}

$statusKey   = (string) ($applicant['status'] ?? 'accepted');
$statusBadge = $statusDisplay[$statusKey] ?? ['label' => ucfirst($statusKey), 'class' => 'badge-unknown'];
$dobTs       = $applicant ? strtotime((string) $applicant['dob']) : false;
$appliedTs   = $applicant ? strtotime((string) $applicant['applied_at']) : false;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Profile — Student</title>
    <link rel="stylesheet" href="/niitproject/public/assets/style.css">
    <style>
        body { font-family: system-ui, sans-serif; margin: 0; color: #222; background: #f4f5f7; }
        .topbar { background: #1e293b; color: #fff; padding: .8rem 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: .5rem; }
        .topbar .brand { font-weight: 700; }
        .topbar nav a { color: #cbd5e1; text-decoration: none; margin-left: 1rem; font-size: .9rem; }
        .topbar nav a:hover, .topbar nav a.active { color: #fff; }
        main { max-width: 760px; margin: 1.5rem auto; padding: 0 1rem; }
        h1 { font-size: 1.4rem; margin: 0 0 1rem; }
        h2 { font-size: 1rem; margin: 1.5rem 0 .5rem; color: #444; border-bottom: 1px solid #e5e5e5; padding-bottom: .3rem; }
        a { color: #2563eb; text-decoration: none; }
        a:hover { text-decoration: underline; }
        .card { background: #fff; border: 1px solid #e5e5e5; border-radius: 8px; padding: 1rem 1.5rem; }
        .grid { display: grid; grid-template-columns: 12rem 1fr; gap: .45rem 1rem; font-size: .95rem; }
        .grid .k { color: #666; }
        .muted { color: #999; }
        .mono { font-family: ui-monospace, monospace; }
        .badge { display: inline-flex; align-items: center; gap: .4rem; padding: .2rem .6rem; border-radius: 999px; font-size: .85rem; font-weight: 600; }
        .badge::before { content: ''; width: .55rem; height: .55rem; border-radius: 50%; background: currentColor; }
        .badge-accepted { background: #dcfce7; color: #166534; }
        .badge-pending  { background: #fef9c3; color: #854d0e; }
        .badge-declined { background: #fee2e2; color: #991b1b; }
        .badge-unknown  { background: #eee; color: #555; }
    </style>
</head>
<body>
    <?php $navActive = 'dashboard'; include __DIR__ . '/../../includes/student_header.php'; ?>
    <!-- <div class="topbar">
        <span class="brand">Student Portal</span>
        <nav>
            <a href="dashboard.php">Dashboard</a>
            <a href="profile.php" class="active">Profile</a>
            <a href="subjects.php">Subjects</a>
            <a href="announcements.php">Announcements</a>
            <a href="../logout.php">Log out</a>
        </nav>
    </div> -->

    <main>
        <h1>My Profile</h1>

        <?php if ($applicant === null): ?>
            <div class="card"><p class="muted">Your application record could not be found.</p></div>
        <?php else: ?>
            <div class="card">
                <div class="grid">
                    <span class="k">Reference</span><span class="mono"><?= e($applicant['reference_no']) ?></span>
                    <span class="k">Admission status</span><span><span class="badge <?= e($statusBadge['class']) ?>"><?= e($statusBadge['label']) ?></span></span>
                    <span class="k">Applied on</span><span><?= $appliedTs !== false ? e(date('M j, Y', $appliedTs)) : '—' ?></span>
                </div>

                <h2>Personal details</h2>
                <div class="grid">
                    <span class="k">Full name</span><span><?= e($applicant['full_name']) ?></span>
                    <span class="k">Date of birth</span><span><?= $dobTs !== false ? e(date('M j, Y', $dobTs)) : fieldValue($applicant['dob']) ?></span>
                    <span class="k">Gender</span><span><?= $applicant['gender'] !== null && $applicant['gender'] !== '' ? e(ucfirst((string) $applicant['gender'])) : '<span class="muted">—</span>' ?></span>
                    <span class="k">Email</span><span><?= fieldValue($applicant['email']) ?></span>
                    <span class="k">Phone</span><span><?= fieldValue($applicant['phone']) ?></span>
                    <span class="k">Home address</span><span><?= fieldValue($applicant['home_address']) ?></span>
                    <span class="k">State of origin</span><span><?= fieldValue($applicant['state_of_origin']) ?></span>
                    <span class="k">Previous school</span><span><?= fieldValue($applicant['previous_school']) ?></span>
                </div>

                <h2>Guardian</h2>
                <div class="grid">
                    <span class="k">Guardian name</span><span><?= fieldValue($applicant['guardian_name']) ?></span>
                    <span class="k">Relationship</span><span><?= fieldValue($applicant['relationship_to_guardian']) ?></span>
                    <span class="k">Guardian phone</span><span><?= fieldValue($applicant['guardian_phone']) ?></span>
                    <span class="k">Guardian email</span><span><?= fieldValue($applicant['guardian_email']) ?></span>
                </div>

                <h2>Application</h2>
                <div class="grid">
                    <span class="k">Class level</span><span><?= e($applicant['program_applied']) ?></span>
                </div>

                <h2>Medical &amp; special needs</h2>
                <div class="grid">
                    <span class="k">Medical / allergies</span>
                    <span><?php $m = trim((string) $applicant['medical_notes']); echo $m === '' ? '<span class="muted">—</span>' : nl2br(e($m)); ?></span>
                    <span class="k">Disabilities / special needs</span>
                    <span><?php $sn = trim((string) $applicant['special_needs']); echo $sn === '' ? '<span class="muted">—</span>' : nl2br(e($sn)); ?></span>
                </div>
            </div>

            <p style="margin-top:1rem;"><a href="change-password.php">Change my password</a></p>
        <?php endif; ?>
    </main>
</body>
</html>
