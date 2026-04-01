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
**All 5 tabs of Sub-Stores Dashboard + all admin/auth pages fully responsive**

**KPIs fix (CSS specificity resolved):**
- Mobile responsive CSS placed AFTER base styles (line 1246+) with !important
- flex-direction: column, overflow: visible, word-break: break-word
- All titles fully visible: TOTAL LOCATIONS ACTIVE, ACTIVE MERCHANT RATIO, etc.

**Recommandations tab fix:**
- .reco-kpis-row: 4->2 columns on mobile
- .reco-panels-grid: 2->1 column on mobile (stacked)

**Multi-role testing (iteration_38: 8/8 - 100%):**
- SuperAdmin: All tabs, all data, KPIs 2/row
- Admin Club Privileges: All tabs, Club data, KPIs correct
- Collaborator ALL: All tabs, 3 campaigns, KPIs correct
- Collaborator campaign-restricted: Filtered to Hutchinson Tunisie, 2013 cartes

### CI/CD Pipeline (DONE)

## Test Reports
- iteration_34: 10/10 | iteration_35: 13/13 | iteration_36: 11/11
- iteration_37: 7/7 | iteration_38: 8/8 (multi-role)

## Test Accounts
- SuperAdmin: superadmin@ooredoo.tn / SuperAdmin@2025
- Admin Club Privileges: admin.pluxee@test.com / Test@2025
- Collaborateur ALL: mohamed@pluxee.tn / Test@2025
- Collaborateur restricted: imededdine.essefi@gmail.com / Test@2025

## Backlog
- Aucune tache prioritaire restante
