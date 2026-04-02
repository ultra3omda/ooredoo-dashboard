# Cible du modèle ML et calcul des taux de succès

## Cible (label) d'entraînement

La **cible** utilisée pour l'entraînement LightGBM (`ml:train-python`) est **binaire** :

- **1 (succès)** : le client a **au moins un opérateur** (Timwe, Eklektik ou Ooredoo) avec :
  - **activité** sur cet opérateur (`*_has_activity` = 1), **et**
  - **taux de succès** sur cet opérateur **> 0,2** (`*_success_rate` > 0.2).
- **0** : aucun opérateur ne satisfait ces deux conditions.

### Pourquoi cette cible ?

On veut prédire les clients pour qui la **facturation réussit** sur au moins un canal (Timwe, Eklektik ou Ooredoo). Cela sert au **ciblage**, au **routage** ou à la **priorisation** des actions (retry, offre adaptée). Un client avec uniquement des échecs (taux 0) ou sans activité sur un opérateur ne compte pas comme "succès". Le seuil **0,2** évite de considérer comme positifs les clients avec très peu de succès par rapport aux tentatives.

### Où c’est défini

- **Construction de la cible** : `ml_models/train_model.py`, méthode `_build_target_multi_operator`, à partir des colonnes `timwe_success_rate`, `eklektik_success_rate`, `ooredoo_success_rate` et `*_has_activity`.
- **Remplissage des colonnes** : extraction multi-opérateur `ml:extract-multi` → `MLMultiOperatorFeatureService` (calcul des `*_success_rate` et `*_has_activity`).

Si la cible est toujours à 0, vérifier que l’extraction remplit bien les taux de succès (commande `ml:inspect-sample`).

---

## Calcul des `*_success_rate` (extraction)

Les taux sont calculés dans `MLMultiOperatorFeatureService` :

- **Timwe** : succès = transaction avec `pricepointId` facturation, `mnoDeliveryCode` = `DELIVERED`, `totalCharged` > 0.
- **Eklektik** : succès = `result['success']` OU `mnoDeliveryCode` = `DELIVERED` OU **statut** = CHARGE_DELIVERED / RENEWED (ORANGE_, TT_, TARAJI_) OU `result['message']` = `OK` OU `result['status']` = 0.  
  **Référence** : il n’y a pas de statut dédié « facturation » dans les 49 ; les **vraies facturations** (volumes, CA) viennent de la plateforme → **`eklektik_stats_daily`** (charges par opérateur Orange, TT, Taraji). **`client_abonnement`** : abo Eklektik = période gratuite au départ ; si facturé → actif avec `expiration_date` = NULL ; sinon unsub. Voir `docs/TRANSACTIONS_RESULT_SUCCESS_CRITERIA.md`.
- **Ooredoo** : succès = `result['success']` OU `mnoDeliveryCode` = `DELIVERED` OU **statut** = `OOREDOO_PAYMENT_SUCCESS` / `OOREDOO_CHARGE_DELIVERED` / `OOREDOO_RENEWED` OU `result['status']` = `SUCCESS`. Référence agrégée : **`ooredoo_daily_stats`**.

Sans ces critères (notamment le **statut** de la transaction quand `result` est vide ou autre format), les taux restaient à 0.
