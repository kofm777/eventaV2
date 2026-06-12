<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset your password</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f7;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <div style="max-width:560px;margin:0 auto;padding:24px;">
        <h1 style="font-size:20px;margin:0 0 16px;">Reset your password</h1>

        <p style="font-size:14px;line-height:1.6;margin:0 0 20px;">
            We received a request to reset the password for your
            {{ ($audience ?? '') === 'admin' ? 'admin' : 'attendee' }} account.
            Click the button below to choose a new password. This link expires in 60 minutes.
        </p>

        <div style="border:1px solid #e5e7eb;border-radius:8px;padding:16px;margin:0 0 14px;background:#ffffff;">
            <a href="{{ $resetUrl }}"
               style="display:inline-block;padding:10px 18px;background:#2563eb;color:#ffffff;
                      text-decoration:none;border-radius:6px;font-size:14px;font-weight:bold;">
                Reset my password
            </a>
            <p style="font-size:12px;margin:10px 0 0;color:#9ca3af;word-break:break-all;">
                {{ $resetUrl }}
            </p>
        </div>

        <p style="font-size:12px;line-height:1.6;color:#9ca3af;margin:20px 0 0;">
            If you did not request a password reset you can safely ignore it. Your password will stay unchanged.
        </p>
    </div>
</body>
</html>
