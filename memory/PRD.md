# Club Privileges Dashboard - PRD

## Original Problem Statement
High-performance Laravel 10 dashboard for Club Privileges loyalty program. ML-powered predictive recommendations. Merchant Intelligence powered by Gemini AI. Digital Presence Scoring. Role-based campaign access for collaborators.

## Tech Stack
- **Backend**: Laravel 10, FastAPI (Python), Redis Cache
- **Database**: MySQL (remote)
- **ML**: LightGBM LambdaRank + Exploration/Exploitation (28 features)
- **AI**: Gemini 2.5 Flash (Emergent LLM Key)
- **Frontend**: Blade templates, Vanilla JS, Chart.js

## Data Model - Campaign Linking
- `carte_recharge`: Cards with `client_id`, `campain_name`, `carte_recharge_used` (1=activated), `stores`, `card_generated_number`
- **Campaign filter**: Uses `carte_recharge.client_id WHERE carte_recharge_used=1 AND campain_name=X`
- NOT carte_recharge_client (empty for some campaigns)

## KPI Definitions (Unified across Vue d'Ensemble and Users tabs)
- **Distribue**: sum(card_generated_number) for campaign, or count(clients) for all
- **Inscriptions**: Clients with at least one abonnement + campaign filter
- **Active Users**: Clients with non-expired abonnement + campaign filter
- **Transactions**: Count of history entries for campaign clients
- **Clients avec transactions**: Distinct clients with at least one transaction (replaced "Transactions Cohorte" in Vue d'Ensemble)
- **Cartes Utilisees/Subscriptions**: Count of abonnements created in period for campaign clients
- **Inscriptions Cohorte**: Clients created within period + campaign filter
- **Taux de Conversion**: inscriptions / distribue * 100

## Implemented Features (ALL DONE)

### Campaign Data Filtering - COMPLETE (Avril 2026)
- `applyPluxeeCampaignFilter` uses `carte_recharge.client_id` (NOT carte_recharge_client)
- `getUsersKPIs` now uses `getActiveUsersWithCards` (consistent with Vue d'Ensemble)
- All Pluxee functions apply campaign filter consistently
- SuperAdmin can filter by specific campaign via dropdown and see same data as Collaborateur

### RBAC Permission System - COMPLETE
- Admin Sub-Store sees only their sub-store users/logs/invitations
- Collaborateur gets 403 on admin pages, sees only campaign-filtered data

### KPI Renaming - COMPLETE (Avril 2026)
- Replaced "Transactions Cohorte" with "Clients avec transactions" in Vue d'Ensemble
- Backend API returns `clientsWithTransactions` key (not `transactionsCohorte`)
- Both loading state and data-loaded state show correct label
- Tooltips updated to remove old references

## Test Reports
- iteration_39-44: ALL 100% PASS

## Test Accounts
- SuperAdmin: superadmin@ooredoo.tn / SuperAdmin@2025
- Admin Pluxee: admin.pluxee@test.com / Test@2025
- Collaborateur: imededdine.essefi@gmail.com / Test@2025

## Backlog
- Export PDF du dashboard par campagne (P1)
- CI/CD: Verify pipeline once user clears VPS disk space (P2)
- Extract inline CSS from Blade templates into dedicated CSS files (P3)
