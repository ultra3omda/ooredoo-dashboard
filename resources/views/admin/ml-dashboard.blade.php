@extends('layouts.app')

@section('title', 'ML Dashboard - Optimisation Facturation')

@section('content')
<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">🤖 ML Dashboard - Optimisation Facturation</h1>
            <p class="mb-0 text-muted">Intelligence artificielle pour maximiser les revenus Timwe</p>
        </div>
        <div class="btn-group">
            <button class="btn btn-primary" onclick="refreshDashboard(event)">
                <i class="fas fa-sync-alt"></i> Actualiser
            </button>
            <button class="btn btn-success" onclick="generateRecommendations()">
                <i class="fas fa-magic"></i> Nouvelles Recommandations
            </button>
        </div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">
            @foreach($errors->all() as $error)
                <p class="mb-0">{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <!-- KPIs Principaux -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Taux de Succès Global
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="global-success-rate">
                                {{ isset($portfolioStats['avg_success_rate']) ? $portfolioStats['avg_success_rate'] . '%' : '...' }}
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-chart-line fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Clients Actifs
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="total-clients">
                                {{ isset($portfolioStats['total_clients']) ? number_format($portfolioStats['total_clients']) : '...' }}
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Clients à Risque de Churn
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="churn-risk">
                                {{ isset($portfolioStats['avg_churn_risk']) ? $portfolioStats['avg_churn_risk'] . '%' : '...' }}
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Recommandations Actives
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="active-recommendations">
                                {{ isset($recommendations['summary']['total']) ? $recommendations['summary']['total'] : '...' }}
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-lightbulb fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Graphiques et Segments -->
    <div class="row">
        <!-- Graphique Tendances -->
        <div class="col-xl-8 col-lg-7">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">📈 Tendances des Performances</h6>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary active" onclick="switchTrend('success_rate')">Taux Succès</button>
                        <button class="btn btn-outline-primary" onclick="switchTrend('revenue')">Revenus</button>
                        <button class="btn btn-outline-primary" onclick="switchTrend('churn')">Churn Risk</button>
                    </div>
                </div>
                <div class="card-body">
                    <canvas id="trendsChart" height="100"></canvas>
                </div>
            </div>
        </div>

        <!-- Répartition par Segments -->
        <div class="col-xl-4 col-lg-5">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">🎯 Répartition par Segments</h6>
                </div>
                <div class="card-body">
                    <div class="chart-segments-wrapper" style="height: 200px; position: relative; width: 100%;">
                        <canvas id="segmentsChart"></canvas>
                    </div>
                    <div class="mt-3" id="segments-legend">
                        @if(isset($segmentStats))
                            @foreach($segmentStats as $segment)
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="text-sm">{{ ucfirst(str_replace('_', ' ', $segment['segment'])) }}</span>
                                    <span class="badge badge-primary">{{ number_format($segment['count']) }} clients</span>
                                </div>
                            @endforeach
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recommandations Prioritaires -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">💡 Recommandations Prioritaires</h6>
                </div>
                <div class="card-body">
                    <div id="recommendations-container">
                        @if(isset($recommendations['recommendations']) && count($recommendations['recommendations']) > 0)
                            @foreach($recommendations['recommendations'] as $recommendation)
                                <div class="recommendation-card mb-3 p-3 border rounded" data-id="{{ $recommendation->id }}">
                                    <div class="row align-items-center">
                                        <div class="col-md-1">
                                            <span class="badge badge-{{ $recommendation->priority == 'critical' ? 'danger' : ($recommendation->priority == 'high' ? 'warning' : 'info') }} badge-pill">
                                                {{ strtoupper($recommendation->priority) }}
                                            </span>
                                        </div>
                                        <div class="col-md-2">
                                            <small class="text-muted">{{ ucfirst(str_replace('_', ' ', $recommendation->recommendation_type)) }}</small>
                                        </div>
                                        <div class="col-md-6">
                                            <strong>{{ $recommendation->recommended_value }}</strong>
                                            <br><small class="text-muted">{{ $recommendation->recommendation_reason }}</small>
                                        </div>
                                        <div class="col-md-2 text-center">
                                            <div class="text-success font-weight-bold">
                                                +{{ $recommendation->expected_improvement_percentage }}%
                                            </div>
                                            <small class="text-muted">amélioration attendue</small>
                                        </div>
                                        <div class="col-md-1">
                                            <div class="btn-group-vertical btn-group-sm">
                                                <button class="btn btn-success btn-sm" onclick="approveRecommendation({{ $recommendation->id }})">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button class="btn btn-info btn-sm" onclick="simulateRecommendation({{ $recommendation->id }})">
                                                    <i class="fas fa-calculator"></i>
                                                </button>
                                                <button class="btn btn-danger btn-sm" onclick="rejectRecommendation({{ $recommendation->id }})">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        @else
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-magic fa-3x mb-3"></i>
                                <p>Aucune recommandation disponible. Cliquez sur "Nouvelles Recommandations" pour en générer.</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Prédictions Récentes -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">🔮 Prédictions de Paiement Récentes</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" id="predictions-table">
                            <thead>
                                <tr>
                                    <th>Client</th>
                                    <th>Téléphone</th>
                                    <th>Segment</th>
                                    <th>Probabilité Succès</th>
                                    <th>Timing Optimal</th>
                                    <th>Prix Optimal</th>
                                    <th>Confiance</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="predictions-tbody">
                                @if(isset($predictions['predictions']) && count($predictions['predictions']) > 0)
                                    @foreach($predictions['predictions'] as $prediction)
                                        <tr>
                                            <td>{{ $prediction->client_nom ?? 'N/A' }} {{ $prediction->client_prenom ?? '' }}</td>
                                            <td>{{ $prediction->client_telephone ?? 'N/A' }}</td>
                                            <td>
                                                <span class="badge badge-secondary">{{ ucfirst(str_replace('_', ' ', $prediction->client_segment ?? 'unknown')) }}</span>
                                            </td>
                                            <td>
                                                <div class="progress" style="height: 20px;">
                                                    <div class="progress-bar bg-{{ $prediction->payment_success_probability > 0.5 ? 'success' : ($prediction->payment_success_probability > 0.3 ? 'warning' : 'danger') }}" 
                                                         style="width: {{ ($prediction->payment_success_probability ?? 0) * 100 }}%;">
                                                        {{ round(($prediction->payment_success_probability ?? 0) * 100, 1) }}%
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <small>{{ $prediction->optimal_billing_time ? \Carbon\Carbon::parse($prediction->optimal_billing_time)->format('d/m H:i') : 'N/A' }}</small>
                                            </td>
                                            <td>
                                                <strong>{{ $prediction->optimal_price ?? 3 }} TND</strong>
                                                <br><small class="text-muted">{{ $prediction->optimal_frequency ?? 'monthly' }}</small>
                                            </td>
                                            <td>
                                                <span class="badge badge-{{ ($prediction->success_confidence ?? 0) > 0.7 ? 'success' : (($prediction->success_confidence ?? 0) > 0.5 ? 'warning' : 'danger') }}">
                                                    {{ round(($prediction->success_confidence ?? 0) * 100) }}%
                                                </span>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-info" onclick="viewClientDetails({{ $prediction->client_id }})">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Simulation -->
<div class="modal fade" id="simulationModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">📊 Simulation d'Impact</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="simulation-content">
                <!-- Contenu de simulation -->
            </div>
        </div>
    </div>
</div>

<!-- Modal Détails Client -->
<div class="modal fade" id="clientDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">👤 Détails Client ML</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="client-details-content">
                <!-- Contenu détails client -->
            </div>
        </div>
    </div>
</div>
@endsection

@section('styles')
<style>
.recommendation-card {
    transition: all 0.3s ease;
}
.recommendation-card:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}
.border-left-primary {
    border-left: 0.25rem solid #4e73df !important;
}
.border-left-success {
    border-left: 0.25rem solid #1cc88a !important;
}
.border-left-warning {
    border-left: 0.25rem solid #f6c23e !important;
}
.border-left-info {
    border-left: 0.25rem solid #36b9cc !important;
}
.progress {
    border-radius: 10px;
}
.badge-pill {
    padding: 0.5em 1em;
    font-size: 0.7em;
}
</style>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
// Configuration globale
const mlDashboard = {
    charts: {},
    currentTrendType: 'success_rate',
    data: @json([
        'portfolio' => $portfolioStats ?? [],
        'segments' => $segmentStats ?? [],
        'trends' => $trendData ?? []
    ])
};

