<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><style>
body { font-family: 'Segoe UI', Arial, sans-serif; background: #f4f0fa; margin: 0; padding: 20px; color: #2d2d2d; }
.container { max-width: 680px; margin: 0 auto; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 24px rgba(108,75,160,0.1); }
.header { background: linear-gradient(135deg, #1a1040, #4c3489); padding: 32px 40px; color: #fff; }
.header h1 { margin: 0 0 4px; font-size: 22px; font-weight: 700; }
.header .subtitle { color: #D4A843; font-size: 15px; font-weight: 600; }
.header .period { color: rgba(255,255,255,0.7); font-size: 13px; margin-top: 8px; }
.section { padding: 28px 40px; border-bottom: 1px solid #ede8f5; }
.section:last-child { border-bottom: none; }
.section-title { font-size: 16px; font-weight: 700; color: #4c3489; margin: 0 0 16px; text-transform: uppercase; letter-spacing: 0.5px; }
.kpi-grid { display: flex; flex-wrap: wrap; gap: 12px; }
.kpi-box { flex: 1 1 140px; background: #f8f5ff; border-radius: 8px; padding: 16px; text-align: center; border-left: 3px solid #6C4BA0; }
.kpi-box .value { font-size: 24px; font-weight: 800; color: #4c3489; }
.kpi-box .label { font-size: 11px; color: #71717a; text-transform: uppercase; margin-top: 4px; }
.kpi-box .delta { font-size: 12px; margin-top: 4px; font-weight: 600; }
.up { color: #10b981; } .down { color: #ef4444; }
table { width: 100%; border-collapse: collapse; font-size: 13px; }
table th { background: #f8f5ff; color: #4c3489; text-align: left; padding: 10px 12px; font-weight: 600; border-bottom: 2px solid #ede8f5; }
table td { padding: 10px 12px; border-bottom: 1px solid #f0ecf7; }
table tr:nth-child(even) { background: #fdfbff; }
.ml-section { background: linear-gradient(135deg, #0f172a, #1e293b); border-radius: 10px; padding: 24px; margin: 16px 0; color: #fff; }
.ml-section h3 { color: #60a5fa; margin: 0 0 16px; font-size: 15px; font-weight: 700; }
.ml-grid { display: flex; flex-wrap: wrap; gap: 10px; }
.ml-box { flex: 1 1 120px; background: rgba(255,255,255,0.06); border-radius: 8px; padding: 12px; text-align: center; border: 1px solid rgba(255,255,255,0.1); }
.ml-box .v { font-size: 20px; font-weight: 800; color: #60a5fa; }
.ml-box .l { font-size: 10px; color: rgba(255,255,255,0.6); text-transform: uppercase; margin-top: 4px; }
.seg-bar { height: 6px; border-radius: 3px; margin-top: 6px; display: flex; overflow: hidden; }
.seg-bar span { height: 100%; }
.ai-section { background: linear-gradient(135deg, #fef9ef, #fff8e8); border-left: 4px solid #D4A843; border-radius: 8px; padding: 20px; margin: 16px 0; }
.ai-section h3 { color: #D4A843; margin: 0 0 12px; font-size: 15px; }
.ai-section .content { font-size: 13px; line-height: 1.7; color: #44403c; white-space: pre-line; }
.footer { background: #f8f5ff; text-align: center; padding: 20px 40px; font-size: 11px; color: #a1a1aa; }
</style></head>
<body>
<div class="container">
  <div class="header">
    <h1>Rapport Associe</h1>
    <div class="subtitle">Performance Reseau & Analyse Strategique</div>
    <div class="period">{{ $period_start }} - {{ $period_end }}</div>
  </div>

  <div class="section">
    <div class="section-title">Indicateurs Globaux</div>
    @php $gk = $global_kpis ?? []; @endphp
    <div class="kpi-grid">
      <div class="kpi-box">
        <div class="value">{{ number_format($gk['activeSubscriptions']['current'] ?? 0) }}</div>
        <div class="label">Abonnes Actifs</div>
      </div>
      <div class="kpi-box">
        <div class="value">{{ number_format($gk['retentionRate']['current'] ?? 0, 1) }}%</div>
        <div class="label">Retention</div>
      </div>
      <div class="kpi-box">
        <div class="value">{{ number_format($gk['churnRate']['current'] ?? 0, 1) }}%</div>
        <div class="label">Churn</div>
      </div>
      <div class="kpi-box">
        <div class="value">{{ number_format($total_active_partners ?? 0) }}</div>
        <div class="label">Partenaires Actifs</div>
      </div>
    </div>
  </div>

  @if(!empty($eklektik_stats) && ($eklektik_stats['revenue_ttc'] ?? 0) > 0)
  <div class="section">
    <div class="section-title">Performance Financiere</div>
    @php
      $revDelta = ($eklektik_comp['revenue_ttc'] ?? 0) > 0 ? round((($eklektik_stats['revenue_ttc'] - $eklektik_comp['revenue_ttc']) / $eklektik_comp['revenue_ttc']) * 100, 1) : 0;
    @endphp
    <div class="kpi-grid">
      <div class="kpi-box">
        <div class="value">{{ number_format($eklektik_stats['revenue_ttc'], 2) }}</div>
        <div class="label">Revenu TTC (TND)</div>
        <div class="delta {{ $revDelta >= 0 ? 'up' : 'down' }}">{{ $revDelta >= 0 ? '+' : '' }}{{ $revDelta }}% vs sem. prec.</div>
      </div>
      <div class="kpi-box">
        <div class="value">{{ number_format($eklektik_stats['revenue_ht'], 2) }}</div>
        <div class="label">Revenu HT (TND)</div>
      </div>
      <div class="kpi-box">
        <div class="value">{{ number_format($eklektik_stats['ca_bigdeal'], 2) }}</div>
        <div class="label">CA BigDeal (TND)</div>
      </div>
    </div>
  </div>
  @endif

  @if(!empty($top_categories))
  <div class="section">
    <div class="section-title">Reseau Partenaire - Top Categories</div>
    <table>
      <tr><th>Categorie</th><th>Transactions</th><th>Partenaires</th></tr>
      @foreach($top_categories as $cat)
      <tr>
        <td style="font-weight:600;">{{ $cat->category }}</td>
        <td><strong>{{ number_format($cat->transactions) }}</strong></td>
        <td>{{ $cat->partners }}</td>
      </tr>
      @endforeach
    </table>
  </div>
  @endif

  @if(!empty($ml) && ($ml['available'] ?? false))
  <div class="section">
    <div class="ml-section">
      <h3>Predictions ML - Intelligence Artificielle</h3>
      <div class="ml-grid">
        <div class="ml-box"><div class="v">{{ number_format($ml['total_clients']) }}</div><div class="l">Clients Analyses</div></div>
        <div class="ml-box"><div class="v" style="color:#ef4444;">{{ number_format($ml['high_churn_clients']) }}</div><div class="l">Risque Churn Eleve</div></div>
        <div class="ml-box"><div class="v" style="color:#10b981;">{{ number_format($ml['high_value_clients']) }}</div><div class="l">Haute Valeur</div></div>
        <div class="ml-box"><div class="v">{{ $ml['avg_success_rate'] }}%</div><div class="l">Taux Succes Moyen</div></div>
      </div>
      @if(!empty($ml['segments']))
      <div style="margin-top:16px;">
        <div style="font-size:12px; color:rgba(255,255,255,0.5); margin-bottom:8px;">SEGMENTATION CLIENTS</div>
        @foreach($ml['segments'] as $seg)
        <div style="display:flex; align-items:center; gap:8px; margin-bottom:6px; font-size:12px;">
          <span style="width:120px; color:rgba(255,255,255,0.8);">{{ ucfirst(str_replace('_', ' ', $seg['segment'])) }}</span>
          <div style="flex:1; background:rgba(255,255,255,0.1); border-radius:4px; height:8px; overflow:hidden;">
            <div style="height:100%; width:{{ min(100, ($seg['count'] / max(1, $ml['total_clients'])) * 100) }}%; background:{{ $seg['avg_churn'] > 30 ? '#ef4444' : ($seg['avg_churn'] > 10 ? '#f59e0b' : '#10b981') }}; border-radius:4px;"></div>
          </div>
          <span style="color:rgba(255,255,255,0.6); width:60px; text-align:right;">{{ number_format($seg['count']) }}</span>
        </div>
        @endforeach
      </div>
      @endif
    </div>
  </div>
  @endif

  @if(!empty($ai_suggestions))
  <div class="section">
    <div class="ai-section">
      <h3>Recommandations IA Strategiques</h3>
      <div class="content">{!! nl2br(e($ai_suggestions)) !!}</div>
    </div>
  </div>
  @endif

  <div class="footer">
    Club Privileges &bull; Rapport genere le {{ $generated_at }} &bull; Confidentiel - Usage Interne Associe
  </div>
</div>
</body></html>
