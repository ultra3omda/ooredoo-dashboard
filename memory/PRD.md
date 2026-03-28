# Club Privileges - Performance Dashboard

## Probleme Original
Dashboard haute performance Laravel pour suivi des abonnements, transactions et KPIs operateurs (Timwe, Ooredoo/DGV). Objectifs : temps de reponse sub-seconde, statistiques exactes, monitoring temps reel, architecture decouplee.

## Stack Technique
- Laravel 10, PHP 8.2, Nginx, PHP-FPM, MySQL, Redis
- Chart.js, Vanilla JavaScript modulaire, DomPDF
- FastAPI proxy + OpenAI GPT-4o via emergentintegrations

## Ce qui est implemente
- [x] Services domaine KPIs, refactoring DashboardService
- [x] Navigation restructuree (groupes, Agent IA flottant)
- [x] Tooltips KPI audites et corriges (descriptions = calculs exacts)
- [x] Dark theme Club Privileges (violet #6C4BA0 / or #D4A843)
- [x] Systeme de reporting hebdomadaire (CRUD, emails HTML+PDF, IA, RGPD)
- [x] Previsualisation des rapports avec suggestions IA
- [x] Fix deltas inverses : churn, deactivations, unsubscriptions, simchurn, avgInterTxDays (rouge=hausse, vert=baisse)
- [x] Fix charts : suppression Blade directives dans charts.js, completion tables.js, deduplication eklektik.js
- [x] Responsive complet : nav scrollable horizontal, filtres empiles, tables scroll, KPIs adaptatifs (768px, 600px, 480px)

## Design System
| Variable | Valeur |
|----------|--------|
| --bg | #0D0A1A |
| --card | #161131 |
| --brand-primary | #6C4BA0 |
| --brand-secondary | #D4A843 |
| --border | #2A2350 |

## Navigation
- **Donnees:** Overview | Subscriptions | Transactions | Merchants
- **Operateurs:** Timwe | Ooredoo/DGV | Eklektik
- **Outils:** Comparison | Reporting (SuperAdmin)

## Deltas Inverses (hausse = rouge)
deactivated, Deactivated, churn, Churn, lostSubscriptions, avgInterTxDays, simchurn, unsubscriptions, Unsubscriptions

## Responsive Breakpoints
- 768px: 2 KPI/ligne, filtres empiles, nav scroll
- 600px: filtres 1 colonne, tables scroll horizontal
- 480px: KPI 2/ligne compact, header empile, nav ultra compact

## Backlog
- Aucune tache en attente

## Credentials
- Email: superadmin@ooredoo.tn
- Password: SuperAdmin@2025
