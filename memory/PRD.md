# Club Privileges - Performance Dashboard PRD

## Original Problem Statement
High-performance Laravel dashboard for Club Privileges with mathematically accurate stats, automated weekly reporting with AI suggestions, decoupled architecture, robust navigation, and fully responsive UI matching actual brand colors (purple/gold).

## Architecture
- Laravel 10 + Nginx/PHP-FPM + Redis/File Cache + MySQL
- Frontend: Vanilla JS + Chart.js (modular in public/js/dashboard/)
- Backend: Laravel Services (Dashboard/, KPI, Merchant, Subscription)
- AI Reporting: Python FastAPI proxy (server.py) with Emergent LLM integration
- View: Single Blade template (resources/views/dashboard.blade.php)
- Eklektik: Blade component (resources/views/components/eklektik-charts.blade.php)

## Key Files
- `resources/views/dashboard.blade.php` - Main UI/CSS/inline JS
- `resources/views/components/eklektik-charts.blade.php` - Eklektik charts component
- `public/js/dashboard/` - charts.js, tables.js, reporting.js, eklektik.js, utils.js
- `app/Services/Dashboard/` - KPIService, MerchantService, SubscriptionService
- `app/Http/Controllers/Api/` - DataControllerOptimized, EklektikDashboardController
- `server.py` - FastAPI AI proxy

## Completed Features
### Phase 1 (Done)
- Club Privileges brand theme (Purple #6C4BA0, Gold #D4A843) applied everywhere
- KPI tooltips with mathematical accuracy
- Automated Weekly Reporting System
- Inverse delta colors for negative KPIs
- Chart loading bugs fixed (no Blade directives in .js files)

### Phase 2 (Done - 2026-03-28)
- Merchant data: aligned with clubprivileges.app (636 partners, 1357 POS) using promo_active + has_location logic
- Active Merchants constrained to Total Merchants set (no more Active > Total)
- Added avgInterTransactionDays KPI (1.7 j)
- Fixed transactionsPerUser decimal display (1.3)
- updateTables() function defined + orchestrates all table updates
- updateComparisonTable called in progressive loading
- Comparison table: proper number formatting (0 decimals for integers, 1 for percentages)
- Eklektik charts: fully harmonized with purple/gold theme (all 5+ charts)
- Mobile responsive: 2 KPIs per row on ALL tabs (span 6 grid)
- Reporting tab: responsive layout with overflow control
- PHP-FPM nginx config on port 8002
- File cache fallback when Redis is unreachable

## Data Logic
- **Total Merchants** = Partners with active promotion (promotion_active=1) AND at least 1 location
- **Active Merchants** = Partners in the "Total Merchants" set who had transactions in the period  
- **Total POS** = Locations for partners with at least 1 active promotion
- **Active Merchant Ratio** = Active Merchants / Total Merchants * 100 (always <= 100%)
- **Transactions/User** = Total Transactions / Distinct Transacting Users
- **Avg Inter-Transaction Days** = Average days between consecutive transactions per user

## Credentials
- Email: superadmin@ooredoo.tn
- Password: SuperAdmin@2025

## Environment Notes
- PHP 8.2 installed via apt-get (may need reinstall on environment rebuild)
- Nginx config for Laravel at /etc/nginx/nginx-laravel.conf (port 8002)
- Socket permissions: chmod 777 /run/php/php8.2-fpm.sock
- Cache driver: file (Redis external server may be unreachable)
- OPcache disabled for development

## Known Constraints
- NEVER put Blade directives in .js files
- Use window._dashboardData to pass PHP data to JS
- Eklektik chart colors defined in BOTH controller AND blade component (eklektik-charts.blade.php)
- activeMerchants must always be <= totalPartners
