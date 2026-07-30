<?php

declare(strict_types=1);

/*
 * Email sending via PHPMailer.
 *
 * Relies on the SMTP_* variables in .env, which are loaded into $_ENV by
 * config/database.php (Dotenv). Any script that calls sendEmail() already
 * requires database.php, so those variables are present by the time we run.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * Send an HTML email and record the attempt in email_log.
 *
 * Returns true if PHPMailer accepted and sent the message, false otherwise.
 * All PHPMailer exceptions are caught here — they never bubble up to crash the
 * calling page. Every attempt (success or failure) is logged to email_log.
 *
 * $pdo is required (in addition to your listed parameters) because the function
 * writes the audit row itself. $applicantId may be null for non-applicant mail.
 */
function sendEmail(
    PDO $pdo,
    string $to,
    string $toName,
    string $subject,
    string $htmlBody,
    ?int $applicantId = null,
    string $emailType = 'general'
): bool {
    $mail         = null;
    $status       = 'failed';
    $errorMessage = null;

    try {
        $mail = new PHPMailer(true); // true → throw exceptions we handle below

        // ── SMTP transport ──────────────────────────────────────────────
        $mail->isSMTP();
        $mail->Host       = (string) ($_ENV['SMTP_HOST'] ?? '');
        $mail->Port       = (int) ($_ENV['SMTP_PORT'] ?? 587);
        $mail->SMTPAuth   = true;
        $mail->Username   = (string) ($_ENV['SMTP_USERNAME'] ?? '');
        $mail->Password   = (string) ($_ENV['SMTP_PASSWORD'] ?? '');
        // Port 465 = implicit TLS (SMTPS); anything else = STARTTLS.
        $mail->SMTPSecure = ((int) ($_ENV['SMTP_PORT'] ?? 587) === 465)
            ? PHPMailer::ENCRYPTION_SMTPS
            : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->CharSet    = 'UTF-8';

        // ── Message ─────────────────────────────────────────────────────
        $mail->setFrom(
            (string) ($_ENV['SMTP_FROM_EMAIL'] ?? ''),
            (string) ($_ENV['SMTP_FROM_NAME'] ?? '')
        );
        $mail->addAddress($to, $toName);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = trim(strip_tags($htmlBody)); // plain-text fallback

        $mail->send();
        $status = 'sent';
    } catch (Throwable $e) {
        // PHPMailer throws PHPMailerException, but we catch Throwable so nothing
        // at all can escape this function into the page. ErrorInfo carries the
        // most useful detail when the mailer object exists.
        $errorMessage = ($mail !== null && $mail->ErrorInfo !== '')
            ? $mail->ErrorInfo
            : $e->getMessage();
        error_log('sendEmail failed to ' . $to . ': ' . $errorMessage);
    }

    // Log the attempt regardless of outcome. Wrapped in its own try/catch so a
    // logging failure also can't throw into the caller.
    try {
        $log = $pdo->prepare(
            'INSERT INTO email_log
                (applicant_id, email_type, recipient, status, error_message, sent_at)
             VALUES
                (:applicant_id, :email_type, :recipient, :status, :error_message, NOW())'
        );
        $log->execute([
            ':applicant_id'  => $applicantId,
            ':email_type'    => $emailType,
            ':recipient'     => $to,
            ':status'        => $status,
            ':error_message' => $errorMessage,
        ]);
    } catch (Throwable $logEx) {
        error_log('email_log insert failed for ' . $to . ': ' . $logEx->getMessage());
    }

    return $status === 'sent';
}
