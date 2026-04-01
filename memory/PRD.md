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

**Key CSS fixes:**
- KPIs: overflow: visible !important, word-break: break-word, flex-direction: column on mobile
- Tables: Card layout with data-label on admin pages
- Tabs: overflow-x: auto for scrollable navigation
- Recommandations: reco-panels-grid -> 1 column on mobile
- CSS specificity: All mobile overrides placed AFTER base styles with !important

**Multi-role testing (iteration_38: 8/8 - 100%):**
- SuperAdmin: All tabs, all data
- Admin Club Privileges: All tabs, Club data
- Collaborator ALL campaigns: All tabs, 3 campaigns
- Collaborator Hutchinson: Filtered data, campaign isolation verified

### Known Issues (Pre-existing)
- AI Agent page: SQL GROUP BY error (SQLSTATE[42000] only_full_group_by)
  Not related to responsive changes

### CI/CD Pipeline (DONE)

## Test Reports
- iteration_34: 10/10 | iteration_35: 13/13 | iteration_36: 11/11
- iteration_37: 7/7 | iteration_38: 8/8 (multi-role final)

## Test Accounts
- SuperAdmin: superadmin@ooredoo.tn / SuperAdmin@2025
- Admin Club Privileges: admin.pluxee@test.com / Test@2025
- Collaborateur ALL: mohamed@pluxee.tn / Test@2025
- Collaborateur restricted: imededdine.essefi@gmail.com / Test@2025

## Backlog
- Fix AI Agent SQL GROUP BY error (pre-existing, not responsive related)
