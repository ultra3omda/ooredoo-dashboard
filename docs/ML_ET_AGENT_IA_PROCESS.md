# Processus ML et alimentation de l’agent IA

Ce document décrit ce qui est déjà en place et comment enchaîner **extraction des features → apprentissage → alimentation de l’agent IA** pour améliorer ses réponses. Le modèle doit couvrir **tous les types d’abonnement** (Timwe, Eklektik, DGV/Ooredoo), pas uniquement Timwe.

---

## 1. Ce qui est déjà implémenté et fonctionnel

### 1.1 Tables et données

| Table | Rôle |
|-------|------|
| **ml_client_features** | Features calculées par client et par date (historique). Une ligne = un client à une date de calcul. |
| **ml_predictions** | Prédictions (probabilité succès, prix optimal, etc.) par client/date. |
| **ml_recommendations** | Recommandations ML (pricing, timing, etc.). |
| **transactions_history** | Source des tentatives/facturations Timwe (et autres opérateurs). |
| **client_abonnement** | Utilisé pour savoir quels clients sont “actifs” à une date donnée. |

L’extraction multi-agrégateur lit **transactions_history** (tous statuts pertinents) et **client_abonnement** ; elle ne s’appuie pas sur les tables d’agrégation diagnostic pour les features.

**Prix et classification des abonnements** : la table **abonnement_tarifs** est liée à **client_abonnement** via `client_abonnement.tarif_id` = `abonnement_tarifs.abonnement_tarifs_id`. Elle fournit le **prix** (`abonnement_tarifs_prix`) et la **durée/fréquence** (`abonnement_tarifs_duration`, `abonnement_tarifs_frequence` en jours). L’extraction ML s’en sert pour : préférence prix (bas/élevé), nombre de prix distincts, et classification **quotidien vs mensuel**. En pratique, **seul Timwe est mensuel** (duration 28–31 ou nom "timwe") ; Eklektik (Orange, TT, Taraji) et offres avec duration 1 = quotidien.

**Agrégateurs et sources (modèle pour tous types d’abonnement) :**

| Agrégateur | Offres | Facturation | Source d’extraction |
|------------|--------|-------------|---------------------|
| **Timwe** | Ooredoo Privileges, abonnement **mensuel 3 TND** | 3 TND / mois | **transactions_history** : `status` TIMWE_RENEWED_NOTIF / TIMWE_CHARGE_DELIVERED ; **client_abonnement** : country_payments_methods "timwe". |
| **Eklektik** | TT, Orange, Taraji Privileges, DGV avec Ooredoo | **0,3 TND quotidien** | **transactions_history** : EKLEKTIK / CLUB_PRIVILEGE / DAILY ; **client_abonnement** : "eklektik" ou prix 0.3. Optionnel : eklektik_stats_daily. |
| **DGV / Ooredoo** | Plusieurs offres Ooredoo/DGV | **0,3 TND quotidien** | **transactions_history** : OOREDOO / DGV (ex. OOREDOO_PAYMENT_SUCCESS) ; **client_abonnement** : "ooredoo" ou "dgv". Optionnel : stats DGV. |

Le **même modèle ML** utilise **ml_client_features** avec colonnes par agrégateur (`timwe_success_rate`, `eklektik_success_rate`, `ooredoo_success_rate`, etc.). Utiliser l’**extraction multi-opérateur** (`ml:extract-multi`) pour tous les clients actifs, tous types d’abonnement.

### 1.2 Extraction des features

**Option A – Timwe uniquement** (`MLFeatureExtractionService`, commande `ml:extract-features`)  
- Clients : abonnements actifs **Timwe** à la date de calcul (country_payments_methods "timwe").  
- Source : **transactions_history** (statuts TIMWE_*).  
- Utile si tu ne veux que le périmètre Timwe ; **pour un modèle tous types d’abonnement, utiliser Option B**.

