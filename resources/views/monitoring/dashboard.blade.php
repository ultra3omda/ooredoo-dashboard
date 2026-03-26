@extends('layouts.app')

@section('title', 'Monitoring - Performance Dashboard')

@section('content')
<div class="container-fluid px-4 py-3" data-testid="monitoring-dashboard">
    <div class="d-flex" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <h2 style="color: #e63946; font-weight: 700; margin:0; font-size:1.4rem;">
            <i class="fas fa-chart-line" style="margin-right:8px;"></i>Monitoring & Performance
        </h2>
        <div>
            <button onclick="refreshMonitoring()" data-testid="refresh-monitoring-btn" style="background:#e63946;color:white;border:none;padding:8px 16px;border-radius:6px;cursor:pointer;font-size:13px;">
                <i class="fas fa-sync-alt" style="margin-right:4px;"></i>Rafraichir
            </button>
            <span style="margin-left:12px;color:#64748b;font-size:12px;" id="lastUpdate">--</span>
        </div>
    </div>

    <!-- Service Health Cards -->
    <div class="row g-3 mb-4" id="serviceCards">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100" data-testid="mysql-status-card">
                <div class="card-body text-center">
                    <div class="mb-2"><i class="fas fa-database fa-2x" style="color: #e63946;"></i></div>
                    <h6 class="text-muted mb-1">MySQL</h6>
                    <div id="mysqlStatus" class="badge bg-secondary">--</div>
                    <div class="mt-2 small text-muted">Latence: <span id="mysqlLatency">--</span>ms</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100" data-testid="redis-status-card">
                <div class="card-body text-center">
                    <div class="mb-2"><i class="fas fa-memory fa-2x" style="color: #457b9d;"></i></div>
                    <h6 class="text-muted mb-1">Redis Cache</h6>
                    <div id="redisStatus" class="badge bg-secondary">--</div>
                    <div class="mt-2 small text-muted">Latence: <span id="redisLatency">--</span>ms</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100" data-testid="cache-hitrate-card">
                <div class="card-body text-center">
                    <div class="mb-2"><i class="fas fa-bolt fa-2x" style="color: #2a9d8f;"></i></div>
                    <h6 class="text-muted mb-1">Cache Hit Rate</h6>
                    <div id="cacheHitRate" class="h4 mb-0 fw-bold" style="color: #2a9d8f;">--</div>
                    <div class="mt-2 small text-muted">Clés: <span id="cacheKeys">--</span></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100" data-testid="monitoring-time-card">
                <div class="card-body text-center">
                    <div class="mb-2"><i class="fas fa-stopwatch fa-2x" style="color: #f4a261;"></i></div>
                    <h6 class="text-muted mb-1">Temps Monitoring</h6>
                    <div id="monitoringTime" class="h4 mb-0 fw-bold" style="color: #f4a261;">--</div>
                    <div class="mt-2 small text-muted">Mem Redis: <span id="redisMemory">--</span></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <!-- Database Volumes -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100" data-testid="db-volumes-card">
                <div class="card-header bg-white border-0">
                    <h6 class="mb-0 fw-bold"><i class="fas fa-table me-2"></i>Volumes Base de Donnees</h6>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr><th>Table</th><th class="text-end">Lignes</th></tr>
                        </thead>
                        <tbody id="dbVolumes">
                            <tr><td colspan="2" class="text-center text-muted py-3">Chargement...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- API Response Times Chart -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100" data-testid="api-history-card">
                <div class="card-header bg-white border-0">
                    <h6 class="mb-0 fw-bold"><i class="fas fa-clock me-2"></i>Historique Temps de Reponse API</h6>
                </div>
                <div class="card-body">
                    <canvas id="apiHistoryChart" height="250"></canvas>
                    <div id="noApiHistory" class="text-center text-muted py-4" style="display:none;">
                        <i class="fas fa-info-circle me-1"></i>Aucun historique disponible. Les donnees apparaitront apres des appels dashboard.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Cache Details -->
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm" data-testid="cache-details-card">
                <div class="card-header bg-white border-0">
                    <h6 class="mb-0 fw-bold"><i class="fas fa-layer-group me-2"></i>Details Cache Redis</h6>
                </div>
                <div class="card-body">
                    <div class="row text-center" id="cacheDetails">
                        <div class="col-md-2">
                            <div class="small text-muted">Driver</div>
                            <div class="fw-bold" id="cacheDriver">--</div>
                        </div>
                        <div class="col-md-2">
                            <div class="small text-muted">Hits</div>
                            <div class="fw-bold text-success" id="cacheHits">--</div>
                        </div>
                        <div class="col-md-2">
                            <div class="small text-muted">Misses</div>
                            <div class="fw-bold text-danger" id="cacheMisses">--</div>
                        </div>
                        <div class="col-md-2">
                            <div class="small text-muted">Section Keys</div>
                            <div class="fw-bold" id="cacheSectionKeys">--</div>
                        </div>
                        <div class="col-md-2">
                            <div class="small text-muted">Memoire</div>
                            <div class="fw-bold" id="cacheMemory">--</div>
                        </div>
                        <div class="col-md-2">
                            <div class="small text-muted">Uptime</div>
                            <div class="fw-bold" id="cacheUptime">--</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@section('scripts')
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
let apiChart = null;

