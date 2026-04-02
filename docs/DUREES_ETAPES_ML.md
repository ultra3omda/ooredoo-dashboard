# Durées des étapes ML : extraction, apprentissage, A/B test

Ce document détaille **chaque étape** du pipeline ML (extraction sur 3 jours, apprentissage, A/B test), **comment elle fonctionne** et **combien de temps** elle prend.

---

## 1. Extraction des features (3 jours)

### Ce que fait l’étape

- **Commande** : `php artisan ml:extract-features --start-date=YYYY-MM-DD --end-date=YYYY-MM-DD`
- Pour **chaque jour** de la période (ex. 3 jours), le système :
  1. Récupère la liste des **clients actifs** à cette date (abonnements Timwe non expirés) via `getActiveClientIds()`.
  2. Pour **chaque client**, calcule toutes les features ML :
     - Historique de paiement (tentatives, succès, montants, échecs consécutifs)
     - Patterns de solde
     - Patterns temporels (meilleur jour/heure de facturation)
     - Comportement d’usage, scores de risque, features avancées v2 (taux matin/après-midi/soir, récupération après échec, etc.)
  3. Utilise un **cache** (1 h) pour les stats de transactions par client/période afin d’éviter de refaire les mêmes requêtes.
  4. Enregistre le résultat dans la table **`ml_client_features`** (une ligne par `client_id` + `calculation_date`).

### Modes d’exécution

| Mode | Option | Comportement |
|------|--------|--------------|
| **Synchrone** | (défaut) | Traite les clients par **batch de 100** dans le même processus. Une pause de **2 secondes** entre chaque batch de jours pour limiter la charge. |
| **Queue** | `--use-queue` | Envoie les clients par **chunks de 500** (configurable via `ML_EXTRACTION_CHUNK_SIZE`) dans la queue `ml-extraction`. Le traitement réel dépend des workers (`php artisan queue:work --queue=ml-extraction`). |

### Durée estimée pour **3 jours**

- **Nombre de clients** : dépend de votre base (ordre de grandeur typique : **~30k–85k** clients actifs/jour).
- **Synchrone** :
  - Par client : ~0,1–0,5 s (requêtes SQL + calculs ; moins si cache).
  - Exemple : 50 000 clients/jour × 3 jours = 150 000 clients, batch 100 → 1 500 batches.  
  - **Estimation** : environ **30 min à 2 h** selon volume et performance BDD (sans queue).
- **Avec queue** :
  - L’envoi des jobs est **quasi instantané** (quelques secondes).
  - La durée réelle = temps que mettent les workers à traiter tous les chunks (ex. 5 workers → diviser le temps synchrone par ~5).  
  - **Estimation** : **~10–40 min** de temps “mur” si plusieurs workers.

### Commandes utiles

```bash
# Extraction 3 jours (ex. du 30 jan au 1er fév 2026)
php artisan ml:extract-features --start-date=2026-01-30 --end-date=2026-02-01

# Avec files d’attente (nécessite queue:work)
php artisan ml:extract-features --start-date=2026-01-30 --end-date=2026-02-01 --use-queue
```

---

## 2. Apprentissage du modèle (entraînement)

### Ce que fait l’étape

- **Commande** : `php artisan ml:train-python` (ou `ml:train` selon le flux).
- Le système :
  1. Lit les données depuis **`ml_client_features`** (derniers 90 jours, dernière date de calcul par client).
  2. Prépare la cible binaire : **succès** si `payment_success_rate > 0.3`, sinon échec.
  3. Lance l’entraînement **LightGBM** (arbres gradient boosting) avec :
     - Jusqu’à **200 rounds** (early stopping possible vers 20 rounds sans progrès)
     - Gestion du **déséquilibre** (scale_pos_weight)
     - Split train/test (par défaut 80 % / 20 %).
  4. Calcule AUC, précision, rappel, F1, seuil optimal, importance des features.
  5. Sauvegarde le modèle (fichier dans `storage/ml_models/`) et écrit les métriques dans **`ml_model_performance`**.

### Durée estimée

- Dépend du **nombre d’enregistrements** dans `ml_client_features` (souvent **dizaines de milliers** après une extraction 3 jours + historique déjà présent).
- LightGBM est très rapide : typiquement **1 à 5 minutes** pour 50k–100k lignes et ~25–30 features.
- La sortie affiche explicitement la durée, par ex. : `⏱️ Durée: 45.2s` et `training_duration_minutes` dans les résultats JSON.

### Commande

```bash
php artisan ml:train-python
```

---

## 3. A/B test

### Ce que fait l’étape

- **Création** : `php artisan ml:ab-test --name="..." --participants=5000 --days=7 --segment=high_risk --price=0.3 --frequency=daily`
- Le système :
  1. Crée une entrée dans **`ml_ab_tests`** (id du test, nom, dates de début/fin, stratégies contrôle/traitement, etc.).
  2. Récupère des **client_id** (ex. segment `high_risk` dans `ml_client_features`).
  3. Répartit ces clients en deux groupes (control / treatment) et insère les lignes dans **`ml_ab_test_participants`**.

### Durée de cette étape (“création”)

- **Quelques secondes** (ex. **2–10 s**) : requêtes SQL + insert en masse. Vous avez déjà vu ~4 s pour 5 000 participants.

### “Durée” du test côté métier

- Le test **s’exécute** pendant **7 jours calendaires** (ou la valeur passée en `--days=7`). Pendant cette période :
  - Les prédictions / facturations réelles utilisent le groupe (control vs treatment) assigné.
  - Les résultats sont collectés au fil du temps (succès, montants, etc.).
- Il n’y a **pas de calcul long** à la création : le “temps” du test est surtout **attendre la fin des 7 jours** pour analyser les résultats.

### Commandes utiles

```bash
# Créer un test (ex. déjà fait)
php artisan ml:ab-test --name="A/B test quotidien 0.3 TND high_risk" --participants=5000 --days=7 --segment=high_risk --price=0.3 --frequency=daily

# Lister les tests
php artisan ml:ab-test --list
```

---

## Récapitulatif des durées (ordre de grandeur)

| Étape | Ce qui se passe | Durée typique |
|-------|-----------------|---------------|
| **Extraction 3 jours** | Calcul des features pour tous les clients actifs, 1 jour par 1 jour, avec ou sans queue | **30 min – 2 h** (synchrone) ou **~10–40 min** (queue + workers) |
| **Apprentissage** | Lecture `ml_client_features` → entraînement LightGBM → sauvegarde modèle + métriques | **1–5 min** |
| **Création A/B test** | Insertion test + répartition des participants en control/treatment | **2–10 s** |
| **“Exécution” A/B test** | Période pendant laquelle les 7 jours de test s’écoulent (pas un calcul continu) | **7 jours** (calendaire) |

### Enchaînement complet (sans les 7 jours d’attente)

1. **Extraction 3 jours** : ~30 min à 2 h (ou moins avec queue).
2. **Entraînement** : ~1–5 min.
3. **Création A/B test** : quelques secondes.

**Total calcul machine** : environ **35 min à 2 h 10** selon le volume et l’usage de la queue. Ensuite, le test A/B “tourne” pendant 7 jours avant d’analyser les résultats.
