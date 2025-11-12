<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Access</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #2563eb;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        .content {
            background-color: #f8fafc;
            padding: 30px;
            border-radius: 0 0 8px 8px;
        }
        .status-pending {
            background-color: #fbbf24;
            color: #92400e;
            padding: 10px;
            border-radius: 4px;
            margin: 20px 0;
            text-align: center;
        }
        .status-accepted {
            background-color: #10b981;
            color: white;
            padding: 10px;
            border-radius: 4px;
            margin: 20px 0;
            text-align: center;
        }
        .status-rejected {
            background-color: #ef4444;
            color: white;
            padding: 10px;
            border-radius: 4px;
            margin: 20px 0;
            text-align: center;
        }
        .qr-container {
            text-align: center;
            margin: 30px 0;
            padding: 20px;
            background-color: white;
            border-radius: 8px;
            border: 2px dashed #d1d5db;
        }
        .participant-info {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            font-size: 14px;
            color: #6b7280;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Event Access</h1>
        <p>Event access management system</p>
    </div>

    <div class="content">
        <h2>Hello {{ $participant->first_name }} {{ $participant->last_name }},</h2>

        @if($emailType === 'deleted')
            <div class="status-rejected">
                <strong>Registration cancelled</strong>
            </div>
            <p>We inform you that your registration for the event has been cancelled by the administrator.</p>
            <p>If you believe this is a mistake, feel free to contact us.</p>
        @elseif($participant->status === 'pending')
            <div class="status-pending">
                <strong>Your access request is pending validation</strong>
            </div>
            <p>We have received your registration for the event. Your request is currently being reviewed by our team.</p>
            <p>You will receive a new email once your registration is validated.</p>
        @elseif($participant->status === 'accepted')
            <div class="status-accepted">
                <strong>Access confirmed — Welcome!</strong>
            </div>
            <p>Congratulations! Your registration has been accepted. You can now access the event.</p>

            @if($qrImage)
                <div class="qr-container">
                    <h3>Your access QR Code</h3>
                    <img src="data:image/png;base64,{{ $qrImage }}" alt="Access QR Code" style="max-width: 300px;">
                    <p><small>Show this QR code at the event entrance</small></p>
                </div>
            @endif
        @elseif($participant->status === 'rejected')
            <div class="status-rejected">
                <strong>Access denied</strong>
            </div>
            <p>We regret to inform you that your access request could not be accepted.</p>
            <p>If you believe this is a mistake, feel free to contact us.</p>
        @endif

        <div class="participant-info">
            <h3>Your registration information</h3>
            <ul>
                <li><strong>Name:</strong> {{ $participant->first_name }} {{ $participant->last_name }}</li>
                <li><strong>Company Name:</strong> {{ $participant->company_name }}</li>
                <li><strong>Email:</strong> {{ $participant->email }}</li>
                <li><strong>Access Type:</strong>
                    @if($participant->access_type === 'foire')
                        Fair only
                    @elseif($participant->access_type === 'conference')
                        Conference only
                    @else
                        Fair and Conference
                    @endif
                </li>
                <li><strong>Status:</strong>
                    @if($participant->status === 'pending')
                        Pending
                    @elseif($participant->status === 'accepted')
                        Accepted
                    @else
                        Denied
                    @endif
                </li>
            </ul>
        </div>

        @if($participant->status === 'accepted')
            <p><strong>Important instructions:</strong></p>
            <ul>
                <li>Keep this QR code safe</li>
                <li>Show it at the event entrance</li>
                <li>Make sure your phone is charged</li>
                <li>You can also print this QR code</li>
            </ul>
        @endif
    </div>

    <div class="footer">
        <p>Event Access - Event management system</p>
        <p>This email was sent automatically, please do not reply.</p>
    </div>
</body>
</html>
