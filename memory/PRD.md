# Club Privileges Dashboard - PRD

## Original Problem Statement
High-performance Laravel 10 dashboard for Club Privileges loyalty program. ML-powered predictive recommendations. Merchant Intelligence powered by Gemini AI. Digital Presence Scoring. Role-based campaign access for collaborators.

## Tech Stack
- **Backend**: Laravel 10, FastAPI (Python), Redis Cache
- **Database**: MySQL (remote)
- **ML**: LightGBM LambdaRank + Exploration/Exploitation (28 features)
- **AI**: Gemini 2.5 Flash / OpenAI GPT-4o (fallback)
- **Frontend**: Blade templates, Vanilla JS, Chart.js

## RBAC Model
- **SuperAdmin**: Full access (all dashboards, ML, Recommandations, Admin pages)
- **Admin Sub-Store (Pluxee)**: Sub-Stores dashboard, Users, Invitations, Audit Logs (own sub-store only)
- **Collaborateur**: Sub-Stores dashboard (own campaign only), no admin pages
- **Recommandations ML / Dashboard ML**: SuperAdmin ONLY

## Performance Optimization (Avril 2026)
### Pre-resolved Campaign Client IDs
- `getCampaignClientIds()` executes campaign filter ONCE, caches for 30 minutes
- Replaces 18 identical sub-queries with a single cached array lookup
- Property `$resolvedCampaignClientIds` prevents re-fetching within same request

### Batch SQL Queries (Pluxee Path)
- `computeKpisPluxeeBatch()`: 3 queries instead of 15+ (distributed, subscriptions, transactions)
- `getUsersKPIsPluxeeBatch()`: 2 queries instead of 14 (subscriptions, transactions)
- Uses CASE WHEN aggregation to compute multiple metrics in single query

### SQL Indexes
- Migration: `2026_04_01_230000_add_performance_indexes_substores.php`
- 7 targeted indexes on carte_recharge, client_abonnement, history, client, carte_recharge_client

### Frontend
- Timeout increased to 180s for large datasets
- Load time displayed in notification

## Implemented Features (ALL DONE)
- Campaign Data Filtering, RBAC Permissions, KPI Renaming, Code Review (7 bugs fixed)
- emergentintegrations fallback to direct SDK, Gemini→OpenAI auto-fallback
- DB credentials security, connection leak fixes, PHP-FPM version detection
- Users Loss KPI: comptage des clients supprimés via table `deleted_clients`, affiché en Vue d'Ensemble + onglet Users
  - Hutchinson: 657 inscriptions + 7 loss = 664 total (equilibre confirmé)
  - Toutes campagnes Pluxee: 658 inscriptions + 7 loss = 665
- Journal d'Audit hidden for Admin/Collaborateur (SuperAdmin only)
- Fixed calculateUserChange bug: percentage change was inverted (-100% → +100%)
- Mobile scroll CSS fix for Users tab: overflow-x hidden, proper table-wrapper sizing
- Performance: Cache TTL increased from 1h to 4h, lazy-loading for Merchants/Users tabs
- Performance: Initial load reduced from 5 parallel requests to 3 essential + 2 background
- Cache Warming: `substores:warmup` CLI command + bouton SuperAdmin + cron scheduling (*/3h)
  - Pre-calcule les 5 sections pour chaque sub-store et campagne
  - Réduit le temps serveur de ~8.6s à ~530ms (16x plus rapide)
  - Route `/sub-stores/api/warmup` (POST, SuperAdmin) + `/sub-stores/api/warmup-status` (GET)
- RBAC campagnes auto-résolu: admin/collaborateur ne voit que ses campagnes via user_operators→stores→carte_recharge (sans pluxee_campaign_access explicite)
- Fix distributed KPI centralisé via getPluxeeDistributed() avec filtre allowedCampaigns
- **Fix KPI "Cartes Utilisées" (2 avril 2026)**: `totalSubscriptions` corrigé pour compter `count($clientIds)` (clients distincts avec cartes activées) au lieu de `COUNT(client_abonnement_id)` (lignes d'abonnement). Avant: 707, Après: 664/665. `cards_activated`/`newUsers` corrigés avec `COUNT(DISTINCT)` au lieu de `SUM`.

## Test Accounts
- SuperAdmin: superadmin@ooredoo.tn / SuperAdmin@2025
- Admin Pluxee: imedos001@gmail.com / Test@2025
- Collaborateur: imededdine.essefi@gmail.com / Test@2025

## Deployment Steps (VPS)
1. git pull
2. php artisan migrate (for indexes)
3. php artisan cache:clear
4. sudo supervisorctl restart fastapi_dashboard_prod

## Backlog
- Export PDF du dashboard par campagne (P1)
- CI/CD: Verify pipeline once user clears VPS disk space (P2)
- Extract inline CSS from Blade templates into dedicated CSS files (P3)
