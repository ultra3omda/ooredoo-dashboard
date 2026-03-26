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

### Optimisations Performance - Phase 2 (26 Mars 2026) - SESSION ACTUELLE
- **Fix 500 Error**: L'API `/api/dashboard/data` bloquait indefiniment sur `calculateRetentionTrendOptimized`. Corrige par:
  - Suppression du JOIN inutile avec `country_payments_methods` pour l'operateur ALL
  - Ajout de `SET SESSION max_execution_time=30000` (timeout MySQL 30s par requete)
  - Ajout d'un systeme de "budget de temps" (90s max) pour les calculs secondaires
  - Retour de donnees partielles si le budget est depasse
- **Optimisation calculateQuarterlyActiveLocations**: Reduit de 16 requetes DB a 2 (Schema::hasColumn cache 24h)
- **Optimisation getKPIsOptimized**: Ajout de `applyOperatorJoinAndFilter` pour eviter les JOINs inutiles pour ALL
- **Fix Express proxy timeout**: 120s -> 300s (permettre les reponses longues)
- **Error handling gracieux**: Chaque sous-calcul est wrapping dans try/catch, timeout = donnees vides

### Resultats Performance Phase 2
| Metrique | Avant Phase 2 | Apres Phase 2 |
|---------|---------------|---------------|
| KPIs (cold cache) | ~31s | ~15s (-50%) |
| retentionTrend | Bloquait indefiniment | ~1s |
| quarterlyActiveLocations | ~60s+ (16 requetes) | ~1s (2 requetes) |
| Total cold cache | 165s+ (souvent timeout) | ~150s (complete) |
| Cache HIT | ~500ms | ~535ms |
| Status | 500 ERROR | 200 OK |

## Utilisateurs
- Super Administrateur: superadmin@ooredoo.tn / Soufiane@2025 (reset)
- Administrateurs operateurs
- Utilisateurs dashboard

## Tests Passes
- Login/Logout
- Dashboard navigation (7 onglets)
- KPI cards loading avec donnees reelles
- Date pickers
- Operator selection (14 operateurs)
- Charts rendering (Performance Overview - Period Comparison)
- Cache warmup command
- API monitoring endpoint

## Problemes Connus
1. `getSubscriptionDetails` timeout toujours a 30s (requete correllee complexe) - retourne vide gracieusement
2. SQL Injection potentielle dans `DashboardService.php` (DB::raw avec interpolation de chaines)
3. `getMerchantsOptimized` prend ~46s (JOINs encore presents pour ALL)

## Backlog
### P1
- Securiser les requetes SQL (PDO bindings) dans `DashboardService.php`
- Optimiser `getMerchantsOptimized` (supprimer JOINs inutiles pour ALL)
- Optimiser `getSubscriptionDetails` (simplifier la sous-requete correlee)

### P2
- Index MySQL sur `client_abonnement_creation`, `client_abonnement_expiration`
- Vues materialisees pour les calculs KPI frequents
- Cron job automatique pour le warmup cache

### P3
- Chargement progressif du dashboard (chunks AJAX)
- Notifications de performance en temps reel

## Fichiers cles
- `app/Services/DashboardService.php` - Service principal (3400+ lignes)
- `app/Http/Middleware/DashboardPerformanceMiddleware.php` - Middleware de timeout/monitoring
- `app/Console/Commands/WarmupDashboardCache.php` - Pre-remplissage cache
- `app/Http/Controllers/Api/EklektikDashboardController.php` - Endpoints Eklektik rapides
- `/etc/nginx/sites-available/laravel` - Configuration Nginx
- `backend/server.py` - Proxy FastAPI (port 8001 -> 8002)
- `frontend/server.js` - Proxy Express (port 3000 -> 8002)
