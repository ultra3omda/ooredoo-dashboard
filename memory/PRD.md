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
- KPIs: `width: 100% !important`, `flex: 1 1 auto !important` for Merchant/Users/Reco grids
- Reco KPIs: color: var(--muted) for titles, var(--brand-dark) for values (was white-on-white)
- Tables: Card layout with data-label on admin pages
- Tabs: overflow-x: auto for scrollable navigation

### RBAC Permission System - COMPLETE (Avril 2026)
**Roles:**
- `super_admin` (id=1): Full access to everything
- `admin` (id=2): Admin for specific operator/sub-store
  - `admin_operator`: Manages users of their operator
  - `admin_sub_store`: Manages users/campaigns of their sub-store
- `collaborator` (id=3): Read-only access to assigned sub-store data

**Permission Matrix:**
| Feature | SuperAdmin | Admin Sub-Store | Collaborator |
|---|---|---|---|
| /dashboard | Full access | Redirect to /sub-stores | Redirect to /sub-stores |
| /sub-stores | All sub-stores | Their sub-store | Their campaign only |
| /admin/users | All users | Only sub-store users | 403 |
| /admin/invitations | All invitations | Sub-store invitations | 403 |
| /admin/audit-logs | All logs | Sub-store logs only | 403 |
| /admin/users/permissions | All users | Sub-store users | 403 |
| /admin/ai-agent | Full access | Full access | 403 |
| User creation | Any role | Collaborators only | N/A |
| User deletion | Yes | No (super_admin only) | N/A |
| Expirations chart | All data | All sub-store data | Campaign-filtered (0 if no activations) |
| Sub-Stores ranking | Top 15 sub-stores | Top 15 sub-stores | Campaign-level stats only |

### Campaign Data Filtering - COMPLETE (Avril 2026)
- `getExpirationsByMonth($ss, $months, $campaign)`: Filters by campaign when user has restriction
- `computeCampaignRanking($ss)`: Shows campaign-level data instead of sub-store ranking
- `applyPluxeeCampaignFilter`: Fixed join path (carte_recharge_client → carte_recharge)
- Frontend: Dynamic title/headers adapt between "Classement Sub-Stores" vs "Classement Campagne"
- Data integrity: Hutchinson has 2013 cards, 0 activations → all KPIs/charts show 0

### Bug Fixes (Avril 2026)
- Fixed SQL GROUP BY error on AI Agent page (AIConversation.php)
- Fixed expirations chart race condition (updateCharts called before API response)
- Fixed KPI card width on mobile (was 79px due to calc(50% - 8px))
- Fixed Reco KPI text invisible (white on white → dark colors)
- Fixed applyPluxeeCampaignFilter using non-existent carte_recharge_code table
- Fixed UserManagement index: admin_sub_store now sees all sub-store users
- Fixed InvitationController: admin_sub_store sees sub-store invitations
- Fixed AuditLogController: logs/stats filtered by sub-store for non-super_admin

## Test Reports
- iteration_39: 100% | iteration_40: 100% | iteration_41: 100% | iteration_42: 100% (RBAC)

## Test Accounts
- SuperAdmin: superadmin@ooredoo.tn / SuperAdmin@2025
- Admin Club Privileges: admin.pluxee@test.com / Test@2025 (pluxee_campaign_access=NULL → all campaigns)
- Collaborateur ALL: mohamed@pluxee.tn / Test@2025
- Collaborateur restricted: imededdine.essefi@gmail.com / Test@2025 (Hutchinson only)

## Backlog
- CI/CD: Verify pipeline once user clears VPS disk space (P1)
- Extract inline CSS from Blade templates into dedicated CSS files (P2)
