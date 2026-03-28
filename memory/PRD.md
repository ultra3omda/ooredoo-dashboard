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
- `app/Http/Controllers/Api/ReportController.php` : CRUD destinataires + envoi manuel
- `app/Console/Commands/SendWeeklyReports.php` : Commande Artisan schedulee
- `resources/views/dashboard.blade.php` : Frontend principal (~5 800 lignes, refactore)
- `resources/views/reports/email/` : Templates email HTML (ceo, marketing, partner)
- `resources/views/reports/pdf/` : Templates PDF (ceo, marketing, partner)
- `public/js/dashboard/` : 7 modules JS (incl. reporting.js)
- `backend/server.py` : Proxy FastAPI + endpoint /api/report-ai-suggestions

## Design System (Dark Theme - Club Privileges Brand)
| Variable | Valeur | Usage |
|----------|--------|-------|
| --bg | #0D0A1A | Fond page (violet tres sombre) |
| --card | #161131 | Fond cartes |
| --brand-primary | #6C4BA0 | Violet Club Privileges |
| --brand-secondary | #D4A843 | Or/Dore (accent) |
| --accent | #D4A843 | Accent dore |
| --border | #2A2350 | Bordures (violet teinte) |

## Navigation Structure
- **Groupe 1 (Donnees):** Overview | Subscriptions | Transactions | Merchants
- **Groupe 2 (Operateurs):** Timwe | Ooredoo/DGV | Eklektik
- **Groupe 3 (Outils):** Comparison | Reporting (SuperAdmin only)

## Systeme de Reporting Hebdomadaire (28/03/2026)
### Types de rapports
1. **CEO** : Vue globale tous operateurs + KPIs + top marchands + Eklektik + suggestions IA
2. **Marketing** : Acquisition, retention, churn, conversion, evolution quotidienne, canaux, operateurs
3. **Partenaire** : Transactions individuelles (RGPD strict), offres les plus utilisees, clients uniques

### Fonctionnalites
- Email HTML + PDF en piece jointe
- Suggestions IA (OpenAI GPT-4o) integrees dans chaque rapport
- Interface admin de configuration des destinataires (onglet Reporting)
- Recherche de partenaires pour associer aux rapports
- Envoi automatique chaque lundi 08:00 (Laravel Scheduler)
- Envoi manuel par destinataire ou pour tous
- Historique d'envoi avec statut (envoye/echoue)
- RGPD : isolation stricte des donnees partenaires

### Endpoints API
- `GET /api/reports/recipients` - Lister destinataires
- `POST /api/reports/recipients` - Creer destinataire
- `PUT /api/reports/recipients/{id}` - Modifier
- `DELETE /api/reports/recipients/{id}` - Supprimer
- `POST /api/reports/recipients/{id}/toggle` - Activer/Desactiver
- `POST /api/reports/send` - Envoyer manuellement
- `GET /api/reports/logs` - Historique d'envoi
- `GET /api/reports/partners?q=` - Recherche partenaires
- `GET /api/reports/schedule` - Config du scheduler
- `POST /api/report-ai-suggestions` - Suggestions IA (FastAPI)

### Tables DB
- `report_recipients` (id, name, email, type, partner_id, is_active, schedule_day, schedule_time)
- `report_logs` (id, recipient_id, report_type, status, period_start, period_end, ai_suggestions, error_message, sent_at)

## Ce qui est implemente
- [x] Services domaine (KPIService, MerchantService, etc.)
- [x] Refactoring DashboardService.php (~4000 -> 170 lignes)
- [x] Navigation restructuree (groupes, separateurs, Agent IA flottant)
- [x] 95+ tooltips explicatifs (audites et corriges)
- [x] Dark theme Club Privileges (violet/or)
- [x] Couleurs marque Club Privileges appliquees
- [x] Audit tooltips KPI (descriptions = calculs exacts)
- [x] Systeme de reporting hebdomadaire complet (CRUD, emails, PDF, IA, RGPD)
- [x] Interface admin Reporting dans le dashboard

## Backlog
- Aucune tache en attente

## Credentials
- Email: superadmin@ooredoo.tn
- Password: SuperAdmin@2025
