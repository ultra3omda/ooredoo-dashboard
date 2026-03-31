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

### ML Pipeline v1 - Churn Prediction (DONE)
- Feature extraction, LightGBM training, A/B testing, ML Dashboard UI

### AI Weekly Reporting (DONE)
- Automated reports, multiple profiles, ML + merchant reco enrichment

### Pluxee B2B Campaign Support (DONE)
- Campaign dropdown with filtering per campaign via carte_recharge chain
- `applyPluxeeCampaignFilter` applied to all 16 Pluxee methods
- Distribué: uses `card_generated_number` from `carte_recharge` when campaign selected
- Other KPIs: filters clients via `carte_recharge_client → carte_recharge_code → carte_recharge`

### Sub-Store Dashboard Refactoring (DONE)
- 5 split endpoints with Promise.allSettled
- 8 Merchant KPIs fully implemented
- Redis caching: CACHE_DRIVER=redis -> 14x faster

### ML Merchant Recommendation Engine (DONE - Optimized 2026-04-02)
- LightGBM LambdaRank Ranker with 28 features
- FastAPI endpoints with detailed score explanations
- Batch scoring (N+1 queries eliminated → single batch prediction)
- Training optimized: numpy sampling replaces CROSS JOIN + ORDER BY RAND() (~10x faster)
- User context returned (profile summary, loyalty score, etc.)
- Score normalization 0-100 for readability
- Detailed explanation per recommendation (factors, interpretation, model type)
- Cold-start fallback for new users (popularity-based)

### Bug Fixes (2026-04-02)
- Category distribution UTILISATION=0: frontend read `utilizations` but backend sent `transactions`
- Campaign selection not updating data: added `campaign` param to all 5 API split endpoints
- Default date changed from 30 days to 365 days (1 year)
- Retrain 504 timeout: JS calls FastAPI directly, synchronous with 600s timeout

## ML Recommendation - Score Explanation

### What the score means:
- **Score brut**: Output of LightGBM LambdaRank model. Higher = more relevant for this specific user
- **Score normalisé (0-100)**: Relative ranking among all active merchants. 100 = best match, 0 = least match
- **Source**: `ml_model` = personalized ML prediction, `fallback_popularity` = popular merchants (new users)

### 28 Features used by the model:
1. **User-Merchant interaction** (6): visit_count, unique_promotions_used, days_since_last_visit, avg_days_between_visits, recency_score, frequency_score
2. **User profile** (10): total_visits, unique_merchants, unique_categories, days_since_last_activity, avg_visits, category_diversity, loyalty_score, subscription_tier, gender, age
3. **Merchant characteristics** (11): active/total promotions, avg/max discount, total_visits, unique_visitors, popularity_score, avg_visits_per_user, is_featured, is_premium, location_count
4. **Cross-feature** (1): same_fav_category (user's favorite category matches merchant)

### Why merchant X is recommended for client Y:
The model learns patterns from 139K+ training samples:
- Positive samples: real user-merchant visits (relevance 1-4 based on visit frequency)
- Negative samples: random user-merchant pairs with no interaction (relevance 0)
- The model learns which combinations of user profile + merchant characteristics predict future visits

## Test Results (2026-04-02):
- Client 118580 (555 visites, 252 marchands, premium): ML model correctly recommends frequently visited merchants with high scores
- Client 130212 (137 visites, 1 marchand, Femme): ML recommends her single visited merchant #1, then diversification in same category
- Client 49949 (115 visites, 12 marchands, diversifié): Mix of familiar merchants and category-matched discoveries
- Client 49949 with exclude_visited: New merchants prioritized by category match and promotions

## Pending / Backlog
- P2: Client-facing recommendation widget for end users (mobile app/web)
- P3: Dashboard Analytics Temporel (Chart.js graphs 30/60/90 days)
- P3: A/B testing framework ML vs Popularity recommendations

## Admin Credentials
- SuperAdmin: superadmin@ooredoo.tn / SuperAdmin@2025
