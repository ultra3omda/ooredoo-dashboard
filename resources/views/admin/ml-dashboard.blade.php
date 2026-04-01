@extends('layouts.app')

@section('title', 'ML Dashboard - Optimisation Facturation')

@section('styles')
<style>
.ml-kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
.ml-kpi { background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 18px 20px; box-shadow: var(--shadow-sm); border-left: 4px solid var(--brand-primary); min-width: 0; }
.ml-kpi-label { font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: var(--muted); font-weight: 600; margin-bottom: 6px; }
.ml-kpi-value { font-size: 26px; font-weight: 700; color: var(--text-primary); font-family: 'Outfit', sans-serif; }
.ml-kpi.success { border-left-color: var(--success); }
.ml-kpi.warning { border-left-color: var(--warning); }
.ml-kpi.info { border-left-color: #3b82f6; }

.ml-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 24px; }
.rec-card { padding: 14px; border: 1px solid var(--border); border-radius: 10px; margin-bottom: 10px; display: flex; align-items: center; gap: 12px; transition: box-shadow 0.2s; background: var(--card); }
.rec-card:hover { box-shadow: var(--shadow-md); }
.rec-body { flex: 1; min-width: 0; }
.rec-title { font-weight: 600; font-size: 14px; color: var(--text-primary); margin-bottom: 2px; }
.rec-reason { font-size: 12px; color: var(--muted); }
.rec-impact { text-align: center; min-width: 80px; }
.rec-impact-value { font-size: 18px; font-weight: 700; color: var(--success); }
.rec-actions { display: flex; gap: 4px; }
.rec-actions button { padding: 4px 8px; border-radius: 6px; border: none; cursor: pointer; font-size: 11px; }

.pred-prob { display: flex; align-items: center; gap: 8px; }
.pred-bar { flex: 1; height: 8px; background: var(--table-stripe); border-radius: 4px; overflow: hidden; }
.pred-bar-fill { height: 100%; border-radius: 4px; }
.pred-pct { font-size: 12px; font-weight: 600; min-width: 40px; text-align: right; }

.segment-item { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid var(--border); }
.segment-item:last-child { border-bottom: none; }

@media (max-width: 768px) {
    .ml-kpi-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
    .ml-grid { grid-template-columns: 1fr; }
    .ml-kpi { padding: 14px; }
    .ml-kpi-value { font-size: 20px; }
    .rec-card { flex-direction: column; text-align: center; }
    .rec-impact { min-width: auto; }
    .rec-actions { justify-content: center; }
}
@media (max-width: 480px) {
    .ml-kpi-grid { grid-template-columns: 1fr 1fr; gap: 8px; }
    .ml-kpi { padding: 10px; }
    .ml-kpi-value { font-size: 18px; }
    .ml-kpi-label { font-size: 10px; }
}
</style>
@endsection

@section('content')
<!-- Page Header -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 12px;">
    <div>
        <h1 style="font-family: 'Outfit', sans-serif; font-size: 22px; font-weight: 700; color: var(--text-primary); margin: 0;">
            <i class="fas fa-brain" style="color: var(--brand-primary);"></i> ML Dashboard
        </h1>
        <p style="color: var(--muted); font-size: 13px; margin: 4px 0 0;">Intelligence artificielle pour maximiser les revenus</p>
    </div>
    <div class="btn-group">
        <button class="btn-primary" onclick="refreshDashboard(event)" data-testid="ml-refresh-btn">
            <i class="fas fa-sync-alt"></i> Actualiser
        </button>
        <button class="btn-success" onclick="generateRecommendations()" data-testid="ml-generate-btn">
            <i class="fas fa-magic"></i> Recommandations
        </button>
    </div>
</div>

@if($errors->any())
    <div class="alert alert-danger">
        @foreach($errors->all() as $error)
            <p style="margin: 0;">{{ $error }}</p>
        @endforeach
    </div>
@endif

<!-- KPIs -->
<div class="ml-kpi-grid" data-testid="ml-kpi-grid">
    <div class="ml-kpi">
        <div class="ml-kpi-label">Taux de Succes Global</div>
        <div class="ml-kpi-value" id="global-success-rate">{{ isset($portfolioStats['avg_success_rate']) ? $portfolioStats['avg_success_rate'] . '%' : '--' }}</div>
    </div>
    <div class="ml-kpi success">
        <div class="ml-kpi-label">Clients Actifs</div>
        <div class="ml-kpi-value" id="total-clients">{{ isset($portfolioStats['total_clients']) ? number_format($portfolioStats['total_clients']) : '--' }}</div>
    </div>
    <div class="ml-kpi warning">
        <div class="ml-kpi-label">Risque de Churn</div>
        <div class="ml-kpi-value" id="churn-risk">{{ isset($portfolioStats['avg_churn_risk']) ? $portfolioStats['avg_churn_risk'] . '%' : '--' }}</div>
    </div>
    <div class="ml-kpi info">
        <div class="ml-kpi-label">Recommandations Actives</div>
        <div class="ml-kpi-value" id="active-recommendations">{{ isset($recommendations['summary']['total']) ? $recommendations['summary']['total'] : '--' }}</div>
    </div>
</div>

