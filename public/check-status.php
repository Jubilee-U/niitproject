<?php

declare(strict_types=1);

/*
 * PUBLIC status-check page — no login. All input is untrusted: validated,
 * bound into a prepared statement, and escaped on output. This is a read-only
 * lookup (no mutation), so a plain POST that renders results is fine — no PRG.
 */

require_once __DIR__ . '/../config/database.php'; // provides $pdo
require_once __DIR__ . '/../includes/auth.php';   // startSecureSession() + CSRF helpers

startSecureSession();

if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

/*
 * Human-friendly labels + a CSS class per stored status value. Unknown values
 * fall back to a neutral badge so a new status can't break the page.
 */
$statusDisplay = [
    'pending'  => ['label' => 'Pending',  'class' => 'badge-pending'],
    'accepted' => ['label' => 'Accepted', 'class' => 'badge-accepted'],
    'declined' => ['label' => 'Declined', 'class' => 'badge-declined'],
];

/*
 * Naive rate limiting: count failed lookups in the session within a time
 * window. It only slows casual reference-number guessing from one browser — a
 * determined attacker with fresh sessions isn't stopped by this, which is why
 * the paired reference_no + email check is the real access control. A DB- or
 * IP-based limiter would be the next step for stronger protection.
 */
const MAX_ATTEMPTS = 5;
const RL_WINDOW    = 900; // 15 minutes

$rl = $_SESSION['status_rl'] ?? ['count' => 0, 'first' => time()];
if ((time() - (int) $rl['first']) > RL_WINDOW) {
    $rl = ['count' => 0, 'first' => time()]; // window elapsed → reset
}
$_SESSION['status_rl'] = $rl;
$rateLimited = $rl['count'] >= MAX_ATTEMPTS;

$errors   = [];
$values   = ['reference_no' => '', 'email' => ''];
$result   = null;   // matched applicant row
$notFound = false;  // a lookup ran but matched nothing

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$rateLimited) {
    // CSRF first.
    $token = $_POST['csrf_token'] ?? null;
    if (!verifyCsrfToken(is_string($token) ? $token : null)) {
        $errors['csrf'] = 'Your session has expired. Please reload the page and try again.';
    }

    $values['reference_no'] = trim((string) ($_POST['reference_no'] ?? ''));
    $values['email']        = trim((string) ($_POST['email'] ?? ''));

    if ($values['reference_no'] === '') {
        $errors['reference_no'] = 'Reference number is required.';
    }
    if ($values['email'] === '') {
        $errors['email'] = 'Email is required.';
    }

    if ($errors === []) {
        /*
         * Match on reference_no AND (applicant email OR guardian email), since
         * the applicant's own email is optional. Positional placeholders are
         * used because native prepared statements (EMULATE_PREPARES = false)
         * don't allow reusing a named placeholder, so the email is bound twice.
         */
        $stmt = $pdo->prepare(
            'SELECT full_name, program_applied, status, applied_at
               FROM applicants
              WHERE reference_no = ? AND (email = ? OR guardian_email = ?)
              LIMIT 1'
        );
        $stmt->execute([$values['reference_no'], $values['email'], $values['email']]);
        $row = $stmt->fetch();

        if ($row === false) {
            $notFound = true;
            // Count this failed attempt and re-evaluate the limit.
            $rl['count']++;
            $_SESSION['status_rl'] = $rl;
            $rateLimited = $rl['count'] >= MAX_ATTEMPTS;
        } else {
            $result = $row;
            // Successful lookup clears the failure counter.
            unset($_SESSION['status_rl']);
            $rateLimited = false;
        }
    }
}

