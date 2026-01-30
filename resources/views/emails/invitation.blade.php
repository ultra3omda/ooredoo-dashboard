<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invitation Club Privilèges</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            line-height: 1.6;
            color: #1f2937;
            margin: 0;
            padding: 0;
            background-color: #f8fafc;
        }
        
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .header {
            background: linear-gradient(135deg, #8B5CF6 0%, #7C3AED 100%);
            padding: 40px 30px;
            text-align: center;
            color: white;
        }
        
        .header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: bold;
        }
        
        .header p {
            margin: 10px 0 0 0;
            font-size: 16px;
            opacity: 0.9;
        }
        
        .content {
            padding: 40px 30px;
        }
        
        .greeting {
            font-size: 18px;
            margin-bottom: 20px;
        }
        
        .message {
            margin-bottom: 30px;
            font-size: 16px;
            line-height: 1.7;
        }
        
        .details {
            background-color: #f8fafc;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            border-left: 4px solid #8B5CF6;
        }
        
        .details h3 {
            margin: 0 0 15px 0;
            color: #8B5CF6;
            font-size: 16px;
        }
        
        .details p {
            margin: 5px 0;
            font-size: 14px;
        }
        
        .cta-button {
            display: inline-block;
            background: linear-gradient(135deg, #8B5CF6 0%, #7C3AED 100%);
            color: white;
            padding: 15px 30px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            font-size: 16px;
            margin: 20px 0;
            text-align: center;
            box-shadow: 0 4px 6px rgba(139, 92, 246, 0.2);
        }
        
        .cta-container {
            text-align: center;
            margin: 30px 0;
        }
        
        .instructions {
            background-color: #fef3c7;
            border: 1px solid #f59e0b;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .instructions h4 {
            margin: 0 0 10px 0;
            color: #d97706;
            font-size: 16px;
        }
        
        .instructions ol {
            margin: 10px 0;
            padding-left: 20px;
        }
        
        .instructions li {
            margin: 8px 0;
            font-size: 14px;
        }
        
        .footer {
            background-color: #f8fafc;
            padding: 30px;
            text-align: center;
            border-top: 1px solid #e2e8f0;
        }
        
        .footer p {
            margin: 5px 0;
            font-size: 14px;
            color: #64748b;
        }
        
        .expiry-warning {
            background-color: #fee2e2;
            border: 1px solid #fecaca;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
            text-align: center;
        }
        
        .expiry-warning p {
            margin: 0;
            color: #dc2626;
            font-weight: bold;
            font-size: 14px;
        }
        
        @media (max-width: 600px) {
            .container {
                margin: 0;
                border-radius: 0;
            }
            
            .header, .content, .footer {
                padding: 20px;
            }
            
            .header h1 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Club Privilèges</h1>
            <p>Dashboard Administrateur</p>
        </div>
        
        <div class="content">
            <div class="greeting">
                Bonjour {{ $invitation->first_name }} {{ $invitation->last_name }},
            </div>
            
            <div class="message">
                <p>Vous avez été invité(e) à rejoindre la plateforme <strong>Club Privilèges Dashboard</strong> par <strong>{{ $invitation->invitedBy->name ?? 'un administrateur' }}</strong>.</p>
                
                @if($invitation->additional_data && isset($invitation->additional_data['message']))
                    <div class="details">
                        <h3>Message personnel :</h3>
                        <p>{{ $invitation->additional_data['message'] }}</p>
                    </div>
                @endif
            </div>
            
            <div class="details">
                <h3>Détails de votre compte :</h3>
                <p><strong>Rôle :</strong> {{ $invitation->role->display_name ?? 'Non défini' }}</p>
                <p><strong>Opérateur assigné :</strong> {{ $invitation->operator_name }}</p>
                <p><strong>Adresse e-mail :</strong> {{ $invitation->email }}</p>
            </div>
            
            <div class="instructions">
                <h4>📋 Comment procéder :</h4>
                <ol>
                    <li>Cliquez sur le bouton ci-dessous pour accepter l'invitation</li>
                    <li>Vous recevrez un code de vérification à 6 chiffres par email</li>
                    <li>Saisissez ce code pour confirmer votre identité</li>
                    <li>Votre compte sera automatiquement créé et vous serez connecté</li>
                </ol>
            </div>
            
            <div class="cta-container">
                <a href="{{ $invitationUrl }}" class="cta-button">
                    🚀 Accepter l'invitation
                </a>
            </div>
            
            <div class="expiry-warning">
                <p>⚠️ Cette invitation expire le {{ $invitation->expires_at->format('d/m/Y à H:i') }}</p>
            </div>
            
            <div class="message">
                <p><strong>Note de sécurité :</strong> Si vous n'avez pas demandé cette invitation ou si vous ne connaissez pas l'expéditeur, veuillez ignorer cet email.</p>
            </div>
        </div>
        
        <div class="footer">
            <p><strong>Club Privilèges</strong></p>
            <p>Dashboard Administrateur</p>
            <p style="margin-top: 15px;">
                Si vous avez des questions, contactez-nous à : 
                <a href="mailto:{{ config('mail.from_address') }}" style="color: #8B5CF6;">{{ config('mail.from_address') }}</a>
            </p>
        </div>
    </div>
</body>
</html>
