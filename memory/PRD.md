# PRD - Dashboard Club Privilèges (Ooredoo)

## Problème Original
Dashboard de performance pour le programme Club Privilèges d'Ooredoo Tunisie. Application Laravel 10 avec PHP-FPM, Nginx, MySQL distant, Redis. Objectif : optimiser les temps de réponse, assurer l'exactitude des données, et maintenir la sécurité du code.

## Architecture
- **Backend**: Laravel 10 (PHP 8.2-FPM) + Nginx (port 8002)
- **Proxy**: FastAPI (port 8001) auto-start PHP-FPM + Express frontend (port 3000)
- **Base de données**: MySQL distant (51.38.187.245)
- **Cache**: Redis (local, TTL 30 min par endpoint)
- **Frontend**: Vanilla JS dans Blade templates (dashboard.blade.php)

## Tables Matérialisées
| Table | Lignes | Couverture | Usage |
|-------|--------|------------|-------|
| `dashboard_daily_stats` | 2 281 | 365 jours | KPIs all-in-one (périodes courtes) |
| `subscription_daily_stats` | 1 822+ | 5 ans | Abonnements (toutes périodes) |
| `transaction_daily_stats` | 1 822 | 5 ans | Transactions (toutes périodes) |
| `timwe_daily_stats` | ~365 | 1 an | Stats Timwe |
| `ooredoo_daily_stats` | ~365 | 1 an | Stats Ooredoo |

## Fonctionnalités Implémentées

### Phase 1-4: Foundation (DONE)
- Déploiement, analyse, optimisation progressive, API split, correctifs données
- Agent IA (Gemini), monitoring quota, nettoyage secrets

### Phase 5: Benchmarks (DONE)
- Benchmarks 1M, 6M, 12M, Lifetime complets

### Phase 6: Matérialisation Subscriptions (DONE - 27 Mar 2026)
- Table `subscription_daily_stats`, commande batch, chemin matérialisé + fallback
- Résultats: Lifetime 20s → 7.3s (-63%)

### Phase 7: Matérialisation KPIs + Transactions (DONE - 27 Mar 2026)
- Table `transaction_daily_stats`, commande batch
- KPIs: chemin combiné `subscription_daily_stats` + `transaction_daily_stats` pour Lifetime
- Transactions: lecture directe `transaction_daily_stats`
- Résultats: KPIs 13.2s → 6.4s (-52%), Transactions 6.6s → 1.8s (-72%)
- Per-operator subscription materialization (14 opérateurs) en cours de backfill

## Crons Automatiques
- Quotidien 03:00-03:30: matérialisation 7 derniers jours (3 tables)
- Hebdo dim 04:30-05:30: matérialisation complète 365 jours (3 tables)

## Endpoints API
- `GET /api/dashboard/split/kpis` (matérialisé)
- `GET /api/dashboard/split/merchants`
- `GET /api/dashboard/split/transactions` (matérialisé)
- `GET /api/dashboard/split/subscriptions` (matérialisé)
- `GET /api/dashboard/split/timwe` (matérialisé)
- `GET /api/dashboard/split/ooredoo` (matérialisé)

## Credentials Test
- Email: `superadmin@ooredoo.tn` / Password: `SuperAdmin@2025`

## Fichiers Clés
- `app/Services/DashboardService.php` - Service principal (~4000 lignes)
- `app/Http/Controllers/Api/DataControllerOptimized.php` - Contrôleur API split
- `app/Console/Commands/MaterializeSubscriptionStats.php` - Matérialisation subs
- `app/Console/Commands/MaterializeTransactionStats.php` - Matérialisation tx
- `app/Console/Commands/MaterializeDailyStats.php` - Matérialisation KPIs
- `app/Console/Kernel.php` - Scheduler crons
- `resources/views/dashboard.blade.php` - Frontend UI

## Backlog
- P3: Monitoring temps réel (alertes, health checks)
- P4: Refactoring DashboardService.php (>4000 lignes → services spécialisés)
