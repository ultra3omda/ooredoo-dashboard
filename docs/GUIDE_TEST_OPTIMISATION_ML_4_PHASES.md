# Guide de test – Optimisation ML (4 phases)

Ce guide décrit comment tester les 4 phases d’optimisation du système ML (extraction features, agent IA, modèle Python, cache intelligent) mises en place récemment.

---

## Prérequis globaux

- **Laravel** : projet à jour, `php artisan` fonctionnel.
- **Base de données** : migrations ML exécutées (`ml_client_features`, `ml_predictions`, `ml_model_performance`, etc.).
- **Données minimales** : au moins quelques clients avec historique de transactions (Timwe/Eklektik/Ooredoo) pour les tests Phase 1 et 3.
- **Fichier `.env`** : variables DB, optionnellement `QUEUE_CONNECTION`, `CACHE_DRIVER`, `PYTHON_PATH`, clés API IA.

---

## Phase 1 : Optimisation de l’extraction de features

**Objectif** : Vérifier l’extraction des features ML (synchrone et via queues), le cache et le batch.

### 1.1 Prérequis Phase 1

- Table `ml_client_features` créée.
- Table `transactions_history` (ou équivalent) avec des lignes pour des `client_id` existants.
- Pour le mode **queue** : `QUEUE_CONNECTION=redis` (ou `database`) dans `.env` et Redis/DB de queues opérationnel.

### 1.2 Test en mode synchrone (sans queue)

Sans lancer de worker, l’extraction se fait client par client dans la commande.

```bash
# Depuis la racine du projet
php artisan ml:extract-features --start-date=2025-12-01 --end-date=2025-12-07 --batch-days=3
```

- **À vérifier** :
  - La commande affiche la période, le nombre de jours, et pour chaque jour un message du type : `✅ YYYY-MM-DD: N clients`.
  - En fin d’exécution : tableau récapitulatif (période, total clients traités, moyenne clients/jour).
  - Section « Vérification de la qualité des données » : répartition par segment, stats de taux de succès.

```bash
# Vérification en base
php artisan tinker
>>> \DB::table('ml_client_features')->whereBetween('calculation_date', ['2025-12-01', '2025-12-07'])->count();
>>> \DB::table('ml_client_features')->where('calculation_date', '2025-12-07')->select('client_segment', \DB::raw('count(*) as c'))->groupBy('client_segment')->get();
>>> exit
```

- **Succès** : nombre d’enregistrements cohérent avec les clients actifs sur la période ; segments présents (`premium_payers`, `high_risk`, etc.).

### 1.3 Test en mode queue (parallélisation)

1. **Configurer les queues** (si pas déjà fait)  
   Dans `.env` : `QUEUE_CONNECTION=redis` (ou `database`).  
   Si `database` : `php artisan queue:table` puis `php artisan migrate`.

2. **Lancer un worker dédié à la queue `ml-extraction`** (dans un terminal à part) :

```bash
php artisan queue:work --queue=ml-extraction --tries=2 --timeout=3600
```

3. **Lancer l’extraction en mode queue** (autre terminal) :

```bash
php artisan ml:extract-features --start-date=2025-12-01 --end-date=2025-12-03 --use-queue
```

- **À vérifier** :
  - Message du type : « Mode queue activé: les jobs seront traités par les workers (queue: ml-extraction) ».
  - Pour chaque jour : « N clients dispatchés » (pas « N clients » comme en synchrone).
  - Dans le terminal du worker : jobs `ExtractClientFeaturesJob` qui s’exécutent et se terminent sans erreur.
  - Après quelques minutes, les lignes apparaissent dans `ml_client_features` pour les dates concernées.

```bash
# Vérifier les jobs en attente (si driver database)
php artisan queue:monitor ml-extraction
# ou en tinker
>>> \DB::table('jobs')->where('queue', 'ml-extraction')->count();
```

- **Succès** : les jobs sont bien dispatchés, le worker les traite, et le nombre de lignes en base correspond aux clients traités pour les dates demandées.

### 1.4 Option `--force`

Pour ignorer la confirmation en cas de données existantes :

```bash
php artisan ml:extract-features --start-date=2025-12-01 --end-date=2025-12-02 --force
```

- **À vérifier** : pas de question « Voulez-vous continuer ? », les features sont (re)calculées pour la période.

### 1.5 Dépannage Phase 1

