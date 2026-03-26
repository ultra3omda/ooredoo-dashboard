# Analyse des statuts et result — transactions_history

Statuts distincts : **49**

## Ooredoo/DGV (16 statuts)

### 1OOREDOO\_PAYMENT\_SUCCESS

| Échantillons result (clés racine) | event, date, subscription_id, service_id, session_id, ope_id, msisdn, error_code, opt1, opt2 |
| Lignes avec result non vide | 11 |

**Exemple 1** : event=Subscription

**Exemple 2** : event=Subscription

**Critère succès / facturée** : Vérifier result.status='SUCCESS' ou result.type si présent.


### DELAYED\_OOREDOO\_PAYMENT\_UNSUBSCRIBE

| Échantillons result (clés racine) | type, status, correlationId, creationDate, operationId, date, subscription, data, user, customization, dimensions, package, offer, iat |
| Lignes avec result non vide | 1,537 |

**Exemple 1** : type=EXPIRATION, status=SUCCESS

**Exemple 2** : type=EXPIRATION, status=SUCCESS

**Critère succès / facturée** : Vérifier result.status='SUCCESS' ou result.type si présent.


### OOREDOO\_CALLBACK\_FAILED

| Échantillons result (clés racine) | session_id, error_code, error_desc, ope_id, opt1, opt2, msisdn |
| Lignes avec result non vide | 11,809 |

**Exemple 1** : 

**Exemple 2** : 

**Critère succès / facturée** : Vérifier result.status='SUCCESS' ou result.type si présent.


### OOREDOO\_CALLBACK\_SUCCESS

| Échantillons result (clés racine) | session_id, error_code, error_desc, ope_id, subscription_id, opt1, opt2, msisdn |
| Lignes avec result non vide | 8,583 |

**Exemple 1** : 

**Exemple 2** : 

**Critère succès / facturée** : Vérifier result.status='SUCCESS' ou result.type si présent.


### OOREDOO\_PAYMENT\_OFFLINE

| Échantillons result (clés racine) |  |
| Lignes avec result non vide | 0 |
| Lignes result null/vide | 65,929 |

**Critère succès / facturée** : Facturation réussie (ancienne période, result souvent null).


### OOREDOO\_PAYMENT\_OFFLINE\_INIT

| Échantillons result (clés racine) | type, status, correlationId, creationDate, operationId, date, subscription, data, user, customization, dimensions, package, offer, iat |
| Lignes avec result non vide | 237,737 |

**Exemple 1** : type=EXPIRATION, status=SUCCESS

**Exemple 2** : type=SUBSCRIPTION, status=SUCCESS

**Critère succès / facturée** : Facturation si result.type='INVOICE' et result.status='SUCCESS'.


### OOREDOO\_PAYMENT\_SUCCESS

| Échantillons result (clés racine) | event, date, subscription_id, service_id, session_id, ope_id, msisdn, error_code, opt1, opt2 |
| Lignes avec result non vide | 79,191 |

**Exemple 1** : event=Subscription

**Exemple 2** : event=Subscription

**Critère succès / facturée** : Abonnement réussi ; result peut avoir status='SUCCESS' ou event='Subscription'.


### OOREDOO\_PAYMENT\_UNSUBSCRIBE

| Échantillons result (clés racine) | event, date, service_id, subscription_id, unsubscription_reason |
| Lignes avec result non vide | 39,514 |

**Exemple 1** : event=Resiliation

**Exemple 2** : event=Resiliation

**Critère succès / facturée** : Vérifier result.status='SUCCESS' ou result.type si présent.


### OOREDOO\_PURCHASE\_CALLULAR\_VALIDATE

| Échantillons result (clés racine) | code, message, detail, data |
| Lignes avec result non vide | 20,461 |

**Exemple 1** : message=Request refused due to a technical error.

**Exemple 2** : message=Success

**Critère succès / facturée** : Vérifier result.status='SUCCESS' ou result.type si présent.


### OOREDOO\_PURCHASE\_CANCELATION

| Échantillons result (clés racine) |  |
| Lignes avec result non vide | 2,031 |

**Critère succès / facturée** : Vérifier result.status='SUCCESS' ou result.type si présent.


### OOREDOO\_PURCHASE\_CHECK\_PIN

| Échantillons result (clés racine) | code, message, data |
| Lignes avec result non vide | 18,361 |

**Exemple 1** : message=Success

**Exemple 2** : message=Success

**Critère succès / facturée** : Vérifier result.status='SUCCESS' ou result.type si présent.


