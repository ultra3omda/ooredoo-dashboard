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
- Category distribution UTILISATION fix

### ML Recommendation Engine v2.0 — AWS Personalize-inspired (DONE)
**Recommendation Types:**
- DISCOVERY: Never visited, predicted high potential
- RE_ENGAGEMENT: Visited before but not recently (>30 days)
- LOYALTY: Frequently visited merchant
- TRENDING: Popular with many active promotions

**Key Features:**
- "Because you visited X" contextual linking
- Collaborative signal ("X clients with similar preferences...")
- Exploration/Exploitation (15% exploration weight)
- Score normalization 0-100
- Detailed explanations (summary, factors, details, model_type)
- Cold-start fallback (popularity-based)
- Batch scoring (no N+1 queries)
- Training optimized: numpy sampling

### P2: Client-Facing Recommendation Widget (DONE)
- GET /api/merchant-recommendations/widget/{client_id} — Lightweight JSON for mobile/web
- GET /api/merchant-recommendations/widget/{client_id}/html — Embeddable HTML widget
- Fields: id, name, category, score, type, reason, promos, discount, visited, visits

### Merchant Intelligence Engine (DONE)
- GET /api/merchant-intelligence/analyze — Traffic patterns, anomaly detection, health scores
- GET /api/merchant-intelligence/digest — Boost/Watch/Performers classification
- POST /api/merchant-intelligence/report — Gemini AI-powered commercial recommendations
- GET /api/merchant-intelligence/report/html — Full HTML intelligence report
- Health scoring: activity (40pts) + trend (30pts) + consistency (30pts) = 0-100
- Status classification: PERFORMANT (70+), A_SURVEILLER (40-70), A_BOOSTER (<40)
- Gemini generates: executive summary, boost recommendations with P0/P1/P2 priorities, promo strategies, digital strategies, success patterns

### Weekly Reports with Intelligence Integration (DONE)
- gatherMerchantIntelligenceData() calls FastAPI digest endpoint
- Intelligence data injected into CEO, Marketing, Associe report prompts
- AI suggestions now include merchant boost/watch/performer insights

**All Endpoints:**
- POST /api/merchant-recommendations — Personalized reco
- GET /api/merchant-recommendations/explain/{client_id} — HTML visual report
- GET /api/merchant-recommendations/health — Model status
- POST /api/merchant-recommendations/retrain — Synchronous retraining
- GET /api/merchant-recommendations/stats — Usage statistics
- POST /api/merchant-recommendations/track — Interaction tracking
- GET /api/merchant-recommendations/categories — Category list
- GET /api/merchant-recommendations/widget/{client_id} — Widget JSON
- GET /api/merchant-recommendations/widget/{client_id}/html — Widget HTML
- GET /api/merchant-intelligence/analyze — Traffic analysis
- GET /api/merchant-intelligence/digest — Boost/Watch digest
- POST /api/merchant-intelligence/report — AI report
- GET /api/merchant-intelligence/report/html — HTML report

### Test Reports
- iteration_22: 20/20 ML API tests
- iteration_23: 37/37 AWS Personalize features + regression (100%)
- iteration_24: 18/18 Widget P2 + Intelligence + regression (100%)

## Pending / Backlog
- P3: Dashboard Analytics Temporel (Chart.js 30/60/90 days tracking recommendation interactions)
- P3: A/B Test Framework (ML Model vs Popularity to measure uplift in redemption rates)

## Admin Credentials
- SuperAdmin: superadmin@ooredoo.tn / SuperAdmin@2025
