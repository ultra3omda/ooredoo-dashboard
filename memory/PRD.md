# Club Privileges - Performance Dashboard PRD

## Original Problem Statement
Deploy a high-performance Laravel dashboard, mathematically accurate stats matching `clubprivileges.app`, automated weekly AI reporting, robust navigation, fully responsive UI with Light/Dark mode theming.

## Architecture
- **Backend**: Laravel 10 + Nginx + PHP-FPM + Redis cache
- **Frontend**: Vanilla JS + Chart.js + Blade templates
- **AI Reporting**: FastAPI proxy (server.py) using Emergent LLM Key
- **DB**: MySQL with materialized views, daily stat tables

## Key DB Tables
- `client_abonnement`, `subscription_daily_stats`, `transaction_daily_stats`
- `ooredoo_daily_stats`, `eklektik_stats_daily`, `timwe_daily_stats`
- `report_recipients`, `report_logs`

## Completed Features (as of 2026-03-29)
- [x] Full dashboard with Overview, Subscriptions, Transactions, Merchants, Timwe, Ooredoo/DGV, Eklektik, Comparison, Reporting tabs
- [x] Mathematically accurate KPIs matching clubprivileges.app
- [x] Automated AI Weekly Reporting System
- [x] Mobile responsive layout (2 KPIs per row)
- [x] Redis caching for all heavy queries
- [x] Chart.js visualizations with Purple/Gold brand colors
- [x] **Light Mode as default** with Dark/Light toggle (localStorage persistence)
- [x] **Ooredoo/DGV billing rate** = average of daily rates (not total success/total attempts)
- [x] **Eklektik "Statistiques Quotidiennes"** expandable monthly/daily table (replaced "Statistiques par Opérateur")
- [x] **Profile dropdown** menu properly styled and responsive
- [x] **Subscription details modal** bug fix (clientId validation, JSON error handling)
- [x] **SubStore dashboard** updated with matching Light/Dark theme variables
- [x] Admin views (Users, Invitations) updated with brand colors

## Key API Endpoints
- `/api/dashboard/split/kpis` - KPIs
- `/api/dashboard/split/merchants` - Merchants
- `/api/dashboard/split/transactions` - Transactions
- `/api/dashboard/split/subscriptions` - Subscriptions
- `/api/dashboard/split/ooredoo` - Ooredoo/DGV stats
- `/api/dashboard/split/timwe` - Timwe stats
- `/api/dashboard/split/eklektik` - Eklektik daily stats (NEW)
- `/api/dashboard/subscriptions/{clientId}` - User subscription details

## Key Files
- `resources/views/dashboard.blade.php` - Main dashboard (CSS + HTML + JS)
- `public/js/dashboard/` - JS modules (charts, tables, eklektik, ooredoo, timwe, reporting, utils)
- `app/Services/Dashboard/KPIService.php` - KPI calculations
- `app/Services/Dashboard/StatisticsService.php` - Monthly stats grouping
- `app/Http/Controllers/Api/DataControllerOptimized.php` - API controllers
- `resources/views/sub-stores/dashboard_harmonized.blade.php` - SubStore dashboard
- `server.py` - FastAPI for AI reporting

## Business Rules
- Ooredoo/DGV billing rate = average of daily billing_rate percentages
- Merchants: activeMerchants <= totalPartners
- Taux de facturation displayed with 3 decimal places
- Theme persists via localStorage key 'dashboard-theme'

## Testing
- Test iterations 9, 10, 11 all passed
- Credentials: superadmin@ooredoo.tn / SuperAdmin@2025