<!-- Charts Row -->
<div class="ml-grid">
    <!-- Trends Chart -->
    <div class="cp-card">
        <div class="cp-card-header">
            <span class="cp-card-title"><i class="fas fa-chart-line" style="color: var(--brand-primary);"></i> Tendances</span>
            <div class="btn-group">
                <button class="btn-outline btn-sm active" onclick="switchTrend('success_rate', this)">Taux Succes</button>
                <button class="btn-outline btn-sm" onclick="switchTrend('revenue', this)">Revenus</button>
                <button class="btn-outline btn-sm" onclick="switchTrend('churn', this)">Churn</button>
            </div>
        </div>
        <canvas id="trendsChart" height="100" data-testid="ml-trends-chart"></canvas>
    </div>

    <!-- Segments -->
    <div class="cp-card">
        <div class="cp-card-header">
            <span class="cp-card-title"><i class="fas fa-chart-pie" style="color: var(--brand-primary);"></i> Segments</span>
        </div>
        <div style="height: 200px; position: relative;">
            <canvas id="segmentsChart" data-testid="ml-segments-chart"></canvas>
        </div>
        <div id="segments-legend" style="margin-top: 12px;">
            @if(isset($segmentStats))
                @foreach($segmentStats as $segment)
                    <div class="segment-item">
                        <span style="font-size: 13px; color: var(--text-secondary);">{{ ucfirst(str_replace('_', ' ', $segment['segment'])) }}</span>
                        <span class="badge badge-primary">{{ number_format($segment['count']) }}</span>
                    </div>
                @endforeach
            @endif
        </div>
    </div>
</div>

<!-- Recommendations -->
<div class="cp-card" style="margin-bottom: 24px;">
    <div class="cp-card-header">
        <span class="cp-card-title"><i class="fas fa-lightbulb" style="color: var(--accent);"></i> Recommandations Prioritaires</span>
    </div>
    <div id="recommendations-container" data-testid="ml-recommendations">
        @if(isset($recommendations['recommendations']) && count($recommendations['recommendations']) > 0)
            @foreach($recommendations['recommendations'] as $rec)
                <div class="rec-card" data-id="{{ $rec->id }}">
                    <span class="badge badge-{{ $rec->priority == 'critical' ? 'danger' : ($rec->priority == 'high' ? 'warning' : 'info') }}">
                        {{ strtoupper($rec->priority) }}
                    </span>
                    <div class="rec-body">
                        <div class="rec-title">{{ $rec->recommended_value }}</div>
                        <div class="rec-reason">{{ ucfirst(str_replace('_', ' ', $rec->recommendation_type)) }} - {{ $rec->recommendation_reason }}</div>
                    </div>
                    <div class="rec-impact">
                        <div class="rec-impact-value">+{{ $rec->expected_improvement_percentage }}%</div>
                        <div style="font-size: 10px; color: var(--muted);">attendu</div>
                    </div>
                    <div class="rec-actions">
                        <button class="btn-success btn-sm" onclick="approveRecommendation({{ $rec->id }})" title="Approuver"><i class="fas fa-check"></i></button>
                        <button style="background: #3b82f6; color: #fff;" class="btn-sm" onclick="simulateRecommendation({{ $rec->id }})" title="Simuler"><i class="fas fa-calculator"></i></button>
                        <button class="btn-danger btn-sm" onclick="rejectRecommendation({{ $rec->id }})" title="Rejeter"><i class="fas fa-times"></i></button>
                    </div>
                </div>
            @endforeach
        @else
            <div style="text-align: center; padding: 40px; color: var(--muted);">
                <i class="fas fa-magic" style="font-size: 32px; margin-bottom: 12px; display: block;"></i>
                Aucune recommandation. Cliquez sur "Recommandations" pour en generer.
            </div>
        @endif
    </div>
</div>

<!-- Predictions Table -->
<div class="cp-card" style="margin-bottom: 24px;">
    <div class="cp-card-header">
        <span class="cp-card-title"><i class="fas fa-crystal-ball" style="color: var(--brand-primary);"></i> Predictions de Paiement</span>
    </div>
    <div style="overflow-x: auto; -webkit-overflow-scrolling: touch;">
        <table class="cp-table" data-testid="ml-predictions-table">
            <thead>
                <tr>
                    <th>Client</th>
                    <th>Segment</th>
                    <th>Prob. Succes</th>
                    <th>Timing Optimal</th>
                    <th>Prix Optimal</th>
                    <th>Confiance</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="predictions-tbody">
                @if(isset($predictions['predictions']) && count($predictions['predictions']) > 0)
                    @foreach($predictions['predictions'] as $p)
                        <tr>
                            <td style="font-weight: 500;">{{ $p->client_nom ?? 'N/A' }} {{ $p->client_prenom ?? '' }}</td>
                            <td><span class="badge badge-secondary">{{ ucfirst(str_replace('_', ' ', $p->client_segment ?? 'unknown')) }}</span></td>
                            <td>
                                <div class="pred-prob">
                                    <div class="pred-bar">
                                        <div class="pred-bar-fill" style="width: {{ ($p->payment_success_probability ?? 0) * 100 }}%; background: {{ ($p->payment_success_probability ?? 0) > 0.5 ? 'var(--success)' : (($p->payment_success_probability ?? 0) > 0.3 ? 'var(--warning)' : 'var(--danger)') }};"></div>
                                    </div>
                                    <span class="pred-pct">{{ round(($p->payment_success_probability ?? 0) * 100, 1) }}%</span>
                                </div>
                            </td>
                            <td style="font-size: 12px;">{{ $p->optimal_billing_time ? \Carbon\Carbon::parse($p->optimal_billing_time)->format('d/m H:i') : 'N/A' }}</td>
                            <td><strong>{{ $p->optimal_price ?? 3 }} TND</strong> <span style="font-size: 11px; color: var(--muted);">{{ $p->optimal_frequency ?? 'monthly' }}</span></td>
                            <td><span class="badge badge-{{ ($p->success_confidence ?? 0) > 0.7 ? 'success' : (($p->success_confidence ?? 0) > 0.5 ? 'warning' : 'danger') }}">{{ round(($p->success_confidence ?? 0) * 100) }}%</span></td>
                            <td><button class="btn-primary btn-sm" onclick="viewClientDetails({{ $p->client_id }})"><i class="fas fa-eye"></i></button></td>
                        </tr>
                    @endforeach
                @else
                    <tr><td colspan="7" style="text-align: center; padding: 30px; color: var(--muted);">Aucune prediction disponible</td></tr>
                @endif
            </tbody>
        </table>
    </div>
