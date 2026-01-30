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
    <title>Vérification OTP - {{ $brandName }} Dashboard</title>
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
            background: var(--success);
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
        
        .email-display {
            background: var(--bg);
            padding: 12px;
            border-radius: 8px;
            font-weight: 500;
            color: var(--brand-primary);
            margin-bottom: 24px;
        }
        
        .otp-inputs {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin-bottom: 24px;
        }
        
        .otp-input {
            width: 50px;
            height: 50px;
            text-align: center;
            border: 2px solid var(--border);
            border-radius: 8px;
            font-size: 20px;
            font-weight: 600;
            transition: all 0.2s ease;
            font-family: inherit;
        }
        
        .otp-input:focus {
            outline: none;
            border-color: var(--brand-primary);
            box-shadow: 0 0 0 3px {{ $isOoredoo ? 'rgba(227, 6, 19, 0.1)' : 'rgba(107, 70, 193, 0.1)' }};
        }
        
        .otp-input.filled {
            background: {{ $isOoredoo ? '#fee2e2' : '#f3f0ff' }};
            border-color: var(--brand-primary);
            color: var(--brand-primary);
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
            color: var(--muted);
            border: 2px solid var(--border);
            font-size: 14px;
        }
        
        .btn-secondary:hover:not(:disabled) {
            border-color: var(--brand-primary);
            color: var(--brand-primary);
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
        
        .timer {
            color: var(--muted);
            font-size: 14px;
            margin-bottom: 16px;
        }
        
        .timer.warning {
            color: var(--warning);
        }
        
        .timer.danger {
            color: var(--danger);
        }
        
        .resend-link {
            color: var(--brand-primary);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
        }
        
        .resend-link:hover {
            text-decoration: underline;
        }
        
        .resend-link.disabled {
            color: var(--muted);
            cursor: not-allowed;
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
                <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"></path>
                <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"></path>
            </svg>
        </div>
        
        <h2 class="title">Code de vérification envoyé</h2>
        <p class="subtitle">Saisissez le code à 6 chiffres envoyé à votre adresse e-mail</p>
        
        <div class="email-display">
            {{ $email ?? session('otp_email') ?? 'votre.email@exemple.com' }}
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
        
        <form id="otpVerifyForm" action="{{ isset($invitation_token) ? route('auth.invitation.accept') : route('auth.otp.verify') }}" method="POST">
            @csrf
            <input type="hidden" name="email" value="{{ $email ?? session('otp_email') }}">
            @if(isset($invitation_token))
                <input type="hidden" name="invitation_token" value="{{ $invitation_token }}">
            @endif
            
            <div class="otp-inputs">
                <input type="text" class="otp-input" maxlength="1" data-index="0" autocomplete="off">
                <input type="text" class="otp-input" maxlength="1" data-index="1" autocomplete="off">
                <input type="text" class="otp-input" maxlength="1" data-index="2" autocomplete="off">
                <input type="text" class="otp-input" maxlength="1" data-index="3" autocomplete="off">
                <input type="text" class="otp-input" maxlength="1" data-index="4" autocomplete="off">
                <input type="text" class="otp-input" maxlength="1" data-index="5" autocomplete="off">
            </div>
            
            <input type="hidden" name="code" id="otpCode">
            
            @error('code')
                <div class="alert alert-danger">
                    {{ $message }}
                </div>
            @enderror
            
            <div class="timer" id="timer">
                Code valide pendant <span id="countdown">10:00</span>
            </div>
            
            <button type="submit" class="btn" id="verifyBtn" disabled>
                <span class="loading hidden"></span>
                <span id="verifyText">Vérifier le code</span>
            </button>
        </form>
        
        <form id="resendForm" action="{{ route('auth.otp.resend') }}" method="POST" style="display: inline;">
            @csrf
            <input type="hidden" name="email" value="{{ $email ?? session('otp_email') }}">
            <button type="submit" class="btn btn-secondary" id="resendBtn">
                Renvoyer le code
            </button>
        </form>
    </div>
    
    <script>
        // Gestion des inputs OTP
        const otpInputs = document.querySelectorAll('.otp-input');
        const otpCodeInput = document.getElementById('otpCode');
        const verifyBtn = document.getElementById('verifyBtn');
        
        otpInputs.forEach((input, index) => {
            input.addEventListener('input', function(e) {
                const value = e.target.value;
                
                if (value.length === 1) {
                    e.target.classList.add('filled');
                    // Passer au champ suivant
                    if (index < otpInputs.length - 1) {
                        otpInputs[index + 1].focus();
                    }
                } else {
                    e.target.classList.remove('filled');
                }
                
                updateOtpCode();
            });
            
            input.addEventListener('keydown', function(e) {
                // Supprimer et revenir au champ précédent
                if (e.key === 'Backspace' && !e.target.value && index > 0) {
                    otpInputs[index - 1].focus();
                    otpInputs[index - 1].classList.remove('filled');
                }
            });
            
            input.addEventListener('paste', function(e) {
                e.preventDefault();
                const paste = (e.clipboardData || window.clipboardData).getData('text');
                const pasteArray = paste.split('').slice(0, 6);
                
                pasteArray.forEach((char, i) => {
                    if (otpInputs[i]) {
                        otpInputs[i].value = char;
                        otpInputs[i].classList.add('filled');
                    }
                });
                
                updateOtpCode();
            });
        });
        
        function updateOtpCode() {
            const code = Array.from(otpInputs).map(input => input.value).join('');
            otpCodeInput.value = code;
            verifyBtn.disabled = code.length !== 6;
        }
        
        // Timer de compte à rebours
        let timeLeft = 600; // 10 minutes en secondes
        const countdownElement = document.getElementById('countdown');
        const timerElement = document.getElementById('timer');
        
        function updateTimer() {
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            countdownElement.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
            
            if (timeLeft <= 60) {
                timerElement.classList.add('danger');
            } else if (timeLeft <= 300) {
                timerElement.classList.add('warning');
            }
            
            if (timeLeft <= 0) {
                timerElement.innerHTML = '<span style="color: var(--danger);">Code expiré</span>';
                verifyBtn.disabled = true;
                otpInputs.forEach(input => input.disabled = true);
            } else {
                timeLeft--;
                setTimeout(updateTimer, 1000);
            }
        }
        
        updateTimer();
        
        // Gestion du formulaire de vérification
        document.getElementById('otpVerifyForm').addEventListener('submit', function() {
            const btn = document.getElementById('verifyBtn');
            const loading = btn.querySelector('.loading');
            const text = document.getElementById('verifyText');
            
            btn.disabled = true;
            loading.classList.remove('hidden');
            text.textContent = 'Vérification...';
        });
        
        // Focus automatique sur le premier champ
        otpInputs[0].focus();
    </script>
</body>
</html>
