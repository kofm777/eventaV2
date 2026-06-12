<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your ticket link(s)</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f7;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <div style="max-width:560px;margin:0 auto;padding:24px;">
        <h1 style="font-size:20px;margin:0 0 16px;">Here are your ticket links</h1>

        <p style="font-size:14px;line-height:1.6;margin:0 0 20px;">
            We found the purchase(s) below tied to your email. Open the link for each order to
            view your QR ticket and download your badge. No login is required.
        </p>

        @foreach ($links as $link)
            <div style="border:1px solid #e5e7eb;border-radius:8px;padding:16px;margin:0 0 14px;background:#ffffff;">
                <p style="font-size:13px;margin:0 0 4px;color:#6b7280;">
                    Order {{ $link['order_number'] }}@if (!empty($link['event_name'])) &middot; {{ $link['event_name'] }}@endif
                </p>
                <a href="{{ $link['url'] }}"
                   style="display:inline-block;margin-top:8px;padding:10px 18px;background:#2563eb;color:#ffffff;
                          text-decoration:none;border-radius:6px;font-size:14px;font-weight:bold;">
                    View my ticket
                </a>
                <p style="font-size:12px;margin:10px 0 0;color:#9ca3af;word-break:break-all;">
                    {{ $link['url'] }}
                </p>
            </div>
        @endforeach

        <p style="font-size:12px;line-height:1.6;color:#9ca3af;margin:20px 0 0;">
            If you did not request this email you can safely ignore it.
        </p>
    </div>
</body>
</html>