</div>

<!-- ML Configuration Section -->
<div class="cp-card" style="margin-bottom: 24px;">
    <div class="cp-card-header">
        <span class="cp-card-title"><i class="fas fa-chart-bar" style="color: var(--brand-primary);"></i> Performance du Modele</span>
    </div>
    <div id="model-performance-container" data-testid="ml-model-performance">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px;">
            @php
                $mm = $modelMetrics ?? [];
                $trainedAt = isset($mm['trained_at']) ? \Carbon\Carbon::parse($mm['trained_at'])->format('d/m/Y H:i') : 'Jamais';
                $featureImp = $mm['feature_importance'] ?? [];
                arsort($featureImp);
                $topFeatures = array_slice($featureImp, 0, 5, true);
                
                $models = [
                    ['name' => 'Billing Success Predictor (LightGBM)', 'version' => 'v3.0', 
                     'accuracy' => $mm['accuracy'] ?? 0, 'precision' => $mm['precision'] ?? 0, 
                     'recall' => $mm['recall'] ?? 0, 'f1' => $mm['f1'] ?? 0, 
                     'auc_roc' => $mm['auc_roc'] ?? 0, 'status' => 'active',
                     'samples' => ($mm['samples_train'] ?? 0) + ($mm['samples_test'] ?? 0),
                     'trained_at' => $trainedAt],
                ];
            @endphp
            @foreach($models as $m)
            <div style="padding: 16px; border: 1px solid var(--border); border-radius: 10px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                    <h4 style="font-size: 14px; font-weight: 600; color: var(--text-primary); margin: 0;">{{ $m['name'] }}</h4>
                    <span class="badge badge-success">Actif</span>
                </div>
                <div style="font-size: 11px; color: var(--muted); margin-bottom: 6px;">Version: {{ $m['version'] }} | Entraine: {{ $m['trained_at'] }} | {{ number_format($m['samples']) }} samples</div>
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-bottom: 12px;">
                    @foreach(['Accuracy' => $m['accuracy'], 'Precision' => $m['precision'], 'Recall' => $m['recall'], 'F1 Score' => $m['f1'], 'AUC-ROC' => $m['auc_roc']] as $label => $val)
                    <div>
                        <span style="font-size: 10px; color: var(--muted);">{{ $label }}</span>
                        <div class="progress" style="margin: 3px 0;"><div class="progress-bar {{ $val >= 70 ? 'bg-success' : ($val >= 50 ? 'bg-warning' : 'bg-danger') }}" style="width: {{ min($val, 100) }}%"></div></div>
                        <span style="font-size: 12px; font-weight: 700;">{{ $val }}%</span>
                    </div>
                    @endforeach
                </div>
            </div>
            @endforeach
            
            <!-- Feature Importance -->
            <div style="padding: 16px; border: 1px solid var(--border); border-radius: 10px;">
                <h4 style="font-size: 14px; font-weight: 600; color: var(--text-primary); margin: 0 0 12px;">Importance des Features</h4>
                @if(count($topFeatures) > 0)
                    @php $maxImp = max(array_values($topFeatures)) ?: 1; @endphp
                    @foreach($topFeatures as $fname => $fval)
                    <div style="margin-bottom: 8px;">
                        <div style="display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 2px;">
                            <span style="color: var(--text-secondary);">{{ str_replace('_', ' ', ucfirst($fname)) }}</span>
                            <span style="font-weight: 600;">{{ $fval }}</span>
                        </div>
                        <div class="progress"><div class="progress-bar bg-success" style="width: {{ round($fval / $maxImp * 100) }}%"></div></div>
                    </div>
                    @endforeach
                @else
                    <p style="font-size: 13px; color: var(--muted);">Aucune donnee. Entrainez le modele.</p>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- ML Configuration Section -->
