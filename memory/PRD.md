# Club Privileges - Performance Dashboard

## Probleme Original
Dashboard haute performance Laravel pour suivi des abonnements, transactions et KPIs operateurs (Timwe, Ooredoo/DGV). Objectifs : temps de reponse sub-seconde, statistiques exactes, monitoring temps reel, architecture decouplee.

## Stack Technique
- Laravel 10, PHP 8.2, Nginx, PHP-FPM
- MySQL (vues materialisees), Redis (cache ultra-rapide)
- Chart.js, Vanilla JavaScript
- FastAPI proxy (port 8001 -> Nginx port 8002)

## Architecture
- `app/Services/Dashboard/` : Services domaine
- `app/Services/DashboardService.php` : Facade legere (~170 lignes)
- `app/Http/Controllers/Api/DataControllerOptimized.php` : Endpoints split avec cache Redis
- `resources/views/dashboard.blade.php` : Frontend principal (~5 600 lignes, refactore)
- `resources/views/monitoring/dashboard.blade.php` : Dashboard monitoring
- `public/js/dashboard/` : 6 modules JS extraits (~4 860 lignes)

## Design System (Dark Theme - Swiss Brutalist Control Room)
| Variable | Valeur | Usage |
|----------|--------|-------|
| --bg | #050505 | Fond page |
| --card | #121212 | Fond cartes |
| --text-primary | #FFFFFF | Texte principal |
| --brand-primary | #E60000 | Rouge Ooredoo |
| --accent | #00E5FF | Cyan electrique |
| --muted | #A1A1AA | Texte secondaire |
| --border | #27272A | Bordures |
| Font KPIs | Outfit 700 | Chiffres grands |
| Font Body | Manrope 400-600 | Texte courant |

## Navigation Structure
- **Groupe 1 (Donnees):** Overview | Subscriptions | Transactions | Merchants
- **Groupe 2 (Operateurs):** Timwe | Ooredoo/DGV | Eklektik
- **Groupe 3 (Outils):** Comparison
- **Agent IA:** Bouton flottant bas-droite -> Panel slide-in
- **Diagnostic Timwe:** Lien dans l'onglet Timwe

## Modules JS Extraits (28/03/2026)
| Module | Lignes | Description |
|--------|--------|-------------|
| eklektik.js | 2 072 | Stats Eklektik |
| timwe.js | 878 | Stats Timwe |
| charts.js | 852 | Graphiques Chart.js |
| tables.js | 703 | Tableaux (daily stats, merchants, subs) |
| ooredoo.js | 283 | Stats Ooredoo/DGV |
| utils.js | 72 | Fonctions utilitaires |

## Coherence des Donnees (Analyse 28/03/2026)
- Activated Subscriptions : 100% coherent (DIFF=0 jour par jour, teste sur 5+ mois)
- Active Subscriptions : Overview=cohorte, Timwe=base totale (convergent en lifetime)
- Decision utilisateur : garder logique originale

## Ce qui est implemente
- [x] Services domaine (KPIService, MerchantService, etc.)
- [x] Refactoring DashboardService.php (~4000 -> 170 lignes)
- [x] Monitoring temps reel
- [x] Fix dropdown operateurs
- [x] Analyse coherence donnees
- [x] Navigation restructuree (groupes, separateurs, Agent IA flottant, Diagnostic dans Timwe)
- [x] 95 tooltips explicatifs
- [x] Refactoring JS : 10 320 -> 5 600 lignes + 6 modules externes
- [x] Dark theme "Control Room" (CSS variables, glassmorphism, animations, fonts)
- [x] Filtres compacts inline (date + comparaison + actions sur 1 ligne)

## Backlog
- Aucune tache en attente

## Credentials Test
- Email: superadmin@ooredoo.tn
- Password: SuperAdmin@2025
