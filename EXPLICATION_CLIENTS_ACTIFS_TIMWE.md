# 📊 Explication : Clients Actifs Timwe

## 🔍 Vue d'ensemble des statuts TIMWE

Dans la table `transactions_history`, il existe **7 statuts TIMWE différents** :

| Statut | Signification | Utilité |
|--------|--------------|---------|
| `TIMWE_REQUEST_SUBSCRIPTION` | Demande d'abonnement | Nouveau client qui s'abonne |
| `TIMWE_RENEWED_NOTIF` | **Tentative de renouvellement** | **Principal statut de facturation** |
| `TIMWE_CHARGE_DELIVERED` | **Charge livrée** | **Facturation directe** |
| `TIMWE_CHECK_STATUS` | Vérification du statut | Check de l'état de l'abonnement |
| `TIMWE_OPTOUT_NOTIF` | Notification de désabonnement | Client qui se désabonne |
| `TIMWE_REQUEST_UNSUBSCRIPTION` | Demande de désabonnement | Demande explicite de désabo |
| `TIMWE_SEND_SMS` | Envoi de SMS | Communication avec le client |

---

## 💰 Les 2 statuts de facturation

### ⚡ Statuts qui tentent de facturer :
- **`TIMWE_RENEWED_NOTIF`** : Renouvellement automatique (facturation récurrente)
- **`TIMWE_CHARGE_DELIVERED`** : Charge directe

Ces 2 statuts sont utilisés pour **tenter de prélever le client**.

### 📊 Statistiques période 23-30 janvier 2026 :

```
TIMWE_RENEWED_NOTIF:
  • Total transactions: 17 806
  • Numéros uniques: 10 965
  • Delivery codes:
    - NO_BALANCE: 16 179 (91%)  ← Échecs
    - DELIVERED: 1 295 (7%)     ← Succès
    - NOT_DELIVERED: 332 (2%)   ← Échecs

TIMWE_CHARGE_DELIVERED:
  • Total transactions: 2 951
  • Numéros uniques: 2 380
  • Delivery codes:
    - DELIVERED: 2 285 (77%)    ← Succès
    - NO_BALANCE: 659 (22%)     ← Échecs
    - NOT_DELIVERED: 7 (0.2%)   ← Échecs
```

---

## 🎯 Définition : "Clients Actifs Timwe"

### Option A : Abonnements actifs (table `client_abonnement`)
```
Critère: Abonnements Timwe NON expirés
Résultat: 5 962 clients
```

❌ **Problème** : Ne reflète pas l'activité réelle de facturation
- Des clients avec abonnement actif peuvent ne jamais être facturés
- Ne montre pas les tentatives réelles

---

### Option B : Tous les numéros avec transactions TIMWE
```
Critère: Tous les statuts TIMWE confondus
Résultat: 14 895 clients
```

❌ **Problème** : Trop large
- Inclut les désabonnements (`TIMWE_OPTOUT_NOTIF`)
- Inclut les vérifications de statut (`TIMWE_CHECK_STATUS`)
- Inclut les SMS (`TIMWE_SEND_SMS`)
- Ne représente pas les "clients actifs à facturer"

---

### ✅ Option C : Numéros qu'on a **tenté de facturer** (RECOMMANDÉ)
```
Critère: Statuts TIMWE_RENEWED_NOTIF ou TIMWE_CHARGE_DELIVERED
Résultat: 12 814 clients
```

✅ **Avantages** :
- Reflète l'**activité réelle** de facturation
- Inclut les **succès ET les échecs** de facturation
- Montre combien de clients on **tente réellement** de facturer
- Correspond aux **"NUMÉROS UNIQUES"** du diagnostic actuel

---

## 📈 Analyse des résultats (23-30 janvier 2026)

### Vue d'ensemble :
```
Total transactions TIMWE (tous statuts):    33 722
Total numéros uniques (tous statuts):       14 895

Tentatives de facturation:
  → Total transactions:                     20 757
  → Numéros uniques tentés:                 12 814  ← CLIENTS ACTIFS
  → Numéros effectivement facturés:          1 300
  → Taux de succès:                         10.15%
```