### OOREDOO\_PURCHASE\_SEND\_PIN

| Échantillons result (clés racine) | code, message, detail |
| Lignes avec result non vide | 33,863 |

**Exemple 1** : message=Request refused due to a technical error. Please t

**Exemple 2** : message=Request refused due to a technical error. Please t

**Critère succès / facturée** : Vérifier result.status='SUCCESS' ou result.type si présent.


### OOREDOO\_REQUEST\_SUBSCRIBE

| Échantillons result (clés racine) |  |
| Lignes avec result non vide | 33,075 |
| Lignes result null/vide | 90 |

**Critère succès / facturée** : Vérifier result.status='SUCCESS' ou result.type si présent.


### OOREDOO\_REQUEST\_TOKEN

| Échantillons result (clés racine) |  |
| Lignes avec result non vide | 30,310 |

**Critère succès / facturée** : Vérifier result.status='SUCCESS' ou result.type si présent.


### OOREDOO\_SUBMIT\_PAY

| Échantillons result (clés racine) |  |
| Lignes avec result non vide | 30,239 |
| Lignes result null/vide | 61 |

**Critère succès / facturée** : Vérifier result.status='SUCCESS' ou result.type si présent.


### OOREDOO\_UNSUBSCRIBE

| Échantillons result (clés racine) |  |
| Lignes avec result non vide | 122 |

**Critère succès / facturée** : Vérifier result.status='SUCCESS' ou result.type si présent.


## Eklektik (Orange, TT, Taraji) (16 statuts)

### EKLECTIC\_GET\_TOKEN

| Échantillons result (clés racine) | access_token, expires_in, token_type, scope |
| Lignes avec result non vide | 2,919 |

**Exemple 1** : 

**Exemple 2** : 

**Critère succès / facturée** : Pas de facturation directe (CHECK_USER, GET_SUBSCRIPTION, etc.) ; succès = confirm='ok' pour CONFIRM/UNSUB.


### ORANGE\_CHECK\_USER

| Échantillons result (clés racine) | GetInfoCustomerResponse |
| Lignes avec result non vide | 615,251 |
| Lignes result null/vide | 13 |

**Exemple 1** : 

**Exemple 2** : 

**Critère succès / facturée** : Pas de facturation directe (CHECK_USER, GET_SUBSCRIPTION, etc.) ; succès = confirm='ok' pour CONFIRM/UNSUB.


### ORANGE\_CONFIRM\_SUBSCRIBE

| Échantillons result (clés racine) | SubscriptionResponse |
| Lignes avec result non vide | 48,546 |

**Exemple 1** : 

**Exemple 2** : 

**Critère succès / facturée** : Succès si result.confirm='ok'.


### ORANGE\_GET\_SUBSCRIPTION

| Échantillons result (clés racine) | subscritionID |
| Lignes avec result non vide | 647,323 |
| Lignes result null/vide | 59 |

**Exemple 1** : 

**Exemple 2** : 

**Critère succès / facturée** : Pas de facturation directe (CHECK_USER, GET_SUBSCRIPTION, etc.) ; succès = confirm='ok' pour CONFIRM/UNSUB.


### ORANGE\_REQUEST\_SMS

| Échantillons result (clés racine) | SubscriptionResponse |
| Lignes avec result non vide | 89,796 |

**Exemple 1** : 

**Critère succès / facturée** : Pas de facturation directe (CHECK_USER, GET_SUBSCRIPTION, etc.) ; succès = confirm='ok' pour CONFIRM/UNSUB.


### ORANGE\_UNSUBSCRIBE

| Échantillons result (clés racine) | confirm, user |
| Lignes avec result non vide | 1,131 |

**Exemple 1** : confirm=ok

**Exemple 2** : confirm=ok

**Critère succès / facturée** : Succès si result.confirm='ok'.


### TARAJI\_CHECK\_USER

| Échantillons result (clés racine) | message, mobile, status, deleted, expiration |
| Lignes avec result non vide | 77,839 |
| Lignes result null/vide | 14 |

**Exemple 1** : status=0, message=OK

**Exemple 2** : status=0, message=OK

**Critère succès / facturée** : Succès possible si result.message='OK'.


### TARAJI\_CONFIRM\_SUBSCRIBE

| Échantillons result (clés racine) | message |
| Lignes avec result non vide | 13,865 |

**Exemple 1** : message=OK

**Exemple 2** : message=OK

**Critère succès / facturée** : Succès si result.confirm='ok'.


### TARAJI\_GET\_SUBSCRIPTION

