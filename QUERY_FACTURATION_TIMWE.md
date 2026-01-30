# Requête du nombre de facturation Timwe (rubrique Timwe)

Le **Nombre Facturation** affiché dans la rubrique **Timwe** du dashboard (ex. 4 345 pour décembre 2025) provient de la méthode `calculateTimweBillingRate()` dans `app/Services/DashboardService.php`.

## Source des données

- **Avec cache** : somme de `total_billings` par jour dans la table `timwe_daily_stats` pour la période (chaque jour = numéros uniques facturés).
- **Sans cache** : calcul direct sur `transactions_history` (voir critères ci‑dessus).

## Critères d’une facturation « normale » Timwe

Une ligne `transactions_history` compte comme **1 facturation** si :

1. **Statut** : `status` contient `TIMWE_RENEWED_NOTIF` **ou** `TIMWE_CHARGE_DELIVERED`.
2. **Période** : `created_at` dans la plage de la période sélectionnée.
3. **Client** (sans cache) : `client_id` appartient à un client ayant un abonnement Timwe (opérateur Timwe dans `client_abonnement`).
4. **Champ `result` (JSON)** :
   - `pricepointId` = **63980** (ou variable d’env `TIMWE_BILLING_PPID`)
   - `mnoDeliveryCode` = **DELIVERED**
   - `totalCharged` **> 0**

## Requête SQL équivalente (nombre de facturations pour une période)

Pour obtenir le **nombre de facturations** (sans déduplication par client) sur une période, en ne s’appuyant que sur `transactions_history` et le JSON `result` :

```sql
-- Exemple : décembre 2025
-- Remplace les dates selon ta période
SELECT COUNT(*) AS nombre_facturation
FROM transactions_history th
WHERE th.created_at >= '2025-12-01 00:00:00'
  AND th.created_at <  '2026-01-01 00:00:00'
  AND (th.status LIKE '%TIMWE_RENEWED_NOTIF%' OR th.status LIKE '%TIMWE_CHARGE_DELIVERED%')
  AND JSON_VALID(th.result) = 1
  AND (TRIM(BOTH '"' FROM COALESCE(JSON_UNQUOTE(JSON_EXTRACT(th.result, '$.pricepointId')), '')) = '63980')
  AND (LOWER(TRIM(BOTH '"' FROM COALESCE(JSON_UNQUOTE(JSON_EXTRACT(th.result, '$.mnoDeliveryCode')), ''))) = 'delivered')
  AND (COALESCE(JSON_EXTRACT(th.result, '$.totalCharged'), 0) + 0) > 0;
```

Avec filtre **clients Timwe uniquement** (comme le dashboard sans cache) :

```sql
-- Clients avec abonnement Timwe
WITH timwe_clients AS (
  SELECT DISTINCT ca.client_id
  FROM client_abonnement ca
  JOIN country_payments_methods cpm ON ca.country_payments_methods_id = cpm.country_payments_methods_id
  WHERE TRIM(cpm.country_payments_methods_name) LIKE '%timwe%'
)
SELECT COUNT(*) AS nombre_facturation
FROM transactions_history th
INNER JOIN timwe_clients tc ON th.client_id = tc.client_id
WHERE th.created_at >= '2025-12-01 00:00:00'
  AND th.created_at <  '2026-01-01 00:00:00'
  AND (th.status LIKE '%TIMWE_RENEWED_NOTIF%' OR th.status LIKE '%TIMWE_CHARGE_DELIVERED%')
  AND th.result IS NOT NULL AND th.result != '' AND JSON_VALID(th.result) = 1
  AND (COALESCE(JSON_UNQUOTE(JSON_EXTRACT(th.result, '$.pricepointId')), '') = '63980'
       OR JSON_EXTRACT(th.result, '$.pricepointId') = 63980)
  AND LOWER(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(th.result, '$.mnoDeliveryCode')), '')) = 'delivered'
  AND (COALESCE(JSON_EXTRACT(th.result, '$.totalCharged'), 0) + 0) > 0;
```

Le **vrai nombre de facturation normal** correspond à ce `COUNT(*)` (une ligne = une facturation).
