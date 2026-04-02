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
- "Toutes les campagnes" table fix: shows per-campaign breakdown with correct distributed cards
- Journal d'Audit hidden for Admin/Collaborateur (SuperAdmin only)

## Test Accounts
- SuperAdmin: superadmin@ooredoo.tn / SuperAdmin@2025
- Admin Pluxee: admin.pluxee@test.com / Test@2025
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
