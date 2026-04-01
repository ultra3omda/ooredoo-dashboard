{{-- Shared Admin Header - Consistent navigation across all pages --}}
<style>
    .admin-navbar {
        background: var(--card, #fff);
        border-bottom: 1px solid var(--border, #e2e8f0);
        padding: 0 20px;
        position: sticky;
        top: 0;
        z-index: 1000;
        box-shadow: 0 1px 4px rgba(0,0,0,0.06);
    }
    .admin-navbar-inner {
        max-width: 1400px;
        margin: 0 auto;
        display: flex;
        align-items: center;
        justify-content: space-between;
        height: 56px;
        gap: 16px;
    }
    .admin-navbar .nav-left {
        display: flex;
        align-items: center;
        gap: 14px;
        flex-shrink: 0;
    }
    .admin-navbar .nav-logo {
        width: 110px;
        height: 36px;
        flex-shrink: 0;
    }
    .admin-navbar .nav-links {
        display: flex;
        align-items: center;
        gap: 2px;
        overflow-x: auto;
        -ms-overflow-style: none;
        scrollbar-width: none;
    }
    .admin-navbar .nav-links::-webkit-scrollbar { display: none; }
    .admin-navbar .nav-link {
        padding: 8px 12px;
        font-size: 13px;
        font-weight: 500;
        color: var(--muted, #64748b);
        text-decoration: none;
        border-radius: 6px;
        white-space: nowrap;
        transition: all 0.15s;
    }
    .admin-navbar .nav-link:hover {
        background: rgba(107,70,193,0.08);
        color: var(--brand-primary, #6B46C1);
    }
    .admin-navbar .nav-link.active {
        background: var(--brand-primary, #6B46C1);
        color: #fff;
    }
    .admin-navbar .nav-right {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-shrink: 0;
    }
    .admin-navbar .nav-user-btn {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 6px 12px;
        background: var(--input-bg, #f1f5f9);
        border: 1px solid var(--border, #e2e8f0);
        border-radius: 8px;
        cursor: pointer;
        color: var(--text-primary, #1f2937);
        font-size: 13px;
        font-weight: 500;
        transition: all 0.15s;
    }
    .admin-navbar .nav-user-btn:hover { background: rgba(107,70,193,0.08); }
    .admin-navbar .nav-user-name { max-width: 120px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .admin-navbar .nav-user-role { font-size: 11px; color: var(--muted, #64748b); }

    .admin-navbar .nav-dropdown {
        display: none;
        position: fixed;
        background: var(--card, #fff);
        border: 1px solid var(--border, #e2e8f0);
        border-radius: 10px;
        min-width: 220px;
        z-index: 10001;
        box-shadow: 0 8px 30px rgba(0,0,0,0.18);
        padding: 6px 0;
    }
    .admin-navbar .nav-dropdown.show { display: block; }
    .admin-navbar .nav-dropdown-item {
        display: block;
        padding: 10px 16px;
        font-size: 13px;
        color: var(--text-primary, #1f2937);
        text-decoration: none;
        transition: background 0.12s;
    }
    .admin-navbar .nav-dropdown-item:hover { background: rgba(107,70,193,0.06); }
    .admin-navbar .nav-dropdown-sep { border-top: 1px solid var(--border, #e2e8f0); margin: 4px 0; }

    .admin-navbar .nav-theme-btn {
        background: var(--card, #fff);
        border: 1px solid var(--border, #e2e8f0);
        border-radius: 8px;
        padding: 6px 8px;
        cursor: pointer;
        color: var(--text-primary, #1f2937);
        display: flex;
        align-items: center;
        transition: all 0.15s;
    }
    .admin-navbar .nav-theme-btn:hover { background: rgba(107,70,193,0.08); }

    /* Hamburger for mobile */
    .admin-navbar .nav-hamburger {
        display: none;
        background: none;
        border: none;
        cursor: pointer;
        padding: 6px;
        color: var(--text-primary, #1f2937);
    }
    .admin-navbar .nav-mobile-menu {
        display: none;
        position: fixed;
        top: 56px;
        left: 0;
        right: 0;
        background: var(--card, #fff);
        border-bottom: 1px solid var(--border, #e2e8f0);
        box-shadow: 0 4px 16px rgba(0,0,0,0.1);
        z-index: 999;
        padding: 8px 0;
        max-height: 70vh;
        overflow-y: auto;
    }
    .admin-navbar .nav-mobile-menu.show { display: block; }
    .admin-navbar .nav-mobile-menu a {
        display: block;
        padding: 12px 20px;
        font-size: 14px;
        color: var(--text-primary, #1f2937);
        text-decoration: none;
        transition: background 0.12s;
    }
    .admin-navbar .nav-mobile-menu a:hover { background: rgba(107,70,193,0.06); }
    .admin-navbar .nav-mobile-menu a.active { background: var(--brand-primary, #6B46C1); color: #fff; }

    @media (max-width: 900px) {
        .admin-navbar .nav-links { display: none; }
        .admin-navbar .nav-hamburger { display: flex; }
        .admin-navbar .nav-user-name { display: none; }
    }
    @media (max-width: 480px) {
        .admin-navbar { padding: 0 10px; }
        .admin-navbar-inner { gap: 8px; }
        .admin-navbar .nav-logo { width: 80px; height: 28px; }
        .admin-navbar .nav-right { gap: 6px; }
        .admin-navbar .nav-user-btn { padding: 4px 8px; font-size: 12px; }
        .admin-navbar .nav-theme-btn { padding: 4px 6px; }
    }
</style>

@php
    $currentRoute = Route::currentRouteName() ?? '';
    $user = Auth::user();
    $isSuper = $user && $user->isSuperAdmin();
    $canInvite = $user && $user->canInviteCollaborators();
    $canSubStores = $user && $user->canAccessSubStoresDashboard();
    $canEklektik = $user && $user->canAccessEklektikConfig();
@endphp

<nav class="admin-navbar" data-testid="admin-navbar">
    <div class="admin-navbar-inner">
        <!-- Left: Logo + Nav Links -->
        <div class="nav-left">
            <a href="{{ route('dashboard') }}" title="Retour au Dashboard">
                <svg class="nav-logo" viewBox="0 0 200 60" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <defs>
                        <linearGradient id="navGradient" x1="0%" y1="0%" x2="100%" y2="0%">
                            <stop offset="0%" style="stop-color:var(--brand-primary, #6B46C1);stop-opacity:1" />
                            <stop offset="100%" style="stop-color:var(--brand-secondary, #8B5CF6);stop-opacity:1" />
                        </linearGradient>
                    </defs>
                    <rect width="200" height="60" fill="url(#navGradient)" rx="8"/>
                    <text x="20" y="25" fill="white" font-family="Inter, sans-serif" font-size="16" font-weight="bold">Club</text>
                    <text x="20" y="45" fill="#F59E0B" font-family="Inter, sans-serif" font-size="14" font-weight="600" font-style="italic">Privileges</text>
                </svg>
            </a>

            <div class="nav-links" data-testid="nav-links">
                <a href="{{ route('dashboard') }}" class="nav-link {{ $currentRoute === 'dashboard' ? 'active' : '' }}" data-testid="nav-dashboard">Dashboard</a>
                @if($canSubStores)
                <a href="{{ route('sub-stores.dashboard') }}" class="nav-link {{ $currentRoute === 'sub-stores.dashboard' ? 'active' : '' }}" data-testid="nav-substores">Sub-Stores</a>
                @endif
                @if($canInvite)
                <a href="{{ route('admin.users.index') }}" class="nav-link {{ str_starts_with($currentRoute, 'admin.users') ? 'active' : '' }}" data-testid="nav-users">Utilisateurs</a>
                <a href="{{ route('admin.invitations.index') }}" class="nav-link {{ str_starts_with($currentRoute, 'admin.invitations') ? 'active' : '' }}" data-testid="nav-invitations">Invitations</a>
                @endif
                <a href="{{ route('admin.merchant-reco.dashboard') }}" class="nav-link {{ $currentRoute === 'admin.merchant-reco.dashboard' ? 'active' : '' }}" data-testid="nav-ml">ML</a>
                @if($isSuper)
                <a href="{{ route('admin.users.permissions') }}" class="nav-link {{ $currentRoute === 'admin.users.permissions' ? 'active' : '' }}" data-testid="nav-permissions">Permissions</a>
                <a href="{{ route('admin.audit-logs.index') }}" class="nav-link {{ str_starts_with($currentRoute, 'admin.audit-logs') ? 'active' : '' }}" data-testid="nav-audit">Audit</a>
                @endif
            </div>
        </div>

        <!-- Right: Theme + User -->
        <div class="nav-right">
            <button class="nav-theme-btn" onclick="toggleAdminTheme()" title="Changer le theme" data-testid="nav-theme-toggle">
                <svg id="navSunIcon" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="display:none;"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
                <svg id="navMoonIcon" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/></svg>
            </button>

            <button class="nav-user-btn" id="navProfileToggle" data-testid="nav-profile-toggle">
                <div>
                    <div class="nav-user-name">{{ $user->name ?? 'Utilisateur' }}</div>
                    <div class="nav-user-role">{{ $user->role->display_name ?? '' }}</div>
                </div>
                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
            </button>

            <!-- Hamburger for mobile -->
            <button class="nav-hamburger" id="navHamburger" data-testid="nav-hamburger">
                <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 12h18M3 6h18M3 18h18"/></svg>
            </button>
        </div>
    </div>

    <!-- Profile Dropdown -->
    <div class="nav-dropdown" id="navProfileDropdown" data-testid="nav-profile-dropdown">
        <a href="{{ route('dashboard') }}" class="nav-dropdown-item">Dashboard Principal</a>
        @if($canSubStores)
        <a href="{{ route('sub-stores.dashboard') }}" class="nav-dropdown-item">Sub-Stores</a>
        @endif
        <a href="{{ route('password.change') }}" class="nav-dropdown-item">Mot de passe</a>
        @if($canEklektik)
        <div class="nav-dropdown-sep"></div>
        <a href="{{ route('admin.eklektik-cron') }}" class="nav-dropdown-item">Config Eklektik</a>
        <a href="{{ route('admin.eklektik.sync') }}" class="nav-dropdown-item">Gestion Sync</a>
        <a href="{{ route('admin.eklektik.sync-tracking') }}" class="nav-dropdown-item">Suivi Sync</a>
        @endif
        <div class="nav-dropdown-sep"></div>
        <a href="{{ route('admin.ml.dashboard') }}" class="nav-dropdown-item">Dashboard ML</a>
        <a href="{{ route('admin.merchant-reco.dashboard') }}" class="nav-dropdown-item">Recommandations ML</a>
        @if($isSuper)
        <a href="{{ route('admin.pluxee.users.index') }}" class="nav-dropdown-item">Gestion Pluxee</a>
        @endif
        <div class="nav-dropdown-sep"></div>
        <form action="{{ route('auth.logout') }}" method="POST" style="margin:0;">
            @csrf
            <button type="submit" class="nav-dropdown-item" style="width:100%;text-align:left;color:var(--danger,#ef4444);border:none;background:none;cursor:pointer;font-size:13px;" data-testid="nav-logout">Deconnexion</button>
        </form>
    </div>

    <!-- Mobile Menu -->
    <div class="nav-mobile-menu" id="navMobileMenu" data-testid="nav-mobile-menu">
        <a href="{{ route('dashboard') }}" class="{{ $currentRoute === 'dashboard' ? 'active' : '' }}">Dashboard</a>
        @if($canSubStores)
        <a href="{{ route('sub-stores.dashboard') }}" class="{{ $currentRoute === 'sub-stores.dashboard' ? 'active' : '' }}">Sub-Stores</a>
        @endif
        @if($canInvite)
        <a href="{{ route('admin.users.index') }}" class="{{ str_starts_with($currentRoute, 'admin.users') ? 'active' : '' }}">Utilisateurs</a>
        <a href="{{ route('admin.invitations.index') }}" class="{{ str_starts_with($currentRoute, 'admin.invitations') ? 'active' : '' }}">Invitations</a>
        @endif
        <a href="{{ route('admin.merchant-reco.dashboard') }}" class="{{ $currentRoute === 'admin.merchant-reco.dashboard' ? 'active' : '' }}">Recommandations ML</a>
        <a href="{{ route('admin.ml.dashboard') }}" class="{{ $currentRoute === 'admin.ml.dashboard' ? 'active' : '' }}">Dashboard ML</a>
        @if($isSuper)
        <a href="{{ route('admin.users.permissions') }}" class="{{ $currentRoute === 'admin.users.permissions' ? 'active' : '' }}">Permissions</a>
        <a href="{{ route('admin.audit-logs.index') }}" class="{{ str_starts_with($currentRoute, 'admin.audit-logs') ? 'active' : '' }}">Journal d'Audit</a>
        <a href="{{ route('admin.pluxee.users.index') }}" class="{{ $currentRoute === 'admin.pluxee.users.index' ? 'active' : '' }}">Gestion Pluxee</a>
        @endif
        @if($canEklektik)
        <a href="{{ route('admin.eklektik-cron') }}">Config Eklektik</a>
        <a href="{{ route('admin.eklektik.sync') }}">Gestion Sync</a>
        @endif
        <a href="{{ route('password.change') }}">Mot de passe</a>
        <div style="border-top:1px solid var(--border,#e2e8f0);margin:4px 0;"></div>
        <form action="{{ route('auth.logout') }}" method="POST" style="margin:0;">
            @csrf
            <button type="submit" style="width:100%;text-align:left;padding:12px 20px;font-size:14px;color:var(--danger,#ef4444);border:none;background:none;cursor:pointer;">Deconnexion</button>
        </form>
    </div>
</nav>

<script>
(function() {
    // Theme toggle
    window.toggleAdminTheme = function() {
        const isDark = document.documentElement.classList.toggle('dark-mode');
        localStorage.setItem('dashboard-theme', isDark ? 'dark' : 'light');
        const sun = document.getElementById('navSunIcon');
        const moon = document.getElementById('navMoonIcon');
        if (sun && moon) { sun.style.display = isDark ? 'block' : 'none'; moon.style.display = isDark ? 'none' : 'block'; }
    };
    // Init theme icons
    const isDark = document.documentElement.classList.contains('dark-mode');
    const sun = document.getElementById('navSunIcon');
    const moon = document.getElementById('navMoonIcon');
    if (sun && moon) { sun.style.display = isDark ? 'block' : 'none'; moon.style.display = isDark ? 'none' : 'block'; }

    // Profile dropdown
    const profileBtn = document.getElementById('navProfileToggle');
    const profileDd = document.getElementById('navProfileDropdown');
    if (profileBtn && profileDd) {
        profileBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            const rect = profileBtn.getBoundingClientRect();
            profileDd.style.top = (rect.bottom + 4) + 'px';
            profileDd.style.right = (window.innerWidth - rect.right) + 'px';
            profileDd.classList.toggle('show');
            // Close mobile menu
            document.getElementById('navMobileMenu')?.classList.remove('show');
        });
    }

    // Hamburger menu
    const hamburger = document.getElementById('navHamburger');
    const mobileMenu = document.getElementById('navMobileMenu');
    if (hamburger && mobileMenu) {
        hamburger.addEventListener('click', function(e) {
            e.stopPropagation();
            mobileMenu.classList.toggle('show');
            profileDd?.classList.remove('show');
        });
    }

    // Close on outside click
    document.addEventListener('click', function() {
        profileDd?.classList.remove('show');
        mobileMenu?.classList.remove('show');
    });
    // Prevent close when clicking inside dropdown
    profileDd?.addEventListener('click', function(e) { e.stopPropagation(); });
    mobileMenu?.addEventListener('click', function(e) { e.stopPropagation(); });
})();
</script>
