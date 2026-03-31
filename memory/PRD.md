# Club Privileges Dashboard - PRD

## Original Problem Statement
High-performance Laravel 10 dashboard for Club Privileges loyalty program. ML-powered predictive recommendations inspired by AWS Personalize. Merchant Intelligence powered by Gemini AI. Digital Presence Scoring with real web scraping.

## Tech Stack
- **Backend**: Laravel 10, FastAPI (Python), Redis Cache
- **Database**: MySQL (remote)
- **ML**: LightGBM LambdaRank + Exploration/Exploitation (28 features)
- **AI**: Gemini 2.5 Flash (Emergent LLM Key) for Merchant Intelligence + Digital Audit
- **Scraping**: httpx + BeautifulSoup (real web/social scraping)
- **Frontend**: Blade templates, Vanilla JS, Chart.js

## Implemented Features

### Core Dashboard (DONE)
- Main + Sub-store dashboards, KPIs, merchants, subscriptions, period comparison, export
- Pluxee campaign filtering (16 methods with `applyPluxeeCampaignFilter`)

### ML Recommendation Engine v2.0 — AWS Personalize-inspired (DONE)
- Types: DISCOVERY, RE_ENGAGEMENT, LOYALTY, TRENDING
- "Because you visited X" contextual linking, Collaborative signals
- Score normalization 0-100, Cold-start fallback

### P2: Client-Facing Recommendation Widget (DONE)
- GET /api/merchant-recommendations/widget/{client_id} — JSON + HTML

### Merchant Intelligence Engine (DONE)
- Analyze, Digest, Report (Gemini AI), Report HTML, Weekly Email Preview

### P3: Analytics Temporel (DONE)
- Timeline 30/60/90 days with Chart.js, Source breakdown, Category doughnut

### P3: A/B Test Framework (DONE)
- ML Model vs Popularity, Deterministic MD5 hash assignment
- Uplift metrics: CTR, Conversion Rate, Winner detection

### Digital Presence Scoring (DONE - NEW)
- **Real web scraping** of merchant websites, Facebook, Instagram, Google
- Score 0-100 with breakdown: Website (30pts), Facebook (25pts), Instagram (25pts), Google (20pts)
- Levels: EXCELLENT (70+), BON (50-69), MOYEN (30-49), FAIBLE (<30)
- Per-merchant AI audit via Gemini with:
  - Diagnostic, Points forts/faibles
  - Prioritized recommendations (P0/P1/P2) per canal
  - Score potentiel, Strategie de contenu
- Dashboard admin: Scanner button, KPI cards, sortable table, modal audit overlay
- HTML report standalone page

### Weekly Reports with Intelligence Integration (DONE)
- gatherMerchantIntelligenceData() calls FastAPI digest endpoint
- Intelligence data injected into CEO, Marketing, Associe report prompts

## All API Endpoints
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
- GET /api/merchant-intelligence/digital-scores?limit=N
- GET /api/merchant-intelligence/digital-score/{partner_id}
- POST /api/merchant-intelligence/digital-audit/{partner_id}
- GET /api/merchant-intelligence/digital-scores/html?limit=N
- /{path:path} — Proxy catch-all to Laravel (MUST be last)

### Test Reports
- iteration_22: 20/20 ML API tests (100%)
- iteration_23: 37/37 AWS Personalize features (100%)
- iteration_24: 18/18 Widget P2 + Intelligence (100%)
- iteration_25: 23/23 Analytics Temporel + A/B Test + Email Preview (100%)
- iteration_26: 10/10 Digital Scoring + scraping + Gemini audit (100%)

## Admin Credentials
- SuperAdmin: superadmin@ooredoo.tn / SuperAdmin@2025
