# Club Privilèges - Performance Dashboard

## Problème Original
Dashboard haute performance Laravel pour suivi des abonnements, transactions et KPIs opérateurs (Timwe, Ooredoo/DGV). Objectifs : temps de réponse sub-seconde, statistiques exactes, monitoring temps réel, architecture découplée.

## Stack Technique
- Laravel 10, PHP 8.2, Nginx, PHP-FPM
- MySQL (vues matérialisées), Redis (cache ultra-rapide)
- Chart.js, Vanilla JavaScript
- FastAPI proxy (port 8001 -> Nginx port 8002)

## Architecture
- `app/Services/Dashboard/` : Services domaine (KPIService, MerchantService, StatisticsService, SubscriptionService, TransactionService)
- `app/Services/DashboardService.php` : Facade legere (~170 lignes)
- `app/Http/Controllers/Api/DataControllerOptimized.php` : Endpoints split avec cache Redis
- `resources/views/dashboard.blade.php` : Frontend principal (~5 500 lignes, refactore)
- `resources/views/monitoring/dashboard.blade.php` : Dashboard monitoring
- `public/js/dashboard/` : Modules JS extraits (6 fichiers, ~4 860 lignes)

## Modules JS Extraits (Refactoring 28/03/2026)
| Module | Lignes | Description |
|--------|--------|-------------|
| `eklektik.js` | 2 072 | Graphiques et stats Eklektik |
| `timwe.js` | 878 | Stats, KPIs, tableaux Timwe |
| `charts.js` | 852 | Creation graphiques Chart.js |
| `tables.js` | 703 | Tableaux (daily stats, merchants, subscriptions) |
| `ooredoo.js` | 283 | Stats et KPIs Ooredoo/DGV |
| `utils.js` | 72 | Fonctions utilitaires partagees |

## Endpoints API Principaux
- `GET /api/dashboard/split/kpis` - KPIs globaux
- `GET /api/dashboard/split/subscriptions` - Details abonnements
- `GET /api/dashboard/split/timwe` - Stats operateur Timwe
- `GET /api/dashboard/split/ooredoo` - Stats operateur Ooredoo
- `GET /api/operators` - Liste operateurs
- `GET /api/monitoring/health` - Sante systeme

## Coherence des Donnees (Analyse 28/03/2026)
### Activated Subscriptions : 100% coherent (DIFF=0 jour par jour)
### Active Subscriptions : 
- Overview = cohorte periode (actives pendant la periode et encore actifs)
- Timwe tab = base totale (tous abonnes actifs au dernier jour)
- En lifetime les 2 convergent
- Decision utilisateur : garder la logique originale (option A)

## Ce qui est implemente
- [x] Services domaine (KPIService, MerchantService, etc.)
- [x] Refactoring DashboardService.php (~4000 -> 170 lignes)
- [x] Monitoring temps reel
- [x] Fix dropdown operateurs
- [x] Analyse coherence donnees inter-onglets
- [x] Restauration logique originale calcul Timwe
- [x] Navigation restructuree (groupes avec separateurs, Agent IA en bouton flottant, Diagnostic dans Timwe)
- [x] Tooltips explicatifs sur tous les KPIs et graphiques (68 tooltips)
- [x] Refactoring JS : dashboard.blade.php 10 320 -> 5 467 lignes, 6 modules externes

## Backlog
- Aucune tache en attente

## Credentials Test
- Email: superadmin@ooredoo.tn
- Password: SuperAdmin@2025
