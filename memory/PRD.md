# PRD - Dashboard Club Privilèges Ooredoo

## Description
Dashboard haute performance pour la gestion des abonnements, transactions et marchands de Club Privilèges Ooredoo Tunisie. API sub-seconde via Redis, vues matérialisées MySQL, Chart.js frontend.

## Architecture Technique
- **Backend**: Laravel 10, PHP 8.2, Nginx/PHP-FPM
- **BDD**: MySQL (tables matérialisées: subscription_daily_stats, transaction_daily_stats, dashboard_daily_stats)
- **Cache**: Redis (JSON brut, warmup cron, TTL adaptatif)
- **Frontend**: Blade templates, Chart.js, Fetch API
- **Proxy**: FastAPI (port 8001) → PHP-FPM/Nginx (port 8002)

## Architecture des Services (Post-P4 Refactoring)
```
DashboardService.php (172 lignes - Facade)
├── Dashboard/KPIService.php (446 lignes)
├── Dashboard/MerchantService.php (273 lignes)
├── Dashboard/TransactionService.php (239 lignes)
├── Dashboard/SubscriptionService.php (636 lignes)
├── Dashboard/StatisticsService.php (423 lignes)
├── Traits/OperatorHelper.php (shared)
└── Traits/TransactionHelper.php (shared)
```

## Fonctionnalités Implémentées

### Phase 1 - Dashboard Core (DONE)
- KPIs: activations, abonnements actifs, taux rétention/conversion/churn
- Graphiques: subscriptions daily, transactions volume, merchants breakdown
- 6 split endpoints cachés: kpis, subscriptions, transactions, merchants, timwe, ooredoo
- Warmup cron: `app:warmup-split-endpoints`
- Smart Comparison: YoY pour périodes > 365 jours
- Cumulative Active POS: courbe trimestrielle

### Phase 2 - Performance (DONE)
- Tables matérialisées
- Redis ultra-fast (5-10ms en cache)
- Split endpoints asynchrones
- Frontend race condition fixes (Chart.js canvas)

### Phase 3 - Monitoring & Alertes (DONE - 28/03/2026)
- **AlertService**: Création/acquittement/purge d'alertes (Redis-backed)
- **HealthCheckCommand**: Artisan cron, 5 composants (DB, Redis, Warmup, Disk, API)
- **Endpoints API**:
  - `GET /api/monitoring/health` - Health check complet
  - `GET /api/monitoring/alerts` - Liste des alertes + stats
  - `POST /api/monitoring/alerts/{id}/acknowledge`
  - `POST /api/monitoring/alerts/acknowledge-all`
  - `DELETE /api/monitoring/alerts`
  - `GET /api/monitoring/warmup-status` - Couverture cache détaillée
- **UI Monitoring**: Dashboard Bootstrap avec auto-refresh 30s, badges sévérité, graphique API latency, health check on-demand

### Phase 4 - Refactoring DashboardService (DONE - 28/03/2026)
- DashboardService réduit de ~4000 → 172 lignes (thin facade)
- 5 services de domaine + 2 traits partagés
- API publique identique (zéro breaking change)
- Tests: 100% backend (18/18), 95% frontend

## Endpoints API Principaux
- `GET /api/dashboard/split/kpis`
- `GET /api/dashboard/split/subscriptions`
- `GET /api/dashboard/split/transactions`
- `GET /api/dashboard/split/merchants`
- `GET /api/dashboard/split/timwe`
- `GET /api/dashboard/split/ooredoo`
- `GET /api/monitoring/health`
- `GET /api/monitoring/alerts`
- `GET /api/monitoring/warmup-status`

## Backlog
- P5: Notifications externes (email/SMS) pour alertes critiques
- P5: Export de données (CSV/PDF)
- P5: Dashboard comparatif multi-opérateurs

## Credentials
- Email: superadmin@ooredoo.tn
- Password: SuperAdmin@2025
