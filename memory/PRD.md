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

### Core Dashboard (DONE)
### ML Recommendation Engine v2.0 (DONE)
### Client-Facing Widget (DONE)
### Merchant Intelligence Engine (DONE)
### Analytics Temporel (DONE)
### A/B Test Framework (DONE)
### Digital Presence Scoring (DONE)
### Collaborateur Role with Campaign Access (DONE)
### Permissions Management Page (DONE)
### Searchable Multi-Select Campagnes (DONE)
### Permission Audit Log (DONE)
### Navigation Unifiee (DONE)
### Edit User Form - Sub-Store vs Operateur (DONE)

### Formulaire Creation Utilisateur - Logique Dynamique (DONE)
### Re-invitation & Suppression Invitations (DONE)

### Responsive Mobile - TOUTES les pages (DONE)
**Date:** Avril 2026
**Pattern CSS:** Tables -> Card layout mobile, KPIs 2-per-row, Nav tabs scrollable, Forms 1 colonne

**Pages corrigees (Phase 1 - Admin):**
- invitations/index, users/index, permissions, audit-logs/index - Card layout avec data-label
- users/edit - CSS responsive ajoute
- users/create, invitations/create - Grid 1 colonne
- _admin-header - Fix overflow 480px

**Pages corrigees (Phase 2 - Dashboards):**
- sub-stores/dashboard.blade.php - Header stack, KPIs calc(50% - 8px), .grid:not(#kpisGrid) pour charts column, tabs overflow-x auto + flex-wrap nowrap
- dashboard.css - Header stack, KPIs compact, charts responsive
- Eklektik dashboard - Deja responsive

**Pages corrigees (Phase 3 - Auth):**
- login, forgot-password, reset-password, first-login, otp-request, otp-verify - Padding compact 480px
- change-password - Margin/padding reduits

**Pages corrigees (Phase 4 - Admin restantes):**
- layouts/eklektik-config - Nav pills stack, cards padding
- pluxee-users - Grid 1 colonne, user-row wrap
- ai-agent - Container padding, chat height
- ml-dashboard - KPIs 2 per row compact
- merchant-recommendations - Grid 2 col, cards wrap
- timwe-diagnostic - Summary 2 col, tab buttons compact

**Tests:** 
- iteration_35: 13/13 (100%) - Admin pages mobile + desktop regression
- iteration_36: 11/11 (100%) - All pages mobile + desktop regression

### Donnees abonnements a expiration
**Statut:** DONNEES REELLES de la base client_abonnement (champ client_abonnement_expiration)
Les 38 expirations Mars 2026 et 1 en Avril 2026 sont des donnees reelles.

### CI/CD Pipeline (DONE)
- `.github/workflows/deploy.yml` + `deploy.sh` pour branche `emergent`
- Fonctionnel (confirme par utilisateur)

## Backlog
- Aucune tache prioritaire restante

## Admin Credentials
- SuperAdmin: superadmin@ooredoo.tn / SuperAdmin@2025