<div class="cp-card" style="margin-bottom: 24px;">
    <div class="cp-card-header">
        <span class="cp-card-title"><i class="fas fa-cog" style="color: var(--muted);"></i> Configuration ML</span>
    </div>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px;">
        <!-- Feature Extraction -->
        <div style="padding: 16px; border: 1px solid var(--border); border-radius: 10px;">
            <h4 style="font-size: 14px; font-weight: 600; color: var(--text-primary); margin: 0 0 8px;">Extraction de Features</h4>
            <p style="font-size: 12px; color: var(--muted); margin: 0 0 12px;">Extraire les features clients pour l'entrainement du modele.</p>
            <button class="btn-primary btn-sm" onclick="extractFeatures()" data-testid="ml-extract-btn">
                <i class="fas fa-database"></i> Extraire Features
            </button>
            <div id="extract-status" style="margin-top: 8px; font-size: 12px; color: var(--muted);"></div>
        </div>
        <!-- Model Training -->
        <div style="padding: 16px; border: 1px solid var(--border); border-radius: 10px;">
            <h4 style="font-size: 14px; font-weight: 600; color: var(--text-primary); margin: 0 0 8px;">Entrainement du Modele</h4>
            <p style="font-size: 12px; color: var(--muted); margin: 0 0 12px;">Entrainer le modele ML avec les dernieres donnees.</p>
            <button class="btn-warning btn-sm" onclick="trainModel()" data-testid="ml-train-btn">
                <i class="fas fa-graduation-cap"></i> Entrainer Modele
            </button>
            <div id="train-status" style="margin-top: 8px; font-size: 12px; color: var(--muted);"></div>
        </div>
        <!-- A/B Testing -->
        <div style="padding: 16px; border: 1px solid var(--border); border-radius: 10px;">
            <h4 style="font-size: 14px; font-weight: 600; color: var(--text-primary); margin: 0 0 8px;">A/B Testing</h4>
            <p style="font-size: 12px; color: var(--muted); margin: 0 0 12px;">Lancer un test A/B pour valider les predictions.</p>
            <button class="btn-primary btn-sm" onclick="startABTest()" data-testid="ml-abtest-btn">
                <i class="fas fa-flask"></i> Lancer Test A/B
            </button>
            <div id="abtest-status" style="margin-top: 8px; font-size: 12px; color: var(--muted);"></div>
        </div>
        <!-- AI Report Generation -->
        <div style="padding: 16px; border: 1px solid var(--border); border-radius: 10px;">
            <h4 style="font-size: 14px; font-weight: 600; color: var(--text-primary); margin: 0 0 8px;">Rapport IA Hebdomadaire</h4>
            <p style="font-size: 12px; color: var(--muted); margin: 0 0 12px;">Generer un rapport d'analyse IA avec GPT-4o.</p>
            <button class="btn-primary btn-sm" onclick="generateAIReport()" data-testid="ml-report-btn" style="background: linear-gradient(135deg, #6C4BA0, #3b82f6); border: none;">
                <i class="fas fa-file-alt"></i> Generer Rapport IA
            </button>
            <div id="report-status" style="margin-top: 8px; font-size: 12px; color: var(--muted);"></div>
        </div>
    </div>
</div>

<!-- AI Report Section -->
<div class="cp-card" style="margin-bottom: 24px;" id="report-section">
    <div class="cp-card-header">
        <span class="cp-card-title"><i class="fas fa-file-alt" style="color: #3b82f6;"></i> Dernier Rapport IA</span>
        <button class="btn-outline btn-sm" onclick="loadLatestReport()" data-testid="ml-load-report-btn">
            <i class="fas fa-sync-alt"></i> Charger
        </button>
    </div>
    <div id="ai-report-container" data-testid="ml-ai-report" style="padding: 8px;">
        <div style="text-align: center; padding: 40px; color: var(--muted);">
            <i class="fas fa-robot" style="font-size: 32px; margin-bottom: 12px; display: block;"></i>
            Aucun rapport. Cliquez "Generer Rapport IA" pour en creer un.
        </div>
    </div>
</div>

<!-- Simulation Modal -->
<div id="simulationModal" style="display:none;" data-testid="simulation-modal">
    <div class="modal-overlay" onclick="if(event.target===this) document.getElementById('simulationModal').style.display='none'">
        <div class="modal-content">
            <div class="modal-header">
                <span class="cp-card-title"><i class="fas fa-chart-bar" style="color: var(--brand-primary);"></i> Simulation d'Impact</span>
                <button class="modal-close" onclick="document.getElementById('simulationModal').style.display='none'">&times;</button>
            </div>
            <div id="simulation-content"></div>
        </div>
    </div>
</div>

<!-- Client Details Modal -->
<div id="clientDetailsModal" style="display:none;" data-testid="client-details-modal">
    <div class="modal-overlay" onclick="if(event.target===this) document.getElementById('clientDetailsModal').style.display='none'">
        <div class="modal-content">
            <div class="modal-header">
                <span class="cp-card-title"><i class="fas fa-user" style="color: var(--brand-primary);"></i> Details Client ML</span>
                <button class="modal-close" onclick="document.getElementById('clientDetailsModal').style.display='none'">&times;</button>
            </div>
            <div id="client-details-content"></div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
const mlDashboard = {
    charts: {},
    currentTrendType: 'success_rate',
    data: @json([
        'portfolio' => $portfolioStats ?? [],
        'segments' => $segmentStats ?? [],
        'trends' => $trendData ?? []
    ])
};

document.addEventListener('DOMContentLoaded', function() {
    initializeCharts();
});

function getChartColors() {
    const isDark = document.documentElement.classList.contains('dark-mode');
    return {
        grid: isDark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)',
        text: isDark ? '#A1A1AA' : '#52525b'
    };
}

