<?php

declare(strict_types=1);

/*
 * Email template: "password reset".
 * Inline styles (email clients strip <style>). The name is escaped; the reset
 * URL is escaped for use in both href and visible text.
 */

/**
 * @param string $name     Recipient's display name.
 * @param string $resetUrl Absolute reset URL containing the plaintext token.
 */
function renderPasswordResetEmail(string $name, string $resetUrl): string
{
    $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $safeUrl  = htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8');

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
                    <td style="background:#0F2444; padding:20px 28px;">
                        <h1 style="margin:0; color:#ffffff; font-size:18px;">Bright House College</h1>
                    </td>
                </tr>
                <tr>
                    <td style="padding:28px;">
                        <p style="margin:0 0 16px; font-size:15px; line-height:1.5;">Dear {$safeName},</p>

                        <p style="margin:0 0 16px; font-size:15px; line-height:1.5;">
                            We received a request to reset the password for your account. Click the button
                            below to choose a new password. <strong>This link expires in 1 hour.</strong>
                        </p>

                        <p style="margin:0 0 24px;">
                            <a href="{$safeUrl}"
                               style="display:inline-block; background:#1E4380; color:#ffffff; text-decoration:none;
                                      padding:11px 22px; border-radius:6px; font-size:15px;">Reset my password</a>
                        </p>

                        <p style="margin:0 0 16px; font-size:14px; line-height:1.5; color:#555;">
                            If you didn't request this, you can safely ignore this email — your password
                            won't change until you use the link above.
                        </p>

                        <p style="margin:0; font-size:15px; line-height:1.5;">
                            Regards,<br>The Admissions Team
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="padding:16px 28px; background:#fafafa; border-top:1px solid #eee; font-size:12px; color:#888;">
                        If the button doesn't work, copy this link into your browser:<br>{$safeUrl}
                    </td>
                </tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
HTML;
}
