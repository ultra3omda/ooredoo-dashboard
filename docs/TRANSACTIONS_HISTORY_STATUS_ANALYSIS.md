# Analyse des statuts — `transactions_history`

Plusieurs APIs (agrégateurs) notifient la même table `transactions_history`. Le champ **`status`** et le format du champ **`result`** (JSON) permettent de savoir quelle API a créé la ligne et comment interpréter le résultat.

---

## 1. Vue d’ensemble — mapping officiel des 3 agrégateurs

**Règle de différenciation (confirmée) :**

| Préfixe(s) / groupe | Agrégateur |
|----------------------|------------|
| **OOREDOO_** + **DELAYED_OOREDOO_** | **Ooredoo / DGV** |
| **ORANGE_** + **TARAJI_** + **TT_** | **Eklektik** |
| **TIMWE_** | **Timwe** |

- **Ooredoo/DGV** : tous les statuts dont le libellé contient `OOREDOO` (ex. `OOREDOO_PAYMENT_SUCCESS`, `OOREDOO_PAYMENT_OFFLINE`, `DELAYED_OOREDOO_PAYMENT_UNSUBSCRIBE`, etc.).
- **Eklektik** : tous les statuts qui commencent par `ORANGE_`, `TARAJI_` ou `TT_` (ex. `ORANGE_CHECK_USER`, `TT_CONFIRM_SUBSCRIBE`, `TARAJI_GET_SUBSCRIPTION`). Le statut `EKLECTIC_GET_TOKEN` (typo) est aussi à considérer comme Eklektik.
- **Timwe** : tous les statuts qui commencent par `TIMWE_` (ex. `TIMWE_RENEWED_NOTIF`, `TIMWE_CHARGE_DELIVERED`).

### Statuts réellement présents en base (exemple)

- **OOREDOO_** : `OOREDOO_PAYMENT_SUCCESS`, `OOREDOO_PAYMENT_OFFLINE`, `OOREDOO_PAYMENT_OFFLINE_INIT`, `OOREDOO_PAYMENT_UNSUBSCRIBE`, `OOREDOO_UNSUBSCRIBE`, `DELAYED_OOREDOO_PAYMENT_UNSUBSCRIBE`, `OOREDOO_CALLBACK_FAILED` / `_SUCCESS`, `OOREDOO_PURCHASE_*`, `OOREDOO_REQUEST_*`, `OOREDOO_SUBMIT_PAY`, etc.
- **ORANGE_** : `ORANGE_CHECK_USER`, `ORANGE_CONFIRM_SUBSCRIBE`, `ORANGE_GET_SUBSCRIPTION`, `ORANGE_REQUEST_SMS`, `ORANGE_UNSUBSCRIBE`.
- **TARAJI_** : `TARAJI_CHECK_USER`, `TARAJI_CONFIRM_SUBSCRIBE`, `TARAJI_GET_SUBSCRIPTION`, `TARAJI_REQUEST_SMS`, `TARAJI_UNSUBSCRIBE`.
- **TIMWE_** : `TIMWE_CHARGE_DELIVERED`, `TIMWE_RENEWED_NOTIF`, `TIMWE_CHECK_STATUS`, `TIMWE_OPTOUT_NOTIF`, `TIMWE_REQUEST_SUBSCRIPTION`, `TIMWE_REQUEST_UNSUBSCRIPTION`, `TIMWE_SEND_SMS`.
- **TT_** : `TT_CHECK_USER`, `TT_CONFIRM_SUBSCRIBE`, `TT_GET_SUBSCRIPTION`, `TT_REQUEST_SMS`, `TT_UNSUBSCRIBE`.
- **AUTRE** (hors 3 agrégateurs) : `EKLECTIC_GET_TOKEN` (typo Eklektik), `PASS_ORDER_*`, `PAYMENT_*`, `REGISTER_*`, `UNSUBSCRIPTION`.

---

## 2. Inventaire des statuts (d’après le code)

### 2.1 Statuts mappés dans `EklektikController::mapStatusToAction`

