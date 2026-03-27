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
- Cold cache: 165s -> 17.2s (-89.6%), Warm cache: 4.0s progressif
- KPIs: 31s -> 1.97s (tables materialisees, -93.6%)
- 6 endpoints split paralleles (kpis, merchants, transactions, subscriptions, ooredoo, timwe)

### Fusion branche develop (26 Mars 2026)
- 228 fichiers, 10 onglets: Overview, Subscriptions, Transactions, Merchants, Timwe, Ooredoo/DGV, Eklektik, Comparison, Agent IA, Diagnostic Timwe

### Integration IA (26 Mars 2026)
- Gemini 2.5 Flash (35% plus rapide que GPT-4, defini par defaut)

### Correction donnees Timwe (26-27 Mars 2026)
- 3 ppids (63980, 63981, 63982), comptage par transaction
- Fevrier: 3 837 (Timwe: 4 012, ecart 4.4% accepte), Mars: 4 008

### Nettoyage securite GitGuardian (27 Mars 2026)
- 6 fichiers nettoyes, 0 secret dans fichiers trackes

### Rate limiting + Monitoring Agent IA (27 Mars 2026)
- Limite quotidienne: 250 req/jour, 10 req/min
- Widget monitoring: quota temps reel, temps moyen, questions 30j, tokens 30j

### Materialisation & Warmup (27 Mars 2026)
- Warmup etendu: merchants (-92%), transactions (-96%), subscriptions (-99%)
- PHP-FPM 15 workers, materialisation 365j lancee
- Cron hebdomadaire (dimanche 4h30) refresh complet, quotidien (3h00) 7 derniers jours

### Correction bugs critiques (27 Mars 2026)
- FIX: operator "all" -> "ALL" normalise (merchants/transactions/subscriptions retournaient vide)
- FIX: Conflit de nom formatNumber() ecrasee par widget IA -> renommee formatNumberShort()
- FIX: taux_facturation string -> parseFloat() pour calcul correct du taux moyen Timwe
- FIX: Creation endpoint split/timwe dedie (les KPIs Timwe dependaient des subscriptions lentes)
- FIX: KPIs Ooredoo/DGV valeurs float brutes -> correctement formatees (46,47%, 0,322 TND)
- FIX: Auto-demarrage PHP-FPM + Nginx dans le proxy FastAPI au redemarrage conteneur
- FIX: APP_URL mise a jour vers le bon domaine (corrige erreur CSRF 419)

### Analyse complete 10 onglets (27 Mars 2026)
- Tous les 10 onglets testes et fonctionnels
- KPIs correctement formates (arrondis, separateurs, unites)
- Graphiques et tableaux remplis
- Periodes courte (7j), moyenne (30j) et longue (90j) validees

## Backlog
- P3: Monitoring temps reel (alertes, health checks)
