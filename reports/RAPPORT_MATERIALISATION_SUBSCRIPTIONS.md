# Rapport de Benchmark - Subscriptions Matérialisées

## Date: 27 Mars 2026

## Contexte
L'endpoint `/api/dashboard/split/subscriptions` utilisait des requêtes live sur la table `client_abonnement` (353K lignes) pour calculer les métriques d'abonnement. Les cold cache loads prenaient 20-27 secondes pour les longues périodes.

## Solution: Table `subscription_daily_stats` matérialisée
- Table pré-calculée avec 1822 lignes quotidiennes (2021-04-01 → 2026-03-27)
- Métriques pré-agrégées: activations par canal, distribution par plan, renouvellements, durée de vie
- Cron quotidien (3h15) et hebdomadaire (dim 5h00) pour mise à jour automatique
- Fallback vers requêtes live si données matérialisées insuffisantes

## Résultats Benchmark - Cold Cache

| Période | AVANT (ms) | APRÈS (ms) | Réduction |
|---------|-----------|-----------|-----------|
| 1 mois  | 19 551    | 7 032     | **-64%**  |
| 6 mois  | 26 069    | 7 009     | **-73%**  |
| 12 mois | 27 086    | 6 482     | **-76%**  |
| Lifetime| 19 982    | 6 377     | **-68%**  |

## Observation clé
Le temps de réponse est désormais **constant** (~6.5-7s) quelle que soit la période, vs variable (20-27s) avant. Le cache Redis (30 min TTL) élimine complètement la latence pour les requêtes suivantes.

## Répartition du temps (chemin matérialisé)
- Agrégats matérialisés (channels, plans, renewal, lifespan): ~0.9s
- Rétention matérialisée: ~1.0s
- Locations trimestrielles: ~1.0s
- Détails abonnements (1000 records): ~1.5s
- Cohortes batch (1 requête): ~1.5s
- Timwe + groupement: ~0.5s

## Intégrité des données vérifiée
- daily_activations: OK (29-60 points selon période)
- retention_trend: OK (29-31 points)
- quarterly_active_locations: OK (8 trimestres)
- activations_by_channel: OK (CB, Recharge, Solde Tél.)
- plan_distribution: OK (Daily, Monthly, Annual, Other)
- cohorts: OK (6 mois)
- renewal_rate: OK (7.5%-21.8% selon période)
- average_lifespan: OK (43.5-161.2 jours selon période)
