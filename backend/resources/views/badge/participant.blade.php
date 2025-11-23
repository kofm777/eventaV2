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
            max-width: 480px; /* ← 50% bigger than 320px */
            margin: 0 auto;
            padding: 30px; /* Increased padding for more space */
            border: 2px solid #2563eb;
            border-radius: 12px;
            background: #ffffff;
            display: flex;
            flex-direction: column;
            align-items: center;
            box-shadow: 0 4px 16px rgba(0,0,0,0.1);
        }

        .status-group {
            position: absolute;
            top: 12px;
            right: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
            background: #ffffff;
            padding: 2px 6px;
            border-radius: 6px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            z-index: 10;
        }

        .status-label {
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
            line-height: 1;
        }

        .status-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            border: 2px solid #ffffff;
            box-shadow: 0 1px 2px rgba(0,0,0,0.2);
        }

        .status-accepted         { background-color: #10b981; }
        .status-pending          { background-color: #f59e0b; }
        .status-rejected         { background-color: #ef4444; }
        .status-fair-scanned     { background-color: #f59e0b; }
        .status-conference-scanned { background-color: #93c5fd; }
        .status-both-scanned     { background-color: #8b5cf6; }

        .status-text-accepted         { color: #10b981; }
        .status-text-pending          { color: #f59e0b; }
        .status-text-rejected         { color: #ef4444; }
        .status-text-fair-scanned     { color: #f59e0b; }
        .status-text-conference-scanned { color: #93c5fd; }
        .status-text-both-scanned     { color: #8b5cf6; }

        .header {
            text-align: center;
            margin-top: 30px; /* Space below status group */
            margin-bottom: 20px;
        }

        .event-name {
            font-size: 24px; /* Slightly larger to match bigger container */
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
            font-size: 18px;
            font-weight: bold;
            margin: 4px 0;
        }

        .company-name {
            font-size: 16px;
            color: #4b5563;
            margin-bottom: 6px;
        }

        .access-type {
            display: inline-block;
            background-color: #dbeafe;
            color: #1e40af;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: bold;
            margin: 6px 0;
        }

        .details {
            font-size: 14px;
            color: #6b7280;
            margin: 2px 0;
        }

        .qr-section {
            text-align: center;
            margin-top: 20px;
        }

        .qr-code {
            max-width: 200px;
            max-height: 200px;
            margin-bottom: 6px;
        }

        .qr-label {
            font-size: 12px;
            color: #6b7280;
        }

        .footer {
            text-align: center;
            font-size: 12px;
            color: #9ca3af;
            margin-top: 20px;
        }

        @media screen and (max-width: 500px) {
            .badge-container {
                max-width: 100%;
                padding: 20px;
            }
            .event-name { font-size: 20px; }
            .name { font-size: 16px; }
            .qr-code { max-width: 160px; max-height: 160px; }
        }
    </style>
</head>
<body>
    <div class="badge-container">
        <!-- Status Indicator + Label (Top Right) -->
        <div class="status-group">
            <div class="status-label status-text-{{ $participant->getCurrentBadgeStatusColor() }}">
                {{ $participant->getBadgeStatusLabel() }}
            </div>
            <div class="status-indicator status-{{ $participant->getCurrentBadgeStatusColor() }}"></div>
        </div>

        <!-- Event Name Header (Below Status Group) -->
        <div class="header">
            <h1 class="event-name">{{ $event_name }}</h1>
        </div>

        <!-- Participant Info -->
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
                <strong>Gender:</strong> {{ $participant->gender === 'male' ? 'Mr.' : ($participant->gender === 'female' ? 'Ms.' : 'Other') }}
            </div>
            @if($participant->phone)
                <div class="details"><strong>Phone:</strong> {{ $participant->phone }}</div>
            @endif
        </div>

        <!-- QR Code Section -->
        <div class="qr-section">
            @if($qr_image)
                <img src="data:image/png;base64,{{ $qr_image }}" alt="QR Code" class="qr-code" />
                <div class="qr-label">Access QR Code</div>
            @else
                <div class="qr-label">QR not available</div>
            @endif
        </div>

        <!-- Footer -->
        <div class="footer">
            Generated on {{ $generated_at }}
        </div>
    </div>
</body>
</html>