| Échantillons result (clés racine) | error, message |
| Lignes avec result non vide | 20,091 |

**Exemple 1** : message=Offre_ID Error

**Exemple 2** : message=Offre_ID Error

**Critère succès / facturée** : Succès possible si result.message='OK'.


### TARAJI\_REQUEST\_SMS

| Échantillons result (clés racine) | status |
| Lignes avec result non vide | 23,579 |

**Exemple 1** : status=0

**Exemple 2** : status=0

**Critère succès / facturée** : Succès possible si result.status=0 (entier).


### TARAJI\_UNSUBSCRIBE

| Échantillons result (clés racine) | error, message |
| Lignes avec result non vide | 644 |

**Exemple 1** : message=ACTION not found

**Exemple 2** : message=ACTION not found

**Critère succès / facturée** : Succès si result.confirm='ok'.


### TT\_CHECK\_USER

| Échantillons result (clés racine) | code, card, phone, tarif_id, price, abonnement_id |
| Lignes avec result non vide | 436,063 |
| Lignes result null/vide | 51 |

**Exemple 1** : 

**Exemple 2** : 

**Critère succès / facturée** : Pas de facturation directe (CHECK_USER, GET_SUBSCRIPTION, etc.) ; succès = confirm='ok' pour CONFIRM/UNSUB.


### TT\_CONFIRM\_SUBSCRIBE

| Échantillons result (clés racine) | message |
| Lignes avec result non vide | 30,398 |

**Exemple 1** : message=OK

**Exemple 2** : message=OK

**Critère succès / facturée** : Succès si result.confirm='ok'.


### TT\_GET\_SUBSCRIPTION

| Échantillons result (clés racine) | confirm, Description |
| Lignes avec result non vide | 43,569 |
| Lignes result null/vide | 5 |

**Exemple 1** : confirm=ko

**Exemple 2** : confirm=ko

**Critère succès / facturée** : Pas de facturation directe (CHECK_USER, GET_SUBSCRIPTION, etc.) ; succès = confirm='ok' pour CONFIRM/UNSUB.


### TT\_REQUEST\_SMS

| Échantillons result (clés racine) | status |
| Lignes avec result non vide | 45,916 |

**Exemple 1** : status=0

**Exemple 2** : status=0

**Critère succès / facturée** : Succès possible si result.status=0 (entier).


### TT\_UNSUBSCRIBE

| Échantillons result (clés racine) | confirm, user, msg |
| Lignes avec result non vide | 512 |

**Exemple 1** : confirm=ok

**Exemple 2** : confirm=ok

**Critère succès / facturée** : Succès si result.confirm='ok'.


## Timwe (7 statuts)

### TIMWE\_CHARGE\_DELIVERED

| Échantillons result (clés racine) | productId, pricepointId, mcc, mnc, msisdn, userIdentifier, largeAccount, transactionUUID, mnoDeliveryCode, entryChannel, tags, totalCharged |
| Lignes avec result non vide | 87,175 |

**Exemple 1** : mnoDeliveryCode=DELIVERED, totalCharged=300

**Exemple 2** : mnoDeliveryCode=DELIVERED, totalCharged=300

**Critère succès / facturée** : Facturation si result.mnoDeliveryCode='DELIVERED' et result.totalCharged>0.


### TIMWE\_CHECK\_STATUS

| Échantillons result (clés racine) |  |
| Lignes avec result non vide | 345,046 |

**Critère succès / facturée** : Timwe : succès = mnoDeliveryCode=DELIVERED + totalCharged>0.


### TIMWE\_OPTOUT\_NOTIF

| Échantillons result (clés racine) | productId, mcc, mnc, msisdn, userIdentifier, transactionUUID, entryChannel, tags |
| Lignes avec result non vide | 15,554 |

**Exemple 1** : 

**Exemple 2** : 

**Critère succès / facturée** : Timwe : succès = mnoDeliveryCode=DELIVERED + totalCharged>0.


### TIMWE\_RENEWED\_NOTIF

| Échantillons result (clés racine) | productId, pricepointId, mcc, mnc, msisdn, userIdentifier, largeAccount, transactionUUID, mnoDeliveryCode, entryChannel, tags, totalCharged |
| Lignes avec result non vide | 725,083 |

**Exemple 1** : mnoDeliveryCode=NO_BALANCE, totalCharged=0

**Exemple 2** : mnoDeliveryCode=NO_BALANCE, totalCharged=0

**Critère succès / facturée** : Facturation si result.mnoDeliveryCode='DELIVERED' et result.totalCharged>0.


