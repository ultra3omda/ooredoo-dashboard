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
- **Recommandations ML / Dashboard ML**: SuperAdmin ONLY (hidden from menu + middleware protected)

## KPI Definitions
- **Distribue**: sum(card_generated_number) for campaign
- **Inscriptions**: Clients with at least one abonnement + campaign filter
- **Active Users**: Clients with non-expired abonnement + campaign filter
- **Transactions**: Count of history entries for campaign clients
- **Clients avec transactions**: Distinct clients with at least one transaction (Vue d'Ensemble)
- **Taux de Conversion**: inscriptions / distribue * 100

## Implemented Features (ALL DONE)

### Code Review & Bug Fix (Avril 2026)
- **emergentintegrations fallback**: All 4 files (server.py, merchant_intelligence.py, digital_scoring.py, generate_report.py) now try emergentintegrations first, fall back to direct OpenAI/Gemini SDK
- **Gemini→OpenAI auto-fallback**: All merchant intelligence routes try Gemini first, if error (e.g. "pattern mismatch"), automatically retry with OpenAI GPT-4o
- **DB credentials security**: Removed hardcoded credentials from generate_report.py, reads from .env
- **DB connection leak fix**: All MySQL connections wrapped in try/finally
- **PHP-FPM version detection**: Lifespan auto-detects PHP 8.1 or 8.2
- **Storage path fix**: Uses relative paths instead of hardcoded /app/
- **RBAC Recommandations**: Restricted ML/Reco pages to SuperAdmin only (menu + controller middleware)
- **Invitation flow**: Admin Pluxee can invite Collaborateurs with multi-campaign assignment

### Campaign Data Filtering - COMPLETE
- `applyPluxeeCampaignFilter` uses `carte_recharge.client_id`
- SuperAdmin can filter by specific campaign via dropdown

### KPI Renaming - COMPLETE
- "Transactions Cohorte" → "Clients avec transactions" in Vue d'Ensemble

## Test Accounts
- SuperAdmin: superadmin@ooredoo.tn / SuperAdmin@2025
- Admin Pluxee: admin.pluxee@test.com / Test@2025
- Collaborateur: imededdine.essefi@gmail.com / Test@2025

## Backlog
- Export PDF du dashboard par campagne (P1)
- CI/CD: Verify pipeline once user clears VPS disk space (P2)
- Extract inline CSS from Blade templates into dedicated CSS files (P3)
