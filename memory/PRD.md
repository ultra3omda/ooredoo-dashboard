# Club Privileges Dashboard - PRD

## Original Problem Statement
High-performance Laravel 10 dashboard for Club Privileges loyalty program. ML-powered predictive recommendations inspired by AWS Personalize. Merchant Intelligence powered by Gemini AI.

## Tech Stack
- **Backend**: Laravel 10, FastAPI (Python), Redis Cache
- **Database**: MySQL (remote)
- **ML**: LightGBM LambdaRank + Exploration/Exploitation (28 features)
- **AI**: Gemini 2.5 Flash (Emergent LLM Key) for Merchant Intelligence, GPT-4o for AI Suggestions
- **Frontend**: Blade templates, Vanilla JS, Chart.js

## Implemented Features

### Core Dashboard (DONE)
- Main + Sub-store dashboards, KPIs, merchants, subscriptions, period comparison, export
- Pluxee campaign filtering (16 methods with `applyPluxeeCampaignFilter`)
- Default date: 365 days

### ML Recommendation Engine v2.0 — AWS Personalize-inspired (DONE)
- Types: DISCOVERY, RE_ENGAGEMENT, LOYALTY, TRENDING
- "Because you visited X" contextual linking
- Collaborative signal, Exploration/Exploitation (15%)
- Score normalization 0-100, Cold-start fallback

### P2: Client-Facing Recommendation Widget (DONE)
- GET /api/merchant-recommendations/widget/{client_id} — JSON
- GET /api/merchant-recommendations/widget/{client_id}/html — Embeddable HTML

### Merchant Intelligence Engine (DONE)
- GET /api/merchant-intelligence/analyze — Traffic analysis + anomaly detection
- GET /api/merchant-intelligence/digest — Boost/Watch/Performers classification
- POST /api/merchant-intelligence/report — Gemini AI commercial recommendations
- GET /api/merchant-intelligence/report/html — Full HTML intelligence report
- GET /api/merchant-intelligence/weekly-email-preview — Preview email hebdomadaire

### P3: Analytics Temporel (DONE)
- GET /api/merchant-recommendations/stats/timeline?days=30|60|90
- Chart.js line chart (interactions/jour par type) + doughnut (categories)
- Source breakdown (ML vs organic vs A/B)
- Dashboard: period selector (30/60/90j), real-time Chart.js rendering

### P3: A/B Test Framework — ML vs Popularity (DONE)
- GET /api/merchant-recommendations/ab-test/{client_id} — Serve reco via A/B
- GET /api/merchant-recommendations/ab-test/results — Uplift metrics
- Deterministic assignment via MD5 hash (50/50 split)
- Tracks impressions, clicks, redeems per group
- Calculates CTR, conversion rate, uplift, winner
- Dashboard: KPI cards (winner, uplift CTR, uplift conversion), comparison table

### Weekly Reports with Intelligence Integration (DONE)
- gatherMerchantIntelligenceData() calls FastAPI digest endpoint
- Intelligence data injected into CEO, Marketing, Associe report prompts
- AI suggestions include merchant boost/watch/performer insights

### Admin Dashboard — Merchant Recommendations (DONE)
- KPIs: model status, active merchants, profiled users, interactions
- Personalized recommendations search with user context
- Popular merchants panel
- Interaction stats table (7 days)
- Model performance (NDCG@5, NDCG@10, top features)
- Analytics Temporel (Chart.js 30/60/90 days)
- A/B Test results panel
- Intelligence Marchands digest + links to full report / email preview

### Test Reports
- iteration_22: 20/20 ML API tests
- iteration_23: 37/37 AWS Personalize features + regression (100%)
- iteration_24: 18/18 Widget P2 + Intelligence + regression (100%)
- iteration_25: 23/23 Analytics Temporel + A/B Test + Email Preview + regression (100%)

## All API Endpoints
- POST /api/report-ai-suggestions
- POST /api/merchant-recommendations
- GET /api/merchant-recommendations/explain/{client_id}
- GET /api/merchant-recommendations/health
- POST /api/merchant-recommendations/retrain
- POST /api/merchant-recommendations/track
- GET /api/merchant-recommendations/stats
- GET /api/merchant-recommendations/stats/timeline?days=30|60|90
- GET /api/merchant-recommendations/categories
- GET /api/merchant-recommendations/widget/{client_id}
- GET /api/merchant-recommendations/widget/{client_id}/html
- GET /api/merchant-recommendations/ab-test/results
- GET /api/merchant-recommendations/ab-test/{client_id}
- GET /api/merchant-intelligence/analyze
- GET /api/merchant-intelligence/digest
- POST /api/merchant-intelligence/report
- GET /api/merchant-intelligence/report/html
- GET /api/merchant-intelligence/weekly-email-preview
- /{path:path} — Proxy catch-all to Laravel (MUST be last)

## Admin Credentials
- SuperAdmin: superadmin@ooredoo.tn / SuperAdmin@2025
