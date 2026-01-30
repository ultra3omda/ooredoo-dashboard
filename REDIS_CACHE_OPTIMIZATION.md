# Optimisation Performance Dashboard avec Redis

## 🎯 Objectif

Améliorer drastiquement les temps de chargement du dashboard, surtout pour les longues périodes, en utilisant Redis comme système de cache distribué.

## ✅ Configuration

### 1. Variables d'environnement (.env)

```env
CACHE_DRIVER=redis
REDIS_HOST=51.38.187.245
REDIS_PORT=7905
REDIS_PASSWORD=hxtrJ74
REDIS_DB=0
REDIS_CACHE_DB=1
```

### 2. Configuration Laravel

- **config/cache.php** : Driver par défaut = `redis`
- **config/database.php** : Configuration Redis déjà présente

## 🚀 Fonctionnalités Implémentées

### 1. DashboardCacheService

Service de cache intelligent avec :

- **TTL Adaptatif** : Le temps de cache varie selon :
  - La longueur de la période (7j, 30j, 90j, 180j+)
  - Le type de données (KPIs, transactions, subscriptions, etc.)
  
- **Cache Multi-Niveaux** :
  - **Niveau 1** : Cache complet (si disponible)
  - **Niveau 2** : Cache par composants (reconstruction partielle)
  - **Niveau 3** : Calcul en temps réel avec mise en cache

- **Fallback Stale** : Pour les longues périodes, conservation d'une version "stale" (expirée) pour fallback en cas d'erreur

### 2. Intégration DashboardService

Le `DashboardService` utilise maintenant automatiquement Redis pour :
- Cache des données complètes du dashboard
- TTL adaptatif selon la période
- Invalidation intelligente par opérateur ou période

### 3. Commande de Préchargement

```bash
php artisan dashboard:cache:warmup
```

Précharge le cache pour :
- Périodes courantes : 7j, 30j, 90j
- Mois en cours et mois précédent
- Opérateurs : ALL, Timwe, Ooredoo (configurable)

**Options** :
- `--operators=ALL,Timwe` : Spécifier les opérateurs
- `--days=7,30,90` : Spécifier les périodes en jours

## 📊 TTL par Type de Données

| Type | TTL Base | Multiplicateur (période) | TTL Final (90j) |
|------|----------|--------------------------|-----------------|
| KPIs | 5 min | x4 | 20 min |
| Merchants | 10 min | x4 | 40 min |
| Transactions | 15 min | x4 | 60 min |
| Subscriptions | 20 min | x4 | 80 min |
| Heavy (90j+) | 30 min | x4 | 120 min (2h) |

## 🔧 Utilisation

### Préchargement Manuel

```bash
# Précharger pour les périodes courantes
php artisan dashboard:cache:warmup

# Précharger pour des opérateurs spécifiques
php artisan dashboard:cache:warmup --operators=Timwe,Ooredoo

# Précharger pour des périodes spécifiques
php artisan dashboard:cache:warmup --days=7,30,90,180
```

### Préchargement Automatique (Cron)

Ajouter dans `app/Console/Kernel.php` :

```php
protected function schedule(Schedule $schedule)
{
    // Précharger le cache tous les jours à 2h du matin
    $schedule->command('dashboard:cache:warmup')
             ->dailyAt('02:00');
}
```

### Statistiques du Cache

```php
$cacheService = app(DashboardCacheService::class);
$stats = $cacheService->getStats();
// Retourne : total_keys, memory_used, hit_rate, etc.
```

### Invalidation du Cache

```php
$cacheService = app(DashboardCacheService::class);

// Invalider par opérateur
$cacheService->invalidateOperator('Timwe');

// Invalider par période
$cacheService->invalidatePeriod('2025-01-01', '2025-01-31');
```

## 📈 Gains de Performance Attendus

### Avant (sans Redis)
- Période 7j : ~2-5 secondes
- Période 30j : ~10-20 secondes
- Période 90j : ~30-60 secondes
- Période 180j+ : ~60-120 secondes (timeout fréquent)

### Après (avec Redis)
- Période 7j : **< 100ms** (cache hit)
- Période 30j : **< 200ms** (cache hit)
- Période 90j : **< 500ms** (cache hit)
- Période 180j+ : **< 1s** (cache hit)

**Amélioration** : **20x à 100x plus rapide** pour les requêtes en cache !

## 🔍 Monitoring

### Vérifier la connexion Redis

```bash
php artisan tinker
>>> Redis::connection('cache')->ping()
```

### Voir les statistiques

```bash
php artisan tinker
>>> app(\App\Services\DashboardCacheService::class)->getStats()
```

### Vérifier les clés en cache

```bash
php artisan tinker
>>> Redis::connection('cache')->keys('dashboard_v3:*')
```

## ⚠️ Notes Importantes

1. **Première Requête** : La première requête après un vidage de cache sera lente (calcul normal), mais les suivantes seront instantanées.

2. **Invalidation** : Le cache est automatiquement invalidé après le TTL, mais peut être invalidé manuellement si nécessaire.

3. **Mémoire Redis** : Surveiller l'utilisation mémoire Redis. Le cache utilise la DB 1 (REDIS_CACHE_DB=1).

4. **Fallback** : Si Redis est indisponible, Laravel utilisera le driver de fallback (file) configuré.

## 🎯 Prochaines Étapes

1. **Configurer le Cron** : Ajouter le préchargement automatique quotidien
2. **Monitoring** : Surveiller les statistiques Redis (hit rate, mémoire)
3. **Optimisation** : Ajuster les TTL selon les besoins réels
4. **Invalidation** : Configurer l'invalidation automatique lors des mises à jour de données

## 📝 Commandes Utiles

```bash
# Vider le cache Redis
php artisan cache:clear

# Précharger le cache
php artisan dashboard:cache:warmup

# Voir les logs
tail -f storage/logs/laravel.log | grep "Cache"
```