function applyTrendsToChart() {
    const trends = mlDashboard.data.trends?.daily_trends || [];
    const labels = trends.map(t => t.calculation_date ? t.calculation_date.split(' ')[0] : '');
    const successData = trends.map(t => Number(t.avg_success_rate || 0).toFixed(2));
    const revenueData = trends.map(t => Number(t.total_payments || 0));
    const churnData = trends.map(t => Number(t.avg_churn_risk || 0).toFixed(2));

    if (!mlDashboard.charts.trends) return;
    mlDashboard.charts.trends.data.labels = labels;
    mlDashboard.charts.trends.data.datasets = [
        { label: 'Taux de Succes (%)', data: successData, borderColor: '#6C4BA0', backgroundColor: 'rgba(108,75,160,0.1)', fill: true, yAxisID: 'y' },
        { label: 'Revenus (TND)', data: revenueData, borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,0.1)', fill: true, yAxisID: 'y1', hidden: true },
        { label: 'Risque Churn (%)', data: churnData, borderColor: '#D4A843', backgroundColor: 'rgba(212,168,67,0.1)', fill: true, yAxisID: 'y', hidden: true }
    ];
    mlDashboard.charts.trends.update('none');
}

function initializeCharts() {
    const trendsCtx = document.getElementById('trendsChart');
    if (!trendsCtx) return;
    const colors = getChartColors();
    
    mlDashboard.charts.trends = new Chart(trendsCtx.getContext('2d'), {
        type: 'line',
        data: { labels: [], datasets: [] },
        options: {
            responsive: true, animation: false,
            plugins: { legend: { display: true, labels: { color: colors.text } } },
            scales: {
                y: { beginAtZero: true, max: 100, position: 'left', grid: { color: colors.grid }, ticks: { color: colors.text } },
                y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, ticks: { color: colors.text } },
                x: { grid: { color: colors.grid }, ticks: { color: colors.text } }
            }
        }
    });
    applyTrendsToChart();
    switchTrend('success_rate');

    const segEl = document.getElementById('segmentsChart');
    if (segEl) {
        const segLabels = mlDashboard.data.segments?.length > 0 ? mlDashboard.data.segments.map(s => (s.segment || '').replace('_', ' ')) : ['Aucune donnee'];
        const segData = mlDashboard.data.segments?.length > 0 ? mlDashboard.data.segments.map(s => Number(s.count || 0)) : [0];
        mlDashboard.charts.segments = new Chart(segEl.getContext('2d'), {
            type: 'doughnut',
            data: { labels: segLabels, datasets: [{ data: segData, backgroundColor: ['#6C4BA0', '#10b981', '#3b82f6', '#D4A843', '#ef4444', '#71717a'] }] },
            options: { responsive: true, animation: false, maintainAspectRatio: false, plugins: { legend: { display: false } } }
        });
    }
}

function switchTrend(type, btn) {
    mlDashboard.currentTrendType = type;
    if (!mlDashboard.charts.trends?.data?.datasets) return;
    mlDashboard.charts.trends.data.datasets.forEach(ds => {
        ds.hidden = !(type === 'success_rate' && ds.label.includes('Succes')) && !(type === 'revenue' && ds.label.includes('Revenus')) && !(type === 'churn' && ds.label.includes('Churn'));
    });
    document.querySelectorAll('.btn-outline.btn-sm').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');
    mlDashboard.charts.trends.update('none');
}

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

function refreshDashboard(ev) {
    const btn = ev?.target?.closest('button');
    if (btn) btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Chargement...';
    fetch('/admin/ml-dashboard/data', { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => r.json())
        .then(data => { if (data.success) updateDashboardData(data.data); showNotification('Dashboard actualise', 'success'); })
        .catch(e => showNotification('Erreur: ' + e.message, 'error'))
        .finally(() => { if (btn) btn.innerHTML = '<i class="fas fa-sync-alt"></i> Actualiser'; });
}

function updateDashboardData(data) {
    if (!data) return;
    const p = data.portfolio || {};
    const el = (id, v) => { const e = document.getElementById(id); if (e) e.textContent = v; };
    el('global-success-rate', p.avg_success_rate != null ? p.avg_success_rate + '%' : '--');
    el('total-clients', p.total_clients != null ? Number(p.total_clients).toLocaleString() : '--');
    el('churn-risk', p.avg_churn_risk != null ? p.avg_churn_risk + '%' : '--');
    el('active-recommendations', data.recommendations?.summary?.total ?? '--');
    if (data.recommendations) renderRecommendations(data.recommendations);
    if (data.trends) { mlDashboard.data.trends = data.trends; applyTrendsToChart(); switchTrend(mlDashboard.currentTrendType); }
}

function generateRecommendations() {
    const btn = event.target.closest('button');
    if (btn) btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    fetch('/admin/ml-dashboard/recommendations/generate', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() }, body: '{}' })
        .then(r => r.json())
        .then(data => { if (data.success) { showNotification('Recommandations generees', 'success'); refreshDashboard({}); } else throw new Error(data.message); })
        .catch(e => showNotification('Erreur: ' + e.message, 'error'))
        .finally(() => { if (btn) btn.innerHTML = '<i class="fas fa-magic"></i> Recommandations'; });
}