**Option B – Multi-agrégateur (recommandé pour modèle global)** (`MLMultiOperatorFeatureService`, commande `ml:extract-multi`)  
- Clients : **tous** les clients ayant un abonnement actif sur **au moins un** agrégateur (Timwe, Eklektik, Ooredoo/DGV) à la date de calcul.  
- Sources : **transactions_history** (TIMWE_*, EKLEKTIK_*, OOREDOO_*, DGV) + **client_abonnement** + **country_payments_methods**.  
- Features par agrégateur : Timwe (taux succès, tentatives, no_balance, etc.), Eklektik (taux succès, consistance quotidienne), Ooredoo/DGV (taux succès, consistance mensuelle), plus cross-opérateur (meilleur opérateur, préférence quotidien/mensuel, diversité).  
- Les résultats sont **upsertés** dans **ml_client_features** (clé `client_id` + `calculation_date`). Les colonnes multi-opérateur (timwe_*, eklektik_*, ooredoo_*) sont remplies pour chaque client.

**Période d’analyse** (A et B) : pour une `calculation_date`, on regarde les **6 mois précédents** (180 jours pour l’extraction batch).

#### Qui remplit quelles colonnes (éviter les confusions NULL / unknown)

La table **ml_client_features** a beaucoup de colonnes ; elles ne sont pas toutes remplies par le même job :

| Rempli par | Commande | Colonnes concernées |
|------------|----------|----------------------|
| **Option B – Multi-opérateur** | `ml:extract-multi`, `ml:reset-and-extract` | `client_id`, `calculation_date`, **timwe_***, **eklektik_***, **ooredoo_***, `total_operators_used`, `operator_diversity_score`, `price_preference`, `unique_price_points`, `prefers_low_price`, `prefers_high_price`, `is_multi_operator_user`, `daily_offers_count`, `monthly_offers_count`, `total_offers_count`, `daily_engagement_rate`, `monthly_engagement_rate`, `preferred_frequency`, `prefers_daily_offers`, `prefers_monthly_offers`, `is_frequency_flexible`, `best_performing_operator`, `created_at`, `updated_at`. |
| **Option A – Ancien pipeline** | `ml:extract-features` | `payment_success_rate`, `consecutive_failures`, `client_segment`, `churn_probability`, `engagement_score`, `lifetime_value_score`, `morning_success_rate`, `afternoon_success_rate`, `evening_success_rate`, `has_recent_failures`, `payment_reliability_score`, etc. |

Si vous n’exécutez que **Option B** (multi-opérateur), il est **normal** que les colonnes de l’Option A restent **NULL** ou à 0, et que `client_segment` soit **unknown** (ou NULL). Les valeurs **unknown** pour `price_preference` et `preferred_frequency` sont le défaut quand le client n’a aucun abonnement dans la fenêtre.

**Optionnel – remplir aussi segment et scores** : pour avoir `client_segment`, `churn_probability`, `engagement_score`, `morning/afternoon/evening_success_rate`, etc., il faut lancer l’extraction **Option A** (`ml:extract-features`) pour les mêmes dates. Elle cible principalement les clients Timwe ; les lignes sont mises à jour par upsert (même clé `client_id` + `calculation_date`), donc les colonnes multi-opérateur déjà remplies par Option B sont conservées.

#### Commandes utiles

| Action | Commande |
|--------|----------|
| Extraire sans ré-insérer les dates déjà traitées | `php artisan ml:extract-multi --start-date=YYYY-MM-DD --end-date=YYYY-MM-DD` |
| Forcer le recalcul d’une date | `php artisan ml:extract-multi --start-date=YYYY-MM-DD --end-date=YYYY-MM-DD --force` |
| Vider la table puis ré-extraire une période | `php artisan ml:reset-and-extract --start-date=YYYY-MM-DD --end-date=YYYY-MM-DD` |
| Vérifier le contenu (lignes, activité, timestamps) | `php artisan ml:verify-features` ou `php artisan ml:verify-features --date=YYYY-MM-DD` |
| Diagnostic détaillé pour un client | `php artisan ml:diagnose-features --client-id=XXX --date=YYYY-MM-DD` |

**Référence détaillée** : pour savoir colonne par colonne qui remplit quoi et si la table est correctement alimentée, voir [ML_CLIENT_FEATURES_COLUMNS_SOURCES.md](ML_CLIENT_FEATURES_COLUMNS_SOURCES.md).

