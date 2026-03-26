# PRD - Dashboard Club Privileges / Ooredoo

## Probleme Original
Deployer l'application Ooredoo Privileges et effectuer des analyses fonctionnelles et des ameliorations de temps de reponse et requetes.

## Architecture Technique
- **Framework**: Laravel 10 (PHP 8.2), Nginx, PHP-FPM, Redis
- **Base de donnees**: MySQL distante (51.38.187.245:3306)
- **Cache**: Redis distant (51.38.187.245:7905) 
- **Deploiement**: FastAPI proxy (8001) + Node.js proxy (3000) -> Nginx + PHP-FPM (8002)
- **Repo GitHub**: ultra3omda/ooredoo-dashboard (PR #1 merged: emergent -> develop)

## Travail accompli (26 Mars 2026)

### Performance
- Cold cache: 165s (500 ERROR) -> 17.2s (-89.6%)
- Warm cache: timeout -> 4.0s (progressif) / 2.5s (monolithique)
- KPIs: 31s -> 1.97s (tables materialisees, -93.6%)
- Tables materialisees: 1,278 lignes, 90 jours, 13 operateurs + ALL
- Chargement progressif: 5 endpoints split paralleles
- PDO bindings (securisation SQL)
- Cron warmup */25min + materialisation daily 3h00

### Fusion branche develop
- 228 fichiers importes (Agent IA, Diagnostic Timwe, ML Dashboard)
- Merge conflict-free avec develop (PR #1 merged sur GitHub)
- 10 onglets: Overview, Subscriptions, Transactions, Merchants, Timwe, Ooredoo/DGV, Eklektik, Comparison, Agent IA, Diagnostic Timwe

### Configuration
- Cles API configurees: OPENAI_API_KEY, GEMINI_API_KEY (fournies par user)
- TrustProxies='*', PHP-FPM 10 workers, Nginx timeout 300s
- 11 jobs planifies (scheduler Laravel)

## Utilisateur
- superadmin@ooredoo.tn / Soufiane@2025

## Backlog
- P2: Materialiser merchants/subscriptions/transactions (cold cache < 5s)
- P3: Nettoyer secrets dans env.production.example (GitGuardian)
- P3: Materialisation 365 jours
- P3: Monitoring temps reel
