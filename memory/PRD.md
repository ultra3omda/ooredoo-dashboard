# Club Privileges - Performance Dashboard PRD

## Original Problem Statement
High-performance Laravel dashboard for Club Privileges with mathematically accurate stats, automated weekly reporting with AI suggestions, decoupled architecture, robust navigation, and fully responsive UI matching actual brand colors (purple/gold).

## Architecture
- Laravel 10 + Nginx/PHP-FPM + Redis + MySQL
- Frontend: Vanilla JS + Chart.js (modular in public/js/dashboard/)
- Backend: Laravel Services (Dashboard/, KPI, Merchant, Subscription)
- AI Reporting: Python FastAPI proxy (server.py) with Emergent LLM integration
- View: Single Blade template (resources/views/dashboard.blade.php)

## Key Files
- `resources/views/dashboard.blade.php` - Main UI/CSS/inline JS
- `public/js/dashboard/` - charts.js, tables.js, reporting.js, eklektik.js, utils.js
- `app/Services/Dashboard/` - KPIService, MerchantService, SubscriptionService, StatisticsService
- `app/Http/Controllers/Api/DataControllerOptimized.php` - Split API endpoints
- `server.py` - FastAPI AI proxy

## Completed Features (All tested)
- Club Privileges brand theme (Purple/Gold) applied
- KPI tooltips with mathematical accuracy
- Automated Weekly Reporting System (DB, Models, Artisan command, UI config, AI summaries)
- Inverse delta colors for negative KPIs (churn, unsubscriptions)
- Chart loading bugs fixed (no Blade directives in .js files)
- Mobile responsiveness: 2 KPIs per row across ALL tabs
- Merchant data logic: Total Merchants (all partners) >= Active Merchants
- Comparison tab: Period-over-Period table loads correctly
- Data tables: Subscriptions (140 items), Merchants (50 items) with pagination
- Reporting tab: Destinataires table loads, responsive layout

## Credentials
- Email: superadmin@ooredoo.tn
- Password: SuperAdmin@2025

## API Endpoints
- /api/dashboard/split/kpis
- /api/dashboard/split/merchants
- /api/dashboard/split/subscriptions
- /api/dashboard/split/comparison
- /api/reports/preview

## DB Schema
- report_recipients, report_logs, client_abonnement, subscription_daily_stats, transaction_daily_stats, partner, partner_location, history, promotion

## Known Constraints
- NEVER put Blade directives in .js files
- Use window._dashboardData to pass PHP data to JS
- Cache TTL is 1 hour (Redis). Clear with php artisan cache:clear + restart backend
- KPIService.php provides KPI values displayed in Merchants tab (not MerchantService)
