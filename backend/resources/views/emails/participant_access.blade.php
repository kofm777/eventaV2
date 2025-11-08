<!DOCTYPE html>
<html lang="fr">
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
        <p>Système de gestion d'accès aux événements</p>
    </div>

    <div class="content">
        <h2>Bonjour {{ $participant->first_name }} {{ $participant->last_name }},</h2>

        @if($participant->status === 'pending')
            <div class="status-pending">
                <strong>Votre demande d'accès est en attente de validation</strong>
            </div>
            <p>Nous avons bien reçu votre inscription pour l'événement. Votre demande est actuellement en cours d'examen par notre équipe.</p>
            <p>Vous recevrez un nouvel email dès que votre inscription sera validée.</p>
        @elseif($participant->status === 'accepted')
            <div class="status-accepted">
                <strong>Accès confirmé — Bienvenue !</strong>
            </div>
            <p>Félicitations ! Votre inscription a été acceptée. Vous pouvez maintenant accéder à l'événement.</p>
            
            @if($qrImage)
                <div class="qr-container">
                    <h3>Votre QR Code d'accès</h3>
                    <img src="data:image/png;base64,{{ $qrImage }}" alt="QR Code d'accès" style="max-width: 300px;">
                    <p><small>Présentez ce QR code à l'entrée de l'événement</small></p>
                </div>
            @endif
        @elseif($participant->status === 'rejected')
            <div class="status-rejected">
                <strong>Accès refusé</strong>
            </div>
            <p>Nous regrettons de vous informer que votre demande d'accès n'a pas pu être acceptée.</p>
            <p>Si vous pensez qu'il s'agit d'une erreur, n'hésitez pas à nous contacter.</p>
        @endif

        <div class="participant-info">
            <h3>Informations de votre inscription</h3>
            <ul>
                <li><strong>Nom :</strong> {{ $participant->first_name }} {{ $participant->last_name }}</li>
                <li><strong>Email :</strong> {{ $participant->email }}</li>
                <li><strong>Type d'accès :</strong> 
                    @if($participant->access_type === 'foire')
                        Foire uniquement
                    @elseif($participant->access_type === 'conference')
                        Conférence uniquement
                    @else
                        Foire et Conférence
                    @endif
                </li>
                <li><strong>Statut :</strong> 
                    @if($participant->status === 'pending')
                        En attente
                    @elseif($participant->status === 'accepted')
                        Accepté
                    @else
                        Refusé
                    @endif
                </li>
            </ul>
        </div>

        @if($participant->status === 'accepted')
            <p><strong>Instructions importantes :</strong></p>
            <ul>
                <li>Conservez ce QR code précieusement</li>
                <li>Présentez-le à l'entrée de l'événement</li>
                <li>Assurez-vous que votre téléphone est chargé</li>
                <li>Vous pouvez également imprimer ce QR code</li>
            </ul>
        @endif
    </div>

    <div class="footer">
        <p>Event Access - Système de gestion d'événements</p>
        <p>Cet email a été envoyé automatiquement, merci de ne pas y répondre.</p>
    </div>
</body>
</html>
