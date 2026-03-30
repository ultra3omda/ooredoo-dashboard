# Club Privileges - Performance Dashboard

## Problem Statement
Deploy and optimize a Laravel 10 dashboard with mathematically accurate stats matching `clubprivileges.app`, automated weekly reporting, ML-powered predictions, and responsive UI with Light/Dark mode.

## Architecture
- **Backend**: Laravel 10 + PHP 8.2 FPM + Nginx (port 8002)
- **Frontend Proxy**: Node.js (port 3000) → PHP (port 8002)
- **Backend Proxy**: FastAPI (port 8001) → PHP (port 8002) + AI endpoints
- **DB**: External MySQL (51.38.187.245)
- **Cache**: File-based (Redis unreachable from container)
- **ML**: Python (scikit-learn, LightGBM) via Symfony Process

## Key Files
- `app/Services/Dashboard/KPIService.php` - Main KPI calculations
- `app/Services/Dashboard/SubscriptionService.php` - Subscription data
- `app/Services/Dashboard/TransactionService.php` - Transaction data
- `app/Services/Dashboard/MerchantService.php` - Merchant data
- `app/Http/Controllers/Api/DataControllerOptimized.php` - API endpoints
- `app/Http/Controllers/Admin/MLDashboardController.php` - ML Dashboard
- `resources/views/dashboard.blade.php` - Main dashboard view
- `public/js/dashboard/main.js` - Frontend JS
- `public/css/dashboard.css` - Dashboard CSS

## What's Been Implemented (Latest Session - March 30, 2026)

### Bug Fixes
1. **Conversion Rate 180% → 48%**: Changed formula from `transacting_users / active_subscriptions` to `transacting_users / users_with_active_sub_during_period` (queryRegisteredUsers)
2. **Retention Rate 101% → 96%**: Root cause was mismatch between materialized (dashboard_daily_stats) and direct (client_abonnement) data sources. Today's stats missing from materialized table caused denominator to be smaller than numerator. Fixed by using direct queries for both.
3. **Timwe Billing Rate 0% → 2.71%**: Was using last day's rate (today = 0 because incomplete). Now uses average of daily rates, filtering out days with rate=0.
4. **Trial 30j pricing**: Priority order fixed - ppid check before duration check, so free trials correctly show 0 TND.
5. **Churn Rate**: Now calculated as `(directActivated - stillActive) / directActivated * 100` for consistency.

### UI Fixes
6. **Emoji icons removed** from merchant KPI cards
7. **"Tous" pagination option** added to merchant ranking table
8. **Date shortcuts**: Added 3 mois, 6 mois, 12 mois (replaced "Ce mois" / "Mois dernier")

### ML Pipeline
9. **trainModel endpoint** added to MLDashboardController
10. **startABTest endpoint** added to MLDashboardController
11. ML model metrics display verified working

### Infrastructure (This Container)
- PHP 8.2 + FPM installed and configured
- Nginx configured on port 8002 for Laravel
- AlertService Redis error fixed (graceful fallback)
- Cache directories created for file driver

## Credentials
- Email: superadmin@ooredoo.tn
- Password: SuperAdmin@2025

## Prioritized Backlog
### P1
- Verify ML Pipeline UI Actions end-to-end (Extract → Train → A/B Test)
- Add ML Insights Widget to Overview Tab

### P2
- Refactor MLPythonBridgeService to use Laravel Queue/Jobs
- Historical Ooredoo billing rate data investigation (all showing 100%)

### P3
- Performance optimization for long-period queries
- Dashboard export/PDF functionality
