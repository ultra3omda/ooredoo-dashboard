<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><style>
body { font-family: 'Segoe UI', Arial, sans-serif; background: #f4f0fa; margin: 0; padding: 20px; color: #2d2d2d; }
.container { max-width: 680px; margin: 0 auto; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 24px rgba(108,75,160,0.1); }
.header { background: linear-gradient(135deg, #4c3489, #6C4BA0); padding: 32px 40px; color: #fff; }
.header h1 { margin: 0 0 4px; font-size: 22px; font-weight: 700; }
.header .partner-name { color: #D4A843; font-size: 18px; font-weight: 700; margin-top: 4px; }
.header .period { color: rgba(255,255,255,0.8); font-size: 13px; margin-top: 8px; }
.section { padding: 28px 40px; border-bottom: 1px solid #ede8f5; }
.section:last-child { border-bottom: none; }
.section-title { font-size: 16px; font-weight: 700; color: #4c3489; margin: 0 0 16px; text-transform: uppercase; letter-spacing: 0.5px; }
.kpi-grid { display: flex; flex-wrap: wrap; gap: 12px; }
.kpi-box { flex: 1 1 140px; background: #f8f5ff; border-radius: 8px; padding: 16px; text-align: center; border-left: 3px solid #6C4BA0; }
.kpi-box .value { font-size: 28px; font-weight: 800; color: #4c3489; }
.kpi-box .label { font-size: 11px; color: #71717a; text-transform: uppercase; margin-top: 4px; }
.delta.up { color: #10b981; font-size: 13px; font-weight: 600; }
.delta.down { color: #ef4444; font-size: 13px; font-weight: 600; }
table { width: 100%; border-collapse: collapse; font-size: 13px; }
table th { background: #f8f5ff; color: #4c3489; text-align: left; padding: 10px 12px; font-weight: 600; border-bottom: 2px solid #ede8f5; }
table td { padding: 10px 12px; border-bottom: 1px solid #f0ecf7; }
.ai-section { background: linear-gradient(135deg, #fef9ef, #fff8e8); border-left: 4px solid #D4A843; border-radius: 8px; padding: 20px; margin: 16px 0; }
.ai-section h3 { color: #D4A843; margin: 0 0 12px; font-size: 15px; }
.ai-section .content { font-size: 13px; line-height: 1.7; color: #44403c; white-space: pre-line; }
.rgpd { background: #f8f5ff; padding: 16px 40px; font-size: 10px; color: #a1a1aa; border-top: 1px solid #ede8f5; }
.footer { background: #f8f5ff; text-align: center; padding: 16px 40px; font-size: 11px; color: #a1a1aa; }
</style></head>
<body>
<div class="container">
  <div class="header">
    <h1>Rapport Transactions</h1>
    <div class="partner-name">{{ $partner_info->partner_name ?? 'Partenaire' }}</div>
    <div class="period">Periode : {{ $period_start }} - {{ $period_end }}</div>
  </div>

  <div class="section">
    <div class="section-title">Resume de la Semaine</div>
    <div class="kpi-grid">
      <div class="kpi-box">
        <div class="value">{{ number_format($transactions ?? 0) }}</div>
        <div class="label">Transactions</div>
        @php $delta = ($transactions_comp ?? 0) > 0 ? round((($transactions - $transactions_comp) / $transactions_comp) * 100, 1) : 0; @endphp
        <div class="delta {{ $delta >= 0 ? 'up' : 'down' }}">{{ $delta >= 0 ? '+' : '' }}{{ $delta }}% vs sem. prec.</div>
      </div>
      <div class="kpi-box">
        <div class="value">{{ number_format($unique_clients ?? 0) }}</div>
        <div class="label">Clients Uniques</div>
      </div>
      <div class="kpi-box">
        <div class="value">{{ ($unique_clients ?? 0) > 0 ? number_format(($transactions ?? 0) / $unique_clients, 1) : '0' }}</div>
        <div class="label">Visites/Client</div>
      </div>
    </div>
  </div>

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
    <div class="section-title">Offres les Plus Utilisees</div>
    <table>
      <tr><th>#</th><th>Offre</th><th>Utilisations</th></tr>
      @foreach($top_promotions as $i => $promo)
      <tr><td>{{ $i + 1 }}</td><td>{{ $promo->title }}</td><td><strong>{{ number_format($promo->uses) }}</strong></td></tr>
      @endforeach
    </table>
  </div>
  @endif

  @if(!empty($ai_suggestions))
  <div class="section">
    <div class="ai-section">
      <h3>Suggestions pour Optimiser vos Performances</h3>
      <div class="content">{!! nl2br(e($ai_suggestions)) !!}</div>
    </div>
  </div>
  @endif

  <div class="rgpd">
    RGPD : Ce rapport contient uniquement les donnees relatives a votre etablissement. Aucune donnee d'autres partenaires n'est incluse. Les donnees sont traitees conformement a notre politique de confidentialite.
  </div>
  <div class="footer">
    Club Privileges &bull; Rapport genere le {{ $generated_at }} &bull; Confidentiel
  </div>
</div>
</body></html>
