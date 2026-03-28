@extends('layouts.app')

@section('title', 'Monitoring & Alertes')

@section('content')
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">

<div class="container-fluid px-3 py-3" id="monitoring-root">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="fw-bold mb-0"><i class="fas fa-heartbeat text-danger me-2"></i>Monitoring & Alertes</h4>
        <div class="d-flex align-items-center gap-2">
            <span id="last-refresh" class="text-muted small">--</span>
            <span id="auto-refresh-badge" class="badge bg-success">Auto-refresh: ON</span>
            <button class="btn btn-sm btn-outline-primary" onclick="refreshAll()"><i class="fas fa-sync-alt"></i></button>
            <a href="/dashboard" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Dashboard</a>
        </div>
    </div>

    <div id="health-banner" class="alert alert-info mb-3 d-flex align-items-center" role="alert">
        <i class="fas fa-spinner fa-spin me-2"></i> Chargement du statut de santé...
    </div>

    <div class="d-flex align-items-center gap-2 mb-3" id="alert-badges">
        <span class="badge bg-danger fs-6" id="badge-critical">0 Critiques</span>
        <span class="badge bg-warning text-dark fs-6" id="badge-warning">0 Warnings</span>
        <span class="badge bg-info text-dark fs-6" id="badge-info">0 Info</span>
        <div class="ms-auto">
            <button class="btn btn-sm btn-outline-success" onclick="acknowledgeAll()"><i class="fas fa-check-double me-1"></i>Tout acquitter</button>
            <button class="btn btn-sm btn-outline-danger ms-1" onclick="clearAllAlerts()"><i class="fas fa-trash me-1"></i>Purger</button>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-4 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-bold border-bottom"><i class="fas fa-server me-2 text-primary"></i>Services</div>
                <div class="card-body p-0" id="services-panel"><div class="text-center py-4 text-muted">Chargement...</div></div>
            </div>
        </div>
        <div class="col-lg-4 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-bold border-bottom"><i class="fas fa-database me-2 text-success"></i>Cache Redis</div>
                <div class="card-body" id="cache-panel"><div class="text-center py-4 text-muted">Chargement...</div></div>
            </div>
        </div>
        <div class="col-lg-4 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-bold border-bottom"><i class="fas fa-fire me-2 text-warning"></i>Warmup Cache</div>
                <div class="card-body" id="warmup-panel"><div class="text-center py-4 text-muted">Chargement...</div></div>
            </div>
        </div>
        <div class="col-lg-4 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-bold border-bottom"><i class="fas fa-table me-2 text-info"></i>Base de Données</div>
                <div class="card-body" id="db-panel"><div class="text-center py-4 text-muted">Chargement...</div></div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-bold border-bottom"><i class="fas fa-chart-line me-2 text-primary"></i>Temps de Réponse API</div>
                <div class="card-body"><canvas id="apiResponseChart" height="180"></canvas></div>
            </div>
        </div>

        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-bold border-bottom"><i class="fas fa-bell me-2 text-danger"></i>Alertes récentes</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light"><tr><th>Sévérité</th><th>Type</th><th>Message</th><th>Date</th><th>Action</th></tr></thead>
                            <tbody id="alerts-tbody"><tr><td colspan="5" class="text-center py-3 text-muted">Chargement...</td></tr></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-bold border-bottom d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-stethoscope me-2 text-success"></i>Health Checks Détaillés</span>
                    <button class="btn btn-sm btn-outline-primary" onclick="runHealthCheck()"><i class="fas fa-play me-1"></i>Relancer</button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light"><tr><th>Composant</th><th>Statut</th><th>Message</th><th>Détails</th></tr></thead>
                            <tbody id="healthcheck-tbody"><tr><td colspan="4" class="text-center py-3 text-muted">Cliquez "Relancer"</td></tr></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .status-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; }
    .status-dot.healthy { background: #28a745; }
    .status-dot.warning { background: #ffc107; }
    .status-dot.critical { background: #dc3545; animation: pulse-red 1.5s infinite; }
    .status-dot.degraded { background: #ffc107; }
    @keyframes pulse-red { 0%, 100% { opacity: 1; } 50% { opacity: 0.4; } }
    .service-row { padding: 0.6rem 1rem; border-bottom: 1px solid #f0f0f0; display: flex; align-items: center; justify-content: space-between; }
    .service-row:last-child { border-bottom: none; }
    .metric-label { font-size: 0.75rem; color: #6c757d; text-transform: uppercase; letter-spacing: 0.5px; }
    .metric-value { font-size: 1.3rem; font-weight: 700; }
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
@verbatim
<script>
const API_BASE = '/api/monitoring';
const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';
let apiChart = null;

async function fetchJSON(url) {
    const res = await fetch(url);
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return res.json();
}

const statusMap = {healthy:'success',warning:'warning',critical:'danger',degraded:'warning',info:'info'};
const sevMap = {critical:'danger',warning:'warning',info:'info'};
function statusBadge(s) { return `<span class="badge bg-${statusMap[s]||'secondary'}">${s}</span>`; }
function severityBadge(s) { return `<span class="badge bg-${sevMap[s]||'secondary'}">${s}</span>`; }

async function loadDashboard() {
    try {
        const data = await fetchJSON(`${API_BASE}/dashboard`);
        renderServices(data.services);
        renderCache(data.cache);
        renderDb(data.database);
        renderApiChart(data.api_history);
        updateHealthBanner(data.status);
    } catch (e) {
        document.getElementById('health-banner').innerHTML = `<i class="fas fa-exclamation-triangle me-2"></i>Erreur: ${e.message}`;
        document.getElementById('health-banner').className = 'alert alert-danger mb-3';
    }
}

async function loadAlerts() {
    try {
        const data = await fetchJSON(`${API_BASE}/alerts`);
        renderAlerts(data.alerts);
        const s = data.stats;
        document.getElementById('badge-critical').textContent = `${s.by_severity.critical||0} Critiques`;
        document.getElementById('badge-warning').textContent = `${s.by_severity.warning||0} Warnings`;
        document.getElementById('badge-info').textContent = `${s.by_severity.info||0} Info`;
    } catch (e) { console.error('Alerts:', e); }
}

async function loadWarmup() {
    try {
        const d = await fetchJSON(`${API_BASE}/warmup-status`);
        const pct = d.coverage_pct;
        const barClass = pct>=80?'bg-success':pct>=50?'bg-warning':'bg-danger';
        const lw = d.last_warmup;
        const lwHtml = lw && lw !== 'N/A' ? `<div class="small text-muted mt-2">Dernier: ${new Date(lw.completed_at).toLocaleString('fr-FR')} (${lw.cached} OK, ${lw.errors} err, ${lw.duration_seconds}s)</div>` : '<div class="small text-muted mt-2">Aucun warmup enregistré</div>';
        document.getElementById('warmup-panel').innerHTML = `
            <div class="text-center mb-2"><div class="metric-label">Couverture</div><div class="metric-value ${pct>=80?'text-success':pct>=50?'text-warning':'text-danger'}">${pct}%</div></div>
            <div class="progress" style="height:8px"><div class="progress-bar ${barClass}" style="width:${pct}%"></div></div>
            <div class="small text-center mt-1">${d.total_cached} / ${d.total_expected} entrées</div>${lwHtml}`;
    } catch (e) { console.error('Warmup:', e); }
}

function updateHealthBanner(status) {
    const b = document.getElementById('health-banner');
    if (status === 'healthy') { b.className='alert alert-success mb-3'; b.innerHTML='<i class="fas fa-check-circle me-2"></i>Tous les systèmes sont opérationnels'; }
    else if (status === 'error'||status === 'critical') { b.className='alert alert-danger mb-3'; b.innerHTML='<i class="fas fa-exclamation-triangle me-2"></i>Problème détecté'; }
    else { b.className='alert alert-warning mb-3'; b.innerHTML='<i class="fas fa-exclamation-circle me-2"></i>Dégradation détectée'; }
}

function renderServices(svcs) {
    let h = '';
    for (const [n, s] of Object.entries(svcs)) {
        h += `<div class="service-row"><div><span class="status-dot ${s.status}"></span> <strong class="ms-2">${n.toUpperCase()}</strong></div><div><span class="badge bg-${s.latency_ms<100?'success':s.latency_ms<1000?'warning':'danger'}">${s.latency_ms}ms</span></div></div>`;
    }
    document.getElementById('services-panel').innerHTML = h || '<div class="p-3 text-muted">N/A</div>';
}

function renderCache(c) {
    if (c.error) { document.getElementById('cache-panel').innerHTML=`<div class="text-danger">${c.error}</div>`; return; }
    document.getElementById('cache-panel').innerHTML = `
        <div class="row text-center g-2">
            <div class="col-6"><div class="metric-label">Mémoire</div><div class="metric-value text-primary">${c.memory_used||'N/A'}</div></div>
            <div class="col-6"><div class="metric-label">Hit Rate</div><div class="metric-value text-success">${c.hit_rate||'N/A'}</div></div>
            <div class="col-4"><div class="metric-label">Clés</div><div class="metric-value">${c.total_keys||0}</div></div>
            <div class="col-4"><div class="metric-label">Hits</div><div class="metric-value text-success">${(c.hits||0).toLocaleString()}</div></div>
            <div class="col-4"><div class="metric-label">Misses</div><div class="metric-value text-warning">${(c.misses||0).toLocaleString()}</div></div>
        </div>`;
}

function renderDb(db) {
    if (db.error) { document.getElementById('db-panel').innerHTML=`<div class="text-danger">${db.error}</div>`; return; }
    const icons = {clients:'users',subscriptions:'id-card',transactions_history:'exchange-alt',history:'history',partners:'store',users:'user-shield'};
    let h = '<div class="row text-center g-2">';
    for (const [k, v] of Object.entries(db)) { h += `<div class="col-4"><div class="metric-label"><i class="fas fa-${icons[k]||'database'} me-1"></i>${k.replace(/_/g,' ')}</div><div class="metric-value">${typeof v==='number'?v.toLocaleString():v}</div></div>`; }
    h += '</div>';
    document.getElementById('db-panel').innerHTML = h;
}

function renderApiChart(history) {
    if (!history || !history.length) return;
    const ctx = document.getElementById('apiResponseChart');
    if (!ctx) return;
    const last = history.slice(-50);
    const labels = last.map((h,i)=>(h.endpoint||'').split('/').pop()||`#${i}`);
    const data = last.map(h=>h.time_ms||0);
    const colors = data.map(v=>v>5000?'rgba(220,53,69,0.7)':v>1000?'rgba(255,193,7,0.7)':'rgba(40,167,69,0.7)');
    if (apiChart) apiChart.destroy();
    apiChart = new Chart(ctx, { type:'bar', data:{labels, datasets:[{label:'ms', data, backgroundColor:colors, borderWidth:0, borderRadius:3}]}, options:{responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true, title:{display:true,text:'ms'}}, x:{display:false}}} });
}

function renderAlerts(alerts) {
    const tb = document.getElementById('alerts-tbody');
    if (!alerts || !alerts.length) { tb.innerHTML='<tr><td colspan="5" class="text-center py-3 text-muted">Aucune alerte</td></tr>'; return; }
    let h = '';
    alerts.slice(0,30).forEach(a => {
        h += `<tr class="${a.acknowledged?'opacity-50':''}"><td>${severityBadge(a.severity)}</td><td><code class="small">${a.type}</code></td><td>${a.message}</td><td class="text-nowrap small">${new Date(a.created_at).toLocaleString('fr-FR')}</td><td>${!a.acknowledged?`<button class="btn btn-sm btn-outline-success py-0" onclick="ackAlert('${a.id}')"><i class="fas fa-check"></i></button>`:'<i class="fas fa-check-circle text-success"></i>'}</td></tr>`;
    });
    tb.innerHTML = h;
}

async function ackAlert(id) { await fetch(`${API_BASE}/alerts/${id}/acknowledge`, {method:'POST', headers:{'X-CSRF-TOKEN':CSRF}}); loadAlerts(); }
async function acknowledgeAll() { await fetch(`${API_BASE}/alerts/acknowledge-all`, {method:'POST', headers:{'X-CSRF-TOKEN':CSRF}}); loadAlerts(); }
async function clearAllAlerts() { if(!confirm('Purger toutes les alertes ?')) return; await fetch(`${API_BASE}/alerts`, {method:'DELETE', headers:{'X-CSRF-TOKEN':CSRF}}); loadAlerts(); }

async function runHealthCheck() {
    const tb = document.getElementById('healthcheck-tbody');
    tb.innerHTML = '<tr><td colspan="4" class="text-center py-3"><i class="fas fa-spinner fa-spin me-2"></i>Exécution...</td></tr>';
    try {
        const res = await fetch(`${API_BASE}/health`);
        const d = await res.json();
        let h = '';
        for (const [comp, check] of Object.entries(d.checks)) {
            const det = Object.entries(check).filter(([k])=>!['status','message'].includes(k)).map(([k,v])=>`${k}: ${JSON.stringify(v)}`).join(', ');
            h += `<tr><td><strong>${comp}</strong></td><td>${statusBadge(check.status)}</td><td>${check.message}</td><td class="small text-muted">${det}</td></tr>`;
        }
        tb.innerHTML = h;
        updateHealthBanner(d.overall_status);
        loadAlerts();
    } catch (e) { tb.innerHTML=`<tr><td colspan="4" class="text-danger">Erreur: ${e.message}</td></tr>`; }
}

async function refreshAll() {
    document.getElementById('last-refresh').textContent = 'Rafraîchissement...';
    await Promise.all([loadDashboard(), loadAlerts(), loadWarmup()]);
    document.getElementById('last-refresh').textContent = `Mis à jour: ${new Date().toLocaleTimeString('fr-FR')}`;
}

refreshAll();
setInterval(refreshAll, 30000);
</script>
@endverbatim
@endsection
