@php
    $theme = $theme ?? 'club_privileges';
    $isOoredoo = $theme === 'ooredoo';
    $isClubPrivileges = $theme === 'club_privileges';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>{{ $isOoredoo ? 'Ooredoo Privileges' : 'Club Privilèges' }} - Comprehensive Performance Dashboard</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Manrope:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <link rel="stylesheet" href="/css/dashboard.css">
</head>
<body>
  <div class="container">
    <!-- Header -->
    <div class="header">
      <div class="header-left">
        @if($isOoredoo)
        <img src="{{ asset('images/ooredoo-logo.png') }}" alt="Ooredoo" class="logo" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
        <svg class="logo" viewBox="0 0 200 60" fill="none" xmlns="http://www.w3.org/2000/svg" style="display: none;">
          <rect width="200" height="60" fill="var(--brand-primary)"/>
          <text x="20" y="35" fill="white" font-family="Arial, sans-serif" font-size="24" font-weight="bold">ooredoo</text>
        </svg>
        <h1>Ooredoo Privileges - Performance Dashboard</h1>
        @else
        <svg class="logo" viewBox="0 0 200 60" fill="none" xmlns="http://www.w3.org/2000/svg">
          <defs>
            <linearGradient id="clubGradient" x1="0%" y1="0%" x2="100%" y2="0%">
              <stop offset="0%" style="stop-color:var(--brand-primary);stop-opacity:1" />
              <stop offset="100%" style="stop-color:var(--brand-secondary);stop-opacity:1" />
            </linearGradient>
          </defs>
          <rect width="200" height="60" fill="url(#clubGradient)" rx="8"/>
          <text x="20" y="25" fill="white" font-family="Arial, sans-serif" font-size="16" font-weight="bold">Club</text>
          <text x="20" y="45" fill="#F59E0B" font-family="Arial, sans-serif" font-size="14" font-weight="600" font-style="italic">Privilèges</text>
        </svg>
        <h1>Club Privilèges - Performance Dashboard</h1>
        @endif
      </div>
      <div class="header-right">
        <span style="font-size: 14px;">{{ Auth::user()->isSuperAdmin() ? 'Vue Globale' : 'Vue ' . (Auth::user()->getPrimaryOperatorName() ?? 'Opérateur') }}</span>
        
        <button id="theme-toggle-btn" onclick="toggleTheme()" data-testid="theme-toggle-btn" style="background: var(--card); border: 1px solid var(--border); border-radius: 8px; padding: 6px 10px; cursor: pointer; color: var(--text-primary); display: flex; align-items: center; gap: 6px; font-size: 13px; transition: all 0.2s ease;">
          <span id="theme-icon" style="font-size: 16px; line-height: 1;">
            <svg id="sun-icon" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="display:none;"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
            <svg id="moon-icon" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/></svg>
          </span>
        </button>
        
        <div class="user-menu">
          <div class="user-info" id="profileMenuToggle" style="cursor: pointer;" data-testid="profile-menu-toggle">
            <div class="user-name">{{ Auth::user()->name ?? 'Utilisateur' }}</div>
            <div class="user-role">{{ Auth::user()->role->display_name ?? 'Aucun rôle' }}</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Profile Dropdown - Outside header for proper z-index stacking -->
    <div id="profileDropdown" style="display:none; position:fixed; background: var(--card); border:1px solid var(--border); border-radius: 10px; min-width: 220px; max-width: 280px; z-index: 10000; box-shadow: 0 8px 30px rgba(0,0,0,0.18); padding: 6px 0; overflow: hidden;" data-testid="profile-dropdown">
      @if(Auth::user()->canInviteCollaborators())
      <a href="{{ route('admin.users.index') }}" class="dropdown-item" data-testid="menu-users">Utilisateurs</a>
      <a href="{{ route('admin.invitations.index') }}" class="dropdown-item" data-testid="menu-invitations">Invitations</a>
      @endif
      <a href="{{ route('password.change') }}" class="dropdown-item" data-testid="menu-password">Mot de passe</a>
      @if(Auth::user()->canAccessSubStoresDashboard())
      <a href="{{ route('sub-stores.dashboard') }}" class="dropdown-item" data-testid="menu-substores">Sub-Stores</a>
      @endif
      @if(Auth::user()->canAccessEklektikConfig())
      <a href="{{ route('admin.eklektik-cron') }}" class="dropdown-item" data-testid="menu-eklektik-config">Configuration Eklektik</a>
      <a href="{{ route('admin.eklektik.sync') }}" class="dropdown-item" data-testid="menu-eklektik-sync">Gestion Synchronisations</a>
      <a href="{{ route('admin.eklektik.sync-tracking') }}" class="dropdown-item" data-testid="menu-sync-tracking">Suivi Synchronisations</a>
      @endif
      <a href="{{ route('admin.ml.dashboard') }}" class="dropdown-item" data-testid="menu-ml-dashboard">Dashboard ML</a>
      <div style="border-top: 1px solid var(--border); margin: 4px 0;"></div>
      <form action="{{ route('auth.logout') }}" method="POST" style="margin: 0;">
        @csrf
        <button type="submit" class="dropdown-item" style="width:100%; text-align: left; color: var(--danger); border: none; background: none; cursor: pointer; font-size: 13px;" onclick="return confirm('Êtes-vous sûr de vouloir vous déconnecter ?')" data-testid="menu-logout">Déconnexion</button>
      </form>
    </div>

    <!-- Navigation Tabs - Groupée et simplifiée -->
    <div class="nav-wrapper">
      <div class="nav-tabs">
        <!-- Groupe: Données principales -->
        <div class="nav-group">
          <button class="nav-tab active" data-tab="overview" onclick="showTab('overview')">Overview</button>
          <button class="nav-tab" data-tab="subscriptions" onclick="showTab('subscriptions')">Subscriptions</button>
          <button class="nav-tab" data-tab="transactions" onclick="showTab('transactions')">Transactions</button>
          <button class="nav-tab" data-tab="merchants" onclick="showTab('merchants')">Merchants</button>
        </div>

        <!-- Séparateur -->
        <div class="nav-divider"></div>

        <!-- Groupe: Opérateurs -->
        <div class="nav-group">
          @if(Auth::user()->canViewTimweSection())
          <button class="nav-tab" data-tab="timwe" onclick="showTab('timwe')">Timwe</button>
          @endif
          @if(Auth::user()->canViewTimweSection())
          <button class="nav-tab" data-tab="ooredoo" onclick="showTab('ooredoo')">Ooredoo/DGV</button>
          @endif
          @if(Auth::user()->canViewEklektikSection())
          <button class="nav-tab" data-tab="eklektik" onclick="showTab('eklektik')">Eklektik</button>
          @endif
        </div>

        <!-- Séparateur -->
        <div class="nav-divider"></div>

        <!-- Groupe: Outils -->
        <div class="nav-group">
          <button class="nav-tab" data-tab="comparison" onclick="showTab('comparison')">Comparison</button>
          @if(Auth::user()->isSuperAdmin())
          <button class="nav-tab" data-tab="reporting" onclick="showTab('reporting')" data-testid="reporting-tab">Reporting</button>
          @endif
        </div>
      </div>
    </div>

    <!-- Bouton flottant Agent IA -->
    @if(Auth::user()->isSuperAdmin())
    <button class="ai-fab" onclick="toggleAIPanel()" title="Agent IA" data-testid="ai-agent-fab">
      <span class="ai-fab-tooltip">Agent IA</span>
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 2a2 2 0 012 2c0 .74-.4 1.39-1 1.73V7h1a7 7 0 017 7h1a1 1 0 110 2h-1.07A7 7 0 0113 22h-2a7 7 0 01-6.93-6H3a1 1 0 110-2h1a7 7 0 017-7h1V5.73A2 2 0 0112 2zm-1 9a1 1 0 100 2 1 1 0 000-2zm4 0a1 1 0 100 2 1 1 0 000-2z"/></svg>
    </button>

    <!-- Panel Agent IA (slide-in) -->
    <div class="ai-panel-overlay" id="aiPanelOverlay" onclick="toggleAIPanel()"></div>
    <div class="ai-panel" id="aiPanel">
      <div class="ai-panel-header">
        <h3>Agent IA</h3>
        <button class="ai-panel-close" onclick="toggleAIPanel()" data-testid="ai-panel-close">&times;</button>
      </div>
      <div class="ai-panel-body" id="aiPanelBody">
        <!-- Le contenu Agent IA sera déplacé ici dynamiquement -->
      </div>
    </div>
    @endif

    <script src="/js/dashboard/filters.js"></script>

    <!-- Enhanced Date & Filters Bar -->
    <div class="enhanced-filters-bar">
      <!-- Compact single-row layout -->
      <div class="filters-grid" style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 16px; align-items: end;">
        
        <!-- Période Principale -->
        <div>
          <div style="font-size: 0.7rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: var(--muted); margin-bottom: 6px;">Période Principale</div>
          <div style="display: flex; gap: 8px; align-items: center;">
            <input type="date" id="start-date" class="enhanced-date-input" onchange="updateDateRange()" style="flex:1;">
            <span style="color: var(--muted); font-size: 0.8rem;">au</span>
            <input type="date" id="end-date" class="enhanced-date-input" onchange="updateDateRange()" style="flex:1;">
          </div>
          <div class="period-display" id="primaryPeriod" style="font-size: 0.72rem; color: var(--muted); margin-top: 4px;"></div>
        </div>

        <!-- Période de Comparaison -->
        <div>
          <div style="font-size: 0.7rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: var(--muted); margin-bottom: 6px;">Comparaison</div>
          <div style="display: flex; gap: 8px; align-items: center;">
            <input type="date" id="comparison-start-date" class="enhanced-date-input" onchange="updateDateRange()" style="flex:1;">
            <span style="color: var(--muted); font-size: 0.8rem;">au</span>
            <input type="date" id="comparison-end-date" class="enhanced-date-input" onchange="updateDateRange()" style="flex:1;">
          </div>
          <div class="period-display" id="comparisonPeriod" style="font-size: 0.72rem; color: var(--muted); margin-top: 4px;"></div>
        </div>

        <!-- Actions inline -->
        <div style="display: flex; flex-direction: column; gap: 6px;">
          <button class="btn-primary enhanced-btn" onclick="autoCompareAndLoad()" id="refresh-btn" style="justify-content: center;">
            <span id="refresh-text">Actualiser</span>
            <span id="refresh-loading" style="display: none;">Chargement...</span>
          </button>
          <div style="display: flex; gap: 4px;">
            <button class="btn-secondary enhanced-btn" onclick="setSmartComparison()" style="font-size: 0.72rem; padding: 4px 8px;">Auto</button>
            <button class="btn-secondary enhanced-btn" onclick="toggleDatePickerMode()" style="font-size: 0.72rem; padding: 4px 8px;">Raccourcis</button>
          </div>
        </div>
      </div>

      <!-- Operator selector row -->
      <div class="operator-row" style="display: flex; align-items: center; gap: 12px; margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--border);">
        <div style="font-size: 0.7rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: var(--muted); white-space: nowrap;">Opérateurs</div>
        <div class="multi-select-container" style="flex: 1; max-width: 320px;">
          <div class="multi-select-header" onclick="toggleOperatorDropdown()">
            <span id="selected-operators-text">Tous les opérateurs</span>
            <span class="dropdown-arrow">&#9662;</span>
          </div>
          <div id="operators-dropdown" class="multi-select-dropdown" style="display: none;">
            <div class="select-all-option">
              <label class="checkbox-label">
                <input type="checkbox" id="select-all-operators" onchange="handleSelectAllOperators()" checked>
                <span class="checkmark"></span>
                <span>Tous les opérateurs</span>
              </label>
            </div>
            <div class="operators-list" id="operators-list">
            </div>
          </div>
        </div>
        <div id="operator-info" class="control-info" style="font-size: 0.72rem; color: var(--muted);">
          Chargement des opérateurs...
        </div>
        <div style="margin-left: auto;">
          <button class="btn-secondary enhanced-btn" onclick="showKeyboardShortcutsHelp()" style="font-size: 0.72rem; padding: 4px 10px;">Aide</button>
        </div>
      </div>
    </div>

    <!-- Tab 1: Overview -->
    <div id="overview" class="tab-content active">
      <!-- KPIs Row 1 (4 KPI) -->
      <div class="grid">
        <div class="card kpi-card">
          <div class="kpi-title">Activated Subscriptions <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Nombre d'abonnements dont la date de création tombe dans la période sélectionnée (client_abonnement_creation ∈ [start, end)). Tous opérateurs ou filtrés selon le filtre actif.">ⓘ</span></div>
          <div class="kpi-value" id="activatedSubscriptions">Loading...</div>
          <div class="kpi-delta" id="activatedSubscriptionsDelta">Loading...</div>
        </div>
        <div class="card kpi-card">
          <div class="kpi-title">Active Subscriptions <span style="margin-left:4px; cursor: help; color: var(--muted);" title="COHORTE PÉRIODE : Abonnements créés dans [start, end) ET encore actifs à la fin de la période (expiration NULL ou >= end). Ce n'est PAS la base active totale — uniquement les nouveaux de la période qui sont restés.">ⓘ</span></div>
          <div class="kpi-value" id="activeSubscriptions">Loading...</div>
          <div class="kpi-delta" id="activeSubscriptionsDelta">Loading...</div>
        </div>
        <div class="card kpi-card">
          <div class="kpi-title">Retention Rate <span style="margin-left:4px; cursor: help; color: var(--muted);" title="(Active Subscriptions / Activated Subscriptions) × 100. Pourcentage des NOUVEAUX abonnés de la période qui sont encore actifs à la fin. Formule : cohorte active ÷ cohorte activée.">ⓘ</span></div>
          <div class="kpi-value" id="overview-retentionRate">Loading...</div>
          <div class="kpi-delta" id="overview-retentionRateDelta">Loading...</div>
        </div>
        <div class="card kpi-card">
          <div class="kpi-title">Conversion Rate <span style="margin-left:4px; cursor: help; color: var(--muted);" title="(Transacting Users période / Active Subscriptions cohorte) × 100. Part des utilisateurs ayant transigé parmi les abonnés de la cohorte encore actifs.">ⓘ</span></div>
          <div class="kpi-value" id="conversionRate">Loading...</div>
          <div class="progress-bar">
            <div class="progress-fill" id="overview-conversionProgress" style="width: 0%"></div>
          </div>
          <div style="font-size: 12px; color: var(--muted); margin-top: 4px;">Target: 30%</div>
        </div>
      </div>

      <!-- KPIs Row 2 (4 KPI) -->
      <div class="grid">
        <div class="card kpi-card">
          <div class="kpi-title">Total Transactions <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Nombre total d’achats effectués pendant la période.">ⓘ</span></div>
          <div class="kpi-value" id="totalTransactions">Loading...</div>
          <div class="kpi-delta" id="totalTransactionsDelta">Loading...</div>
        </div>
        <div class="card kpi-card">
          <div class="kpi-title">Cohort Transactions <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Transactions effectuées par les abonnements dont la date de création est dans [start,end).">ⓘ</span></div>
          <div class="kpi-value" id="cohortTransactions">Loading...</div>
          <div class="kpi-delta" id="cohortTransactionsDelta">Loading...</div>
        </div>
        <div class="card kpi-card">
          <div class="kpi-title">Transacting Users (Période) <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Nombre total d'utilisateurs transigeants durant la période.">ⓘ</span></div>
          <div class="kpi-value" id="totalTransactingUsers">Loading...</div>
          <div class="kpi-delta" id="totalTransactingUsersDelta">Loading...</div>
        </div>
        <div class="card kpi-card">
          <div class="kpi-title">Transacting Users (Cohorte) <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Clients de la cohorte (créés dans [start,end)) ayant transigé dans la période.">ⓘ</span></div>
          <div class="kpi-value" id="cohortTransactingUsers">Loading...</div>
          <div class="kpi-delta" id="cohortTransactingUsersDelta">Loading...</div>
        </div>
        </div>

        <!-- Overview Chart -->
      <div class="grid">
        <div class="card chart-card full-width">
          <div class="chart-title">Performance Overview - Period Comparison <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Comparaison visuelle des KPIs principaux entre la période actuelle (bleu) et la période de comparaison (gris). Permet d'identifier rapidement les tendances.">ⓘ</span></div>
          <div class="chart-container">
            <canvas id="overviewChart"></canvas>
          </div>
        </div>
      </div>

      <!-- Global snapshots row removed on request -->
    </div>

    <!-- Tab 2: Detailed Subscription Analysis -->
    <div id="subscriptions" class="tab-content">
      <!-- Subscriptions KPIs: Row 1 (4 KPI) -->
      <div class="sub-kpis-row">
        <div class="card kpi-card">
          <div class="kpi-title">Activated Subscriptions <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Nombre total d'abonnements créés durant la période sélectionnée (client_abonnement_creation ∈ [start, end)). Identique à l'Overview.">ⓘ</span></div>
          <div class="kpi-value" id="sub-activatedSubscriptions">Loading...</div>
          <div class="kpi-delta" id="sub-activatedSubscriptionsDelta">Loading...</div>
        </div>
        <div class="card kpi-card">
          <div class="kpi-title">Active Subscriptions <span style="margin-left:4px; cursor: help; color: var(--muted);" title="COHORTE : Abonnements créés dans la période ET encore actifs à la fin (expiration NULL ou >= end). Ne représente PAS la base active totale, uniquement la rétention de la cohorte.">ⓘ</span></div>
          <div class="kpi-value" id="sub-activeSubscriptions">Loading...</div>
          <div class="kpi-delta" id="sub-activeSubscriptionsDelta">Loading...</div>
        </div>
        <div class="card kpi-card">
          <div class="kpi-title">Retention Rate <span style="margin-left:4px; cursor: help; color: var(--muted);" title="(Active Subs cohorte / Activated Subs) × 100. Sur 100 nouveaux abonnés de la période, combien sont encore actifs à la fin.">ⓘ</span></div>
          <div class="kpi-value" id="sub-retentionRate">Loading...</div>
          <div class="kpi-delta" id="sub-retentionRateDelta">Loading...</div>
        </div>
        <div class="card kpi-card">
          <div class="kpi-title">Conversion Rate (Période) <span style="margin-left:4px; cursor: help; color: var(--muted);" title="(Transacting Users période / Active Subs cohorte) × 100. Parmi les abonnés de la cohorte encore actifs, part de ceux ayant effectué au moins une transaction.">ⓘ</span></div>
          <div class="kpi-value" id="sub-conversionRate">Loading...</div>
          <div class="kpi-delta" id="sub-conversionRateDelta">Loading...</div>
        </div>
      </div>

      <!-- Subscriptions KPIs: Row 2 (2 KPI) -->
      <div class="sub-kpis-row">
        <div class="card kpi-card">
          <div class="kpi-title">Deactivated (Période) <span style="margin-left:4px; cursor: help; color: var(--muted);" title="TOUS les abonnements dont la date d'expiration tombe dans la période, quelle que soit leur date de création.">ⓘ</span></div>
          <div class="kpi-value" id="sub-deactivatedSubscriptions">Loading...</div>
          <div class="kpi-delta" id="sub-deactivatedSubscriptionsDelta">Loading...</div>
        </div>
        <div class="card kpi-card">
          <div class="kpi-title">Deactivated (Cohorte) <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Abonnements créés dans [start, end) ET dont la date d'expiration tombe aussi dans cette même période. Sous-ensemble de la cohorte.">ⓘ</span></div>
          <div class="kpi-value" id="sub-lostSubscriptions">Loading...</div>
          <div class="kpi-delta" id="sub-lostSubscriptionsDelta">Loading...</div>
        </div>
        <div class="card kpi-card">
          <div class="kpi-title">Taux de churn <span style="margin-left:4px; cursor: help; color: var(--muted);" title="(Deactivated cohorte / Activated) × 100. Part des nouveaux abonnés de la cohorte qui ont été désactivés durant la période.">ⓘ</span></div>
          <div class="kpi-value" id="sub-retentionRateTrue">Loading...</div>
          <div class="kpi-delta" id="sub-retentionRateTrueDelta">Loading...</div>
        </div>
        <div class="card kpi-card">
          <div class="kpi-title">Transactions (Période) <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Nombre total de transactions effectuées dans la période (history.time ∈ [start, end)), tous abonnés confondus.">ⓘ</span></div>
          <div class="kpi-value" id="sub-totalTransactions">Loading...</div>
          <div class="kpi-delta" id="sub-totalTransactionsDelta">Loading...</div>
        </div>
        </div>

      <!-- Subscription Trends (two charts side by side) -->
      <div class="grid">
        <div class="card chart-card">
          <div class="chart-title">Retention Rate Trend <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Part des abonnés qui restent actifs au fil du temps. Plus la courbe est haute, plus les clients restent.">ⓘ</span></div>
          <div class="chart-container">
            <canvas id="retentionChart"></canvas>
          </div>
        </div>

        <div class="card chart-card">
          <div class="chart-title">Daily Activated Subscriptions <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Nombre d'abonnements commencés chaque jour. Un pic = beaucoup de nouveaux inscrits ce jour-là.">ⓘ</span></div>
          <div class="chart-container">
            <canvas id="subscriptionTrendChart"></canvas>
          </div>
          </div>
        </div>

      <!-- Nouveaux KPIs Avancés - Masqué pour les collaborateurs -->
      @if(!Auth::user()->isCollaborator())
      <div class="grid" style="margin-top: 20px;">
        <h3 style="grid-column: 1 / -1; margin-bottom: 15px; color: var(--text); font-size: 18px; font-weight: 600;">📊 Analyses Avancées</h3>
        
        <!-- Activations par Canal -->
        <div class="card kpi-card">
          <div class="kpi-title">Activations CB <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Nombre d’abonnements commencés via carte bancaire.">ⓘ</span></div>
          <div class="kpi-value" id="sub-activationsCB">Loading...</div>
          <div class="kpi-delta" id="sub-activationsCBDelta">Loading...</div>
        </div>
        <div class="card kpi-card">
          <div class="kpi-title">Activations Recharge <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Nombre d’abonnements commencés via recharge.">ⓘ</span></div>
          <div class="kpi-value" id="sub-activationsRecharge">Loading...</div>
          <div class="kpi-delta" id="sub-activationsRechargeDelta">Loading...</div>
        </div>
        <div class="card kpi-card">
          <div class="kpi-title">Activations Solde Tél. <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Nombre d’abonnements commencés via solde téléphonique.">ⓘ</span></div>
          <div class="kpi-value" id="sub-activationsPhone">Loading...</div>
          <div class="kpi-delta" id="sub-activationsPhoneDelta">Loading...</div>
        </div>

        <!-- Répartition par Plan -->
        <div class="card kpi-card">
          <div class="kpi-title">Plans Journaliers <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Combien d’abonnements de type journalier ont été pris.">ⓘ</span></div>
          <div class="kpi-value" id="sub-plansDaily">Loading...</div>
          <div class="kpi-delta" id="sub-plansDailyDelta">Loading...</div>
        </div>
        <div class="card kpi-card">
          <div class="kpi-title">Plans Mensuels <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Combien d’abonnements mensuels ont été pris.">ⓘ</span></div>
          <div class="kpi-value" id="sub-plansMonthly">Loading...</div>
          <div class="kpi-delta" id="sub-plansMonthlyDelta">Loading...</div>
        </div>
        <div class="card kpi-card">
          <div class="kpi-title">Plans Annuels <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Combien d’abonnements annuels ont été pris.">ⓘ</span></div>
          <div class="kpi-value" id="sub-plansAnnual">Loading...</div>
          <div class="kpi-delta" id="sub-plansAnnualDelta">Loading...</div>
        </div>

        <!-- Métriques de Performance -->
        <div class="card kpi-card">
          <div class="kpi-title">Taux de Renouvellement <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Part des abonnements qui ont été repris à la fin de la période.">ⓘ</span></div>
          <div class="kpi-value" id="sub-renewalRate">Loading...</div>
          <div class="kpi-delta" id="sub-renewalRateDelta">Loading...</div>
        </div>
        <div class="card kpi-card">
          <div class="kpi-title">Durée de Vie Moyenne <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Nombre moyen de jours pendant lesquels un abonnement reste actif.">ⓘ</span></div>
          <div class="kpi-value" id="sub-averageLifespan">Loading...</div>
          <div class="kpi-delta" id="sub-averageLifespanDelta">Loading...</div>
        </div>
        
      </div>
      @endif

      <!-- Graphiques Avancés - Masqués pour les collaborateurs -->
      @if(!Auth::user()->isCollaborator())
      <div class="grid" style="margin-top: 20px;">
        <div class="card chart-card">
          <div class="chart-title">Répartition des Activations par Canal <span style="margin-left:4px; cursor: help; color: var(--muted);" title="D'où viennent les activations: carte, recharge, solde téléphonique…">ⓘ</span></div>
          <div class="chart-container">
            <canvas id="activationsByChannelChart"></canvas>
          </div>
        </div>

        <div class="card chart-card">
          <div class="chart-title">Distribution des Plans d'Abonnement <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Quels types de plans (journalier, mensuel, annuel) sont le plus choisis.">ⓘ</span></div>
          <div class="chart-container">
            <canvas id="planDistributionChart"></canvas>
          </div>
        </div>

        <div class="card chart-card">
          <div class="chart-title">Analyse de Cohortes - Survie J+30/J+60 <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Comment les groupes d'inscrits par date continuent d'utiliser le service après 30/60 jours.">ⓘ</span></div>
          <div class="chart-container">
            <canvas id="cohortsAnalysisChart"></canvas>
          </div>
        </div>
      </div>
      @endif

      <!-- Tableau des abonnements (détails) -->
      <div class="card table-card" style="margin-top: 20px;">
        <div class="table-header">
          <div class="table-title">📋 Détails des Abonnements</div>
                  <div class="table-controls">
          <select class="table-pagination" onchange="changeSubscriptionsPerPage(this.value)">
            <option value="25">25 par page</option>
            <option value="50">50 par page</option>
            <option value="100">100 par page</option>
          </select>
          <button class="export-btn">Exporter</button>
        </div>
        </div>
        <div class="table-container table-wrapper">
          <table class="enhanced-table">
            <thead>
              <tr>
                <th>Client</th>
                <th>Téléphone</th>
                <th>Opérateur</th>
                <th>Plan</th>
                <th>Date Activation</th>
                <th>Date Fin</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="subs-details-body">
              <tr><td colspan="7" class="loading">Chargement...</td></tr>
            </tbody>
          </table>
        </div>
        <div class="subscriptions-pagination"></div>
      </div>

    </div>

    <!-- Tab 3: Detailed Transaction Analysis -->
    <div id="transactions" class="tab-content">
      <div class="trans-kpis-row">
        <!-- Transaction KPIs -->
        <div class="card kpi-card">
          <div class="kpi-title">Total Transactions <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Nombre total de transactions effectuées dans la période (history.time ∈ [start, end)), tous abonnés confondus.">ⓘ</span></div>
          <div class="kpi-value" id="trans-totalTransactions">Loading...</div>
          <div class="kpi-delta" id="trans-totalTransactionsDelta">Loading...</div>
        </div>
        <div class="card kpi-card">
          <div class="kpi-title">Total Transactions (Cohorte) <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Transactions effectuées par les abonnés dont la date de création ET la date de transaction tombent dans [start, end). Sous-ensemble des Total Transactions.">ⓘ</span></div>
          <div class="kpi-value" id="trans-cohortTransactions">Loading...</div>
          <div class="kpi-delta" id="trans-cohortTransactionsDelta">Loading...</div>
        </div>
        <div class="card kpi-card">
          <div class="kpi-title">Transacting Users (Période) <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Nombre d'utilisateurs uniques (client_id distincts) ayant effectué au moins une transaction dans la période, tous abonnements confondus.">ⓘ</span></div>
          <div class="kpi-value" id="trans-transactingUsers">Loading...</div>
          <div class="kpi-delta" id="trans-transactingUsersDelta">Loading...</div>
        </div>
        <div class="card kpi-card">
          <div class="kpi-title">Transacting Users (Cohorte) <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Clients de la cohorte (créés dans [start,end)) ayant transigé dans la période.">ⓘ</span></div>
          <div class="kpi-value" id="trans-cohortTransactingUsers">Loading...</div>
          <div class="kpi-delta" id="trans-cohortTransactingUsersDelta">Loading...</div>
        </div>
      </div>

      <!-- Transactions KPIs: Row 2 (4 KPI alignés comme Overview) -->
      <div class="trans-kpis-row">
        <div class="card kpi-card">
          <div class="kpi-title">Conversion Rate (Cohorte) <span style="margin-left:4px; cursor: help; color: var(--muted);" title="(Transacting Users Cohorte / Active Subscriptions Cohorte) × 100. Part des abonnés de la cohorte encore actifs qui ont transigé.">ⓘ</span></div>
          <div class="kpi-value" id="trans-convCohort">Loading...</div>
          <div class="kpi-delta" id="trans-convCohortDelta">Loading...</div>
        </div>
        <div class="card kpi-card">
          <div class="kpi-title">Conversion Rate (Période) <span style="margin-left:4px; cursor: help; color: var(--muted);" title="(Transacting Users Période / Active Subscriptions Cohorte) × 100. Part de TOUS les utilisateurs ayant transigé, rapportée à la cohorte active.">ⓘ</span></div>
          <div class="kpi-value" id="trans-convPeriod">Loading...</div>
          <div class="kpi-delta" id="trans-convPeriodDelta">Loading...</div>
        </div>
        <div class="card kpi-card">
          <div class="kpi-title">Transactions/User <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Total Transactions / Transacting Users (Période). Nombre moyen de passages en caisse par utilisateur actif.">ⓘ</span></div>
          <div class="kpi-value" id="trans-transactionsPerUser">Loading...</div>
          <div class="kpi-delta" id="trans-transactionsPerUserDelta">Loading...</div>
        </div>
        <div class="card kpi-card">
          <div class="kpi-title">Avg. Durée entre 2 transactions <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Durée moyenne en jours entre deux transactions consécutives pour un même utilisateur. Plus cette valeur est basse, plus les utilisateurs sont fréquents.">ⓘ</span></div>
          <div class="kpi-value" id="trans-avgInterTxDays">Loading...</div>
          <div class="kpi-delta" id="trans-avgInterTxDaysDelta">Loading...</div>
          </div>
        </div>

      <div class="grid">

        <!-- Transaction Charts -->
        <div class="card chart-card">
          <div class="chart-title">Daily Transaction Volume <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Nombre d'achats/passages en caisse effectués chaque jour.">ⓘ</span></div>
          <div class="chart-container">
            <canvas id="transactionVolumeChart"></canvas>
          </div>
        </div>

        <div class="card chart-card">
          <div class="chart-title">Transacting Users Trend <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Combien de personnes ont payé au moins une fois chaque jour.">ⓘ</span></div>
          <div class="chart-container">
            <canvas id="transactingUsersChart"></canvas>
          </div>
        </div>

        <!-- Cumulative Charts (separated) -->
        <div class="card chart-card">
          <div class="chart-title">Cumulative Transactions <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Total qui s'ajoute jour après jour. Comme un compteur qui monte.">ⓘ</span></div>
          <div class="chart-container">
            <canvas id="transactionVolumeCumulativeChart"></canvas>
          </div>
        </div>

        <div class="card chart-card">
          <div class="chart-title">Cumulative Transacting Users <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Nombre de personnes uniques cumulées qui ont payé au fil des jours.">ⓘ</span></div>
          <div class="chart-container">
            <canvas id="transactingUsersCumulativeChart"></canvas>
          </div>
        </div>

        <!-- Nouveaux graphiques d'analyse des transactions - Masqués pour les collaborateurs -->
        @if(!Auth::user()->isCollaborator())
        <div class="card chart-card">
          <div class="chart-title">📊 Transactions par Opérateurs <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Répartition des transactions par moyen de paiement/opérateur.">ⓘ</span></div>
          <div class="chart-container">
            <canvas id="transactionsByOperatorChart"></canvas>
          </div>
        </div>

        <div class="card chart-card">
          <div class="chart-title">📋 Transactions par Plans d'Abonnement <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Combien de transactions pour chaque type de plan.">ⓘ</span></div>
          <div class="chart-container">
            <canvas id="transactionsByPlanChart"></canvas>
          </div>
        </div>
        @endif
      </div>
    </div>

    <!-- Tab 4: Merchant Analysis -->
    <div id="merchants" class="tab-content">
      <!-- KPIs Section - 8 cartes (2 lignes de 4) -->
      <div class="merchants-kpis-row">
        <div class="card kpi-card merchants-kpi">
          <div class="kpi-icon">🏪</div>
          <div class="kpi-content">
            <div class="kpi-title">Total Merchants <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Nombre total de partenaires (table partner).">ⓘ</span></div>
            <div class="kpi-value" id="merch-totalPartners">Loading...</div>
            <div class="kpi-delta" id="merch-totalPartnersDelta">→ 0.0%</div>
          </div>
        </div>
        <div class="card kpi-card merchants-kpi">
          <div class="kpi-icon">📈</div>
          <div class="kpi-content">
            <div class="kpi-title">Active Merchants <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Marchands ayant eu au moins une transaction dans la période (history.time ∈ [start,end)).">ⓘ</span></div>
            <div class="kpi-value" id="merch-activeMerchants">Loading...</div>
            <div class="kpi-delta" id="merch-activeMerchantsDelta">Loading...</div>
          </div>
        </div>
        <div class="card kpi-card merchants-kpi">
          <div class="kpi-icon">📍</div>
          <div class="kpi-content">
            <div class="kpi-title">Total Points de Vente <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Nombre total de points de vente (partner_location) des marchands actifs.">ⓘ</span></div>
            <div class="kpi-value" id="merch-totalLocationsActive">Loading...</div>
            <div class="kpi-delta" id="merch-totalLocationsActiveDelta">→ 0.0%</div>
          </div>
        </div>
        <div class="card kpi-card merchants-kpi">
          <div class="kpi-icon">% </div>
          <div class="kpi-content">
            <div class="kpi-title">Active Merchant Ratio <span style="margin-left:4px; cursor: help; color: var(--muted);" title="(Active Merchants) / (Total Merchants) × 100.">ⓘ</span></div>
            <div class="kpi-value" id="merch-activeMerchantRatio">Loading...</div>
            <div class="kpi-delta" id="merch-activeMerchantRatioDelta">Loading...</div>
          </div>
        </div>
      </div>

      <div class="merchants-kpis-row">
        <div class="card kpi-card merchants-kpi">
          <div class="kpi-icon">🔢</div>
          <div class="kpi-content">
            <div class="kpi-title">Total Transactions <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Nombre total de transactions effectuées dans la période (history.time ∈ [start, end)), tous abonnés confondus.">ⓘ</span></div>
            <div class="kpi-value" id="merch-totalTransactions">Loading...</div>
            <div class="kpi-delta" id="merch-totalTransactionsDelta">Loading...</div>
          </div>
        </div>
        <div class="card kpi-card merchants-kpi">
          <div class="kpi-icon">💳</div>
          <div class="kpi-content">
            <div class="kpi-title">Transactions/Merchant <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Transactions opérateur chez marchands / Marchands actifs (période).">ⓘ</span></div>
            <div class="kpi-value" id="merch-transactionsPerMerchant">Loading...</div>
            <div class="kpi-delta" id="merch-transactionsPerMerchantDelta">Loading...</div>
          </div>
        </div>
        <div class="card kpi-card merchants-kpi">
          <div class="kpi-icon">🏆</div>
          <div class="kpi-content">
            <div class="kpi-title">Top Merchant <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Meilleur marchand par volume (part de marché période).">ⓘ</span></div>
            <div class="kpi-value" id="merch-topMerchantShare">Loading...</div>
            <div class="kpi-delta" id="merch-topMerchantName">Loading...</div>
          </div>
        </div>
        <div class="card kpi-card merchants-kpi">
          <div class="kpi-icon">🎯</div>
          <div class="kpi-content">
            <div class="kpi-title">Diversity <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Niveau basé sur le nombre de marchands actifs (période).">ⓘ</span></div>
            <div class="kpi-value" id="merch-diversity">Loading...</div>
            <div class="kpi-delta" id="merch-diversityDetail">Loading...</div>
          </div>
        </div>
      </div>

      <!-- Charts Section - 2 graphiques côte à côte -->
      <div class="merchants-charts-row">
        <div class="card chart-card merchants-chart">
          <div class="chart-header">
            <div class="chart-title">🏪 Top Merchants by Volume <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Les marchands avec le plus de transactions dans la période.">ⓘ</span></div>
            <div class="chart-subtitle">Transactions par marchand</div>
          </div>
          <div class="chart-container">
            <canvas id="topMerchantsChart"></canvas>
          </div>
        </div>

        <div class="card chart-card merchants-chart">
          <div class="chart-header">
            <div class="chart-title">📊 Distribution by Category <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Classement des transactions par types de magasins (ex: restaurants, mode).">ⓘ</span></div>
            <div class="chart-subtitle">Répartition par catégorie</div>
          </div>
          <div class="chart-container">
            <canvas id="categoryChart"></canvas>
          </div>
        </div>
      </div>

      <!-- Ligne suivante: évolution points de vente actifs -->
      <div class="merchants-charts-row">
        <div class="card chart-card">
          <div class="chart-title">Active Points of Sale Over Time <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Nombre de boutiques actives visibles trimestre par trimestre.">ⓘ</span></div>
          <div class="chart-container">
            <canvas id="activeLocationsTrend"></canvas>
          </div>
        </div>
      </div>

      <!-- Table Section - Tableau pleine largeur -->
      <div class="merchants-table-section">
        <div class="card table-card merchants-table">
          <div class="table-header">
            <div class="table-title">📋 Performance Détaillée des Marchands</div>
            <div class="table-actions">
              <select id="merchantsPerPage" onchange="changeMerchantsPerPage()" style="margin-right: 10px; padding: 4px 8px; border: 1px solid var(--border); border-radius: 4px;">
                <option value="10">10 par page</option>
                <option value="25" selected>25 par page</option>
                <option value="50">50 par page</option>
                <option value="100">100 par page</option>
              </select>
              <button class="btn-secondary" onclick="exportMerchantsData()">📥 Exporter</button>
            </div>
          </div>
          <div class="table-container table-wrapper">
            <table class="enhanced-table">
              <thead>
                <tr>
                  <th>Merchant</th>
                  <th>Category</th>
                  <th>Current</th>
                  <th>Previous</th>
                  <th>Change</th>
                  <th>Market Share</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody id="merchantsTableBody">
                <tr>
                  <td colspan="7" class="loading">
                    <div class="spinner"></div>
                    Chargement des données marchands...
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          
          <!-- Pagination Controls -->
          <div class="pagination-controls" style="display: flex; justify-content: space-between; align-items: center; padding: 16px; border-top: 1px solid var(--border);">
            <div class="pagination-info">
              <span id="merchantsPaginationInfo">Affichage de 1-25 sur 0 marchands</span>
            </div>
            <div class="pagination-buttons">
              <button id="merchantsPrevBtn" onclick="previousMerchantsPage()" style="padding: 8px 12px; margin-right: 8px; border: 1px solid var(--border); border-radius: 4px; background: var(--card); cursor: pointer;" disabled>
                ← Précédent
              </button>
              <span id="merchantsPageNumbers" style="margin: 0 16px; font-weight: 500;"></span>
              <button id="merchantsNextBtn" onclick="nextMerchantsPage()" style="padding: 8px 12px; margin-left: 8px; border: 1px solid var(--border); border-radius: 4px; background: var(--card); cursor: pointer;" disabled>
                Suivant →
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Tab 5: Eklektik Integration -->
    @if(Auth::user()->canViewEklektikSection())
    <div id="eklektik" class="tab-content">


      <!-- Statistiques Eklektik KPIs - 8 KPIs sur 2 lignes -->
      <div class="grid">
        <!-- Première ligne - 4 KPIs -->
        <div class="card kpi-card" style="grid-column: span 3;">
          <div class="kpi-title">Revenus TTC <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Revenus Toutes Taxes Comprises générés via la plateforme Eklektik pour la période sélectionnée (somme des montants facturés).">ⓘ</span></div>
          <div class="kpi-value" id="eklektik-revenue-ttc">Loading...</div>
          <div class="kpi-delta" id="eklektik-revenue-ttc-delta">Loading...</div>
        </div>
        <div class="card kpi-card" style="grid-column: span 3;">
          <div class="kpi-title">Revenus HT <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Revenus Hors Taxes calculés en appliquant les formules contractuelles spécifiques à chaque opérateur (TVA déduite selon les taux applicables).">ⓘ</span></div>
          <div class="kpi-value" id="eklektik-revenue-ht">Loading...</div>
          <div class="kpi-delta" id="eklektik-revenue-ht-delta">Loading...</div>
        </div>
        <div class="card kpi-card" style="grid-column: span 3;">
          <div class="kpi-title">CA BigDeal <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Part du chiffre d'affaires revenant à BigDeal selon les termes contractuels avec chaque opérateur (pourcentage du Revenu HT).">ⓘ</span></div>
          <div class="kpi-value" id="eklektik-ca-bigdeal">Loading...</div>
          <div class="kpi-delta" id="eklektik-ca-bigdeal-delta">Loading...</div>
        </div>
        <div class="card kpi-card" style="grid-column: span 3;">
          <div class="kpi-title">Active Subs <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Nombre total d'abonnés actifs sur la plateforme Eklektik à la fin de la période sélectionnée.">ⓘ</span></div>
          <div class="kpi-value" id="eklektik-active-subs">Loading...</div>
          <div class="kpi-delta" id="eklektik-active-subs-delta">Loading...</div>
        </div>
      </div>

      <div class="grid">
        <!-- Deuxième ligne - 4 KPIs -->
        <div class="card kpi-card" style="grid-column: span 3;">
          <div class="kpi-title">Nouveaux Abonnements <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Nouveaux abonnements créés">ⓘ</span></div>
          <div class="kpi-value" id="eklektik-new-subscriptions">Loading...</div>
          <div class="kpi-delta" id="eklektik-new-subscriptions-delta">Loading...</div>
        </div>
        <div class="card kpi-card" style="grid-column: span 3;">
          <div class="kpi-title">Désabonnements <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Nombre de désabonnements">ⓘ</span></div>
          <div class="kpi-value" id="eklektik-unsubscriptions">Loading...</div>
          <div class="kpi-delta" id="eklektik-unsubscriptions-delta">Loading...</div>
        </div>
        <div class="card kpi-card" style="grid-column: span 3;">
          <div class="kpi-title">Simchurn <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Perte d'abonnés (Simchurn)">ⓘ</span></div>
          <div class="kpi-value" id="eklektik-simchurn">Loading...</div>
          <div class="kpi-delta" id="eklektik-simchurn-delta">Loading...</div>
        </div>
        <div class="card kpi-card" style="grid-column: span 3;">
          <div class="kpi-title">Abonnements Facturés <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Nombre total d'abonnements facturés">ⓘ</span></div>
          <div class="kpi-value" id="eklektik-facturation">Loading...</div>
          <div class="kpi-delta" id="eklektik-facturation-delta">Loading...</div>
        </div>
      </div>

      <!-- Graphiques Eklektik - Utilisation du composant optimisé -->
      <div class="grid">
        <div class="card" style="grid-column: span 12;">
          <div class="chart-title">
            📊 Graphiques Eklektik Optimisés
            <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Graphiques Eklektik optimisés pour éliminer le sautillement">ⓘ</span>
          </div>
          {{-- Utiliser le composant graphiques Eklektik --}}
          <x-eklektik-charts />
        </div>
      </div>

      <div class="grid">
        <div class="card" style="grid-column: span 12;">
          <div class="chart-title" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
            <span>Statistiques Quotidiennes Eklektik</span>
            <div style="display: flex; gap: 8px;">
              <button onclick="exportEklektikStatsToExcel()" class="btn-sm" style="padding: 4px 10px; background: var(--accent); color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px;" data-testid="eklektik-export-btn">Export CSV</button>
              <button onclick="copyEklektikStatsToClipboard()" class="btn-sm" style="padding: 4px 10px; background: var(--brand-primary); color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px;" data-testid="eklektik-copy-btn">Copier</button>
            </div>
          </div>
          <div style="overflow-x: auto; -webkit-overflow-scrolling: touch;">
            <table class="stats-table" style="width: 100%; border-collapse: collapse; font-size: 13px; min-width: 700px;" data-testid="eklektik-daily-stats-table">
              <thead>
                <tr style="background: var(--card); border-bottom: 2px solid var(--border);">
                  <th style="padding: 10px; width: 30px;"></th>
                  <th style="padding: 10px; text-align: left;">Période</th>
                  <th style="padding: 10px; text-align: center;">New Sub</th>
                  <th style="padding: 10px; text-align: center;">Renewals</th>
                  <th style="padding: 10px; text-align: center;">Unsub</th>
                  <th style="padding: 10px; text-align: center;">Active Sub</th>
                  <th style="padding: 10px; text-align: center;">NB Facturation</th>
                  <th style="padding: 10px; text-align: center;">Taux Fact. %</th>
                  <th style="padding: 10px; text-align: center;">Revenu TTC (TND)</th>
                  <th style="padding: 10px; text-align: center;">CA BigDeal (TND)</th>
                </tr>
              </thead>
              <tbody id="eklektikStatsTableBody">
                <tr><td colspan="10" class="no-data" style="text-align: center; padding: 40px; color: var(--muted);">Chargement...</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>


    </div>
    @endif

    <!-- Tab 5: Timwe Integration (Super Admin Only) -->
    @if(Auth::user()->canViewTimweSection())
    <div id="timwe" class="tab-content">

      <!-- En-tête Timwe avec lien Diagnostic -->
      @if(Auth::user()->isSuperAdmin())
      <div style="display: flex; justify-content: flex-end; margin-bottom: 12px;">
        <a href="{{ route('admin.timwe-diagnostic') }}" class="timwe-diagnostic-link" data-testid="timwe-diagnostic-link">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
          Diagnostic Timwe
        </a>
      </div>
      @endif

      <!-- Statistiques Timwe KPIs - 3 lignes de KPIs -->
      <div class="grid">
        <!-- Première ligne - 4 KPIs principaux -->
        <div class="card kpi-card" style="grid-column: span 3;">
          <div class="kpi-title">Taux de Facturation <span style="margin-left:4px; cursor: help; color: var(--muted);" title="(Nombre de facturations réussies / Base Active Totale Timwe) × 100. Critères : pricepointId=63980 ET mnoDeliveryCode=DELIVERED uniquement.">ⓘ</span></div>
          <div class="kpi-value" id="timwe-billing-rate">Loading...</div>
        </div>
        <div class="card kpi-card" style="grid-column: span 3;">
          <div class="kpi-title">Taux de Croissance Nette <span style="margin-left:4px; cursor: help; color: var(--muted);" title="((Nouveaux Abonnements - Désabonnements - Simchurn) / Active Subscriptions) × 100. Indique la croissance nette du portefeuille client.">ⓘ</span></div>
          <div class="kpi-value" id="timwe-net-growth-rate">Loading...</div>
          <div class="kpi-delta" id="timwe-net-growth-rate-delta">Loading...</div>
        </div>
        <div class="card kpi-card" style="grid-column: span 3;">
          <div class="kpi-title">Nombre Facturation <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Nombre total de transactions de facturation réussies (pricepointId=63980 ET mnoDeliveryCode=DELIVERED)">ⓘ</span></div>
          <div class="kpi-value" id="timwe-total-billings">Loading...</div>
          <div class="kpi-delta" id="timwe-total-billings-delta">Loading...</div>
        </div>
        <div class="card kpi-card" style="grid-column: span 3;">
          <div class="kpi-title">Active Subscriptions <span style="margin-left:4px; cursor: help; color: var(--muted);" title="BASE ACTIVE TOTALE Timwe au dernier jour de la période (source : timwe_daily_stats). Inclut TOUS les abonnés actifs quelle que soit leur date d'activation. Diffère de l'Overview qui montre uniquement la cohorte de la période.">ⓘ</span></div>
          <div class="kpi-value" id="timwe-active-subs">Loading...</div>
        </div>
      </div>

      <div class="grid">
        <!-- Deuxième ligne - 4 KPIs d'abonnements -->
        <div class="card kpi-card" style="grid-column: span 3;">
          <div class="kpi-title">Nouveaux Abonnements <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Nouveaux abonnements créés dans la période">ⓘ</span></div>
          <div class="kpi-value" id="timwe-new-subscriptions">Loading...</div>
          <div class="kpi-delta" id="timwe-new-subscriptions-delta">Loading...</div>
        </div>
        <div class="card kpi-card" style="grid-column: span 3;">
          <div class="kpi-title">Désabonnements <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Nombre de désabonnements dans la période">ⓘ</span></div>
          <div class="kpi-value" id="timwe-unsubscriptions">Loading...</div>
          <div class="kpi-delta" id="timwe-unsubscriptions-delta">Loading...</div>
        </div>
        <div class="card kpi-card" style="grid-column: span 3;">
          <div class="kpi-title">Simchurn <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Abonnements créés et expirés le même jour">ⓘ</span></div>
          <div class="kpi-value" id="timwe-simchurn">Loading...</div>
          <div class="kpi-delta" id="timwe-simchurn-delta">Loading...</div>
        </div>
        <div class="card kpi-card" style="grid-column: span 3;">
          <div class="kpi-title">Revenu Simchurn <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Revenu généré par les simchurn">ⓘ</span></div>
          <div class="kpi-value" id="timwe-simchurn-revenue">Loading...</div>
          <div class="kpi-delta" id="timwe-simchurn-revenue-delta">Loading...</div>
        </div>
      </div>

      <div class="grid">
        <!-- Troisième ligne - 4 KPIs de revenus -->
        <div class="card kpi-card" style="grid-column: span 3;">
          <div class="kpi-title">Revenu TTC (TND) <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Revenu total TTC basé sur la somme des totalCharged (en TND)">ⓘ</span></div>
          <div class="kpi-value" id="timwe-revenue-tnd">Loading...</div>
          <div class="kpi-delta" id="timwe-revenue-tnd-delta">Loading...</div>
        </div>
        <div class="card kpi-card" style="grid-column: span 3;">
          <div class="kpi-title">CA BigDeal HT (TND) <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Chiffre d'affaires BigDeal Hors Taxes calculé selon le contrat">ⓘ</span></div>
          <div class="kpi-value" id="timwe-revenue-usd">Loading...</div>
          <div class="kpi-delta" id="timwe-revenue-usd-delta">Loading...</div>
        </div>
        <div class="card kpi-card" style="grid-column: span 3;">
          <div class="kpi-title">ARPU (TND) <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Revenu moyen par utilisateur normalisé sur 30 jours : (Revenu Total / Active Subs) × (30 / Nombre de jours)">ⓘ</span></div>
          <div class="kpi-value" id="timwe-arpu">Loading...</div>
        </div>
        <div class="card kpi-card" style="grid-column: span 3;">
          <div class="kpi-title">Revenu Moyen/Facturation <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Revenu moyen par facturation (Revenu Total / Total Facturations)">ⓘ</span></div>
          <div class="kpi-value" id="timwe-avg-billing-revenue">Loading...</div>
        </div>
      </div>

      <!-- Tableau Statistiques Quotidiennes Timwe -->
      <div class="grid">
        <div class="card" style="grid-column: span 12;">
          <div class="chart-title">
            📊 Statistiques Quotidiennes Timwe
            <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Statistiques détaillées par jour pour Timwe">ⓘ</span>
            <button onclick="exportTimweStatsToExcel()" style="float: right; padding: 8px 16px; background: var(--primary); color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; margin-left: 8px;">
              📥 Excel
            </button>
            <button onclick="copyTimweStatsToClipboard()" style="float: right; padding: 8px 16px; background: var(--secondary); color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px;">
              📋 Copy
            </button>
          </div>
          
          <!-- Search bar -->
          <div style="padding: 16px; border-bottom: 1px solid var(--border);">
            <input type="text" id="timweStatsSearch" placeholder="🔍 Rechercher..." 
                   onkeyup="filterTimweStats()" 
                   style="width: 100%; padding: 8px 12px; border: 1px solid var(--border); border-radius: 4px; font-size: 14px;">
          </div>
          
          <div class="table-container" style="max-height: 600px; overflow-y: auto;">
            <table id="timweStatsTable">
              <thead style="position: sticky; top: 0; background: var(--card); z-index: 10;">
                <tr>
                  <th style="width: 30px; text-align: center;"></th>
                  <th style="text-align: left;">Période</th>
                  <th style="text-align: center;">New Sub</th>
                  <th style="text-align: center;">Unsub</th>
                  <th style="text-align: center;">Simchurn</th>
                  <th style="text-align: center;">Active Sub</th>
                  <th style="text-align: center;">NB Facturation</th>
                  <th style="text-align: center;">Taux Fact %</th>
                  <th style="text-align: center;">Revenu TTC (TND)</th>
                  <th style="text-align: center;">CA BigDeal HT (TND)</th>
                </tr>
              </thead>
              <tbody id="timweStatsTableBody">
                <tr>
                  <td colspan="10" style="text-align: center; padding: 40px;">
                    <i class="fas fa-spinner fa-spin"></i> Chargement des statistiques...
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- DÉSACTIVÉ POUR OPTIMISATION: Tableau des Transactions Timwe par Utilisateur -->
      <!-- Ce tableau a été désactivé définitivement pour améliorer les performances du dashboard -->
      <!--
      <div class="grid" style="margin-top: 20px;">
        <div class="card" style="grid-column: span 12;">
          <div class="chart-title">
            📋 Détails des Transactions Timwe par Utilisateur
            <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Liste des transactions Timwe (renouvellements et désabonnements) groupées par utilisateur">ⓘ</span>
          </div>
          <div style="padding: 40px; text-align: center; color: var(--muted);">
            ⚠️ Tableau désactivé pour optimisation des performances
          </div>
        </div>
      </div>
      -->

    </div>
    @endif

    <!-- Tab: Ooredoo/DGV Section -->
    @if(Auth::user()->canViewTimweSection())
    <div id="ooredoo" class="tab-content">

      <!-- Statistiques Ooredoo KPIs - 2 lignes de KPIs -->
      <div class="grid">
        <!-- Première ligne - 4 KPIs principaux -->
        <div class="card kpi-card" style="grid-column: span 3;">
          <div class="kpi-title">Taux de Facturation <span style="margin-left:4px; cursor: help; color: var(--muted);" title="(Clients facturés) / (Total clients Ooredoo) * 100. Transactions de type INVOICE avec statut SUCCESS.">ⓘ</span></div>
          <div class="kpi-value" id="ooredoo-billing-rate">Loading...</div>
          <div class="kpi-delta" id="ooredoo-billing-rate-delta">Loading...</div>
        </div>
        <div class="card kpi-card" style="grid-column: span 3;">
          <div class="kpi-title">Total Facturations <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Nombre total de transactions de facturation réussies (type INVOICE)">ⓘ</span></div>
          <div class="kpi-value" id="ooredoo-total-billings">Loading...</div>
          <div class="kpi-delta" id="ooredoo-total-billings-delta">Loading...</div>
        </div>
        <div class="card kpi-card" style="grid-column: span 3;">
          <div class="kpi-title">Active Subscriptions <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Base totale d'abonnés Ooredoo/DGV actifs au dernier jour de la période (source: ooredoo_daily_stats).">ⓘ</span></div>
          <div class="kpi-value" id="ooredoo-active-subs">Loading...</div>
        </div>
        <div class="card kpi-card" style="grid-column: span 3;">
          <div class="kpi-title">Nouveaux Abonnements <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Nouveaux abonnements créés dans la période (OOREDOO_PAYMENT_SUCCESS)">ⓘ</span></div>
          <div class="kpi-value" id="ooredoo-new-subscriptions">Loading...</div>
          <div class="kpi-delta" id="ooredoo-new-subscriptions-delta">Loading...</div>
        </div>
      </div>

      <div class="grid">
        <!-- Deuxième ligne - 4 KPIs d'abonnements -->
        <div class="card kpi-card" style="grid-column: span 3;">
          <div class="kpi-title">Désabonnements <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Nombre de désabonnements dans la période">ⓘ</span></div>
          <div class="kpi-value" id="ooredoo-unsubscriptions">Loading...</div>
          <div class="kpi-delta" id="ooredoo-unsubscriptions-delta">Loading...</div>
        </div>
        <div class="card kpi-card" style="grid-column: span 3;">
          <div class="kpi-title">Revenu Total TND <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Revenu total en TND (dinars tunisiens)">ⓘ</span></div>
          <div class="kpi-value" id="ooredoo-revenue-tnd">Loading...</div>
          <div class="kpi-delta" id="ooredoo-revenue-tnd-delta">Loading...</div>
        </div>
        <div class="card kpi-card" style="grid-column: span 3;">
          <div class="kpi-title">ARPU (TND) <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Revenu moyen par utilisateur normalisé sur 30 jours : (Revenu Total / Active Subs) × (30 / Nombre de jours)">ⓘ</span></div>
          <div class="kpi-value" id="ooredoo-arpu">Loading...</div>
        </div>
        <div class="card kpi-card" style="grid-column: span 3;">
          <div class="kpi-title">Revenu Moyen/Facturation <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Revenu moyen par transaction de facturation (Revenu Total / Total Facturations)">ⓘ</span></div>
          <div class="kpi-value" id="ooredoo-avg-billing-revenue">Loading...</div>
        </div>
      </div>

      <!-- Tableau Statistiques Mensuelles Ooredoo -->
      <div class="grid">
        <div class="card" style="grid-column: span 12;">
          <div class="chart-title">
            📊 Statistiques Mensuelles Ooredoo/DGV
            <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Statistiques groupées par mois pour Ooredoo/DGV. Cliquez sur un mois pour voir les détails quotidiens.">ⓘ</span>
            <button onclick="exportOoredooStatsToExcel()" style="float: right; padding: 8px 16px; background: var(--primary); color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; margin-left: 8px;">
              📥 Excel
            </button>
            <button onclick="copyOoredooStatsToClipboard()" style="float: right; padding: 8px 16px; background: var(--secondary); color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px;">
              📋 Copy
            </button>
          </div>
          
          <!-- Search bar -->
          <div style="padding: 16px; border-bottom: 1px solid var(--border);">
            <input type="text" id="ooredooStatsSearch" placeholder="🔍 Rechercher..." 
                   onkeyup="filterOoredooStats()" 
                   style="width: 100%; padding: 8px 12px; border: 1px solid var(--border); border-radius: 4px; font-size: 14px;">
          </div>
          
          <div class="table-container" style="max-height: 600px; overflow-y: auto;">
            <table id="ooredooStatsTable">
              <thead style="position: sticky; top: 0; background: var(--card); z-index: 10;">
                <tr>
                  <th style="cursor: pointer; width: 30px;"></th>
                  <th onclick="sortOoredooStatistics(0)" style="cursor: pointer;">Période <span class="sort-icon">⇅</span></th>
                  <th onclick="sortOoredooStatistics(1)" style="cursor: pointer;">New Sub <span class="sort-icon">⇅</span></th>
                  <th onclick="sortOoredooStatistics(2)" style="cursor: pointer;">Unsub <span class="sort-icon">⇅</span></th>
                  <th onclick="sortOoredooStatistics(3)" style="cursor: pointer;">Active Sub <span class="sort-icon">⇅</span></th>
                  <th onclick="sortOoredooStatistics(4)" style="cursor: pointer;">NB Facturation <span class="sort-icon">⇅</span></th>
                  <th onclick="sortOoredooStatistics(5)" style="cursor: pointer;">Taux Fact % <span class="sort-icon">⇅</span></th>
                  <th onclick="sortOoredooStatistics(6)" style="cursor: pointer;">Revenu TND <span class="sort-icon">⇅</span></th>
                </tr>
              </thead>
              <tbody id="ooredooStatsTableBody">
                <tr>
                  <td colspan="8" style="text-align: center; padding: 40px;">
                    <i class="fas fa-spinner fa-spin"></i> Chargement des statistiques...
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

    </div>
    @endif

    <!-- Tab 6: Comparison -->
    <div id="comparison" class="tab-content">
      <div class="grid">
        <!-- Comparison Table -->
        <div class="card table-card">
          <div class="chart-title">Period-over-Period Comparison <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Compare cette période à la période d'avant pour voir si on s'améliore.">ⓘ</span></div>
          <div class="table-container">
            <table>
              <thead>
                <tr>
                  <th>Metric</th>
                  <th>Current Period</th>
                  <th>Previous Period</th>
                  <th>Absolute Change</th>
                  <th>% Change</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody id="comparisonTableBody">
                <tr>
                  <td colspan="6" class="loading">
                    <div class="spinner"></div>
                    Loading comparison data...
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Comparison Chart -->
        <div class="card chart-card full-width">
          <div class="chart-title">Key Metrics Comparison <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Graphique en toile d'araignée: plus la zone verte est grande, mieux c'est par rapport à avant.">ⓘ</span></div>
          <div class="chart-container">
            <canvas id="comparisonChart"></canvas>
          </div>
        </div>
      </div>
    </div>

    <!-- Tab: Reporting Configuration -->
    @if(Auth::user()->isSuperAdmin())
    <div id="reporting" class="tab-content" data-testid="reporting-tab-content">
      <div class="grid" style="grid-template-columns: 1fr; gap: 20px;">

        <!-- Header actions -->
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
          <div>
            <h2 style="margin: 0; color: var(--text-primary); font-size: 1.3rem; font-weight: 700;">Configuration Reporting</h2>
            <p style="margin: 4px 0 0; color: var(--muted); font-size: 0.85rem;">Gerez les destinataires et l'envoi automatique des rapports hebdomadaires</p>
          </div>
          <div style="display: flex; gap: 8px;">
            <button class="btn-primary" onclick="openAddRecipientModal()" data-testid="add-recipient-btn" style="font-size: 0.85rem; padding: 8px 16px;">
              <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="vertical-align: -2px; margin-right: 4px;"><path d="M12 5v14M5 12h14"/></svg>
              Ajouter un destinataire
            </button>
            <button class="btn-secondary enhanced-btn" onclick="sendAllReportsNow()" data-testid="send-all-btn" style="font-size: 0.85rem; padding: 8px 16px;">
              <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="vertical-align: -2px; margin-right: 4px;"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/></svg>
              Envoyer tous les rapports
            </button>
          </div>
        </div>

        <!-- Schedule info card -->
        <div class="card" style="padding: 16px 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
          <div style="display: flex; align-items: center; gap: 12px;">
            <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(108,75,160,0.12); display: flex; align-items: center; justify-content: center;">
              <svg width="20" height="20" fill="none" stroke="var(--brand-primary)" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
            </div>
            <div>
              <div style="color: var(--text-primary); font-weight: 600; font-size: 0.9rem;">Envoi automatique</div>
              <div style="color: var(--muted); font-size: 0.8rem;">Chaque lundi a 08:00 - <span id="reportingActiveCount">0</span> destinataires actifs</div>
            </div>
          </div>
          <div style="color: var(--muted); font-size: 0.8rem;">
            Dernier envoi : <span id="reportingLastRun" style="color: var(--text-primary); font-weight: 500;">--</span>
          </div>
        </div>

        <!-- Recipients table -->
        <div class="card table-card">
          <div class="chart-title" style="display: flex; justify-content: space-between; align-items: center;">
            <span>Destinataires</span>
            <div style="display: flex; gap: 8px;">
              <select id="recipientTypeFilter" onchange="loadRecipients()" style="background: var(--card); color: var(--text-primary); border: 1px solid var(--border); padding: 4px 10px; border-radius: 6px; font-size: 0.8rem;">
                <option value="">Tous les types</option>
                <option value="ceo">CEO</option>
                <option value="marketing">Marketing</option>
                <option value="partner">Partenaire</option>
              </select>
            </div>
          </div>
          <div class="table-container">
            <table>
              <thead>
                <tr>
                  <th>Nom</th>
                  <th>Email</th>
                  <th>Type</th>
                  <th>Partenaire</th>
                  <th>Statut</th>
                  <th>Dernier envoi</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody id="recipientsTableBody" data-testid="recipients-table">
                <tr><td colspan="7" class="loading"><div class="spinner"></div> Chargement...</td></tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Report Logs -->
        <div class="card table-card">
          <div class="chart-title">Historique des envois</div>
          <div class="table-container">
            <table>
              <thead>
                <tr>
                  <th>Date</th>
                  <th>Destinataire</th>
                  <th>Type</th>
                  <th>Periode</th>
                  <th>Statut</th>
                  <th>IA</th>
                  <th>Erreur</th>
                </tr>
              </thead>
              <tbody id="reportLogsTableBody" data-testid="report-logs-table">
                <tr><td colspan="7" class="loading"><div class="spinner"></div> Chargement...</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
    @endif

    <!-- Modal: Ajouter/Modifier Destinataire -->
    <div id="recipientModal" style="display:none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(13,10,26,0.7); z-index: 10000; justify-content: center; align-items: center;" data-testid="recipient-modal">
      <div style="background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 28px; width: 480px; max-width: 95vw; max-height: 90vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
          <h3 id="recipientModalTitle" style="margin: 0; color: var(--text-primary); font-size: 1.1rem;">Ajouter un destinataire</h3>
          <button onclick="closeRecipientModal()" style="background: none; border: none; color: var(--muted); cursor: pointer; font-size: 1.3rem;">&times;</button>
        </div>
        <form id="recipientForm" onsubmit="saveRecipient(event)">
          <input type="hidden" id="recipientId" value="">
          <div style="margin-bottom: 14px;">
            <label style="display: block; color: var(--muted); font-size: 0.8rem; margin-bottom: 4px;">Nom *</label>
            <input type="text" id="recipientName" required class="enhanced-date-input" style="width: 100%; box-sizing: border-box;" data-testid="recipient-name-input">
          </div>
          <div style="margin-bottom: 14px;">
            <label style="display: block; color: var(--muted); font-size: 0.8rem; margin-bottom: 4px;">Email *</label>
            <input type="email" id="recipientEmail" required class="enhanced-date-input" style="width: 100%; box-sizing: border-box;" data-testid="recipient-email-input">
          </div>
          <div style="margin-bottom: 14px;">
            <label style="display: block; color: var(--muted); font-size: 0.8rem; margin-bottom: 4px;">Type de rapport *</label>
            <select id="recipientType" required onchange="togglePartnerField()" class="enhanced-date-input" style="width: 100%; box-sizing: border-box;" data-testid="recipient-type-select">
              <option value="">Choisir...</option>
              <option value="ceo">CEO - Rapport complet tous operateurs</option>
              <option value="marketing">Marketing - Acquisition & Retention</option>
              <option value="partner">Partenaire - Transactions individuelles</option>
            </select>
          </div>
          <div id="partnerFieldGroup" style="display: none; margin-bottom: 14px;">
            <label style="display: block; color: var(--muted); font-size: 0.8rem; margin-bottom: 4px;">Partenaire associe * <span style="font-size: 0.7rem;">(RGPD: seules les donnees de CE partenaire seront incluses)</span></label>
            <input type="text" id="partnerSearch" placeholder="Rechercher un partenaire..." class="enhanced-date-input" style="width: 100%; box-sizing: border-box;" oninput="searchPartners()" autocomplete="off">
            <input type="hidden" id="recipientPartnerId" data-testid="recipient-partner-id">
            <div id="partnerSearchResults" style="max-height: 150px; overflow-y: auto; margin-top: 4px; border-radius: 6px;"></div>
          </div>
          <div style="display: flex; gap: 12px; margin-bottom: 14px;">
            <div style="flex: 1;">
              <label style="display: block; color: var(--muted); font-size: 0.8rem; margin-bottom: 4px;">Jour d'envoi</label>
              <select id="recipientDay" class="enhanced-date-input" style="width: 100%; box-sizing: border-box;">
                <option value="monday">Lundi</option>
                <option value="tuesday">Mardi</option>
                <option value="wednesday">Mercredi</option>
                <option value="thursday">Jeudi</option>
                <option value="friday">Vendredi</option>
              </select>
            </div>
            <div style="flex: 1;">
              <label style="display: block; color: var(--muted); font-size: 0.8rem; margin-bottom: 4px;">Heure d'envoi</label>
              <input type="time" id="recipientTime" value="08:00" class="enhanced-date-input" style="width: 100%; box-sizing: border-box;">
            </div>
          </div>
          <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 20px;">
            <button type="button" onclick="closeRecipientModal()" class="btn-secondary enhanced-btn" style="font-size: 0.85rem; padding: 8px 20px;">Annuler</button>
            <button type="submit" class="btn-primary" style="font-size: 0.85rem; padding: 8px 20px;" data-testid="save-recipient-btn">Enregistrer</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal: Apercu du Rapport -->
    <div id="previewModal" style="display:none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(13,10,26,0.85); z-index: 10001; justify-content: center; align-items: center;" data-testid="preview-modal">
      <div style="background: var(--card); border: 1px solid var(--border); border-radius: 16px; width: 780px; max-width: 95vw; height: 85vh; display: flex; flex-direction: column; overflow: hidden;">
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 16px 24px; border-bottom: 1px solid var(--border);">
          <h3 id="previewTitle" style="margin: 0; color: var(--text-primary); font-size: 1rem; font-weight: 700;">Apercu du rapport</h3>
          <button onclick="closePreviewModal()" style="background: none; border: none; color: var(--muted); cursor: pointer; font-size: 1.4rem; padding: 4px 8px;" data-testid="close-preview-btn">&times;</button>
        </div>
        <div id="previewContent" style="flex: 1; overflow: auto; padding: 0;"></div>
      </div>
    </div>

    <!-- Tab: Agent IA (Style ChatGPT avec Sidebar) -->
    @if(Auth::user()->isSuperAdmin())
    <div id="ai-agent" class="tab-content">
      <!-- Widget Quota + Monitoring -->
      <div id="aiQuotaMonitoring" style="display: flex; gap: 12px; margin-bottom: 16px; flex-wrap: wrap;">
        <!-- Quota du jour -->
        <div style="flex: 1; min-width: 200px; background: white; border-radius: 10px; border: 1px solid #e5e7eb; padding: 14px 18px; display: flex; align-items: center; gap: 14px;">
          <div style="width: 48px; height: 48px; border-radius: 50%; background: #eef2ff; display: flex; align-items: center; justify-content: center;">
            <svg width="22" height="22" fill="none" stroke="#6366f1" stroke-width="2" viewBox="0 0 24 24"><path d="M12 8v4l3 3"/><circle cx="12" cy="12" r="10"/></svg>
          </div>
          <div style="flex:1;">
            <div style="font-size: 0.75rem; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px;">Quota Aujourd'hui</div>
            <div style="display: flex; align-items: baseline; gap: 6px;">
              <span id="aiQuotaUsed" style="font-size: 1.4rem; font-weight: 700; color: #374151;">--</span>
              <span style="font-size: 0.85rem; color: #9ca3af;">/ <span id="aiQuotaLimit">250</span></span>
            </div>
            <div style="margin-top: 6px; height: 5px; background: #f3f4f6; border-radius: 3px; overflow: hidden;">
              <div id="aiQuotaBar" style="height: 100%; width: 0%; background: #6366f1; border-radius: 3px; transition: width 0.5s ease;"></div>
            </div>
          </div>
        </div>
        <!-- Temps de reponse moyen -->
        <div style="flex: 1; min-width: 200px; background: white; border-radius: 10px; border: 1px solid #e5e7eb; padding: 14px 18px; display: flex; align-items: center; gap: 14px;">
          <div style="width: 48px; height: 48px; border-radius: 50%; background: #ecfdf5; display: flex; align-items: center; justify-content: center;">
            <svg width="22" height="22" fill="none" stroke="#10b981" stroke-width="2" viewBox="0 0 24 24"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
          </div>
          <div>
            <div style="font-size: 0.75rem; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px;">Temps moyen</div>
            <span id="aiAvgTime" style="font-size: 1.4rem; font-weight: 700; color: #374151;">--</span>
            <span style="font-size: 0.85rem; color: #9ca3af;">ms</span>
          </div>
        </div>
        <!-- Total conversations 30j -->
        <div style="flex: 1; min-width: 200px; background: white; border-radius: 10px; border: 1px solid #e5e7eb; padding: 14px 18px; display: flex; align-items: center; gap: 14px;">
          <div style="width: 48px; height: 48px; border-radius: 50%; background: #fef3c7; display: flex; align-items: center; justify-content: center;">
            <svg width="22" height="22" fill="none" stroke="#f59e0b" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
          </div>
          <div>
            <div style="font-size: 0.75rem; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px;">Questions (30j)</div>
            <span id="aiTotalQuestions" style="font-size: 1.4rem; font-weight: 700; color: #374151;">--</span>
          </div>
        </div>
        <!-- Tokens consommes -->
        <div style="flex: 1; min-width: 200px; background: white; border-radius: 10px; border: 1px solid #e5e7eb; padding: 14px 18px; display: flex; align-items: center; gap: 14px;">
          <div style="width: 48px; height: 48px; border-radius: 50%; background: #fce7f3; display: flex; align-items: center; justify-content: center;">
            <svg width="22" height="22" fill="none" stroke="#ec4899" stroke-width="2" viewBox="0 0 24 24"><path d="M12 2a10 10 0 100 20 10 10 0 000-20z"/><path d="M12 6v6l4 2"/></svg>
          </div>
          <div>
            <div style="font-size: 0.75rem; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px;">Tokens (30j)</div>
            <span id="aiTotalTokens" style="font-size: 1.4rem; font-weight: 700; color: #374151;">--</span>
          </div>
        </div>
      </div>

      <div style="display: flex; gap: 16px; height: 650px;">
        
        <!-- Sidebar Historique -->
        <div class="ai-sidebar" style="width: 280px; background: #f7f7f8; border-radius: 12px; border: 1px solid #e5e7eb; display: flex; flex-direction: column;">
          <div style="padding: 16px; border-bottom: 1px solid #e5e7eb; background: white; border-radius: 12px 12px 0 0;">
            <div style="display: flex; justify-content: between; align-items: center; margin-bottom: 12px;">
              <h6 style="margin: 0; font-weight: 600; color: #374151;">Conversations</h6>
              <button onclick="newAIConversationNow()" style="background: #6366f1; border: none; color: white; padding: 6px 10px; border-radius: 6px; font-size: 0.8rem; cursor: pointer;">+ Nouveau Chat</button>
            </div>
            <div style="display: flex; gap: 6px;">
              <button onclick="saveCurrentConversation()" style="background: #10b981; border: none; color: white; padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; cursor: pointer;">Sauver</button>
              <button onclick="loadConversationDialog()" style="background: #f59e0b; border: none; color: white; padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; cursor: pointer;">Charger</button>
              <button onclick="clearAllConversations()" style="background: #ef4444; border: none; color: white; padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; cursor: pointer;">Vider</button>
            </div>
          </div>
          <div id="aiConversationsList" style="flex: 1; overflow-y: auto; padding: 8px;">
            <div class="ai-conversation-item active" data-session="current" style="padding: 12px; margin: 4px 0; background: white; border-radius: 8px; border-left: 3px solid #6366f1; cursor: pointer;">
              <div style="font-size: 0.85rem; font-weight: 500; color: #374151;">Conversation Actuelle</div>
              <div style="font-size: 0.75rem; color: #6b7280; margin-top: 4px;">Juste maintenant</div>
            </div>
          </div>
          <div style="padding: 12px; border-top: 1px solid #e5e7eb; background: #f9fafb; border-radius: 0 0 12px 0;">
            <div style="font-size: 0.75rem; color: #6b7280; text-align: center;">
              Session : <code id="aiSessionSidebar" style="font-size: 0.7rem;">nouvelle</code><br>
              Expert ML
            </div>
          </div>
        </div>

        <!-- Zone de Chat Principale -->
        <div class="ai-chat-container" style="flex: 1; background: white; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); display: flex; flex-direction: column;">
          <div class="ai-header" style="padding: 16px 24px; border-bottom: 1px solid #e5e7eb; background: linear-gradient(135deg, #6366f1, #8b5cf6); color: white; border-radius: 12px 12px 0 0;">
            <div style="display: flex; justify-content: between; align-items: center;">
              <div>
                <h5 style="margin: 0; font-weight: 600;">Assistant IA Expert ML</h5>
                <small style="opacity: 0.9;">Recommandations instantanees</small>
              </div>
              <button onclick="newAIConversationNow()" style="background: rgba(255,255,255,0.2); border: none; color: white; padding: 6px 12px; border-radius: 6px; font-size: 0.85rem; cursor: pointer;">+ Nouveau</button>
            </div>
          </div>

          <div id="aiMessagesZone" style="flex: 1; overflow-y: auto; padding: 0;">
            <div class="ai-welcome-msg" style="padding: 24px; background: #f9fafb; border-bottom: 1px solid #f0f0f0;">
              <div style="max-width: 800px;">
                <p style="margin: 0 0 12px 0; color: #374151; font-size: 1rem;">
                  <strong>Salut ! Je suis votre expert IA.</strong> Posez-moi n'importe quelle question sur vos donnees ML et strategies de pricing.
                </p>
                <div style="display: flex; flex-wrap: wrap; gap: 8px; margin-top: 16px;">
                  <button onclick="askAIQuestion('Quel est le taux de succes actuel ?')" style="background: #f3f4f6; border: 1px solid #d1d5db; border-radius: 16px; padding: 6px 12px; font-size: 0.9rem; color: #374151; cursor: pointer;">Taux de succes actuel ?</button>
                  <button onclick="askAIQuestion('Compare quotidien 0.3 TND vs mensuel 3.0 TND')" style="background: #f3f4f6; border: 1px solid #d1d5db; border-radius: 16px; padding: 6px 12px; font-size: 0.9rem; color: #374151; cursor: pointer;">ROI quotidien vs mensuel</button>
                  <button onclick="askAIQuestion('Quelle strategie pour les clients High Risk ?')" style="background: #f3f4f6; border: 1px solid #d1d5db; border-radius: 16px; padding: 6px 12px; font-size: 0.9rem; color: #374151; cursor: pointer;">Strategie High Risk ?</button>
                  <button onclick="askAIQuestion('Explique les top 5 features ML les plus importantes')" style="background: #f3f4f6; border: 1px solid #d1d5db; border-radius: 16px; padding: 6px 12px; font-size: 0.9rem; color: #374151; cursor: pointer;">Top features ML</button>
                </div>
              </div>
            </div>
            <div id="aiMessagesContainer" style="padding: 0; min-height: 200px;"></div>
            <div id="aiTypingIndicator" style="display: none; padding: 16px 24px;">
              <div style="display: flex; align-items: center; color: #6b7280;">
                <div style="display: flex; gap: 4px; margin-right: 8px;">
                  <div style="width: 6px; height: 6px; background: #6b7280; border-radius: 50%; animation: ai-dot1 1.4s infinite;"></div>
                  <div style="width: 6px; height: 6px; background: #6b7280; border-radius: 50%; animation: ai-dot2 1.4s infinite;"></div>
                  <div style="width: 6px; height: 6px; background: #6b7280; border-radius: 50%; animation: ai-dot3 1.4s infinite;"></div>
                </div>
                <span style="font-style: italic; font-size: 0.9rem;">Agent IA analyse vos donnees...</span>
              </div>
            </div>
          </div>

          <div class="ai-input-zone" style="padding: 16px 24px; background: white; border-top: 1px solid #e5e7eb; border-radius: 0 0 12px 12px;">
            <div style="display: flex; align-items: end; gap: 8px; max-width: 100%; position: relative;">
              <div style="flex: 1; position: relative;">
                <textarea id="aiQuestionInput" placeholder="Posez votre question..." style="width: 100%; min-height: 44px; max-height: 120px; padding: 12px 50px 12px 16px; border: 2px solid #e5e7eb; border-radius: 22px; font-size: 1rem; resize: none; outline: none; font-family: inherit;" rows="1" onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendAIQuestionNow();}"></textarea>
                <button id="aiSendBtn" onclick="sendAIQuestionNow()" style="position: absolute; right: 8px; bottom: 6px; width: 32px; height: 32px; background: #6366f1; border: none; border-radius: 50%; color: white; display: flex; align-items: center; justify-content: center; cursor: pointer;">&#10148;</button>
              </div>
            </div>
            <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: 12px; margin-top: 8px;">
              <label for="aiProviderSelectDashboard" style="color: #9ca3af; font-size: 0.8rem; margin: 0;">Modele :</label>
              <select id="aiProviderSelectDashboard" style="font-size: 0.8rem; padding: 4px 8px; border-radius: 6px; border: 1px solid #e5e7eb; color: #374151; min-width: 160px;">
                <option value="gemini" selected>Gemini 2.5 Flash (Rapide)</option>
                <option value="openai">OpenAI GPT-4 (Detaille)</option>
                <option value="anthropic">Claude (Anthropic)</option>
              </select>
              <small style="color: #9ca3af; font-size: 0.8rem;">Session <code id="aiCurrentSession" style="font-size: 0.75rem; color: #6366f1;">nouvelle</code></small>
            </div>
          </div>
        </div>
      </div>
    </div>
    @endif

    <!-- Modal pour nommer la conversation Agent IA -->
    <div id="aiRenameModal" style="display: none; position: fixed; inset: 0; z-index: 9999; background: rgba(0,0,0,0.4); align-items: center; justify-content: center;">
      <div style="background: white; border-radius: 12px; padding: 24px; min-width: 360px; max-width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.2);">
        <div style="font-weight: 600; font-size: 1.1rem; color: #374151; margin-bottom: 12px;">Nommer la conversation</div>
        <input type="text" id="aiRenameModalInput" placeholder="Nom de la conversation" style="width: 100%; padding: 10px 12px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 1rem; margin-bottom: 16px; box-sizing: border-box;">
        <div style="display: flex; gap: 8px; justify-content: flex-end;">
          <button type="button" id="aiRenameModalCancel" style="padding: 8px 16px; border: 1px solid #d1d5db; border-radius: 8px; background: #f9fafb; color: #374151; cursor: pointer; font-size: 0.9rem;">Annuler</button>
          <button type="button" id="aiRenameModalOk" style="padding: 8px 16px; border: none; border-radius: 8px; background: #6366f1; color: white; cursor: pointer; font-size: 0.9rem;">OK</button>
        </div>
      </div>
    </div>

    <!-- Tab 6: Insights (Hidden) -->
    <!--
    <div id="insights" class="tab-content">
      <div class="insights-grid">
        <div class="insight-card">
          <div class="insight-title">
            <span style="color: var(--success);">✅</span>
            Positive Insights
          </div>
          <ul class="insight-list" id="positiveInsights">
            <li class="loading">
              <div class="spinner"></div>
              Loading insights...
            </li>
          </ul>
        </div>

        <div class="insight-card">
          <div class="insight-title">
            <span style="color: var(--warning);">⚠️</span>
            Challenges & Areas for Improvement
          </div>
          <ul class="insight-list" id="challenges">
            <li class="loading">
              <div class="spinner"></div>
              Loading challenges...
            </li>
          </ul>
        </div>

        <div class="insight-card">
          <div class="insight-title">
            <span style="color: var(--accent);">🎯</span>
            Strategic Recommendations
          </div>
          <ul class="insight-list" id="recommendations">
            <li class="loading">
              <div class="spinner"></div>
              Loading recommendations...
            </li>
          </ul>
        </div>

        <div class="insight-card">
          <div class="insight-title">
            <span style="color: var(--brand-red);">🚀</span>
            Next Steps
          </div>
          <ul class="insight-list" id="nextSteps">
            <li class="loading">
              <div class="spinner"></div>
              Loading next steps...
            </li>
          </ul>
        </div>
      </div>
    </div>
    -->
  </div>

  <!-- Modules JS extraits pour maintenabilité -->
  <script src="/js/dashboard/utils.js"></script>
  <script src="/js/dashboard/eklektik.js"></script>
  <script src="/js/dashboard/charts.js"></script>
  <script src="/js/dashboard/timwe.js"></script>
  <script src="/js/dashboard/ooredoo.js"></script>
  <script src="/js/dashboard/tables.js"></script>
  <script src="/js/dashboard/reporting.js"></script>

  <script src="/js/dashboard/main.js"></script>

  <!-- DÉSACTIVÉ POUR OPTIMISATION: Modal pour afficher les détails des transactions d'un client -->
  <!-- Ce modal a été désactivé définitivement pour améliorer les performances du dashboard -->
  <!--
  <div id="clientTransactionsModal" style="display: none;">Modal désactivé</div>
  -->

  <style>
    @keyframes slideDown {
      from {
        opacity: 0;
        transform: translateY(-50px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    
    #clientTransactionsModal table tbody tr:hover {
      background-color: #f9fafb;
    }
    
    #clientTransactionsModal table tbody tr {
      transition: background-color 0.2s;
    }

    /* Agent IA Styles - Dark Theme */
    #ai-agent [style*="background: white"],
    #ai-agent [style*="background: #f7f7f8"],
    #ai-agent [style*="background: #f9fafb"],
    #ai-agent [style*="background: #f3f4f6"],
    #ai-agent [style*="background: #f9fafb"] {
      background: var(--card) !important;
    }
    #ai-agent [style*="color: #374151"],
    #ai-agent [style*="color: #6b7280"] {
      color: var(--text-secondary) !important;
    }
    #ai-agent [style*="border: 1px solid #e5e7eb"],
    #ai-agent [style*="border: 1px solid #d1d5db"] {
      border-color: var(--border) !important;
    }
    #ai-agent [style*="border-bottom: 1px solid #e5e7eb"],
    #ai-agent [style*="border-top: 1px solid #e5e7eb"],
    #ai-agent [style*="border-bottom: 1px solid #f0f0f0"] {
      border-color: var(--border) !important;
    }
    #ai-agent textarea,
    #ai-agent select,
    #ai-agent input[type="text"] {
      background: rgba(255,255,255,0.04) !important;
      color: var(--text-primary) !important;
      border-color: var(--border) !important;
    }
    #ai-agent [style*="background: #eef2ff"],
    #ai-agent [style*="background: #ecfdf5"],
    #ai-agent [style*="background: #fef3c7"],
    #ai-agent [style*="background: #fce7f3"] {
      background: rgba(255,255,255,0.05) !important;
    }
    #aiRenameModal > div {
      background: var(--card) !important;
      border: 1px solid var(--border);
    }
    #aiRenameModal [style*="color: #374151"] {
      color: var(--text-primary) !important;
    }
    #aiRenameModal input {
      background: rgba(255,255,255,0.04) !important;
      color: var(--text-primary) !important;
      border-color: var(--border) !important;
    }
    #aiRenameModal [style*="background: #f9fafb"] {
      background: rgba(255,255,255,0.05) !important;
      color: var(--text-secondary) !important;
    }
    
    .ai-conversation-item {
      transition: all 0.2s;
      border: 1px solid transparent;
    }
    .ai-conversation-item:hover {
      background: rgba(255,255,255,0.05) !important;
      border-color: var(--border);
    }
    .ai-conversation-item.active {
      border-left-color: var(--brand-primary) !important;
      background: rgba(108, 75, 160, 0.08) !important;
    }
    .ai-sidebar button:hover {
      opacity: 0.8;
      transform: translateY(-1px);
    }
    .ai-message-user {
      padding: 16px 24px;
      background: var(--card);
      border-bottom: 1px solid var(--border);
    }
    .ai-message-assistant {
      padding: 16px 24px; 
      background: rgba(255,255,255,0.02);
      border-bottom: 1px solid var(--border);
    }
    .ai-message-content {
      max-width: 100%;
      line-height: 1.6;
      color: var(--text-secondary);
    }
    .ai-message-user .ai-message-content {
      font-weight: 500;
      color: var(--text-primary);
    }
    .ai-suggestion-simple:hover {
      background: rgba(255,255,255,0.1) !important;
      border-color: var(--border) !important;
    }
    #aiSendBtn:hover {
      background: #5B3FA0 !important;
      transform: scale(1.05);
    }
    #aiSendBtn:disabled {
      background: rgba(255,255,255,0.1) !important;
      cursor: not-allowed;
      transform: none;
    }
    @keyframes ai-dot1 { 0%, 60%, 100% { transform: translateY(0); } 30% { transform: translateY(-10px); } }
    @keyframes ai-dot2 { 0%, 60%, 100% { transform: translateY(0); } 30% { transform: translateY(-10px); } }
    @keyframes ai-dot3 { 0%, 60%, 100% { transform: translateY(0); } 30% { transform: translateY(-10px); } }
    .ai-dot1 { animation-delay: 0ms; }
    .ai-dot2 { animation-delay: 150ms; }
    .ai-dot3 { animation-delay: 300ms; }
  </style>

  <script src="/js/dashboard/ai-reporting.js"></script>

</body>
</html>