### Détail des delivery codes (facturations) :
```
DELIVERED (succès):          3 580 transactions → 1 300 numéros uniques
NO_BALANCE (échec):         16 838 transactions
NOT_DELIVERED (échec):         339 transactions
─────────────────────────────────────────────────────────────
TOTAL tentatives:           20 757 transactions → 12 814 numéros uniques
```

---

## 💡 Interprétation pour le Diagnostic Timwe

### Actuellement affiché (capture d'écran) :
```
TOTAL TRANSACTIONS:  20 757  ✓
NUMÉROS UNIQUES:     12 814  ✓ ← Clients qu'on a tenté de facturer
FACTURÉS:             1 303  ≈ (devrait être 1300)
TAUX FACTURATION:    10.17%  ✓
REVENU TOTAL:      3 909 TND ✓
```

### ✅ La logique actuelle est **CORRECTE** !

**"NUMÉROS UNIQUES" = 12 814** représente bien :
- Les clients **actifs** du point de vue de la facturation
- Tous les numéros qu'on a **tenté de facturer** dans la période
- La base de calcul pour le **taux de facturation**

---

## 🔄 Taux de Facturation : 2 méthodes possibles

### Méthode 1 : Basé sur les **tentatives**
```
Taux = Facturés / Numéros uniques tentés
     = 1 300 / 12 814
     = 10.15% ✓

→ C'est la méthode ACTUELLE du diagnostic
→ Répond à : "Sur 100 clients qu'on tente de facturer, combien paient ?"
```

### Méthode 2 : Basé sur les **abonnements actifs**
```
Taux = Facturés / Abonnements actifs
     = 1 300 / 5 962
     = 21.80%

→ Utilisé par le Dashboard (KPI)
→ Répond à : "Sur 100 clients avec abonnement, combien paient ?"
```

---

## 🎯 Conclusion et Recommandations

### ✅ Pour le **Diagnostic Timwe** :
```
"NUMÉROS UNIQUES" = Clients qu'on a TENTÉ de facturer
                  = 12 814
                  = Statuts TIMWE_RENEWED_NOTIF + TIMWE_CHARGE_DELIVERED
```

**C'est la bonne définition !** Car :
1. Reflète l'activité **réelle** de facturation
2. Permet de calculer le **taux de succès** des tentatives
3. Inclut tous les cas (succès + échecs NO_BALANCE)
4. Aide au diagnostic des problèmes de facturation

### ✅ Pour le **Dashboard (KPI)** :
```
"Clients Actifs" = Abonnements actifs
                 = 5 962
                 = Basé sur client_abonnement
```

**C'est aussi correct !** Car :
1. Reflète la **base d'abonnés** réelle
2. Permet de mesurer le **taux de paiement** des abonnés
3. Indicateur **métier** pour la direction
4. Prédictif pour les revenus attendus

---

## 📝 Résumé des chiffres clés

| Métrique | Valeur | Source | Usage |
|----------|--------|--------|-------|
| **Tous clients Timwe (tous statuts)** | 14 895 | Tous statuts TIMWE | Activité globale |
| **Clients actifs (abonnements)** | 5 962 | `client_abonnement` | Dashboard KPI |
| **Clients tentés de facturer** | 12 814 | `RENEWED` + `CHARGE` | Diagnostic |
| **Clients effectivement facturés** | 1 300 | `DELIVERED` + `charged>0` | Revenus |
| **Taux succès facturation** | 10.15% | 1300 / 12814 | Performance |
| **Taux paiement abonnés** | 21.80% | 1300 / 5962 | Rentabilité |

---

## 🚀 Amélioration possible

Si vous voulez afficher "Clients Actifs Timwe" dans le diagnostic :

```sql
SELECT COUNT(DISTINCT c.client_telephone)
FROM client_abonnement ca
JOIN client c ON ca.client_id = c.client_id
WHERE ca.country_payments_methods_id IN (IDs Timwe)
  AND (ca.client_abonnement_expiration IS NULL 
       OR ca.client_abonnement_expiration > NOW())
```

Cela donnerait une ligne supplémentaire :
```
CLIENTS ACTIFS (abonnements): 5 962
TENTATIVES DE FACTURATION:   12 814
FACTURÉS:                     1 300
```

Mais **ce n'est pas obligatoire** - la logique actuelle est déjà excellente !
