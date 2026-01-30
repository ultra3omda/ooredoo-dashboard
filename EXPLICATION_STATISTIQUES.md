# Explication des Calculs du Tableau "Statistique"

**⚠️ IMPORTANT:** Ce tableau est destiné **UNIQUEMENT aux abonnés Timwe**. Les autres KPIs du dashboard restent pour tous les opérateurs.

Ce document explique comment chaque métrique du tableau "Statistique" est calculée avec les requêtes SQL correspondantes.

## 1. NEW SUB (new_sub) - Nouveaux Abonnements

**Description:** Nombre de nouveaux abonnements créés chaque jour.

**Requête SQL:**
```sql
SELECT 
    DATE(ca.client_abonnement_creation) as date,
    COUNT(*) as count
FROM client_abonnement as ca
INNER JOIN country_payments_methods as cpm 
    ON ca.country_payments_methods_id = cpm.country_payments_methods_id
WHERE ca.client_abonnement_creation BETWEEN ? AND ?
    -- Filtre opérateur appliqué si nécessaire
GROUP BY DATE(ca.client_abonnement_creation)
```

**Logique:**
- Compte tous les abonnements créés (`client_abonnement_creation`) dans la période
- Groupe par jour (DATE)
- Filtre par opérateur si sélectionné

**Code:** Lignes 1895-1906

---

## 2. UNSUB (unsub) - Désabonnements

**Description:** Nombre d'abonnements expirés chaque jour.

**Requête SQL:**
```sql
SELECT 
    DATE(ca.client_abonnement_expiration) as date,
    COUNT(*) as count
FROM client_abonnement as ca
INNER JOIN country_payments_methods as cpm 
    ON ca.country_payments_methods_id = cpm.country_payments_methods_id
WHERE ca.client_abonnement_expiration IS NOT NULL
    AND ca.client_abonnement_expiration BETWEEN ? AND ?
    -- Filtre opérateur appliqué si nécessaire
GROUP BY DATE(ca.client_abonnement_expiration)
```

**Logique:**
- Compte tous les abonnements expirés (`client_abonnement_expiration`) dans la période
- Exclut les abonnements sans date d'expiration
- Groupe par jour

**Code:** Lignes 1908-1920

---

## 3. SIMCHURN (simchurn) - Abonnements Créés et Expirés le Même Jour

**Description:** Nombre d'abonnements créés ET expirés le même jour (churn immédiat).

**Requête SQL:**
```sql
SELECT 
    DATE(ca.client_abonnement_creation) as date,
    COUNT(*) as count
FROM client_abonnement as ca
INNER JOIN country_payments_methods as cpm 
    ON ca.country_payments_methods_id = cpm.country_payments_methods_id
WHERE ca.client_abonnement_creation BETWEEN ? AND ?
    AND ca.client_abonnement_expiration IS NOT NULL
    AND DATE(ca.client_abonnement_creation) = DATE(ca.client_abonnement_expiration)
    -- Filtre opérateur appliqué si nécessaire
GROUP BY DATE(ca.client_abonnement_creation)
```

**Logique:**
- Compte les abonnements où la date de création = date d'expiration
- Indique un churn immédiat (abonnement annulé le jour même)

**Code:** Lignes 1922-1935

---

## 4. REV SIMCHURN (rev_simchurn) - Revenu des Simchurn

**Description:** Revenu généré par les abonnements simchurn (créés et expirés le même jour).

**Logique:**
- Pour chaque abonnement simchurn, extraire le montant depuis les transactions de facturation
- Somme tous les revenus des abonnements simchurn pour la journée
- Utilise les mêmes critères que NB FACTURATION : `pricepointId=63980`, `mnoDeliveryCode="DELIVERED"`, `totalCharged > 0`

**Code:** À implémenter

---

## 5. ACTIVE SUB (active_sub) - Abonnés Actifs

**Description:** Nombre total d'abonnements actifs à la fin de chaque journée (logique normale).

**Requête SQL:**
```sql
SELECT 
    DATE(?) as date,  -- Date du jour
    COUNT(*) as count
FROM client_abonnement as ca
INNER JOIN country_payments_methods as cpm 
    ON ca.country_payments_methods_id = cpm.country_payments_methods_id
WHERE cpm.country_payments_methods_name LIKE '%Timwe%'
    AND (
        -- Abonnements créés avant ou le jour J
        ca.client_abonnement_creation <= DATE_ADD(?, INTERVAL 1 DAY)
        AND (
            -- Abonnements sans expiration (actifs indéfiniment)
            ca.client_abonnement_expiration IS NULL
            OR
            -- Abonnements expirés après le jour J
            ca.client_abonnement_expiration > DATE_ADD(?, INTERVAL 1 DAY)
        )
    )
```