### TIMWE\_REQUEST\_SUBSCRIPTION

| Échantillons result (clés racine) |  |
| Lignes avec result non vide | 98,530 |

**Critère succès / facturée** : Timwe : succès = mnoDeliveryCode=DELIVERED + totalCharged>0.


### TIMWE\_REQUEST\_UNSUBSCRIPTION

| Échantillons result (clés racine) |  |
| Lignes avec result non vide | 2,035 |

**Critère succès / facturée** : Timwe : succès = mnoDeliveryCode=DELIVERED + totalCharged>0.


### TIMWE\_SEND\_SMS

| Échantillons result (clés racine) |  |
| Lignes avec result non vide | 102,739 |

**Critère succès / facturée** : Timwe : succès = mnoDeliveryCode=DELIVERED + totalCharged>0.


## Autre (10 statuts)

### PASS\_ORDER\_STATUS\_ERROR

| Échantillons result (clés racine) | errorCode, errorMessage, orderNumber, orderStatus, actionCode, actionCodeDescription, amount, currency, date, attributes, authDateTime, paymentAmountInfo, bankInfo, chargeback, feeAmount, fraudLevel |
| Lignes avec result non vide | 24,558 |

**Exemple 1** : 

**Exemple 2** : 


### PASS\_ORDER\_STATUS\_SUCCESS

| Échantillons result (clés racine) | errorCode, errorMessage, orderNumber, orderStatus, actionCode, actionCodeDescription, amount, currency, date, ip, merchantOrderParams, attributes, cardAuthInfo, authDateTime, terminalId, authRefNum, paymentAmountInfo, bankInfo, chargeback, paymentWay, feeAmount, fraudLevel |
| Lignes avec result non vide | 16,386 |

**Exemple 1** : 

**Exemple 2** : 


### PASS\_ORDER\_SUCCESS

| Échantillons result (clés racine) | orderId, status, transactionId |
| Lignes avec result non vide | 93 |

**Exemple 1** : status=success

**Exemple 2** : status=success


### PASS\_PURCHASE\_SUCCESS

| Échantillons result (clés racine) | errorCode, orderId, formUrl |
| Lignes avec result non vide | 39,508 |

**Exemple 1** : 

**Exemple 2** : 


### PAYMENT\_FAILED

| Échantillons result (clés racine) | expiration, cardholderName, depositAmount, currency, authCode, ErrorCode, ErrorMessage, OrderStatus, OrderNumber, Pan, Amount, Ip |
| Lignes avec result non vide | 250 |

**Exemple 1** : 

**Exemple 2** : 


### PAYMENT\_SUCCESS

| Échantillons result (clés racine) | event, date, subscription_id, service_id, session_id, ope_id, msisdn, error_code, opt1, opt2 |
| Lignes avec result non vide | 365 |

**Exemple 1** : event=Subscription

**Exemple 2** : event=Subscription


### REGISTER\_ERROR

| Échantillons result (clés racine) | errorCode, errorMessage |
| Lignes avec result non vide | 21 |

**Exemple 1** : 

**Exemple 2** : 


### REGISTER\_PASS\_ORDER\_SUCCESS

| Échantillons result (clés racine) | client_id, reserved_to, order_price, promotion_id, updated_at, created_at, id |
| Lignes avec result non vide | 18,753 |

**Exemple 1** : 

**Exemple 2** : 


### REGISTER\_SUCCESS

| Échantillons result (clés racine) | orderId, formUrl |
| Lignes avec result non vide | 11,818 |

**Exemple 1** : 

**Exemple 2** : 


### UNSUBSCRIPTION

| Échantillons result (clés racine) |  |
| Lignes avec result non vide | 230,780 |
| Lignes result null/vide | 114,867 |


## Synthèse : transaction facturée (réussie)

- **Timwe** : `result.mnoDeliveryCode` = 'DELIVERED' et `result.totalCharged` > 0 (et pricepointId facturation).
- **Ooredoo/DGV** : statut = OOREDOO_PAYMENT_OFFLINE (facturation ancienne), ou OOREDOO_PAYMENT_OFFLINE_INIT + result.type='INVOICE' + result.status='SUCCESS', ou OOREDOO_PAYMENT_SUCCESS (abonnement).
- **Eklektik** : statut contient CHARGE_DELIVERED ou RENEWED, ou result.confirm='ok' (CONFIRM_SUBSCRIBE, UNSUBSCRIBE), ou result.message='OK' / result.status=0.