| Problème | Piste |
|----------|--------|
| « Aucun client actif » ou 0 clients | Vérifier que `transactions_history` (ou la table utilisée) contient des données pour la période et que la logique « clients actifs » (ex. `getActiveClientIds`) cible la bonne table/champ. |
| Erreur SQL / colonne manquante | Vérifier que toutes les migrations ML (y compris `add_ml_features_v2`) sont exécutées. |
| Jobs en queue mais pas traités | Vérifier que le worker tourne avec `--queue=ml-extraction`. Vérifier `QUEUE_CONNECTION` et Redis/DB. |
| Timeout / mémoire | Réduire `--batch-days` ou la taille des chunks dans `ExtractClientFeaturesJob` (ex. 500 → 200). |

---

## Phase 2 : Agent IA (prompts Dr. ML + insights avancés)

**Objectif** : Vérifier que l’agent IA reçoit le contexte enrichi (dont `advanced_insights`) et répond dans le style demandé (chiffres, structure, recommandations).

### 2.1 Prérequis Phase 2

- Au moins une clé API configurée : `OPENAI_API_KEY`, ou `ANTHROPIC_API_KEY`, ou `GEMINI_API_KEY` dans `.env`.
- Données ML présentes : `ml_client_features` (au moins une date de calcul) pour que les insights avancés soient pertinents.

### 2.2 Test via l’interface web

1. Se connecter à l’admin et ouvrir l’agent IA :  
   **URL typique** : `/admin/ai-agent` (selon vos routes).

2. Choisir un fournisseur (OpenAI, Claude ou Gemini) si un sélecteur est affiché.

3. Poser des questions qui sollicitent le contexte ML et les insights avancés, par exemple :
   - « Quelle stratégie recommandes-tu pour le segment high_risk ? »
   - « Quelles sont les opportunités de revenus par segment ? »
   - « Donne-moi les quick wins et les alertes risque actuelles. »
   - « Résume les performances par segment et le taux de succès global. »

- **À vérifier** :
  - Réponse structurée (sections type 🎯 Réponse directe, 📊 Données, 💡 Recommandation, ✅ Prochaines étapes).
  - Chiffres cohérents avec les données (segments, taux, revenus estimés).
  - Pas d’invention de chiffres non présents dans le contexte (ex. revenus ou segments inventés).

### 2.3 Test via Tinker (contexte seul)

Vérifier que le contexte « insights avancés » est bien généré et mis en cache :

```bash
php artisan tinker
>>> $provider = app(\App\Services\AIContextProvider::class);
>>> $insights = $provider->getAdvancedInsightsContext();
>>> $insights['revenue_opportunities'][0] ?? null;
>>> $insights['quick_wins'];
>>> $insights['risk_alerts'];
>>> count($insights['ab_test_suggestions']['suggestions'] ?? []);
>>> exit
```

- **Succès** : `revenue_opportunities` est un tableau (souvent trié par opportunité), `quick_wins` et `risk_alerts` sont remplis si les données ML le permettent, `ab_test_suggestions` contient des suggestions.

### 2.4 Dépannage Phase 2

| Problème | Piste |
|----------|--------|
| Réponses génériques / sans chiffres | Vérifier que `getAdvancedInsightsContext()` est bien appelé dans `AIAgentService::buildContext()` et que le prompt système « Dr. ML » est bien utilisé. |
| Erreur 401/403 / clé API | Vérifier la clé correspondant au fournisseur choisi dans `.env`. |
| Contexte vide ou erreur | Vérifier les logs `storage/logs/laravel-*.log` et que `ml_client_features` a des lignes (au moins une `calculation_date` récente). |

---

## Phase 3 : Modèle ML Python (LightGBM + bridge PHP)

**Objectif** : Vérifier l’entraînement du modèle, la prédiction via le script Python depuis PHP, et le fallback rule-based si le modèle est absent ou en erreur.

### 3.1 Prérequis Phase 3

- **Python 3.8+** avec `pip` disponible.
- **Variables DB** dans `.env` (ou exportées) pour que `train_model.py` puisse se connecter à la même base que Laravel.
- **Données** : au moins ~100 lignes dans `ml_client_features` (idéalement plus) pour un entraînement minimal.

### 3.2 Installation des dépendances Python

```bash
cd ml_models
pip install -r requirements.txt
# ou selon l’environnement :
python -m pip install -r requirements.txt
```

- **À vérifier** : aucune erreur d’installation (lightgbm, scikit-learn, pandas, mysql-connector-python, python-dotenv, joblib).

### 3.3 Chargement du .env par le script Python

Le script `train_model.py` charge le `.env` à la racine du projet. Depuis la racine :

```bash
# Depuis la racine du projet (pas depuis ml_models)
python ml_models/train_model.py
```

Si vous préférez être dans `ml_models` :

```bash
cd ml_models
# Le script remonte d’un niveau pour trouver .env
python train_model.py
```

- **À vérifier** : pas d’erreur « Modèle non trouvé » côté prédiction ; en cas d’erreur DB, vérifier `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` (et `DB_PORT` si besoin).

