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
    <title>Réinitialisation du mot de passe - Club Privilèges</title>
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
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            backdrop-filter: blur(10px);
            padding: 48px;
            width: 100%;
            max-width: 450px;
            margin: 20px;
            position: relative;
            z-index: 1;
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
        
        .btn:hover {
            background: linear-gradient(45deg, #553C9A, var(--club-primary));
            transform: translateY(-2px);
            box-shadow: 0 15px 35px rgba(107, 70, 193, 0.3);
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
        
        .password-requirements {
            font-size: 12px;
            color: var(--muted);
            margin-top: 8px;
            line-height: 1.4;
        }
        
        .password-requirements ul {
            margin: 4px 0 0 0;
            padding-left: 16px;
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
            <p class="club-subtitle">Nouveau mot de passe</p>
            <p class="welcome-text">Définissez votre nouveau mot de passe<br>sécurisé pour votre compte</p>
        </div>
        
        @if(session('error'))
            <div class="alert alert-danger">
                {{ session('error') }}
            </div>
        @endif
        
        <form id="resetForm" action="{{ route('password.reset') }}" method="POST">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            
            <div class="form-group">
                <label for="password" class="form-label">Nouveau mot de passe</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    class="form-input" 
                    required
                    placeholder="••••••••"
                >
                @error('password')
                    <div class="alert alert-danger" style="margin-top: 8px; margin-bottom: 0;">
                        {{ $message }}
                    </div>
                @enderror
                <div class="password-requirements">
                    <strong>Exigences du mot de passe :</strong>
                    <ul>
                        <li>Au moins 8 caractères</li>
                        <li>Au moins une majuscule et une minuscule</li>
                        <li>Au moins un chiffre</li>
                    </ul>
                </div>
            </div>
            
            <div class="form-group">
                <label for="password_confirmation" class="form-label">Confirmer le mot de passe</label>
                <input 
                    type="password" 
                    id="password_confirmation" 
                    name="password_confirmation" 
                    class="form-input" 
                    required
                    placeholder="••••••••"
                >
            </div>
            
            <button type="submit" class="btn" id="resetBtn">
                <span class="loading hidden"></span>
                <span id="resetText">Réinitialiser</span>
            </button>
        </form>
    </div>
    
    <script>
        document.getElementById('resetForm').addEventListener('submit', function() {
            const btn = document.getElementById('resetBtn');
            const loading = btn.querySelector('.loading');
            const text = document.getElementById('resetText');
            
            btn.disabled = true;
            loading.classList.remove('hidden');
            text.textContent = 'Traitement...';
        });
    </script>
</body>
</html>

