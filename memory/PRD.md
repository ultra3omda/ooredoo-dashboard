# Club Privileges Dashboard - PRD

## Original Problem Statement
High-performance Laravel 10 dashboard for Club Privileges loyalty program. Includes mathematically accurate stats, automated AI weekly reporting, ML-powered predictive dashboard, B2B sub-store campaign management, and a merchant recommendation engine.

## Tech Stack
- **Backend**: Laravel 10 (PHP 8.2), FastAPI (Python), Redis Cache
- **Database**: MySQL (remote: 51.38.187.245:3306 / clubprivileges)
- **ML**: LightGBM, scikit-learn
- **AI**: OpenAI GPT-4o via litellm (Emergent LLM Key)
- **Frontend**: Blade templates, Vanilla JS, Chart.js

## Architecture
- Laravel: port 8002 (nginx/php-fpm)
- Frontend proxy: port 3000 → 8002
- FastAPI: port 8001 (ML + AI endpoints)
- Kubernetes ingress: /api/* → 8001, rest → 3000

## What's Been Implemented

### Core Dashboard (DONE)
- Main dashboard with KPIs, merchants, subscriptions
- Sub-store dashboard with full data segmentation
- Period comparison (current vs previous period)
- Export functionality

### ML Pipeline (DONE)
- Feature extraction with batch processing (~70s for full DB)
- LightGBM model training (churn prediction, CLV)
- A/B testing framework
- ML Dashboard UI

### AI Weekly Reporting (DONE)
- Automated report generation via FastAPI
- Multiple recipient profiles: CEO, Marketing, Associe, Store, Sub-store
- ML data enrichment in reports
- PDF/Email templates for all profiles

### Pluxee B2B Campaign Support (DONE - 2026-03-31)
- **Bug Fix**: Pluxee campaigns have no carte_recharge_client data. Added alternate KPI query methods using client.sub_store + client_abonnement directly.
- **Methods added**: isPluxeeCampaign(), getPluxeeDistributed/Inscriptions/ActiveUsers/Transactions/etc.
- **User Access Control**: pluxee_campaign_access column on users table, isolated campaign view
- **Admin UI**: /admin/pluxee/users management page (create, deactivate, reactivate users per campaign)
- **JS Fix**: Corrected fetch URLs in sub-stores dashboard to use /sub-stores/api/ prefix
- **Route Fix**: Fixed route('logout') → route('auth.logout') in sub-stores template
- Tested: 100% backend (9/9), 100% frontend (iteration_16.json)

## Pending / Backlog

### P1 - ML Merchant Recommendation Engine
- Phase 1: DB Infrastructure (cp_user_merchant_history, cp_merchants_catalog, cp_user_profile, cp_user_offer_interactions)
- Phase 2: Feature Extraction + LightGBM Ranker training
- Phase 3: POST /api/merchant-recommendations in FastAPI
- Phase 4: MLMerchantRecommendationService.php + Artisan command
- Phase 5: Feedback Loop + Weekly retrain scheduler

## Key Files
- `app/Http/Controllers/SubStoreController.php` - Sub-store dashboard with Pluxee support
- `app/Services/SubStoreService.php` - Access control with pluxee_campaign_access
- `app/Http/Controllers/Admin/PluxeeUserController.php` - Pluxee user management
- `backend/server.py` - FastAPI (AI Reports, ML endpoints)
- `ml_models/` - Python ML scripts
- `resources/views/sub-stores/dashboard.blade.php` - Sub-store dashboard UI
- `resources/views/admin/pluxee-users.blade.php` - Pluxee admin UI

## DB Schema (Key Tables)
- `stores`: store_id, store_name, store_type, is_sub_store, store_active
- `client`: client_id, sub_store (FK to stores.store_id), client_email
- `client_abonnement`: subscriptions with expiration dates
- `history`: transaction history
- `carte_recharge` / `carte_recharge_client`: card distribution (NOT used for Pluxee)
- `users`: id, email, role_id, pluxee_campaign_access (nullable)

## Admin Credentials
- SuperAdmin: superadmin@ooredoo.tn / SuperAdmin@2025
