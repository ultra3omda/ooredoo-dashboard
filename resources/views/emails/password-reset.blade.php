<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réinitialisation de votre mot de passe</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
            background-color: #f8fafc;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .header {
            background: linear-gradient(135deg, #6B46C1 0%, #8B5CF6 100%);
            padding: 32px 24px;
            text-align: center;
        }
        .logo {
            color: white;
            font-size: 28px;
            font-weight: bold;
            margin: 0;
        }
        .subtitle {
            color: #F59E0B;
            font-size: 14px;
            font-style: italic;
            margin: 8px 0 0 0;
        }
        .content {
            padding: 32px 24px;
        }
        .greeting {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 16px;
            color: #1f2937;
        }
        .message {
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 24px;
            color: #374151;
        }
        .button-container {
            text-align: center;
            margin: 32px 0;
        }
        .button {
            display: inline-block;
            padding: 16px 32px;
            background: linear-gradient(45deg, #6B46C1, #8B5CF6);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
        }
        .button:hover {
            background: linear-gradient(45deg, #553C9A, #6B46C1);
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(107, 70, 193, 0.3);
        }
        .info-box {
            background: #f0f9ff;
            border: 1px solid #e0f2fe;
            border-radius: 8px;
            padding: 16px;
            margin: 24px 0;
        }
        .info-box h4 {
            margin: 0 0 8px 0;
            color: #0369a1;
            font-size: 14px;
            font-weight: 600;
        }
        .info-box p {
            margin: 0;
            font-size: 14px;
            color: #0284c7;
        }
        .footer {
            background: #f8fafc;
            padding: 24px;
            text-align: center;
            border-top: 1px solid #e2e8f0;
        }
        .footer p {
            margin: 0;
            font-size: 14px;
            color: #64748b;
        }
        .footer a {
            color: #6B46C1;
            text-decoration: none;
        }
        .security-notice {
            background: #fef3c7;
            border: 1px solid #fbbf24;
            border-radius: 8px;
            padding: 16px;
            margin: 24px 0;
        }
        .security-notice h4 {
            margin: 0 0 8px 0;
            color: #92400e;
            font-size: 14px;
            font-weight: 600;
        }
        .security-notice p {
            margin: 0;
            font-size: 14px;
            color: #a16207;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 class="logo">Club Privilèges</h1>
            <p class="subtitle">Profitez de remises exclusives et permanentes</p>
        </div>
        
        <div class="content">
            <div class="greeting">
                Bonjour {{ $user->first_name }} {{ $user->last_name }},
            </div>
            
            <div class="message">
                Vous avez demandé la réinitialisation de votre mot de passe pour votre compte 
                <strong>{{ $user->email }}</strong> sur la plateforme Club Privilèges.
            </div>
            
            <div class="message">
                Cliquez sur le bouton ci-dessous pour créer un nouveau mot de passe sécurisé :
            </div>
            
            <div class="button-container">
                <a href="{{ $resetUrl }}" class="button">
                    🔒 Réinitialiser mon mot de passe
                </a>
            </div>
            
            <div class="info-box">
                <h4>📋 Informations importantes :</h4>
                <p>• Ce lien est valide pendant <strong>1 heure</strong> seulement</p>
                <p>• Il ne peut être utilisé qu'une seule fois</p>
                <p>• Votre nouveau mot de passe doit contenir au moins 8 caractères avec majuscules, minuscules et chiffres</p>
            </div>
            
            <div class="security-notice">
                <h4>🛡️ Sécurité :</h4>
                <p>Si vous n'avez pas demandé cette réinitialisation, ignorez cet email. Votre compte reste sécurisé et aucune action n'est requise.</p>
            </div>
            
            <div class="message">
                Si le bouton ne fonctionne pas, copiez et collez ce lien dans votre navigateur :
                <br><br>
                <a href="{{ $resetUrl }}" style="color: #6B46C1; word-break: break-all;">{{ $resetUrl }}</a>
            </div>
        </div>
        
        <div class="footer">
            <p>
                <strong>Club Privilèges</strong> - Plateforme de gestion des commerces partenaires
                <br>
                Cet email a été envoyé automatiquement, merci de ne pas y répondre.
                <br>
                <a href="mailto:support@clubprivileges.com">Contactez le support</a> si vous avez des questions.
            </p>
        </div>
    </div>
</body>
</html>

