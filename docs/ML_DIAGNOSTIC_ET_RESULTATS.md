# Diagnostic et résultats du modèle ML

## Comment tester le modèle (guide rapide)

### Prérequis
- **Données** : la table `ml_client_features` doit contenir des lignes (extraction multi-opérateur).
- **Python** : installé et accessible (sous Windows, optionnel dans `.env` : `PYTHON_PATH=python` ou `py`).

### Ordre recommandé

1. **Vérifier les données** (features extraites)  
   ```bash
   php artisan ml:verify-features
   ```  
   Si la table est vide :  
   ```bash
   php artisan ml:extract-multi --start-date=YYYY-MM-DD --end-date=YYYY-MM-DD
   ```

2. **Diagnostic du modèle** (fichier .pkl, Python, libs, test de prédiction)  
   ```bash
   php artisan ml:diagnose-model
   ```  
   Si le fichier modèle est absent :  
   ```bash
   php artisan ml:train-python
   ```  
   Puis relancer `php artisan ml:diagnose-model`.

3. **Test complet du système** (données, extraction, prédictions, recommandations, suggestions stratégie)  
   ```bash
   php artisan ml:test-system
   ```  
   Option : tester un client précis :  
   ```bash
   php artisan ml:test-system --client-id=123
   ```

4. **Tester depuis l’interface**  
   - Aller sur le **dashboard ML** (admin).  
   - Cliquer sur **« Actualiser »** pour charger prédictions et suggestions.  
   - Lancer **« Nouvelles Recommandations »** pour générer (et voir) les suggestions acquisition / conversion / facturation.

### Résumé des commandes de test

| Commande | Rôle |
|----------|------|
| `php artisan ml:verify-features` | Vérifie le contenu de `ml_client_features` (lignes, dates). |
| `php artisan ml:diagnose-model` | Vérifie le .pkl, Python, libs et fait une prédiction test. |
| `php artisan ml:train-python` | Entraîne le modèle et crée `billing_predictor_v3.pkl`. |
| `php artisan ml:test-system` | Test complet (données, extraction, prédictions, recommandations). |
| `php artisan ml:test-system --client-id=XXX` | Même test sur un client donné. |
| `php artisan ml:test-system --skip-extract` | Test sans lancer l’extraction (évite deadlock si un autre job écrit dans `ml_client_features`). |

### Erreur « Deadlock » (1213) à l’extraction

Si `ml:test-system` ou une extraction affiche **1213 Deadlock found when trying to get lock** :

- Les upserts dans `ml_client_features` **réessaient jusqu’à 3 fois** en cas de deadlock (délai 100–300 ms).
- La taille des lots en mode synchrone est réduite (défaut **50** lignes, configurable via `ML_EXTRACTION_SYNC_BATCH_SIZE` dans `.env`).
- En parallèle d’un autre job d’extraction (ex. `ml:extract-multi`), lancer le test **sans** extraction :  
  `php artisan ml:test-system --skip-extract`.

---

## État actuel

- **Fichier modèle** : `ml_models/billing_predictor_v3.pkl` (créé par `ml_models/train_model.py`).
- **Pipeline utilisé en prédiction** : `MLPredictionService` → `MLPythonBridgeService` → `ml_models/predict.py` + le fichier `.pkl`.
- Les features envoyées par PHP ont été alignées sur les colonnes de `train_model.py` (FEATURE_COLUMNS + CAT_COLUMNS) pour que les prédictions soient cohérentes.

## Pourquoi "pas de résultat" a pu apparaître

1. **Features désalignées** : Le PHP envoyait un ancien jeu de colonnes (ex. `consecutive_failures`, `total_payments`, …) alors que le modèle a été entraîné avec les colonnes multi-opérateur (Timwe, Eklektik, Ooredoo). C’est corrigé : `buildFeatureArrayForPython()` envoie maintenant exactement les colonnes attendues par le `.pkl`.
2. **Modèle absent** : Si `billing_predictor_v3.pkl` n’existait pas, `isModelAvailable()` était faux et le système tombait en prédiction rule-based (sans vrai modèle). Il suffit d’exécuter une fois `php artisan ml:train-python` pour générer le fichier.
3. **Python / chemin** : Sous Windows, si `python3` n’est pas dans le PATH, définir `PYTHON_PATH=python` (ou `py`) dans `.env`.

## Commandes utiles

| Commande | Rôle |
|----------|------|
| `php artisan ml:diagnose-model` | Vérifie la présence du `.pkl`, Python, les libs et fait un test de prédiction. |
| `php artisan ml:train-python` | Entraîne le modèle (charge les données depuis `ml_client_features`, sauvegarde `billing_predictor_v3.pkl`). |
| `php artisan ml:test-system` | Test complet (données, extraction, prédictions, recommandations). |
| `php artisan ml:verify-features` | Vérifie le contenu de `ml_client_features` (lignes, dates, opérateurs). |

## AUC / accuracy à 1.0 = anormal (data leakage)

Si l’entraînement affiche **AUC-ROC: 1.0000** et **accuracy 100 %**, c’est en général dû à une **fuite de données** : la cible est calculée à partir des mêmes colonnes que les features (ex. `timwe_success_rate` et `timwe_has_activity` définissent la cible et étaient aussi en entrée). Le script `train_model.py` a été corrigé : les colonnes qui définissent directement la cible (`LEAKING_COLUMNS`) sont **exclues des features** d’entraînement. Après correction, réentraînez avec `php artisan ml:train-python` : vous devriez obtenir une AUC plus réaliste (ex. 0.65–0.85).

## Améliorations possibles du modèle

1. **Réentraînement régulier** : Relancer `php artisan ml:train-python` après de gros changements de données (ex. hebdomadaire ou après une grosse extraction).
2. **Cible (label)** : Dans `train_model.py`, la cible est « au moins un opérateur avec succès (taux > 0.2 et activité) ». On peut affiner plus tard (ex. cible = succès au prochain mois, ou par opérateur).
3. **Données** : Le script Python charge la dernière `calculation_date` par client depuis `ml_client_features`. S’assurer que `ml:extract-multi` (ou équivalent) tourne pour alimenter cette table.
4. **Validation** : Utiliser `php artisan ml:validate --model=lightgbm_billing_predictor` après entraînement pour comparer aux prédictions enregistrées (table `ml_predictions` si utilisée).

## Vérification rapide

Après correction des features et présence du `.pkl` :

```bash
php artisan ml:diagnose-model
```

Vous devez voir au moins :
- Fichier modèle présent
- Python + joblib (et si possible lightgbm, sklearn) OK
- Prédiction test OK avec une probabilité et un `model_version` cohérent.

Si une erreur apparaît à l’étape « Test de prédiction », vérifier les logs Laravel et éventuellement l’exécution de `predict.py` à la main avec un JSON de features pour isoler l’erreur.
