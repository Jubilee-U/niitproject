<?php

declare(strict_types=1);

/*
 * Email template: "application received".
 *
 * Returns a self-contained HTML string. Email clients strip <style> blocks and
 * external CSS unpredictably, so all styling is INLINE. Values are escaped
 * because a name is user-supplied data going into HTML.
 */

/**
 * Build the confirmation email body.
 *
 * @param string $name        Applicant's full name.
 * @param string $referenceNo The generated reference number.
 * @return string             Complete HTML email body.
 */
function renderApplicationReceivedEmail(string $name, string $referenceNo): string
{
    $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $safeRef  = htmlspecialchars($referenceNo, ENT_QUOTES, 'UTF-8');

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>
<body style="margin:0; padding:0; background:#f4f5f7; font-family:Arial, Helvetica, sans-serif; color:#222;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f5f7; padding:24px 0;">
        <tr>
            <td align="center">
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
                                Thank you for your application. We're glad to let you know that we've
                                received it successfully, and it's now with our admissions team for review.
                            </p>

                            <p style="margin:0 0 8px; font-size:15px; line-height:1.5;">
                                Your application reference number is:
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:8px 0 20px;">
                                <tr>
                                    <td align="center"
                                        style="background:#eef2ff; border:1px solid #c7d2fe; border-radius:6px;
                                               padding:16px; font-size:22px; font-weight:bold; letter-spacing:2px;
                                               font-family:'Courier New', Courier, monospace; color:#1e3a8a;">
                                        {$safeRef}
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0 0 16px; font-size:15px; line-height:1.5;">
                                <strong>Please save this reference number.</strong> You'll need it to check the
                                status of your application later.
                            </p>

                            <p style="margin:0; font-size:15px; line-height:1.5;">
                                Warm regards,<br>
                                The Admissions Team
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 28px; background:#fafafa; border-top:1px solid #eee;
                                   font-size:12px; color:#888;">
                            This is an automated message — please do not reply to this email.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
}
