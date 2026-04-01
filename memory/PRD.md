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

### Bug Fixes (April 2026)
- **Z-index Operator Dropdown**: Fixed `.enhanced-filters-bar` stacking context (added `position: relative; z-index: 50`) so dropdown overlays KPI cards
- **Intelligence Marchands (Gemini AI)**: Restored PHP-FPM service for Laravel page serving; API was already returning 200 OK
- **Campaign Selection for Pluxee**: Added `checkCampaignVisibility()` function with role-based logic (`data-role-name` attributes on options). Campaigns now appear when selecting Collaborateur/Administrateur role + Pluxee sub-store
- **pluxee_campaign_access $fillable**: Added to User model so Eloquent `update()` properly persists campaign restrictions

### Searchable Multi-Select Campagnes (DONE - NEW)
**Location:** `/admin/invitations/create`
- Replaced checkbox grid with searchable multi-select list
- Search input with real-time filtering
- Selected campaign tags displayed above search
- "Tout sélectionner" / "Tout désélectionner" buttons
- Counter showing number of selected campaigns
- Scalable for thousands of campaigns

### Responsive Admin Pages (DONE - NEW)
- Added responsive CSS (`@media 768px, 480px`) to: users/index, invitations/index, users/permissions
- Tables wrapped in `.table-wrapper` for horizontal scroll on mobile
- Headers stack vertically, font sizes adapt on small screens

### Permission Audit Log (DONE - NEW)
**Route:** `/admin/audit-logs`
**DB Table:** `permission_audit_logs`
**Features:**
- 5 KPI cards: Total modifications, Accès complet accordés, Restrictions, Utilisateurs concernés, Admins actifs
- Filterable table: search, action type, date range, pagination
- Columns: Date/IP, Utilisateur modifié, Modifié par, Action (badge), Détails, Campagnes avant/après
- Auto-logging via `AuditLogController::logPermissionChange()` called from `updateCampaignAccess()`
- Navigation links added to: dashboard profile menu, sub-stores dashboard, permissions page

### Navigation Unifiée (DONE - NEW)
**Partial:** `/app/resources/views/partials/_admin-header.blade.php`
- Header/navbar partagé avec logo Club Privileges, liens de navigation, toggle thème, dropdown profil
- Intégré dans: users/index, invitations/index, invitations/create, users/permissions, audit-logs/index, layouts/app.blade.php (ML, mot de passe, pluxee)
- Mobile responsive: hamburger menu (<900px) avec menu déroulant complet
- Onglet actif mis en surbrillance automatiquement selon la route courante

### Test Reports
- iteration_22: 20/20 (100%)
- iteration_23: 37/37 (100%)
- iteration_24: 18/18 (100%)
- iteration_25: 23/23 (100%)
- iteration_26: 10/10 (100%)
- iteration_27: 17/17 (100%)
- iteration_28: 28/28 (100%)
- iteration_29: 3/3 P0 bugs verified (100%)
- iteration_30: P2 features all PASSED (100%)
- iteration_31: Permissions modal filtering PASSED (100%) - campaigns filtrees par operateur
- iteration_32: Unified navigation PASSED (100%) - navbar partagee sur toutes les pages admin
- iteration_33: Sub-store user fixes PASSED (100%) - label Dashboard Sub-Store, redirection SubStoreService, first-login

## Backlog
- Aucune tâche prioritaire restante

## Admin Credentials
- SuperAdmin: superadmin@ooredoo.tn / SuperAdmin@2025
