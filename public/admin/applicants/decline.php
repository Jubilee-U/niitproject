<?php

declare(strict_types=1);

// PATH NOTE: three levels up to reach /includes and /templates.
require_once __DIR__ . '/../../../includes/admin_bootstrap.php';        // $pdo, $currentUser, admin gate
require_once __DIR__ . '/../../../includes/mailer.php';                 // sendEmail()
require_once __DIR__ . '/../../../templates/admission_declined.php';    // renderAdmissionDeclinedEmail()

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

// Data captured on success, used AFTER commit to send the email.
$committed = null;

try {
    $pdo->beginTransaction();

    /*
     * Same row-lock guard as accept.php: SELECT ... FOR UPDATE locks the row for
     * the duration of the transaction, so two simultaneous decisions can't both
     * pass the "is it still pending?" check and both act. A plain pre-check
     * outside the lock would leave a race between the check and the update; the
     * lock serializes read-decide-write so only one decision wins.
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

    if ($applicant['status'] !== 'pending') {
        $pdo->rollBack();
        $_SESSION['flash'] = 'This application has already been processed.';
        header('Location: view.php?id=' . $id);
        exit;
    }

    // Flip the applicant to declined and stamp the decision time.
    $upd = $pdo->prepare('UPDATE applicants SET status = :s, decision_at = NOW() WHERE id = :id');
    $upd->execute([':s' => 'declined', ':id' => $id]);

    $pdo->commit();

    // Notify at the applicant's own email if present, else the guardian's.
    $applicantEmail = trim((string) $applicant['email']);
    $guardianEmail  = trim((string) $applicant['guardian_email']);

    $committed = [
        'name'  => (string) $applicant['full_name'],
        'email' => $applicantEmail !== '' ? $applicantEmail : $guardianEmail,
    ];
} catch (Throwable $ex) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('decline.php failed for applicant ' . $id . ': ' . $ex->getMessage());
    $_SESSION['flash'] = 'Something went wrong while declining this application. Please try again.';
    header('Location: view.php?id=' . $id);
    exit;
}

/*
 * AFTER commit — send the decline email as best-effort. The status change is
 * already durable; email failure must not affect it.
 */
if ($committed !== null) {
    if ($committed['email'] !== '') {
        $htmlBody = renderAdmissionDeclinedEmail($committed['name']);
        sendEmail(
            $pdo,
            $committed['email'],
            $committed['name'],
            'Your Admission Application — Update',
            $htmlBody,
            $id,
            'declined'
        );
        $_SESSION['flash'] = 'Applicant declined. Notification sent.';
    } else {
        // No email on file — the decision still stands; we just can't notify.
        $_SESSION['flash'] = 'Applicant declined. (No email on file to notify.)';
    }

    header('Location: view.php?id=' . $id);
    exit;
}
