# 🏗️ Architecture Hybride ML - Guide d'utilisation

## 📐 Architecture

### Deux tables complémentaires :

```
┌─────────────────────────────────────────────────────────────┐
│                   ml_client_features                         │
│  🚀 PRODUCTION - Dernières features par client              │
│     Usage : Prédictions temps réel, dashboard, API          │
│     Volume : ~150K lignes (1 par client actif)              │
│     MAJ : Quotidienne (automatique)                          │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│             ml_client_features_training                      │
│  🎓 ENTRAÎNEMENT ML - Échantillon historique               │
│     Usage : Entraînement modèles, backtesting               │
│     Volume : ~10M lignes (échantillon hebdo/mensuel)        │
│     MAJ : Manuelle (selon besoin)                            │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│           ml_client_features_current (VUE)                   │
│  📊 ALIAS - Vue simplifiée sur ml_client_features           │
│     Usage : Queries simplifiées pour état actuel            │
└─────────────────────────────────────────────────────────────┘
```

---

## 🚀 Mise en place

### 1. Créer les tables

```bash
# Créer la nouvelle architecture
php artisan migrate

# Vérifier les tables
php artisan tinker --execute="dd(Schema::hasTable('ml_client_features_training'))"
```

### 2. Optimiser les données existantes

```bash
# MODE DRY-RUN (simulation, aucune modification)
php artisan ml:optimize-historical --dry-run --strategy=hybrid

# EXÉCUTION RÉELLE (après validation)
php artisan ml:optimize-historical --strategy=hybrid
```

**Ce que fait cette commande :**

1. ✅ **Copie l'échantillon** → `ml_client_features_training`
   - Lundis de chaque semaine
   - 1er et dernier jour de chaque mois
   - ~85% de réduction de volume

2. 🧹 **Nettoie** `ml_client_features`
   - Garde uniquement la dernière date par client
   - Supprime tout l'historique quotidien

3. ⚡ **Optimise** les tables
   - `ANALYZE TABLE` pour les statistiques
   - `OPTIMIZE TABLE` pour la performance

---

## 📊 Utilisation des tables

### A. Pour la PRODUCTION (prédictions temps réel)

```php
// Récupérer les features actuelles d'un client
$features = DB::table('ml_client_features')
    ->where('client_id', $clientId)
    ->first();

// Ou via la vue (équivalent)
$features = DB::table('ml_client_features_current')
    ->where('client_id', $clientId)
    ->first();

// Prédire le risque de churn
$prediction = MLPredictionService::predictChurnRisk($clientId);
```

### B. Pour l'ENTRAÎNEMENT ML

```php
// Récupérer l'historique d'entraînement
$trainingData = DB::table('ml_client_features_training')
    ->whereBetween('calculation_date', ['2021-01-01', '2025-12-31'])
    ->where('client_segment', '!=', 'unknown')
    ->get();

// Filtrer par type d'échantillon
$weeklyData = DB::table('ml_client_features_training')
    ->where('sample_type', 'weekly')
    ->get();

$monthlyData = DB::table('ml_client_features_training')
    ->where('sample_type', 'monthly')
    ->get();

// Entraîner le modèle
python ml_models/train_model.py --data-source=ml_client_features_training
```

### C. Maintenir l'architecture

```bash
# 1. MISE À JOUR QUOTIDIENNE (automatique via cron)
php artisan ml:extract-multi --batch-days=1

# ➜ Met à jour ml_client_features avec les dernières features

# 2. AJOUT PÉRIODIQUE À L'HISTORIQUE D'ENTRAÎNEMENT (manuel, 1x/semaine)
php artisan tinker --execute="
DB::table('ml_client_features_training')->insert(
    DB::table('ml_client_features')
        ->select([
            'client_id', 'calculation_date',
            'timwe_success_rate', 'timwe_total_attempts', 'timwe_has_activity',
            'eklektik_success_rate', 'eklektik_total_attempts', 'eklektik_has_activity',
            'ooredoo_success_rate', 'ooredoo_total_attempts', 'ooredoo_has_activity',
            'total_90d_count', 'total_90d_sum', 'total_90d_avg', 'last_tx_90d_at',
            'timwe_90d_count', 'timwe_90d_sum', 'eklektik_90d_count', 'eklektik_90d_sum',
            'ooredoo_90d_count', 'ooredoo_90d_sum',
            'best_performing_operator', 'total_operators_used',
            'price_preference', 'preferred_frequency', 'client_segment',
            'payment_reliability_score', 'engagement_score', 'lifetime_value_score',
            DB::raw(\"'weekly' as sample_type\"),
            DB::raw('NOW() as created_at'),
            DB::raw('NOW() as updated_at')
        ])
        ->get()
        ->toArray()
);
"

# 3. RÉ-ENTRAÎNEMENT MENSUEL
php artisan ml:train --source=training
```

