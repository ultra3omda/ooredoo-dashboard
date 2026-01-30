@php
    $theme = $theme ?? 'club_privileges';
    $isOoredoo = $theme === 'ooredoo';
    $brandName = $isOoredoo ? 'Ooredoo Privileges' : 'Club Privilèges';
    $primaryColor = $isOoredoo ? '#E30613' : '#8B5CF6';
    $secondaryColor = $isOoredoo ? '#DC2626' : '#7C3AED';
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Code de vérification {{ $brandName }}</title>
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
            background: linear-gradient(135deg, {{ $primaryColor }} 0%, {{ $secondaryColor }} 100%);
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
            text-align: center;
        }
        
        .icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            color: white;
            font-size: 40px;
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
        
        .otp-container {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            border: 3px solid {{ $primaryColor }};
            border-radius: 12px;
            padding: 30px;
            margin: 30px 0;
            text-align: center;
        }
        
        .otp-label {
            font-size: 14px;
            color: #64748b;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: bold;
        }
        
        .otp-code {
            font-size: 48px;
            font-weight: bold;
            color: {{ $primaryColor }};
            letter-spacing: 8px;
            font-family: 'Courier New', monospace;
            margin: 10px 0;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
        }
        
        .otp-info {
            font-size: 14px;
            color: #64748b;
            margin-top: 15px;
        }
        
        .instructions {
            background-color: #fef3c7;
            border: 1px solid #f59e0b;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            text-align: left;
        }
        
        .instructions h4 {
            margin: 0 0 15px 0;
            color: #d97706;
            font-size: 16px;
            text-align: center;
        }
        
        .instructions ol {
            margin: 10px 0;
            padding-left: 20px;
        }
        
        .instructions li {
            margin: 8px 0;
            font-size: 14px;
        }
        
        .security-notice {
            background-color: #fee2e2;
            border: 1px solid #fecaca;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .security-notice h4 {
            margin: 0 0 10px 0;
            color: #dc2626;
            font-size: 16px;
        }
        
        .security-notice p {
            margin: 0;
            font-size: 14px;
            color: #7f1d1d;
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
        
        .expiry-info {
            background-color: #dbeafe;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
            text-align: center;
        }
        
        .expiry-info p {
            margin: 0;
            color: #1d4ed8;
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
            
            .otp-code {
                font-size: 36px;
                letter-spacing: 4px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{ $brandName }}</h1>
            <p>{{ $isInvitation ? 'Confirmation d\'invitation' : 'Code de connexion' }}</p>
        </div>
        
        <div class="content">
            <div class="icon">
                {{ $isInvitation ? '✉️' : '🔐' }}
            </div>
            
            @if($userName)
                <div class="greeting">
                    Bonjour {{ $userName }},
                </div>
            @else
                <div class="greeting">
                    Bonjour,
                </div>
            @endif
            
            <div class="message">
                @if($isInvitation)
                    <p>Voici votre code de vérification pour confirmer votre invitation à rejoindre le dashboard {{ $brandName }} :</p>
                @else
                    <p>Voici votre code de vérification pour vous connecter au dashboard {{ $brandName }} :</p>
                @endif
            </div>
            
            <div class="otp-container">
                <div class="otp-label">Code de vérification</div>
                <div class="otp-code">{{ $otpCode }}</div>
                <div class="otp-info">
                    Ce code est valide pendant <strong>{{ $expiresIn }} minutes</strong>
                </div>
            </div>
            
            <div class="instructions">
                <h4>📋 Comment l'utiliser :</h4>
                <ol>
                    @if($isInvitation)
                        <li>Retournez sur la page d'invitation</li>
                        <li>Saisissez ce code dans les 6 champs prévus</li>
                        <li>Cliquez sur "Vérifier le code"</li>
                        <li>Votre compte sera automatiquement créé</li>
                    @else
                        <li>Retournez sur la page de connexion</li>
                        <li>Saisissez ce code dans les 6 champs prévus</li>
                        <li>Cliquez sur "Vérifier le code"</li>
                        <li>Vous serez connecté automatiquement</li>
                    @endif
                </ol>
            </div>
            
            <div class="expiry-info">
                <p>⏰ Ce code expire dans {{ $expiresIn }} minutes</p>
            </div>
            
            <div class="security-notice">
                <h4>🔒 Sécurité importante</h4>
                <p>
                    Ne partagez jamais ce code avec qui que ce soit. Nos équipes ne vous demanderont jamais votre code de vérification.
                    Si vous n'avez pas demandé ce code, veuillez ignorer cet email.
                </p>
            </div>
        </div>
        
        <div class="footer">
            <p><strong>{{ $brandName }}</strong></p>
            <p>Dashboard Administrateur</p>
            <p style="margin-top: 15px;">
                Si vous avez des questions, contactez-nous à : 
                <a href="mailto:{{ config('mail.from_address') }}" style="color: {{ $primaryColor }};">{{ config('mail.from_address') }}</a>
            </p>
        </div>
    </div>
</body>
</html>
