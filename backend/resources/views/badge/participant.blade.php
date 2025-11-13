<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Badge - {{ $participant->full_name }}</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 10px;
            background-color: #f9fafb;
            color: #1f2937;
            line-height: 1.2;
        }

        .badge-container {
            position: relative;
            max-width: 320px;
            margin: 0 auto;
            padding: 20px;
            border: 2px solid #2563eb;
            border-radius: 12px;
            background: #ffffff;
            display: flex;
            flex-direction: column;
            align-items: center;
            box-shadow: 0 4px 16px rgba(0,0,0,0.1);
        }

        .status-indicator {
            position: absolute;
            top: 12px;
            right: 12px;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            border: 2px solid #ffffff;
        }
        .status-accepted { background-color: #10b981; }
        .status-pending  { background-color: #f59e0b; }
        .status-rejected { background-color: #ef4444; }

        .header {
            text-align: center;
            margin-bottom: 15px;
        }
        .event-name {
            font-size: 20px;
            font-weight: bold;
            color: #2563eb;
            text-transform: uppercase;
            margin: 0;
        }

        .participant-info {
            text-align: center;
            margin-bottom: 20px;
        }
        .name {
            font-size: 16px;
            font-weight: bold;
            margin: 4px 0;
        }
        .company-name {
            font-size: 14px;
            color: #4b5563;
            margin-bottom: 6px;
        }
        .access-type {
            display: inline-block;
            background-color: #dbeafe;
            color: #1e40af;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: bold;
            margin: 6px 0;
        }
        .details {
            font-size: 12px;
            color: #6b7280;
            margin: 2px 0;
        }

        .qr-section {
            text-align: center;
            margin-top: 10px;
        }
        .qr-code {
            max-width: 160px;
            max-height: 160px;
            margin-bottom: 6px;
        }
        .qr-label {
            font-size: 10px;
            color: #6b7280;
        }

        .footer {
            text-align: center;
            font-size: 10px;
            color: #9ca3af;
            margin-top: 15px;
        }

        @media screen and (max-width: 360px) {
            .badge-container { padding: 15px; }
            .event-name { font-size: 18px; }
            .name { font-size: 14px; }
            .qr-code { max-width: 140px; max-height: 140px; }
        }
    </style>
</head>
<body>
    <div class="badge-container">
        <div class="status-indicator status-{{ $participant->status }}"></div>

        <div class="header">
            <h1 class="event-name">{{ $event_name }}</h1>
        </div>

        <div class="participant-info">
            <div class="name">{{ $participant->first_name }} {{ $participant->last_name }}</div>
            <div class="company-name">{{ $participant->company_name }}</div>

            <div class="access-type">
                @if($participant->access_type === 'fair')
                    Fair only
                @elseif($participant->access_type === 'conference')
                    Conference only
                @else
                    Fair + Conference
                @endif
            </div>

            <div class="details">
                <strong>Gender:</strong> {{ $participant->gender === 'Homme' ? 'Mr.' : ($participant->gender === 'Femme' ? 'Ms.' : 'Other') }}
            </div>
            @if($participant->phone)
                <div class="details"><strong>Phone:</strong> {{ $participant->phone }}</div>
            @endif
        </div>

        <div class="qr-section">
            @if($qr_image)
                <img src="data:image/png;base64,{{ $qr_image }}" alt="QR Code" class="qr-code" />
                <div class="qr-label">Access QR Code</div>
            @else
                <div class="qr-label">QR not available</div>
            @endif
        </div>

        <div class="footer">
            Generated on {{ $generated_at }}
        </div>
    </div>
</body>
</html>
