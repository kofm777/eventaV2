<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify your email</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f7;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <div style="max-width:560px;margin:0 auto;padding:24px;">
        <h1 style="font-size:20px;margin:0 0 16px;">Verify your email</h1>

        <p style="font-size:14px;line-height:1.6;margin:0 0 20px;">
            Thanks for creating an account. Please confirm your email address by clicking the
            button below. This link expires in 48 hours.
        </p>

        <div style="border:1px solid #e5e7eb;border-radius:8px;padding:16px;margin:0 0 14px;background:#ffffff;">
            <a href="{{ $verifyUrl }}"
               style="display:inline-block;padding:10px 18px;background:#2563eb;color:#ffffff;
                      text-decoration:none;border-radius:6px;font-size:14px;font-weight:bold;">
                Verify my email
            </a>
            <p style="font-size:12px;margin:10px 0 0;color:#9ca3af;word-break:break-all;">
                {{ $verifyUrl }}
            </p>
        </div>

        <p style="font-size:12px;line-height:1.6;color:#9ca3af;margin:20px 0 0;">
            If you did not create this account you can safely ignore this email.
        </p>
    </div>
</body>
</html>
