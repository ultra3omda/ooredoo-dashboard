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
- Bypass carte_recharge_client for global Pluxee stats, use it for campaign-specific filtering
- Dedicated user access, admin UI

### Sub-Store Dashboard Refactoring (DONE)
- 5 split endpoints with Promise.allSettled
- 8 Merchant KPIs fully implemented
- Redis caching: CACHE_DRIVER=redis -> 14x faster

### ML Merchant Recommendation Engine (DONE)
- LightGBM Ranker, FastAPI endpoints, Admin dashboard
- Retrain: synchronous endpoint with 600s timeout, calls FastAPI directly (no Laravel proxy)
- Training optimized: numpy sampling replaces CROSS JOIN + ORDER BY RAND() (~10x faster)

### Bug Fixes (2026-04-02)
- Category distribution UTILISATION=0: frontend read `utilizations` but backend sent `transactions`
- Campaign selection not updating data: added `campaign` param to all 5 API split endpoints + 16 Pluxee backend methods with `applyPluxeeCampaignFilter`
- Default date changed from 30 days to 365 days (1 year)
- Retrain 504 timeout: JS calls FastAPI directly, increased timeout to 600s

## KPI Definitions
- **Distribué**: Total cards/codes generated (campaign: sum of card_generated_number)
- **Inscriptions**: Distinct clients with active subscription
- **Cartes Utilisées**: Distinct clients with at least 1 transaction
- **Total Transactions (période)**: Transaction count within selected period
- **Répartition Catégories**: Transactions per merchant category with % of total

## Pending / Backlog
- P2: Client-facing recommendation widget for end users
- P3: Dashboard Analytics Temporel (Chart.js graphs 30/60/90 days)
- P3: A/B testing framework ML vs Popularity recommendations

## Admin Credentials
- SuperAdmin: superadmin@ooredoo.tn / SuperAdmin@2025
