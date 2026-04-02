# Critères de succès (transaction réussie) par agrégateur

La colonne **`result`** (JSON) de `transactions_history` ne suit pas le même format selon l’agrégateur. **Seul Timwe** utilise `mnoDeliveryCode` / `DELIVERED`. **Eklektik** et **DGV** sont les deux agrégateurs de paiement pour le canal « Solde téléphonique » ; ils utilisent d’autres structures ; les **tables officielles** (plateforme) donnent la référence pour le nombre de facturations et le CA.

---

## Facturation directe Eklektik : pas de statut dédié

- **Les 49 statuts** de `transactions_history` ne contiennent **pas** de statut dédié « facturation » pour Eklektik. On s'appuie sur des statuts d'action (ex. `ORANGE_CHARGE_DELIVERED`, `TT_RENEWED`, `TARAJI_CHARGE_DELIVERED`) et sur le champ `result` pour déduire le succès.
- **Source de vérité pour les volumes et le CA** : la table **`eklektik_stats_daily`** (données plateforme) :
  - **`charges`** = nombre de facturations réussies par jour / opérateur / offre
  - **`total_revenue`** / **`revenu_ttc_tnd`** = CA
  - **`operator`** = Orange, TT, Taraji — les vrais chiffres facturés par Eklektik par opérateur viennent de cette table.
- **Lien avec `client_abonnement`** :
  - À chaque création d'abonnement Eklektik, il y a une **durée gratuite** au départ.
  - Selon la réponse de l'API : si le client a été **facturé**, l'abonnement reste **actif sans date d'expiration** → **`client_abonnement_expiration` = NULL**.
  - Sinon (non facturé / échec) → désabonnement (unsub) → une date d'expiration est renseignée.
  - Donc **`client_abonnement_expiration IS NULL`** pour un abonnement Eklektik est un **indicateur indirect** : le client a été facturé et est resté actif. Les chiffres agrégés « vrais » restent dans **`eklektik_stats_daily`** (charges par opérateur).

**Paiement par solde téléphonique** : **Eklektik** et **DGV** sont les noms des **agrégateurs de paiement** pour le canal « Solde téléphonique » (méthode de paiement dans `country_payments_methods`). On distingue Eklektik et DGV par les statuts de transaction (ORANGE/TT/TARAJI vs OOREDOO/DGV) ou par `abonnement_tarifs_id`.

**Mapping opérateurs / canaux (country_payments_methods_id)** :

| Opérateur / canal | country_payments_methods_id | Remarque |
|-------------------|-----------------------------|----------|
| **Taraji Mobile** (géré par Eklektik) | **10** | Offre Taraji, offre_id = 10 |
| **TT, Orange, Ooredoo** (Solde téléphonique) | **9** | Eklektik (Orange, TT) + DGV (Ooredoo) ; affiner avec jointure `abonnement_tarifs` + `abonnement` + `store` |
| **Timwe** | **11** | Paiement Timwe |

Pour **affiner la distinction** entre TT, Orange et Ooredoo (tous en CPM 9), utiliser une jointure entre `abonnement_tarifs`, la table **`abonnement`** (offre) et la table **`stores`** (via `client.sub_store` ou `abonnement.abonnement_store`) :

```sql
SELECT
  ca.client_abonnement_id,
  ca.client_id,
  ca.tarif_id,
  ca.country_payments_methods_id,
  at.abonnement_tarifs_prix,
  at.abonnement_id,
  a.abonnement_nom,
  a.abonnement_store,
  c.sub_store,
  s.store_id,
  s.store_name
FROM client_abonnement ca
JOIN abonnement_tarifs at ON at.abonnement_tarifs_id = ca.tarif_id
JOIN abonnement a ON a.abonnement_id = at.abonnement_id
JOIN client c ON c.client_id = ca.client_id
LEFT JOIN stores s ON s.store_id = c.sub_store
WHERE ca.country_payments_methods_id = 9
ORDER BY ca.client_abonnement_creation DESC;
```

On peut ensuite croiser avec les statuts `transactions_history` (ORANGE_*, TT_*, TARAJI_*, OOREDOO_*) ou avec `abonnement_nom` / `store_name` pour répartir Orange, TT et Ooredoo.

**Résultat de l'affinement (CPM 9 — Solde téléphonique)**  
Jointure exécutée (`run_operator_refinement.php`) :

| tarif_id | prix | abonnement_id | abonnement_nom | abonnement_store | Opérateur dominant |
|----------|------|---------------|----------------|------------------|--------------------|
| 10 | 0,3 | 8  | STANDARD | 13 | **Orange** |
| 15 | 0,3 | 11 | STANDARD | 20 | **TT** |
| 16 | 0,3 | 12 | STANDARD | 19 | **Orange** |
| 39 | 0,3 | 10 | STANDARD | 18 | **Ooredoo** (DGV) |
| 51 | 0,5 | 17 | Abonnement medianet | 22 | à vérifier |

