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
- 228 fichiers, 10 onglets fonctionnels

### Integration IA (26 Mars 2026)
- Gemini 2.5 Flash (35% plus rapide que GPT-4, defini par defaut)

### Correction donnees Timwe (26-27 Mars 2026)
- 3 ppids (63980, 63981, 63982), comptage par transaction
- Fevrier: 3 837, Mars: 4 008

### Nettoyage securite GitGuardian (27 Mars 2026)
- 6 fichiers nettoyes, 0 secret dans fichiers trackes

### Rate limiting + Monitoring Agent IA (27 Mars 2026)
- Limite quotidienne: 250 req/jour, 10 req/min, widget monitoring frontend

### Materialisation & Warmup (27 Mars 2026)
- Warmup etendu: merchants (-92%), transactions (-96%), subscriptions (-99%)
- PHP-FPM 15 workers, materialisation 365j
- Cron hebdomadaire + quotidien

### Correction bugs critiques (27 Mars 2026)
- operator "all" -> "ALL" normalise
- Conflit formatNumber() ecrasee par widget IA -> formatNumberShort()
- taux_facturation string -> parseFloat()
- Endpoint split/timwe dedie cree
- KPIs Ooredoo/DGV formatage corrige
- Auto-demarrage PHP-FPM/Nginx au redemarrage conteneur
- APP_URL corrigee (erreur CSRF 419)
- Limite periode augmentee de 365j a 5 ans (lifetime)

### Benchmark 4 periodes (27 Mars 2026)
- 1 mois: ~20s cold, <500ms warm
- 6 mois: ~26s cold, <500ms warm
- 12 mois: ~27s cold, <500ms warm
- Lifetime (~5 ans): ~20s cold, <500ms warm
- 10/10 onglets fonctionnels pour toutes les periodes
- Rapport detaille: /app/reports/RAPPORT_BENCHMARK_PERIODES.md

## Backlog
- P2: Materialiser subscriptions pour reduire cold cache (20-27s -> <5s)
- P3: Monitoring temps reel (alertes, health checks)