### 3.4 Entraînement du modèle

```bash
# Depuis la racine du projet
python ml_models/train_model.py
```

- **À vérifier** :
  - Message du type : « X enregistrements chargés » (X ≥ 100 conseillé).
  - « Y features préparées » et distribution de la cible.
  - « Modèle entraîné » avec une métrique type AUC-ROC.
  - « Modèle sauvegardé: .../ml_models/billing_predictor_v3.pkl ».
  - Une ligne insérée dans `ml_model_performance` (auc_roc, accuracy, etc.).

```bash
php artisan tinker
>>> \DB::table('ml_model_performance')->orderBy('evaluation_date', 'desc')->first();
>>> exit
```

### 3.5 Test du script de prédiction (Python seul)

```bash
# Exemple avec un JSON minimal (même ordre/noms que les features du modèle)
python ml_models/predict.py --features "{\"consecutive_failures\": 2, \"total_payments\": 10, \"payment_success_rate\": 0.35}"
```

- **À vérifier** : sortie JSON du type `{"probability": 0.xx, "confidence": 0.xx}`.

### 3.6 Test de la prédiction depuis Laravel (bridge PHP)

Avec le fichier `billing_predictor_v3.pkl` présent dans `ml_models/` :

```bash
php artisan tinker
>>> $service = app(\App\Services\MLPredictionService::class);
>>> $clientId = 12345;   // Remplacer par un client_id qui a des features dans ml_client_features
>>> $pred = $service->predictPaymentSuccess($clientId);
>>> $pred['model_version'];   // Doit être "lightgbm_v3.0" si le modèle Python a répondu
>>> $pred['payment_success_probability'];
>>> $pred['features_used'];
>>> exit
```

- **Succès** : `model_version` = `lightgbm_v3.0`, probabilité et `features_used` cohérents. Aucune exception.

### 3.7 Test du fallback rule-based (modèle absent)

1. Renommer ou déplacer le fichier du modèle pour simuler son absence :  
   `mv ml_models/billing_predictor_v3.pkl ml_models/billing_predictor_v3.pkl.bak`

2. Relancer une prédiction pour un client ayant des features :

```bash
php artisan tinker
>>> $service = app(\App\Services\MLPredictionService::class);
>>> $pred = $service->predictPaymentSuccess(12345);
>>> $pred['model_version'];   // Doit être "rule_based_v1.0" (fallback)
>>> exit
```

3. Remettre le modèle en place :  
   `mv ml_models/billing_predictor_v3.pkl.bak ml_models/billing_predictor_v3.pkl`

- **Succès** : pas d’exception, `model_version` = règle-based, prédiction retournée.

### 3.8 Variable PYTHON_PATH (optionnel)

Si `python3` n’est pas dans le PATH ou que vous utilisez un environnement virtuel :

- Dans `.env` : `PYTHON_PATH=C:\chemin\vers\python.exe` (Windows) ou `PYTHON_PATH=/usr/bin/python3` (Linux/Mac).

### 3.9 Dépannage Phase 3

| Problème | Piste |
|----------|--------|
| « Pas assez de données » / erreur à l’entraînement | Augmenter la période d’extraction Phase 1 ou réduire la fenêtre dans la requête SQL (ex. 90 → 30 jours) pour tester. |
| Erreur MySQL dans `train_model.py` | Vérifier `.env` (DB_*) et que le script est lancé depuis la racine (ou que le chemin vers `.env` est correct). |
| « Modèle non trouvé » dans predict | Vérifier que `billing_predictor_v3.pkl` existe dans `ml_models/` et que `PYTHON_PATH` pointe vers le bon Python. |
| ProcessFailedException dans le bridge | Regarder les logs Laravel et la sortie d’erreur du processus Python ; vérifier que `predict.py` reçoit un JSON valide et que les noms de features correspondent à ceux du modèle. |

---

## Phase 4 : Cache intelligent et warmup

**Objectif** : Vérifier que le cache intelligent (TTL adaptatif, stats) fonctionne et que la commande `cache:warmup` précharge bien les contextes.

### 4.1 Prérequis Phase 4

- **Cache** : `CACHE_DRIVER=redis` (recommandé) ou `file`/`database`. Si Redis : Redis démarré et configuré dans `config/database.php`.

### 4.2 Test de la commande de warmup

```bash
php artisan cache:warmup --stats
```

- **À vérifier** :
  - Message « Préchauffage du cache intelligent... » puis « Warmup terminé ».
  - Avec `--stats` : tableau avec Hits, Misses, Taux de hit, Mémoire (Redis). Après un premier warmup, les misses augmentent ; les hits augmenteront aux appels suivants.

