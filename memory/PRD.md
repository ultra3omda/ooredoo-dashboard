# Club Privileges Dashboard - PRD

## Problem Statement
Dashboard haute performance Laravel pour Ooredoo Club Privileges. KPIs mathematiquement exacts, reporting automatise, UI responsive Light/Dark, pipeline ML predictif.

## Architecture
- **Backend**: Laravel 10 + PHP-FPM + Nginx (port 8002) + Redis
- **Frontend**: Vanilla JS + Blade templates
- **ML**: Python (scikit-learn, LightGBM, pandas, pymysql) via async PHP CLI workers
- **DB**: MySQL remote (51.38.187.245) + Redis cache local

## Completed Features
- KPI math corrigee (Conversion, Retention, Churn, Billing Rates)
- Date shortcuts (3M, 6M, 12M) custom date picker
- Timwe 30-day trial display (0 TND via ppid check)
- Merchant KPIs sans emojis
- Option "Tous" pagination merchants
- Retention Rate fix (mismatch materialized table vs direct query)
- Ooredoo Billing Rate historique fix (filter mock data pre-April 2025)
- ML Pipeline Async (MLAsyncTaskService + async_worker.php)
- ML Overview widget on main dashboard tab
- **[2026-03-30] Bug Fix: pymysql ModuleNotFoundError** - Fixed PYTHON_PATH and /root permissions for www-data background worker
- **[2026-03-30] Bug Fix: SQL Column Mismatch** - Added 9 advanced features to getDefaultFeatures() (morning/afternoon/evening_success_rate, recovery_after_failure_rate, max_consecutive_successes, payment_amount_std, amount_flexibility, no_balance_failure_rate, not_delivered_failure_rate)
- **[2026-03-30] Bug Fix: Cache permissions** - Fixed /app/storage/framework/cache/data permissions for background workers
- **[2026-03-30] Bug Fix: ML model save permissions** - Fixed /app/ml_models directory permissions for www-data

## Verified ML Training Results
- Accuracy: 99.99%, Precision: 100%, Recall: 99.97%, F1: 99.98%, AUC ROC: 100%
- 80,000 samples (64k train / 16k test)
- Top features: ooredoo_success_rate (436), timwe_success_rate (406), payment_success_rate (330)

## Backlog / Upcoming Tasks
### P1
- E2E ML Pipeline verification (Extraction complete -> Training -> A/B Test via UI)
- Extraction performance optimization (23k clients at 3s each is too slow)

### P2  
- Automated weekly AI reporting
- ML Insights widget data refresh from real model predictions

## Key Files
- `app/Services/Dashboard/KPIService.php` - FRAGILE math, do not alter without care
- `app/Services/MLAsyncTaskService.php` - Creates/tracks background tasks
- `app/Services/MLFeatureExtractionService.php` - Feature extraction + upsert
- `ml_models/async_worker.php` - CLI background worker
- `ml_models/train_model.py` - Python training script
- `public/js/dashboard/main.js` - Frontend polling + ML widget

## Credentials
- Login: superadmin@ooredoo.tn / SuperAdmin@2025
- DB: looker_user @ 51.38.187.245:3306 / clubprivileges