**Si les features multi-opérateur sont à 0 / NULL** : les lignes ont souvent été créées lors d’une ancienne exécution où le code levait des erreurs (ex. colonne manquante), donc chaque client a reçu des valeurs par défaut (0). Il faut **ré-exécuter l’extraction avec `--force`** pour la date concernée afin d’écraser et recalculer. Exemple :  
`php artisan ml:extract-multi --start-date=2026-01-01 --end-date=2026-01-01 --force`

### 1.3 Apprentissage (entraînement)

- **Données** : uniquement **ml_client_features** (pas les tables brutes).
- **Cible (label)** : dérivée des features, ex. “a au moins un paiement réussi” (`payment_success_rate > 0` → 1, sinon 0).
- **Deux chemins possibles** :
  1. **PHP (LightGBM via export)** : `php artisan ml:train` → `MLModelTrainingService` prépare les données, exporte vers Python, exécute l’entraînement.
  2. **Python direct** : `php artisan ml:train-python` → `MLPythonBridgeService` appelle `ml_models/train_model.py` qui lit la base (config DB dans .env) et produit un modèle (ex. `billing_predictor_v3.pkl`).

Les deux s’attendent à avoir **suffisamment de lignes** dans `ml_client_features` (ordre de grandeur : au moins 50–100, mieux plusieurs milliers).

### 1.5 Agent IA

- **Contexte** : `AIContextProvider` lit **ml_client_features**, **ml_predictions**, **ml_recommendations**, etc.
- **Contenu injecté dans le prompt** (selon la question) :
  - **system** : segments, modèles ML, stratégies, performance globale, qualité des données (dont stats sur `ml_client_features`).
  - **kpis** : taux de succès, revenus, segments qui performent le mieux / le pire, etc.
  - **ml_features** : importance des features, complétude, dernières extractions (basé sur `ml_client_features`).
  - **client** : pour une question “client 12345”, profil depuis `MLClientFeature::getLatestForClient()` + prédictions + dernières transactions.
  - **recommendations**, **operators**, **segments**, **advanced_insights** : selon mots-clés.

Donc **oui** : mieux `ml_client_features` (et prédictions/recos) est rempli et à jour, plus l’agent a de données pertinentes et plus ses réponses peuvent être précises.

---

## 2. Faut-il faire l’extraction depuis le début des données ?

**Oui, c’est recommandé** si tu veux :

- Un historique de features le plus long possible pour l’entraînement (plus de dates = plus de “snapshots” par client).
- Des segments et KPIs cohérents sur toute la période.
- Que l’agent puisse s’appuyer sur des stats et tendances basées sur toute l’histoire disponible.

**Comment définir “le début” :**

- **Date de début** = première date pour laquelle tu as à la fois :
  - des abonnements actifs (**tous types** : client_abonnement lié à Timwe, Eklektik ou Ooredoo/DGV) ;
  - des transactions pour au moins un agrégateur (transactions_history : TIMWE_*, EKLEKTIK_*, OOREDOO_* ou DGV).
- Pour un **modèle tous types d’abonnement**, utilise **`ml:extract-multi`** avec `--start-date` / `--end-date`. Par défaut `ml:extract-multi` prend les 7 derniers jours si tu ne fournis pas de dates.

Pour chaque jour entre `--start-date` et `--end-date`, le système :

1. Considère ce jour comme `calculation_date`.
2. Prend les clients actifs à cette date.
3. Pour chaque client, calcule les features sur la période **[calculation_date - 6 mois ; calculation_date]**.
4. Enregistre une ligne dans `ml_client_features` (client_id, calculation_date, …).

Donc “extraction depuis le début” = lancer l’extraction avec une `--start-date` au premier jour où tu as des données exploitables, et `--end-date` à aujourd’hui (ou à la dernière date utile).

---

## 3. Comment se fait l’apprentissage ?

