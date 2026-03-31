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

### Pluxee B2B Campaign Support (DONE - 2026-03-31)
- Pluxee campaigns bypass carte_recharge_client checks
- Dedicated user access control (pluxee_campaign_access column)
- Admin UI: /admin/pluxee/users management page

### Sub-Store Dashboard Refactoring (DONE - 2026-03-31)
- SubStoreController refactored from ~2500 to ~900 lines
- Split into 5 concurrent API endpoints: kpis, stores, charts, merchants, users
- Frontend uses Promise.allSettled for parallel loading
- Cleaned up obsolete files and code

### ML Merchant Recommendation Engine (DONE - 2026-04-02)
- **Phase 1 - DB Infrastructure**: 4 tables created (cp_user_merchant_history, cp_merchants_catalog, cp_user_profile, cp_user_offer_interactions) + ml_recommendations enum extended
- **Phase 2 - Feature Extraction & Training**: LightGBM Ranker trained on 139K samples (576 merchants, 19K user profiles, 57K user-merchant pairs). NDCG@5 = 1.0
- **Phase 3 - FastAPI API**: POST /api/merchant-recommendations (personalized + cold-start), GET /health, POST /track, GET /stats, POST /retrain
- **Phase 4 - Laravel Service**: MLMerchantRecommendationService.php + Artisan command (status, recommend, retrain)
- **Phase 5 - Feedback Loop**: Interaction tracking (impression/click/redeem/dismiss/share) + weekly retrain scheduler (Sunday 06:30)
- **Testing**: 18/18 backend tests passed (iteration_18.json)

## Key API Endpoints

### Sub-Store Split APIs (auth required)
- GET /sub-stores/api/split/kpis
- GET /sub-stores/api/split/stores
- GET /sub-stores/api/split/charts
- GET /sub-stores/api/split/merchants
- GET /sub-stores/api/split/users

### ML Merchant Recommendations (FastAPI)
- POST /api/merchant-recommendations (client_id, top_k, category_id, exclude_visited)
- GET /api/merchant-recommendations/health
- POST /api/merchant-recommendations/track
- GET /api/merchant-recommendations/stats
- POST /api/merchant-recommendations/retrain

### Other APIs
- POST /api/report-ai-suggestions (AI weekly report)
- Various Eklektik/Ooredoo/Timwe dashboard APIs

## Key Files
- `app/Http/Controllers/SubStoreController.php` - Sub-store dashboard
- `app/Http/Controllers/Api/DataControllerOptimized.php` - Operator dashboard
- `app/Services/MLMerchantRecommendationService.php` - ML recommendation service
- `app/Console/Commands/MerchantRecommendationCommand.php` - Artisan command
- `backend/server.py` - FastAPI (AI Reports, ML endpoints, recommendation API)
- `ml_models/train_merchant_recommender.py` - LightGBM Ranker training pipeline
- `ml_models/predict_merchant.py` - Inference engine
- `resources/views/sub-stores/dashboard.blade.php` - Sub-store dashboard UI

## DB Schema (Key Tables)
- `stores`: store_id, store_name, store_type, is_sub_store
- `partner`: partner_id, partner_name, partner_category_id (merchants)
- `partner_category`: categories (Restaurants, Sport, Mode, etc.)
- `promotion`: partner_id, promotion_title, promotion_discount
- `history`: client_id, promotion_id (redemption history)
- `client`: client_id, sub_store, client_gender, client_age
- `client_abonnement`: subscriptions
- `cp_user_merchant_history`: pre-computed user-merchant interaction features
- `cp_merchants_catalog`: enriched merchant catalog for ML
- `cp_user_profile`: aggregated user features for ML
- `cp_user_offer_interactions`: feedback tracking

## Pending / Backlog
- P2: Performance optimization (query caching, index tuning)
- P2: AI reporting enrichment with merchant recommendations
- P3: Frontend dashboard for merchant recommendations visualization

## Admin Credentials
- SuperAdmin: superadmin@ooredoo.tn / SuperAdmin@2025
