# Club Privileges - Performance Dashboard

## Probleme Original
Dashboard haute performance Laravel pour suivi des abonnements, transactions et KPIs operateurs (Timwe, Ooredoo/DGV). Objectifs : temps de reponse sub-seconde, statistiques exactes, monitoring temps reel, architecture decouplee.

## Stack Technique
- Laravel 10, PHP 8.2, Nginx, PHP-FPM
- MySQL (vues materialisees), Redis (cache ultra-rapide)
- Chart.js, Vanilla JavaScript
- FastAPI proxy (port 8001 -> Nginx port 8002)
- DomPDF pour generation PDF rapports
- OpenAI GPT-4o via emergentintegrations pour suggestions IA

## Architecture
- `app/Services/Dashboard/` : Services domaine KPIs
- `app/Services/WeeklyReportService.php` : Generation et envoi des rapports hebdomadaires
- `app/Http/Controllers/Api/ReportController.php` : CRUD destinataires + envoi manuel + preview
- `app/Console/Commands/SendWeeklyReports.php` : Commande Artisan schedulee
- `resources/views/dashboard.blade.php` : Frontend principal
- `resources/views/reports/email/` : Templates email HTML (ceo, marketing, partner)
- `resources/views/reports/pdf/` : Templates PDF (ceo, marketing, partner)
- `public/js/dashboard/` : 7 modules JS (incl. reporting.js)
- `backend/server.py` : Proxy FastAPI + endpoint /api/report-ai-suggestions

## Design System (Dark Theme - Club Privileges Brand)
| Variable | Valeur | Usage |
|----------|--------|-------|
| --bg | #0D0A1A | Fond page |
| --card | #161131 | Fond cartes |
| --brand-primary | #6C4BA0 | Violet Club Privileges |
| --brand-secondary | #D4A843 | Or/Dore (accent) |
| --border | #2A2350 | Bordures |

## Navigation
- **Groupe 1:** Overview | Subscriptions | Transactions | Merchants
- **Groupe 2:** Timwe | Ooredoo/DGV | Eklektik
- **Groupe 3:** Comparison | Reporting (SuperAdmin only)

## Systeme de Reporting (28/03/2026)
### Types de rapports
1. **CEO** : Vue globale tous operateurs + KPIs + top marchands + Eklektik + suggestions IA
2. **Marketing** : Acquisition, retention, churn, canaux d'acquisition, evolution quotidienne
3. **Partenaire** : Transactions individuelles (RGPD strict), offres top, clients uniques

### Fonctionnalites
- Email HTML + PDF en piece jointe
- Suggestions IA (OpenAI GPT-4o) dans chaque rapport
- Interface admin CRUD destinataires
- Previsualisation du rapport avant envoi (modal avec rendu HTML + IA)
- Recherche partenaires avec auto-complete
- Envoi automatique chaque lundi 08:00
- Envoi manuel par destinataire ou pour tous
- Historique d'envoi avec statut
- RGPD : isolation stricte des donnees partenaires

### Endpoints API
- `GET /api/reports/recipients` - Lister
- `POST /api/reports/recipients` - Creer
- `PUT /api/reports/recipients/{id}` - Modifier
- `DELETE /api/reports/recipients/{id}` - Supprimer
- `POST /api/reports/recipients/{id}/toggle` - Activer/Desactiver
- `GET /api/reports/preview/{id}` - Previsualiser le rapport (avec IA)
- `POST /api/reports/send` - Envoyer
- `GET /api/reports/logs` - Historique
- `GET /api/reports/partners?q=` - Recherche partenaires
- `GET /api/reports/schedule` - Config scheduler
- `POST /api/report-ai-suggestions` - Suggestions IA (FastAPI)

## Ce qui est implemente
- [x] Services domaine KPIs, refactoring DashboardService
- [x] Navigation restructuree (groupes, Agent IA flottant)
- [x] Tooltips KPI audites et corriges
- [x] Dark theme Club Privileges (violet/or)
- [x] Systeme de reporting hebdomadaire complet
- [x] Interface admin Reporting
- [x] Previsualisation des rapports avec suggestions IA
- [x] Fix modal auto-close apres save

## Backlog
- Aucune tache en attente

## Credentials
- Email: superadmin@ooredoo.tn
- Password: SuperAdmin@2025
