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
### Searchable Multi-Select Campagnes (DONE)
### Permission Audit Log (DONE)
### Navigation Unifiee (DONE)
### Edit User Form - Sub-Store vs Operateur (DONE)

### Formulaire Creation Utilisateur - Logique Dynamique (DONE)
**Date:** Avril 2026
- Selecteur Type (Operateur / Sub-Store) pour SuperAdmin
- Si Operateur: affiche dropdown operateurs de paiement
- Si Sub-Store: affiche dropdown sub-stores + multi-select campagnes (avec recherche)
- Backend: `UserManagementController@store` accepte `type_selection`, `operator_name`/`substore_name`, `campaign_access[]`

### Re-invitation & Suppression Invitations (DONE)
**Date:** Avril 2026
- Validation modifiee: `Rule::unique('invitations', 'email')` avec clause WHERE `status=pending AND expires_at > now()`
- Permet de re-inviter un email apres acceptation/annulation/expiration
- Suppression d'invitations pendantes fonctionnelle

### Responsive Mobile - Toutes les vues admin (DONE)
**Date:** Avril 2026
- **Pattern:** Tables converties en cartes mobiles (CSS `display: block`, `data-label`, `::before`)
- **Pages corrigees:**
  - `invitations/index.blade.php` - Card layout, colonnes secondaires masquees (lien, date)
  - `users/index.blade.php` - Card layout, colonnes secondaires masquees (operateurs, derniere connexion)
  - `users/permissions.blade.php` - Table card layout, modal 95% width, KPI 2x2
  - `audit-logs/index.blade.php` - Card layout, filtres empiles verticalement
  - `users/edit.blade.php` - CSS responsive ajoute (grid 1 col, header colonne)
  - `users/create.blade.php` - Deja responsive (grid 1 col)
  - `invitations/create.blade.php` - Enrichi avec styles container/header mobile
  - `partials/_admin-header.blade.php` - Fix overflow 480px, padding reduit
- **Tests:** 13/13 (100%) - Mobile + Desktop regression

### CI/CD Pipeline (DONE)
- `.github/workflows/deploy.yml` + `deploy.sh` pour branche `emergent`

## Test Reports
- iteration_22 to iteration_33: All 100%
- iteration_34: 10/10 (100%) - User creation dynamic form + invitation re-invite
- iteration_35: 13/13 (100%) - Mobile responsive all admin pages + desktop regression

## Backlog
- Aucune tache prioritaire restante

## Admin Credentials
- SuperAdmin: superadmin@ooredoo.tn / SuperAdmin@2025