En résumé pour **CPM 9** : **Orange** = tarif_id 10, 16 (abonnement_store 13, 19) ; **TT** = tarif_id 15 (abonnement_store 20) ; **Ooredoo** = tarif_id 39 (abonnement_store 18). Le champ **`abonnement_store`** (13, 18, 19, 20) permet d’affiner par « store » logique ; **`client.sub_store`** → **`stores.store_id`** donne le nom du store (ex. Club Privilèges, One Tech).

Pour recalculer : `php run_operator_refinement.php` (à la racine du projet).

**Alimentation de `ml_client_features`** : la même jointure (**client_abonnement** + **abonnement_tarifs** + **abonnement**) et le mapping **tarif_id → opérateur** (Orange/TT/Ooredoo) sont utilisés dans l’extraction multi-opérateur (`MLMultiOperatorFeatureService`). Pour chaque client et chaque date de calcul, on dérive l’**état d’abonnement** (facturé = expiration NULL, actif, expiré) et on remplit les colonnes **subs_facture_count**, **subs_expire_count**, **subs_actif_count**, **has_facture_subscription**, **orange_subs_count**, **tt_subs_count**, **ooredoo_subs_count** pour un apprentissage ML plus pertinent. Voir `docs/ML_CLIENT_FEATURES_COLUMNS_SOURCES.md`.

**Offres daily 0,3 (abonnement_tarifs)** : les offres à 0,3 (millimes/jour), paiement par solde téléphonique, sont soit **Eklektik** soit **DGV**.  
`SELECT abonnement_tarifs_id FROM abonnement_tarifs WHERE abonnement_tarifs_prix = 0.3` → ids **10, 15, 16, 39**.  
Croisement avec `client_abonnement` + statuts dans `transactions_history` donne : **10, 15, 16** = agrégateur **Eklektik** (ORANGE/TT/TARAJI) ; **39** = agrégateur **DGV/Ooredoo**. CPM « Solde téléphonique » (id 9) regroupe les deux agrégateurs ; « Carte cadeaux » (id 8) est un autre canal.

**Taraji Privileges (prix 0,45 ou 0,5 DT)** : l’offre Taraji Privileges a un prix différent (0,45 ou 0,5 DT). En base, aucun tarif à 0,45 n’a été trouvé ; les tarifs à **0,5 DT** sont les ids **6, 8, 13, 17, 43, 49, 51, 60**. Le croisement avec les statuts `ORANGE_*` / `TT_*` / `TARAJI_*` permet de répartir les **tarif_id par opérateur** :

| Prix | tarif_id | Opérateur dominant (Eklektik) |
|------|----------|-------------------------------|
| 0,3  | 10, 16   | **Orange**                    |
| 0,3  | 15       | **TT**                        |
| 0,3  | 39       | **DGV** (agrégateur)          |
| 0,5  | 43       | **Taraji** (Taraji Privileges)|
| 0,5  | 6, 8, 13, 17, 49, 51, 60 | à vérifier (peu ou pas de tx Eklektik) |

Requête pour lister les tarifs à 0,45 ou 0,5 :  
`SELECT abonnement_tarifs_id FROM abonnement_tarifs WHERE ROUND(abonnement_tarifs_prix, 2) IN (0.45, 0.5)`.

Pour recalculer la répartition tarif_id × opérateur (Orange / TT / Taraji) :  
`php run_tarif_by_operator.php` (à la racine du projet).

**Analyse match abonnements / transactions et écart USSD** : pour comparer les clients Eklektik avec facturation activée (expiration NULL) vs avec date d’expiration, et voir les types de notifications (statuts) associés, puis comparer les totaux avec `eklektik_stats_daily` (écart = hypothèse inscriptions USSD sans trace dans `transactions_history`) :

```bash
php artisan eklektik:analyze-billing-vs-subscriptions --days=90 --sample-null=500 --sample-expired=500
# Export markdown
php artisan eklektik:analyze-billing-vs-subscriptions --days=90 --export=docs/eklektik_billing_vs_subscriptions.md
```

---

## 1. Timwe

- **Format** : `result` contient en général `pricepointId`, `mnoDeliveryCode`, `totalCharged` (à la racine ou dans `response` / `data`).
- **Facturation réussie** :  
  `pricepointId` = PPID facturation (ex. 63980) **et** `mnoDeliveryCode` = `DELIVERED` **et** `totalCharged` > 0.
- **Source de vérité** : calcul depuis `transactions_history` (même critères) ; tables type `timwe_daily_stats` / agrégats Timwe.

---

## 2. Eklektik (ORANGE_*, TARAJI_*, TT_*, EKLEKTIK, etc.)

