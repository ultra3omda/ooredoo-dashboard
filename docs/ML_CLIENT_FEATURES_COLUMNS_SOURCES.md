# Alimentation des colonnes de `ml_client_features`

Ce document indique **quelle source remplit quelle colonne** et si la table est correctement alimentée pour chaque utilisateur selon le job que vous exécutez.

---

## En résumé

| Situation | Résultat |
|-----------|----------|
| **Vous lancez uniquement `ml:extract-multi`** (ou `ml:reset-and-extract`) | Les colonnes **multi-opérateur** (Timwe, Eklektik, Ooredoo, préférences, type d’offre) sont **correctement remplies** pour chaque client à chaque date. Les autres colonnes (paiement global, segment, scores, créneaux horaires) restent à **0, NULL ou "unknown"** car elles sont gérées par un autre job. |
| **Vous lancez également `ml:extract-features`** | En plus des colonnes multi-opérateur, les colonnes **paiement global, segment, churn, scores, créneaux** peuvent être remplies pour les **clients Timwe** couverts par ce job. |
| **Pour un modèle ou un rapport “tous opérateurs”** | Il suffit que **ml:extract-multi** soit exécuté : les colonnes nécessaires (timwe_*, eklektik_*, ooredoo_*, préférences, etc.) sont bien alimentées. Les colonnes laissées à NULL/0 n’ont pas à l’être pour ce cas d’usage. |

---

## Liste colonne par colonne

### Clés et métadonnées

| Colonne | Remplie par | Commentaire |
|---------|-------------|-------------|
| `id` | Base de données (AUTO_INCREMENT) | Toujours rempli. |
| `client_id` | **Multi-opérateur** | Toujours rempli pour chaque ligne. |
| `calculation_date` | **Multi-opérateur** | Toujours rempli. |
| `created_at` | **Multi-opérateur** | Rempli depuis la correction (upsert). |
| `updated_at` | **Multi-opérateur** | Rempli à chaque upsert. |

### Historique de paiement (vue globale)

| Colonne | Remplie par | Commentaire |
|---------|-------------|-------------|
| `payment_success_rate` | Ancien pipeline (ml:extract-features) | NULL/0 si seul multi-opérateur. |
| `consecutive_failures` | Ancien pipeline | NULL/0 si seul multi-opérateur. |
| `days_since_last_payment` | Ancien pipeline | NULL si seul multi-opérateur. |
| `avg_payment_amount` | Ancien pipeline | 0 si seul multi-opérateur. |
| `payment_frequency` | Ancien pipeline | 0 si seul multi-opérateur. |
| `total_payments` | Ancien pipeline | 0 si seul multi-opérateur. |
| `total_attempts` | Ancien pipeline | 0 si seul multi-opérateur. |

### Patterns de solde

| Colonne | Remplie par | Commentaire |
|---------|-------------|-------------|
| `avg_balance` | Ancien pipeline | NULL si seul multi-opérateur. |
| `balance_volatility` | Ancien pipeline | 0 si seul multi-opérateur. |
| `recharge_frequency` | Ancien pipeline | 0 si seul multi-opérateur. |
| `recharge_amount_avg` | Ancien pipeline | 0 si seul multi-opérateur. |
| `days_since_recharge` | Ancien pipeline | NULL si seul multi-opérateur. |
| `balance_trend` | Ancien pipeline | "unknown" par défaut. |

### Patterns temporels (créneaux)

| Colonne | Remplie par | Commentaire |
|---------|-------------|-------------|
| `best_billing_day_week` | Ancien pipeline | NULL si seul multi-opérateur. |
| `best_billing_hour` | Ancien pipeline | NULL si seul multi-opérateur. |
| `seasonal_pattern` | Ancien pipeline | NULL si seul multi-opérateur. |
| `end_month_success_rate` | Ancien pipeline | 0 si seul multi-opérateur. |
| `beginning_month_success_rate` | Ancien pipeline | 0 si seul multi-opérateur. |
| `morning_success_rate` | Ancien pipeline | NULL si seul multi-opérateur. |
| `afternoon_success_rate` | Ancien pipeline | NULL si seul multi-opérateur. |
| `evening_success_rate` | Ancien pipeline | NULL si seul multi-opérateur. |
| `recovery_after_failure_rate` | Ancien pipeline | NULL si seul multi-opérateur. |
| `max_consecutive_successes` | Ancien pipeline | NULL si seul multi-opérateur. |
| `payment_amount_std` | Ancien pipeline | NULL si seul multi-opérateur. |
| `amount_flexibility` | Ancien pipeline | NULL si seul multi-opérateur. |
| `no_balance_failure_rate` | Ancien pipeline | NULL si seul multi-opérateur. |
| `not_delivered_failure_rate` | Ancien pipeline | NULL si seul multi-opérateur. |

