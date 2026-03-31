<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><style>
body { font-family: 'Segoe UI', Arial, sans-serif; background: #f4f0fa; margin: 0; padding: 20px; color: #2d2d2d; }
.container { max-width: 680px; margin: 0 auto; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 24px rgba(108,75,160,0.1); }
.header { background: linear-gradient(135deg, #4c3489, #6C4BA0); padding: 32px 40px; color: #fff; }
.header h1 { margin: 0 0 4px; font-size: 22px; font-weight: 700; }
.header .store-name { color: #D4A843; font-size: 18px; font-weight: 700; margin-top: 4px; }
.header .badge { display: inline-block; background: rgba(212,168,67,0.2); color: #D4A843; padding: 2px 10px; border-radius: 12px; font-size: 11px; margin-top: 8px; font-weight: 600; }
.header .period { color: rgba(255,255,255,0.7); font-size: 13px; margin-top: 8px; }
.section { padding: 28px 40px; border-bottom: 1px solid #ede8f5; }
.section:last-child { border-bottom: none; }
.section-title { font-size: 16px; font-weight: 700; color: #4c3489; margin: 0 0 16px; text-transform: uppercase; letter-spacing: 0.5px; }
.kpi-grid { display: flex; flex-wrap: wrap; gap: 12px; }
.kpi-box { flex: 1 1 130px; background: #f8f5ff; border-radius: 8px; padding: 16px; text-align: center; border-left: 3px solid #6C4BA0; }
.kpi-box .value { font-size: 28px; font-weight: 800; color: #4c3489; }
.kpi-box .label { font-size: 11px; color: #71717a; text-transform: uppercase; margin-top: 4px; }
.delta { font-size: 12px; margin-top: 4px; font-weight: 600; }
.up { color: #10b981; } .down { color: #ef4444; }
table { width: 100%; border-collapse: collapse; font-size: 13px; }
table th { background: #f8f5ff; color: #4c3489; text-align: left; padding: 10px 12px; font-weight: 600; border-bottom: 2px solid #ede8f5; }
table td { padding: 10px 12px; border-bottom: 1px solid #f0ecf7; }
.hour-bar { display: inline-block; height: 18px; border-radius: 3px; background: linear-gradient(90deg, #6C4BA0, #D4A843); min-width: 4px; vertical-align: middle; }
.insight-card { background: linear-gradient(135deg, #f0fdf4, #ecfdf5); border-left: 4px solid #10b981; border-radius: 8px; padding: 16px; margin-bottom: 12px; }
.insight-card .title { font-size: 13px; font-weight: 700; color: #065f46; margin: 0 0 4px; }
.insight-card .desc { font-size: 12px; color: #047857; }
.ml-badge { display: inline-block; background: linear-gradient(135deg, #1e293b, #334155); color: #60a5fa; font-size: 10px; padding: 2px 8px; border-radius: 10px; font-weight: 600; }
.ai-section { background: linear-gradient(135deg, #fef9ef, #fff8e8); border-left: 4px solid #D4A843; border-radius: 8px; padding: 20px; margin: 16px 0; }
.ai-section h3 { color: #D4A843; margin: 0 0 12px; font-size: 15px; }
.ai-section .content { font-size: 13px; line-height: 1.7; color: #44403c; white-space: pre-line; }
.rgpd { background: #f8f5ff; padding: 16px 40px; font-size: 10px; color: #a1a1aa; border-top: 1px solid #ede8f5; }
.footer { background: #f8f5ff; text-align: center; padding: 16px 40px; font-size: 11px; color: #a1a1aa; }
</style></head>
<body>
<div class="container">
  <div class="header">
    <h1>Rapport {{ $report_type === 'sub-store' ? 'Sub-Store' : 'Store' }}</h1>
    <div class="store-name">{{ $partner_info->partner_name ?? 'Store' }}</div>
    <div class="badge">{{ $report_type === 'sub-store' ? 'SUB-STORE' : 'STORE' }}</div>
    <div class="period">Periode : {{ $period_start }} - {{ $period_end }}</div>
  </div>

  <div class="section">
    <div class="section-title">Performance de la Semaine</div>
    @php $delta = ($transactions_comp ?? 0) > 0 ? round((($transactions - $transactions_comp) / $transactions_comp) * 100, 1) : 0; @endphp
    <div class="kpi-grid">
      <div class="kpi-box">
        <div class="value">{{ number_format($transactions ?? 0) }}</div>
        <div class="label">Transactions</div>
        <div class="delta {{ $delta >= 0 ? 'up' : 'down' }}">{{ $delta >= 0 ? '+' : '' }}{{ $delta }}% vs sem. prec.</div>
      </div>
      <div class="kpi-box">
        <div class="value">{{ number_format($unique_clients ?? 0) }}</div>
        <div class="label">Clients Uniques</div>
      </div>
      <div class="kpi-box">
        <div class="value">{{ ($unique_clients ?? 0) > 0 ? number_format(($transactions ?? 0) / $unique_clients, 1) : '0' }}</div>
        <div class="label">Visites / Client</div>
      </div>
      <div class="kpi-box">
        <div class="value">{{ number_format($avg_daily_transactions ?? 0, 1) }}</div>
        <div class="label">Moy. Quotidienne</div>
      </div>
    </div>
  </div>

  @if(!empty($hourly_distribution))
  <div class="section">
    <div class="section-title">Affluence par Heure <span class="ml-badge">ML Insight</span></div>
    @php $maxHour = collect($hourly_distribution)->max('count') ?: 1; @endphp
    <table>
      <tr><th>Heure</th><th>Transactions</th><th>Affluence</th></tr>
      @foreach($hourly_distribution as $h)
      <tr style="{{ $h->hour == ($peak_hour ?? -1) ? 'background:#f0fdf4;' : '' }}">
        <td style="font-weight:600;">{{ sprintf('%02d', $h->hour) }}:00{{ $h->hour == ($peak_hour ?? -1) ? ' *' : '' }}</td>
        <td>{{ number_format($h->count) }}</td>
        <td><span class="hour-bar" style="width:{{ max(4, ($h->count / $maxHour) * 200) }}px;"></span></td>
      </tr>
      @endforeach
    </table>
    @if($peak_hour !== null)
    <div class="insight-card" style="margin-top:12px;">
      <div class="title">Heure de pointe identifiee</div>
      <div class="desc">Le creneau {{ sprintf('%02d', $peak_hour) }}:00 concentre le plus de transactions. Recommandation: renforcer le personnel et les offres durant ce creneau.</div>
    </div>
    @endif
  </div>
  @endif

  @if(!empty($daily_transactions))
  <div class="section">
    <div class="section-title">Transactions par Jour</div>
    <table>
      <tr><th>Date</th><th>Transactions</th></tr>
      @foreach($daily_transactions as $day)
      <tr><td>{{ \Carbon\Carbon::parse($day->date)->format('d/m/Y') }}</td><td><strong>{{ number_format($day->count) }}</strong></td></tr>
      @endforeach
    </table>
  </div>
  @endif

  @if(!empty($top_promotions))
  <div class="section">
    <div class="section-title">Top 10 Offres</div>
    <table>
      <tr><th>#</th><th>Offre</th><th>Utilisations</th><th>Part</th></tr>
      @php $totalUses = collect($top_promotions)->sum('uses') ?: 1; @endphp
      @foreach($top_promotions as $i => $promo)
      <tr>
        <td>{{ $i + 1 }}</td>
        <td style="font-weight:500;">{{ $promo->title }}</td>
        <td><strong>{{ number_format($promo->uses) }}</strong></td>
        <td>{{ number_format(($promo->uses / $totalUses) * 100, 1) }}%</td>
      </tr>
      @endforeach
    </table>
  </div>
  @endif

  @if(!empty($ml) && ($ml['available'] ?? false))
  <div class="section">
    <div style="background:linear-gradient(135deg,#0f172a,#1e293b); border-radius:10px; padding:20px; color:#fff;">
      <h3 style="color:#60a5fa; margin:0 0 12px; font-size:14px;">Intelligence ML - Vos Clients</h3>
      <div style="font-size:12px; color:rgba(255,255,255,0.7); line-height:1.6;">
        Sur les <strong style="color:#fff;">{{ number_format($ml['total_clients']) }}</strong> clients du programme,
        <strong style="color:#ef4444;">{{ number_format($ml['high_churn_clients']) }}</strong> sont a risque de depart.
        Le taux de succes de paiement moyen est de <strong style="color:#60a5fa;">{{ $ml['avg_success_rate'] }}%</strong>.
        Concentrez vos offres sur les periodes de forte affluence pour maximiser la retention.
      </div>
    </div>
  </div>
  @endif

  @if(!empty($ai_suggestions))
  <div class="section">
    <div class="ai-section">
      <h3>Suggestions IA pour Optimiser Votre Store</h3>
      <div class="content">{!! nl2br(e($ai_suggestions)) !!}</div>
    </div>
  </div>
  @endif

  <div class="rgpd">
    RGPD : Ce rapport contient uniquement les donnees relatives a votre etablissement. Aucune donnee d'autres partenaires n'est incluse.
  </div>
  <div class="footer">
    Club Privileges &bull; Rapport genere le {{ $generated_at }} &bull; Confidentiel
  </div>
</div>
</body></html>
