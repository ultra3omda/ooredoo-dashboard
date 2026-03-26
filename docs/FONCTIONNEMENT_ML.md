# Fonctionnement technique du système ML – Club Privilèges

## 1. Pas d’« entraînement » classique

Le système n’utilise **pas** de modèle statistique ou de réseau de neurones qui s’« entraîne » sur des données (pas de gradient, pas d’epochs).

- **Prédictions** : règles métier à partir des features (ex. taux de succès, segment, échecs consécutifs) → `MLPredictionService`.
- **Recommandations** : règles métier à partir des stats (segments, timing, churn) → `MLRecommendationService`.
- La seule étape « lourde » côté données est l’**extraction de features**, qui remplit `ml_client_features` (voir ci‑dessous). C’est elle qui peut prendre du temps, pas un entraînement de modèle.

---

## 2. Qui alimente quelles tables ML

| Table | Alimentée par | Quand |
|-------|----------------|-------|
| **ml_client_features** | Commande `php artisan ml:extract-features` | Manuelle (ou cron si vous l’ajoutez). Période en option (ex. `--start-date=2025-08-01 --end-date=...`). |
| **ml_recommendations** | `MLRecommendationService::generateRecommendations()` | Quand vous cliquez sur « Nouvelles Recommandations » dans le ML Dashboard (ou appel API POST `/admin/ml-dashboard/recommendations/generate`). |
| **ml_predictions** | `MLPredictionService` (prédictions à la volée ou batch) | À chaque prédiction (dashboard, détail client, etc.) ; peut être mis en cache ou écrit en base selon le code. |
| **ml_client_segments** | Déduit / dérivé des features | Utilisé pour segmentation ; peut être rempli lors de l’extraction ou par un job dédié selon l’implémentation. |
| **ml_model_performance** | Métriques du « modèle » (rule-based) | Si implémenté : écriture lors d’évaluations ou de logs de performance. |
| **ml_ab_tests** / **ml_ab_test_participants** | Tests A/B | Quand vous créez/lancez des tests A/B dans l’appli. |

Donc : **toutes les tables ML ne sont pas remplies en permanence** ; chacune a sa source (commande, clic, prédiction, etc.).

---

## 3. Temps que peut prendre l’extraction de features

- **Commande concernée** : `php artisan ml:extract-features [--start-date=...] [--end-date=...] [--batch-days=7]`.
- **Effet** : lit les transactions / abonnements, calcule pour chaque client et chaque jour des indicateurs (taux de succès, churn, segment, etc.) et écrit dans `ml_client_features`.
- **Durée** : dépend du nombre de clients × nombre de jours. Exemple : 85k clients sur 6 mois ≈ beaucoup de lignes ; ça peut aller de quelques minutes à plusieurs dizaines de minutes (ou plus) selon la machine et la base.
- **Pendant l’exécution** : le terminal reste bloqué jusqu’à la fin. Il faut laisser le PC (et le terminal) ouverts si vous voulez que la commande aille au bout.

Il n’y a pas de « temps d’entraînement » séparé : ce qui prend du temps, c’est cette extraction de features.

---

## 4. Pourquoi les recommandations étaient générées mais pas affichées

- Les recommandations sont bien **créées et enregistrées** dans `ml_recommendations` (statut `pending`, etc.).
- Le problème était côté **front** : après un clic sur « Nouvelles Recommandations », la page ne rechargeait pas la liste depuis l’API.
- **Correction** : après génération, le front appelle l’API des données du dashboard puis met à jour le bloc « Recommandations Prioritaires » avec la réponse (liste + résumé). Ainsi, les recommandations générées sont bien affichées après le clic.

---

## 5. Résumé du flux « modèle »

1. **Features** : `ml:extract-features` → remplit `ml_client_features` (par client, par jour).
2. **Prédictions** : le service lit les features (et éventuellement `ml_client_segments`) et applique des règles → pas d’entraînement, calcul à la volée (ou résultat mis en cache/DB).
3. **Recommandations** : le service lit les stats (segments, timing, churn, etc.), génère des recommandations et les enregistre dans `ml_recommendations` ; le dashboard les récupère via l’API et les affiche.
4. **Affichage** : le ML Dashboard affiche les recommandations **pending** valides ; après « Nouvelles Recommandations », la liste se met à jour sans rechargement complet de la page.

