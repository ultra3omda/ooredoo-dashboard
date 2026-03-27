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
- Chargement progressif: 5 endpoints split paralleles
- PDO bindings (securisation SQL)
- Cron warmup */25min + materialisation daily 3h00

### Fusion branche develop (26 Mars 2026)
- 228 fichiers importes (Agent IA, Diagnostic Timwe, ML Dashboard)
- 10 onglets: Overview, Subscriptions, Transactions, Merchants, Timwe, Ooredoo/DGV, Eklektik, Comparison, Agent IA, Diagnostic Timwe

### Integration IA (26 Mars 2026)
- Gemini 2.5 Flash (35% plus rapide que GPT-4, defini par defaut)
- OpenAI GPT-4 disponible en fallback

### Correction donnees Timwe (26-27 Mars 2026)
- 3 ppids (63980, 63981, 63982), comptage par transaction
- Fevrier recalcule: 3 837 (Timwe: 4 012, ecart 4.4% accepte)
- Mars recalcule: 4 008

### Nettoyage securite GitGuardian (27 Mars 2026)
- 6 fichiers nettoyes, 0 secret restant dans les fichiers trackes

### Rate limiting + Monitoring Agent IA (27 Mars 2026)
- Limite quotidienne: 250 req/jour (free tier Gemini 2.5 Flash)
- Limite par minute: 10 req/min
- Widget monitoring frontend: quota temps reel, temps moyen, questions 30j, tokens 30j
- Quota mis a jour en temps reel apres chaque reponse IA

### Materialisation endpoints split (27 Mars 2026)
- Warmup etendu: merchants (-92%), transactions (-96%), subscriptions (-99%)
- PHP-FPM 15 workers

### Materialisation 365 jours (27 Mars 2026)
- Commande lancee en background (365j x 13 operateurs)
- Cron hebdomadaire (dimanche 4h30) pour refresh complet
- Cron quotidien (3h00) pour les 7 derniers jours

### Configuration
- TrustProxies='*', PHP-FPM 15 workers, Nginx timeout 300s
- TIMWE_BILLING_PPIDS: 63980,63981,63982
- AI_AGENT_DAILY_LIMIT: 250, AI_AGENT_MINUTE_LIMIT: 10

## Backlog
- P3: Monitoring temps reel (alertes, health checks)
