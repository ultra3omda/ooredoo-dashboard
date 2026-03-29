# Club Privileges - Performance Dashboard PRD

## Original Problem Statement
Deploy a high-performance Laravel dashboard, mathematically accurate stats matching `clubprivileges.app`, automated weekly AI reporting, robust navigation, fully responsive UI with Light/Dark mode theming.

## Architecture
- **Backend**: Laravel 10 + Nginx + PHP-FPM + Redis cache
- **Frontend**: Vanilla JS + Chart.js + Blade templates
- **AI Reporting**: FastAPI proxy (server.py) using Emergent LLM Key
- **DB**: MySQL with daily stat tables

## Key DB Tables
- `client_abonnement`, `subscription_daily_stats`, `transaction_daily_stats`
- `ooredoo_daily_stats`, `eklektik_stats_daily`, `timwe_daily_stats`
- `report_recipients`, `report_logs`, `ml_client_features`, `ml_predictions`

## File Structure (Post-Refactoring)
```
resources/views/
  dashboard.blade.php          (1654 lines - lean HTML only)
  layouts/app.blade.php        (shared layout for admin pages)
  components/theme-init.blade.php (shared theme CSS partial)
  admin/ml-dashboard.blade.php (ML Dashboard)
  admin/users/index.blade.php
  admin/invitations/index.blade.php
  sub-stores/dashboard_harmonized.blade.php

public/css/
  dashboard.css                (2385 lines - all dashboard CSS)

public/js/dashboard/
  main.js         (loadDashboardData, updateDashboardSection, updateKPI)
  filters.js      (filter sidebar, notifications)
  ai-reporting.js (AI agent, theme toggle)
  eklektik.js     (Eklektik charts, daily table, profile dropdown)
  charts.js       (Chart.js initialization)
  tables.js       (subscription tables, modal)
  timwe.js        (Timwe KPIs, tables)
  ooredoo.js      (Ooredoo KPIs)
  utils.js        (formatNumber, formatPercentage helpers)
  reporting.js    (weekly AI reporting)
```

## Completed Features
- [x] Full dashboard with 8 tabs (Overview, Subscriptions, Transactions, Merchants, Timwe, Ooredoo/DGV, Eklektik, Comparison, Reporting)
- [x] Mathematically accurate KPIs matching clubprivileges.app
- [x] Automated AI Weekly Reporting System
- [x] Mobile responsive layout (2 KPIs per row)
- [x] Redis caching for all heavy queries
- [x] Light Mode default + Dark/Light toggle (localStorage)
- [x] Ooredoo/DGV billing rate = average of daily rates
- [x] Eklektik "Statistiques Quotidiennes" expandable table
- [x] Profile dropdown (z-index: 10000, position: fixed)
- [x] Subscription details modal bug fix
- [x] SubStore dashboard with matching theme
- [x] All admin pages adapted (Users, Invitations, ML Dashboard, Eklektik Config, Sync)
- [x] ML Dashboard with KPIs, trends, segments, recommendations, predictions, config
- [x] **Refactored dashboard.blade.php**: 6087 -> 1654 lines (73% reduction)

## Key API Endpoints
- `/api/dashboard/split/kpis` - KPIs
- `/api/dashboard/split/merchants` - Merchants
- `/api/dashboard/split/transactions` - Transactions
- `/api/dashboard/split/subscriptions` - Subscriptions
- `/api/dashboard/split/ooredoo` - Ooredoo/DGV stats
- `/api/dashboard/split/timwe` - Timwe stats
- `/api/dashboard/split/eklektik` - Eklektik daily stats
- `/admin/ml-dashboard` - ML Dashboard
- `/admin/ml-dashboard/data` - ML data API
- `/admin/ml-dashboard/recommendations/generate` - Generate ML recommendations

## Business Rules
- Ooredoo/DGV billing rate = average of daily billing_rate percentages
- Merchants: activeMerchants <= totalPartners
- Theme persists via localStorage key 'dashboard-theme'
- Default theme: Light Mode

## Testing
- Iterations 9-12 all passed (100% success)
- Credentials: superadmin@ooredoo.tn / SuperAdmin@2025
