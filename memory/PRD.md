# Club Privileges Dashboard - PRD

## Original Problem Statement
High-performance Laravel 10 dashboard for Club Privileges loyalty program. ML-powered predictive recommendations inspired by AWS Personalize. Merchant Intelligence powered by Gemini AI. Digital Presence Scoring with real web scraping. Role-based campaign access for collaborators.

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
- Score normalization 0-100, Cold-start fallback, Collaborative signals

### P2: Client-Facing Recommendation Widget (DONE)

### Merchant Intelligence Engine (DONE)
- Analyze, Digest, Report (Gemini AI), Report HTML, Weekly Email Preview

### P3: Analytics Temporel (DONE)
- Timeline 30/60/90 days with Chart.js

### P3: A/B Test Framework (DONE)
- ML Model vs Popularity, Uplift CTR/Conversion

### Digital Presence Scoring (DONE)
- Real web scraping (websites, Facebook, Instagram, Google)
- Score 0-100, AI audit via Gemini

### Collaborateur Role with Campaign Access Control (DONE - NEW)
**Architecture:**
- `pluxee_campaign_access` column: TEXT type storing JSON array (e.g., `["Campagne pilote Pluxee","test tarek"]`)
- NULL or empty = full access (can see all campaigns + can invite others)
- Non-empty = restricted (can only see assigned campaigns, cannot invite)

**User Model Methods:**
- `getAllowedCampaigns()`: Decodes JSON, returns array of campaign names
- `hasCampaignRestriction()`: Returns true if user has restricted access
- `canInviteCollaborators()`: SuperAdmin/Admin always true; Collaborator only if unrestricted

**Invitation Flow:**
- Form shows multi-campaign checkboxes when sub-store is selected
- Campaigns loaded dynamically via `/admin/invitations/campaigns?store_name=X`
- `campaign_access[]` stored in `invitations.additional_data` JSON
- On acceptance: `pluxee_campaign_access` set on user record

**Dashboard Enforcement:**
- `SubStoreController.normalizeSubStoreParams()`: Forces campaign filter for restricted users
- `getSubStores()` API: Returns `has_campaign_restriction`, `allowed_campaigns`, `can_invite`
- Campaign dropdown: Disabled/filtered for restricted users, shows all for admins

**Permission Matrix:**
| Role | See All Campaigns | Invite Others |
|---|---|---|
| Super Admin | Yes | Yes |
| Admin Sub-Store | Yes | Yes |
| Collaborator (no restriction) | Yes | Yes |
| Collaborator (with campaigns) | Only assigned | No |

### Weekly Reports with Intelligence Integration (DONE)

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
- iteration_27: 17/17 Campaign Restriction + regression (100%)

## Admin Credentials
- SuperAdmin: superadmin@ooredoo.tn / SuperAdmin@2025
