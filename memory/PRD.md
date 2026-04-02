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

## Performance Optimization (Avril 2026)
### Pre-resolved Campaign Client IDs
- `getCampaignClientIds()` executes campaign filter ONCE, caches for 30 minutes
- Replaces 18 identical sub-queries with a single cached array lookup

### Batch SQL Queries (Pluxee Path)
- `computeKpisPluxeeBatch()`: 3 queries instead of 15+
- `getUsersKPIsPluxeeBatch()`: 2 queries instead of 14
- Uses CASE WHEN aggregation

### SQL Indexes
- Migration: `2026_04_01_230000_add_performance_indexes_substores.php`
- 7 targeted indexes

## Implemented Features (ALL DONE)
- Campaign Data Filtering, RBAC Permissions, KPI Renaming
- emergentintegrations fallback to direct SDK, Gemini->OpenAI auto-fallback
- DB credentials security, connection leak fixes, PHP-FPM version detection
- Users Loss KPI via `deleted_clients` table
- Journal d'Audit hidden for Admin/Collaborateur (SuperAdmin only)
- Fixed calculateUserChange bug: percentage change was inverted
- Mobile scroll CSS fix for Users tab
- Performance: Cache TTL 4h, lazy-loading for Merchants/Users tabs
- Cache Warming: `substores:warmup` CLI command + bouton SuperAdmin + cron scheduling
- RBAC campagnes auto-résolu via user_operators->stores->carte_recharge
- Fix distributed KPI centralisé via getPluxeeDistributed()
- **Fix KPI "Cartes Utilisées" (2 avril 2026)**: `totalSubscriptions` = `count($clientIds)` au lieu de `COUNT(client_abonnement_id)`. `cards_activated`/`newUsers` avec `COUNT(DISTINCT)`.
- **Fix Graphique Expirations (2 avril 2026)**: Filtre `status != 'removed'` pour exclure les tentatives avortées (43 faux positifs). Plage étendue +12 mois pour montrer les expirations futures. Paramètre `campaign` transmis à l'API.
- **Fix Catégories Évolution (2 avril 2026)**: Calcul de la comparaison avec période précédente pour le delta Evolution.
- **Fix Loading uniforme (2 avril 2026)**: Tous les KPIs passent en "Chargement..." uniformément, deltas masqués pendant le chargement, données Merchant/Users réinitialisées lors du refresh.
- **Campaign Dropdown Fallback (2 avril 2026)**: `updateCampaignDropdown()` maintenant async avec fallback via `/api/split/campaigns` si le cache initial est vide. Nouvel endpoint `getCampaignsSplit` ajouté.

## Test Accounts
- SuperAdmin: superadmin@ooredoo.tn / SuperAdmin@2025
- Admin Pluxee: imedos001@gmail.com / Test@2025
- Collaborateur: imededdine.essefi@gmail.com / Test@2025

## Deployment Steps (VPS)
1. git pull
2. php artisan migrate (for indexes)
3. php artisan cache:clear (ou redis-cli -a hxtrJ74 FLUSHALL)
4. sudo supervisorctl restart fastapi_dashboard_prod

## Backlog
- Export PDF du dashboard par campagne (P1)
- CI/CD: Verify pipeline once user clears VPS disk space (P2)
- Extract inline CSS from Blade templates into dedicated CSS files (P3)