---

## 6. Que faire si une table ML reste vide

- **ml_client_features** : lancer `php artisan ml:extract-features` sur la période voulue (ex. depuis le 01/08/2025). Sans cette table remplie, segments et stats utilisées par les recommandations peuvent être vides ou par défaut.
- **ml_recommendations** : cliquer sur « Nouvelles Recommandations » dans le ML Dashboard (ou appeler l’API de génération). Vérifier que le bloc « Recommandations Prioritaires » se met à jour après le clic.
- **ml_predictions** : dépend des écrans qui appellent le service de prédiction ; vérifier les appels depuis le dashboard et les APIs.
- **client_id à NULL** : dans votre cas, les recommandations sont surtout **globales** (timing, stratégie). Si vous voulez des recommandations par client, le service doit être étendu pour remplir `client_id` lors de l’insertion dans `ml_recommendations`.

Si vous voulez, on peut détailler pour une table précise (ex. `ml_client_features` ou `ml_recommendations`) les champs et le code qui les remplissent.

---

## 7. Comment tester chaque composant ML

### ml_client_features
- **Commande** : `php artisan ml:extract-features [--start-date=2025-08-01] [--end-date=...] [--batch-days=7]`
- **Effet** : remplit la table pour chaque client/jour sur la période. Sans cette table remplie, prédictions et recommandations ont peu de données.

### ml_recommendations
- **Dans l’appli** : ML Dashboard → bouton « Nouvelles Recommandations » (ou appel API `POST /admin/ml-dashboard/recommendations/generate`).
- **Effet** : génère des recommandations et les enregistre dans `ml_recommendations` ; le bloc « Recommandations Prioritaires » se met à jour après le clic.

### ml_predictions
- **Ce n’est pas automatique au simple chargement du dashboard.** La page du ML Dashboard **lit** ce qui est déjà dans `ml_predictions` pour la date du jour ; elle ne lance pas de prédictions pour tous les clients.
- **La table est écrite** quand :
  1. Vous **ouvrez le détail d’un client** (clic sur un client) → une prédiction est calculée et enregistrée pour ce client.
  2. Vous appelez **`POST /admin/ml-dashboard/predict`** avec `client_id` (et optionnellement `prediction_date`).
  3. Vous lancez **`php artisan ml:test-system`** ou `php artisan ml:test-system --client-id=123`.
- Pour avoir la liste du dashboard remplie sans cliquer client par client : lancer régulièrement `php artisan ml:test-system` (ex. en cron) ou prévoir un job qui appelle le service en batch.

### ml_client_segments
Données simulées dans le dashboard ; la table n’est pas encore écrite par un job d’évaluation.

### ml_model_performance
- **Aujourd’hui** : le dashboard affiche des métriques simulées ; aucun job n’écrit dans la table.
- **Pour écrire la table** : une commande existe : **`php artisan ml:log-performance`**. Elle insère une ligne dans `ml_model_performance` (métriques du prédicteur rule-based). Option : `--days-ago=1` pour la période de référence.
- **Planification (cron)** : dans `app/Console/Kernel.php`, décommenter la ligne du schedule pour exécuter la commande chaque jour, par ex. à 3h :
  ```php
  $schedule->command('ml:log-performance')->dailyAt('03:00')->withoutOverlapping();
  ```
  Aucune autre configuration n’est nécessaire ; le cron Laravel (`php artisan schedule:run` toutes les minutes sur le serveur) exécutera la commande au moment prévu.

### ml_ab_tests / ml_ab_test_participants
`php artisan ml:ab-test` (voir ci‑dessus) ; `--list` pour lister.  
Options utiles : `--name="Mon test"`, `--participants=200`, `--days=14`.

### En résumé
**features** = commande d’extraction ; **recommandations** = bouton ou API ; **prédictions** = dashboard, API `/predict` ou `ml:test-system` ; **A/B** = `ml:ab-test`. Les segments et la performance modèle sont prévus en base mais pas encore alimentés par le code.