// Initialisation
document.addEventListener('DOMContentLoaded', function() {
    initializeCharts();
    setupEventListeners();
});

// Remplir le graphique des tendances à partir des données
function applyTrendsToChart() {
    const trends = mlDashboard.data.trends && mlDashboard.data.trends.daily_trends
        ? mlDashboard.data.trends.daily_trends
        : [];
    const labels = trends.map(t => t.calculation_date ? t.calculation_date.split(' ')[0] : '');
    const successData = trends.map(t => Number(t.avg_success_rate || 0).toFixed(2));
    const revenueData = trends.map(t => Number(t.total_payments || 0));
    const churnData = trends.map(t => Number(t.avg_churn_risk || 0).toFixed(2));

    if (!mlDashboard.charts.trends) return;
    mlDashboard.charts.trends.data.labels = labels;
    mlDashboard.charts.trends.data.datasets = [
        { label: 'Taux de Succès (%)', data: successData, borderColor: '#4e73df', backgroundColor: 'rgba(78, 115, 223, 0.1)', fill: true, yAxisID: 'y' },
        { label: 'Revenus (TND)', data: revenueData, borderColor: '#1cc88a', backgroundColor: 'rgba(28, 200, 138, 0.1)', fill: true, yAxisID: 'y1', hidden: true },
        { label: 'Risque Churn (%)', data: churnData, borderColor: '#f6c23e', backgroundColor: 'rgba(246, 194, 62, 0.1)', fill: true, yAxisID: 'y', hidden: true }
    ];
    mlDashboard.charts.trends.update('none');
}