---

## 📈 Avantages de cette architecture

### ✅ Performance

| Métrique | Avant | Après | Gain |
|----------|-------|-------|------|
| **Volume Production** | 73M lignes | 150K lignes | **99.8%** ↓ |
| **Temps Query** | 5-10s | <100ms | **50-100x** ↑ |
| **Taille DB** | 110 GB | 2 GB | **98%** ↓ |
| **Backup Time** | 2h | 5min | **24x** ↑ |

### ✅ Maintenance

- **Production** : Toujours à jour automatiquement
- **Training** : Échantillon représentatif sans overhead
- **Flexibilité** : Ajout de nouvelles dates d'entraînement sans impact production

### ✅ ML Quality

- **Temporal Coverage** : Historique complet (2021-2026)
- **Pattern Detection** : Tendances long-terme préservées
- **No Overfitting** : Échantillonnage réduit le biais de sur-ajustement

---

## 🔄 Workflow de maintenance

### Quotidien (automatique)
```bash
# Cron : 0 2 * * *
php artisan ml:extract-multi --batch-days=1
# ➜ Met à jour ml_client_features
```

### Hebdomadaire (semi-automatique)
```bash
# Cron : 0 3 * * 1  (Lundi 3h)
php artisan ml:append-to-training --last-week
# ➜ Ajoute la semaine passée à ml_client_features_training
```

### Mensuel (manuel)
```bash
# Ré-entraîner le modèle
php artisan ml:train --source=training --validate
```

---

## 🎯 Prochaines étapes

### 1. Créer la commande d'ajout incrémental
```bash
php artisan make:command AppendToMLTrainingCommand
```

### 2. Implémenter les hooks dans ExtractMultiOperatorFeaturesCommand
```php
// À la fin de l'extraction quotidienne
if (Carbon::parse($currentDate)->isDayOfWeek(Carbon::MONDAY)) {
    $this->call('ml:append-to-training', ['--last-week' => true]);
}
```

### 3. Mettre à jour le script d'entraînement Python
```python
# ml_models/train_model.py
def load_training_data():
    # Utiliser ml_client_features_training au lieu de ml_client_features
    query = "SELECT * FROM ml_client_features_training WHERE calculation_date >= ?"
    return pd.read_sql(query, engine, params=['2021-01-01'])
```

---

## 🔍 Monitoring

### Vérifier les volumes
```sql
-- Production (doit être ~ nombre de clients actifs)
SELECT COUNT(*), COUNT(DISTINCT client_id) 
FROM ml_client_features;

-- Training (doit être ~ 15% de l'historique complet)
SELECT 
    COUNT(*) as total,
    COUNT(DISTINCT client_id) as clients,
    COUNT(DISTINCT calculation_date) as dates,
    sample_type,
    MIN(calculation_date) as date_min,
    MAX(calculation_date) as date_max
FROM ml_client_features_training
GROUP BY sample_type;
```

### Vérifier la fraîcheur
```sql
-- Dernière MAJ production
SELECT MAX(calculation_date), MAX(updated_at)
FROM ml_client_features;

-- Dernière MAJ training
SELECT MAX(calculation_date), MAX(updated_at)
FROM ml_client_features_training;
```

---

## 📚 Documentation associée

- `ML_VOLUMETRIE_ET_SCALABILITE.md` : Analyse volumétrie
- `ML_ET_AGENT_IA_PROCESS.md` : Processus ML global
- `ML_INDEXES_OPTIMIZATION.md` : Optimisation indexes

---

## 🆘 Troubleshooting

### La table training est vide
```bash
php artisan ml:optimize-historical --strategy=hybrid
```

### Les prédictions sont lentes
```sql
-- Vérifier les index
SHOW INDEX FROM ml_client_features;
ANALYZE TABLE ml_client_features;
```

### Ré-entraînement ne converge pas
```bash
# Augmenter l'échantillon de training
php artisan ml:optimize-historical --strategy=hybrid --sample-rate=0.20
```

---

**Créé le** : 2026-02-06  
**Auteur** : Ooredoo ML Team  
**Version** : 1.0
