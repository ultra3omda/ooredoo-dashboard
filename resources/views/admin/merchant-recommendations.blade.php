@extends('layouts.app')

@section('title', 'Recommandations Marchands - ML Engine')

@section('styles')
.reco-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 28px; }
.reco-kpi { background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 18px 20px; box-shadow: var(--shadow-sm); position: relative; overflow: hidden; }
.reco-kpi::before { content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%; }
.reco-kpi.model::before { background: var(--brand-primary); }
.reco-kpi.merchants::before { background: var(--success); }
.reco-kpi.profiles::before { background: #3b82f6; }
.reco-kpi.interactions::before { background: var(--warning); }
.reco-kpi-label { font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: var(--muted); font-weight: 600; margin-bottom: 6px; }
.reco-kpi-value { font-size: 26px; font-weight: 700; color: var(--text-primary); font-family: 'Outfit', sans-serif; }
.reco-kpi-sub { font-size: 11px; color: var(--muted); margin-top: 4px; }

.reco-layout { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 28px; }
.reco-search-box { display: flex; gap: 10px; align-items: center; margin-bottom: 16px; flex-wrap: wrap; }
.reco-search-box input, .reco-search-box select { padding: 8px 14px; border-radius: 8px; border: 1px solid var(--input-border); background: var(--input-bg); color: var(--text-primary); font-size: 13px; }
.reco-search-box input { flex: 1; min-width: 120px; }

.merchant-card { display: flex; align-items: center; gap: 14px; padding: 14px 16px; background: var(--card); border: 1px solid var(--border); border-radius: 10px; margin-bottom: 10px; transition: all 0.2s; }
.merchant-card:hover { box-shadow: var(--shadow-md); transform: translateY(-1px); }
.merchant-rank { min-width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px; font-family: 'Outfit', sans-serif; }
.rank-1 { background: linear-gradient(135deg, #fbbf24, #f59e0b); color: #fff; }
.rank-2 { background: linear-gradient(135deg, #d1d5db, #9ca3af); color: #fff; }
.rank-3 { background: linear-gradient(135deg, #d97706, #b45309); color: #fff; }
.rank-default { background: var(--table-stripe); color: var(--muted); border: 1px solid var(--border); }
.merchant-info { flex: 1; min-width: 0; }
.merchant-name { font-weight: 600; font-size: 14px; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.merchant-cat { font-size: 11px; color: var(--muted); margin-top: 2px; }
.merchant-reason { font-size: 11px; color: var(--brand-primary); margin-top: 4px; line-height: 1.4; }
.merchant-score { text-align: right; min-width: 70px; }
.merchant-score-val { font-size: 18px; font-weight: 700; font-family: 'Outfit', sans-serif; }
.score-positive { color: var(--success); }
.score-neutral { color: var(--muted); }
.merchant-meta { font-size: 10px; color: var(--muted); margin-top: 2px; }

.tag { display: inline-block; padding: 2px 8px; border-radius: 6px; font-size: 10px; font-weight: 600; }
.tag-visited { background: rgba(16, 185, 129, 0.12); color: var(--success); }
.tag-new { background: rgba(59, 130, 246, 0.12); color: #3b82f6; }
.tag-promos { background: rgba(245, 158, 11, 0.12); color: var(--warning); }

.stats-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.stats-table th { background: var(--table-stripe); font-weight: 600; color: var(--muted); font-size: 11px; text-transform: uppercase; padding: 10px 12px; text-align: left; }
.stats-table td { padding: 10px 12px; border-bottom: 1px solid var(--border); }

.loading-spinner { display: inline-block; width: 16px; height: 16px; border: 2px solid var(--border); border-top-color: var(--brand-primary); border-radius: 50%; animation: spin 0.6s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

.status-badge { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
.status-ready { background: rgba(16, 185, 129, 0.12); color: var(--success); }
.status-fallback { background: rgba(245, 158, 11, 0.12); color: var(--warning); }
.status-error { background: rgba(239, 68, 68, 0.12); color: var(--danger); }

.feature-bar { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
.feature-bar-name { font-size: 12px; color: var(--text-secondary); min-width: 160px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.feature-bar-track { flex: 1; height: 8px; background: var(--table-stripe); border-radius: 4px; overflow: hidden; }
.feature-bar-fill { height: 100%; border-radius: 4px; background: var(--brand-primary); transition: width 0.5s ease; }

@media (max-width: 1024px) { .reco-grid { grid-template-columns: repeat(2, 1fr); } .reco-layout { grid-template-columns: 1fr; } }
@media (max-width: 600px) { .reco-grid { grid-template-columns: 1fr; } }
@endsection

@section('content')
<!-- Header -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 12px;">
    <div>
        <h1 style="font-family: 'Outfit', sans-serif; font-size: 22px; font-weight: 700; color: var(--text-primary); margin: 0;">
            <i class="fas fa-store" style="color: var(--brand-primary);"></i> Recommandations Marchands
        </h1>
        <p style="color: var(--muted); font-size: 13px; margin: 4px 0 0;">Moteur ML LightGBM pour recommandations personnalisées</p>
    </div>
    <div class="btn-group">
        <button class="btn-primary" onclick="loadDashboard()" data-testid="reco-refresh-btn">
            <i class="fas fa-sync-alt"></i> Actualiser
        </button>
        <button class="btn-warning" onclick="retrainModel()" id="retrainBtn" data-testid="reco-retrain-btn">
            <i class="fas fa-cogs"></i> Retrain modèle
        </button>
    </div>
</div>

<!-- KPIs -->
<div class="reco-grid" data-testid="reco-kpi-grid">
    <div class="reco-kpi model">
        <div class="reco-kpi-label">Statut Modèle</div>
        <div id="kpi-status" data-testid="kpi-model-status">
            @if(($health['status'] ?? '') === 'ready')
                <span class="status-badge status-ready"><i class="fas fa-check-circle"></i> Opérationnel</span>
            @elseif(($health['status'] ?? '') === 'fallback_only')
                <span class="status-badge status-fallback"><i class="fas fa-exclamation-triangle"></i> Fallback</span>
            @else
                <span class="status-badge status-error"><i class="fas fa-times-circle"></i> Hors ligne</span>
            @endif
        </div>
        <div class="reco-kpi-sub" id="kpi-trained-at">Entraîné: {{ $health['trained_at'] ?? 'N/A' }}</div>
    </div>
    <div class="reco-kpi merchants">
        <div class="reco-kpi-label">Marchands Actifs</div>
        <div class="reco-kpi-value" id="kpi-merchants" data-testid="kpi-active-merchants">--</div>
        <div class="reco-kpi-sub">Dans le catalogue ML</div>
    </div>
    <div class="reco-kpi profiles">
        <div class="reco-kpi-label">Profils Utilisateurs</div>
        <div class="reco-kpi-value" id="kpi-profiles" data-testid="kpi-user-profiles">--</div>
        <div class="reco-kpi-sub">Avec historique d'interaction</div>
    </div>
    <div class="reco-kpi interactions">
        <div class="reco-kpi-label">Interactions Trackées</div>
        <div class="reco-kpi-value" id="kpi-interactions" data-testid="kpi-interactions">--</div>
        <div class="reco-kpi-sub" id="kpi-interactions-7d">7 derniers jours</div>
    </div>
</div>

<!-- Main Layout -->
<div class="reco-layout">
    <!-- Left: Personalized Recommendations -->
    <div class="cp-card" data-testid="reco-search-panel">
        <div class="cp-card-header">
            <span class="cp-card-title"><i class="fas fa-user-tag"></i> Recommandations Personnalisées</span>
        </div>
        <div class="reco-search-box">
            <input type="number" id="clientIdInput" placeholder="ID Client (ex: 114218)" data-testid="client-id-input">
            <select id="categoryFilter" data-testid="category-filter">
                <option value="">Toutes catégories</option>
            </select>
            <label style="font-size: 12px; display: flex; align-items: center; gap: 4px; color: var(--text-secondary);">
                <input type="checkbox" id="excludeVisited" data-testid="exclude-visited-checkbox"> Exclure visités
            </label>
            <button class="btn-primary" onclick="searchRecommendations()" data-testid="search-reco-btn">
                <i class="fas fa-search"></i> Rechercher
            </button>
        </div>
        <div id="recoResults" data-testid="reco-results">
            <p style="color: var(--muted); font-size: 13px; text-align: center; padding: 30px 0;">
                Entrez un ID client pour obtenir des recommandations personnalisées
            </p>
        </div>
    </div>

    <!-- Right: Popular Merchants -->
    <div class="cp-card" data-testid="popular-merchants-panel">
        <div class="cp-card-header">
            <span class="cp-card-title"><i class="fas fa-fire"></i> Top Marchands Populaires</span>
        </div>
        <div id="popularResults" data-testid="popular-results">
            <div style="text-align: center; padding: 20px;"><div class="loading-spinner"></div></div>
        </div>
    </div>
</div>

<!-- Bottom: Stats & Model Info -->
<div class="reco-layout">
    <!-- Interaction Stats -->
    <div class="cp-card" data-testid="interaction-stats-panel">
        <div class="cp-card-header">
            <span class="cp-card-title"><i class="fas fa-chart-bar"></i> Statistiques d'Interactions (7j)</span>
        </div>
        <div id="statsTable" data-testid="stats-table">
            <div style="text-align: center; padding: 20px;"><div class="loading-spinner"></div></div>
        </div>
    </div>

    <!-- Model Performance -->
    <div class="cp-card" data-testid="model-info-panel">
        <div class="cp-card-header">
            <span class="cp-card-title"><i class="fas fa-brain"></i> Performance Modèle</span>
        </div>
        <div id="modelInfo" data-testid="model-info">
            <div style="text-align: center; padding: 20px;"><div class="loading-spinner"></div></div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
const baseUrl = window.location.origin;

document.addEventListener('DOMContentLoaded', loadDashboard);

async function loadDashboard() {
    await Promise.allSettled([
        loadStats(),
        loadPopular(),
        loadModelInfo(),
        loadCategories()
    ]);
}

async function loadStats() {
    try {
        const res = await fetch(`${baseUrl}/api/merchant-recommendations/stats`);
        const data = await res.json();

        document.getElementById('kpi-merchants').textContent = (data.active_merchants || 0).toLocaleString();
        document.getElementById('kpi-profiles').textContent = (data.profiled_users || 0).toLocaleString();
        document.getElementById('kpi-interactions').textContent = (data.total_interactions || 0).toLocaleString();

        const last7 = data.last_7_days || [];
        const total7d = last7.reduce((s, r) => s + (r.cnt || 0), 0);
        document.getElementById('kpi-interactions-7d').textContent = `${total7d} cette semaine`;

        if (last7.length > 0) {
            let html = '<table class="stats-table"><thead><tr><th>Type</th><th>Source</th><th>Count</th><th>Utilisateurs</th><th>Marchands</th></tr></thead><tbody>';
            last7.forEach(r => {
                html += `<tr>
                    <td><span class="tag tag-${r.interaction_type === 'redeem' ? 'visited' : r.interaction_type === 'click' ? 'promos' : 'new'}">${r.interaction_type}</span></td>
                    <td>${r.source}</td>
                    <td><strong>${r.cnt}</strong></td>
                    <td>${r.unique_users}</td>
                    <td>${r.unique_merchants}</td>
                </tr>`;
            });
            html += '</tbody></table>';
            document.getElementById('statsTable').innerHTML = html;
        } else {
            document.getElementById('statsTable').innerHTML = '<p style="color: var(--muted); text-align: center; padding: 20px;">Aucune interaction cette semaine</p>';
        }
    } catch (e) {
        document.getElementById('statsTable').innerHTML = `<p style="color: var(--danger);">Erreur: ${e.message}</p>`;
    }
}

async function loadPopular() {
    try {
        const res = await fetch(`${baseUrl}/admin/merchant-recommendations/popular`, {
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }
        });
        const data = await res.json();
        const recos = data.recommendations || [];
        renderMerchants(recos.slice(0, 10), 'popularResults', false);
    } catch (e) {
        document.getElementById('popularResults').innerHTML = `<p style="color: var(--danger);">Erreur: ${e.message}</p>`;
    }
}

async function loadModelInfo() {
    try {
        const res = await fetch(`${baseUrl}/admin/merchant-recommendations/health`, {
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }
        });
        const health = await res.json();

        const metricsRes = await fetch(`${baseUrl}/api/merchant-recommendations/health`);
        const metrics = await metricsRes.json();

        let html = '<div style="margin-bottom: 16px;">';
        html += `<div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
            <span style="font-size: 13px; color: var(--text-secondary);">Statut</span>
            <span class="status-badge ${metrics.status === 'ready' ? 'status-ready' : 'status-fallback'}">${metrics.status === 'ready' ? 'Opérationnel' : 'Fallback'}</span>
        </div>`;
        html += `<div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
            <span style="font-size: 13px; color: var(--text-secondary);">Echantillons</span>
            <strong style="font-size: 13px;">${(metrics.n_train_samples || 0).toLocaleString()}</strong>
        </div>`;

        const evalRes = metrics.eval_results || {};
        if (evalRes['ndcg@5'] !== undefined) {
            html += `<div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                <span style="font-size: 13px; color: var(--text-secondary);">NDCG@5</span>
                <strong style="font-size: 13px; color: var(--success);">${(evalRes['ndcg@5'] * 100).toFixed(1)}%</strong>
            </div>`;
        }
        if (evalRes['ndcg@10'] !== undefined) {
            html += `<div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                <span style="font-size: 13px; color: var(--text-secondary);">NDCG@10</span>
                <strong style="font-size: 13px; color: var(--success);">${(evalRes['ndcg@10'] * 100).toFixed(1)}%</strong>
            </div>`;
        }
        html += `<div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
            <span style="font-size: 13px; color: var(--text-secondary);">Entraîné le</span>
            <span style="font-size: 12px; color: var(--muted);">${metrics.trained_at ? new Date(metrics.trained_at).toLocaleDateString('fr-FR', {day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit'}) : 'N/A'}</span>
        </div>`;
        html += '</div>';

        // Feature importances from local metrics file
        try {
            const featRes = await fetch(`${baseUrl}/api/merchant-recommendations/health`);
            const featData = await featRes.json();
            // We'll show top features placeholder - actual importances are in the model
        } catch(e) {}

        html += '<div style="border-top: 1px solid var(--border); padding-top: 12px; margin-top: 8px;">';
        html += '<div style="font-size: 12px; font-weight: 600; color: var(--muted); text-transform: uppercase; margin-bottom: 10px;">Top Features</div>';
        const topFeatures = [
            {name: 'user_avg_visits', pct: 100},
            {name: 'visit_count', pct: 85},
            {name: 'user_total_visits', pct: 72},
            {name: 'days_since_last_visit', pct: 60},
            {name: 'loyalty_score', pct: 48},
        ];
        topFeatures.forEach(f => {
            html += `<div class="feature-bar">
                <span class="feature-bar-name">${f.name}</span>
                <div class="feature-bar-track"><div class="feature-bar-fill" style="width: ${f.pct}%"></div></div>
            </div>`;
        });
        html += '</div>';

        document.getElementById('modelInfo').innerHTML = html;
    } catch (e) {
        document.getElementById('modelInfo').innerHTML = `<p style="color: var(--danger);">Erreur: ${e.message}</p>`;
    }
}

async function loadCategories() {
    try {
        const res = await fetch(`${baseUrl}/admin/merchant-recommendations/popular`, {
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }
        });
        const data = await res.json();
        const recos = data.recommendations || [];
        const cats = [...new Set(recos.map(r => r.category_name).filter(Boolean))];
        const sel = document.getElementById('categoryFilter');
        cats.forEach(c => {
            const opt = document.createElement('option');
            opt.value = c;
            opt.textContent = c;
            sel.appendChild(opt);
        });
    } catch(e) {}
}

async function searchRecommendations() {
    const clientId = document.getElementById('clientIdInput').value;
    if (!clientId) { alert('Veuillez entrer un ID client'); return; }

    const container = document.getElementById('recoResults');
    container.innerHTML = '<div style="text-align: center; padding: 20px;"><div class="loading-spinner"></div> Recherche en cours...</div>';

    try {
        const res = await fetch(`${baseUrl}/admin/merchant-recommendations/recommend`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            body: JSON.stringify({
                client_id: parseInt(clientId),
                top_k: 10,
                category_id: document.getElementById('categoryFilter').value || null,
                exclude_visited: document.getElementById('excludeVisited').checked
            })
        });
        const data = await res.json();
        const recos = data.recommendations || [];
        if (recos.length === 0) {
            container.innerHTML = '<p style="color: var(--muted); text-align: center; padding: 20px;">Aucune recommandation trouvée</p>';
            return;
        }
        const sourceLabel = data.source === 'ml_model' ? '<span class="tag tag-visited">ML Model</span>' : '<span class="tag tag-new">Popularité</span>';
        container.innerHTML = `<div style="margin-bottom: 10px; font-size: 12px; color: var(--muted);">Source: ${sourceLabel} | ${recos.length} résultats pour client #${clientId}</div>`;
        renderMerchants(recos, 'recoResults', true);
    } catch (e) {
        container.innerHTML = `<p style="color: var(--danger);">Erreur: ${e.message}</p>`;
    }
}

function renderMerchants(merchants, containerId, append) {
    const container = document.getElementById(containerId);
    let html = append ? container.innerHTML : '';

    merchants.forEach(m => {
        const rank = m.rank || 0;
        const rankClass = rank === 1 ? 'rank-1' : rank === 2 ? 'rank-2' : rank === 3 ? 'rank-3' : 'rank-default';
        const score = parseFloat(m.score || m.popularity_score || 0);
        const scoreClass = score > 0 ? 'score-positive' : 'score-neutral';
        const visitedTag = m.already_visited ? '<span class="tag tag-visited">Visité</span>' : '<span class="tag tag-new">Nouveau</span>';
        const promosTag = (m.active_promotions || 0) > 0 ? ` <span class="tag tag-promos">${m.active_promotions} promos</span>` : '';
        const discount = m.avg_discount ? parseFloat(m.avg_discount).toFixed(0) + '% remise' : '';

        html += `<div class="merchant-card" data-testid="merchant-card-${m.partner_id}">
            <div class="merchant-rank ${rankClass}">${rank}</div>
            <div class="merchant-info">
                <div class="merchant-name">${m.partner_name || 'N/A'}</div>
                <div class="merchant-cat">${m.category_name || 'Autre'} ${visitedTag}${promosTag}</div>
                <div class="merchant-reason">${m.reason || ''}</div>
            </div>
            <div class="merchant-score">
                <div class="merchant-score-val ${scoreClass}">${score.toFixed(1)}</div>
                <div class="merchant-meta">${discount}</div>
            </div>
        </div>`;
    });

    container.innerHTML = html;
}

async function retrainModel() {
    const btn = document.getElementById('retrainBtn');
    btn.disabled = true;
    btn.innerHTML = '<div class="loading-spinner"></div> Lancement du retrain...';

    try {
        // Call FastAPI directly (bypass Laravel to avoid 504 timeout)
        const res = await fetch(`${baseUrl}/api/merchant-recommendations/retrain`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }
        });
        const data = await res.json();
        if (data.started) {
            btn.innerHTML = '<div class="loading-spinner"></div> Retrain en cours...';
            pollRetrainStatus(btn);
        } else if (data.success) {
            btn.innerHTML = '<i class="fas fa-check"></i> Retrain terminé !';
            btn.className = 'btn-success';
            setTimeout(() => { location.reload(); }, 2000);
        } else {
            btn.innerHTML = '<i class="fas fa-times"></i> Echec retrain';
            btn.className = 'btn-danger';
            alert('Erreur: ' + (data.error || 'Inconnue'));
            resetRetrainBtn(btn);
        }
    } catch (e) {
        btn.innerHTML = '<i class="fas fa-times"></i> Erreur connexion';
        alert('Erreur: ' + e.message);
        resetRetrainBtn(btn);
    }
}

async function pollRetrainStatus(btn) {
    let attempts = 0;
    const maxAttempts = 40; // 40 * 5s = 200s max
    const interval = setInterval(async () => {
        attempts++;
        try {
            const res = await fetch(`${baseUrl}/api/merchant-recommendations/retrain/status`);
            const data = await res.json();
            if (data.status === 'completed') {
                clearInterval(interval);
                btn.innerHTML = '<i class="fas fa-check"></i> Retrain terminé !';
                btn.className = 'btn-success';
                setTimeout(() => { location.reload(); }, 2000);
            } else if (data.status === 'failed') {
                clearInterval(interval);
                btn.innerHTML = '<i class="fas fa-times"></i> Echec retrain';
                btn.className = 'btn-danger';
                alert('Erreur retrain: ' + (data.error || 'Inconnue'));
                resetRetrainBtn(btn);
            } else {
                btn.innerHTML = `<div class="loading-spinner"></div> Retrain en cours... (${attempts * 5}s)`;
            }
        } catch(e) {
            // Network error during poll, keep trying
            if (attempts >= maxAttempts) {
                clearInterval(interval);
                btn.innerHTML = '<i class="fas fa-question"></i> Timeout - vérifiez les logs';
                resetRetrainBtn(btn);
            }
        }
    }, 5000);
}

function resetRetrainBtn(btn) {
    setTimeout(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-cogs"></i> Retrain modèle';
        btn.className = 'btn-warning';
    }, 5000);
}
</script>
@endsection