function renderRecommendations(recData) {
    const container = document.getElementById('recommendations-container');
    if (!container) return;
    const list = recData?.recommendations || [];
    if (list.length === 0) { container.innerHTML = '<div style="text-align:center;padding:40px;color:var(--muted);"><i class="fas fa-magic" style="font-size:32px;margin-bottom:12px;display:block;"></i>Aucune recommandation disponible</div>'; return; }
    container.innerHTML = list.map(r => {
        const bc = r.priority === 'critical' ? 'danger' : r.priority === 'high' ? 'warning' : 'info';
        return `<div class="rec-card" data-id="${r.id}"><span class="badge badge-${bc}">${(r.priority||'').toUpperCase()}</span><div class="rec-body"><div class="rec-title">${(r.recommended_value||'').replace(/</g,'&lt;')}</div><div class="rec-reason">${(r.recommendation_type||'').replace(/_/g,' ')} - ${(r.recommendation_reason||'').replace(/</g,'&lt;')}</div></div><div class="rec-impact"><div class="rec-impact-value">+${r.expected_improvement_percentage||0}%</div><div style="font-size:10px;color:var(--muted);">attendu</div></div><div class="rec-actions"><button class="btn-success btn-sm" onclick="approveRecommendation(${r.id})"><i class="fas fa-check"></i></button><button style="background:#3b82f6;color:#fff;" class="btn-sm" onclick="simulateRecommendation(${r.id})"><i class="fas fa-calculator"></i></button><button class="btn-danger btn-sm" onclick="rejectRecommendation(${r.id})"><i class="fas fa-times"></i></button></div></div>`;
    }).join('');
}

function approveRecommendation(id) { updateRecStatus(id, 'approved'); }
function rejectRecommendation(id) { updateRecStatus(id, 'rejected'); }
function updateRecStatus(id, status) {
    fetch('/admin/ml-dashboard/recommendations/status', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() }, body: JSON.stringify({ recommendation_id: id, status }) })
        .then(r => r.json()).then(d => { if (d.success) { document.querySelector(`[data-id="${id}"]`)?.remove(); showNotification('Statut mis a jour', 'success'); } })
        .catch(e => showNotification('Erreur', 'error'));
}

function simulateRecommendation(id) {
    fetch('/admin/ml-dashboard/recommendations/simulate', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() }, body: JSON.stringify({ recommendation_id: id }) })
        .then(r => r.json()).then(d => { if (d.success) showSimulationModal(d.simulation); })
        .catch(e => showNotification('Erreur simulation', 'error'));
}

function showSimulationModal(sim) {
    document.getElementById('simulation-content').innerHTML = `
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:16px;">
            <div><h4 style="font-size:14px;color:var(--muted);margin:0 0 8px;">Metriques Actuelles</h4>
                <div style="padding:12px;border:1px solid var(--border);border-radius:8px;">
                    <div style="display:flex;justify-content:space-between;padding:6px 0;"><span>Taux succes</span><strong>${sim.current_metrics.success_rate}%</strong></div>
                    <div style="display:flex;justify-content:space-between;padding:6px 0;border-top:1px solid var(--border);"><span>Revenus</span><strong>${sim.current_metrics.monthly_revenue?.toLocaleString()} TND</strong></div>
                    <div style="display:flex;justify-content:space-between;padding:6px 0;border-top:1px solid var(--border);"><span>Clients actifs</span><strong>${sim.current_metrics.active_clients?.toLocaleString()}</strong></div>
                </div>
            </div>
            <div><h4 style="font-size:14px;color:var(--success);margin:0 0 8px;">Metriques Projetees</h4>
                <div style="padding:12px;border:1px solid var(--success);border-radius:8px;">
                    <div style="display:flex;justify-content:space-between;padding:6px 0;"><span>Taux succes</span><strong style="color:var(--success);">${sim.projected_metrics.success_rate?.toFixed(2)}%</strong></div>
                    <div style="display:flex;justify-content:space-between;padding:6px 0;border-top:1px solid var(--border);"><span>Revenus</span><strong style="color:var(--success);">${sim.projected_metrics.monthly_revenue?.toLocaleString()} TND</strong></div>
                    <div style="display:flex;justify-content:space-between;padding:6px 0;border-top:1px solid var(--border);"><span>Clients actifs</span><strong>${sim.projected_metrics.active_clients?.toLocaleString()}</strong></div>
                </div>
            </div>
        </div>
        <div class="alert alert-info"><strong>Timeline:</strong> ${sim.timeline?.implementation_time || 'N/A'} | Impact complet: ${sim.timeline?.full_impact_time || 'N/A'}</div>`;
    document.getElementById('simulationModal').style.display = 'block';
}

function viewClientDetails(clientId) {
    fetch(`/admin/ml-dashboard/client/${clientId}`, { headers: { 'Accept': 'application/json' } })
        .then(r => r.json()).then(d => { if (d.success) showClientModal(d.client); })
        .catch(e => showNotification('Erreur chargement details', 'error'));
}

