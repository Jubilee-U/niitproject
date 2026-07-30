<?php

declare(strict_types=1);

/*
 * Email template: "admission declined" — respectful and encouraging, with no
 * sensitive data. Inline styles; the name is escaped.
 */

/**
 * @param string $name Applicant full name.
 */
function renderAdmissionDeclinedEmail(string $name): string
{
    $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>
<body style="margin:0; padding:0; background:#f4f5f7; font-family:Arial, Helvetica, sans-serif; color:#222;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f5f7; padding:24px 0;">
        <tr><td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                   style="max-width:520px; background:#ffffff; border-radius:8px; overflow:hidden;">
                <tr>
                    <td style="background:#2563eb; padding:20px 28px;">
                        <h1 style="margin:0; color:#ffffff; font-size:18px;">School Admissions</h1>
                    </td>
                </tr>
                <tr>
                    <td style="padding:28px;">
                        <p style="margin:0 0 16px; font-size:15px; line-height:1.5;">Dear {$safeName},</p>

                        <p style="margin:0 0 16px; font-size:15px; line-height:1.5;">
                            Thank you for taking the time to apply and for your interest in joining us.
                            After careful consideration, we're sorry to let you know that we're unable to
                            offer a place on this occasion.
                        </p>

                        <p style="margin:0 0 16px; font-size:15px; line-height:1.5;">
                            This decision is not a reflection of your potential. Admissions are limited and
                            highly competitive, and we received many strong applications. We warmly encourage
                            you to apply again in a future intake, and we wish you every success in your
                            educational journey.
                        </p>

                        <p style="margin:0; font-size:15px; line-height:1.5;">
                            With best wishes,<br>The Admissions Team
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="padding:16px 28px; background:#fafafa; border-top:1px solid #eee; font-size:12px; color:#888;">
                        This is an automated message — please do not reply to this email.
                    </td>
                </tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
HTML;
}
