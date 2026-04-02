# RAPPORT DÉTAILLÉ - Analyse Facturations Timwe (13-26 Mars 2026)

## 1. PROBLÈME SIGNALÉ
Le 26/03/2026, la valeur "NB FACTURATION" affichait **740** au lieu de **332**.

## 2. CAUSE RACINE
Le calcul du 26/03 a été exécuté à **12:48:04** (en journée) au lieu du cron nocturne à 02:30.
La valeur 740 provient d'une **version antérieure du code** qui ne filtrait pas correctement les transactions.

### Correction appliquée
Recalcul avec `php artisan timwe:calculate-daily --date=2026-03-26` → **332 facturations** (valeur correcte).

## 3. RAPPORT DE VÉRIFICATION COMPLET

| Date       | NB FACT | Tx Total | PPID Match | Delivered | Charged | Active Subs | Taux Fact | Status |
|------------|---------|----------|------------|-----------|---------|-------------|-----------|--------|
| 2026-03-13 | 78      | 3,426    | 2,927      | 79        | 79      | 41,525      | 0.19%     | OK     |
| 2026-03-14 | 91      | 2,774    | 2,377      | 91        | 91      | 41,874      | 0.22%     | OK     |
| 2026-03-15 | 63      | 3,220    | 2,814      | 63        | 63      | 42,258      | 0.15%     | OK     |
| 2026-03-16 | 70      | 4,332    | 2,492      | 70        | 70      | 44,081      | 0.16%     | OK     |
| 2026-03-17 | 88      | 3,864    | 2,746      | 88        | 88      | 45,151      | 0.20%     | OK     |
| 2026-03-18 | 103     | 3,485    | 3,147      | 104       | 104     | 45,433      | 0.23%     | OK     |
| 2026-03-19 | 102     | 1,707    | 1,560      | 102       | 102     | 45,441      | 0.23%     | OK     |
| 2026-03-20 | 142     | 3,428    | 3,333      | 145       | 145     | 45,350      | 0.31%     | OK     |
| 2026-03-21 | 222     | 3,910    | 3,840      | 222       | 222     | 43,716      | 0.51%     | OK     |
| 2026-03-22 | 361     | 6,967    | 6,918      | 361       | 361     | 39,826      | 0.91%     | OK     |
| 2026-03-23 | 398     | 8,649    | 8,574      | 398       | 398     | 36,304      | 1.10%     | OK     |
| 2026-03-24 | 317     | 2,578    | 2,523      | 317       | 317     | 32,996      | 0.97%     | OK     |
| 2026-03-25 | 416     | 8,247    | 8,204      | 416       | 416     | 29,110      | 1.44%     | OK     |
| 2026-03-26 | **332** | 7,951    | 7,903      | 333       | 333     | 26,896      | 1.24%     | **CORRIGÉ** |

## 4. EXPLICATION DE LA LOGIQUE DE CALCUL

La requête SQL qui calcule le NB FACTURATION parcourt ces étapes :

### Étape 1 : Sélection des transactions Timwe du jour
```sql
SELECT th.client_id, th.result, c.client_telephone
FROM transactions_history th
JOIN client c ON th.client_id = c.client_id
WHERE th.created_at BETWEEN '{date} 00:00:00' AND '{date} 23:59:59'
AND (th.status LIKE '%TIMWE_RENEWED_NOTIF%' OR th.status LIKE '%TIMWE_CHARGE_DELIVERED%')
```

### Étape 2 : Filtrage en PHP (JSON dans `th.result`)
Pour chaque transaction, on parse le JSON du champ `result` et on vérifie :
1. `pricepointId` = **63980** (PPID de facturation Timwe)
2. `mnoDeliveryCode` = **DELIVERED** (notification livrée avec succès)
3. `totalCharged` > **0** (montant réellement facturé)

### Étape 3 : Déduplication par numéro de téléphone
On compte les **numéros de téléphone uniques** (via `client.client_telephone`).
Si un client est facturé 3 fois dans la journée, il est compté **1 seule fois**.

### Étape 4 : Calcul du revenu
```
Revenu TND = NB_FACTURATION × 3.0 TND (prix abonnement)
```

### Étape 5 : Taux de facturation
```
Taux = (NB_FACTURATION / Total Clients Actifs) × 100
```

## 5. ANALYSE DE LA TENDANCE MONTANTE (21-25 Mars)

La montée des facturations de 78 à 416 entre le 13 et le 25 mars est **RÉELLE**.

### Observations :
- **Active Subs en chute** : 41,525 → 26,896 (baisse de 35%)
- **Transactions en hausse** : 3,426 → 8,247 (hausse de 140%)
- **PPID Match ratio** : 85% → 99.5% (quasi-toutes les transactions sont des facturations)
- **Taux de facturation** : 0.19% → 1.44% (montée de 7.5x)

### Interprétation :
Les abonnements actifs diminuent (désabonnements massifs), mais le nombre de TENTATIVES de facturation augmente fortement (le système Timwe tente de facturer plus agressivement les clients restants). Le nombre de numéros uniques réellement facturés (Delivered + Charged) augmente proportionnellement.

C'est un comportement typique de "billing catch-up" : le système tente de rattraper les facturations manquées sur les jours précédents.

## 6. COLONNES DE LA TABLE timwe_daily_stats

| Colonne | Description | Source |
|---------|-------------|--------|
| stat_date | Date du calcul | Paramètre |
| new_subscriptions | Abonnements créés ce jour (opérateur Timwe) | `client_abonnement.client_abonnement_creation` |
| unsubscriptions | Désabonnements ce jour | `client_abonnement.client_abonnement_expiration` |
| simchurn | Abonnements créés ET expirés le même jour | `client_abonnement` |
| active_subscriptions | Abonnements actifs en fin de journée | `client_abonnement` (création <= fin jour, expiration null ou > fin jour) |
| total_clients | Clients uniques actifs | `DISTINCT client_id` de la requête ci-dessus |
| total_billings | **NB FACTURATION** (numéros uniques facturés) | `transactions_history` → JSON `result` → ppid=63980 + DELIVERED + charged>0 |
| billing_rate | total_billings / total_clients × 100 | Calculé |
| revenue_tnd | total_billings × 3.0 TND | Calculé |
| revenue_usd | revenue_tnd × 0.343 | Conversion approx |
| simchurn_revenue_tnd | Revenu des simchurn | `transactions_history` (clients simchurn uniquement) |
