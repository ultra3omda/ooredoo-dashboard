# Club Privileges Dashboard - PRD

## Original Problem Statement
High-performance Laravel 10 dashboard for Club Privileges loyalty program. ML-powered predictive recommendations. Merchant Intelligence powered by Gemini AI. Digital Presence Scoring. Role-based campaign access for collaborators.

## Tech Stack
- **Backend**: Laravel 10, FastAPI (Python), Redis Cache
- **Database**: MySQL (remote)
- **ML**: LightGBM LambdaRank + Exploration/Exploitation (28 features)
- **AI**: Gemini 2.5 Flash / OpenAI GPT-4o (fallback)
- **Frontend**: Blade templates, Vanilla JS, Chart.js

## RBAC Model
- **SuperAdmin**: Full access (all dashboards, ML, Recommandations, Admin pages)
- **Admin Sub-Store (Pluxee)**: Sub-Stores dashboard, Users, Invitations, Audit Logs (own sub-store only)
- **Collaborateur**: Sub-Stores dashboard (own campaign only), no admin pages

## Implemented Features (ALL DONE)
- Campaign Data Filtering, RBAC Permissions, KPI Renaming
- emergentintegrations fallback, Gemini->OpenAI auto-fallback
- Users Loss KPI via `deleted_clients` table
- Journal d'Audit hidden for Admin/Collaborateur
- Performance: Cache TTL 4h, lazy-loading, cache warming
- RBAC campagnes auto-résolu via user_operators->stores->carte_recharge
- Fix KPI "Cartes Utilisées": `count($clientIds)` au lieu de `COUNT(client_abonnement_id)`
- Fix Graphique Expirations: `status != 'removed'` + plage +12 mois + filtre campagne
- Fix Catégories Évolution: comparaison avec période précédente
- Fix Loading uniforme: tous KPIs en "Chargement..." + deltas masqués
- Campaign Dropdown Fallback: async + API `/api/split/campaigns`
- Fix Invitation Permissions: `getCampaigns()` filtre par campagnes autorisées de l'admin
- CI/CD Production: `.github/workflows/deploy-prod.yml` → merge PR dans main → deploy `/var/www/dashboard_cp`
- **Multi-select Opérateurs/Sub-stores (2 avril 2026)**: Les formulaires d'invitation et de création utilisateur utilisent maintenant des checkboxes au lieu de dropdowns simples. Le SuperAdmin peut sélectionner un ou plusieurs opérateurs/sub-stores avec "Tout sélectionner". Le backend crée un `UserOperator` par sélection. L'acceptation d'invitation crée aussi les multiples `UserOperator`.

## Test Accounts
- SuperAdmin: superadmin@ooredoo.tn / SuperAdmin@2025
- Collaborateur: imededdine.essefi@gmail.com / pca: ["Hutchinson Tunisie - By Pluxee"]

## Deployment Steps (VPS)
1. git pull
2. php artisan migrate (for indexes)
3. php artisan cache:clear
4. sudo supervisorctl restart fastapi_dashboard_prod

## Backlog
- Export PDF du dashboard par campagne (P1)
- CI/CD Preprod: Verify pipeline once user clears VPS disk space (P2)
- Extract inline CSS from Blade templates into dedicated CSS files (P3)
- Nettoyage des `user_operators` orphelins (user_id 42, 43, 47, 48, 50, 51, 52)