// Initialisation des graphiques
function initializeCharts() {
    const trendsCtx = document.getElementById('trendsChart');
    if (!trendsCtx) return;
    const ctx = trendsCtx.getContext('2d');
    mlDashboard.charts.trends = new Chart(ctx, {
        type: 'line',
        data: {
            labels: [],
            datasets: []
        },
        options: {
            responsive: true,
            animation: false,
            plugins: { legend: { display: true } },
            scales: {
                y: { beginAtZero: true, max: 100, type: 'linear', position: 'left' },
                y1: { beginAtZero: true, type: 'linear', position: 'right', grid: { drawOnChartArea: false } }
            }
        }
    });
    applyTrendsToChart();
    switchTrend(mlDashboard.currentTrendType);

    // Graphique des segments (taille fixe + pas d'animation pour éviter boucle infinie / requestAnimationFrame)
    const segmentsEl = document.getElementById('segmentsChart');
    if (segmentsEl) {
        if (mlDashboard.charts.segments) {
            mlDashboard.charts.segments.destroy();
            mlDashboard.charts.segments = null;
        }
        var segW = segmentsEl.parentElement ? segmentsEl.parentElement.offsetWidth : 300;
        segmentsEl.width = segW;
        segmentsEl.height = 200;
        const segmentLabels = (mlDashboard.data.segments && mlDashboard.data.segments.length > 0)
            ? mlDashboard.data.segments.map(s => (s.segment || '').replace('_', ' '))
            : ['Aucune donnée'];
        const segmentData = (mlDashboard.data.segments && mlDashboard.data.segments.length > 0)
            ? mlDashboard.data.segments.map(s => Number(s.count || 0))
            : [0];
        mlDashboard.charts.segments = new Chart(segmentsEl.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: segmentLabels,
                datasets: [{
                    data: segmentData,
                    backgroundColor: ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', '#858796', '#5a5c69']
                }]
            },
            options: {
                responsive: false,
                animation: false,
                maintainAspectRatio: false,
                layout: { padding: 8 },
                plugins: { legend: { display: false } }
            }
        });
    }
}

