<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>Verify your email</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f3f8f6; color: #17312d; font-family: Arial, Helvetica, sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color: #f3f8f6; padding: 40px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width: 560px; background-color: #ffffff; border: 1px solid #dcebe6; border-radius: 16px; overflow: hidden;">
                    <tr>
                        <td style="padding: 28px 36px; background-color: #eff8f5; border-bottom: 1px solid #dcebe6;">
                            <p style="margin: 0; color: #17312d; font-size: 24px; font-weight: 700; letter-spacing: -0.5px;">
                                quick<span style="color: #00695c;">Folio</span>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 36px;">
                            <p style="margin: 0 0 12px; color: #00695c; font-size: 12px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase;">
                                One last step
                            </p>
                            <h1 style="margin: 0 0 16px; color: #17312d; font-size: 28px; line-height: 1.3;">
                                Verify your email
                            </h1>
                            <p style="margin: 0 0 24px; color: #52635f; font-size: 16px; line-height: 1.6;">
                                @if ($recipientName)
                                    Hi {{ $recipientName }},
                                @else
                                    Hi,
                                @endif
                                click below to confirm your email address and secure your quickFolio account.
                            </p>
                            <table role="presentation" cellspacing="0" cellpadding="0" style="margin: 0 0 24px;">
                                <tr>
                                    <td align="center" style="border-radius: 10px; background-color: #00695c;">
                                        <a href="{{ $url }}" style="display: inline-block; padding: 14px 24px; border: 1px solid #00695c; border-radius: 10px; color: #ffffff; font-size: 15px; font-weight: 700; text-decoration: none;">
                                            Verify email address
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin: 0 0 8px; color: #52635f; font-size: 14px; line-height: 1.6;">
                                If the button doesn't work, copy and paste this link into your browser:
                            </p>
                            <p style="margin: 0; overflow-wrap: anywhere; color: #00695c; font-size: 13px; line-height: 1.6;">
                                <a href="{{ $url }}" style="color: #00695c; overflow-wrap: anywhere;">{{ $url }}</a>
                            </p>
                            <p style="margin: 24px 0 0; color: #7a8985; font-size: 13px; line-height: 1.6;">
                                If you didn't create a quickFolio account, you can safely ignore this email.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 20px 36px; border-top: 1px solid #e7efec; color: #7a8985; font-size: 12px; line-height: 1.5;">
                            Secure access, built into quickFolio.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
