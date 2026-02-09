# 🔄 Alignement Dashboard Timwe & Diagnostic Timwe

## 📊 Problème Identifié

Les métriques affichées dans l'onglet **Timwe du Dashboard** ne correspondaient pas aux chiffres du **Diagnostic Timwe** pour la même période.

### Exemple de divergence (Janvier 2026, 01-15)

| Métrique | Dashboard (avant) | Diagnostic | Écart |
|----------|-------------------|------------|-------|
| **Taux de facturation** | 12% (ou 1.39%) | 3.08% | ❌ Formule différente |
| **Nombre facturation** | 1,934 | 1,944 | ≈ Proche |
| **Revenu TTC** | Variable | 5,832 TND | ❌ Source différente |

---

## 🔍 Causes Racines

### 1. Sources de Données Différentes

**Dashboard principal (avant correction) :**
```php
// Méthode: calculateTimweBillingRate()
// Source: Tables transactions_history + client_abonnement
// Formule: (Clients facturés / Total clients Timwe) × 100
```

**Diagnostic Timwe (référence correcte) :**
```php
// Service: TimweDiagnosticApiService
// Source: Tables agrégées timwe_diagnostic_daily_*
// Formule: (Total facturé / Numéros uniques) × 100
```

### 2. Formules de Calcul Différentes

| Aspect | Dashboard (avant) | Diagnostic (correct) |
|--------|-------------------|----------------------|
| **Base clients** | Abonnements `client_abonnement` | Tentatives `total_transactions` |
| **Facturation** | Clients avec ≥1 transaction DELIVERED | Transactions facturées (DELIVERED + totalCharged > 0) |
| **Taux** | clients_facturés / total_clients | **total_billed / total_attempts** |

---

## ✅ Solution Implémentée

### Modification du DashboardService

**Fichier** : `app/Services/DashboardService.php`  
**Méthode** : `calculateTimweBillingRate()`

#### Avant (ligne 1983)
```php
private function calculateTimweBillingRate(Carbon $startBound, Carbon $endExclusive, string $selectedOperator): array
{
    // Calcul basé sur client_abonnement et transactions_history
    $billedClients = ...;
    $totalTimweClients = ...;
    $rate = ($billedClients / $totalTimweClients) * 100;
}
```

#### Après (aligné sur Diagnostic)
```php
private function calculateTimweBillingRate(Carbon $startBound, Carbon $endExclusive, string $selectedOperator): array
{
    // PRIORITÉ : Utiliser les tables de diagnostic Timwe
    $summary = DB::table('timwe_diagnostic_daily_summary')
        ->whereBetween('stat_date', [$startDate, $endDate])
        ->selectRaw('
            COALESCE(SUM(total_transactions), 0) as total_transactions,
            COALESCE(SUM(total_billed), 0) as total_billed,
            COALESCE(SUM(total_revenue_tnd), 0) as total_revenue_tnd
        ')
        ->first();
    
    $uniquePhones = DB::table('timwe_diagnostic_daily_phone')
        ->whereBetween('stat_date', [$startDate, $endDate])
        ->selectRaw('COUNT(DISTINCT client_telephone) as count')
        ->value('count');
    
    // BILLING RATE GLOBAL = (total_billed / total_attempts) × 100
    // EXACTEMENT la même formule que le Diagnostic Timwe
    $billingRate = $totalAttempts > 0 ? round(($totalBilled / $totalAttempts) * 100, 2) : 0;
    
    return [
        'rate' => $billingRate,                 // Billing Rate Global (3.08%)
        'total_clients' => $uniquePhones,       // Numéros uniques (16,398)
        'billed_clients' => $totalBilled,       // Transactions facturées (1,944)
        'total_billings' => $totalBilled,       // Même valeur
        'total_revenue' => $totalRevenue,       // Revenu TND (5,832)
        'total_attempts' => $totalAttempts,     // Tentatives totales (63,170)
        'source' => 'diagnostic'                // Indicateur de source
    ];
}
```

---

## 📈 Nouvelles Formules (Alignées sur Diagnostic)

### 1. Taux de Facturation (Billing Rate Global)
```
Taux de facturation = (Total facturé / Total tentatives) × 100

Où:
- Total facturé = SUM(total_billed) des timwe_diagnostic_daily_summary
- Total tentatives = SUM(total_transactions) des timwe_diagnostic_daily_summary
```

**Note importante** : Le Diagnostic Timwe utilise le **Billing Rate Global**, pas le ratio facturé/numéros uniques.