function showClientModal(client) {
    document.getElementById('client-details-content').innerHTML = `
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;">
            <div style="padding:12px;border:1px solid var(--border);border-radius:8px;">
                <h4 style="font-size:13px;color:var(--muted);margin:0 0 8px;">Informations</h4>
                <p style="margin:0;font-size:13px;"><strong>ID:</strong> ${client.client_id}<br><strong>Segment:</strong> ${client.features?.client_segment || 'N/A'}<br><strong>Taux succes:</strong> ${((client.features?.payment_success_rate||0)*100).toFixed(2)}%</p>
            </div>
            <div style="padding:12px;border:1px solid var(--border);border-radius:8px;">
                <h4 style="font-size:13px;color:var(--muted);margin:0 0 8px;">Prediction</h4>
                <p style="margin:0;font-size:13px;"><strong>Prob. succes:</strong> ${((client.prediction?.payment_success_probability||0)*100).toFixed(2)}%<br><strong>Prix optimal:</strong> ${client.prediction?.optimal_price||3} TND<br><strong>Timing:</strong> ${client.prediction?.optimal_billing_time||'N/A'}</p>
            </div>
            <div style="padding:12px;border:1px solid var(--border);border-radius:8px;">
                <h4 style="font-size:13px;color:var(--muted);margin:0 0 8px;">Risques</h4>
                <p style="margin:0;font-size:13px;"><strong>Churn:</strong> ${((client.features?.churn_probability||0)*100).toFixed(2)}%<br><strong>Echecs consecutifs:</strong> ${client.features?.consecutive_failures||0}<br><strong>Haute valeur:</strong> ${client.features?.is_high_value_client ? 'Oui' : 'Non'}</p>
            </div>
        </div>`;
    document.getElementById('clientDetailsModal').style.display = 'block';
}

function extractFeatures() {
    const today = new Date().toISOString().split('T')[0];
    const thirtyDaysAgo = new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
    const statusEl = document.getElementById('extract-status');
    statusEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Lancement de l\'extraction...';
    fetch('/admin/ml-dashboard/features/extract', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() }, body: JSON.stringify({ start_date: thirtyDaysAgo, end_date: today }) })
        .then(r => r.json()).then(d => {
            if (d.success && d.async) {
                statusEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Extraction en arriere-plan...';
                pollTaskStatus(d.task_id, statusEl);
            } else if (!d.success) {
                statusEl.innerHTML = '<span style="color:var(--danger)"><i class="fas fa-times"></i> ' + (d.message || 'Erreur') + '</span>';
            }
        })
        .catch(e => { statusEl.innerHTML = '<span style="color:var(--danger)"><i class="fas fa-times"></i> Erreur: ' + e.message + '</span>'; });
}

function trainModel() {
    const statusEl = document.getElementById('train-status');
    statusEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Lancement de l\'entrainement...';
    fetch('/admin/ml-dashboard/train', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() }, body: '{}' })
        .then(r => r.json()).then(d => {
            if (d.success && d.async) {
                statusEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Entrainement en arriere-plan...';
                pollTaskStatus(d.task_id, statusEl);
            } else if (!d.success) {
                statusEl.innerHTML = '<span style="color:var(--danger)"><i class="fas fa-times"></i> ' + (d.message || 'Erreur') + '</span>';
            }
        })
        .catch(e => { statusEl.innerHTML = '<span style="color:var(--danger)"><i class="fas fa-times"></i> Erreur: ' + e.message + '</span>'; });
}

function pollTaskStatus(taskId, statusEl, onComplete) {
    const interval = setInterval(() => {
        fetch('/admin/ml-dashboard/task-status?task_id=' + taskId, { headers: { 'Accept': 'application/json' } })
            .then(r => r.json()).then(d => {
                if (!d.success || !d.task) { clearInterval(interval); return; }
                const t = d.task;
                if (t.status === 'running') {
                    statusEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + (t.message || 'En cours...') + ' (' + (t.progress || 0) + '%)';
                } else if (t.status === 'completed') {
                    clearInterval(interval);
                    statusEl.innerHTML = '<span style="color:var(--success)"><i class="fas fa-check"></i> ' + (t.message || 'Termine') + '</span>';
                    if (t.metrics) { showNotification('Modele mis a jour !', 'success'); setTimeout(() => location.reload(), 2000); }
                    if (typeof onComplete === 'function') onComplete(t);
                } else if (t.status === 'failed') {
                    clearInterval(interval);
                    statusEl.innerHTML = '<span style="color:var(--danger)"><i class="fas fa-times"></i> ' + (t.message || 'Echec') + '</span>';
                }
            }).catch(() => clearInterval(interval));
    }, 3000);
}

function startABTest() {
    document.getElementById('abtest-status').textContent = 'Lancement du test...';
    fetch('/admin/ml-dashboard/ab-test/start', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() }, body: '{}' })
        .then(r => r.json()).then(d => { document.getElementById('abtest-status').textContent = d.success ? 'Test A/B lance ! ID: ' + (d.test_id || '') : 'Erreur: ' + (d.message || d.error || ''); })
        .catch(e => { document.getElementById('abtest-status').textContent = 'Erreur: ' + e.message; });
}

function generateAIReport() {
    const statusEl = document.getElementById('report-status');
    statusEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Lancement de la generation IA...';
    fetch('/admin/ml-dashboard/report/generate', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() }, body: '{}' })
        .then(r => r.json()).then(d => {
            if (d.success && d.async) {
                statusEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Rapport IA en cours de generation...';
                pollTaskStatus(d.task_id, statusEl, function() { loadLatestReport(); });
            } else {
                statusEl.innerHTML = '<span style="color:var(--danger)"><i class="fas fa-times"></i> ' + (d.message || 'Erreur') + '</span>';
            }
        })
        .catch(e => { statusEl.innerHTML = '<span style="color:var(--danger)"><i class="fas fa-times"></i> Erreur: ' + e.message + '</span>'; });
}

