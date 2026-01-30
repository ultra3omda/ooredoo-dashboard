# Optimisations Performance Dashboard - Janvier 2026

## 🎯 Problème Identifié

**33 secondes** pour charger le dashboard pour une période de **13 jours** - C'est beaucoup trop long !

### Analyse des logs (ligne 2690-2731)

| Étape | Temps | Problème |
|-------|-------|----------|
| getSubscriptionDetails | 6s | Compte 80,187 abonnements mais n'en retourne que 140 |
| Cohorts | 4s | Calcul lourd |
| Renewal rate | 1s | OK |
| Average lifespan | 2s | OK |
| Reactivation rate | 5s × 2 | Calcule même si skipped (trop de clients) |
| getTransactionsData | 7s | Peut être optimisé |
| **TOTAL** | **33s** | ⚠️ Trop long |

## ✅ Optimisations Appliquées

### 1. getSubscriptionDetails - Suppression du count() inutile

**Avant** :
```php
$totalCount = $query->count(); // 6 secondes pour compter 80k+ lignes
$results = $query->limit(140)->get();
```

**Après** :
```php
$totalCount = null; // Désactivé pour améliorer les performances
$results = $query->limit(140)->get(); // Directement limité
```

**Gain** : **~6 secondes économisées**

### 2. calculateReactivationRate - Check avant de charger les IDs

**Avant** :
```php
$expiredClients = $expiredBeforePeriod->pluck('ca.client_id'); // Charge tous les IDs
$expiredCount = $expiredClients->count(); // Puis compte
if ($expiredCount > 15000) return 0; // Skip après avoir chargé
```

**Après** :
```php
$expiredCount = $expiredBeforePeriod->count('ca.client_id'); // Compte d'abord (rapide)
if ($expiredCount > 15000) return 0; // Skip immédiatement
$expiredClients = $expiredBeforePeriod->pluck('ca.client_id'); // Charge seulement si OK
```

**Gain** : **~5 secondes économisées** (×2 = 10s pour current + previous)

### 3. Cache Redis pour les longues périodes

- Cache intelligent avec TTL adaptatif
- Cache multi-niveaux (complet → composants → calcul)
- Fallback "stale" pour les longues périodes

**Gain** : **20x à 100x plus rapide** pour les requêtes en cache

## 📊 Résultats Attendus

### Avant Optimisations
- Période 13j : **33 secondes**
- Période 30j : **60-90 secondes**
- Période 90j+ : **120+ secondes** (timeout fréquent)

### Après Optimisations
- Période 13j : **~15-20 secondes** (première requête)
- Période 13j : **< 100ms** (cache hit)
- Période 30j : **~30-40 secondes** (première requête)
- Période 30j : **< 200ms** (cache hit)
- Période 90j+ : **< 1s** (cache hit)

## 🔧 Optimisations Futures Possibles

### 1. Cache des Cohorts
Les cohorts prennent 4 secondes. On peut les mettre en cache par mois :
```php
$cohorts = Cache::remember("cohorts:{$month}", 3600, function() {
    return $this->calculateCohorts($month);
});
```

### 2. Calcul Asynchrone pour les Longues Périodes
Pour les périodes > 90 jours, utiliser des queues :
```php
if ($periodDays > 90) {
    ProcessDashboardData::dispatch($params);
    return ['status' => 'processing', 'job_id' => $jobId];
}
```

### 3. Index Database
Vérifier que les index suivants existent :
- `client_abonnement(client_abonnement_creation, country_payments_methods_id)`
- `client_abonnement(client_abonnement_expiration, country_payments_methods_id)`
- `transactions_history(client_id, created_at, status)`

### 4. Pagination Lazy Loading
Pour `getSubscriptionDetails`, charger seulement les 140 premiers sans compter le total.

## 📝 Commandes Utiles

```bash
# Vider le cache
php artisan cache:clear

# Précharger le cache
php artisan dashboard:cache:warmup

# Voir les stats Redis
php artisan tinker
>>> app(\App\Services\DashboardCacheService::class)->getStats()
```

## ⚠️ Notes

1. **Première Requête** : Sera toujours lente (calcul normal), mais les suivantes seront instantanées grâce au cache Redis.

2. **Invalidation** : Le cache est automatiquement invalidé après le TTL, mais peut être invalidé manuellement si nécessaire.

3. **Monitoring** : Surveiller les logs pour identifier d'autres goulots d'étranglement.
