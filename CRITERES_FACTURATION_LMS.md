# Critères facturation normale vs facturation LMS

## Contexte important : deux intégrateurs Ooredoo

Ooredoo a **deux intégrateurs** dans le dashboard ; les formules de calcul sont distinctes et **toutes les deux correctes** :

1. **Timwe** : facturations basées sur les réponses Timwe → statuts `TIMWE_RENEWED_NOTIF` ou `TIMWE_CHARGE_DELIVERED` dans `transactions_history`. Critères dans `result` : `pricepointId=63980`, `mnoDeliveryCode=DELIVERED`, `totalCharged>0`.
2. **Ooredoo DGV** : facturations basées sur les réponses Ooredoo DGV → statuts `OOREDOO_PAYMENT_OFFLINE` (avant 01/09/2025) ou `OOREDOO_PAYMENT_OFFLINE_INIT` (après). Critères dans `result` : `type=INVOICE` (Ooredoo Privilege) ou `type=LMS` / `dimensions.billingChannel=LMS` (LMS Ooredoo Privilege).

Ne pas mélanger les deux : on ne compte pas les facturations Ooredoo DGV avec les statuts Timwe, ni l’inverse.

---

## 1. Timwe (transactions_history – statuts TIMWE_*)

### Facturation normale (transactions Timwe)

| Critère | Valeur / condition |
|--------|---------------------|
| **status** (table) | `LIKE '%TIMWE_RENEWED_NOTIF%'` ou `LIKE '%TIMWE_CHARGE_DELIVERED%'` |
| **result.pricepointId** | `63980` |
| **result.mnoDeliveryCode** | `'DELIVERED'` |
| **result.totalCharged** | `> 0` |

### Facturation LMS (parmi les mêmes transactions Timwe)

Même **status** (TIMWE_RENEWED_NOTIF ou TIMWE_CHARGE_DELIVERED), mais **paramètre différent dans `result`** :

- **result.type** = `'LMS'`, ou  
- **result.dimensions.billingChannel** = `'LMS'`, ou  
- **result.channel** / **result.entryChannel** / **result.source** = `'LMS'`, ou  
- **result.pricepointId** = une valeur dédiée LMS (à confirmer avec les données).

Dès qu’une de ces valeurs apparaît dans le flux Timwe, les lignes peuvent être comptées comme LMS.

---

## 2. Deux intégrateurs, deux formules

- **Rubrique Timwe** : facturations = transactions avec statut `TIMWE_RENEWED_NOTIF` ou `TIMWE_CHARGE_DELIVERED` (et critères `result` Timwe).
- **Rubrique Ooredoo DGV** : facturations = transactions avec statut `OOREDOO_PAYMENT_OFFLINE` ou `OOREDOO_PAYMENT_OFFLINE_INIT` (et critères `result` DGV : type INVOICE / LMS).
Chaque rubrique utilise sa propre formule ; ne pas croiser les statuts.

---

## 3. Champs à vérifier dans `result` pour identifier LMS

Résultat de l’extraction sur décembre 2025 (`extract_lms_criteria.php`) :

| Champ | Valeurs distinctes vues (déc. 2025) | Rôle possible pour LMS |
|-------|-------------------------------------|-------------------------|
| **result.type** | `INVOICE` (41 729), `SUBSCRIPTION` (4 142), `EXPIRATION` (939) | **Paramètre différent si le flux envoie `type = 'LMS'`** (aucune valeur LMS dans l’échantillon). |
| **result.dimensions.billingChannel** | `MNO-BILLING` (46 810) | **Paramètre différent si Ooredoo envoie `billingChannel = 'LMS'`** pour les 1073 LMS. |
| **result.dimensions.orderChannel** | `BROWSER` (45 669), `USSD` (1 123), `SMS` (18) | Possible valeur dédiée LMS (ex. `LMS`) si Ooredoo l’utilise. |
| **result.dimensions.networkChannel** | `WIFI-LAN` (26 329), `CELLULAR` (20 481) | Idem, à surveiller si une valeur LMS apparaît. |
| channel, entryChannel, source (racine) | (aucune valeur trouvée) | Structure Ooredoo utilise `dimensions.*` plutôt que ces champs à la racine. |

En pratique : le **paramètre différent** pour la facturation LMS est soit **`result.type` = `'LMS'`**, soit **`result.dimensions.billingChannel`** (ou autre sous-objet `dimensions`) avec une valeur dédiée LMS. Dès qu’une de ces valeurs apparaît dans le flux, on peut compter les lignes comme LMS.

---

## 4. Implémentation actuelle

**Ooredoo DGV** (`OoredooStatsService::getBillingsBreakdown()`) : source = statuts **OOREDOO_PAYMENT_OFFLINE** / **OOREDOO_PAYMENT_OFFLINE_INIT**.

- **Facturation normale (Ooredoo Privilege)** :  
  `JSON_EXTRACT(result, '$.type') = 'INVOICE'` et `JSON_EXTRACT(result, '$.status') = 'SUCCESS'`.
- **Facturation LMS (LMS Ooredoo Privilege)** :  
  `JSON_EXTRACT(result, '$.type') = 'LMS'` **ou** `result.dimensions.billingChannel = 'LMS'` (ou `result LIKE '%LMS%'` avec type ≠ INVOICE).

**Timwe** (`TimweStatsService`) : source = statuts **TIMWE_RENEWED_NOTIF** / **TIMWE_CHARGE_DELIVERED** ; critères `result` = pricepointId 63980, mnoDeliveryCode DELIVERED, totalCharged > 0.

---

## 5. Script d’extraction des critères LMS

Le script **`extract_lms_criteria.php`** (voir ci‑dessus) parcourt les `result` de décembre pour :

- lister les **valeurs distinctes** de `type`, `channel`, `entryChannel`, `source`, `productId`, etc. ;
- repérer les clés dont le nom ou la valeur évoque **LMS** ;

à partir de là, on valide quel est le **paramètre différent** pour la facturation LMS dans la réponse.

---

## 6. Facturations « autres » Timwe (nouveau calcul)

**Définition** : parmi les réponses Timwe (statuts `TIMWE_RENEWED_NOTIF` / `TIMWE_CHARGE_DELIVERED`), les **facturations autres** sont toutes les lignes qui ne sont **pas** la facturation normale (pricepointId 63980, mnoDeliveryCode DELIVERED, totalCharged > 0).

- **Facturation normale** : pricepointId = 63980, mnoDeliveryCode = DELIVERED, totalCharged > 0.
- **Facturations autres** : tout le reste (autre pricepointId, autre mnoDeliveryCode, totalCharged ≤ 0, etc.).

On peut distinguer :
- **Autres (total)** : toutes les réponses Timwe hors normale.
- **Autres avec totalCharged > 0** : parmi les autres, celles avec un montant facturé (facturations « autres » potentiellement facturables).

**Script d’analyse** : **`analyze_timwe_december_autres.php`**

- Parcourt **uniquement** les transactions décembre avec status `TIMWE_RENEWED_NOTIF` ou `TIMWE_CHARGE_DELIVERED`.
- Affiche : volumétrie, facturation normale, facturations autres (total et avec totalCharged > 0).
- Répartition des « autres » par pricepointId, mnoDeliveryCode, et par combinaison (pricepointId | mnoDeliveryCode | totalCharged>0).
- Pour comparer aux chiffres de l’intégrateur :  
  `php analyze_timwe_december_autres.php [référence]`  
  (ex. `php analyze_timwe_december_autres.php 1073` pour signaler les groupes proches de 1073 ± 150).
