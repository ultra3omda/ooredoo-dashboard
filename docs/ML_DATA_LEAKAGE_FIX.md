# 🚨 Data Leakage Détecté dans le Modèle ML

## ❌ Problème Actuel

### Erreur Critique : Data Leakage
Le modèle obtient **AUC = 1.0000 (100%)** - Ce n'est PAS une bonne nouvelle !

### Explication du Leakage

**Cible actuelle** (ligne 106-121 de `train_model.py`) :
```python
# Prédire : "Au moins un opérateur avec succès > 20%"
target = (timwe_success_rate > 0.2) OR (eklektik_success_rate > 0.2) OR (ooredoo_success_rate > 0.2)
```

**Features utilisées** :
```python
features = [
    'timwe_success_rate',      # ← LEAKAGE ! Contient directement la cible
    'eklektik_success_rate',   # ← LEAKAGE ! Contient directement la cible  
    'ooredoo_success_rate',    # ← LEAKAGE ! Contient directement la cible
    ...
]
```

**Résultat** : Le modèle "triche" en regardant directement les taux de succès pour prédire... les taux de succès !

---

## ✅ Solution : Prédiction Time-Series Correcte

### Nouveau Design

**Objectif** : Prédire le **succès du PROCHAIN paiement** (J+7 ou J+30) en se basant uniquement sur le **comportement PASSÉ**.

### Architecture Temporelle

```
┌─────────────────────────────────────────────────────────────┐
│  PÉRIODE HISTORIQUE (features)     │  PÉRIODE FUTURE (cible) │
│  J-90 ────────────────────────> J  │  J ──────────> J+30     │
│                                     │                          │
│  Features calculées sur J-90 à J    │  Mesurer succès J à J+30│
│  (comportement passé)               │  (ce qu'on veut prédire)│
└─────────────────────────────────────────────────────────────┘
```

### Nouvelle Structure de Données

Au lieu d'une seule date par client, nous devons créer des paires :
- **Features au temps T** (basées sur J-90 à J)
- **Label au temps T+30** (succès sur les 30j suivants)

### Exemples

| Client | Date Features | Features (J-90 à J) | Date Label | Label (J à J+30) |
|--------|---------------|---------------------|------------|------------------|
| 1001 | 2025-01-01 | timwe_attempts_past=10, timwe_success_past=0.3 | 2025-01-31 | had_success=1 |
| 1001 | 2025-02-01 | timwe_attempts_past=12, timwe_success_past=0.25 | 2025-03-01 | had_success=0 |

---

## 🔧 Implémentation

### Étape 1 : Créer une nouvelle table `ml_training_samples`

```sql
CREATE TABLE ml_training_samples (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    client_id BIGINT NOT NULL,
    feature_date DATE NOT NULL,      -- Date des features (J)
    target_date DATE NOT NULL,       -- Date du label (J+30)
    
    -- Features HISTORIQUES (J-90 à J, ne révèlent PAS le futur)
    timwe_past_attempts INT,
    timwe_past_successes INT,
    timwe_past_failures INT,
    timwe_past_avg_success_rate DECIMAL(8,4),
    
    eklektik_past_attempts INT,
    eklektik_past_successes INT,
    ooredoo_past_attempts INT,
    ooredoo_past_successes INT,
    
    days_since_last_success INT,
    consecutive_failures_count INT,
    total_past_revenue DECIMAL(12,3),
    engagement_trend VARCHAR(20),  -- increasing, stable, decreasing
    
    -- Label FUTUR (succès entre J et J+30)
    had_success_next_30d TINYINT,   -- 0 ou 1
    
    INDEX idx_client_feature_date (client_id, feature_date),
    INDEX idx_target (had_success_next_30d)
);
```

### Étape 2 : Commande pour construire les samples

```bash
php artisan ml:build-training-samples --start-date=2021-04-12 --end-date=2026-01-01
```

Cette commande va :
1. Pour chaque client et chaque date J
2. Calculer features sur [J-90, J]
3. Calculer label sur [J, J+30]
4. Insérer dans `ml_training_samples`

### Étape 3 : Entraîner sans leakage

```bash
php artisan ml:export-training-samples
python ml_models/train_model_v2.py --data=storage/ml_training_samples.csv
```

---

## 📊 Résultats Attendus

Avec cette approche correcte :
- **AUC attendu** : 0.65 - 0.85 (réaliste)
- **Accuracy** : 70-80%
- **Pas de leakage** : Features ne révèlent pas le futur

---

## 🚀 Prochaines Actions

1. Créer `ml_training_samples` table
2. Créer commande `ml:build-training-samples`
3. Adapter `train_model.py` pour cette nouvelle structure
4. Ré-entraîner et valider

**Voulez-vous que je procède immédiatement ?**
