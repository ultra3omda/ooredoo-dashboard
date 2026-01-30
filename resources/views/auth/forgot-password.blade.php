@php
    $theme = 'club_privileges';
    $isOoredoo = false;
    $isClubPrivileges = true;
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mot de passe oublié - Club Privilèges</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        :root {
            --club-primary: #6B46C1;
            --club-secondary: #8B5CF6;
            --club-accent: #F59E0B;
            --brand-dark: #1f2937;
            --card: #ffffff;
            --muted: #64748b;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --border: #e2e8f0;
        }
        
        * { box-sizing: border-box; }
        html, body { 
            margin: 0; 
            padding: 0; 
            background: linear-gradient(135deg, var(--club-primary) 0%, var(--club-secondary) 100%);
            color: var(--brand-dark); 
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            line-height: 1.5;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow-x: hidden;
        }
        
        .login-container {
            background: var(--card);
            border-radius: 20px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25), 0 0 0 1px rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            padding: 48px;
            width: 100%;
            max-width: 450px;
            margin: 20px;
            position: relative;
            z-index: 1;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .logo {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .club-logo {
            background: linear-gradient(45deg, var(--club-primary), var(--club-secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-size: 28px;
            font-weight: 700;
            margin: 0;
            letter-spacing: -0.5px;
        }
        
        .club-subtitle {
            background: linear-gradient(45deg, var(--club-accent), #FCD34D);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-size: 14px;
            font-weight: 600;
            margin: 8px 0 0 0;
            font-style: italic;
        }
        
        .welcome-text {
            color: var(--muted);
            font-size: 14px;
            margin: 16px 0 0 0;
            line-height: 1.4;
        }
        
        .form-group {
            margin-bottom: 24px;
        }
        
        .form-label {
            display: block;
            color: var(--brand-dark);
            font-weight: 500;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .form-input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--border);
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.2s ease;
            font-family: inherit;
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--club-primary);
            box-shadow: 0 0 0 3px rgba(107, 70, 193, 0.1);
        }
        
        .btn {
            width: 100%;
            padding: 16px 24px;
            background: linear-gradient(45deg, var(--club-primary), var(--club-secondary));
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: inherit;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            position: relative;
            overflow: hidden;
        }
        
        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }
        
        .btn:hover::before {
            left: 100%;
        }
        
        .btn:hover {
            background: linear-gradient(45deg, #553C9A, var(--club-primary));
            transform: translateY(-2px);
            box-shadow: 0 15px 35px rgba(107, 70, 193, 0.3);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .btn:disabled {
            background: var(--muted);
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            font-size: 14px;
        }
        
        .alert-danger {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }
        
        .alert-success {
            background: #dcfce7;
            color: #16a34a;
            border: 1px solid #bbf7d0;
        }
        
        .back-link {
            text-align: center;
            margin-top: 24px;
        }
        
        .back-link a {
            color: var(--club-primary);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        
        .back-link a:hover {
            text-decoration: underline;
        }
        
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
            margin-right: 8px;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .hidden { display: none; }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <h1 class="club-logo">Club Privilèges</h1>
            <p class="club-subtitle">Mot de passe oublié</p>
            <p class="welcome-text">Saisissez votre adresse e-mail pour recevoir<br>un lien de réinitialisation</p>
        </div>
        
        @if(session('error'))
            <div class="alert alert-danger">
                {{ session('error') }}
            </div>
        @endif
        
        @if(session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif
        
        <form id="forgotForm" action="{{ route('password.send-reset') }}" method="POST">
            @csrf
            <div class="form-group">
                <label for="email" class="form-label">Adresse e-mail</label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    class="form-input" 
                    required 
                    value="{{ old('email') }}"
                    placeholder="admin@exemple.com"
                >
                @error('email')
                    <div class="alert alert-danger" style="margin-top: 8px; margin-bottom: 0;">
                        {{ $message }}
                    </div>
                @enderror
            </div>
            
            <button type="submit" class="btn" id="submitBtn">
                <span class="loading hidden"></span>
                <span id="submitText">Envoyer le lien</span>
            </button>
        </form>
        
        <div class="back-link">
            <a href="{{ route('auth.login') }}">← Retour à la connexion</a>
        </div>
    </div>
    
    <script>
        document.getElementById('forgotForm').addEventListener('submit', function() {
            const btn = document.getElementById('submitBtn');
            const loading = btn.querySelector('.loading');
            const text = document.getElementById('submitText');
            
            btn.disabled = true;
            loading.classList.remove('hidden');
            text.textContent = 'Envoi en cours...';
        });
    </script>
</body>
</html>