function loadLatestReport() {
    const container = document.getElementById('ai-report-container');
    container.innerHTML = '<div style="text-align:center;padding:20px;"><i class="fas fa-spinner fa-spin" style="font-size:20px;color:var(--brand-primary);"></i></div>';
    fetch('/admin/ml-dashboard/report/latest', { headers: { 'Accept': 'application/json' } })
        .then(r => r.json()).then(d => {
            if (d.success && d.report) {
                renderAIReport(d.report, d.generated_at);
            } else {
                container.innerHTML = '<div style="text-align:center;padding:40px;color:var(--muted);"><i class="fas fa-robot" style="font-size:32px;margin-bottom:12px;display:block;"></i>Aucun rapport disponible.</div>';
            }
        })
        .catch(e => { container.innerHTML = '<div style="color:var(--danger);padding:16px;">Erreur: ' + e.message + '</div>'; });
}

function renderAIReport(report, generatedAt) {
    const container = document.getElementById('ai-report-container');
    let html = '';
    
    // Header
    html += '<div style="margin-bottom:16px;padding-bottom:12px;border-bottom:2px solid var(--brand-primary);">';
    html += '<h3 style="font-size:16px;font-weight:700;color:var(--text-primary);margin:0;">' + (report.titre || 'Rapport IA') + '</h3>';
    if (generatedAt) html += '<span style="font-size:11px;color:var(--muted);">Genere le ' + new Date(generatedAt).toLocaleString('fr-FR') + '</span>';
    html += '</div>';
    
    // Executive summary
    if (report.resume_executif) {
        html += '<div style="padding:12px;background:var(--table-stripe);border-radius:8px;margin-bottom:16px;font-size:13px;color:var(--text-secondary);line-height:1.6;">' + report.resume_executif + '</div>';
    }
    
    // KPIs
    if (report.kpis && report.kpis.length > 0) {
        html += '<h4 style="font-size:14px;font-weight:600;margin:0 0 8px;">KPIs Cles</h4>';
        html += '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px;margin-bottom:16px;">';
        report.kpis.forEach(kpi => {
            const tColor = kpi.tendance === 'hausse' ? 'var(--success)' : (kpi.tendance === 'baisse' ? 'var(--danger)' : 'var(--muted)');
            const tIcon = kpi.tendance === 'hausse' ? 'fa-arrow-up' : (kpi.tendance === 'baisse' ? 'fa-arrow-down' : 'fa-minus');
            html += '<div style="padding:10px;border:1px solid var(--border);border-radius:8px;">';
            html += '<div style="font-size:11px;color:var(--muted);text-transform:uppercase;">' + kpi.nom + '</div>';
            html += '<div style="font-size:18px;font-weight:700;color:var(--text-primary);">' + kpi.valeur + ' <i class="fas ' + tIcon + '" style="font-size:12px;color:' + tColor + ';"></i></div>';
            if (kpi.commentaire) html += '<div style="font-size:11px;color:var(--muted);margin-top:4px;">' + kpi.commentaire + '</div>';
            html += '</div>';
        });
        html += '</div>';
    }
    
    // Alerts
    if (report.alertes && report.alertes.length > 0) {
        html += '<h4 style="font-size:14px;font-weight:600;margin:0 0 8px;">Alertes</h4>';
        report.alertes.forEach(a => {
            const colors = { critique: '#ef4444', attention: '#D4A843', info: '#3b82f6' };
            html += '<div style="padding:8px 12px;border-left:3px solid ' + (colors[a.niveau] || '#71717a') + ';background:var(--table-stripe);border-radius:0 6px 6px 0;margin-bottom:6px;font-size:12px;">';
            html += '<strong style="text-transform:uppercase;font-size:10px;color:' + (colors[a.niveau] || '#71717a') + ';">' + a.niveau + '</strong> ' + a.message;
            html += '</div>';
        });
    }
    
    // Recommendations
    if (report.recommandations && report.recommandations.length > 0) {
        html += '<h4 style="font-size:14px;font-weight:600;margin:12px 0 8px;">Recommandations</h4>';
        html += '<div style="display:grid;gap:8px;margin-bottom:16px;">';
        report.recommandations.forEach(r => {
            html += '<div style="padding:10px;border:1px solid var(--border);border-radius:8px;display:flex;align-items:center;gap:10px;">';
            html += '<span class="badge badge-' + (r.priorite === 'P0' ? 'danger' : r.priorite === 'P1' ? 'warning' : 'info') + '">' + r.priorite + '</span>';
            html += '<div style="flex:1;"><div style="font-size:13px;font-weight:500;">' + r.action + '</div>';
            if (r.impact_estime) html += '<div style="font-size:11px;color:var(--success);">Impact: ' + r.impact_estime + '</div>';
            html += '</div></div>';
        });
        html += '</div>';
    }
    
    // Model status
    if (report.modele_ml) {
        html += '<div style="padding:10px;border:1px solid var(--border);border-radius:8px;margin-bottom:12px;">';
        html += '<strong style="font-size:12px;">Modele ML:</strong> Accuracy ' + (report.modele_ml.accuracy || 'N/A') + '% - ' + (report.modele_ml.statut || '') + ' - ' + (report.modele_ml.commentaire || '');
        html += '</div>';
    }
    
    // Raw text fallback
    if (report.raw) {
        html += '<div style="white-space:pre-wrap;font-size:13px;line-height:1.6;color:var(--text-secondary);">' + report.resume_executif + '</div>';
    }
    
    container.innerHTML = html;
}
</script>
@endsection
