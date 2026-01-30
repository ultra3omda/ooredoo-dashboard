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
        
        /* Loading */
        .loading {
            display: none;
            color: var(--muted);
            font-size: 14px;
        }
        
        .loading.active {
            display: inline-flex;
        }
        
        /* Summary Cards */
        .summary-grid {
            display: none;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .summary-grid.active {
            display: grid;
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
        
        /* Tabs */
        .tabs-container {
            display: none;
            background: var(--card);
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .tabs-container.active {
            display: block;
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
                
                <div class="card-body">
                    <!-- Filtres -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Date Début</label>
                            <input type="date" id="start_date" class="form-control" value="{{ \Carbon\Carbon::now()->subDays(30)->format('Y-m-d') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Date Fin</label>
                            <input type="date" id="end_date" class="form-control" value="{{ \Carbon\Carbon::now()->format('Y-m-d') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Rechercher Téléphone</label>
                            <input type="text" id="search_phone" class="form-control" placeholder="Ex: +21612345678">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Filtrer Delivery Code</label>
                            <select id="delivery_code" class="form-select">
                                <option value="">Tous</option>
                                <option value="DELIVERED">DELIVERED</option>
                                <option value="NO_BALANCE">NO_BALANCE</option>
                                <option value="NOT_DELIVERED">NOT_DELIVERED</option>
                                <option value="UNKNOWN">UNKNOWN</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-12">
                            <button id="btnSearch" class="btn btn-primary me-2">
                                <i class="fas fa-search me-1"></i> Rechercher
                            </button>
                            <button id="btnExport" class="btn btn-success" disabled>
                                <i class="fas fa-file-csv me-1"></i> Exporter CSV
                            </button>
                            <span id="loadingIndicator" class="ms-3 text-muted" style="display: none;">
                                <i class="fas fa-spinner fa-spin me-1"></i> Chargement...
                            </span>
                        </div>
                    </div>
                    
                    <!-- Résumé -->
                    <div id="summarySection" class="row mb-4" style="display: none;">
                        <div class="col-md-2">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h6 class="text-muted small">Total Transactions</h6>
                                    <h3 id="totalTransactions" class="mb-0 text-primary">-</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h6 class="text-muted small">Numéros Uniques</h6>
                                    <h3 id="uniquePhones" class="mb-0 text-info">-</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h6 class="text-muted small">Facturés</h6>
                                    <h3 id="totalBilled" class="mb-0 text-success">-</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h6 class="text-muted small">Taux Facturation</h6>
                                    <h3 id="billingRate" class="mb-0 text-warning">-</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h6 class="text-muted small">Revenu Total (TND)</h6>
                                    <h3 id="totalRevenue" class="mb-0 text-success">-</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h6 class="text-muted small">Types Delivery</h6>
                                    <h3 id="deliveryCodesCount" class="mb-0 text-secondary">-</h3>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    @include('admin.timwe-diagnostic-tabs')
                </div>
            </div>
        </div>
    </div>
</div>

@include('admin.timwe-diagnostic-scripts')
@endsection
