<?php

declare(strict_types=1);

// PATH NOTE: three levels up to reach /includes and /templates.
require_once __DIR__ . '/../../../includes/admin_bootstrap.php';        // $pdo, $currentUser, admin gate
require_once __DIR__ . '/../../../includes/mailer.php';                 // sendEmail()
require_once __DIR__ . '/../../../templates/admission_accepted.php';    // renderAdmissionAcceptedEmail()

// Internal domain for school-issued student login identities. These are login
// identifiers, NOT real mailboxes — students never receive mail here (that goes
// to their notification address). Change this to your chosen internal domain.
const STUDENT_LOGIN_DOMAIN = 'students.brighthousecollege.local';

// POST only.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// CSRF.
$token = $_POST['csrf_token'] ?? null;
if (!verifyCsrfToken(is_string($token) ? $token : null)) {
    $_SESSION['flash'] = 'Security check failed. Please try again.';
    header('Location: index.php');
    exit;
}

// id required.
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if ($id === false || $id === null) {
    $_SESSION['flash'] = 'Invalid applicant id.';
    header('Location: index.php');
    exit;
}

/**
 * Build a unique username from the name plus random digits. The uniqueness
 * check runs inside the caller's transaction; a UNIQUE index on users.username
 * is the real guarantee (a duplicate insert would then throw and roll back).
 */
function generateUniqueUsername(PDO $pdo, string $fullName): string
{
    $base = preg_replace('/[^a-z0-9]+/', '', strtolower($fullName));
    if ($base === '' || $base === null) {
        $base = 'student';
    }
    $base = substr($base, 0, 20);

    $check = $pdo->prepare('SELECT 1 FROM users WHERE username = :u LIMIT 1');
    for ($i = 0; $i < 25; $i++) {
        $candidate = $base . random_int(1000, 9999);
        $check->execute([':u' => $candidate]);
        if ($check->fetchColumn() === false) {
            return $candidate;
        }
    }
    throw new RuntimeException('Could not generate a unique username.');
}

// Data captured on success, used AFTER commit to send the email.
$committed = null;

try {
    $pdo->beginTransaction();

    /*
     * Re-fetch the applicant WITH A ROW LOCK (FOR UPDATE) inside the transaction.
     *
     * Why the lock — not just a status check — is what prevents double
     * processing: imagine two admins click Accept at the same moment. Without a
     * lock, both requests SELECT the row, both see status = 'pending', and both
     * proceed to create a user account and flip the status — producing two
     * student logins and two acceptance emails for one applicant. Checking the
     * status before or even at the start of the transaction doesn't help,
     * because the check and the update are separate steps and a second request
     * can slip between them (a time-of-check/time-of-use race).
     *
     * FOR UPDATE closes that gap: it locks this applicant row until our
     * transaction commits or rolls back. The second transaction's own
     * SELECT ... FOR UPDATE BLOCKS until the first finishes; only then does it
     * read the row — and it now sees status = 'accepted' and bails out. The lock
     * serializes the read-decide-write sequence so exactly one accept can win.
     */
    $sel = $pdo->prepare(
        'SELECT full_name, email, guardian_email, status
           FROM applicants
          WHERE id = :id
          FOR UPDATE'
    );
    $sel->execute([':id' => $id]);
    $applicant = $sel->fetch();

    if ($applicant === false) {
        $pdo->rollBack();
        $_SESSION['flash'] = 'Applicant not found.';
        header('Location: index.php');
        exit;
    }

    // Already-processed guard — now safe, because we hold the lock.
    if ($applicant['status'] !== 'pending') {
        $pdo->rollBack();
        $_SESSION['flash'] = 'This application has already been processed.';
        header('Location: view.php?id=' . $id);
        exit;
    }

    // Notification recipient (unchanged): the applicant's own email if present,
    // otherwise the guardian's. This is only where credentials are SENT — it is
    // NOT what we store as the login identity.
    $applicantEmail = trim((string) $applicant['email']);
    $guardianEmail  = trim((string) $applicant['guardian_email']);
    $notifyEmail    = $applicantEmail !== '' ? $applicantEmail : $guardianEmail;

    // We still need somewhere to deliver the credentials.
    if ($notifyEmail === '') {
        $pdo->rollBack();
        $_SESSION['flash'] = 'Cannot accept: no email address on file to send login credentials to.';
        header('Location: view.php?id=' . $id);
        exit;
    }

    // Generate credentials.
    $username = generateUniqueUsername($pdo, (string) $applicant['full_name']);

    // School-issued login identity, derived from the already-unique username, so
    // it is guaranteed unique too. Storing THIS as users.email (instead of the
    // applicant's or guardian's address) means two siblings can share one
    // guardian email for notifications while each keeps a distinct login — so
    // there's no duplicate-email collision, and no conflict check is needed.
    $loginEmail   = $username . '@' . STUDENT_LOGIN_DOMAIN;
    $tempPassword = bin2hex(random_bytes(5)); // 10 hex chars
    $passwordHash = password_hash($tempPassword, PASSWORD_DEFAULT);

    // Create the student account (must change password on first login). The
    // login email is the school-issued address; the notification address is used
    // only for delivery, after commit.
    $insUser = $pdo->prepare(
        'INSERT INTO users
            (applicant_id, username, email, password_hash, role, must_change_password, created_at)
         VALUES
            (:applicant_id, :username, :email, :password_hash, :role, :must_change, NOW())'
    );
    $insUser->execute([
        ':applicant_id'  => $id,
        ':username'      => $username,
        ':email'         => $loginEmail,
        ':password_hash' => $passwordHash,
        ':role'          => 'student',
        ':must_change'   => 1,
    ]);

    // Flip the applicant to accepted and stamp the decision time.
    $upd = $pdo->prepare('UPDATE applicants SET status = :s, decision_at = NOW() WHERE id = :id');
    $upd->execute([':s' => 'accepted', ':id' => $id]);

    $pdo->commit();

    // Capture what the post-commit email needs (plaintext password shown once).
    $committed = [
        'name'         => (string) $applicant['full_name'],
        'notify_email' => $notifyEmail, // where we SEND the credentials
        'login_email'  => $loginEmail,  // what the student SIGNS IN with
        'password'     => $tempPassword,
    ];
} catch (Throwable $ex) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('accept.php failed for applicant ' . $id . ': ' . $ex->getMessage());
    $_SESSION['flash'] = 'Something went wrong while accepting this application. Please try again.';
    header('Location: view.php?id=' . $id);
    exit;
}

/*
 * AFTER commit — send the acceptance email as best-effort, exactly like
 * apply.php. The account and status change are already durable; an email
 * failure must not (and here cannot) undo them. sendEmail() logs its own
 * outcome to email_log and we ignore the return value.
 */
if ($committed !== null) {
    // Absolute login URL for the email. HTTP_HOST is client-supplied; for
    // production, prefer a configured APP_URL in .env over trusting the header.
    $scheme = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443) ? 'https' : 'http';
    $host     = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $loginUrl = $scheme . '://' . $host . '/login.php';

    $htmlBody = renderAdmissionAcceptedEmail(
        $committed['name'],
        $committed['login_email'],
        $committed['password'],
        $loginUrl
    );

    sendEmail(
        $pdo,
        $committed['notify_email'],
        $committed['name'],
        'Your Admission Application — Congratulations!',
        $htmlBody,
        $id,
        'accepted'
    );

    $_SESSION['flash'] = 'Applicant accepted. Login credentials sent.';
    header('Location: view.php?id=' . $id);
    exit;
}
