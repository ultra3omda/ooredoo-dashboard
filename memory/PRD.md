# PRD - Dashboard Club Privilèges (Ooredoo)

## Problème Original
Dashboard de performance pour le programme Club Privilèges d'Ooredoo Tunisie. Application Laravel 10 avec PHP-FPM, Nginx, MySQL distant, Redis. L'objectif est d'optimiser les temps de réponse, assurer l'exactitude des données, et maintenir la sécurité du code.

## Architecture
- **Backend**: Laravel 10 (PHP 8.2-FPM) + Nginx (port 8002)
- **Proxy**: FastAPI (port 8001) auto-start PHP-FPM + Express frontend (port 3000)
- **Base de données**: MySQL distant (51.38.187.245)
- **Cache**: Redis (local)
- **Frontend**: Vanilla JS dans Blade templates (dashboard.blade.php)

## Fonctionnalités Implémentées (Complet)

### Phase 1: Déploiement et Analyse (DONE)
- Déploiement initial avec auto-start PHP-FPM
- Configuration Nginx/FastAPI proxy
- Analyse fonctionnelle complète

### Phase 2: Optimisation Progressive (DONE)
- API split en 5 endpoints progressifs (kpis, merchants, transactions, subscriptions, timwe)
- Cache Redis avec TTL adaptatif
- Correction bug uppercase/lowercase opérateur (ALL vs all)

### Phase 3: Correctifs Données (DONE)
- Recalcul stats Timwe historiques (19 fév - 2 mars)
- Alignement calcul Timwe: ajout PPIDs 63981, 63982, suppression déduplication téléphone
- Nettoyage secrets GitGuardian (.env.production.example, docs, config)

### Phase 4: Fonctionnalités Avancées (DONE)
- Agent IA (Gemini 2.5 Flash) avec quota 250/jour
- Widget monitoring AI quota
- Endpoint dédié /api/dashboard/split/timwe
- Fix formatNumber JS (décimales/pourcentages)

### Phase 5: Benchmarks et Limites (DONE)
- Levée restriction 365 jours
- Benchmarks 1M, 6M, 12M, Lifetime
- Rapport RAPPORT_BENCHMARK_PERIODES.md

### Phase 6: Matérialisation Subscriptions (DONE - 27 Mar 2026)
- Table `subscription_daily_stats` (1822 lignes, 2021-04-01 → 2026-03-27)
- Commande batch `dashboard:materialize-subscriptions`
- Réécriture `getSubscriptionsData` avec chemin matérialisé + fallback live
- Optimisation cohortes: batch SQL unique (18 requêtes → 1)
- Optimisation rétention: matérialisée avec échantillonnage
- Cron quotidien (3h15) et hebdomadaire (dim 5h00)

**Résultats Performance Cold Cache:**
| Période | Avant (ms) | Après (ms) | Réduction |
|---------|-----------|-----------|-----------|
| 1 mois  | 19 551    | 7 032     | -64%      |
| 6 mois  | 26 069    | 7 009     | -73%      |
| 12 mois | 27 086    | 6 482     | -76%      |
| Lifetime| 19 982    | 6 377     | -68%      |

## Endpoints API Principaux
- `GET /api/dashboard/split/kpis`
- `GET /api/dashboard/split/merchants`
- `GET /api/dashboard/split/transactions`
- `GET /api/dashboard/split/subscriptions` (matérialisé)
- `GET /api/dashboard/split/timwe`
- `GET /api/dashboard/split/ooredoo`

## Credentials Test
- Email: `superadmin@ooredoo.tn`
- Password: `SuperAdmin@2025`

## Fichiers Clés
- `app/Services/DashboardService.php` - Service principal (3800+ lignes)
- `app/Http/Controllers/Api/DataControllerOptimized.php` - Contrôleur API split
- `app/Console/Commands/MaterializeSubscriptionStats.php` - Matérialisation subscriptions
- `app/Console/Commands/MaterializeDailyStats.php` - Matérialisation KPIs
- `app/Console/Commands/WarmupDashboardCache.php` - Warmup cache
- `resources/views/dashboard.blade.php` - Frontend UI
- `app/Console/Kernel.php` - Scheduler crons

## Backlog
- P3: Monitoring temps réel (alertes, health checks)
- P4: Refactoring DashboardService.php (>3800 lignes → services spécialisés)
- P4: Matérialisation par opérateur individuel (actuellement ALL seulement)