- **Format** : l’API Eklektik ne renvoie pas un champ type `mnoDeliveryCode`. Les réponses varient :  
  `SubscriptionResponse`, `GetInfoCustomerResponse`, `{"message":"OK"}`, `{"status":0}`, etc.
- **Source de vérité officielle** : table **`eklektik_stats_daily`** (données plateforme, par `date` + `operator` : Orange, TT, Taraji) :
  - **`charges`** = nombre de facturations réussies (vraies valeurs facturées par Eklektik par opérateur)
  - **`total_revenue`** / **`revenu_ttc_tnd`** = CA
  - **`renewals`** = renouvellements
- **Indicateur complémentaire** : **`client_abonnement`** — abo Eklektik créé avec période gratuite ; si l’API confirme la facturation → abonnement actif avec **`client_abonnement_expiration` = NULL** ; sinon unsub. Utile pour relier « client facturé et resté actif » (voir section « Facturation directe Eklektik » ci-dessus).
- **Dans `transactions_history`** (approximation pour le ML) :
  - **Statut** indique l’action : `ORANGE_CHARGE_DELIVERED`, `TT_RENEWED`, `TARAJI_CHARGE_DELIVERED`, etc.  
    → On considère **succès** si le statut contient `CHARGE_DELIVERED` ou `RENEWED` (Eklektik).
  - Si `result` est présent :  
    `result['success']`, ou `mnoDeliveryCode === 'DELIVERED'`, ou `result['message'] === 'OK'`, ou `result['status'] === 0` → succès.

Pour lister **toutes** les structures réelles de `result` (clés, exemples) côté Eklektik :

```bash
php artisan transactions:analyze-result-structures --operator=eklektik --sample=3000
# Optionnel : export
php artisan transactions:analyze-result-structures --operator=eklektik --export=docs/result_eklektik_analysis.md
```

---

## 3. Ooredoo / DGV

- **Format** : selon la période, `result` peut être **null** (anciennes facturations) ou contenir `type`, `status`, `event`, `invoice`, etc.
- **Source de vérité officielle** : table **`ooredoo_daily_stats`** (données officielles DGV ou calculées) :
  - **`total_billings`** = nombre de facturations réussies
  - **`revenue_tnd`** = CA
- **Dans `transactions_history`** (aligné sur `OoredooStatsService` et donc sur la logique des stats officielles) :
  - **Facturation réussie** :
    - **Jusqu’à août 2025** : `status` = `OOREDOO_PAYMENT_OFFLINE` (sans exiger de `result`).
    - **À partir du 01/09/2025** : `status` = `OOREDOO_PAYMENT_OFFLINE_INIT` **et** `result.type` = `'INVOICE'` **et** `result.status` = `'SUCCESS'`.
  - **Nouvel abonnement réussi** : `status` = `OOREDOO_PAYMENT_SUCCESS` (ancien format : `result.event` = `'Subscription'`, nouveau : `result.status` = `'SUCCESS'`).

Pour lister les structures réelles de `result` côté Ooredoo :

```bash
php artisan transactions:analyze-result-structures --operator=ooredoo --sample=3000
php artisan transactions:analyze-result-structures --operator=ooredoo --export=docs/result_ooredoo_analysis.md
```

---

## 4. Commande d’analyse globale

Pour Eklektik **et** Ooredoo (structures + exemples de `result`) :

```bash
php artisan transactions:analyze-result-structures --operator=both --sample=3000 --export=docs/result_structures_analysis.md
```

Cela permet de :
- voir **toutes les clés** présentes dans `result` par opérateur ;
- voir les **exemples de JSON** par type de structure ;
- faire évoluer les critères de succès dans le code (ex. `MLMultiOperatorFeatureService::isEklektikSuccess` / `isOoredooSuccess`) si de nouveaux champs indiquent le succès.

---

## 5. Où c’est utilisé dans le code

| Fichier | Rôle |
|--------|------|
| **MLMultiOperatorFeatureService** | `isEklektikSuccess()`, `isOoredooSuccess()`, `computeTimweFeaturesFromList()` (mnoDeliveryCode + totalCharged) |
| **OoredooStatsService** | `getBillings()`, `getRevenue()` : critères officiels facturation DGV (OOREDOO_PAYMENT_OFFLINE / OOREDOO_PAYMENT_OFFLINE_INIT + INVOICE + SUCCESS) |
| **EklektikStatsService** | Sync plateforme → `eklektik_stats_daily` (charges, CA) ; pas de lecture directe de `result` pour le CA |

En résumé : **Timwe = uniquement mno + DELIVERED** ; **Eklektik et DGV** s’appuient sur les statuts et, pour DGV, sur la logique d’`OoredooStatsService` et des tables **eklektik_stats_daily** / **ooredoo_daily_stats** pour définir et recouper les facturations réussies.
