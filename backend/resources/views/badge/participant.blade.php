<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Badge - {{ $participant->full_name }}</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 10px;
            background-color: #ffffff;
            color: #333;
            font-size: 12px;
            line-height: 1.2;
        }

        .badge-container {
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            border: 2px solid #2563eb;
            border-radius: 8px;
            padding: 8px;
            background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
        }

        .header {
            text-align: center;
            margin-bottom: 8px;
        }

        .event-name {
            font-size: 14px;
            font-weight: bold;
            color: #2563eb;
            margin: 0;
            text-transform: uppercase;
        }

        .participant-info {
            text-align: center;
            margin-bottom: 8px;
        }

        .name {
            font-size: 16px;
            font-weight: bold;
            margin: 4px 0;
            color: #1f2937;
        }

        .details {
            font-size: 10px;
            color: #6b7280;
            margin: 2px 0;
        }

        .access-type {
            display: inline-block;
            background-color: #dbeafe;
            color: #1e40af;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: bold;
            margin: 4px 0;
        }

        .qr-section {
            text-align: center;
            margin-top: auto;
        }

        .qr-code {
            max-width: 80px;
            max-height: 80px;
            margin: 4px 0;
        }

        .qr-label {
            font-size: 8px;
            color: #6b7280;
            margin-top: 2px;
        }

        .footer {
            text-align: center;
            font-size: 8px;
            color: #9ca3af;
            margin-top: 4px;
        }

        .status-indicator {
            position: absolute;
            top: 8px;
            right: 8px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            border: 2px solid #ffffff;
        }

        .status-accepted {
            background-color: #10b981;
        }

        .status-pending {
            background-color: #f59e0b;
        }

        .status-rejected {
            background-color: #ef4444;
        }

        .badge-container {
            position: relative;
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
            <div class="name">{{ $participant->first_name }}</div>
            <div class="name">{{ $participant->last_name }}</div>
            <div class="name">{{ $participant->company_name }}</div>

            <div class="access-type">
                @if($participant->access_type === 'foire')
                    Foire
                @elseif($participant->access_type === 'conference')
                    Conférence
                @else
                    Foire + Conférence
                @endif
            </div>

            <div class="details">
                <strong>Genre:</strong> {{ $participant->gender === 'Homme' ? 'M.' : ($participant->gender === 'Femme' ? 'Mme' : 'Autre') }}
            </div>

            @if($participant->phone)
                <div class="details">
                    <strong>Tél:</strong> {{ $participant->phone }}
                </div>
            @endif
        </div>

        <div class="qr-section">
            @if($qr_image)
                <img src="data:image/png;base64,{{ $qr_image }}" alt="QR Code" class="qr-code" />
                <div class="qr-label">Code d'accès</div>
            @else
                <div class="qr-label">QR non disponible</div>
            @endif
        </div>

        <div class="footer">
            Généré le {{ $generated_at }}
        </div>
    </div>
</body>
</html>
