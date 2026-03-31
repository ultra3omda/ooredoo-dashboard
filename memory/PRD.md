# Club Privileges Dashboard - PRD

## Original Problem Statement
High-performance Laravel 10 dashboard for Club Privileges loyalty program. Includes mathematically accurate stats, automated AI weekly reporting, ML-powered predictive dashboard, B2B sub-store campaign management, and a merchant recommendation engine.

## Tech Stack
- **Backend**: Laravel 10 (PHP 8.2), FastAPI (Python), Redis Cache
- **Database**: MySQL (remote: 51.38.187.245:3306 / clubprivileges)
- **ML**: LightGBM (Churn + Merchant Ranker), scikit-learn, numpy
- **AI**: OpenAI GPT-4o via litellm (Emergent LLM Key)
- **Frontend**: Blade templates, Vanilla JS, Chart.js
- **Cache**: Redis (51.38.187.245:7905, CACHE_DRIVER=redis)

## What's Been Implemented

### Core Dashboard (DONE)
- Main + Sub-store dashboards with KPIs, merchants, subscriptions, period comparison, export

### ML Merchant Recommendation Engine (DONE - Optimized 2026-04-02)
- LightGBM LambdaRank Ranker with 28 features
- Batch scoring (N+1 queries eliminated)
- Training optimized: numpy sampling replaces CROSS JOIN + ORDER BY RAND()
- Score normalization 0-100 for readability
- Detailed explanation per recommendation (summary, factors, details, model_type)
- User context in response (profile summary, loyalty, visits)
- Cold-start fallback with normalized popularity scores
- Filters: exclude_visited, category_id
- Admin dashboard + Sub-Store widget with explanations displayed
- API tested: 20/20 tests passed (iteration_22)

### Pluxee Campaign Filtering (DONE - 2026-04-02)
- `applyPluxeeCampaignFilter` applied to all 16 Pluxee methods
- Campaign parameter flows through all 5 split API endpoints
- Cache keys include campaign for proper invalidation

### Bug Fixes (2026-04-02)
- Category distribution UTILISATION=0: frontend read `utilizations` → fixed to `transactions`
- Campaign selection not updating data: campaign param added to all APIs
- Default date: 30 days → 365 days
- Retrain 504 timeout: synchronous FastAPI, 600s timeout
- Score display: raw score → normalized 0-100 in both dashboards
- Fallback scores: were all 0 → now properly distributed 0-100

## ML Recommendation - Score Explanation
### Score:
- **Normalisé (0-100)**: Position relative. 100 = meilleure correspondance, 0 = plus faible
- **Source**: `ml_model` = ML personnalisé, `fallback_popularity` = popularité (nouveaux clients)

### 28 Features:
1. User-Merchant (6): visit_count, unique_promotions, recency, frequency, days_since_last
2. User Profile (10): total_visits, unique_merchants/categories, loyalty, subscription, gender, age
3. Merchant (11): promotions, discount, popularity, visits, premium, featured, locations
4. Cross (1): same_fav_category

### Explanation object:
- `summary`: interpretation textuelle du score
- `factors`: liste des facteurs clés (historique, catégorie, promos, popularité)
- `details`: précisions sur les boosts de score
- `score_interpretation`: explication technique du score brut vs normalisé
- `model_type`: LightGBM LambdaRank

## Test Reports
- iteration_22: Merchant Recommendations API 20/20 (100%)

## Pending / Backlog
- P2: Client-facing recommendation widget for end users (mobile app/web)
- P3: Dashboard Analytics Temporel (Chart.js graphs 30/60/90 days)
- P3: A/B testing framework ML vs Popularity recommendations

## Admin Credentials
- SuperAdmin: superadmin@ooredoo.tn / SuperAdmin@2025
