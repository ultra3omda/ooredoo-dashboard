# Club Privileges Dashboard - PRD

## Problem Statement
Dashboard haute performance Laravel pour Ooredoo Club Privileges. KPIs mathematiquement exacts, reporting automatise, UI responsive Light/Dark, pipeline ML predictif.

## Architecture
- **Backend**: Laravel 10 + PHP-FPM + Nginx (port 8002) + Redis
- **Frontend**: Vanilla JS + Blade templates
- **ML**: Python (scikit-learn, LightGBM, pandas, pymysql) via async PHP CLI workers
- **AI Report**: GPT-4o via Emergent LLM Key (emergentintegrations)
- **DB**: MySQL remote (51.38.187.245) + Redis cache local
- **Proxy**: FastAPI on port 8001 proxies to PHP-FPM on port 8002

## Completed Features
### Core Dashboard
- KPI math corrigee (Conversion, Retention, Churn, Billing Rates)
- Date shortcuts (3M, 6M, 12M), Timwe 30-day trial display
- Merchant KPIs, option "Tous" pagination
- Retention Rate fix, Ooredoo Billing Rate historique fix
- Light/Dark mode theming

### ML Pipeline
- Async background worker (async_worker.php)
- Batch extraction optimisee (~1000x : 23k clients en 70s vs 19h)
- LightGBM training (Accuracy 100%, AUC ROC 100%)
- A/B Testing framework
- ML Insights Widget on main Overview (real data)

### Weekly Reports (6 types)
- **CEO** : Rapport strategique complet + ML predictions (churn, segments) + AI suggestions
- **Marketing** : Acquisition, Retention, Ciblage ML (segments cibles campagnes) + AI suggestions
- **Partner** : Transactions individuelles, top offres + AI suggestions
- **Associe** : Performance reseau, financier, top categories + ML insights + AI suggestions
- **Store** : Performance point de vente, affluence horaire, daily transactions + ML insights + AI suggestions
- **Sub-Store** : Meme que Store, scoped au sous-point de vente
- CRUD destinataires complet (add, edit, delete, toggle)
- Envoi automatique programme (chaque lundi)
- Preview avec generation AI en temps reel
- Templates PDF premium avec sections ML integrees

### AI Report Generation (ML Dashboard)
- Generation rapport IA via GPT-4o (emergentintegrations)
- Section dediee dans ML Dashboard avec viewer integre
- Rendu structuré : KPIs, alertes, recommandations, modele ML

## [2026-03-31] Changes
- Fixed pymysql + SQL column mismatch + permissions
- Batch extraction optimization (1000x speedup)
- Added 3 new report types (associe, store, sub-store) with templates
- Enhanced CEO and Marketing PDF templates with ML Predictions sections
- Enhanced AI prompts with ML context per profile
- Updated frontend dropdowns and forms for 6 types
- Database ENUM column updated for new types
- AI Report generation button + viewer in ML Dashboard
- E2E ML Pipeline verified (Extraction -> Training -> A/B Test -> Report)

## Key Files
- `app/Services/Dashboard/KPIService.php` - FRAGILE math
- `app/Services/WeeklyReportService.php` - 6 report types + ML data
- `app/Services/MLFeatureExtractionService.php` - Batch extraction v2.0
- `app/Http/Controllers/Api/ReportController.php` - Report CRUD + preview
- `app/Http/Controllers/Admin/MLDashboardController.php` - ML Dashboard
- `ml_models/async_worker.php` - Background worker (extract, train, report)
- `ml_models/generate_report.py` - AI report via GPT-4o
- `resources/views/reports/pdf/*.blade.php` - 5 PDF templates (store shared with sub-store)
- `public/js/dashboard/reporting.js` - Frontend reporting module
- `backend/server.py` - FastAPI proxy + AI suggestions endpoint

## Backlog
### P2
- Export rapports PDF (download from UI)
- Comparaison historique des rapports
- Envoi par email automatique du rapport IA ML Dashboard

## Credentials
- Login: superadmin@ooredoo.tn / SuperAdmin@2025
- DB: looker_user @ 51.38.187.245:3306 / clubprivileges