1. **Données** : le modèle s’entraîne uniquement sur **ml_client_features**.
2. **Période** : en PHP (`ml:train`), tu peux fixer `--start-date` et `--end-date` pour ne prendre que les `calculation_date` dans cette fenêtre.
3. **Features utilisées** : une liste fixe dans le code (ex. `payment_success_rate`, `consecutive_failures`, `timwe_success_rate`, etc.) ; les colonnes manquantes sont traitées comme 0.
4. **Label** : binaire, dérivé des features (ex. “au moins un paiement réussi” sur la période).
5. **Sortie** : un modèle (fichier .pkl en Python, ou équivalent selon ton setup) utilisé ensuite par `MLPythonBridgeService` / `MLPredictionService` pour les prédictions.

L’apprentissage ne lit **pas** directement `transactions_history` ; tout passe par les features déjà calculées dans `ml_client_features`. Donc **ordre logique** : d’abord remplir `ml_client_features` (extraction), puis lancer l’entraînement.

---

## 4. Process complet pour alimenter l’agent IA et améliorer ses réponses

Ordre recommandé :

### Étape 1 : Extraire les features depuis le début des données

**Pour un modèle tous types d’abonnement (Timwe + Eklektik + DGV/Ooredoo), utiliser l’extraction multi-agrégateur :**

```bash
# Extraction multi-agrégateur (tous clients actifs, tous opérateurs)
php artisan ml:extract-multi --start-date=2025-08-01 --end-date=2026-02-03 --force

# Options : --batch-days=7, --operator=timwe|eklektik|ooredoo (optionnel), --client-id=... (un seul client)
```

- Les données sont extraites depuis les **tables adéquates** : **transactions_history** (filtres par statut par agrégateur) et **client_abonnement** (via country_payments_methods). Eklektik/Ooredoo peuvent être enrichis plus tard avec eklektik_stats_daily ou stats DGV si besoin.
- **Alternative Timwe seule** (si tu ne veux que Timwe) :
  ```bash
  php artisan ml:extract-features --start-date=2025-08-01 --end-date=2026-02-03 --force
  # --use-queue pour dispatch en queue "ml-extraction" + php artisan queue:work --queue=ml-extraction
  ```
- Après la première fois, lancer l’extraction **au quotidien** (même commande avec `--start-date` et `--end-date` = date du jour ou J-1) pour garder les features à jour.

### Étape 2 : (Optionnel) Nettoyer les anciennes features

La commande peut proposer en fin de run de supprimer les features de plus d’un an. Tu peux aussi appeler le nettoyage via le service (méthode `cleanOldFeatures()`). Ça évite que la table ne grossisse sans limite.

### Étape 3 : Vérifier la qualité des données

Après extraction, la commande affiche déjà des stats (nombre d’enregistrements, clients uniques, répartition par segment, taux de succès). Tu peux en plus :

```bash
php artisan ml:test-system   # si disponible : vérifie cohérence et donne un aperçu
```

Et en base :

- `SELECT calculation_date, COUNT(*) FROM ml_client_features GROUP BY calculation_date ORDER BY 1;`
- Vérifier que la dernière `calculation_date` est bien celle attendue (ex. aujourd’hui ou hier).

### Étape 4 : Entraîner (ou ré-entraîner) le modèle

Dès que tu as assez de lignes dans `ml_client_features` (au moins 50–100, idéalement plus) :

```bash
# Option A : entraînement via script Python (lit directement la DB)
php artisan ml:train-python

# Option B : entraînement via service PHP (export puis Python)
php artisan ml:train --start-date=2025-08-01 --end-date=2026-02-03
```

Après entraînement, les prédictions utilisées par l’app (et exposées à l’agent) proviendront du nouveau modèle (si le code charge bien le bon fichier / la bonne version).

### Étape 5 : Alimenter / rafraîchir le contexte de l’agent IA

L’agent ne lit **pas** les tables en direct à chaque question ; il passe par **AIContextProvider** qui utilise des **caches** (ex. `AIContextCache` avec TTL 2h pour le contexte système, 15 min pour les KPIs, 4h pour les features ML, etc.).

- **Automatique** : au fil des questions, les caches expirent et sont reconstruits à partir de `ml_client_features`, `ml_predictions`, etc. Donc dès que les tables sont à jour, les prochaines requêtes obtiendront un contexte à jour après expiration des caches.
- **Forcer le rafraîchissement** : si tu as un cache Laravel/Redis, tu peux vider les clés utilisées par `AIContextCache` (ou le cache en général) après une grosse extraction ou un ré-entraînement, pour que la prochaine question recharge tout depuis la base.