| Statut | Action mappée | Interprétation |
|--------|----------------|----------------|
| **ORANGE_** | | |
| `ORANGE_CREATE_SUBSCRIPTION` | SUB | Nouvel abonnement |
| `ORANGE_NEW_SUBSCRIPTION` | SUB | Nouvel abonnement |
| `ORANGE_CHECK_USER` | CHECK | Vérification utilisateur |
| `ORANGE_GET_SUBSCRIPTION` | CHECK | Récupération abonnement |
| `ORANGE_REQUEST_SMS` | (non mappé → CHECK) | Demande SMS (ex. V-Code) |
| `ORANGE_CONFIRM_SUBSCRIBE` | (non mappé → CHECK) | Confirmation abonnement |
| `ORANGE_RENEWED` | RENEW | Renouvellement |
| `ORANGE_CHARGE_DELIVERED` | CHARGE | Facturation livrée |
| `ORANGE_UNSUBSCRIBE` | UNSUB | Désabonnement |
| **TT_** | | |
| `TT_CREATE_SUBSCRIPTION` | SUB | Nouvel abonnement |
| `TT_NEW_SUBSCRIPTION` | SUB | Nouvel abonnement |
| `TT_CHECK_USER` | CHECK | Vérification utilisateur |
| `TT_GET_SUBSCRIPTION` | CHECK | Récupération abonnement |
| `TT_REQUEST_SMS` | (→ CHECK) | Demande SMS |
| `TT_CONFIRM_SUBSCRIBE` | (→ CHECK) | Confirmation abonnement (OK/NOK) |
| `TT_RENEWED` | RENEW | Renouvellement |
| `TT_CHARGE_DELIVERED` | CHARGE | Facturation livrée |
| `TT_UNSUBSCRIBE` | UNSUB | Désabonnement |
| **TIMWE_** | | |
| `TIMWE_CREATE_SUBSCRIPTION` | SUB | Nouvel abonnement |
| `TIMWE_NEW_SUBSCRIPTION` | SUB | Nouvel abonnement |
| `TIMWE_CHECK_STATUS` | CHECK | Vérification statut |
| `TIMWE_GET_SUBSCRIPTION` | CHECK | Récupération abonnement |
| `TIMWE_REQUEST_SUBSCRIPTION` | SUB | Demande d’abonnement |
| `TIMWE_RENEWED_NOTIF` | RENEW | Renouvellement (notif) — **utilisé pour la facturation Timwe** |
| `TIMWE_CHARGE_DELIVERED` | CHARGE | Facturation livrée — **utilisé pour la facturation Timwe** |
| `TIMWE_UNSUBSCRIBE` | UNSUB | Désabonnement |
| **OOREDOO_** | | |
| `OOREDOO_CREATE_SUBSCRIPTION` | SUB | Nouvel abonnement |
| `OOREDOO_NEW_SUBSCRIPTION` | SUB | Nouvel abonnement |
| `OOREDOO_CHECK_USER` | CHECK | Vérification utilisateur |
| `OOREDOO_GET_SUBSCRIPTION` | CHECK | Récupération abonnement |
| `OOREDOO_RENEWED` | RENEW | Renouvellement |
| `OOREDOO_CHARGE_DELIVERED` | CHARGE | Facturation livrée |
| `OOREDOO_UNSUBSCRIBE` | UNSUB | Désabonnement |
| `OOREDOO_PAYMENT_OFFLINE_INIT` | SUB | Init paiement hors ligne (ex. facture) |
| **TARAJI_** | | |
| `TARAJI_CREATE_SUBSCRIPTION` | SUB | Nouvel abonnement |
| `TARAJI_NEW_SUBSCRIPTION` | SUB | Nouvel abonnement |
| `TARAJI_RENEWED` | RENEW | Renouvellement |
| `TARAJI_CHARGE_DELIVERED` | CHARGE | Facturation livrée |
| `TARAJI_UNSUBSCRIBE` | UNSUB | Désabonnement |

