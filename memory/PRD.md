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
**ALL pages responsive with unified mobile patterns**

Key CSS fixes:
- KPIs: overflow: visible, word-break: break-word, flex-direction: column on mobile
- Tables: Card layout with data-label on admin pages
- Tabs: overflow-x: auto for scrollable navigation
- Recommandations: reco-panels-grid -> 1 column on mobile
- CSS specificity: All mobile overrides placed AFTER base styles with !important

### Bug Fixes (Avril 2026)
- Fixed SQL GROUP BY error on AI Agent page (AIConversation.php) - uses MAX(created_at) and orderByRaw
- Fixed User Creation form dynamic logic (Sub-store/Operator toggles, Campaign multi-select)
- Fixed invitation deletion and re-invitation for previously deleted users
- Fixed KPI text truncation on mobile (overflow: visible)
- Added "Aucune donnee disponible" empty state messages for charts
- Fixed date range selectors to display inline on mobile

### CI/CD Pipeline (DONE - blocked by VPS disk space)

## Test Reports
- iteration_34: 10/10 | iteration_35: 13/13 | iteration_36: 11/11
- iteration_37: 7/7 | iteration_38: 8/8 (multi-role)
- iteration_39: 100% PASS (comprehensive re-validation - all roles, all pages, all APIs)

## Test Accounts
- SuperAdmin: superadmin@ooredoo.tn / SuperAdmin@2025
- Admin Club Privileges: admin.pluxee@test.com / Test@2025
- Collaborateur ALL: mohamed@pluxee.tn / Test@2025
- Collaborateur restricted: imededdine.essefi@gmail.com / Test@2025

## Backlog
- CI/CD: Verify pipeline once user clears VPS disk space (P1)
- Extract inline CSS from Blade templates into dedicated CSS files (P2)
