# Club Privileges Dashboard - PRD

## Original Problem Statement
High-performance Laravel 10 dashboard for Club Privileges loyalty program. ML-powered predictive recommendations. Merchant Intelligence powered by Gemini AI. Digital Presence Scoring. Role-based campaign access for collaborators.

## Tech Stack
- **Backend**: Laravel 10, FastAPI (Python), Redis Cache
- **Database**: MySQL (remote)
- **ML**: LightGBM LambdaRank + Exploration/Exploitation (28 features)
- **AI**: Gemini 2.5 Flash (Emergent LLM Key)
- **Frontend**: Blade templates, Vanilla JS, Chart.js

## Implemented Features (ALL DONE)

### Core Features
- Core Dashboard, ML Recommendation Engine v2.0, Client-Facing Widget
- Merchant Intelligence, Analytics Temporel, A/B Test Framework
- Digital Presence Scoring, Collaborateur Role with Campaign Access
- Permissions Management, Audit Log, Unified Navigation
- Edit User Form, Dynamic User Creation, Re-invitation/Deletion

### Responsive Mobile - COMPLETE (Avril 2026)
All pages responsive. Key CSS fixes:
- KPIs: `width: 100% !important`, `flex: 1 1 auto !important` for Merchant/Users/Reco in grid
- Tables: Card layout with data-label on admin pages
- Tabs: overflow-x: auto for scrollable navigation

### RBAC Campaign Filtering - COMPLETE (Avril 2026)
- **Expirations chart**: Now filters by campaign via `getExpirationsByMonth($ss, $months, $campaign)`
- **Sub-Stores ranking**: `computeCampaignRanking()` shows campaign-level data for restricted users
- **applyPluxeeCampaignFilter**: Fixed join path (carte_recharge_client → carte_recharge, NOT carte_recharge_code)
- **Frontend**: Dynamic title/headers adapt between "Classement Sub-Stores" vs "Classement Campagne"
- **Data integrity**: Hutchinson has 2013 cards, 0 activations → all KPIs/charts show 0 (correct per DB admin)

### Bug Fixes (Avril 2026)
- Fixed SQL GROUP BY error on AI Agent page (AIConversation.php)
- Fixed expirations chart race condition (updateCharts called before API response)
- Fixed KPI card width on mobile (was 79px due to calc(50% - 8px) from parent flexbox)
- Fixed Reco KPI text invisible (white on white → dark colors)
- Fixed applyPluxeeCampaignFilter using non-existent carte_recharge_code table

## Test Reports
- iteration_39: 100% | iteration_40: 100% | iteration_41: 100%

## Test Accounts
- SuperAdmin: superadmin@ooredoo.tn / SuperAdmin@2025
- Admin Club Privileges: admin.pluxee@test.com / Test@2025 (pluxee_campaign_access=NULL → full access)
- Collaborateur: imededdine.essefi@gmail.com / Test@2025 (restricted to Hutchinson)

## Backlog
- CI/CD: Verify pipeline once user clears VPS disk space (P1)
- Extract inline CSS from Blade templates into dedicated CSS files (P2)
