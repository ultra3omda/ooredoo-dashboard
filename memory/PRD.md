# Club Privileges Dashboard - PRD

## Original Problem Statement
High-performance Laravel 10 dashboard for Club Privileges loyalty program. Includes mathematically accurate stats, automated AI weekly reporting, ML-powered predictive dashboard, B2B sub-store campaign management, and a merchant recommendation engine.

## Tech Stack
- **Backend**: Laravel 10 (PHP 8.2), FastAPI (Python), Redis Cache
- **Database**: MySQL (remote: 51.38.187.245:3306 / clubprivileges)
- **ML**: LightGBM (Churn + Merchant Ranker), scikit-learn
- **AI**: OpenAI GPT-4o via litellm (Emergent LLM Key)
- **Frontend**: Blade templates, Vanilla JS, Chart.js
- **Cache**: Redis (51.38.187.245:7905, CACHE_DRIVER=redis)

## What's Been Implemented

### Core Dashboard (DONE)
- Main + Sub-store dashboards with KPIs, merchants, subscriptions, period comparison, export

### ML Pipeline v1 - Churn Prediction (DONE)
- Feature extraction, LightGBM training, A/B testing, ML Dashboard UI

### AI Weekly Reporting (DONE)
- Automated reports, multiple profiles, ML + merchant reco enrichment
- Email templates enriched (CEO + Marketing) with top marchands, categories tendances

### Pluxee B2B Campaign Support (DONE)
- Campaign dropdown when selecting "Club Privileges By Pluxee" sub-store (3 campaigns)
- Bypass carte_recharge_client, dedicated user access, admin UI

### Sub-Store Dashboard Refactoring (DONE)
- 5 split endpoints with Promise.allSettled
- 8 Merchant KPIs fully implemented
- Redis caching: CACHE_DRIVER=redis -> 14x faster (4651ms -> 320ms)

### ML Merchant Recommendation Engine (DONE)
- LightGBM Ranker (139K samples, NDCG@5=1.0, 576 marchands, 19K profils)
- FastAPI: /api/merchant-recommendations (POST recommend, GET health, POST track, GET stats, POST retrain, GET retrain/status, GET stats/timeline, GET categories)
- Laravel service + Artisan command + Admin dashboard at /admin/merchant-recommendations
- Feedback loop with interaction tracking + weekly retrain

### Recommendations Widget in Sub-Store Dashboard (DONE)
- New "Recommandations" tab (5th tab) in sub-store dashboard
- KPIs: Model status, active merchants, profiled users, interactions
- Client search with ML Model/Popularity source tags
- Top 10 popular merchants panel with ranked cards
- Category dropdown filter (11 categories)
- Interaction evolution chart (Chart.js, stacked bar, 30 days)

### DB Performance Optimization (DONE)
- 12 new indexes on critical tables (history, client, promotion, partner, carte_recharge, stores)

### Email Reporting Enrichment (DONE)
- CEO template: merchant_reco section with KPIs grid, top 5 marchands table, categories tendances
- Marketing template: insights marchands section with KPIs, top 10 marchands, categories

### Fix Retrain Button 504 Timeout (DONE - 2026-04-02)
- JS calls FastAPI directly instead of Laravel proxy (bypass CSRF + timeout)
- Retrain runs async in background thread (asyncio.to_thread)
- New polling endpoint GET /api/merchant-recommendations/retrain/status
- UI shows real-time progress with polling every 5s

## Test Reports
- iteration_18: ML recommendation API (18/18)
- iteration_19: Merchant reco dashboard (100%)
- iteration_20: Merchant KPIs + Redis + Pluxee campaigns (12/12 + 100%)
- iteration_21: Indexes + Reco widget + Timeline + Email enrichment (90% backend + 100% frontend)

## Pending / Backlog
- P2: Optimize ML queries in predict_merchant.py (batch scoring instead of N+1)
- P2: Client-facing recommendation widget for end users (mobile app/web)
- P3: Dashboard Analytics Temporel (Chart.js graphs 30/60/90 days)
- P3: A/B testing framework ML vs Popularity recommendations

## Admin Credentials
- SuperAdmin: superadmin@ooredoo.tn / SuperAdmin@2025