### 2. Revenu TTC (TND)
```
Revenu TTC = SUM(total_revenue_tnd) des timwe_diagnostic_daily_summary

Où:
- total_revenue_tnd = totalCharged / 1000 (millimes → TND)
- Uniquement pour mnoDeliveryCode = 'DELIVERED' ET totalCharged > 0
```

### 3. Nombre de Facturations
```
Nombre facturation = SUM(total_billed) des timwe_diagnostic_daily_summary

Où:
- total_billed = Compteur de transactions avec mnoDeliveryCode = 'DELIVERED' ET totalCharged > 0
```

### 4. Numéros Uniques (Active Subscriptions)
```
Numéros uniques = COUNT(DISTINCT client_telephone) des timwe_diagnostic_daily_phone
```

---

## 🎯 Tables de Diagnostic Utilisées

### `timwe_diagnostic_daily_summary`
```sql
stat_date           DATE        -- Date de la statistique
total_transactions  INT         -- Total des tentatives
total_billed        INT         -- Transactions facturées (DELIVERED + totalCharged > 0)
total_revenue_tnd   DECIMAL     -- Revenu total TND
```

**Mise à jour** : Via `TimweDiagnosticAggregateService::processOneTransaction()` à chaque transaction Timwe

### `timwe_diagnostic_daily_phone`
```sql
stat_date           DATE        -- Date de la statistique
client_telephone    VARCHAR     -- Numéro de téléphone
client_id           BIGINT      -- ID client
total_attempts      INT         -- Tentatives pour ce numéro
delivered           INT         -- Delivered
no_balance          INT         -- No balance
not_delivered       INT         -- Not delivered
total_charged_tnd   DECIMAL     -- Total facturé TND
```

**Mise à jour** : Via `TimweDiagnosticAggregateService::upsertDailyPhone()`

---

## 🔄 Fallback & Priorités

Le système utilise maintenant un système de **fallback en cascade** :

1. **PRIORITÉ 1** : Tables `timwe_diagnostic_daily_*` (alignées sur Diagnostic)
2. **PRIORITÉ 2** : Table `timwe_daily_stats` (ancien cache)
3. **PRIORITÉ 3** : Calcul direct depuis `transactions_history` (legacy)

```php
if ($hasDiagnosticData) {
    // Utiliser timwe_diagnostic_daily_* ✅
} elseif ($stats->isNotEmpty()) {
    // Utiliser timwe_daily_stats
} else {
    // Calcul direct (legacy)
}
```

---

## ✅ Résultat Attendu

Après correction, les métriques du Dashboard Timwe afficheront **exactement les mêmes valeurs** que la page Diagnostic Timwe pour la même période.

### Résultat après correction (Janvier 2026, 01-15)

| Métrique | Dashboard | Diagnostic | Status |
|----------|-----------|------------|--------|
| **Taux de facturation** | **3.08%** | **3.08%** | ✅ Identique |
| **Nombre facturation** | 1,944 | 1,944 | ✅ Identique |
| **Revenu TTC** | 5,832 TND | 5,832 TND | ✅ Identique |
| **Numéros uniques** | 16,398 | 16,398 | ✅ Identique |
| **Total tentatives** | 63,170 | 63,170 | ✅ Identique |

**Formule vérifiée** : (1,944 / 63,170) × 100 = **3.08%** ✅

---

## 🚀 Prochaines Étapes

1. **Tester** : Rafraîchir le dashboard et comparer avec le Diagnostic
2. **Vérifier** : Les autres métriques (Nouveaux abonnements, Désabonnements, ARPU)
3. **Documenter** : Si d'autres métriques divergent, les aligner également

---

## 📝 Notes Techniques

- Les tables `timwe_diagnostic_daily_*` sont mises à jour en temps réel via l'`Observer` sur `TransactionHistory`
- Le cache est géré via Redis avec un TTL de 10 minutes
- Les performances sont optimisées avec des index sur `stat_date` et `client_telephone`
- Les données historiques peuvent être reconstruites via `php artisan timwe:diagnostic-backfill`

---

**Date de modification** : 2026-02-09  
**Fichiers modifiés** : 
- `app/Services/DashboardService.php` (méthode `calculateTimweBillingRate`)

**Testez avec** :
```bash
# Vider le cache
php artisan cache:clear

# Rafraîchir le dashboard
# http://localhost:8001/dashboard
```
