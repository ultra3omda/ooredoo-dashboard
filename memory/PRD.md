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

### Permissions Management Page (DONE)
**Route:** `/admin/users/permissions`
**Features:**
- KPI dashboard: Total users, Collaborators, Full access, Restricted
- Search + filter by role, access type
- User table showing: name, email, role, operator, campaigns, can_invite status
- **Edit modal**: Checkboxes for all available campaigns grouped by store
- **Real-time save**: AJAX POST to `/admin/users/{id}/campaign-access`
- **Full access button**: Clears campaign restriction, grants all access + invite rights
- Toast notifications, KPI auto-update, row state transitions

### Searchable Multi-Select Campagnes (DONE)
### Responsive Admin Pages (DONE)
### Permission Audit Log (DONE)
### Navigation Unifiee (DONE)

### Edit User Form - Sub-Store vs Operateur (DONE)
- Pour un utilisateur sub-store: affiche la liste des sub-stores (depuis table `stores`) avec label "Dashboard Sub-Store"
- Pour un utilisateur operateur: affiche les moyens de paiement avec label "Operateurs Assignes"

### Formulaire Creation Utilisateur - Logique Dynamique (DONE - NEW)
**Date:** Avril 2026
- Sélecteur Type (Opérateur / Sub-Store) pour SuperAdmin
- Si Opérateur: affiche dropdown opérateurs de paiement
- Si Sub-Store: affiche dropdown sub-stores + multi-select campagnes (avec recherche)
- Gestion campagnes: recherche, tags, tout sélectionner/désélectionner
- Header unifié + Dark mode inclus
- Backend: `UserManagementController@store` accepte `type_selection`, `operator_name`/`substore_name`, `campaign_access[]`
- Pour AdminOperator: type fixé à opérateur avec ses opérateurs
- Pour AdminSubStore: type fixé à sub-store avec ses sub-stores

### Ré-invitation & Suppression Invitations (DONE - NEW)
**Date:** Avril 2026
- Validation modifiée: `Rule::unique('invitations', 'email')` avec clause WHERE `status=pending AND expires_at > now()`
- Permet de ré-inviter un email après acceptation/annulation/expiration de l'invitation précédente
- Permet de ré-inviter un utilisateur supprimé (hard delete)
- Bloque uniquement les doublons d'invitations actives (pending + non expirées)
- Suppression d'invitations pendantes via le bouton Supprimer (existant, vérifié fonctionnel)

### Bug Fixes (April 2026)
- **Z-index Operator Dropdown**: Fixed `.enhanced-filters-bar` stacking context
- **Intelligence Marchands (Gemini AI)**: Restored PHP-FPM service
- **Campaign Selection for Pluxee**: Added `checkCampaignVisibility()` function
- **pluxee_campaign_access $fillable**: Added to User model

### Test Reports
- iteration_22 to iteration_33: All 100%
- iteration_34: 10/10 (100%) - User creation dynamic form + invitation re-invite + deletion

### CI/CD Pipeline (DONE)
- `.github/workflows/deploy.yml` + `deploy.sh` configurés pour la branche `emergent`
- Fonctionnel (confirmé par l'utilisateur)

## Backlog
- Aucune tache prioritaire restante

## Admin Credentials
- SuperAdmin: superadmin@ooredoo.tn / SuperAdmin@2025
