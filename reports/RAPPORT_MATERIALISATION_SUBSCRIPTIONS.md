# Rapport de Benchmark Final - Toutes Matérialisations

## Date: 27 Mars 2026

## Tables Matérialisées

| Table | Lignes | Couverture | Données |
|-------|--------|------------|---------|
| `dashboard_daily_stats` | 2 281 | 365 jours | KPIs all-in-one |
| `subscription_daily_stats` | 1 822+ | 5 ans (2021-2026) | Abonnements quotidiens |
| `transaction_daily_stats` | 1 822 | 5 ans (2021-2026) | Transactions quotidiennes |

## Résultats Benchmark - Cold Cache LIFETIME (~5 ans)

| Endpoint | AVANT (ms) | APRÈS (ms) | Réduction |
|----------|-----------|-----------|-----------|
| **kpis** | 13 242 | **6 399** | **-52%** |
| **transactions** | 6 599 | **1 819** | **-72%** |
| **subscriptions** | ~20 000 | **7 336** | **-63%** |
| merchants | 4 975 | 4 297 | -14% |
| timwe | 2 093 | 1 985 | ~même |
| ooredoo | 2 085 | 2 094 | ~même |
| **TOTAL** | **~49 000** | **~23 930** | **-51%** |

## Résultats Benchmark - Cold Cache 1 MOIS

| Endpoint | APRÈS (ms) |
|----------|-----------|
| kpis | 4 225 |
| transactions | 1 483 |
| subscriptions | 7 001 |
| merchants | 4 174 |

## Stratégie de Matérialisation

### Chemin KPIs (getKPIsFromMaterialized)
1. **Priorité 1**: `dashboard_daily_stats` (si couverture ≥ 100%)
2. **Priorité 2**: `subscription_daily_stats` + `transaction_daily_stats` combinées
3. **Fallback**: Requêtes live SQL

### Chemin Transactions (getTransactionsData)
1. **Priorité 1**: `transaction_daily_stats` (si couverture ≥ 80%)
2. **Fallback**: Requêtes live SQL avec JOINs

### Chemin Subscriptions (getSubscriptionsData)
1. **Priorité 1**: `subscription_daily_stats` (si couverture ≥ 80%)
2. **Fallback**: Requêtes live SQL

## Crons Automatiques

| Commande | Fréquence | Heure |
|----------|-----------|-------|
| `materialize --days=7` | Quotidien | 03:00 |
| `materialize-subscriptions --days=7` | Quotidien | 03:15 |
| `materialize-transactions --days=7` | Quotidien | 03:30 |
| `materialize --days=365 --force` | Hebdo (dim) | 04:30 |
| `materialize-subscriptions --days=365 --force` | Hebdo (dim) | 05:00 |
| `materialize-transactions --days=365 --force` | Hebdo (dim) | 05:30 |

## Intégrité Vérifiée
- ✅ 6/6 endpoints retournent `success: true`
- ✅ KPIs: 30 clés de données
- ✅ Merchants: 50 marchands avec stats
- ✅ Transactions: volume quotidien + opérateur + plan breakdowns
- ✅ Subscriptions: 15 clés (channels, plans, cohorts, retention, lifespan)
- ✅ Timwe/Ooredoo: stats quotidiennes
