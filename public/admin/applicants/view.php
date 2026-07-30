<?php

declare(strict_types=1);

// PATH NOTE: three levels up to reach /includes (see index.php).
require_once __DIR__ . '/../../../includes/admin_bootstrap.php';

// Validate the id.
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($id === false || $id === null) {
    $_SESSION['flash'] = 'Invalid applicant id.';
    header('Location: index.php');
    exit;
}

// One-shot flash (e.g. from accept.php / decline.php).
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

// Optional-field renderer: shows an em dash when empty.
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

// Load the applicant.
$stmt = $pdo->prepare('SELECT * FROM applicants WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $id]);
$applicant = $stmt->fetch();

if ($applicant === false) {
    $_SESSION['flash'] = 'Applicant not found.';
    header('Location: index.php');
    exit;
}

// Load related documents.
$docStmt = $pdo->prepare(
    'SELECT id, doc_type, original_name, uploaded_at
       FROM applicant_documents
      WHERE applicant_id = :id
      ORDER BY uploaded_at ASC, id ASC'
);
$docStmt->execute([':id' => $id]);
$documents = $docStmt->fetchAll();

$statusKey   = (string) $applicant['status'];
$statusBadge = $statusDisplay[$statusKey] ?? ['label' => ucfirst($statusKey), 'class' => 'badge-unknown'];
$isPending   = $statusKey === 'pending';

$appliedTs  = strtotime((string) $applicant['applied_at']);
$decisionTs = $applicant['decision_at'] !== null ? strtotime((string) $applicant['decision_at']) : false;
$dobTs      = strtotime((string) $applicant['dob']);

$csrfToken = generateCsrfToken();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Applicant — <?= e($applicant['full_name']) ?></title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 2rem; color: #222; background: #fafafa; }
        main { max-width: 760px; margin: 0 auto; }
        a { color: #2563eb; text-decoration: none; }
        a:hover { text-decoration: underline; }
        .top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; }
        h1 { font-size: 1.4rem; margin: 0; }
        h2 { font-size: 1rem; margin: 1.5rem 0 .5rem; color: #444; border-bottom: 1px solid #e5e5e5; padding-bottom: .3rem; }
        .flash { background: #e7f6ec; border: 1px solid #b7e0c4; color: #1c7a3f; padding: .6rem .8rem; border-radius: 5px; margin-bottom: 1rem; }
        .card { background: #fff; border: 1px solid #e5e5e5; border-radius: 8px; padding: 1rem 1.25rem; }
        .grid { display: grid; grid-template-columns: 12rem 1fr; gap: .45rem 1rem; font-size: .95rem; }
        .grid .k { color: #666; }
        .block { white-space: pre-wrap; margin: .25rem 0 0; }
        .muted { color: #999; }
        .mono { font-family: ui-monospace, monospace; }
        .badge { display: inline-flex; align-items: center; gap: .4rem; padding: .2rem .6rem; border-radius: 999px; font-size: .85rem; font-weight: 600; }
        .badge::before { content: ''; width: .55rem; height: .55rem; border-radius: 50%; background: currentColor; }
        .badge-pending  { background: #fef9c3; color: #854d0e; }
        .badge-accepted { background: #dcfce7; color: #166534; }
        .badge-declined { background: #fee2e2; color: #991b1b; }
        .badge-unknown  { background: #eee; color: #555; }
        ul.docs { list-style: none; padding: 0; margin: 0; }
        ul.docs li { padding: .5rem 0; border-bottom: 1px solid #f0f0f0; display: flex; justify-content: space-between; gap: 1rem; }
        ul.docs li:last-child { border-bottom: 0; }
        .actions { margin-top: 1.5rem; display: flex; gap: 1rem; }
        .btn { border: 0; padding: .6rem 1.3rem; border-radius: 5px; font-size: 1rem; cursor: pointer; color: #fff; }
        .btn-accept { background: #16a34a; }
        .btn-accept:hover { background: #15803d; }
        .btn-decline { background: #dc2626; }
        .btn-decline:hover { background: #b91c1c; }
        form.inline { display: inline; margin: 0; }
        .decision-info { margin-top: 1.5rem; font-size: .95rem; color: #444; }
    </style>
</head>
<body>
    <main>
        <div class="top">
            <h1><?= e($applicant['full_name']) ?></h1>
            <a href="index.php">&larr; Back to list</a>
        </div>

        <?php if ($flash !== null): ?>
            <p class="flash"><?= e($flash) ?></p>
        <?php endif; ?>

        <div class="card">
            <div class="grid">
                <span class="k">Reference</span><span class="mono"><?= e($applicant['reference_no']) ?></span>
                <span class="k">Status</span><span><span class="badge <?= e($statusBadge['class']) ?>"><?= e($statusBadge['label']) ?></span></span>
                <span class="k">Applied on</span><span><?= $appliedTs !== false ? e(date('M j, Y g:i A', $appliedTs)) : '—' ?></span>
            </div>

            <h2>Applicant</h2>
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
                <span class="k">Class level applied for</span><span><?= e($applicant['program_applied']) ?></span>
            </div>

            <h2>Additional information</h2>
            <div class="grid">
                <span class="k">Medical / allergies</span>
                <span><?php $m = trim((string) $applicant['medical_notes']); echo $m === '' ? '<span class="muted">—</span>' : nl2br(e($m)); ?></span>
                <span class="k">Disabilities / special needs</span>
                <span><?php $sn = trim((string) $applicant['special_needs']); echo $sn === '' ? '<span class="muted">—</span>' : nl2br(e($sn)); ?></span>
            </div>

            <h2>Documents</h2>
            <?php if (count($documents) === 0): ?>
                <p class="muted">No documents uploaded.</p>
            <?php else: ?>
                <ul class="docs">
                    <?php foreach ($documents as $doc): ?>
                        <li>
                            <span>
                                <strong><?= e($doc['doc_type']) ?></strong>
                                &mdash; <?= e($doc['original_name']) ?>
                            </span>
                            <!-- Placeholder link; the secure download script is built separately. -->
                            <a href="download.php?doc_id=<?= (int) $doc['id'] ?>">View / download</a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?php if ($isPending): ?>
                <div class="actions">
                    <!-- Significant, state-changing actions: POST + CSRF + confirm(). -->
                    <form class="inline" method="post" action="accept.php"
                          onsubmit="return confirm('Accept this applicant?');">
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                        <input type="hidden" name="id" value="<?= (int) $applicant['id'] ?>">
                        <button type="submit" class="btn btn-accept">Accept</button>
                    </form>
                    <form class="inline" method="post" action="decline.php"
                          onsubmit="return confirm('Decline this applicant?');">
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                        <input type="hidden" name="id" value="<?= (int) $applicant['id'] ?>">
                        <button type="submit" class="btn btn-decline">Decline</button>
                    </form>
                </div>
            <?php else: ?>
                <div class="decision-info">
                    This application was
                    <span class="badge <?= e($statusBadge['class']) ?>"><?= e($statusBadge['label']) ?></span>
                    <?php if ($decisionTs !== false): ?>
                        on <?= e(date('M j, Y g:i A', $decisionTs)) ?>.
                    <?php else: ?>
                        (decision date not recorded).
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
