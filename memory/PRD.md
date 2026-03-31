# Club Privileges Dashboard - PRD

## Original Problem Statement
High-performance Laravel 10 dashboard for Club Privileges loyalty program. Includes mathematically accurate stats, automated AI weekly reporting, ML-powered predictive dashboard, B2B sub-store campaign management, and a merchant recommendation engine.

## Tech Stack
- **Backend**: Laravel 10 (PHP 8.2), FastAPI (Python), Redis Cache
- **Database**: MySQL (remote: 51.38.187.245:3306 / clubprivileges)
- **ML**: LightGBM (Churn + Merchant Ranker), scikit-learn
- **AI**: OpenAI GPT-4o via litellm (Emergent LLM Key)
- **Frontend**: Blade templates, Vanilla JS, Chart.js
- **Cache**: Redis (51.38.187.245:7905)

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
- Feature extraction, LightGBM training, A/B testing, ML Dashboard UI

### AI Weekly Reporting (DONE)
- Automated reports via FastAPI, multiple profiles, ML + merchant reco data enrichment

### Pluxee B2B Campaign Support (DONE)
- Pluxee campaigns bypass carte_recharge_client checks
- Dedicated user access control
- Admin UI: /admin/pluxee/users
- **Campaign dropdown**: When selecting "Club Privilèges By Pluxee" sub-store, a campaign selector appears showing 3 Pluxee campaigns (2026-04-02)

### Sub-Store Dashboard Refactoring (DONE)
- SubStoreController split into 5 endpoints with Promise.allSettled
- **8 Merchant KPIs** fully implemented (2026-04-02):
  - Total Merchants (576), Active Merchants (136, +7.9%)
  - Total Locations Active (549), Active Merchant Ratio (23.6%, +7.8%)
  - Total Transactions (2017), Transactions per Merchant (14.8)
  - Top Merchant Share (PATHÉ 18.6%), Diversity (Excellent 100)
- **Redis caching**: CACHE_DRIVER=redis → 14x faster (4651ms → 320ms)
- **Fixed**: `.nav-link.active` → `.nav-tab.active` selector bug preventing KPI updates
- **Fixed**: Diversity KPI displaying [object Object] → "Excellent (100)"

### ML Merchant Recommendation Engine (DONE)
- LightGBM Ranker (139K samples, NDCG@5=1.0)
- FastAPI endpoints: recommend, health, track, stats, retrain
- Laravel service + Artisan command + Dashboard at /admin/merchant-recommendations
- Feedback loop with interaction tracking + weekly retrain

## Key API Endpoints
- Sub-Store Split: /sub-stores/api/split/{kpis,stores,charts,merchants,users}
- ML Recommendations: /api/merchant-recommendations, /health, /track, /stats, /retrain
- Admin Dashboard: /admin/merchant-recommendations

## Test Reports
- iteration_18.json: ML recommendation API (18/18 passed)
- iteration_19.json: Merchant reco dashboard (100%)
- iteration_20.json: Merchant KPIs + Redis + Pluxee campaigns (12/12 + 100% frontend)

## Pending / Backlog
- P2: Performance optimization (index tuning, query optimization for slow sub-stores)
- P3: Client-facing recommendation widget
- P3: Temporal analytics charts for recommendation interactions

## Admin Credentials
- SuperAdmin: superadmin@ooredoo.tn / SuperAdmin@2025
