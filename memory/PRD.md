# Club Privileges Dashboard - PRD

## Original Problem Statement
High-performance Laravel 10 dashboard for Club Privileges loyalty program. Includes mathematically accurate stats, automated AI weekly reporting, ML-powered predictive dashboard, B2B sub-store campaign management, and a merchant recommendation engine.

## Tech Stack
- **Backend**: Laravel 10 (PHP 8.2), FastAPI (Python), Redis Cache
- **Database**: MySQL (remote: 51.38.187.245:3306 / clubprivileges)
- **ML**: LightGBM (Churn + Merchant Ranker), scikit-learn
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

### ML Pipeline v1 - Churn Prediction (DONE)
- Feature extraction with batch processing (~70s for full DB)
- LightGBM model training (churn prediction, CLV)
- A/B testing framework
- ML Dashboard UI

### AI Weekly Reporting (DONE)
- Automated report generation via FastAPI
- Multiple recipient profiles: CEO, Marketing, Associe, Store, Sub-store
- ML data enrichment in reports
- PDF/Email templates for all profiles
- **Enriched with merchant recommendation data** (2026-04-02)

### Pluxee B2B Campaign Support (DONE)
- Pluxee campaigns bypass carte_recharge_client checks
- Dedicated user access control (pluxee_campaign_access column)
- Admin UI: /admin/pluxee/users management page

### Sub-Store Dashboard Refactoring (DONE)
- SubStoreController refactored from ~2500 to ~900 lines
- Split into 5 concurrent API endpoints: kpis, stores, charts, merchants, users
- Frontend uses Promise.allSettled for parallel loading
- DataControllerOptimized.php fully cleaned up

### ML Merchant Recommendation Engine (DONE - 2026-04-02)
- **Phase 1 - DB Infrastructure**: 4 tables (cp_user_merchant_history, cp_merchants_catalog, cp_user_profile, cp_user_offer_interactions) + ml_recommendations enum extended
- **Phase 2 - Feature Extraction & Training**: LightGBM Ranker on 139K samples (576 merchants, 19K user profiles). NDCG@5 = 1.0
- **Phase 3 - FastAPI API**: POST /api/merchant-recommendations, /health, /track, /stats, /retrain
- **Phase 4 - Laravel Service**: MLMerchantRecommendationService.php + Artisan command
- **Phase 5 - Feedback Loop**: Interaction tracking + weekly retrain scheduler (Sunday 06:30)

### Merchant Recommendations Dashboard (DONE - 2026-04-02)
- **Admin page**: /admin/merchant-recommendations
- **KPIs panel**: Model status, 576 active merchants, 19K user profiles, interaction count
- **Personalized search**: Search by client_id with ML-scored recommendations + ML Model/Popularity tag
- **Popular merchants panel**: Top 10 with gold/silver/bronze rank badges, scores, promos
- **Stats panel**: 7-day interaction tracking stats table
- **Model performance panel**: NDCG@5/10 metrics, training info, top feature importances
- **Retrain button**: Trigger model retraining from UI
- **Category filter**: Filter recommendations by merchant category
- **Navigation**: Menu link from main dashboard dropdown
- **Testing**: 100% pass (iteration_19.json)

### Enriched AI Weekly Reports (DONE - 2026-04-02)
- generate_report.py now includes merchant catalog stats, top categories, top merchants, interaction stats
- Report prompt includes recommendation engine data for GPT-4o analysis
- Report snapshot stores merchant_reco_snapshot

## Key API Endpoints

### Sub-Store Split APIs (auth required)
- GET /sub-stores/api/split/kpis
- GET /sub-stores/api/split/stores
- GET /sub-stores/api/split/charts
- GET /sub-stores/api/split/merchants
- GET /sub-stores/api/split/users

### ML Merchant Recommendations (FastAPI)
- POST /api/merchant-recommendations
- GET /api/merchant-recommendations/health
- POST /api/merchant-recommendations/track
- GET /api/merchant-recommendations/stats
- POST /api/merchant-recommendations/retrain

### Admin Dashboard Routes
- GET /admin/merchant-recommendations (Dashboard)
- POST /admin/merchant-recommendations/recommend
- GET /admin/merchant-recommendations/popular
- POST /admin/merchant-recommendations/retrain
- GET /admin/merchant-recommendations/health

## Key Files
- `app/Http/Controllers/SubStoreController.php`
- `app/Http/Controllers/Api/DataControllerOptimized.php`
- `app/Http/Controllers/Admin/MerchantRecommendationController.php`
- `app/Services/MLMerchantRecommendationService.php`
- `app/Console/Commands/MerchantRecommendationCommand.php`
- `backend/server.py` (FastAPI)
- `ml_models/train_merchant_recommender.py`
- `ml_models/predict_merchant.py`
- `ml_models/generate_report.py`
- `resources/views/admin/merchant-recommendations.blade.php`
- `resources/views/sub-stores/dashboard.blade.php`

## DB Schema (Key Tables)
- `stores`, `partner`, `partner_category`, `promotion`, `history`, `client`, `client_abonnement`
- `cp_user_merchant_history`: pre-computed user-merchant interaction features
- `cp_merchants_catalog`: enriched merchant catalog for ML
- `cp_user_profile`: aggregated user features for ML
- `cp_user_offer_interactions`: feedback tracking

## Pending / Backlog
- P2: Performance optimization (query caching, index tuning)
- P3: Client-facing recommendation widget in mobile app/web

## Admin Credentials
- SuperAdmin: superadmin@ooredoo.tn / SuperAdmin@2025
