# Club Privilèges - Performance Dashboard

## Problème Original
Dashboard haute performance Laravel pour suivi des abonnements, transactions et KPIs opérateurs (Timwe, Ooredoo/DGV). Objectifs : temps de réponse sub-seconde, statistiques mathématiquement exactes, monitoring temps réel, architecture découplée.

## Stack Technique
- Laravel 10, PHP 8.2, Nginx, PHP-FPM
- MySQL (vues matérialisées), Redis (cache ultra-rapide)
- Chart.js, Vanilla JavaScript
- FastAPI proxy (port 8001 → Nginx port 8002)

## Architecture
- `app/Services/Dashboard/` : Services domaine (KPIService, MerchantService, StatisticsService, SubscriptionService, TransactionService)
- `app/Services/DashboardService.php` : Façade légère (~170 lignes)
- `app/Http/Controllers/Api/DataControllerOptimized.php` : Endpoints split avec cache Redis
- `resources/views/dashboard.blade.php` : Frontend principal (10 000+ lignes)
- `resources/views/monitoring/dashboard.blade.php` : Dashboard monitoring

## Endpoints API Principaux
- `GET /api/dashboard/split/kpis` - KPIs globaux (source: client_abonnement)
- `GET /api/dashboard/split/subscriptions` - Détails abonnements
- `GET /api/dashboard/split/timwe` - Stats opérateur Timwe (source: timwe_daily_stats)
- `GET /api/dashboard/split/ooredoo` - Stats opérateur Ooredoo (source: ooredoo_daily_stats)
- `GET /api/operators` - Liste opérateurs
- `GET /api/monitoring/health` - Santé système

## Modèle de Données Clé
- `client_abonnement` : Table principale abonnements (source de vérité)
- `timwe_daily_stats` : Stats journalières Timwe (sync quotidienne depuis client_abonnement)
- `ooredoo_daily_stats` : Stats journalières Ooredoo/DGV (agrégateur distinct)
- `subscription_daily_stats` / `transaction_daily_stats` : Vues matérialisées

## Cohérence des Données (Analyse Complète - 28/03/2026)
### Activated Subscriptions (Nouveaux Abonnements)
- **Overview** (client_abonnement) vs **Timwe tab** (timwe_daily_stats) : **100% cohérent**
- Testé jour par jour sur Mars 2026, Fév 2026, Juin/Sept/Déc 2025 : **DIFF = 0**
- Seul écart : jour en cours si sync pas encore faite (~225 subs)

### Active Subscriptions
- **Overview** : Cohorte période (activés PENDANT la période et encore actifs)
- **Timwe tab** : Base totale (tous abonnés actifs au dernier jour, quelle que soit leur date d'activation)
- **En lifetime** : Les 2 convergent (26 063 = 26 063 au 27/03/2026)
- **En courte période** : Diffèrent par nature (7 265 cohorte vs 24 801 base totale)
- **Décision utilisateur** : Garder la logique originale (option A) - les 2 métriques sont justes

### Ooredoo/DGV
- Agrégateur distinct, NON concerné par les modifications Overview/Subscriptions/Timwe

## Ce qui est implémenté ✅
- [x] Services domaine créés (KPIService, MerchantService, StatisticsService, etc.)
- [x] Refactoring DashboardService.php (~4000 → 170 lignes)
- [x] Monitoring temps réel (AlertService, HealthCheckCommand, API routes)
- [x] Fix dropdown opérateurs
- [x] Analyse complète cohérence données inter-onglets
- [x] Restauration logique originale calcul Timwe (logique correcte confirmée)

## Backlog
- P2: Refactoring `dashboard.blade.php` (10 000+ lignes → modules JS séparés)

## Credentials Test
- Email: superadmin@ooredoo.tn
- Password: SuperAdmin@2025
