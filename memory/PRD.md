# Club Privileges Dashboard - PRD

## Original Problem Statement
High-performance Laravel 10 dashboard for Club Privileges loyalty program. ML-powered predictive recommendations. Merchant Intelligence powered by Gemini AI. Digital Presence Scoring. Role-based campaign access for collaborators.

## Tech Stack
- **Backend**: Laravel 10, FastAPI (Python), Redis Cache
- **Database**: MySQL (remote)
- **ML**: LightGBM LambdaRank + Exploration/Exploitation (28 features)
- **AI**: Gemini 2.5 Flash (Emergent LLM Key)
- **Frontend**: Blade templates, Vanilla JS, Chart.js

## Implemented Features

### Core Features (ALL DONE)
- Core Dashboard, ML Recommendation Engine v2.0, Client-Facing Widget
- Merchant Intelligence Engine, Analytics Temporel, A/B Test Framework
- Digital Presence Scoring, Collaborateur Role with Campaign Access
- Permissions Management, Permission Audit Log, Navigation Unifiee
- Edit User Form Sub-Store/Operateur, Dynamic User Creation Form
- Re-invitation & Suppression Invitations

### Responsive Mobile - COMPLETE (Avril 2026)
**All pages responsive with unified mobile patterns:**
- Admin tables: Card layout with data-label
- KPIs: 2-per-row compact with word-break for long titles
- Nav tabs: Horizontally scrollable (overflow-x: auto)
- Forms: Single column on mobile
- Auth pages: Compact padding

**Merchant/Users KPIs fix:**
- flex-direction: column on cards (icon top, title below)
- kpi-title: font-size 9px at 600px, 8px at 480px, word-break: break-word
- grid-template-columns: 1fr 1fr (always 2-per-row on mobile)

**Date section fix:**
- flex-direction: row on .date-inputs (dates inline, not stacked)
- period-section gap: 6px for compact vertical spacing

**Empty chart message:**
- showNoDataMessage() function: SVG icon + text + subtitle
- Applied to inscriptionsChart and expirationsChart

### Campaign filtering (VERIFIED)
- Backend enforces campaign restriction via normalizeSubStoreParams()
- Frontend passes campaign parameter in all split API calls
- Works for collaborators with pluxee_campaign_access restrictions

### CI/CD Pipeline (DONE)

## Test Reports
- iteration_34: 10/10 - User creation + invitation
- iteration_35: 13/13 - Admin mobile + desktop regression
- iteration_36: 11/11 - All pages mobile + desktop regression
- iteration_37: 7/7 - Merchant/Users KPIs + dates + collaborator

## Backlog
- Aucune tache prioritaire restante

## Admin Credentials
- SuperAdmin: superadmin@ooredoo.tn / SuperAdmin@2025
- Collaborator (test): imededdine.essefi@gmail.com / Test@2025
