@php
    $isOoredoo = isset($isOoredoo) ? $isOoredoo : false;
    $theme = isset($theme) ? $theme : 'club_privileges';
@endphp

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Journal d'Audit - Permissions</title>
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
            --input-bg: #f1f5f9;
            --input-border: #d1d5db;
            --text-primary: #1f2937;
            --text-secondary: #4b5563;
        }
        .dark-mode { --brand-dark:#FFF; --bg:#0D0A1A; --card:#161131; --card-hover:#1E1745; --muted:#A1A1AA; --border:#2A2350; --text-primary:#FFF; --text-secondary:#A1A1AA; --input-bg:#1E1745; --input-border:#2A2350; }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text-primary); line-height: 1.5; }
        .container { max-width: 1200px; margin: 0 auto; padding: 24px 20px; }

        .breadcrumb { font-size: 13px; color: var(--muted); margin-bottom: 16px; }
        .breadcrumb a { color: var(--brand-primary); text-decoration: none; }

        .page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; flex-wrap: wrap; gap: 12px; }
        .page-header h1 { font-size: 28px; font-weight: 700; color: var(--brand-primary); }
        .header-actions { display: flex; gap: 8px; flex-wrap: wrap; }

        .btn { padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 500; cursor: pointer; text-decoration: none; border: none; transition: all 0.2s; display: inline-flex; align-items: center; gap: 6px; }
        .btn-secondary { background: var(--card); border: 1px solid var(--border); color: var(--text-primary); }
        .btn-secondary:hover { background: var(--input-bg); }
        .btn-primary { background: var(--brand-primary); color: #fff; }
        .btn-primary:hover { opacity: 0.9; }

        /* Stats KPIs */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; margin-bottom: 24px; }
        .stat-card { background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 16px; text-align: center; }
        .stat-value { font-size: 28px; font-weight: 700; color: var(--brand-primary); }
        .stat-label { font-size: 12px; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; margin-top: 4px; }

        /* Filters */
        .filters-bar { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; align-items: center; background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 14px 16px; }
        .filters-bar input, .filters-bar select { padding: 8px 12px; border: 1px solid var(--input-border); border-radius: 8px; font-size: 13px; background: var(--input-bg); color: var(--text-primary); }
        .filters-bar input[type="text"] { flex: 1; min-width: 200px; }
        .filters-bar input[type="date"] { min-width: 140px; }

        /* Table */
        .card { background: var(--card); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; }
        .table-wrapper { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .table { width: 100%; border-collapse: collapse; }
        .table th { background: var(--input-bg); padding: 12px 14px; text-align: left; font-size: 12px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; white-space: nowrap; }
        .table td { padding: 14px; border-top: 1px solid var(--border); font-size: 13px; vertical-align: top; }
        .table tr:hover td { background: rgba(107, 70, 193, 0.03); }

        .badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .badge-grant { background: rgba(16, 185, 129, 0.1); color: var(--success); }
        .badge-restrict { background: rgba(245, 158, 11, 0.1); color: var(--warning); }
        .badge-add { background: rgba(59, 130, 246, 0.1); color: var(--accent); }
        .badge-remove { background: rgba(239, 68, 68, 0.1); color: var(--danger); }

        .user-info { display: flex; flex-direction: column; gap: 2px; }
        .user-info .name { font-weight: 600; color: var(--text-primary); }
        .user-info .email { font-size: 11px; color: var(--muted); }

        .campaigns-list { display: flex; flex-wrap: wrap; gap: 4px; max-width: 250px; }
        .campaign-tag { font-size: 10px; padding: 2px 8px; background: var(--input-bg); border-radius: 12px; color: var(--text-secondary); white-space: nowrap; }

        .timestamp { font-size: 12px; color: var(--muted); }
        .ip { font-size: 11px; color: var(--muted); display: block; margin-top: 2px; }

        .empty-state { text-align: center; padding: 60px 20px; color: var(--muted); }
        .empty-state .icon { font-size: 48px; margin-bottom: 12px; display: block; }

        .pagination-bar { display: flex; justify-content: space-between; align-items: center; padding: 14px 16px; border-top: 1px solid var(--border); background: var(--card); font-size: 13px; color: var(--muted); }
        .pagination-bar .pages { display: flex; gap: 4px; }
        .pagination-bar .page-btn { padding: 6px 12px; border: 1px solid var(--border); border-radius: 6px; cursor: pointer; background: var(--card); color: var(--text-primary); font-size: 12px; }
        .pagination-bar .page-btn.active { background: var(--brand-primary); color: #fff; border-color: var(--brand-primary); }
        .pagination-bar .page-btn:hover:not(.active) { background: var(--input-bg); }

        .loading { text-align: center; padding: 40px; color: var(--muted); }

        @media (max-width: 768px) {
            .container { padding: 12px 8px; }
            .page-header { flex-direction: column; align-items: stretch; }
            .page-header h1 { font-size: 22px; text-align: center; }
            .header-actions { justify-content: center; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .filters-bar { flex-direction: column; }
            .filters-bar input[type="text"] { min-width: auto; }
            /* Table -> Card layout on mobile */
            .table thead { display: none; }
            .table, .table tbody, .table tr, .table td { display: block; width: 100%; }
            .table { min-width: unset; }
            .table tr { 
                padding: 16px; 
                margin-bottom: 12px; 
                border: 1px solid var(--border); 
                border-radius: 10px; 
                background: var(--card);
                box-shadow: 0 1px 3px rgba(0,0,0,0.06);
            }
            .table td { 
                padding: 4px 0; 
                border: none;
                font-size: 13px;
            }
            .table td:before {
                content: attr(data-label);
                display: block;
                font-size: 11px;
                font-weight: 600;
                color: var(--muted);
                text-transform: uppercase;
                letter-spacing: 0.5px;
                margin-bottom: 2px;
                margin-top: 8px;
            }
            .table td:first-child:before { margin-top: 0; }
            .campaigns-list { max-width: 100%; }
            .table-wrapper { overflow-x: visible; }
            .pagination-bar { flex-direction: column; gap: 10px; text-align: center; }
        }
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 8px; }
            .stat-card { padding: 12px; }
            .stat-value { font-size: 22px; }
        }
    </style>
    <script>(function(){var s=localStorage.getItem("dashboard-theme");if(s==="dark")document.documentElement.classList.add("dark-mode");}());</script>
</head>
<body>
    @include('partials._admin-header')
    <div class="container">
        <div class="page-header" style="margin-top: 16px;">
            <h1 data-testid="audit-log-title">Journal d'Audit - Permissions</h1>
        </div>

        <!-- KPI Stats -->
        <div class="stats-grid" id="statsGrid" data-testid="audit-stats">
            <div class="stat-card"><div class="stat-value" id="stat-total">-</div><div class="stat-label">Total modifications</div></div>
            <div class="stat-card"><div class="stat-value" id="stat-grants">-</div><div class="stat-label">Acces complet accordes</div></div>
            <div class="stat-card"><div class="stat-value" id="stat-restrictions">-</div><div class="stat-label">Restrictions appliquees</div></div>
            <div class="stat-card"><div class="stat-value" id="stat-users">-</div><div class="stat-label">Utilisateurs concernes</div></div>
            <div class="stat-card"><div class="stat-value" id="stat-admins">-</div><div class="stat-label">Admins actifs</div></div>
        </div>

        <!-- Filters -->
        <div class="filters-bar" data-testid="audit-filters">
            <input type="text" id="searchInput" placeholder="Rechercher par nom, email..." oninput="debounceLoad()" data-testid="audit-search">
            <select id="actionFilter" onchange="loadLogs()" data-testid="audit-action-filter">
                <option value="">Toutes les actions</option>
                <option value="grant_full_access">Acces complet</option>
                <option value="restrict_campaigns">Restriction</option>
            </select>
            <input type="date" id="dateFrom" onchange="loadLogs()" data-testid="audit-date-from">
            <input type="date" id="dateTo" onchange="loadLogs()" data-testid="audit-date-to">
            <button class="btn btn-secondary" onclick="resetFilters()" data-testid="audit-reset-filters">Reinitialiser</button>
        </div>

        <!-- Table -->
        <div class="card">
            <div class="table-wrapper">
                <table class="table" data-testid="audit-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Utilisateur modifie</th>
                            <th>Modifie par</th>
                            <th>Action</th>
                            <th>Details</th>
                            <th>Campagnes (avant)</th>
                            <th>Campagnes (apres)</th>
                        </tr>
                    </thead>
                    <tbody id="logTableBody">
                        <tr><td colspan="7" class="loading">Chargement...</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="pagination-bar" id="paginationBar" data-testid="audit-pagination">
                <span id="paginationInfo"></span>
                <div class="pages" id="paginationPages"></div>
            </div>
        </div>
    </div>

    <script>
        let currentPage = 1;
        let debounceTimer = null;

        function debounceLoad() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(loadLogs, 300);
        }

        async function loadLogs(page = 1) {
            currentPage = page;
            const search = document.getElementById('searchInput').value;
            const action = document.getElementById('actionFilter').value;
            const dateFrom = document.getElementById('dateFrom').value;
            const dateTo = document.getElementById('dateTo').value;

            const params = new URLSearchParams({ page });
            if (search) params.append('search', search);
            if (action) params.append('action', action);
            if (dateFrom) params.append('date_from', dateFrom);
            if (dateTo) params.append('date_to', dateTo);

            try {
                const res = await fetch(`{{ route('admin.audit-logs.data') }}?${params}`);
                const data = await res.json();

                // Update stats
                if (data.stats) {
                    document.getElementById('stat-total').textContent = data.stats.total || 0;
                    document.getElementById('stat-grants').textContent = data.stats.full_access_grants || 0;
                    document.getElementById('stat-restrictions').textContent = data.stats.restrictions || 0;
                    document.getElementById('stat-users').textContent = data.stats.users_affected || 0;
                    document.getElementById('stat-admins').textContent = data.stats.admins_active || 0;
                }

                // Render table
                const tbody = document.getElementById('logTableBody');
                if (!data.logs || data.logs.length === 0) {
                    tbody.innerHTML = `<tr><td colspan="7" class="empty-state"><span class="icon">&#128221;</span>Aucune modification de permission enregistree.</td></tr>`;
                } else {
                    tbody.innerHTML = data.logs.map(log => renderRow(log)).join('');
                }

                // Pagination
                renderPagination(data.total, data.page, data.total_pages);

            } catch (e) {
                document.getElementById('logTableBody').innerHTML = `<tr><td colspan="7" style="text-align:center;padding:20px;color:var(--danger);">Erreur: ${e.message}</td></tr>`;
            }
        }

        function renderRow(log) {
            const date = new Date(log.created_at);
            const dateStr = date.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' });
            const timeStr = date.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });

            const actionBadge = getActionBadge(log.action);
            const oldCampaigns = renderCampaigns(log.old_value);
            const newCampaigns = renderCampaigns(log.new_value);

            return `<tr>
                <td data-label="Date"><div class="timestamp">${dateStr}<br>${timeStr}</div>${log.ip_address ? `<span class="ip">${log.ip_address}</span>` : ''}</td>
                <td data-label="Utilisateur modifie"><div class="user-info"><span class="name">${log.user_name || '-'}</span><span class="email">${log.user_email || '-'}</span></div></td>
                <td data-label="Modifie par"><div class="user-info"><span class="name">${log.changed_by_name || '-'}</span><span class="email">${log.changed_by_email || '-'}</span></div></td>
                <td data-label="Action">${actionBadge}</td>
                <td data-label="Details" style="max-width:250px;font-size:12px;color:var(--text-secondary);">${log.details || '-'}</td>
                <td data-label="Campagnes (avant)">${oldCampaigns}</td>
                <td data-label="Campagnes (apres)">${newCampaigns}</td>
            </tr>`;
        }

        function getActionBadge(action) {
            const map = {
                'grant_full_access': ['badge-grant', 'Acces complet'],
                'restrict_campaigns': ['badge-restrict', 'Restriction'],
                'add_campaigns': ['badge-add', 'Ajout'],
                'remove_campaigns': ['badge-remove', 'Retrait'],
            };
            const [cls, label] = map[action] || ['badge-restrict', action];
            return `<span class="badge ${cls}">${label}</span>`;
        }

        function renderCampaigns(jsonStr) {
            if (!jsonStr) return '<span style="font-size:12px;color:var(--success);font-weight:600;">Acces complet</span>';
            try {
                const arr = JSON.parse(jsonStr);
                if (!Array.isArray(arr) || arr.length === 0) return '<span style="font-size:12px;color:var(--success);font-weight:600;">Acces complet</span>';
                return `<div class="campaigns-list">${arr.map(c => `<span class="campaign-tag">${c}</span>`).join('')}</div>`;
            } catch {
                return `<span class="campaign-tag">${jsonStr}</span>`;
            }
        }

        function renderPagination(total, page, totalPages) {
            const info = document.getElementById('paginationInfo');
            const pages = document.getElementById('paginationPages');
            info.textContent = `${total} entree(s) - Page ${page}/${totalPages || 1}`;
            
            if (totalPages <= 1) { pages.innerHTML = ''; return; }
            let html = '';
            if (page > 1) html += `<button class="page-btn" onclick="loadLogs(${page - 1})">&laquo;</button>`;
            for (let i = Math.max(1, page - 2); i <= Math.min(totalPages, page + 2); i++) {
                html += `<button class="page-btn ${i === page ? 'active' : ''}" onclick="loadLogs(${i})">${i}</button>`;
            }
            if (page < totalPages) html += `<button class="page-btn" onclick="loadLogs(${page + 1})">&raquo;</button>`;
            pages.innerHTML = html;
        }

        function resetFilters() {
            document.getElementById('searchInput').value = '';
            document.getElementById('actionFilter').value = '';
            document.getElementById('dateFrom').value = '';
            document.getElementById('dateTo').value = '';
            loadLogs(1);
        }

        // Load on page ready
        document.addEventListener('DOMContentLoaded', () => loadLogs());
    </script>
</body>
</html>
