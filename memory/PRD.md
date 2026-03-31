# Club Privileges Dashboard - PRD

## Original Problem Statement
High-performance Laravel 10 dashboard for Club Privileges loyalty program. ML-powered predictive recommendations inspired by AWS Personalize.

## Tech Stack
- **Backend**: Laravel 10, FastAPI (Python), Redis Cache
- **Database**: MySQL (remote)
- **ML**: LightGBM LambdaRank + Exploration/Exploitation (28 features)
- **Frontend**: Blade templates, Vanilla JS, Chart.js

## Implemented Features

### Core Dashboard (DONE)
- Main + Sub-store dashboards, KPIs, merchants, subscriptions, period comparison, export
- Pluxee campaign filtering (16 methods with `applyPluxeeCampaignFilter`)
- Default date: 365 days
- Category distribution UTILISATION fix

### ML Recommendation Engine v2.0 — AWS Personalize-inspired (DONE)
**Recommendation Types:**
- DISCOVERY: Never visited, predicted high potential
- RE_ENGAGEMENT: Visited before but not recently (>30 days)
- LOYALTY: Frequently visited merchant
- TRENDING: Popular with many active promotions

**Key Features:**
- "Because you visited X" — contextual linking to past visits (same category)
- Collaborative signal — "X clients with similar preferences also visit this merchant"
- Exploration/Exploitation (15% exploration weight for undiscovered merchants)
- Score normalization 0-100
- Detailed explanations (summary, factors, details, model_type)
- Cold-start fallback (popularity-based for new users)
- Batch scoring (no N+1 queries)
- Training optimized: numpy sampling

**Endpoints:**
- POST /api/merchant-recommendations — Personalized reco
- GET /api/merchant-recommendations/explain/{client_id} — HTML visual report
- GET /api/merchant-recommendations/health — Model status
- POST /api/merchant-recommendations/retrain — Synchronous retraining
- GET /api/merchant-recommendations/stats — Usage statistics
- POST /api/merchant-recommendations/track — Interaction tracking
- GET /api/merchant-recommendations/categories — Category list

**Dashboards:**
- Admin: /admin/merchant-recommendations (search, KPIs, retrain, link to HTML report)
- Sub-Store: Recommendations tab (client search, top 10 popular, interaction chart)
- Both display: type badges, scores /100, "parce que", collaborative signal

### Test Reports
- iteration_22: 20/20 ML API tests
- iteration_23: 37/37 AWS Personalize features + regression (100%)

## Pending / Backlog
- P2: Client-facing widget for mobile app/web
- P3: Dashboard Analytics Temporel (Chart.js 30/60/90 days)
- P3: A/B testing framework ML vs Popularity

## Admin Credentials
- SuperAdmin: superadmin@ooredoo.tn / SuperAdmin@2025
