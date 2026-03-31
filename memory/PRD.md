# Club Privileges Dashboard - PRD

## Original Problem Statement
High-performance Laravel 10 dashboard for Club Privileges loyalty program. ML-powered predictive recommendations inspired by AWS Personalize. Merchant Intelligence powered by Gemini AI. Digital Presence Scoring with real web scraping. Role-based campaign access for collaborators with real-time permission management.

## Tech Stack
- **Backend**: Laravel 10, FastAPI (Python), Redis Cache
- **Database**: MySQL (remote)
- **ML**: LightGBM LambdaRank + Exploration/Exploitation (28 features)
- **AI**: Gemini 2.5 Flash (Emergent LLM Key) for Merchant Intelligence + Digital Audit
- **Scraping**: httpx + BeautifulSoup (real web/social scraping)
- **Frontend**: Blade templates, Vanilla JS, Chart.js

## Implemented Features

### Core Dashboard (DONE)
### ML Recommendation Engine v2.0 (DONE)
### P2: Client-Facing Widget (DONE)
### Merchant Intelligence Engine (DONE)
### P3: Analytics Temporel (DONE)
### P3: A/B Test Framework (DONE)
### Digital Presence Scoring (DONE)
### Collaborateur Role with Campaign Access (DONE)

### Permissions Management Page (DONE - NEW)
**Route:** `/admin/users/permissions`
**Features:**
- KPI dashboard: Total users, Collaborators, Full access, Restricted
- Search + filter by role, access type
- User table showing: name, email, role, operator, campaigns, can_invite status
- **Edit modal**: Checkboxes for all available campaigns grouped by store
- **Real-time save**: AJAX POST to `/admin/users/{id}/campaign-access`
- **Full access button**: Clears campaign restriction, grants all access + invite rights
- Toast notifications, KPI auto-update, row state transitions
- Navigation link added to Users Index page

**Endpoints:**
- GET `/admin/users/permissions` — Permissions page
- POST `/admin/users/{user}/campaign-access` — Update campaigns (JSON body)
- GET `/admin/users/available-campaigns` — All campaigns for all sub-stores

### Test Reports
- iteration_22: 20/20 (100%)
- iteration_23: 37/37 (100%)
- iteration_24: 18/18 (100%)
- iteration_25: 23/23 (100%)
- iteration_26: 10/10 (100%)
- iteration_27: 17/17 (100%)
- iteration_28: 28/28 (100%)

## Admin Credentials
- SuperAdmin: superadmin@ooredoo.tn / SuperAdmin@2025
