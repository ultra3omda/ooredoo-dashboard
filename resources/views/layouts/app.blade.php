<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Club Privileges - Dashboard')</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        :root {
            --brand-primary: #6C4BA0;
            --brand-secondary: #D4A843;
            --brand-dark: #1a1a2e;
            --bg: #f4f4f8;
            --card: #ffffff;
            --card-hover: #f0edf5;
            --muted: #71717a;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --accent: #D4A843;
            --border: #e2e0ea;
            --text-primary: #1a1a2e;
            --text-secondary: #52525b;
            --input-bg: #ffffff;
            --input-border: #d4d4d8;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --table-stripe: rgba(108, 75, 160, 0.03);
        }
        .dark-mode {
            --brand-dark: #FFFFFF;
            --bg: #0D0A1A;
            --card: #161131;
            --card-hover: #1E1745;
            --muted: #A1A1AA;
            --border: #2A2350;
            --text-primary: #FFFFFF;
            --text-secondary: #A1A1AA;
            --input-bg: #1E1745;
            --input-border: #2A2350;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.3);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.4);
            --table-stripe: rgba(255, 255, 255, 0.03);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { 
            background: var(--bg); 
            color: var(--text-primary); 
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            line-height: 1.6;
            min-height: 100vh;
            transition: background 0.3s ease, color 0.3s ease;
        }

        /* Layout Header */
        .app-header {
            background: var(--card);
            border-bottom: 1px solid var(--border);
            padding: 12px 24px;
            box-shadow: var(--shadow-sm);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .header-inner {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }
        .header-brand {
            font-family: 'Outfit', sans-serif;
            font-size: 18px;
            font-weight: 700;
            color: var(--brand-primary);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .header-brand:hover { opacity: 0.85; }
        .header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            background: var(--brand-primary);
            color: #fff;
            text-decoration: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            transition: opacity 0.2s;
            border: none;
            cursor: pointer;
        }
        .btn-back:hover { opacity: 0.85; }
        .theme-toggle-btn {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 6px 10px;
            cursor: pointer;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            font-size: 13px;
        }
        .user-badge {
            background: var(--card-hover);
            padding: 6px 12px;
            border-radius: 8px;
            border: 1px solid var(--border);
            font-size: 13px;
            color: var(--text-secondary);
        }

        /* Main Content */
        .app-main {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px 24px;
        }

        /* Cards */
        .cp-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            box-shadow: var(--shadow-sm);
            transition: box-shadow 0.2s ease;
        }
        .cp-card:hover { box-shadow: var(--shadow-md); }
        .cp-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border);
        }
        .cp-card-title {
            font-family: 'Outfit', sans-serif;
            font-size: 16px;
            font-weight: 600;
            color: var(--text-primary);
        }

        /* Buttons */
        .btn-primary { background: var(--brand-primary); color: #fff; border: none; padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 500; cursor: pointer; transition: opacity 0.2s; }
        .btn-primary:hover { opacity: 0.85; }
        .btn-success { background: var(--success); color: #fff; border: none; padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 500; cursor: pointer; }
        .btn-warning { background: var(--warning); color: #fff; border: none; padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 500; cursor: pointer; }
        .btn-danger { background: var(--danger); color: #fff; border: none; padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 500; cursor: pointer; }
        .btn-outline { background: transparent; color: var(--text-primary); border: 1px solid var(--border); padding: 8px 16px; border-radius: 8px; font-size: 13px; cursor: pointer; }
        .btn-outline.active, .btn-outline:hover { background: var(--brand-primary); color: #fff; border-color: var(--brand-primary); }
        .btn-sm { padding: 4px 10px; font-size: 12px; }
        .btn-group { display: flex; gap: 4px; flex-wrap: wrap; }

        /* Tables */
        .cp-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .cp-table th { background: var(--table-stripe); font-weight: 600; color: var(--muted); font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; padding: 10px 12px; text-align: left; border-bottom: 2px solid var(--border); }
        .cp-table td { padding: 10px 12px; border-bottom: 1px solid var(--border); color: var(--text-primary); }
        .cp-table tr:hover { background: var(--table-stripe); }

        /* Badges */
        .badge { display: inline-block; padding: 3px 8px; border-radius: 6px; font-size: 11px; font-weight: 600; }
        .badge-primary { background: rgba(108,75,160,0.12); color: var(--brand-primary); }
        .badge-success { background: rgba(16,185,129,0.12); color: var(--success); }
        .badge-warning { background: rgba(245,158,11,0.12); color: var(--warning); }
        .badge-danger { background: rgba(239,68,68,0.12); color: var(--danger); }
        .badge-info { background: rgba(59,130,246,0.12); color: #3b82f6; }
        .badge-secondary { background: var(--table-stripe); color: var(--muted); }

        /* Progress bar */
        .progress { height: 8px; background: var(--table-stripe); border-radius: 4px; overflow: hidden; }
        .progress-bar { height: 100%; border-radius: 4px; transition: width 0.3s; }
        .progress-bar.bg-success { background: var(--success); }
        .progress-bar.bg-warning { background: var(--warning); }
        .progress-bar.bg-danger { background: var(--danger); }

        /* KPI Row */
        .kpi-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 20px; }
        .kpi-card { background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 16px 20px; box-shadow: var(--shadow-sm); }
        .kpi-card .kpi-label { font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: var(--muted); font-weight: 600; margin-bottom: 4px; }
        .kpi-card .kpi-value { font-size: 24px; font-weight: 700; color: var(--text-primary); font-family: 'Outfit', sans-serif; }
        .kpi-card .kpi-accent { border-left: 3px solid var(--brand-primary); }

        /* Grid */
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; }
        .grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; }

        /* Alerts */
        .alert { padding: 12px 16px; border-radius: 8px; font-size: 13px; margin-bottom: 16px; }
        .alert-danger { background: rgba(239,68,68,0.08); color: var(--danger); border: 1px solid rgba(239,68,68,0.2); }
        .alert-success { background: rgba(16,185,129,0.08); color: var(--success); border: 1px solid rgba(16,185,129,0.2); }
        .alert-info { background: rgba(59,130,246,0.08); color: #3b82f6; border: 1px solid rgba(59,130,246,0.2); }

        /* Form elements */
        .form-input, .form-select {
            width: 100%;
            padding: 8px 12px;
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            border-radius: 8px;
            color: var(--text-primary);
            font-size: 14px;
            font-family: inherit;
            transition: border-color 0.2s;
        }
        .form-input:focus, .form-select:focus { outline: none; border-color: var(--brand-primary); }
        .form-label { display: block; font-size: 13px; font-weight: 500; color: var(--text-secondary); margin-bottom: 4px; }

        /* Modals */
        .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9999; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .modal-content { background: var(--card); border-radius: 12px; padding: 24px; max-width: 900px; width: 100%; max-height: 85vh; overflow-y: auto; box-shadow: var(--shadow-md); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
        .modal-close { background: none; border: none; font-size: 20px; cursor: pointer; color: var(--muted); }

        /* Notification */
        .notification { position: fixed; top: 16px; right: 16px; z-index: 10001; padding: 12px 20px; border-radius: 10px; font-size: 13px; box-shadow: var(--shadow-md); animation: slideIn 0.3s ease; }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

        /* Responsive */
        @media (max-width: 768px) {
            .app-main { padding: 12px; }
            .header-inner { gap: 10px; }
            .header-brand { font-size: 15px; }
            .grid-2, .grid-3, .grid-4 { grid-template-columns: 1fr; }
            .kpi-row { grid-template-columns: repeat(2, 1fr); }
            .cp-card { padding: 14px; }
            .btn-group { flex-wrap: wrap; }
        }
        @media (max-width: 480px) {
            .kpi-row { grid-template-columns: 1fr; }
            .header-actions { justify-content: flex-end; width: 100%; }
        }

        @yield('styles')
    </style>
</head>
<body>
    <div class="app-header">
        <div class="header-inner">
            <a href="{{ route('dashboard') }}" class="header-brand">
                <i class="fas fa-chart-pie"></i>
                Club Privileges
            </a>
            <div class="header-actions">
                @if(Auth::check())
                    <span class="user-badge">{{ Auth::user()->name ?? 'Utilisateur' }}</span>
                @endif
                <button class="theme-toggle-btn" onclick="toggleTheme()" data-testid="layout-theme-toggle">
                    <svg id="sun-icon" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="display:none;"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
                    <svg id="moon-icon" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/></svg>
                </button>
                <a href="{{ route('dashboard') }}" class="btn-back">
                    <i class="fas fa-arrow-left"></i> Dashboard
                </a>
            </div>
        </div>
    </div>
    
    <div class="app-main">
        @yield('content')
    </div>

    <script>
    // Theme management - synced with main dashboard
    function initTheme() {
        const saved = localStorage.getItem('dashboard-theme');
        if (saved === 'dark') document.documentElement.classList.add('dark-mode');
        else document.documentElement.classList.remove('dark-mode');
        updateThemeIcons();
    }
    function toggleTheme() {
        const isDark = document.documentElement.classList.toggle('dark-mode');
        localStorage.setItem('dashboard-theme', isDark ? 'dark' : 'light');
        updateThemeIcons();
    }
    function updateThemeIcons() {
        const isDark = document.documentElement.classList.contains('dark-mode');
        const sun = document.getElementById('sun-icon');
        const moon = document.getElementById('moon-icon');
        if (sun) sun.style.display = isDark ? 'block' : 'none';
        if (moon) moon.style.display = isDark ? 'none' : 'block';
    }
    initTheme();

    // Notification helper
    function showNotification(message, type) {
        const colors = { success: 'var(--success)', error: 'var(--danger)', info: 'var(--brand-primary)' };
        const n = document.createElement('div');
        n.className = 'notification';
        n.style.background = colors[type] || colors.info;
        n.style.color = '#fff';
        n.textContent = message;
        document.body.appendChild(n);
        setTimeout(() => n.remove(), 4000);
    }
    </script>
    @yield('scripts')
</body>
</html>
