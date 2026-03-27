# PRD - Dashboard Club Privilèges (Ooredoo)

## Problème Original
Dashboard de performance pour le programme Club Privilèges d'Ooredoo Tunisie. Objectif : temps de réponse < 1 seconde pour toutes les périodes, exactitude des données, sécurité du code.

## Architecture
- **Backend**: Laravel 10 (PHP 8.2-FPM) + Nginx (port 8002)
- **Proxy**: FastAPI (port 8001) + Express frontend (port 3000)
- **Base de données**: MySQL distant (51.38.187.245)
- **Cache**: Redis (pre-computed JSON responses, TTL 1h)
- **Frontend**: Vanilla JS dans Blade templates (dashboard.blade.php)

## Stratégie de Performance (3 niveaux)

### Niveau 1: Tables matérialisées (données pré-agrégées)
| Table | Lignes | Couverture |
|-------|--------|------------|
| `subscription_daily_stats` | 1 822+ | 5 ans |
| `transaction_daily_stats` | 1 822 | 5 ans |
| `dashboard_daily_stats` | 2 281 | 365 jours |
| `timwe_daily_stats` / `ooredoo_daily_stats` | ~365 | 1 an |

### Niveau 2: Cache intermédiaire Redis (Cache::remember)
- Cache par sous-requête (retention, cohorts, etc.)
- TTL: 3600s (1 heure)

### Niveau 3: Pre-computed JSON responses (ULTRA-FAST)
- Commande `dashboard:warmup-split` pré-calcule les réponses JSON complètes
- Stocke dans Redis les clés `split_raw:{endpoint}:{hash}`
- Contrôleurs servent directement la chaîne JSON pré-sérialisée
- **Temps serveur: 5-10ms par endpoint** (×1000 plus rapide qu'avant)

## Résultats de Performance

| Endpoint | AVANT | Matérialisé | Pre-computed | Accélération |
|----------|-------|------------|-------------|-------------|
| kpis | 13 242ms | 6 399ms | **10ms** | **×1324** |
| transactions | 6 599ms | 1 819ms | **6ms** | **×1100** |
| subscriptions | 20 000ms | 7 336ms | **6ms** | **×3333** |
| merchants | 4 975ms | 4 297ms | **6ms** | **×829** |
| timwe | 2 093ms | 1 985ms | **6ms** | **×349** |
| ooredoo | 2 085ms | 2 094ms | **5ms** | **×417** |
| **TOTAL** | **~49 000ms** | **~24 000ms** | **~40ms** | **×1225** |

## Crons Automatiques
| Commande | Fréquence | Description |
|----------|-----------|-------------|
| `dashboard:warmup-split --ttl=3600` | Toutes les 50 min | Maintien cache chaud |
| `dashboard:warmup --operator=ALL` | Toutes les 25 min | Warmup monolithique |
| `dashboard:materialize-subscriptions --days=7` | Quotidien 03:15 | MAJ subs 7 jours |
| `dashboard:materialize-transactions --days=7` | Quotidien 03:30 | MAJ tx 7 jours |
| `dashboard:materialize --days=7` | Quotidien 03:00 | MAJ KPIs 7 jours |

## Credentials Test
- Email: `superadmin@ooredoo.tn` / Password: `SuperAdmin@2025`

## Fichiers Clés
- `app/Services/DashboardService.php` - Service (~4000 lignes)
- `app/Http/Controllers/Api/DataControllerOptimized.php` - Contrôleur avec fast-path
- `app/Console/Commands/WarmupSplitEndpoints.php` - Pre-computation JSON
- `app/Console/Commands/MaterializeSubscriptionStats.php`
- `app/Console/Commands/MaterializeTransactionStats.php`
- `app/Console/Kernel.php` - Scheduler

## Backlog
- P3: Monitoring temps réel (alertes, health checks)
- P4: Refactoring DashboardService.php (>4000 lignes)