### Usage et démographiques

| Colonne | Remplie par | Commentaire |
|---------|-------------|-------------|
| `total_transactions` | Ancien pipeline | 0 si seul multi-opérateur. |
| `avg_transactions_per_day` | Ancien pipeline | 0 si seul multi-opérateur. |
| `unique_statuses_count` | Ancien pipeline | 0 si seul multi-opérateur. |
| `status_distribution` | Ancien pipeline | NULL si seul multi-opérateur. |
| `subscription_age_days` | Ancien pipeline | 0 si seul multi-opérateur. |
| `region` | Ancien pipeline | NULL si seul multi-opérateur. |
| `operator_type` | Ancien pipeline | NULL si seul multi-opérateur. |
| `first_transaction` | Ancien pipeline | NULL si seul multi-opérateur. |
| `last_transaction` | Ancien pipeline | NULL si seul multi-opérateur. |

### Risque et scores (ancien pipeline)

| Colonne | Remplie par | Commentaire |
|---------|-------------|-------------|
| `churn_probability` | Ancien pipeline | 0 si seul multi-opérateur. |
| `has_recent_failures` | Ancien pipeline | 0 si seul multi-opérateur. |
| `failure_streak` | Ancien pipeline | 0 si seul multi-opérateur. |
| `is_high_value_client` | Ancien pipeline | 0 si seul multi-opérateur. |
| `payment_reliability_score` | Ancien pipeline | 0 si seul multi-opérateur. |
| `engagement_score` | Ancien pipeline | 0 si seul multi-opérateur. |
| `lifetime_value_score` | Ancien pipeline | 0 si seul multi-opérateur. |
| `client_segment` | Ancien pipeline | "unknown" si seul multi-opérateur. |

### Features Timwe (multi-opérateur)

| Colonne | Remplie par | Commentaire |
|---------|-------------|-------------|
| `timwe_success_rate` | **Multi-opérateur** | Rempli (0 si aucune tx Timwe). |
| `timwe_total_attempts` | **Multi-opérateur** | Rempli. |
| `timwe_total_successes` | **Multi-opérateur** | Rempli. |
| `timwe_avg_revenue_per_success` | **Multi-opérateur** | Rempli (0 si aucun succès). |
| `timwe_no_balance_rate` | **Multi-opérateur** | Rempli. |
| `timwe_not_delivered_rate` | **Multi-opérateur** | Rempli. |
| `timwe_has_activity` | **Multi-opérateur** | 0 ou 1. |

### Features Eklektik (multi-opérateur)

Les taux Eklektik sont déduits de `transactions_history` (statuts + `result`). Référence agrégée : **`eklektik_stats_daily`** (charges par opérateur Orange, TT, Taraji). Indicateur client : **`client_abonnement_expiration` = NULL** = abo facturé et resté actif. Voir `TRANSACTIONS_RESULT_SUCCESS_CRITERIA.md`.

| Colonne | Remplie par | Commentaire |
|---------|-------------|-------------|
| `eklektik_success_rate` | **Multi-opérateur** | Rempli. |
| `eklektik_total_attempts` | **Multi-opérateur** | Rempli. |
| `eklektik_total_subscriptions` | **Multi-opérateur** | Rempli. |
| `eklektik_avg_daily_successes` | **Multi-opérateur** | Rempli. |
| `eklektik_daily_consistency` | **Multi-opérateur** | Rempli. |
| `eklektik_has_activity` | **Multi-opérateur** | 0 ou 1. |

### Features Ooredoo/DGV (multi-opérateur)

| Colonne | Remplie par | Commentaire |
|---------|-------------|-------------|
| `ooredoo_success_rate` | **Multi-opérateur** | Rempli. |
| `ooredoo_total_attempts` | **Multi-opérateur** | Rempli. |
| `ooredoo_total_subscriptions` | **Multi-opérateur** | Rempli. |
| `ooredoo_avg_monthly_successes` | **Multi-opérateur** | Rempli. |
| `ooredoo_monthly_consistency` | **Multi-opérateur** | Rempli. |
| `ooredoo_has_activity` | **Multi-opérateur** | 0 ou 1. |

### État abonnement et opérateur (multi-opérateur)

Jointure utilisée : **client_abonnement** + **country_payments_methods** + **abonnement_tarifs** + **abonnement** (voir `TRANSACTIONS_RESULT_SUCCESS_CRITERIA.md`). À partir de `client_abonnement_expiration` et de la date de calcul : **facturé** = expiration NULL ; **expiré** = expiration &lt; date ; **actif** = expiration NULL ou ≥ date. Opérateur déduit de **tarif_id** (Orange=10,16 ; TT=15 ; Ooredoo=39).

