# Diagnostic Timwe — Architecture BI et performances

## Objectif

- **Aucun calcul lourd au chargement** : toutes les stats viennent de tables d'agrégation.
- **Lifetime** ne scanne jamais `transactions_history` au runtime (lecture uniquement dans `timwe_phone_lifetime_stats`).
- **Temps total dashboard cible** : < 300 ms.

## Tableau comparatif AVANT / APRÈS (cibles)

| Bloc     | AVANT (cible ms) | APRÈS (mesure ms) | Respect cible |
|----------|------------------|--------------------|---------------|
| summary  | 20               | voir logs          | —             |
| delivery | 20               | voir logs          | —             |
| phones   | 150              | voir logs          | —             |
| lifetime | 50               | voir logs          | —             |
| **Total**| **300**          | sum des 4 blocs    | —             |

Les mesures réelles sont loguées à chaque appel d’endpoint :

- `TimweDiagnostic timing` : `block`, `ms`, `rows`, `memory_mb`, `cible_ms`, `ok`
- `TimweDiagnostic AVANT/APRÈS` : `bloc`, `AVANT_cible_ms`, `APRÈS_mesure_ms`, `respect_cible`

Exemple de recherche dans les logs :

```bash
grep "TimweDiagnostic AVANT/APRÈS" storage/logs/laravel-*.log
```

## Tables d’agrégation

- **Journalières** : `timwe_diagnostic_daily_summary`, `timwe_diagnostic_daily_phone`, `timwe_diagnostic_daily_delivery`
- **Lifetime** : `timwe_phone_lifetime_stats` (alimentée par l’observer + backfill)

## Cache Redis

- Clé : `timwe:{endpoint}:{start}:{end}:{filters}:{page}`
- TTL : summary/delivery/lifetime 10 min, phones 5 min

Pour activer Redis : `CACHE_DRIVER=redis` dans `.env` (déjà la valeur par défaut dans `config/cache.php`).

## Index obligatoires

- `idx_summary_date` sur `timwe_diagnostic_daily_summary(stat_date)`
- `idx_delivery_date` sur `timwe_diagnostic_daily_delivery(stat_date, delivery_code)`
- `idx_phone_date` sur `timwe_diagnostic_daily_phone(stat_date, client_telephone)`
- `timwe_phone_lifetime_stats` : index implicite via clé primaire `client_telephone`

## Interdictions

- Pas d’utilisation de `transactions_history` dans le flux de lecture (sauf endpoint « recent » et backfill).
- Pas de chargement de plus de 1000 lignes sans `LIMIT`.
- Pas de `foreach` PHP sur plus de 5000 éléments.
