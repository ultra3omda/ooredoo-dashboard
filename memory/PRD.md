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

### Optimisations Performance - Phase 3 (26 Mars 2026) - SESSION ACTUELLE
- **Securisation SQL (PDO bindings)**: Toutes les requetes KPIs, transactions, marchands utilisent maintenant des bindings parametres au lieu de DB::raw avec interpolation
- **Optimisation getMerchantsOptimized**: JOIN conditionnel cpm pour ALL (-50% temps)
- **Optimisation getSubscriptionDetails**: Suppression sous-requete correlee sur transactions_history (qui causait un timeout systematique de 30s). Resultats: passe de 0 a 140 resultats retournes
- **Optimisation calculateCohorts, calculateRenewalRate, calculateAverageLifespan, calculateReactivationRate**: JOIN conditionnel cpm pour ALL
- **Cron job warmup cache**: Toutes les 25 minutes via supervisor + Laravel scheduler
- **Fix SQL injection**: DB::raw("...INTERVAL $windowDays DAY") -> whereRaw avec binding

### Resultats Performance FINAL
| Metrique | Phase 1 (avant) | Phase 3 (apres) | Amelioration |
|---------|-----------------|-----------------|-------------|
| Cold cache (14j ALL) | 165s+ (souvent timeout) | 23s | **-86%** |
| Cache HIT | ~500ms | ~427ms | -15% |
| KPIs | ~31s | ~15s | -50% |
| retentionTrend | Bloquait indefiniment | ~1s | 100% fix |
| quarterlyActiveLocations | ~60s+ (16 requetes) | ~1s (2 requetes) | -98% |
| getSubscriptionDetails | Timeout 30s (0 resultats) | ~5s (140 resultats) | 100% fix |
| Status | 500 ERROR | 200 OK | Fix complet |

## Utilisateurs
- Super Administrateur: superadmin@ooredoo.tn / Soufiane@2025 (reset)
- Administrateurs operateurs
- Utilisateurs dashboard

## Backlog
### P2
- Index MySQL sur client_abonnement_creation, client_abonnement_expiration
- Vues materialisees pour les calculs KPI frequents

### P3
- Chargement progressif du dashboard (chunks AJAX)
- Notifications de performance en temps reel

## Fichiers cles
- `app/Services/DashboardService.php` - Service principal (~3300 lignes)
- `app/Http/Middleware/DashboardPerformanceMiddleware.php` - Middleware timeout/monitoring
- `app/Console/Commands/WarmupDashboardCache.php` - Pre-remplissage cache
- `app/Console/Kernel.php` - Scheduler cron job (*/25 * * * *)
- `app/Http/Controllers/Api/EklektikDashboardController.php` - Endpoints Eklektik rapides
- `/etc/nginx/sites-available/laravel` - Configuration Nginx
- `backend/server.py` - Proxy FastAPI (port 8001 -> 8002)
- `frontend/server.js` - Proxy Express (port 3000 -> 8002)
