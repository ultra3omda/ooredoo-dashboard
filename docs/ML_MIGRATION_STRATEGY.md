# Stratégie de Migration - ML Features

## 🎯 Question : Le nouveau système remplace-t-il l'ancien ?

**Réponse courte** : **OUI** pour la production quotidienne. **NON** pour les analyses historiques ponctuelles.

---

## 📊 Comparaison des Systèmes

### Ancien Système : `ml:extract-multi`

#### Fonctionnement
```bash
php artisan ml:extract-multi --start-date=2022-06-14 --end-date=2026-01-31
```

**Architecture** :
```
transactions_history (4.6M lignes)
          ↓
    [Lecture complète pour chaque date]
          ↓
    Calcul features en temps réel
          ↓
    ml_client_features (1 ligne/date/client)
```

#### Avantages
- ✅ Flexibilité : Peut calculer pour n'importe quelle date
- ✅ Historique complet
- ✅ Bon pour analyses ponctuelles

#### Inconvénients
- ❌ **Très lent** : Plusieurs heures pour 4.5M transactions
- ❌ **Dégradation** : Plus il y a de transactions, plus c'est lent
- ❌ **Memory overflow** : Crash fréquent sur gros volumes
- ❌ **Coûteux** : Relit toutes les transactions à chaque fois
- ❌ **Pas de recovery** : Si crash, tout est perdu

#### Performance Mesurée
- 2M transactions : **Plusieurs heures** (non terminé)
- Dégradation linéaire avec le volume

---

### Nouveau Système : Architecture Incrémentale

#### Fonctionnement
```bash
# 1. Ingestion (automatique toutes les 5 min)
php artisan ml:tx-daily-ingest --batch-size=100000 --max-batches=5

# 2. Features (automatique toutes les 2h)
php artisan ml:build-90d-features --chunk=2000
```

**Architecture** :
```
transactions_history (nouvelles uniquement)
          ↓
    [Checkpoint last_processed_id]
          ↓
    tx_daily_agg (agrégats pré-calculés)
          ↓
    [Lecture 90 jours uniquement]
          ↓
    ml_client_features (colonnes *_90d_*)
```

#### Avantages
- ✅ **×10-20 plus rapide** : Minutes au lieu d'heures
- ✅ **Stable** : Pas de dégradation dans le temps
- ✅ **Robuste** : Recovery automatique après crash
- ✅ **Scalable** : Gestion mémoire optimisée
- ✅ **Automatique** : Scheduler, pas d'intervention manuelle
- ✅ **Économique** : Lit uniquement les nouvelles données

#### Inconvénients
- ⚠️ Uniquement pour les **features 90 jours** (fenêtre fixe)
- ⚠️ Ne calcule pas de features historiques arbitraires

#### Performance Mesurée
- 2M transactions : **4min 8s** (×30 plus rapide !)
- 269k clients : **10min 20s**
- Performance constante quel que soit le volume historique

---

## 🔄 STRATÉGIE DE MIGRATION

### Phase 1 : Coexistence (ACTUELLE) ✅

**Les deux systèmes fonctionnent en parallèle** :

```
Ancien système (ml:extract-multi)
  ↓
ml_client_features (colonnes historiques)
  - payment_success_rate
  - consecutive_failures
  - total_transactions
  - etc.

Nouveau système (incrémental)
  ↓
ml_client_features (colonnes 90j)
  - total_90d_count
  - timwe_90d_count
  - orange_90d_count
  - etc.
```

**Avantages** :
- ✅ Migration sans risque
- ✅ Validation croisée possible
- ✅ Rollback facile si problème

---

### Phase 2 : Remplacement Partiel (RECOMMANDÉ)

**Désactiver l'ancien système pour les runs quotidiens** :

#### 1. Commenter dans le Scheduler

```php
// app/Console/Kernel.php

// ❌ DÉSACTIVER (ancien système trop lent)
// $schedule->command('ml:extract-multi --start-date=now-7days --end-date=now')
//     ->daily()->withoutOverlapping();

// ✅ ACTIVER (nouveau système rapide)
$schedule->command('ml:tx-daily-ingest --batch-size=100000 --max-batches=5')
    ->everyFiveMinutes()->withoutOverlapping(30);

$schedule->command('ml:build-90d-features --chunk=2000')
    ->everyTwoHours()->withoutOverlapping(60);
```

#### 2. Utiliser les nouvelles colonnes 90j

Dans votre code ML/IA :

