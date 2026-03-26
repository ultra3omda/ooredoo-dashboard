# PRD - Dashboard Club Privileges / Ooredoo

## Probleme Original
Deployer l'application Ooredoo Privileges et effectuer des analyses fonctionnelles et des ameliorations de temps de reponse et requetes.

## Architecture Technique
- **Framework**: Laravel 10 (PHP 8.2), Nginx, PHP-FPM, Redis
- **Base de donnees**: MySQL distante (51.38.187.245:3306)
- **Cache**: Redis distant (51.38.187.245:7905) 
- **Deploiement**: FastAPI proxy (8001) + Node.js proxy (3000) -> Nginx + PHP-FPM (8002)
- **Repo GitHub**: ultra3omda/ooredoo-dashboard (PR #1 merged: emergent -> develop)

## Utilisateur
- superadmin@ooredoo.tn / SuperAdmin@2025

## Travail accompli

### Performance (26 Mars 2026)
- Cold cache: 165s (500 ERROR) -> 17.2s (-89.6%)
- Warm cache: timeout -> 4.0s (progressif) / 2.5s (monolithique)
- KPIs: 31s -> 1.97s (tables materialisees, -93.6%)
- Tables materialisees: 1,278 lignes, 90 jours, 13 operateurs + ALL
- Chargement progressif: 5 endpoints split paralleles
- PDO bindings (securisation SQL)
- Cron warmup */25min + materialisation daily 3h00

### Fusion branche develop (26 Mars 2026)
- 228 fichiers importes (Agent IA, Diagnostic Timwe, ML Dashboard)
- Merge conflict-free avec develop (PR #1 merged sur GitHub)
- 10 onglets: Overview, Subscriptions, Transactions, Merchants, Timwe, Ooredoo/DGV, Eklektik, Comparison, Agent IA, Diagnostic Timwe

### Integration IA (26 Mars 2026)
- Cles API configurees: OPENAI_API_KEY, GEMINI_API_KEY
- Benchmark: Gemini 2.5 Flash 35% plus rapide que GPT-4
- Gemini defini comme provider par defaut

### Correction donnees Timwe (26 Mars 2026)
- Anomalie 26/03: recalculee (740 -> 332)
- Anomalie historique Feb 19 - Mar 2: 12 jours recalcules avec succes
  - Billings avant: ~450-576/jour (gonfles ~4x)
  - Billings apres: ~103-142/jour (fourchette normale)
  - Cause: ancien code buggue avait calcule ces dates sans filtrage correct

### Configuration
- TrustProxies='*', PHP-FPM 10 workers, Nginx timeout 300s
- 11 jobs planifies (scheduler Laravel)

## Backlog
- P1: Nettoyer secrets dans env.production.example (GitGuardian - 4 secrets exposes)
- P2: Monitoring stabilite Agent IA / Gemini 2.5 Flash
- P2: Materialiser merchants/subscriptions/transactions (cold cache < 5s)
- P3: Materialisation 365 jours
- P3: Monitoring temps reel
