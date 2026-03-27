# PRD - Dashboard Club Privileges / Ooredoo

## Probleme Original
Deployer l'application Ooredoo Privileges et effectuer des analyses fonctionnelles et des ameliorations de temps de reponse et requetes.

## Architecture Technique
- **Framework**: Laravel 10 (PHP 8.2), Nginx, PHP-FPM (15 workers), Redis
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

### Correction donnees Timwe (26-27 Mars 2026)
- Anomalie 26/03: recalculee (740 -> 332)
- Anomalie historique Feb 19 - Mar 2: 12 jours recalcules (billings gonfles ~4x corriges)
- Code mis a jour: 3 ppids (63980, 63981, 63982) au lieu de 63980 seul
- Comptage par transaction (sans dedup telephone) pour coller au rapport Timwe
- Fevrier recalcule integralement: 3 837 (Timwe: 4 012, ecart 4.4% accepte)
- Mars recalcule integralement (1-26): 4 008

### Nettoyage securite GitGuardian (27 Mars 2026)
- 6 fichiers nettoyes: MAIL_PASSWORD, EKLEKTIK_CLIENT_SECRET/ID supprimes
- Secrets migres vers .env uniquement (non commite)
- 0 secret restant dans les fichiers trackes

### Rate limiting Agent IA (27 Mars 2026)
- Limite quotidienne globale: 250 req/jour (free tier Gemini 2.5 Flash)
- Limite par minute: 10 req/min
- Limite par utilisateur: 20 req/5min (existant)
- Info quota retournee dans chaque reponse API et endpoint /stats

### Materialisation endpoints split (27 Mars 2026)
- Warmup etendu: merchants, transactions, subscriptions pre-calcules dans Redis
- Resultats cold cache -> warm cache:
  - Merchants: 4 167ms -> 322ms (-92%)
  - Transactions: 2 659ms -> 106ms (-96%)
  - Subscriptions: 22 088ms -> 212ms (-99%)
- Cron existant (*/25min) pre-chauffe aussi les endpoints split (3 periodes x 3 operateurs)
- PHP-FPM augmente a 15 workers (etait 5)
- Fix permissions storage/logs pour eviter 500 apres warmup

### Configuration
- TrustProxies='*', PHP-FPM 15 workers, Nginx timeout 300s
- 11 jobs planifies (scheduler Laravel)
- TIMWE_BILLING_PPIDS: 63980,63981,63982
- AI_AGENT_DAILY_LIMIT: 250, AI_AGENT_MINUTE_LIMIT: 10

## Backlog
- P2: Monitoring stabilite Agent IA (dashboard frontend)
- P3: Materialisation 365 jours
- P3: Monitoring temps reel
