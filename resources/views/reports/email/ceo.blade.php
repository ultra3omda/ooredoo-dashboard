<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><style>
body { font-family: 'Segoe UI', Arial, sans-serif; background: #f4f0fa; margin: 0; padding: 20px; color: #2d2d2d; }
.container { max-width: 680px; margin: 0 auto; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 24px rgba(108,75,160,0.1); }
.header { background: linear-gradient(135deg, #4c3489, #6C4BA0); padding: 32px 40px; color: #fff; }
.header h1 { margin: 0 0 4px; font-size: 22px; font-weight: 700; }
.header .period { color: #D4A843; font-size: 14px; font-weight: 600; }
.section { padding: 28px 40px; border-bottom: 1px solid #ede8f5; }
.section:last-child { border-bottom: none; }
.section-title { font-size: 16px; font-weight: 700; color: #4c3489; margin: 0 0 16px; text-transform: uppercase; letter-spacing: 0.5px; }
.kpi-grid { display: flex; flex-wrap: wrap; gap: 12px; }
.kpi-box { flex: 1 1 140px; background: #f8f5ff; border-radius: 8px; padding: 16px; text-align: center; border-left: 3px solid #6C4BA0; }
.kpi-box .value { font-size: 24px; font-weight: 800; color: #4c3489; }
.kpi-box .label { font-size: 11px; color: #71717a; text-transform: uppercase; margin-top: 4px; }
.kpi-box .delta { font-size: 12px; margin-top: 4px; font-weight: 600; }
.up { color: #10b981; }
.down { color: #ef4444; }
table { width: 100%; border-collapse: collapse; font-size: 13px; }
table th { background: #f8f5ff; color: #4c3489; text-align: left; padding: 10px 12px; font-weight: 600; border-bottom: 2px solid #ede8f5; }
table td { padding: 10px 12px; border-bottom: 1px solid #f0ecf7; }
table tr:nth-child(even) { background: #fdfbff; }
.ai-section { background: linear-gradient(135deg, #fef9ef, #fff8e8); border-left: 4px solid #D4A843; border-radius: 8px; padding: 20px; margin: 16px 0; }
.ai-section h3 { color: #D4A843; margin: 0 0 12px; font-size: 15px; }
.ai-section .content { font-size: 13px; line-height: 1.7; color: #44403c; white-space: pre-line; }
.footer { background: #f8f5ff; text-align: center; padding: 20px 40px; font-size: 11px; color: #a1a1aa; }
</style></head>
<body>
<div class="container">
  <div class="header">
    <h1>Rapport Hebdomadaire CEO</h1>
    <div class="period">{{ $period_start }} - {{ $period_end }}</div>
  </div>

  <div class="section">
    <div class="section-title">Vue Globale - Tous Operateurs</div>
    <div class="kpi-grid">
      @php $gk = $global_kpis ?? []; @endphp
      <div class="kpi-box">
        <div class="value">{{ number_format($gk['activatedSubscriptions']['current'] ?? 0) }}</div>
        <div class="label">Activations</div>
        @if(isset($gk['activatedSubscriptions']['change']))
        <div class="delta {{ ($gk['activatedSubscriptions']['change'] ?? 0) >= 0 ? 'up' : 'down' }}">{{ ($gk['activatedSubscriptions']['change'] ?? 0) >= 0 ? '+' : '' }}{{ number_format($gk['activatedSubscriptions']['change'] ?? 0, 1) }}%</div>
        @endif
      </div>
      <div class="kpi-box">
        <div class="value">{{ number_format($gk['activeSubscriptions']['current'] ?? 0) }}</div>
        <div class="label">Actifs (Cohorte)</div>
        @if(isset($gk['activeSubscriptions']['change']))
        <div class="delta {{ ($gk['activeSubscriptions']['change'] ?? 0) >= 0 ? 'up' : 'down' }}">{{ ($gk['activeSubscriptions']['change'] ?? 0) >= 0 ? '+' : '' }}{{ number_format($gk['activeSubscriptions']['change'] ?? 0, 1) }}%</div>
        @endif
      </div>
      <div class="kpi-box">
        <div class="value">{{ number_format($gk['retentionRate']['current'] ?? 0, 1) }}%</div>
        <div class="label">Retention</div>
        @if(isset($gk['retentionRate']['change']))
        <div class="delta {{ ($gk['retentionRate']['change'] ?? 0) >= 0 ? 'up' : 'down' }}">{{ ($gk['retentionRate']['change'] ?? 0) >= 0 ? '+' : '' }}{{ number_format($gk['retentionRate']['change'] ?? 0, 1) }}pts</div>
        @endif
      </div>
      <div class="kpi-box">
        <div class="value">{{ number_format($gk['conversionRate']['current'] ?? 0, 1) }}%</div>
        <div class="label">Conversion</div>
      </div>
      <div class="kpi-box">
        <div class="value">{{ number_format($gk['totalTransactions']['current'] ?? 0) }}</div>
        <div class="label">Transactions</div>
        @if(isset($gk['totalTransactions']['change']))
        <div class="delta {{ ($gk['totalTransactions']['change'] ?? 0) >= 0 ? 'up' : 'down' }}">{{ ($gk['totalTransactions']['change'] ?? 0) >= 0 ? '+' : '' }}{{ number_format($gk['totalTransactions']['change'] ?? 0, 1) }}%</div>
        @endif
      </div>
      <div class="kpi-box">
        <div class="value">{{ number_format($gk['churnRate']['current'] ?? 0, 1) }}%</div>
        <div class="label">Churn</div>
      </div>
    </div>
  </div>

  @if(!empty($eklektik_stats) && ($eklektik_stats['revenue_ttc'] ?? 0) > 0)
  <div class="section">
    <div class="section-title">Eklektik</div>
    <div class="kpi-grid">
      <div class="kpi-box"><div class="value">{{ number_format($eklektik_stats['revenue_ttc'], 2) }}</div><div class="label">Revenu TTC (TND)</div></div>
      <div class="kpi-box"><div class="value">{{ number_format($eklektik_stats['revenue_ht'], 2) }}</div><div class="label">Revenu HT (TND)</div></div>
      <div class="kpi-box"><div class="value">{{ number_format($eklektik_stats['ca_bigdeal'], 2) }}</div><div class="label">CA BigDeal (TND)</div></div>
      <div class="kpi-box"><div class="value">{{ number_format($eklektik_stats['active_subs']) }}</div><div class="label">Abonnes Actifs</div></div>
    </div>
  </div>
  @endif

  @if(!empty($top_merchants))
  <div class="section">
    <div class="section-title">Top 10 Marchands</div>
    <table>
      <tr><th>#</th><th>Marchand</th><th>Transactions</th></tr>
      @foreach($top_merchants as $i => $m)
      <tr><td>{{ $i + 1 }}</td><td>{{ $m->name }}</td><td><strong>{{ number_format($m->transactions) }}</strong></td></tr>
      @endforeach
    </table>
  </div>
  @endif

  @if(!empty($ml) && ($ml['available'] ?? false))
  <div class="section">
    <div style="background: linear-gradient(135deg, #0f172a, #1e293b); border-radius: 10px; padding: 24px; color: #fff;">
      <h3 style="color: #60a5fa; margin: 0 0 16px; font-size: 16px; font-weight: 700;">Predictions ML - Analyse Predictive</h3>
      <div style="display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 16px;">
        <div style="flex: 1 1 120px; background: rgba(255,255,255,0.06); border-radius: 8px; padding: 14px; text-align: center; border: 1px solid rgba(255,255,255,0.1);">
          <div style="font-size: 22px; font-weight: 800; color: #60a5fa;">{{ number_format($ml['total_clients']) }}</div>
          <div style="font-size: 10px; color: rgba(255,255,255,0.5); text-transform: uppercase; margin-top: 4px;">Clients Analyses</div>
        </div>
        <div style="flex: 1 1 120px; background: rgba(255,255,255,0.06); border-radius: 8px; padding: 14px; text-align: center; border: 1px solid rgba(239,68,68,0.3);">
          <div style="font-size: 22px; font-weight: 800; color: #ef4444;">{{ number_format($ml['high_churn_clients']) }}</div>
          <div style="font-size: 10px; color: rgba(255,255,255,0.5); text-transform: uppercase; margin-top: 4px;">Risque Churn</div>
        </div>
        <div style="flex: 1 1 120px; background: rgba(255,255,255,0.06); border-radius: 8px; padding: 14px; text-align: center; border: 1px solid rgba(16,185,129,0.3);">
          <div style="font-size: 22px; font-weight: 800; color: #10b981;">{{ number_format($ml['high_value_clients']) }}</div>
          <div style="font-size: 10px; color: rgba(255,255,255,0.5); text-transform: uppercase; margin-top: 4px;">Haute Valeur</div>
        </div>
        <div style="flex: 1 1 120px; background: rgba(255,255,255,0.06); border-radius: 8px; padding: 14px; text-align: center; border: 1px solid rgba(255,255,255,0.1);">
          <div style="font-size: 22px; font-weight: 800; color: #60a5fa;">{{ $ml['avg_success_rate'] }}%</div>
          <div style="font-size: 10px; color: rgba(255,255,255,0.5); text-transform: uppercase; margin-top: 4px;">Taux Succes</div>
        </div>
      </div>
      @if(!empty($ml['segments']))
      <div style="font-size: 12px; color: rgba(255,255,255,0.5); margin-bottom: 8px; font-weight: 600;">SEGMENTATION PREDICTIVE</div>
      @foreach($ml['segments'] as $seg)
      <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 5px; font-size: 12px;">
        <span style="width: 110px; color: rgba(255,255,255,0.8);">{{ ucfirst(str_replace('_', ' ', $seg['segment'])) }}</span>
        <div style="flex: 1; background: rgba(255,255,255,0.1); border-radius: 4px; height: 8px; overflow: hidden;">
          <div style="height: 100%; width: {{ min(100, ($seg['count'] / max(1, $ml['total_clients'])) * 100) }}%; background: {{ $seg['avg_churn'] > 30 ? '#ef4444' : ($seg['avg_churn'] > 10 ? '#f59e0b' : '#10b981') }}; border-radius: 4px;"></div>
        </div>
        <span style="color: rgba(255,255,255,0.6); width: 55px; text-align: right;">{{ number_format($seg['count']) }}</span>
        <span style="color: {{ $seg['avg_churn'] > 30 ? '#ef4444' : '#71717a' }}; width: 55px; text-align: right; font-size: 11px;">{{ $seg['avg_churn'] }}% churn</span>
      </div>
      @endforeach
      @endif
      @if(!empty($ml['model']))
      <div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid rgba(255,255,255,0.1); font-size: 11px; color: rgba(255,255,255,0.4);">
        Modele: {{ $ml['model']['model_type'] ?? 'LightGBM' }} | Precision: {{ $ml['model']['accuracy'] ?? 'N/A' }} | Derniere MAJ: {{ $ml['date'] }}
      </div>
      @endif
    </div>
  </div>
  @endif

  @if(!empty($ai_suggestions))
  <div class="section">
    <div class="ai-section">
      <h3>Recommandations IA</h3>
      <div class="content">{!! nl2br(e($ai_suggestions)) !!}</div>
    </div>
  </div>
  @endif

  @if(!empty($merchant_reco))
  <div class="section">
    <div class="section-title">Recommandations Marchands - Moteur ML</div>
    <div class="kpi-grid" style="margin-bottom: 16px;">
      <div class="kpi-box">
        <div class="value">{{ number_format($merchant_reco['active_merchants'] ?? 0) }}</div>
        <div class="label">Marchands Actifs</div>
      </div>
      <div class="kpi-box">
        <div class="value">{{ number_format($merchant_reco['profiled_users'] ?? 0) }}</div>
        <div class="label">Profils ML</div>
      </div>
      <div class="kpi-box">
        <div class="value">{{ number_format($merchant_reco['user_merchant_pairs'] ?? 0) }}</div>
        <div class="label">Paires User-Marchand</div>
      </div>
    </div>
    @if(!empty($merchant_reco['top_merchants']))
    <table>
      <thead>
        <tr><th>#</th><th>Marchand</th><th>Catégorie</th><th>Visites</th><th>Visiteurs</th><th>Score</th></tr>
      </thead>
      <tbody>
        @foreach(array_slice($merchant_reco['top_merchants'], 0, 5) as $i => $m)
        <tr>
          <td>{{ $i + 1 }}</td>
          <td style="font-weight: 600;">{{ $m['partner_name'] ?? 'N/A' }}</td>
          <td>{{ $m['category_name'] ?? '-' }}</td>
          <td>{{ number_format($m['total_visits'] ?? 0) }}</td>
          <td>{{ number_format($m['unique_visitors'] ?? 0) }}</td>
          <td style="color: #4c3489; font-weight: 700;">{{ number_format($m['popularity_score'] ?? 0, 1) }}</td>
        </tr>
        @endforeach
      </tbody>
    </table>
    @endif
    @if(!empty($merchant_reco['top_categories']))
    <div style="margin-top: 16px;">
      <div style="font-weight: 600; font-size: 13px; margin-bottom: 8px; color: #4c3489;">Tendances Catégories</div>
      @foreach($merchant_reco['top_categories'] as $cat)
      <div style="display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #f0ecf7; font-size: 12px;">
        <span>{{ $cat['category_name'] ?? 'Autre' }}</span>
        <span style="font-weight: 600;">{{ number_format($cat['total_visits'] ?? 0) }} visites ({{ $cat['merchant_count'] ?? 0 }} marchands)</span>
      </div>
      @endforeach
    </div>
    @endif
  </div>
  @endif

  <div class="footer">
    Club Privileges &bull; Rapport genere le {{ $generated_at }} &bull; Confidentiel
  </div>
</div>
</body></html>
