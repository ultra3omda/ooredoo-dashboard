# Club Privileges - Performance Dashboard PRD

## Original Problem Statement
Deploy a high-performance Laravel dashboard, mathematically accurate stats matching `clubprivileges.app`, automated weekly AI reporting, robust navigation, fully responsive UI with Light/Dark mode theming.

## Architecture
- **Backend**: Laravel 10 + Nginx + PHP-FPM + Redis cache
- **Frontend**: Vanilla JS + Chart.js + Blade templates
- **AI Reporting**: FastAPI proxy (server.py) using Emergent LLM Key
- **DB**: MySQL with daily stat tables

## File Structure (Post-Refactoring)
```
resources/views/
  dashboard.blade.php          (1654 lines - lean HTML only)
  layouts/app.blade.php        (shared layout for admin pages)
  components/theme-init.blade.php
  admin/ml-dashboard.blade.php
  admin/users/index.blade.php
  admin/invitations/index.blade.php
  sub-stores/dashboard_harmonized.blade.php

public/css/dashboard.css       (2385 lines - all dashboard CSS)
public/js/dashboard/
  main.js, filters.js, ai-reporting.js, eklektik.js,
  charts.js, tables.js, timwe.js, ooredoo.js, utils.js, reporting.js
```

## Completed Features
- [x] Full dashboard with 8 tabs + Comparison + Reporting
- [x] Mathematically accurate KPIs
- [x] Automated AI Weekly Reporting
- [x] Mobile responsive (2 KPIs per row)
- [x] Redis caching
- [x] Light Mode default + Dark/Light toggle (localStorage)
- [x] Ooredoo/DGV billing rate = average of daily rates
- [x] Eklektik "Statistiques Quotidiennes" with Offre column
- [x] Profile dropdown (z-index: 10000, position: fixed)
- [x] All admin pages adapted (theme coherence)
- [x] ML Dashboard (KPIs, trends, segments, recommendations, predictions, model performance, config)
- [x] Refactored dashboard.blade.php: 6087 -> 1654 lines
- [x] Ooredoo/DGV tab refresh on date change
- [x] Date formatting: YYYY-MM-DD (no timestamps) in all tables

## Business Rules
- Ooredoo/DGV billing rate = average of daily billing_rate percentages
- Taux de facturation displayed with 3 decimal places
- Theme persists via localStorage key 'dashboard-theme', default: light

## Testing
- Iterations 9-13 all passed (100% success)
- Credentials: superadmin@ooredoo.tn / SuperAdmin@2025
