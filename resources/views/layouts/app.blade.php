<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Club Privilèges - Dashboard')</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        :root {
            --club-primary: #6B46C1;
            --club-secondary: #8B5CF6;
            --club-accent: #F59E0B;
            --brand-dark: #1f2937;
            --bg: #f8fafc;
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
            background: var(--bg); 
            color: var(--brand-dark); 
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            line-height: 1.5;
            min-height: 100vh;
        }
        
        .header {
            background: var(--card);
            border-bottom: 1px solid var(--border);
            padding: 16px 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-size: 20px;
            font-weight: 700;
            background: linear-gradient(45deg, var(--club-primary), var(--club-secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 16px;
            font-size: 14px;
            color: var(--muted);
        }
        
        .nav-link {
            color: var(--club-primary);
            text-decoration: none;
            font-weight: 500;
            padding: 8px 16px;
            border-radius: 6px;
            transition: all 0.2s ease;
        }
        
        .nav-link:hover {
            background: rgba(107, 70, 193, 0.1);
        }
        
        .main-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="logo">Club Privilèges</div>
            <div class="user-info">
                @if(Auth::check())
                    <span>{{ Auth::user()->first_name }} {{ Auth::user()->last_name }}</span>
                    <a href="{{ route('dashboard') }}" class="nav-link">← Retour au dashboard</a>
                @else
                    <span>Accès Direct (Test)</span>
                    <a href="/" class="nav-link">← Accueil</a>
                @endif
            </div>
        </div>
    </div>
    
    <div class="main-content">
        @yield('content')
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    
    @yield('scripts')
</body>
</html>

