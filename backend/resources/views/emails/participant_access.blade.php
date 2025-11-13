<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Access</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            line-height: 1.6;
            color: #1f2937;
            background-color: #f9fafb;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 8px 24px rgba(0,0,0,0.1);
        }
        .header {
            background-color: #2563eb;
            color: #fff;
            padding: 25px 20px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .header p {
            margin: 5px 0 0 0;
            font-size: 14px;
        }
        .content {
            padding: 25px 20px;
        }
        .status {
            text-align: center;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            font-weight: bold;
            font-size: 16px;
        }
        .status-pending { background-color: #fef3c7; color: #b45309; }
        .status-accepted { background-color: #d1fae5; color: #065f46; }
        .status-rejected { background-color: #fee2e2; color: #b91c1c; }

        .qr-container {
            text-align: center;
            margin: 25px 0;
            padding: 20px;
            background: #f3f4f6;
            border-radius: 12px;
            border: 2px dashed #d1d5db;
        }
        .qr-container img {
            max-width: 200px;
            height: auto;
            margin-bottom: 10px;
        }
        .participant-info {
            background: #f9fafb;
            padding: 20px;
            border-radius: 12px;
            margin: 20px 0;
        }
        .participant-info ul { list-style: none; padding: 0; margin: 0; }
        .participant-info li { margin-bottom: 8px; }

        .footer {
            text-align: center;
            font-size: 12px;
            color: #6b7280;
            padding: 15px;
        }

        @media screen and (max-width: 640px) {
            .container { margin: 10px; }
            .header h1 { font-size: 20px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Event Access</h1>
            <p>Event access management system</p>
        </div>

        <div class="content">
            <h2>Hello {{ $participant->first_name }} {{ $participant->last_name }},</h2>

            {{-- Status message --}}
            @if($emailType === 'deleted')
                <div class="status status-rejected">Registration cancelled</div>
                <p>Your registration for the event has been cancelled by the administrator.</p>
            @elseif($participant->status === 'pending')
                <div class="status status-pending">Your access request is pending validation</div>
                <p>Your registration is under review. You will receive an email once validated.</p>
            @elseif($participant->status === 'accepted')
                <div class="status status-accepted">Access confirmed — Welcome!</div>
                <p>Congratulations! Your registration has been accepted.</p>

                @if($qrImage)
                    <div class="qr-container">
                        <h3>Your Access QR Code</h3>
                        <img src="data:image/png;base64,{{ $qrImage }}" alt="Access QR Code">
                        <p><small>Show this QR code at the event entrance</small></p>
                    </div>
                @endif
            @elseif($participant->status === 'rejected')
                <div class="status status-rejected">Access denied</div>
                <p>We regret to inform you that your registration could not be accepted.</p>
            @endif

            {{-- Participant info --}}
            <div class="participant-info">
                <h3>Your Registration Information</h3>
                <ul>
                    <li><strong>Name:</strong> {{ $participant->first_name }} {{ $participant->last_name }}</li>
                    <li><strong>Company Name:</strong> {{ $participant->company_name }}</li>
                    <li><strong>Email:</strong> {{ $participant->email }}</li>
                    <li><strong>Access Type:</strong>
                        @if($participant->access_type === 'fair')
                            Fair only
                        @elseif($participant->access_type === 'conference')
                            Conference only
                        @else
                            Fair and Conference
                        @endif
                    </li>
                    <li><strong>Status:</strong> {{ ucfirst($participant->status) }}</li>
                </ul>
            </div>
        </div>

        <div class="footer">
            <p>Event Access — Event management system</p>
            <p>This email was sent automatically, please do not reply.</p>
        </div>
    </div>
</body>
</html>