### 2.2 Statuts Ooredoo/DGV (OoredooStatsService / Dashboard)

| Statut | Usage |
|--------|--------|
| `OOREDOO_PAYMENT_SUCCESS` | Nouvel abonnement (succès) — format `result` ancien ou nouveau (event/type, msisdn dans `$.msisdn` ou `$.data.user.msisdn`) |
| `OOREDOO_PAYMENT_UNSUBSCRIBE` | Désabonnement |
| `OOREDOO_UNSUBSCRIBE` | Désabonnement |
| `DELAYED_OOREDOO_PAYMENT_UNSUBSCRIBE` | Désabonnement différé |
| `OOREDOO_PAYMENT_OFFLINE` | Facturation (période mai 2022 – août 2025, `result` parfois null) |
| `OOREDOO_PAYMENT_OFFLINE_INIT` | Init facturation (à partir de sept. 2025, type=INVOICE dans `result`) |

### 2.3 Statuts Timwe (facturation / diagnostic)

- **Facturation / ML / diagnostic** : le code filtre sur  
  `status LIKE '%TIMWE_RENEWED_NOTIF%' OR status LIKE '%TIMWE_CHARGE_DELIVERED%'`.
- Variante possible : `FROM_TIMWE_RENEWED_NOTIF` est **exclue** dans certains rapports (considerée comme notification interne, pas comme facturation client).

---

## 3. Format du champ `result` (JSON) par type

- **Timwe (RENEWED_NOTIF / CHARGE_DELIVERED)**  
  - Souvent à la racine : `pricepointId`, `mnoDeliveryCode`, `totalCharged`.  
  - Parfois imbriqué : `response.pricepointId`, `data.mnoDeliveryCode`, etc.  
  - Facturation = `pricepointId` = PPID facturation (ex. 63980), `mnoDeliveryCode` = `DELIVERED`, `totalCharged` > 0.

- **TT_**  
  - Exemples vus : `{"status":0}`, `{"message":"OK"}` / `{"message":"NOK"}`, `{"code":"...","card":null,"phone":"...","tar...}` (CHECK_USER).

- **ORANGE_**  
  - Souvent encapsulé : `SubscriptionResponse`, `GetInfoCustomerResponse`, ex. `{"SubscriptionResponse":{"pin":"ok","expire":"2021..."}}`.

- **Ooredoo**  
  - Ancien : `$.msisdn`, `event: "Subscription"`.  
  - Nouveau : `$.data.user.msisdn`, `type: "SUBSCRIPTION"`, `status: "SUCCESS"`.  
  - Facturation récente : `OOREDOO_PAYMENT_OFFLINE_INIT` avec structure type INVOICE (ex. `invoice.price`).

---

## 4. Règle de différenciation des 3 agrégateurs (appliquée dans le code)

| Agrégateur | Critères SQL / code |
|-------------|----------------------|
| **Timwe** | `status LIKE 'TIMWE_%'` (ex. facturation : `TIMWE_RENEWED_NOTIF`, `TIMWE_CHARGE_DELIVERED`) |
| **Eklektik** | `status LIKE 'ORANGE_%' OR status LIKE 'TARAJI_%' OR status LIKE 'TT_%'` (et `EKLECTIC_%` si présent) |
| **Ooredoo/DGV** | `status LIKE '%OOREDOO%'` (inclut `OOREDOO_*` et `DELAYED_OOREDOO_*`) |

---

## 5. Commande pour lister les statuts en base

Pour avoir la liste **réelle** des statuts présents dans votre base, avec regroupement par préfixe et optionnellement le nombre de lignes :

```bash
# Liste des statuts distincts, regroupés par préfixe
php artisan transactions:analyze-statuses

# Avec nombre de lignes par statut
php artisan transactions:analyze-statuses --with-count

# Export CSV
php artisan transactions:analyze-statuses --with-count --export=statuses.csv
```

Ensuite vous pouvez adapter les filtres dans le code (ML, diagnostic, rapports) en vous basant sur cette liste et sur la section 4.
