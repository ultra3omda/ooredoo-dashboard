@php
    $theme = $theme ?? 'club_privileges';
    $isOoredoo = $theme === 'ooredoo';
    $isClubPrivileges = $theme === 'club_privileges';
    $brandName = $isOoredoo ? 'Ooredoo Privileges' : 'Club Privilèges';
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion OTP - {{ $brandName }} Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        :root {
            @if($isOoredoo)
            --brand-primary: #E30613;
            --brand-secondary: #DC2626;
            --theme-name: 'Ooredoo';
            @else
            --brand-primary: #6B46C1;
            --brand-secondary: #8B5CF6;
            --theme-name: 'Club Privilèges';
            @endif
            --brand-dark: #1f2937;
            --bg: #f8fafc;
            --card: #ffffff;
            --muted: #64748b;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --accent: #3b82f6;
            --border: #e2e8f0;
            /* Backward compatibility */
            --brand-red: var(--brand-primary);
        }
        
        * { box-sizing: border-box; }
        html, body { 
            margin: 0; 
            padding: 0; 
            background: linear-gradient(135deg, var(--bg) 0%, #e2e8f0 100%);
            color: var(--brand-dark); 
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            line-height: 1.5;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .otp-container {
            background: var(--card);
            border-radius: 16px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            padding: 48px;
            width: 100%;
            max-width: 420px;
            margin: 20px;
            text-align: center;
        }
        
        .logo {
            margin-bottom: 32px;
        }
        
        .logo h1 {
            color: var(--brand-primary);
            font-size: 24px;
            font-weight: 700;
            margin: 0;
        }
        
        .logo p {
            color: var(--muted);
            font-size: 14px;
            margin: 8px 0 0 0;
        }
        
        .icon {
            width: 80px;
            height: 80px;
            background: var(--brand-primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
        }
        
        .icon svg {
            width: 40px;
            height: 40px;
            color: white;
        }
        
        .title {
            font-size: 22px;
            font-weight: 600;
            color: var(--brand-dark);
            margin: 0 0 8px 0;
        }
        
        .subtitle {
            color: var(--muted);
            font-size: 14px;
            margin: 0 0 32px 0;
        }
        
        .form-group {
            margin-bottom: 24px;
            text-align: left;
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
            border-color: var(--brand-primary);
            box-shadow: 0 0 0 3px {{ $isOoredoo ? 'rgba(227, 6, 19, 0.1)' : 'rgba(107, 70, 193, 0.1)' }};
        }
        
        .btn {
            width: 100%;
            padding: 14px 24px;
            background: var(--brand-primary);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            font-family: inherit;
            margin-bottom: 16px;
        }
        
        .btn:hover:not(:disabled) {
            background: {{ $isOoredoo ? '#c20510' : '#553c9a' }};
            transform: translateY(-1px);
            box-shadow: 0 10px 25px {{ $isOoredoo ? 'rgba(227, 6, 19, 0.2)' : 'rgba(107, 70, 193, 0.2)' }};
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
        
        .btn-secondary {
            background: transparent;
            color: var(--brand-primary);
            border: 2px solid var(--brand-primary);
        }
        
        .btn-secondary:hover:not(:disabled) {
            background: var(--brand-primary);
            color: white;
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            font-size: 14px;
            text-align: left;
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
        
        .alert-info {
            background: #dbeafe;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
        }
        
        .back-link {
            margin-top: 24px;
        }
        
        .back-link a {
            color: var(--muted);
            text-decoration: none;
            font-size: 14px;
        }
        
        .back-link a:hover {
            color: var(--brand-primary);
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
    <div class="otp-container">
        <div class="logo">
            @if($isOoredoo)
            <img src="{{ asset('images/ooredoo-logo.png') }}" alt="Ooredoo" class="logo-img" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';" style="height: 60px; margin-bottom: 16px;">
            <svg class="logo-fallback" viewBox="0 0 200 60" fill="none" xmlns="http://www.w3.org/2000/svg" style="display: none; height: 60px; margin-bottom: 16px;">
                <rect width="200" height="60" fill="var(--brand-primary)"/>
                <text x="20" y="35" fill="white" font-family="Arial, sans-serif" font-size="24" font-weight="bold">ooredoo</text>
            </svg>
            <h1>{{ $brandName }}</h1>
            @else
            <svg class="logo-img" viewBox="0 0 200 60" fill="none" xmlns="http://www.w3.org/2000/svg" style="height: 60px; margin-bottom: 16px;">
                <defs>
                    <linearGradient id="clubGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" style="stop-color: var(--brand-primary)"/>
                        <stop offset="100%" style="stop-color: var(--brand-secondary)"/>
                    </linearGradient>
                </defs>
                <rect width="200" height="60" fill="url(#clubGradient)" rx="8"/>
                <text x="100" y="35" fill="white" font-family="Inter, sans-serif" font-size="20" font-weight="700" text-anchor="middle" dominant-baseline="middle">{{ $brandName }}</text>
            </svg>
            <h1>{{ $brandName }}</h1>
            @endif
            <p>Dashboard Administrateur</p>
        </div>
        
        <div class="icon">
            <svg fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 8a6 6 0 01-7.743 5.743L10 14l-1 1-1 1H6v2H2v-4l4.257-4.257A6 6 0 1118 8zm-6-4a1 1 0 100 2 2 2 0 012 2 1 1 0 102 0 4 4 0 00-4-4z" clip-rule="evenodd"></path>
            </svg>
        </div>
        
        <h2 class="title">Connexion par code OTP</h2>
        <p class="subtitle">Saisissez votre adresse e-mail pour recevoir un code de connexion sécurisé</p>
        
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
        
        @if(session('info'))
            <div class="alert alert-info">
                {{ session('info') }}
            </div>
        @endif
        
        <form id="otpRequestForm" action="{{ route('auth.otp.send') }}" method="POST">
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
                    placeholder="votre.email@exemple.com"
                >
                @error('email')
                    <div class="alert alert-danger" style="margin-top: 8px; margin-bottom: 0;">
                        {{ $message }}
                    </div>
                @enderror
            </div>
            
            <button type="submit" class="btn" id="otpBtn">
                <span class="loading hidden"></span>
                <span id="otpText">Envoyer le code OTP</span>
            </button>
        </form>
        
        <a href="{{ route('auth.login') }}" class="btn btn-secondary">
            Retour à la connexion classique
        </a>
        
        <div class="back-link">
            <a href="{{ route('dashboard') }}">← Retour au dashboard</a>
        </div>
    </div>
    
    <script>
        document.getElementById('otpRequestForm').addEventListener('submit', function() {
            const btn = document.getElementById('otpBtn');
            const loading = btn.querySelector('.loading');
            const text = document.getElementById('otpText');
            
            btn.disabled = true;
            loading.classList.remove('hidden');
            text.textContent = 'Envoi en cours...';
        });
    </script>
</body>
</html>
