# Club Privileges Dashboard - PRD

## Problem Statement
Dashboard haute performance Laravel pour Ooredoo Club Privileges. KPIs mathematiquement exacts, reporting automatise, UI responsive Light/Dark, pipeline ML predictif.

## Architecture
- **Backend**: Laravel 10 + PHP-FPM + Nginx (port 8002) + Redis
- **Frontend**: Vanilla JS + Blade templates
- **ML**: Python (scikit-learn, LightGBM, pandas, pymysql) via async PHP CLI workers
- **AI Report**: GPT-4o via Emergent LLM Key (emergentintegrations)
- **DB**: MySQL remote (51.38.187.245) + Redis cache local

## Completed Features
- KPI math corrigee (Conversion, Retention, Churn, Billing Rates)
- Date shortcuts (3M, 6M, 12M) custom date picker
- Timwe 30-day trial display (0 TND via ppid check)
- Merchant KPIs sans emojis, option "Tous" pagination
- Retention Rate fix, Ooredoo Billing Rate historique fix
- ML Pipeline Async (MLAsyncTaskService + async_worker.php)
- ML Overview widget on main dashboard tab (real data connected)
- **[2026-03-30] Bug Fix: pymysql + SQL Column Mismatch** - Fixed Python path, permissions, and getDefaultFeatures
- **[2026-03-31] Batch Extraction Optimization** - Reduced from ~13 queries/client to ~3 queries/500 clients. 23,595 clients extracted in ~70 seconds (was ~19 hours). ~1000x speedup.
- **[2026-03-31] AI Weekly Report** - GPT-4o generates structured weekly reports with KPIs, alerts, recommendations. Async background generation via generate_report.py + emergentintegrations.
- **[2026-03-31] Report UI** - "Generer Rapport IA" button + "Dernier Rapport IA" section in ML Dashboard with full rendering (KPIs, alertes, recommandations)
- **[2026-03-31] E2E ML Pipeline Verified** - Extraction (70s) -> Training (50s) -> A/B Test -> Report Generation all working end-to-end

## Verified ML Results
- Accuracy: 100%, AUC ROC: 100%, 80k samples
- Extraction: 23,595 clients in ~70 seconds (batch mode)
- AI Report: Structured JSON with KPIs, segments, alertes, recommandations

## Key Files
- `app/Services/Dashboard/KPIService.php` - FRAGILE math
- `app/Services/MLAsyncTaskService.php` - Task management
- `app/Services/MLFeatureExtractionService.php` - Batch extraction v2.0
- `ml_models/async_worker.php` - Background worker (extract, train, report)
- `ml_models/generate_report.py` - AI report generation with GPT-4o
- `ml_models/train_model.py` - LightGBM training
- `public/js/dashboard/main.js` - Frontend + ML widget
- `resources/views/admin/ml-dashboard.blade.php` - ML Dashboard UI

## Backlog
### P2
- Automated weekly scheduling (Laravel scheduler cron job)
- Historical report comparison
- Export reports as PDF

## Credentials
- Login: superadmin@ooredoo.tn / SuperAdmin@2025
- DB: looker_user @ 51.38.187.245:3306 / clubprivileges
