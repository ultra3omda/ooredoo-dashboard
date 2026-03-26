# PRD - Dashboard Club Privilèges / Ooredoo

## Problème Original
Déployer l'application Ooredoo Privileges (Club Privilèges Dashboard) et effectuer des analyses fonctionnelles et des améliorations de temps de réponse et requêtes.

## Architecture Technique
- **Framework**: Laravel 10 (PHP 8.2)
- **Base de données**: MySQL distante (51.38.187.245:3306)
- **Cache**: Redis distant (51.38.187.245:7905) 
- **Frontend**: Blade templates + Chart.js (SSR)
- **Auth**: Session-based + OTP
- **Déploiement**: FastAPI proxy (8001) + Node.js proxy (3000) → Nginx + PHP-FPM (8002)

## Ce qui a été implémenté (26 Mars 2026)

### Déploiement
- Installation PHP 8.2, Composer, PHP-FPM, Nginx
- Configuration des proxys (FastAPI + Node.js) pour intégrer Laravel dans l'infrastructure Emergent
- Configuration de PHP-FPM avec pool optimisé (10 workers, 180s timeout, 512MB memory)
- Configuration Nginx avec timeouts étendus (300s)
- Configuration .env complète avec toutes les variables d'environnement

### Optimisations Performance
- **Cache Redis activé** (CACHE_DRIVER changé de `file` à `redis`) - Gain: 30x sur les requêtes cachées
- **Trusted Proxies configurés** pour fonctionner derrière les proxys
- **Commande de warmup cache** (`php artisan dashboard:warmup`)
- **LOG_LEVEL réduit** à `warning` en production

### Résultats Performance
| Métrique | Avant | Après |
|---------|-------|-------|
| 1ère requête (MISS) | ~42s | ~37-63s |
| Requêtes suivantes (HIT) | ~42s | **~2s** |
| Amélioration | - | **30x plus rapide** |

## Utilisateurs
- Super Administrateur: superadmin@ooredoo.tn
- Administrateurs opérateurs
- Utilisateurs dashboard

## Tests Passés (100%)
- Login/Logout
- Dashboard navigation (7 onglets)
- KPI cards loading
- Date pickers
- Operator selection (13 opérateurs)
- Charts rendering
- CSRF protection
- Session management

## Backlog P0 (Haute Priorité)
1. Réduire le temps de la 1ère requête de 37-63s à <15s via requêtes SQL parallèles
2. Scheduler de warmup cache (cron job toutes les 5 min)
3. Parameterized queries (remplacer l'interpolation SQL par des bindings PDO)

## Backlog P1 (Moyenne Priorité)
4. Découper dashboard.blade.php (9456 lignes) en composants Blade
5. Supprimer DataController legacy au profit de DataControllerOptimized uniquement
6. Consolidation du DashboardService (3388 lignes) en services spécialisés
7. Rate limiting sur les endpoints API

## Backlog P2 (Basse Priorité)
8. Supprimer les routes temporaires non-sécurisées
9. Dashboard de monitoring intégré
10. Tests unitaires et d'intégration