| Colonne | Remplie par | Commentaire |
|---------|-------------|-------------|
| `subs_facture_count` | **Multi-opérateur** | Nb abonnements facturés (expiration NULL) dans la fenêtre 6 mois. |
| `subs_expire_count` | **Multi-opérateur** | Nb abonnements expirés (expiration &lt; date de calcul). |
| `subs_actif_count` | **Multi-opérateur** | Nb abonnements actifs à la date (expiration NULL ou ≥ date). |
| `has_facture_subscription` | **Multi-opérateur** | 1 si au moins un abo facturé, 0 sinon. |
| `orange_subs_count` | **Multi-opérateur** | Nb abonnements Orange (tarif_id 10, 16). |
| `tt_subs_count` | **Multi-opérateur** | Nb abonnements TT (tarif_id 15). |
| `ooredoo_subs_count` | **Multi-opérateur** | Nb abonnements Ooredoo/DGV (tarif_id 39). |

### Cross-opérateur et préférences (multi-opérateur)

| Colonne | Remplie par | Commentaire |
|---------|-------------|-------------|
| `total_operators_used` | **Multi-opérateur** | Rempli. |
| `operator_diversity_score` | **Multi-opérateur** | Rempli. |
| `price_preference` | **Multi-opérateur** | "low" / "high" / "mixed" ou **"unknown"** si aucun abo. |
| `unique_price_points` | **Multi-opérateur** | Rempli. |
| `prefers_low_price` | **Multi-opérateur** | 0 ou 1. |
| `prefers_high_price` | **Multi-opérateur** | 0 ou 1. |
| `is_multi_operator_user` | **Multi-opérateur** | 0 ou 1. |
| `best_performing_operator` | **Multi-opérateur** | "timwe" / "eklektik" / "ooredoo" / "none". |

### Type d’offre (multi-opérateur)

| Colonne | Remplie par | Commentaire |
|---------|-------------|-------------|
| `daily_offers_count` | **Multi-opérateur** | Rempli. |
| `monthly_offers_count` | **Multi-opérateur** | Rempli. |
| `total_offers_count` | **Multi-opérateur** | Rempli. |
| `daily_engagement_rate` | **Multi-opérateur** | Rempli. |
| `monthly_engagement_rate` | **Multi-opérateur** | Rempli. |
| `preferred_frequency` | **Multi-opérateur** | "daily" / "monthly" / "mixed" ou **"unknown"** si aucun abo. |
| `prefers_daily_offers` | **Multi-opérateur** | 0 ou 1. |
| `prefers_monthly_offers` | **Multi-opérateur** | 0 ou 1. |
| `is_frequency_flexible` | **Multi-opérateur** | 0 ou 1. |

---

## Réponse directe à la question

**Est-ce que la table est correctement alimentée avec toutes les données nécessaires dans chaque colonne pour chaque utilisateur ?**

- **Pour l’usage “multi-opérateur” (Timwe + Eklektik + Ooredoo/DGV)**  
  **Oui.** Les colonnes nécessaires à ce cas d’usage sont remplies pour chaque client et chaque date par `ml:extract-multi` :  
  `client_id`, `calculation_date`, toutes les colonnes **timwe_***, **eklektik_***, **ooredoo_***, cross-opérateur, type d’offre, `created_at`, `updated_at`. Les valeurs 0 ou "unknown" (ex. `price_preference`, `preferred_frequency`) sont des **valeurs calculées valides** quand le client n’a pas d’abonnements ou pas d’activité sur un opérateur.

- **Pour l’usage “paiement global + segment + churn + créneaux”**  
  **Seulement si** vous exécutez aussi `ml:extract-features`. Ce job remplit les colonnes paiement global, segment, scores et créneaux (surtout pour les clients Timwe). Sans ce job, ces colonnes restent à 0/NULL/"unknown", ce qui est **volontaire** : un seul job ne remplit pas toute la table.

- **En pratique**  
  - Un **client avec activité** sur au moins un opérateur aura toutes les colonnes **multi-opérateur** correctement remplies (y compris 0 sur les opérateurs sans activité).  
  - Les colonnes **ancien pipeline** ne sont “nécessaires” que si vous utilisez segment, churn, créneaux horaires, etc. ; dans ce cas, il faut lancer aussi `ml:extract-features`.

---

## Vérification rapide

```bash
# Vue d’ensemble
php artisan ml:verify-features

# Une date précise
php artisan ml:verify-features --date=2026-01-01
```

Pour un client donné et une date donnée :

```bash
php artisan ml:diagnose-features --client-id=XXX --date=YYYY-MM-DD
```
