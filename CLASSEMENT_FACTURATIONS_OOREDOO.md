# Classement des facturations Ooredoo (transactions_history)

## Contexte

Alignement avec les statistiques envoyées par Ooredoo pour les facturations :
- **Novembre 2025** : Ooredoo Privilege = 2284 (identique à notre base).
- **Décembre 2025** : Ooredoo Privilege = 4399, **LMS Ooredoo Privilege = 1073** (ce type n’était pas prévu dans le dashboard).

## Définition facturation (champ `result`)

Une **facturation** est une réponse `result` avec :
- **pricepointId** = 63982  
- **mnoDeliveryCode** = DELIVERED  
- **charge_delivered** > 0 (ou **totalCharged** > 0 selon le format)

Champs utiles pour classer les réponses : `productId`, `pricepointId`, `mcc`, `mnc`, `mnoDeliveryCode`, `entryChannel`, `tags`, `charge_delivered` / `totalCharged`.

## Statuts concernés (facturation / « charge »)

- **OOREDOO_PAYMENT_OFFLINE** : facturations avant 01/09/2025 (pas de détail type).
- **OOREDOO_PAYMENT_OFFLINE_INIT** : facturations à partir du 01/09/2025, avec `result` JSON contenant `type` et `status`.

Le seul statut contenant littéralement « charge » dans la base est **TIMWE_CHARGE_DELIVERED** (Timwe, pas Ooredoo). Le classement Ooredoo utilise donc les statuts OFFLINE / OFFLINE_INIT ci‑dessus.

## Règles de classement (après 01/09/2025)

| Type / critère | Libellé dashboard | Règle dans `transactions_history` |
|----------------|--------------------|-----------------------------------|
| **Ooredoo Privilege** | Ooredoo Privilege | `status = 'OOREDOO_PAYMENT_OFFLINE_INIT'` ET `result->type = 'INVOICE'` ET `result->status = 'SUCCESS'` |
| **LMS Ooredoo Privilege** | LMS Ooredoo Privilege | `status = 'OOREDOO_PAYMENT_OFFLINE_INIT'` ET `result->status = 'SUCCESS'` ET (`result->type = 'LMS'` OU `result` contient la chaîne `'LMS'`) |
| **Total facturations** | — | Ooredoo Privilege + LMS Ooredoo Privilege (et OFFLINE pour les dates avant 01/09/2025) |

## Implémentation

1. **OoredooStatsService**
   - `getBillingsBreakdown($start, $end)` : retourne `['ooredoo_privilege' => int, 'lms_ooredoo_privilege' => int, 'total' => int]`.
   - `getBillings($start, $end)` : retourne `getBillingsBreakdown(...)['total']`.

2. **DashboardService**
   - `calculateOoredooBillingRate()` retourne en plus :
     - `total_billings_ooredoo_privilege`
     - `total_billings_lms_ooredoo_privilege`
   - Les KPIs exposent :
     - `totalOoredooPrivilegeBillings` (current / previous / change)
     - `totalLmsOoredooPrivilegeBillings` (current / previous / change)

3. **Scripts d’analyse**
   - `php analyze_ooredoo_billing_classification.php` : détail par statut, par `result->type` (INVOICE, LMS, etc.) pour nov. et déc. 2025.
   - `php analyze_december_result_sql.php` : analyse des champs `result` de **décembre** (pricepointId, mnoDeliveryCode, charge, entryChannel, productId) ; facturation = 63982 + DELIVERED + charge > 0 ; repérage des groupes proches de **1073** (LMS).
   - `php analyze_december_result_classification.php` : analyse en PHP (chunks) de tous les `result` décembre avec classement complet (plus lent).

## Si les 1073 LMS n’apparaissent pas encore en base

Actuellement, si aucun enregistrement n’a `result->type = 'LMS'` ou `result` contenant `'LMS'`, alors **LMS Ooredoo Privilege = 0**. Dès qu’Ooredoo ou le flux DGV enverra des transactions avec un type/channel LMS (ou une chaîne « LMS » dans `result`), elles seront comptées dans **LMS Ooredoo Privilege** sans changement de code.

Pour vérifier la présence éventuelle de LMS dans `result` :

```bash
php analyze_ooredoo_billing_classification.php
```

Consulter la section « DÉCEMBRE 2025 – Facturations LMS » et « EXEMPLES result ».

## Décalage de 54 (Ooredoo Privilege en décembre)

Un écart de 54 entre les 4399 Ooredoo et notre total « Ooredoo Privilege » peut venir :
- d’un filtre côté Ooredoo (périmètre, date de prise en compte, doublons),
- d’un décalage de date (création vs date de facture),
- de transactions avec `result` invalide ou sans `type = 'INVOICE'`.

Le script d’analyse permet de comparer nos totaux (Ooredoo Privilege + LMS) aux références Ooredoo (2284, 4399, 1073) et d’ajuster les règles si besoin (ex. filtre par offre ou par champ dans `result`).
