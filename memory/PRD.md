# PRD - Dashboard Club Privileges / Ooredoo

## Probleme Original
Deployer l'application Ooredoo Privileges (Club Privileges Dashboard) et effectuer des analyses fonctionnelles et des ameliorations de temps de reponse et requetes.

## Architecture Technique
- **Framework**: Laravel 10 (PHP 8.2)
- **Base de donnees**: MySQL distante (51.38.187.245:3306) - MySQL 8.0.45
- **Cache**: Redis distant (51.38.187.245:7905) 
- **Frontend**: Blade templates + Chart.js (SSR)
- **Auth**: Session-based + OTP
- **Deploiement**: FastAPI proxy (8001) + Node.js proxy (3000) -> Nginx + PHP-FPM (8002)

## Ce qui a ete implemente

### Deploiement (26 Mars 2026)
- Installation PHP 8.2, Composer, PHP-FPM, Nginx
- Configuration des proxys (FastAPI + Node.js) pour integrer Laravel dans l'infrastructure Emergent
- Configuration PHP-FPM (10 workers, 300s request_terminate_timeout, 512MB memory)
- Configuration Nginx avec timeouts etendus (300s)
- Configuration .env complete

### Optimisations Performance - Phase 1 (26 Mars 2026)
- Cache Redis active (CACHE_DRIVER: file -> redis)
- Trusted Proxies configures
- Commande de warmup cache (`php artisan dashboard:warmup`)
- LOG_LEVEL reduit a warning en production

### Optimisations Performance - Phase 2 (26 Mars 2026)
- Fix 500 Error: API bloquait indefiniment sur calculateRetentionTrendOptimized
- Suppression JOINs inutiles pour operateur ALL (via applyOperatorJoinAndFilter)
- Timeout MySQL 30s par requete (SET SESSION max_execution_time=30000)
- Systeme de budget de temps (90s max) pour calculs secondaires
- Optimisation calculateQuarterlyActiveLocations: 16 requetes -> 2
- Fix Express proxy timeout: 120s -> 300s

### Optimisations Performance - Phase 3 (26 Mars 2026)
- **Securisation SQL (PDO bindings)**: Toutes les requetes KPIs, transactions, marchands utilisent des bindings parametres
- **Optimisation getMerchantsOptimized**: JOIN conditionnel cpm pour ALL (-50% temps)
- **Optimisation getSubscriptionDetails**: Suppression sous-requete correlee sur transactions_history
- **Optimisation calculateCohorts, calculateRenewalRate, calculateAverageLifespan, calculateReactivationRate**: JOIN conditionnel cpm pour ALL
- **Cron job warmup cache**: Toutes les 25 minutes via supervisor + Laravel scheduler
- **Fix SQL injection**: DB::raw("...INTERVAL $windowDays DAY") -> whereRaw avec binding
- **Chargement progressif (split API)**: 5 endpoints split (kpis, merchants, transactions, subscriptions, ooredoo) charges en parallele
- **Fix auth split endpoints**: Routes deplacees de api.php vers web.php avec middleware auth session
- **Tables materialisees (dashboard_daily_stats)**: Pre-calcul KPIs quotidiens pour 90 jours, avec fallback intelligent
- **Cron matérialisation quotidienne**: Scheduler daily a 3h00 pour recalculer les 3 derniers jours
- **Nettoyage routes**: Suppression routes dupliquees dans api.php

### Resultats Performance FINAL
| Metrique | Phase 1 (avant) | Phase 3 (apres) | Amelioration |
|---------|-----------------|-----------------|-------------|
| Cold cache (14j ALL) | 165s+ (souvent timeout) | 16.9s | **-90%** |
| Warm cache | ~500ms | ~2-3s (progressif) | Perception amelioree |
| KPIs | ~31s | ~1s (materialisees) | -97% |
| retentionTrend | Bloquait indefiniment | ~1s | 100% fix |
| quarterlyActiveLocations | ~60s+ (16 requetes) | ~1s (2 requetes) | -98% |
| getSubscriptionDetails | Timeout 30s (0 resultats) | ~5s (140 resultats) | 100% fix |
| Status | 500 ERROR | 200 OK | Fix complet |

## Utilisateurs
- Super Administrateur: superadmin@ooredoo.tn / Soufiane@2025 (reset)
- Administrateurs operateurs
- Utilisateurs dashboard

## Backlog

### P3
- Notifications de performance en temps reel
- Materialiser les autres sections (merchants, subscriptions, transactions) pour reduire cold cache < 5s

## Fichiers cles
- `app/Services/DashboardService.php` - Service principal (~3600 lignes) avec tables materialisees
- `app/Http/Controllers/Api/DataControllerOptimized.php` - Endpoints split + monolithique
- `app/Http/Middleware/DashboardPerformanceMiddleware.php` - Middleware timeout/monitoring
- `app/Console/Commands/WarmupDashboardCache.php` - Pre-remplissage cache
- `app/Console/Commands/MaterializeDailyStats.php` - Materialisation KPIs quotidiens
- `app/Console/Kernel.php` - Scheduler (warmup */25, materialize daily 3:00)
- `routes/web.php` - Routes dashboard authentifiees (split + monolithique)
- `/etc/nginx/sites-available/laravel` - Configuration Nginx
- `backend/server.py` - Proxy FastAPI (port 8001 -> 8002)
- `frontend/server.js` - Proxy Express (port 3000 -> 8002)