### 4.3 Test du TTL selon la clé

En Tinker, utiliser le service pour mémoriser des clés de différents types et vérifier que le TTL calculé est cohérent :

```bash
php artisan tinker
>>> $cache = app(\App\Services\IntelligentCacheService::class);
>>> $cache->calculateOptimalTTL('warmup_system_context');   // 14400 (4h)
>>> $cache->calculateOptimalTTL('kpis_realtime');          // 300 (5 min)
>>> $cache->calculateOptimalTTL('ml_features_xyz');       // 3600 (1h)
>>> $cache->remember('test_key_kpis', fn () => ['ok' => true]);
>>> $cache->getStats();
>>> exit
```

- **Succès** : TTL différents selon le type de clé ; `getStats()` retourne `hits`, `misses`, `hit_rate`, `memory_used`.

### 4.4 Vérifier que le warmup remplit bien le cache

1. Réinitialiser les stats (optionnel) :  
   En Tinker : `app(\App\Services\IntelligentCacheService::class)->resetStats();`

2. Lancer le warmup :  
   `php artisan cache:warmup --stats`

3. Rappeler le warmup une seconde fois :  
   `php artisan cache:warmup --stats`

- **À vérifier** : au second passage, les hits devraient augmenter (les clés `warmup_system_context`, `warmup_kpis_context`, `warmup_ml_features_context` sont servies depuis le cache).

### 4.5 Scheduler (warmup quotidien)

La tâche planifiée est définie dans `app/Console/Kernel.php` (ex. tous les jours à 6h). Pour tester sans attendre :

```bash
php artisan schedule:run
```

- **À vérifier** : si l’heure actuelle correspond à la planification, la commande `cache:warmup` est exécutée ; sinon, rien ne s’exécute. Vérifier aussi `storage/logs/cache-warmup.log` après un run.

### 4.6 Dépannage Phase 4

| Problème | Piste |
|----------|--------|
| Erreur Redis / connexion | Vérifier `config/database.php` (redis) et que Redis est démarré. Avec `CACHE_DRIVER=file`, le service fonctionne mais sans stats Redis ni mémoire. |
| Stats toujours à 0 | Avec le driver `file`, les compteurs sont dans le cache Laravel ; vérifier que `remember()` est bien appelé (ex. via warmup ou une route qui utilise le service). |
| Warmup échoue (exception) | Vérifier que `AIContextProvider` est résolvable et que les méthodes `getSystemContext()`, `getKPIsContext()`, `getMLFeaturesContext()` ne lèvent pas (ex. erreur DB). |

---

## Checklist finale

Cocher au fur et à mesure :

- [ ] **Phase 1**  
  - [ ] Extraction synchrone sur une courte période → lignes dans `ml_client_features`.  
  - [ ] (Optionnel) Worker `ml-extraction` + extraction avec `--use-queue` → jobs traités, données en base.

- [ ] **Phase 2**  
  - [ ] Au moins une question à l’agent IA (interface ou API) → réponse structurée avec chiffres.  
  - [ ] `getAdvancedInsightsContext()` en Tinker → `revenue_opportunities`, `quick_wins`, `risk_alerts` présents.

- [ ] **Phase 3**  
  - [ ] `pip install -r ml_models/requirements.txt` sans erreur.  
  - [ ] `python ml_models/train_model.py` → modèle sauvegardé et ligne dans `ml_model_performance`.  
  - [ ] Prédiction depuis Tinker → `model_version` = `lightgbm_v3.0`.  
  - [ ] Modèle supprimé/déplacé → prédiction en fallback rule-based sans crash.

- [ ] **Phase 4**  
  - [ ] `php artisan cache:warmup --stats` s’exécute sans erreur.  
  - [ ] `getStats()` retourne des valeurs cohérentes ; après deux warmups, les hits augmentent.

---

## Résumé des commandes utiles

```bash
# Phase 1
php artisan ml:extract-features --start-date=2025-12-01 --end-date=2025-12-07
php artisan ml:extract-features --start-date=2025-12-01 --end-date=2025-12-03 --use-queue
php artisan queue:work --queue=ml-extraction --tries=2 --timeout=3600

# Phase 2 : interface /admin/ai-agent et questions ciblées

# Phase 3
pip install -r ml_models/requirements.txt
python ml_models/train_model.py
python ml_models/predict.py --features '{"consecutive_failures": 2, "total_payments": 10}'
php artisan tinker → MLPredictionService::predictPaymentSuccess($clientId)

# Phase 4
php artisan cache:warmup --stats
php artisan schedule:run
```

Une fois ces points validés, les 4 phases sont considérées testées et opérationnelles dans votre environnement.
