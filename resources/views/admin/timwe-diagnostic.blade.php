<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnostic Notifications Timwe - Club Privilèges</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        :root {
            --brand-primary: #6B46C1;
            --brand-secondary: #8B5CF6;
            --brand-dark: #1f2937;
            --bg: #f8fafc;
            --card: #ffffff;
            --muted: #64748b;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --accent: #3b82f6;
            --border: #e2e8f0;
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: var(--bg);
            color: var(--brand-dark);
            line-height: 1.6;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        /* Header */
        .page-header {
            background: var(--card);
            padding: 24px;
            border-radius: 12px;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .page-header h1 {
            font-size: 28px;
            font-weight: 700;
            color: var(--brand-primary);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .page-header p {
            color: var(--muted);
            margin-top: 8px;
        }
        
        .back-link {
            color: var(--brand-primary);
            text-decoration: none;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border: 1px solid var(--border);
            border-radius: 8px;
            transition: all 0.2s;
        }
        
        .back-link:hover {
            background: var(--bg);
            border-color: var(--brand-primary);
        }
        
        /* Filters Card */
        .filters-card {
            background: var(--card);
            padding: 24px;
            border-radius: 12px;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 20px;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        
        .filter-label {
            font-size: 13px;
            font-weight: 600;
            color: var(--muted);
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .filter-input, .filter-select {
            padding: 10px 14px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            transition: all 0.2s;
        }
        
        .filter-input:focus, .filter-select:focus {
            outline: none;
            border-color: var(--brand-primary);
            box-shadow: 0 0 0 3px rgba(107, 70, 193, 0.1);
        }
        
        /* Buttons */
        .btn {
            padding: 10px 24px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: var(--brand-primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--brand-secondary);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(107, 70, 193, 0.3);
        }
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        
        .btn-success:hover {
            background: #059669;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }
        
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .cache-badge {
            font-size: 12px;
            color: var(--muted);
            background: var(--bg);
            border: 1px solid var(--border);
            padding: 6px 12px;
            border-radius: 6px;
            font-weight: 500;
        }
        
        /* Loading: spinner + état actif */
        .loading {
            display: none;
            align-items: center;
            gap: 10px;
            color: var(--muted);
            font-size: 14px;
        }
        
        .loading.active {
            display: inline-flex;
        }
        
        .loading-spinner {
            width: 22px;
            height: 22px;
            border: 3px solid var(--border);
            border-top-color: var(--brand-primary);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Skeleton (remplissage progressif) */
        .skeleton {
            background: linear-gradient(90deg, var(--border) 25%, var(--bg) 50%, var(--border) 75%);
            background-size: 200% 100%;
            animation: skeleton-pulse 1.2s ease-in-out infinite;
            border-radius: 6px;
        }
        
        .skeleton-text {
            height: 1em;
            min-width: 40px;
        }
        
        .skeleton-value {
            height: 32px;
            width: 80px;
            margin: 8px auto 0;
        }
        
        @keyframes skeleton-pulse {
            0%, 100% { opacity: 0.6; background-position: 200% 0; }
            50% { opacity: 1; background-position: -200% 0; }
        }
        
        .summary-grid.skeleton-mode .summary-value,
        .summary-grid.skeleton-mode .summary-value * {
            visibility: hidden;
        }
        
        .summary-grid.skeleton-mode .summary-value::after {
            content: '';
            display: block;
            visibility: visible;
            height: 32px;
            width: 70px;
            margin: 8px auto 0;
            background: linear-gradient(90deg, var(--border) 25%, var(--bg) 50%, var(--border) 75%);
            background-size: 200% 100%;
            animation: skeleton-pulse 1.2s ease-in-out infinite;
            border-radius: 6px;
        }
        
        /* Summary Cards : visible dès le chargement */
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .summary-grid.hidden-until-data {
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.25s ease;
        }
        
        .summary-grid.visible {
            opacity: 1;
            pointer-events: auto;
        }
        
        .summary-card {
            background: var(--card);
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            text-align: center;
            border-top: 3px solid var(--brand-primary);
        }
        
        .summary-label {
            font-size: 12px;
            color: var(--muted);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }
        
        .summary-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--brand-dark);
        }
        
        /* Tabs : visible dès le chargement */
        .tabs-container {
            background: var(--card);
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .tabs-container.hidden-until-data {
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.25s ease;
        }
        
        .tabs-container.visible {
            opacity: 1;
            pointer-events: auto;
        }
        
        .skeleton-row td {
            padding: 14px 16px;
        }
        
        .skeleton-row .skeleton-cell {
            height: 20px;
            background: linear-gradient(90deg, var(--border) 25%, var(--bg) 50%, var(--border) 75%);
            background-size: 200% 100%;
            animation: skeleton-pulse 1.2s ease-in-out infinite;
            border-radius: 4px;
        }
        
        .skeleton-cell.w-60 { width: 60%; }
        .skeleton-cell.w-40 { width: 40%; }
        .skeleton-cell.w-25 { width: 25%; }

        /* Barre de progression (25% summary → 50% delivery → 75% phones → 100% lifetime) */
        .progress-bar-container {
            margin-bottom: 20px;
            padding: 8px 0;
        }
        .progress-bar-track {
            height: 8px;
            background: var(--border);
            border-radius: 8px;
            overflow: hidden;
        }
        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--brand-primary), var(--brand-secondary));
            border-radius: 8px;
            transition: width 0.3s ease;
        }
        .progress-bar-label {
            font-size: 12px;
            color: var(--muted);
            margin-top: 4px;
            font-weight: 600;
        }
        .lifetime-loading {
            color: var(--muted);
            font-style: italic;
        }

        .timwe-error-zone {
            border-radius: 8px;
            padding: 14px 18px;
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }
        .timwe-error-zone.error {
            background: #fef2f2;
            border: 1px solid var(--danger);
            color: #991b1b;
        }
        .timwe-error-zone.error .timwe-error-icon { color: var(--danger); }
        .timwe-error-zone.warning {
            background: #fef3c7;
            border: 1px solid var(--warning);
            color: var(--brand-dark);
        }
        .timwe-error-zone.warning .timwe-error-icon { color: var(--warning); }
        .timwe-error-zone .timwe-error-icon { font-size: 20px; flex-shrink: 0; }
        .timwe-error-zone .timwe-error-body { flex: 1; }
        .timwe-error-zone .timwe-error-details {
            margin-top: 8px;
            font-size: 12px;
            opacity: 0.9;
        }
        .timwe-no-aggregates-alert {
            background: #fef3c7;
            border: 1px solid var(--warning);
            border-radius: 8px;
            padding: 14px 18px;
            margin-bottom: 20px;
            color: var(--brand-dark);
            font-size: 14px;
        }

        .timwe-chart-section {
            margin-bottom: 24px;
        }
        .timwe-chart-card {
            background: var(--card);
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .timwe-chart-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--brand-dark);
            margin-bottom: 16px;
        }
        .timwe-chart-container {
            position: relative;
            height: 280px;
        }

        .timwe-kpi-blocks {
            display: flex;
            flex-direction: column;
            gap: 24px;
            margin-bottom: 24px;
        }
        .timwe-kpi-blocks.hidden-until-data {
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.25s ease;
        }
        .timwe-kpi-blocks.visible {
            opacity: 1;
            pointer-events: auto;
        }
        .timwe-kpi-block {
            background: var(--card);
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border-top: 4px solid var(--border);
        }
        .timwe-kpi-block-global { border-top-color: var(--brand-primary); }
        .timwe-kpi-block-delivered { border-top-color: var(--success); }
        .timwe-kpi-block-no-balance { border-top-color: var(--warning); }
        .timwe-kpi-block-not-delivered { border-top-color: var(--danger); }
        .timwe-kpi-block-title {
            font-size: 14px;
            font-weight: 700;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 16px;
        }
        .timwe-kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 16px;
        }
        .timwe-kpi-grid .summary-card { border-top: none; }
        .timwe-chart-section.funnel-charts {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }
        @media (max-width: 900px) {
            .timwe-chart-section.funnel-charts { grid-template-columns: 1fr; }
        }
        
        .tabs-nav {
            display: flex;
            border-bottom: 1px solid var(--border);
            background: var(--bg);
        }
        
        .tab-button {
            flex: 1;
            padding: 16px;
            border: none;
            background: transparent;
            font-size: 14px;
            font-weight: 600;
            color: var(--muted);
            cursor: pointer;
            transition: all 0.2s;
            border-bottom: 3px solid transparent;
        }
        
        .tab-button:hover {
            background: rgba(107, 70, 193, 0.05);
            color: var(--brand-primary);
        }
        
        .tab-button.active {
            color: var(--brand-primary);
            border-bottom-color: var(--brand-primary);
            background: var(--card);
        }
        
        .tab-content {
            display: none;
            padding: 24px;
        }
        
        .tab-content.active {
            display: block;
        }
        
        /* Tables */
        .table-container {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        
        thead {
            background: var(--bg);
        }
        
        th {
            padding: 14px 16px;
            text-align: left;
            font-weight: 600;
            color: var(--muted);
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.5px;
            border-bottom: 2px solid var(--border);
        }
        
        td {
            padding: 14px 16px;
            border-bottom: 1px solid var(--border);
        }
        
        tbody tr {
            transition: background 0.2s;
        }
        
        tbody tr:hover {
            background: var(--bg);
        }
        
        /* Badges */
        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        
        .badge-primary { background: rgba(107, 70, 193, 0.1); color: var(--brand-primary); }
        .badge-success { background: rgba(16, 185, 129, 0.1); color: var(--success); }
        .badge-warning { background: rgba(245, 158, 11, 0.1); color: var(--warning); }
        .badge-danger { background: rgba(239, 68, 68, 0.1); color: var(--danger); }
        .badge-secondary { background: rgba(100, 116, 139, 0.1); color: var(--muted); }
        
        /* Progress Bar */
        .progress {
            height: 24px;
            background: var(--bg);
            border-radius: 12px;
            overflow: hidden;
            position: relative;
        }
        
        .progress-bar {
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 12px;
            font-weight: 600;
            transition: width 0.3s;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .container {
                padding: 12px;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 16px;
            }
            
            .filters-grid {
                grid-template-columns: 1fr;
            }
            
            .summary-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .tabs-nav {
                flex-direction: column;
            }
            
            .tab-button {
                border-bottom: none;
                border-left: 3px solid transparent;
            }
            
            .tab-button.active {
                border-left-color: var(--brand-primary);
                border-bottom-color: transparent;
            }
        }
        
        /* Pagination */
        .pagination-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            background: var(--card);
            border-top: 1px solid var(--border);
            border-radius: 0 0 12px 12px;
            margin-top: -12px;
            flex-wrap: wrap;
            gap: 16px;
        }
        
        .pagination-info {
            color: var(--muted);
            font-size: 14px;
        }
        
        .pagination-info strong {
            color: var(--brand-dark);
            font-weight: 600;
        }
        
        .pagination-buttons {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .pagination-btn {
            padding: 8px 14px;
            border: 1px solid var(--border);
            background: var(--card);
            color: var(--brand-dark);
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
            min-width: 40px;
            text-align: center;
        }
        
        .pagination-btn:hover:not(:disabled):not(.active) {
            background: var(--bg);
            border-color: var(--brand-primary);
            color: var(--brand-primary);
        }
        
        .pagination-btn.active {
            background: var(--brand-primary);
            color: white;
            border-color: var(--brand-primary);
        }
        
        .pagination-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            background: var(--bg);
        }
        
        .pagination-ellipsis {
            padding: 8px 4px;
            color: var(--muted);
        }
        
        @media (max-width: 768px) {
            .pagination-container {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .pagination-buttons {
                width: 100%;
                justify-content: center;
            }
            
            .pagination-btn {
                padding: 6px 10px;
                font-size: 12px;
                min-width: 32px;
            }
        }
        
        /* Tri des colonnes */
        .sortable {
            cursor: pointer;
            user-select: none;
            position: relative;
            transition: background 0.2s;
        }
        
        .sortable:hover {
            background: var(--bg) !important;
        }
        
        .sort-icon {
            margin-left: 6px;
            opacity: 0.3;
            font-size: 12px;
            transition: opacity 0.2s;
        }
        
        .sortable:hover .sort-icon {
            opacity: 0.6;
        }
        
        .sortable.sorted-asc .sort-icon::before {
            content: '↑';
            opacity: 1;
            color: var(--brand-primary);
            font-weight: bold;
        }
        
        .sortable.sorted-desc .sort-icon::before {
            content: '↓';
            opacity: 1;
            color: var(--brand-primary);
            font-weight: bold;
        }
        
        .sortable.sorted-asc .sort-icon,
        .sortable.sorted-desc .sort-icon {
            opacity: 1;
        }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 10000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(4px);
            animation: fadeIn 0.3s;
        }
        
        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: var(--card);
            border-radius: 16px;
            width: 90%;
            max-width: 1000px;
            max-height: 90vh;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.3s;
            display: flex;
            flex-direction: column;
        }
        
        .modal-header {
            padding: 24px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(135deg, var(--brand-primary), var(--brand-secondary));
            color: white;
        }
        
        .modal-header h3 {
            margin: 0;
            font-size: 20px;
            font-weight: 600;
        }
        
        .modal-close {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            font-size: 28px;
            cursor: pointer;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s;
        }
        
        .modal-close:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        
        .modal-body {
            padding: 24px;
            overflow-y: auto;
            flex: 1;
        }
        
        .btn-details {
            background: var(--brand-primary);
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .btn-details:hover {
            background: var(--brand-secondary);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(107, 70, 193, 0.3);
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="page-header">
            <div>
                <h1>
                    🩺 Diagnostic des Notifications Timwe
                </h1>
                <p>Analyse détaillée des réponses API Timwe par numéro et type de delivery code</p>
            </div>
            <a href="{{ route('dashboard') }}" class="back-link">
                ← Retour au dashboard
            </a>
        </div>
        
        <!-- Filters -->
        <div class="filters-card">
            <div class="filters-grid">
                <div class="filter-group">
                    <label class="filter-label">Date Début</label>
                    <input type="date" id="start_date" class="filter-input" value="{{ \Carbon\Carbon::now()->subDays(7)->format('Y-m-d') }}" max="{{ \Carbon\Carbon::now()->format('Y-m-d') }}">
                </div>
                <div class="filter-group">
                    <label class="filter-label">Date Fin</label>
                    <input type="date" id="end_date" class="filter-input" value="{{ \Carbon\Carbon::now()->format('Y-m-d') }}" max="{{ \Carbon\Carbon::now()->format('Y-m-d') }}">
                </div>
                <div class="filter-group">
                    <label class="filter-label">Rechercher Téléphone</label>
                    <input type="text" id="search_phone" class="filter-input" placeholder="Ex: +21612345678">
                        </div>
                <div class="filter-group">
                    <label class="filter-label">Filtrer Delivery Code</label>
                    <select id="delivery_code" class="filter-select">
                                <option value="">Tous</option>
                                <option value="DELIVERED">DELIVERED</option>
                                <option value="NO_BALANCE">NO_BALANCE</option>
                                <option value="NOT_DELIVERED">NOT_DELIVERED</option>
                                <option value="UNKNOWN">UNKNOWN</option>
                            </select>
                        </div>
                    </div>
                    
            <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                <button id="btnSearch" class="btn btn-primary">
                    🔍 Rechercher
                            </button>
                            <button id="btnExport" class="btn btn-success" disabled>
                    📥 Exporter CSV
                            </button>
                <span id="loadingIndicator" class="loading" aria-live="polite">
                    <span class="loading-spinner" aria-hidden="true"></span>
                    <span>Chargement des données…</span>
                </span>
                <span id="cacheBadge" class="cache-badge" style="display: none;" title="Données servies depuis le cache Redis">
                    📦 Données en cache
                            </span>
                        </div>
                    </div>

        <!-- Zone d'erreur / info (dates, pas d'agrégats) -->
        <div id="timweErrorZone" class="timwe-error-zone" role="alert" style="display: none;"></div>

        <!-- Barre de progression (summary 25% → delivery 50% → phones 75% → lifetime 100%) -->
        <div id="progressBarContainer" class="progress-bar-container" style="display: none;" aria-hidden="true">
            <div class="progress-bar-track">
                <div id="progressBarFill" class="progress-bar-fill" style="width: 0%;"></div>
            </div>
            <div id="progressBarLabel" class="progress-bar-label">0%</div>
        </div>
                    
        <!-- KPI Cards — Global puis Performance Technique par statut (Delivered / No Balance / Not Delivered) -->
        <div id="summarySection" class="timwe-kpi-blocks hidden-until-data">
            <div class="timwe-kpi-block timwe-kpi-block-global">
                <div class="timwe-kpi-block-title">Global</div>
                <div class="timwe-kpi-grid">
                    <div class="summary-card">
                        <div class="summary-label">Total Tentatives</div>
                        <div class="summary-value" id="kpiTotalAttempts">-</div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-label">Numéros uniques</div>
                        <div class="summary-value" id="kpiUniquePhones">-</div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-label">Revenue Total (TND)</div>
                        <div class="summary-value" id="kpiTotalRevenue" style="color: var(--success)">-</div>
                    </div>
                    <div class="summary-card" title="Paliers BigDeal : 1,2 TND jusqu'à 100k facturés, 1,0 TND de 100k à 250k, +250k : 250k TND flat">
                        <div class="summary-label">Revenu BigDeal TTC (TND)</div>
                        <div class="summary-value" id="kpiBigDealRevenue" style="color: var(--success)">-</div>
                    </div>
                </div>
            </div>
            <!-- Statut Delivered -->
            <div class="timwe-kpi-block timwe-kpi-block-delivered">
                <div class="timwe-kpi-block-title">Performance Technique – Statut Delivered</div>
                <div class="timwe-kpi-grid">
                    <div class="summary-card">
                        <div class="summary-label">Total Delivered</div>
                        <div class="summary-value" id="kpiTotalDelivered">-</div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-label">Delivered Facturés (Success)</div>
                        <div class="summary-value" id="kpiDeliveredBilled" style="color: var(--success)">-</div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-label">Delivered Non Facturés</div>
                        <div class="summary-value" id="kpiDeliveredNonBilled" style="color: var(--warning)">-</div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-label">Delivery Rate (%)</div>
                        <div class="summary-value" id="kpiDeliveryRate" style="color: var(--accent)">-</div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-label">Billing Rate sur Delivered (%)</div>
                        <div class="summary-value" id="kpiBillingRateOnDelivered" style="color: var(--success)">-</div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-label">Billing Rate Global (%)</div>
                        <div class="summary-value" id="kpiBillingRateGlobal" style="color: var(--warning)">-</div>
                    </div>
                </div>
            </div>
            <!-- Statut No Balance -->
            <div class="timwe-kpi-block timwe-kpi-block-no-balance">
                <div class="timwe-kpi-block-title">Performance Technique – Statut No Balance</div>
                <div class="timwe-kpi-grid">
                    <div class="summary-card">
                        <div class="summary-label">No Balance</div>
                        <div class="summary-value" id="kpiTotalNoBalance">-</div>
                    </div>
                    <div class="summary-card" title="Part des tentatives en NO_BALANCE (sur total tentatives)">
                        <div class="summary-label">No Balance Ratio (%)</div>
                        <div class="summary-value" id="kpiNoBalanceRatio">-</div>
                    </div>
                </div>
            </div>
            <!-- Statut Not Delivered -->
            <div class="timwe-kpi-block timwe-kpi-block-not-delivered">
                <div class="timwe-kpi-block-title">Performance Technique – Statut Not Delivered</div>
                <div class="timwe-kpi-grid">
                    <div class="summary-card">
                        <div class="summary-label">Not Delivered</div>
                        <div class="summary-value" id="kpiTotalNotDelivered" style="color: var(--danger)">-</div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-label">Technical Loss Rate (%)</div>
                        <div class="summary-value" id="kpiTechnicalLossRate" style="color: var(--danger)">-</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Graphiques Funnel (volume + taux %) -->
        <div id="funnelChartsSection" class="timwe-chart-section funnel-charts hidden-until-data" style="display: none;">
            <div class="timwe-chart-card">
                <div class="timwe-chart-title">Funnel – Volume</div>
                <div class="timwe-chart-container" style="height: 260px;">
                    <canvas id="funnelVolumeChartCanvas"></canvas>
                </div>
            </div>
            <div class="timwe-chart-card">
                <div class="timwe-chart-title">Taux (%)</div>
                <div class="timwe-chart-container" style="height: 260px;">
                    <canvas id="funnelRatesChartCanvas"></canvas>
                </div>
            </div>
        </div>

        <!-- Graphique évolution du taux de facturation -->
        <div id="billingRateChartSection" class="timwe-chart-section hidden-until-data">
            <div class="timwe-chart-card">
                <div class="timwe-chart-title">Évolution : facturations success et tentatives</div>
                <div class="timwe-chart-container">
                    <canvas id="billingRateChartCanvas"></canvas>
                </div>
            </div>
        </div>
                    
        <!-- Tabs Container (template visible pendant le chargement) -->
        <div id="diagnosticTabs" class="tabs-container hidden-until-data">
            <div class="tabs-nav">
                <button class="tab-button active" data-tab="byPhone">
                    📱 Par Numéro
                </button>
                <button class="tab-button" data-tab="byDeliveryCode">
                    📊 Par Delivery Code
                </button>
                <button class="tab-button" data-tab="recentTransactions">
                    🕐 Transactions Récentes
                </button>
            </div>
            
            @include('admin.timwe-diagnostic-tabs')
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
@include('admin.timwe-diagnostic-scripts')
</body>
</html>