Résumé : **alimenter les tables** (extraction + éventuellement prédictions/recos) **et** éventuellement **vider le cache contexte** = l’agent aura les dernières features, segments et KPIs pour améliorer ses réponses.

### Étape 6 : Automatiser (recommandé)

Pour que l’agent reste à jour sans intervention manuelle :

1. **Extraction quotidienne** (recommandé : multi-agrégateur pour la date du jour ou J-1) :
   - Dans `app/Console/Kernel.php`, ajouter par exemple :
     ```php
     $schedule->command('ml:extract-multi', [
         '--start-date' => now()->subDay()->toDateString(),
         '--end-date'   => now()->subDay()->toDateString(),
         '--force'
     ])->dailyAt('03:00')->withoutOverlapping();
     ```
   - (Si tu ne veux que Timwe, utilise `ml:extract-features` avec les mêmes options.)
2. **Entraînement périodique** (ex. hebdo ou mensuel) :
   ```php
   $schedule->command('ml:train-python')->weekly()->sundays()->at('04:00');
   ```
3. (Optionnel) **Invalider le cache contexte** après extraction ou entraînement (job ou commande qui supprime les entrées concernées de `AIContextCache` / Redis).

---

## 5. Résumé des commandes

| Action | Commande |
|--------|----------|
| **Extraire multi-agrégateur** (recommandé, tous types d’abonnement) | `php artisan ml:extract-multi --start-date=YYYY-MM-DD --end-date=YYYY-MM-DD [--force]` |
| Extraire Timwe seule | `php artisan ml:extract-features --start-date=... --end-date=... [--use-queue]` |
| Entraîner le modèle (Python, lit ml_client_features) | `php artisan ml:train-python` |
| Entraîner (PHP + options) | `php artisan ml:train --start-date=... --end-date=...` |
| Analyser préférences opérateurs | `php artisan ml:analyze-preferences` |
| Tester le système ML | `php artisan ml:test-system` (si dispo) |

---

## 6. Réponses directes à tes questions

- **Faut-il mettre l’extraction depuis le début de la data ?**  
  **Oui.** Utilise `--start-date` à la première date où tu as abonnements + transactions pour **au moins un** agrégateur (Timwe, Eklektik ou Ooredoo/DGV), et `--end-date` à aujourd’hui. Pour un **modèle tous types d’abonnement**, utilise **`ml:extract-multi`** (pas seulement `ml:extract-features`). Ensuite, extractions incrémentales (un jour) en cron.

- **Comment se fait l’apprentissage ?**  
  Uniquement à partir de **ml_client_features** : une période de `calculation_date` est choisie, les features (dont timwe_*, eklektik_*, ooredoo_*) sont lues, un label est dérivé (ex. au moins un paiement réussi), et un modèle (ex. LightGBM) est entraîné via `ml:train-python` ou `ml:train`. Le **même modèle** peut donc être entraîné sur tous les agrégateurs si les features multi-opérateur sont remplies.

- **Comment faire tout le process pour alimenter l’agent IA et améliorer ses réponses (tous types d’abonnement) ?**  
  1) **Extraire** depuis le début avec **`ml:extract-multi`** (données depuis les tables adéquates : transactions_history + client_abonnement par agrégateur).  
  2) Vérifier la qualité (stats, dernière date, répartition Timwe / Eklektik / Ooredoo).  
  3) **Entraîner** le modèle (`ml:train-python` ou `ml:train`) pour obtenir un modèle adapté à tous les agrégateurs.  
  4) L’agent IA lit déjà `ml_client_features` (et prédictions/recos) ; vider le cache contexte si tu veux forcer un rafraîchissement.  
  5) Automatiser extraction quotidienne (multi-agrégateur) et entraînement périodique.

Si tu veux, on peut détailler une étape (ex. aligner la détection du succès Eklektik/Ooredoo avec les vrais statuts en base, ou configurer le cron) en fonction de ta base.
