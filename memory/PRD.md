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
- `carte_recharge`: Card batches with `client_id` (direct link to client), `campain_name`, `carte_recharge_used` (1=activated), `stores` (store IDs), `card_generated_number`
- `carte_recharge_client`: Secondary link table (empty for some campaigns like Hutchinson)
- **Campaign filter**: Uses `carte_recharge.client_id WHERE carte_recharge_used=1 AND campain_name=X` (NOT carte_recharge_client)

## Implemented Features (ALL DONE)

### Campaign Data Filtering - COMPLETE (Avril 2026)
**Critical fix**: `applyPluxeeCampaignFilter` was using `carte_recharge_client` (empty for Hutchinson) → now uses `carte_recharge.client_id` directly.

Results for Collaborateur Hutchinson:
- DISTRIBUÉ: 2,013 | INSCRIPTIONS: 582 | ACTIVE USERS: 580
- TRANSACTIONS: 117 | CARTES UTILISÉES: 617 | TAUX: 28.9%

### RBAC Permission System - COMPLETE
| Feature | SuperAdmin | Admin Sub-Store | Collaborator |
|---|---|---|---|
| /sub-stores data | All sub-stores | Their sub-store (all campaigns) | Their campaign only |
| /admin/users | All users | Sub-store users only | 403 |
| /admin/audit-logs | All logs | Sub-store logs only | 403 |

### Responsive Mobile - COMPLETE
KPIs full-width in 2-col grid, proper text colors for Reco cards.

## Test Reports
- iteration_39-42: 100% | iteration_43: 100% (campaign filter fix)

## Test Accounts
- SuperAdmin: superadmin@ooredoo.tn / SuperAdmin@2025
- Admin Pluxee: admin.pluxee@test.com / Test@2025
- Collaborateur: imededdine.essefi@gmail.com / Test@2025

## Backlog
- CI/CD: Verify pipeline once user clears VPS disk space (P1)
- Extract inline CSS from Blade templates into dedicated CSS files (P2)
