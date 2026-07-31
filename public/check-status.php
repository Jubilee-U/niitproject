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
    <link rel="stylesheet" href="/niitproject/public/assets/style.css">
</head>
<body class="auth">
<?php include __DIR__ . '/../includes/public_header.php'; ?>
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