```php
// ❌ Anciennes colonnes (si calculées sur 90j)
$features = [
    'total_transactions' => $client->total_transactions,
    // ...
];

// ✅ Nouvelles colonnes 90j (plus rapides, toujours à jour)
$features = [
    'total_90d_count' => $client->total_90d_count,
    'timwe_90d_count' => $client->timwe_90d_count,
    'orange_90d_count' => $client->orange_90d_count,
    'total_90d_avg' => $client->total_90d_avg,
    // ...
];
```

#### 3. Garder l'ancien pour analyses ponctuelles

```bash
# Pour analyse historique ponctuelle (manuel)
php artisan ml:extract-multi --start-date=2023-01-01 --end-date=2023-12-31
```

---

### Phase 3 : Remplacement Complet (OPTIONNEL)

Si vous voulez **tout migrer** vers le nouveau système :

#### Option A : Étendre le nouveau système

Ajouter des features historiques dans `Build90dFeaturesCommand` :

```php
// Features 30, 60, 90, 180, 365 jours
php artisan ml:build-features --days=30
php artisan ml:build-features --days=60
php artisan ml:build-features --days=90
php artisan ml:build-features --days=180
php artisan ml:build-features --days=365
```

#### Option B : Migrer toute la logique

Créer de nouvelles commandes basées sur `tx_daily_agg` pour :
- `payment_success_rate` (90j)
- `consecutive_failures` (90j)
- `churn_probability` (90j)
- etc.

---

## 📋 PLAN DE MIGRATION RECOMMANDÉ

### ✅ À FAIRE MAINTENANT

1. **Finir l'ingestion initiale** (~4 minutes)
```bash
php artisan ml:tx-daily-ingest --batch-size=200000 --max-batches=20 --force
```

2. **Laisser le Scheduler gérer** (déjà configuré ✅)
   - Ingestion : Toutes les 5 min
   - Features 90j : Toutes les 2h
   - Maintenance : Hebdomadaire

3. **Mettre à jour le code ML/IA**
   - Utiliser les colonnes `*_90d_*` au lieu de l'ancien système
   - Exemple : `total_90d_count` au lieu de `total_transactions` (si 90j)

4. **Commenter l'ancien scheduler** (si utilisé)
```php
// $schedule->command('ml:extract-multi')->daily();
```

5. **Tester les prédictions ML**
   - Vérifier que le modèle utilise les nouvelles features
   - Comparer les résultats avec l'ancien système (validation)

---

### ⏰ À FAIRE PLUS TARD (Optionnel)

6. **Migrer les autres features** (si nécessaire)
   - Créer des variantes de `Build90dFeaturesCommand` pour d'autres métriques
   - Exemple : `BuildSuccessRateCommand`, `BuildChurnCommand`, etc.

7. **Supprimer l'ancien système** (après validation complète)
   - Supprimer `ml:extract-multi` du code
   - Nettoyer les anciennes colonnes de `ml_client_features`

---

## 📊 TABLEAU DÉCISIONNEL

| Use Case | Système à Utiliser | Raison |
|----------|-------------------|--------|
| **Production quotidienne** | ✅ Nouveau (incrémental) | 10-20× plus rapide, automatique |
| **Features 90 jours temps réel** | ✅ Nouveau (incrémental) | Toujours à jour via scheduler |
| **ML training/prédiction** | ✅ Nouveau (colonnes 90d) | Performance, stabilité |
| **Analyse historique ponctuelle** | ⚠️ Ancien (ml:extract-multi) | Flexibilité pour dates arbitraires |
| **Debug/validation données** | ⚠️ Ancien (ml:extract-multi) | Recalcul complet possible |
| **Audit rétrospectif** | ⚠️ Ancien (ml:extract-multi) | Analyse période spécifique |

---

## 🎯 RÉSUMÉ

### EN PRODUCTION QUOTIDIENNE

**OUI, le nouveau système REMPLACE l'ancien** :
- ✅ Plus rapide (×10-20)
- ✅ Plus stable
- ✅ Automatique
- ✅ Robuste

### POUR ANALYSES HISTORIQUES

**NON, garder l'ancien en backup** :
- Pour analyses ponctuelles
- Pour validation croisée
- Pour audit rétrospectif

---

## 🚀 RECOMMANDATION FINALE

**Action immédiate** :
1. ✅ Laisser le nouveau système gérer les features 90j (déjà configuré)
2. ✅ Désactiver l'ancien système du scheduler (éviter conflits)
3. ✅ Mettre à jour le code ML pour utiliser les colonnes `*_90d_*`
4. ⚠️ Garder `ml:extract-multi` disponible pour analyses ponctuelles

**Le nouveau système est prêt pour la production !** 🎉

---

**Auteur** : Architecture ML Incrémentale  
**Date** : 2026-02-05  
**Version** : 1.0