**Logique:**
- Compte tous les abonnements Timwe qui sont actifs à la fin de la journée
- Un abonnement est actif si :
  - Créé avant ou le jour J
  - ET (pas d'expiration OU expiration après le jour J)
- C'est la logique normale des abonnements actifs (cumulatif)

**Code:** À corriger

---

## 6. NB FACTURATION (nb_facturation) - Nombre de Facturations

**Description:** Nombre de transactions de facturation réussies chaque jour (Timwe uniquement).

**Critères de facturation:**
- `pricepointId = 63980` (billing)
- `mnoDeliveryCode = "DELIVERED"`
- `totalCharged > 0` (dans le champ `result` JSON)

**Requête SQL (optimisée si possible):**
```sql
SELECT 
    DATE(th.created_at) as date,
    COUNT(*) as count
FROM transactions_history as th
INNER JOIN client_abonnement as ca 
    ON th.client_id = ca.client_id
INNER JOIN country_payments_methods as cpm 
    ON ca.country_payments_methods_id = cpm.country_payments_methods_id
WHERE th.created_at BETWEEN ? AND ?
    AND cpm.country_payments_methods_name LIKE '%Timwe%'
    AND (
        th.status LIKE '%TIMWE_RENEWED_NOTIF%'
        OR th.status LIKE '%TIMWE_CHARGE_DELIVERED%'
    )
    -- Filtrage JSON si MySQL 5.7+ supporte JSON_EXTRACT
    AND JSON_EXTRACT(th.result, '$.pricepointId') = '63980'
    AND JSON_EXTRACT(th.result, '$.mnoDeliveryCode') = 'DELIVERED'
    AND CAST(JSON_EXTRACT(th.result, '$.totalCharged') AS DECIMAL(10,2)) > 0
GROUP BY DATE(th.created_at)
```

**Note:** Si le filtrage JSON n'est pas possible en SQL, on utilise une logique de sauvegarde quotidienne pour optimiser les performances.

**Code:** À optimiser

---

## 7. TAUX FACTURATION (taux_facturation) - Taux de Facturation

**Description:** Pourcentage de facturations par rapport aux abonnés actifs.

**Formule:**
```
taux_facturation = (nb_facturation / active_sub) * 100
```

**Logique:**
- `active_sub` = nombre d'abonnements actifs à la fin de la journée (corrigé)
- `nb_facturation` = nombre de facturations dans la journée
- Le ratio sera correct une fois `active_sub` corrigé

**Code:** Ligne 2118

---

## 8. REVENU TTC LOCAL (revenu_ttc_local) - Revenu Total TTC Local

**Description:** Revenu total TTC en devise locale (TND) chaque jour (Timwe uniquement).

**Logique:**
1. Pour chaque transaction de facturation avec les critères :
   - `pricepointId = 63980`
   - `mnoDeliveryCode = "DELIVERED"`
   - `totalCharged > 0`
2. Extrait le montant depuis le champ `result` JSON : `totalCharged`
3. Le montant est **toujours trouvé** car `totalCharged > 0` est un critère obligatoire
4. Somme tous les montants pour la journée

**Extraction du montant:**
- Utilise directement `totalCharged` du champ `result` JSON
- Pas besoin de fallback sur `tarif_prix` car `totalCharged > 0` est garanti

**Code:** Lignes 1992-2000, 2187-2250 (à simplifier)

---

## 9. REVENU TTC USD (revenu_ttc_usd) - Revenu Total TTC USD

**Description:** Revenu total TTC en USD chaque jour.

**Formule:**
```
revenu_ttc_usd = revenu_ttc_local * 0.343
```

**Taux de change:** 1 USD = 2.915 TND (donc 1 TND = 0.343 USD)

**Code:** Ligne 2123

**Note:** Le taux de change est codé en dur (1 USD = 2.915 TND). Pourrait être rendu configurable via `.env`.

---

## 10. REVENU TTC TND (revenu_ttc_tnd) - Revenu Total TTC TND

**Description:** Revenu total TTC en TND chaque jour.

**Logique:**
- Identique à `revenu_ttc_local` (même valeur)

**Code:** Ligne 2137

---

## 11. OFFRE (offre) - Nom de l'Offre

**Description:** Nom de l'offre d'abonnement pour chaque jour.

**Requête SQL:**
```sql
SELECT 
    DATE(ca.client_abonnement_creation) as date,
    MAX(a.abonnement_nom) as offer_name
FROM client_abonnement as ca
INNER JOIN country_payments_methods as cpm 
    ON ca.country_payments_methods_id = cpm.country_payments_methods_id
LEFT JOIN abonnement_tarifs as at 
    ON ca.tarif_id = at.abonnement_tarifs_id
LEFT JOIN abonnement as a 
    ON at.abonnement_id = a.abonnement_id
WHERE ca.client_abonnement_creation BETWEEN ? AND ?
    -- Filtre opérateur appliqué si nécessaire
GROUP BY DATE(ca.client_abonnement_creation)
```

**Logique:**
- Prend le MAX des noms d'offres pour chaque jour
- Si plusieurs offres le même jour, prend la première alphabétiquement

**Code:** Lignes 2068-2081

**⚠️ PROBLÈME POTENTIEL:** Si plusieurs offres différentes le même jour, seule une est affichée (MAX). Il faudrait peut-être afficher toutes les offres ou la plus fréquente.

---

## Problèmes Identifiés à Corriger

1. **REV SIMCHURN:** Toujours à 0, devrait calculer le revenu des simchurn
2. **ACTIVE SUB:** Compte les clients avec transactions dans la journée, pas les abonnés actifs à la fin de la journée
3. **TAUX FACTURATION:** Peut être > 100% si un client a plusieurs facturations
4. **REVENU TTC:** Le montant peut être 0 si non trouvé dans `result` et `tarif_prix` est NULL
5. **TAUX DE CHANGE USD:** Codé en dur, devrait être configurable
6. **OFFRE:** Affiche seulement une offre par jour (MAX), même s'il y en a plusieurs