// Changer la courbe affichée (Taux Succès / Revenus / Churn)
function switchTrend(type) {
    mlDashboard.currentTrendType = type;
    if (!mlDashboard.charts.trends || !mlDashboard.charts.trends.data.datasets) return;
    mlDashboard.charts.trends.data.datasets.forEach((ds, i) => {
        const isSuccess = ds.label && ds.label.indexOf('Succès') !== -1;
        const isRevenue = ds.label && ds.label.indexOf('Revenus') !== -1;
        const isChurn = ds.label && ds.label.indexOf('Churn') !== -1;
        ds.hidden = !(type === 'success_rate' && isSuccess) && !(type === 'revenue' && isRevenue) && !(type === 'churn' && isChurn);
    });
    document.querySelectorAll('.card-header .btn-group-sm .btn, .card-header .btn-group .btn').forEach(btn => {
        btn.classList.toggle('active', btn.textContent.toLowerCase().indexOf(type === 'success_rate' ? 'succès' : type === 'revenue' ? 'revenus' : 'churn') !== -1);
    });
    mlDashboard.charts.trends.update('none');
}

// Remplir le bloc Recommandations Prioritaires (après génération ou actualisation)
function renderRecommendations(recommendationsData) {
    const container = document.getElementById('recommendations-container');
    if (!container) return;
    const list = (recommendationsData && recommendationsData.recommendations) ? recommendationsData.recommendations : [];
    if (list.length === 0) {
        container.innerHTML = '<div class="text-center text-muted py-4"><i class="fas fa-magic fa-3x mb-3"></i><p>Aucune recommandation disponible. Cliquez sur "Nouvelles Recommandations" pour en générer.</p></div>';
        return;
    }
    const badgeClass = (p) => (p === 'critical' ? 'danger' : (p === 'high' ? 'warning' : 'info'));
    const html = list.map(function(rec) {
        const id = rec.id || 0;
        const priority = (rec.priority || 'medium').toLowerCase();
        const type = (rec.recommendation_type || '').replace(/_/g, ' ');
        const value = (rec.recommended_value || '').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        const reason = (rec.recommendation_reason || '').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        const pct = rec.expected_improvement_percentage != null ? Number(rec.expected_improvement_percentage) : 0;
        return '<div class="recommendation-card mb-3 p-3 border rounded" data-id="' + id + '">' +
            '<div class="row align-items-center">' +
            '<div class="col-md-1"><span class="badge badge-' + badgeClass(priority) + ' badge-pill">' + (priority.toUpperCase()) + '</span></div>' +
            '<div class="col-md-2"><small class="text-muted">' + type + '</small></div>' +
            '<div class="col-md-6"><strong>' + value + '</strong><br><small class="text-muted">' + reason + '</small></div>' +
            '<div class="col-md-2 text-center"><div class="text-success font-weight-bold">+' + pct + '%</div><small class="text-muted">amélioration attendue</small></div>' +
            '<div class="col-md-1"><div class="btn-group-vertical btn-group-sm">' +
            '<button class="btn btn-success btn-sm" onclick="approveRecommendation(' + id + ')"><i class="fas fa-check"></i></button>' +
            '<button class="btn btn-info btn-sm" onclick="simulateRecommendation(' + id + ')"><i class="fas fa-calculator"></i></button>' +
            '<button class="btn btn-danger btn-sm" onclick="rejectRecommendation(' + id + ')"><i class="fas fa-times"></i></button>' +
            '</div></div></div></div>';
    }).join('');
    container.innerHTML = html;
}

