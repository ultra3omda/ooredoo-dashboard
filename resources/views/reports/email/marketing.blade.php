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
.delta { font-size: 12px; margin-top: 4px; font-weight: 600; }
.up { color: #10b981; }
.down { color: #ef4444; }
table { width: 100%; border-collapse: collapse; font-size: 13px; }
table th { background: #f8f5ff; color: #4c3489; text-align: left; padding: 10px 12px; font-weight: 600; border-bottom: 2px solid #ede8f5; }
table td { padding: 10px 12px; border-bottom: 1px solid #f0ecf7; }
.ai-section { background: linear-gradient(135deg, #fef9ef, #fff8e8); border-left: 4px solid #D4A843; border-radius: 8px; padding: 20px; margin: 16px 0; }
.ai-section h3 { color: #D4A843; margin: 0 0 12px; font-size: 15px; }
.ai-section .content { font-size: 13px; line-height: 1.7; color: #44403c; white-space: pre-line; }
.footer { background: #f8f5ff; text-align: center; padding: 20px 40px; font-size: 11px; color: #a1a1aa; }
</style></head>
<body>
<div class="container">
  <div class="header">
    <h1>Rapport Marketing Hebdomadaire</h1>
    <div class="period">{{ $period_start }} - {{ $period_end }}</div>
  </div>

  <div class="section">
    <div class="section-title">KPIs Acquisition & Retention</div>
    @php $k = $kpis ?? []; @endphp
    <div class="kpi-grid">
      <div class="kpi-box">
        <div class="value">{{ number_format($k['activatedSubscriptions']['current'] ?? 0) }}</div>
        <div class="label">Nouveaux Abonnes</div>
        @if(isset($k['activatedSubscriptions']['change']))
        <div class="delta {{ ($k['activatedSubscriptions']['change'] ?? 0) >= 0 ? 'up' : 'down' }}">{{ ($k['activatedSubscriptions']['change'] ?? 0) >= 0 ? '+' : '' }}{{ number_format($k['activatedSubscriptions']['change'] ?? 0, 1) }}%</div>
        @endif
      </div>
      <div class="kpi-box">
        <div class="value">{{ number_format($k['activeSubscriptions']['current'] ?? 0) }}</div>
        <div class="label">Cohorte Active</div>
      </div>
      <div class="kpi-box">
        <div class="value">{{ number_format($k['retentionRate']['current'] ?? 0, 1) }}%</div>
        <div class="label">Retention</div>
      </div>
      <div class="kpi-box">
        <div class="value">{{ number_format($k['periodDeactivated']['current'] ?? $k['deactivatedSubscriptions']['current'] ?? 0) }}</div>
        <div class="label">Desactivations</div>
      </div>
      <div class="kpi-box">
        <div class="value">{{ number_format($k['churnRate']['current'] ?? 0, 1) }}%</div>
        <div class="label">Taux Churn</div>
      </div>
      <div class="kpi-box">
        <div class="value">{{ number_format($k['conversionRate']['current'] ?? 0, 1) }}%</div>
        <div class="label">Conversion</div>
      </div>
    </div>
  </div>

  @if(!empty($daily_subs))
  <div class="section">
    <div class="section-title">Evolution Quotidienne</div>
    <table>
      <tr><th>Date</th><th>Activations</th><th>Desactivations</th><th>Actifs Fin</th></tr>
      @foreach($daily_subs as $day)
      <tr>
        <td>{{ \Carbon\Carbon::parse($day->stat_date)->format('d/m') }}</td>
        <td style="color:#10b981;font-weight:600">+{{ number_format($day->activated_count) }}</td>
        <td style="color:#ef4444;font-weight:600">-{{ number_format($day->deactivated_count) }}</td>
        <td>{{ number_format($day->active_snapshot ?? 0) }}</td>
      </tr>
      @endforeach
    </table>
  </div>
  @endif

  @if(!empty($operator_breakdown))
  <div class="section">
    <div class="section-title">Repartition par Operateur</div>
    <table>
      <tr><th>Operateur</th><th>Activations</th><th>Desactivations</th><th>Net</th></tr>
      @foreach($operator_breakdown as $op)
      <tr>
        <td>{{ $op->operator_id }}</td>
        <td style="color:#10b981;font-weight:600">+{{ number_format($op->activated) }}</td>
        <td style="color:#ef4444;font-weight:600">-{{ number_format($op->deactivated) }}</td>
        @php $net = $op->activated - $op->deactivated; @endphp
        <td style="font-weight:700;color:{{ $net >= 0 ? '#10b981' : '#ef4444' }}">{{ $net >= 0 ? '+' : '' }}{{ number_format($net) }}</td>
      </tr>
      @endforeach
    </table>
  </div>
  @endif

  @if(!empty($channel_acquisition))
  <div class="section">
    <div class="section-title">Canaux d'Acquisition</div>
    <table>
      <tr><th>Canal</th><th>Inscriptions</th></tr>
      @foreach($channel_acquisition as $ch)
      <tr><td>{{ ucfirst($ch->channel) }}</td><td><strong>{{ number_format($ch->count) }}</strong></td></tr>
      @endforeach
    </table>
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

  <div class="footer">
    Club Privileges &bull; Rapport genere le {{ $generated_at }} &bull; Confidentiel
  </div>
</div>
</body></html>
