<?php

declare(strict_types=1);

/*
 * Email template: "admission accepted" — contains login credentials.
 * All styling is inline (email clients strip <style>). All interpolated values
 * are escaped since names and generated strings go into HTML.
 */

/**
 * @param string $name         Applicant/student full name.
 * @param string $loginEmail   School-issued login email (what the student signs in with).
 * @param string $tempPassword Generated temporary password (plaintext, shown once).
 * @param string $loginUrl     Absolute URL of the login page.
 */
function renderAdmissionAcceptedEmail(string $name, string $loginEmail, string $tempPassword, string $loginUrl): string
{
    $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $safeUser = htmlspecialchars($loginEmail, ENT_QUOTES, 'UTF-8');
    $safePass = htmlspecialchars($tempPassword, ENT_QUOTES, 'UTF-8');
    $safeUrl  = htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8');

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
                    <td style="background:#16a34a; padding:20px 28px;">
                        <h1 style="margin:0; color:#ffffff; font-size:18px;">Congratulations!</h1>
                    </td>
                </tr>
                <tr>
                    <td style="padding:28px;">
                        <p style="margin:0 0 16px; font-size:15px; line-height:1.5;">Dear {$safeName},</p>

                        <p style="margin:0 0 16px; font-size:15px; line-height:1.5;">
                            We're delighted to let you know that your application has been
                            <strong>accepted</strong>. Welcome aboard! An account has been created for
                            you so you can log in and complete the next steps.
                        </p>

                        <p style="margin:0 0 8px; font-size:15px; line-height:1.5;">Your login details:</p>

                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                               style="margin:8px 0 20px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:6px;">
                            <tr><td style="padding:14px 16px; font-size:15px;">
                                <div style="margin-bottom:6px;">
                                    <span style="color:#555;">Login email:</span>
                                    <strong style="font-family:'Courier New', Courier, monospace;">{$safeUser}</strong>
                                </div>
                                <div>
                                    <span style="color:#555;">Temporary password:</span>
                                    <strong style="font-family:'Courier New', Courier, monospace;">{$safePass}</strong>
                                </div>
                            </td></tr>
                        </table>

                        <p style="margin:0 0 20px; font-size:15px; line-height:1.5;">
                            <strong>For your security, you'll be asked to change this password the first
                            time you log in.</strong> Please don't share these details with anyone.
                        </p>

                        <p style="margin:0 0 24px;">
                            <a href="{$safeUrl}"
                               style="display:inline-block; background:#2563eb; color:#ffffff; text-decoration:none;
                                      padding:11px 22px; border-radius:6px; font-size:15px;">Log in</a>
                        </p>

                        <p style="margin:0; font-size:15px; line-height:1.5;">
                            Warm regards,<br>The Admissions Team
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="padding:16px 28px; background:#fafafa; border-top:1px solid #eee; font-size:12px; color:#888;">
                        If the button doesn't work, copy this link into your browser: {$safeUrl}
                    </td>
                </tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
HTML;
}