// Mise à jour des données du dashboard (KPIs + graphiques + recommandations) après refresh
function updateDashboardData(data) {
    if (!data) return;
    const portfolio = data.portfolio || {};
    const recommendations = data.recommendations || {};
    const summary = recommendations.summary || {};
    const el = (id, value) => { const e = document.getElementById(id); if (e) e.textContent = value; };
    el('global-success-rate', (portfolio.avg_success_rate != null ? portfolio.avg_success_rate : '...') + (portfolio.avg_success_rate != null ? '%' : ''));
    el('total-clients', portfolio.total_clients != null ? Number(portfolio.total_clients).toLocaleString() : '...');
    el('churn-risk', (portfolio.avg_churn_risk != null ? portfolio.avg_churn_risk : '...') + (portfolio.avg_churn_risk != null ? '%' : ''));
    el('active-recommendations', summary.total != null ? summary.total : '...');
    if (data.recommendations) {
        renderRecommendations(data.recommendations);
    }
    if (data.trends) {
        mlDashboard.data.trends = data.trends;
        applyTrendsToChart();
        switchTrend(mlDashboard.currentTrendType);
    }
    if (data.segments && mlDashboard.charts.segments) {
        mlDashboard.charts.segments.data.labels = data.segments.map(s => (s.segment || '').replace('_', ' '));
        mlDashboard.charts.segments.data.datasets[0].data = data.segments.map(s => s.count || 0);
        mlDashboard.charts.segments.update('none');
    }
}

// Actualiser le dashboard
function refreshDashboard(ev) {
    const btn = ev && ev.target ? ev.target.closest('button') : null;
    if (btn) btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Actualisation...';
    fetch('/admin/ml-dashboard/data', { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateDashboardData(data.data);
                if (btn) showNotification('Dashboard actualisé avec succès', 'success');
            }
        })
        .catch(error => {
            if (btn) showNotification('Erreur lors de l\'actualisation', 'error');
            console.error('Erreur:', error);
        })
        .finally(() => {
            if (btn) btn.innerHTML = '<i class="fas fa-sync-alt"></i> Actualiser';
        });
}

// Générer de nouvelles recommandations
function generateRecommendations() {
    const btn = event.target.closest('button');
    if (btn) btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Génération...';
    const csrf = document.querySelector('meta[name="csrf-token"]');
    fetch('/admin/ml-dashboard/recommendations/generate', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrf ? csrf.getAttribute('content') : ''
        },
        body: JSON.stringify({})
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Recommandations générées. Actualisation...', 'success');
            return fetch('/admin/ml-dashboard/data', { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } }).then(r => r.json());
        }
        throw new Error(data.message || 'Erreur génération');
    })
    .then(function(apiData) {
        if (apiData && apiData.success && apiData.data) {
            updateDashboardData(apiData.data);
            showNotification('Recommandations affichées.', 'success');
        }
    })
    .catch(error => {
        showNotification('Erreur lors de la génération: ' + (error.message || error), 'error');
        console.error('Erreur:', error);
    })
    .finally(() => {
        if (btn) btn.innerHTML = '<i class="fas fa-magic"></i> Nouvelles Recommandations';
    });
}

// Approuver une recommandation
function approveRecommendation(recommendationId) {
    updateRecommendationStatus(recommendationId, 'approved');
}

// Rejeter une recommandation
function rejectRecommendation(recommendationId) {
    updateRecommendationStatus(recommendationId, 'rejected');
}

// Mettre à jour le statut d'une recommandation
function updateRecommendationStatus(recommendationId, status) {
    fetch('/admin/ml-dashboard/recommendations/status', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({
            recommendation_id: recommendationId,
            status: status
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.querySelector(`[data-id="${recommendationId}"]`).style.display = 'none';
            showNotification('Statut mis à jour', 'success');
        }
    })
    .catch(error => {
        showNotification('Erreur lors de la mise à jour', 'error');
        console.error('Erreur:', error);
    });
}

// Simuler l'impact d'une recommandation
function simulateRecommendation(recommendationId) {
    fetch(`/admin/ml-dashboard/recommendations/simulate`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({
            recommendation_id: recommendationId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSimulationModal(data.simulation);
        }
    })
    .catch(error => {
        showNotification('Erreur lors de la simulation', 'error');
        console.error('Erreur:', error);
    });
}

