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

        .status-pending {
            background-color: #fef3c7;
            color: #b45309;
        }

        .status-accepted {
            background-color: #d1fae5;
            color: #065f46;
        }

        .status-rejected {
            background-color: #fee2e2;
            color: #b91c1c;
        }

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

        .participant-info ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .participant-info li {
            margin-bottom: 8px;
        }

        .instructions {
            margin-top: 15px;
            padding: 15px;
            background: #eef2ff;
            border-radius: 12px;
            font-size: 14px;
        }

        .footer {
            text-align: center;
            font-size: 12px;
            color: #6b7280;
            padding: 15px;
        }

        @media screen and (max-width: 640px) {
            .container {
                margin: 10px;
            }

            .header h1 {
                font-size: 20px;
            }
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

            {{-- Status messages --}}
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
                        <button onclick="printBadge()" style="margin-top:10px;padding:10px 20px;background:#2563eb;color:white;border:none;border-radius:6px;cursor:pointer;">
                                    Print Badge
                                </button>
                    </div>
                 <script>
                        function printBadge() {
                            const qrImg = document.getElementById('qr-code-img');
                            if (!qrImg) return;

                            const printWindow = window.open('', '_blank');
                            printWindow.document.write(`
                                <html>
                                <head>
                                    <title>Badge - Event Access</title>
                                    <style>
                                        body {
                                            font-family: Arial, sans-serif;
                                            display: flex;
                                            justify-content: center;
                                            align-items: center;
                                            height: 100vh;
                                            margin: 0;
                                            background: #f9fafb;
                                        }
                                        .badge-container {
                                            border: 2px solid #2563eb;
                                            border-radius: 12px;
                                            padding: 30px;
                                            text-align: center;
                                            background: white;
                                        }
                                        .badge-container h2 {
                                            color: #2563eb;
                                            margin-bottom: 15px;
                                        }
                                        .badge-container img {
                                            max-width: 250px;
                                            height: auto;
                                            margin-bottom: 15px;
                                        }
                                        .instructions {
                                            font-size: 14px;
                                            color: #333;
                                        }
                                    </style>
                                </head>
                                <body>
                                    <div class="badge-container">
                                        <h2>Event Access Badge</h2>
                                        <p><strong>{{ $participant->first_name }} {{ $participant->last_name }}</strong></p>
                                        <img src="${qrImg.src}" alt="QR Code">
                                        <div class="instructions">
                                            Show this QR code at the event entrance.<br>
                                            Access Type: {{ $participant->access_type === 'fair' ? 'Fair only' : ($participant->access_type === 'conference' ? 'Conference only' : 'Fair and Conference') }}
                                        </div>
                                    </div>
                                </body>
                                </html>
                            `);
                            printWindow.document.close();
                            printWindow.print();
                        }
                    </script>
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

            {{-- Instructions for accepted participants --}}
            @if($participant->status === 'accepted')
                <div class="instructions">
                    <p><strong>Important Instructions:</strong></p>
                    <ul>
                        <li>Keep this QR code safe</li>
                        <li>Show it at the event entrance</li>
                        <li>Ensure your phone is charged</li>
                        <li>You can also print this QR code</li>
                    </ul>
                </div>
            @endif
        </div>

        <div class="footer">
            <p>Event Access — Event management system</p>
            <p>This email was sent automatically, please do not reply.</p>
        </div>
    </div>
</body>
</html>
