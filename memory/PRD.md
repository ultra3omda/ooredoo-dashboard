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
- **Repo GitHub**: ultra3omda/ooredoo-dashboard (branche develop = production)

## Ce qui a ete implemente

### Deploiement et Configuration (26 Mars 2026)
- Installation PHP 8.2, Composer, PHP-FPM, Nginx
- Configuration des proxys (FastAPI + Node.js) pour integrer Laravel dans l'infrastructure Emergent
- Configuration PHP-FPM (10 workers, 300s request_terminate_timeout, 512MB memory)
- Configuration Nginx avec timeouts etendus (300s)
- Configuration .env complete, TrustProxies='*'

### Optimisations Performance - Phase 1 a 3 (26 Mars 2026)
- Cache Redis active, Trusted Proxies configures
- Fix 500 Error: API bloquait sur calculateRetentionTrendOptimized
- Suppression JOINs inutiles pour operateur ALL
- Timeout MySQL 30s par requete, budget de temps 90s
- Securisation SQL avec PDO bindings
- Cron warmup cache toutes les 25 minutes
- Chargement progressif (split API): 5 endpoints en parallele
- Fix auth split endpoints (routes web.php avec middleware session)
- **Tables materialisees (dashboard_daily_stats)**: 1278 lignes, 90 jours, KPIs < 1s
- Cron materialisation quotidienne a 3h00

### Fusion branche develop (26 Mars 2026)
- Import des 228 fichiers manquants de ultra3omda/develop
- Integration des onglets **Agent IA** et **Diagnostic Timwe** dans le dashboard
- Ajout des routes ML Dashboard, AI Agent, Timwe Diagnostic dans web.php
- Fusion du Kernel.php avec les cron jobs ML (tx-daily-ingest, build-90d-features, ml-maintenance)
- Import des controllers Admin (AIAgentController, TimweDiagnosticController, MLDashboardController)
- Reset TrustProxies pour '*'

### Resultats Performance FINAL
| Metrique | Avant | Apres | Amelioration |
|---------|-------|-------|-------------|
| Cold cache (14j ALL) | 165s+ (timeout) | 16.9s | -90% |
| Warm cache | ~500ms | ~2-3s (progressif) | Perception amelioree |
| KPIs materialisees | ~31s | ~1s | -97% |
| Status | 500 ERROR | 200 OK | Fix complet |

## Utilisateurs
- Super Admin: superadmin@ooredoo.tn / Soufiane@2025

## Backlog
- P3: Materialiser merchants/subscriptions/transactions pour cold cache < 5s
- P3: Notifications de performance en temps reel
- P3: Materialisation etendue sur 365 jours

## Fichiers cles
- `app/Services/DashboardService.php` - Service principal avec tables materialisees
- `app/Http/Controllers/Api/DataControllerOptimized.php` - Endpoints split + monolithique
- `app/Console/Commands/MaterializeDailyStats.php` - Materialisation KPIs quotidiens
- `app/Console/Commands/WarmupDashboardCache.php` - Pre-remplissage cache
- `app/Console/Kernel.php` - Scheduler complet (warmup, materialize, ML, Timwe, Ooredoo)
- `routes/web.php` - Routes dashboard + split + AI Agent + Diagnostic Timwe + ML
- `resources/views/dashboard.blade.php` - Frontend avec chargement progressif + Agent IA
- `resources/views/admin/timwe-diagnostic.blade.php` - Page Diagnostic Timwe
- `resources/views/admin/ai-agent.blade.php` - Page Agent IA standalone