// Afficher le modal de simulation
function showSimulationModal(simulation) {
    const content = `
        <div class="row">
            <div class="col-md-6">
                <h6>📊 Métriques Actuelles</h6>
                <ul class="list-group">
                    <li class="list-group-item d-flex justify-content-between">
                        <span>Taux de succès</span>
                        <strong>${simulation.current_metrics.success_rate}%</strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span>Revenus mensuels</span>
                        <strong>${simulation.current_metrics.monthly_revenue.toLocaleString()} TND</strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span>Clients actifs</span>
                        <strong>${simulation.current_metrics.active_clients.toLocaleString()}</strong>
                    </li>
                </ul>
            </div>
            <div class="col-md-6">
                <h6>🚀 Métriques Projetées</h6>
                <ul class="list-group">
                    <li class="list-group-item d-flex justify-content-between">
                        <span>Taux de succès</span>
                        <strong class="text-success">${simulation.projected_metrics.success_rate.toFixed(2)}%</strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span>Revenus mensuels</span>
                        <strong class="text-success">${simulation.projected_metrics.monthly_revenue.toLocaleString()} TND</strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span>Clients actifs</span>
                        <strong>${simulation.projected_metrics.active_clients.toLocaleString()}</strong>
                    </li>
                </ul>
            </div>
        </div>
        <div class="alert alert-info mt-3">
            <h6>⏰ Timeline d'implémentation</h6>
            <p><strong>Temps d'implémentation:</strong> ${simulation.timeline.implementation_time}<br>
            <strong>Impact complet:</strong> ${simulation.timeline.full_impact_time}<br>
            <strong>Période de mesure:</strong> ${simulation.timeline.measurement_period}</p>
        </div>
    `;
    
    document.getElementById('simulation-content').innerHTML = content;
    new bootstrap.Modal(document.getElementById('simulationModal')).show();
}

// Voir les détails d'un client
function viewClientDetails(clientId) {
    fetch(`/admin/ml-dashboard/client/${clientId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showClientDetailsModal(data.client);
            }
        })
        .catch(error => {
            showNotification('Erreur lors du chargement des détails', 'error');
            console.error('Erreur:', error);
        });
}

// Afficher les détails client
function showClientDetailsModal(client) {
    const content = `
        <div class="row">
            <div class="col-md-4">
                <h6>📋 Informations Client</h6>
                <p><strong>ID:</strong> ${client.client_id}<br>
                <strong>Segment:</strong> ${client.features?.client_segment || 'N/A'}<br>
                <strong>Taux succès:</strong> ${((client.features?.payment_success_rate || 0) * 100).toFixed(2)}%</p>
            </div>
            <div class="col-md-4">
                <h6>🔮 Prédiction</h6>
                <p><strong>Prob. succès:</strong> ${((client.prediction?.payment_success_probability || 0) * 100).toFixed(2)}%<br>
                <strong>Prix optimal:</strong> ${client.prediction?.optimal_price || 3} TND<br>
                <strong>Timing:</strong> ${client.prediction?.optimal_billing_time || 'N/A'}</p>
            </div>
            <div class="col-md-4">
                <h6>⚠️ Risques</h6>
                <p><strong>Churn:</strong> ${((client.features?.churn_probability || 0) * 100).toFixed(2)}%<br>
                <strong>Échecs consécutifs:</strong> ${client.features?.consecutive_failures || 0}<br>
                <strong>Client de valeur:</strong> ${client.features?.is_high_value_client ? 'Oui' : 'Non'}</p>
            </div>
        </div>
    `;
    
    document.getElementById('client-details-content').innerHTML = content;
    new bootstrap.Modal(document.getElementById('clientDetailsModal')).show();
}

// Notifications
function showNotification(message, type = 'info') {
    const alertClass = type === 'success' ? 'alert-success' : 
                     type === 'error' ? 'alert-danger' : 'alert-info';
    
    const notification = document.createElement('div');
    notification.className = `alert ${alertClass} alert-dismissible fade show position-fixed`;
    notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    notification.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.remove();
    }, 5000);
}

// Event listeners
function setupEventListeners() {
    // Actualisation automatique toutes les 10 minutes, uniquement si l'onglet est visible
    setInterval(function() {
        if (document.visibilityState === 'visible') refreshDashboard();
    }, 10 * 60 * 1000);
}
</script>
@endsection