$csrfToken = generateCsrfToken();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Check Application Status — School Admissions</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 2rem; color: #222; background: #fafafa; }
        main { max-width: 480px; margin: 0 auto; }
        h1 { font-size: 1.4rem; }
        a { color: #2563eb; text-decoration: none; }
        a:hover { text-decoration: underline; }
        label { display: block; margin-bottom: 1rem; font-size: .9rem; color: #333; }
        .req { color: #b91c1c; }
        input[type=text], input[type=email] { width: 100%; box-sizing: border-box; padding: .5rem .6rem;
            margin-top: .25rem; border: 1px solid #ccc; border-radius: 5px; font-size: 1rem; }
        input.invalid { border-color: #dc2626; box-shadow: 0 0 0 2px rgba(220,38,38,.12); }
        .field-error { color: #b91c1c; font-size: .8rem; margin: -0.6rem 0 1rem; }
        .btn { background: #2563eb; color: #fff; border: 0; padding: .6rem 1.2rem; border-radius: 5px;
            font-size: 1rem; cursor: pointer; }
        .btn:hover { background: #1d4ed8; }
        .errors { background: #fdecec; border: 1px solid #f3b7b7; color: #a12020; padding: .6rem .8rem;
            border-radius: 5px; margin-bottom: 1rem; }
        .notice { background: #fff7ed; border: 1px solid #fed7aa; color: #9a3412; padding: .8rem 1rem;
            border-radius: 6px; margin-bottom: 1rem; }
        .card { background: #fff; border: 1px solid #e5e5e5; border-radius: 8px; padding: 1.25rem 1.5rem;
            margin-bottom: 1.5rem; }
        .card h2 { margin: 0 0 1rem; font-size: 1.1rem; }
        .row { display: flex; justify-content: space-between; padding: .4rem 0; border-bottom: 1px solid #f0f0f0; font-size: .95rem; }
        .row:last-child { border-bottom: 0; }
        .row .k { color: #666; }
        .row .v { font-weight: 600; text-align: right; }
        .badge { display: inline-flex; align-items: center; gap: .4rem; padding: .2rem .6rem; border-radius: 999px;
            font-size: .85rem; font-weight: 600; }
        .badge::before { content: ''; width: .55rem; height: .55rem; border-radius: 50%; background: currentColor; }
        .badge-pending  { background: #fef9c3; color: #854d0e; }
        .badge-accepted { background: #dcfce7; color: #166534; }
        .badge-declined { background: #fee2e2; color: #991b1b; }
        .badge-unknown  { background: #eee; color: #555; }
        .accepted-note { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; padding: .8rem 1rem;
            border-radius: 6px; margin-top: 1rem; font-size: .95rem; }
    </style>
</head>
<body>
    <main>
        <h1>Check Application Status</h1>

        <?php if ($rateLimited): ?>

            <div class="notice">
                Too many attempts. Please try again later.
            </div>

        <?php else: ?>

            <?php if ($result !== null): ?>
                <?php
                    $key   = (string) $result['status'];
                    $badge = $statusDisplay[$key] ?? ['label' => ucfirst($key), 'class' => 'badge-unknown'];
                    $ts    = strtotime((string) $result['applied_at']);
                ?>
                <div class="card">
                    <h2>Application found</h2>
                    <div class="row"><span class="k">Applicant</span><span class="v"><?= e($result['full_name']) ?></span></div>
                    <div class="row"><span class="k">Class level</span><span class="v"><?= e($result['program_applied']) ?></span></div>
                    <div class="row"><span class="k">Applied on</span>
                        <span class="v"><?= $ts !== false ? e(date('M j, Y', $ts)) : '—' ?></span></div>
                    <div class="row"><span class="k">Status</span>
                        <span class="v"><span class="badge <?= e($badge['class']) ?>"><?= e($badge['label']) ?></span></span></div>

                    <?php if ($key === 'accepted'): ?>
                        <div class="accepted-note">
                            🎉 Congratulations! Please check your email for your login details and next steps.
                        </div>
                    <?php endif; ?>
                </div>

                <!-- On a successful lookup we hide the search form. This link is a
                     plain GET back to the page, which clears the result and shows
                     an empty form again. -->
                <p style="margin-top:1.5rem;">
                    <a class="btn" href="check-status.php" style="display:inline-block; text-decoration:none;">Check another application</a>
                </p>
            <?php else: ?>
                <?php if ($notFound): ?>
                    <div class="errors">
                        No application found with those details. Please check your reference number and email and try again.
                    </div>
                <?php endif; ?>

                <?php if (isset($errors['csrf'])): ?>
                    <div class="errors"><?= e($errors['csrf']) ?></div>
                <?php endif; ?>

                <p>Enter your reference number and the email used on your application.</p>

                <form method="post" action="check-status.php" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                    <label>Reference number <span class="req">*</span>
                        <input type="text" name="reference_no" value="<?= e($values['reference_no']) ?>"
                               placeholder="APP-<?= e(date('Y')) ?>-XXXXXX"
                               class="<?= isset($errors['reference_no']) ? 'invalid' : '' ?>">
                    </label>
                    <?php if (isset($errors['reference_no'])): ?><p class="field-error"><?= e($errors['reference_no']) ?></p><?php endif; ?>

                    <label>Email <span class="req">*</span>
                        <input type="email" name="email" value="<?= e($values['email']) ?>"
                               class="<?= isset($errors['email']) ? 'invalid' : '' ?>">
                    </label>
                    <?php if (isset($errors['email'])): ?><p class="field-error"><?= e($errors['email']) ?></p><?php endif; ?>

                    <div style="margin-top:.5rem;">
                        <button type="submit" class="btn">Check status</button>
                    </div>
                </form>
            <?php endif; ?>

        <?php endif; ?>
    </main>
</body>
</html>