async function refreshMonitoring() {
    try {
        const res = await fetch('/api/monitoring/dashboard', {
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content }
        });
        const data = await res.json();

        // Update status badges
        const statusColor = (s) => s === 'healthy' ? 'bg-success' : s === 'degraded' ? 'bg-warning' : 'bg-danger';
        document.getElementById('mysqlStatus').className = 'badge ' + statusColor(data.services?.mysql?.status);
        document.getElementById('mysqlStatus').textContent = data.services?.mysql?.status || '--';
        document.getElementById('mysqlLatency').textContent = data.services?.mysql?.latency_ms ?? '--';

        document.getElementById('redisStatus').className = 'badge ' + statusColor(data.services?.redis?.status);
        document.getElementById('redisStatus').textContent = data.services?.redis?.status || '--';
        document.getElementById('redisLatency').textContent = data.services?.redis?.latency_ms ?? '--';

        // Cache
        document.getElementById('cacheHitRate').textContent = data.cache?.hit_rate || '--';
        document.getElementById('cacheKeys').textContent = data.cache?.total_keys ?? '--';
        document.getElementById('monitoringTime').textContent = (data.monitoring_time_ms ?? '--') + 'ms';
        document.getElementById('redisMemory').textContent = data.cache?.memory_used || '--';

        // Cache details
        document.getElementById('cacheDriver').textContent = data.cache?.driver || '--';
        document.getElementById('cacheHits').textContent = Number(data.cache?.hits || 0).toLocaleString();
        document.getElementById('cacheMisses').textContent = Number(data.cache?.misses || 0).toLocaleString();
        document.getElementById('cacheSectionKeys').textContent = data.cache?.section_cached_keys ?? '--';
        document.getElementById('cacheMemory').textContent = data.cache?.memory_used || '--';
        const uptime = data.cache?.uptime_seconds ?? 0;
        document.getElementById('cacheUptime').textContent = uptime > 86400 ? Math.floor(uptime/86400) + 'j' : uptime > 3600 ? Math.floor(uptime/3600) + 'h' : Math.floor(uptime/60) + 'min';

        // DB volumes
        if (data.database) {
            const tbody = document.getElementById('dbVolumes');
            const labels = {'clients':'Clients','subscriptions':'Abonnements','transactions_history':'Transactions','history':'Historique','partners':'Partenaires','users':'Utilisateurs'};
            tbody.innerHTML = Object.entries(data.database).filter(([k]) => k !== 'error').map(([key, val]) => 
                `<tr><td>${labels[key] || key}</td><td class="text-end fw-bold">${Number(val).toLocaleString('fr-FR')}</td></tr>`
            ).join('');
        }

        // API History chart
        const history = data.api_history || [];
        if (history.length > 0) {
            document.getElementById('noApiHistory').style.display = 'none';
            document.getElementById('apiHistoryChart').style.display = 'block';
            renderApiChart(history);
        } else {
            document.getElementById('noApiHistory').style.display = 'block';
            document.getElementById('apiHistoryChart').style.display = 'none';
        }

        document.getElementById('lastUpdate').textContent = 'Mis a jour: ' + new Date().toLocaleTimeString('fr-FR');
    } catch (e) {
        console.error('Monitoring error:', e);
    }
}

function renderApiChart(history) {
    const labels = history.map((h, i) => {
        const d = new Date(h.timestamp);
        return d.toLocaleTimeString('fr-FR', {hour:'2-digit', minute:'2-digit'});
    });
    const times = history.map(h => h.time_ms || 0);
    const colors = history.map(h => h.cache_hit ? '#2a9d8f' : '#e63946');

    if (apiChart) apiChart.destroy();
    apiChart = new Chart(document.getElementById('apiHistoryChart'), {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label: 'Temps de reponse (ms)',
                data: times,
                backgroundColor: colors,
                borderWidth: 0,
                borderRadius: 4,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (ctx) => {
                            const h = history[ctx.dataIndex];
                            return `${ctx.raw}ms (${h.cache_hit ? 'Cache HIT' : 'Cache MISS'})`;
                        }
                    }
                }
            },
            scales: {
                y: { beginAtZero: true, title: { display: true, text: 'ms' } },
                x: { display: true, ticks: { maxRotation: 45 } }
            }
        }
    });
}

// Auto-refresh toutes les 30s
refreshMonitoring();
setInterval(refreshMonitoring, 30000);
</script>
@endsection
@endsection
