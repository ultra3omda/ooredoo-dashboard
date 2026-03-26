# Diagnostic Timwe – Nouveau flux API (affichage < 200 ms)

## Objectif
Afficher les données du diagnostic Timwe en **moins de 200 ms** côté API (hors réseau), même sur 90–365 jours, en s’appuyant sur les tables d’agrégats et des endpoints séparés.

## Nouveaux endpoints (sous `/admin/timwe-diagnostic/api/`)

| Endpoint | Paramètres | Retour | Cache (TTL) |
|----------|------------|--------|-------------|
| `GET /summary` | `start`, `end`, `delivery_code` (opt.) | `{ summary, total_count }` | 10 min |
| `GET /delivery` | `start`, `end` | `{ by_delivery_code[] }` | 15 min |
| `GET /phones` | `start`, `end`, `page`, `per_page`, `search_phone`, `delivery_code` | `{ by_phone[], total_phones, meta }` | 5 min |
| `GET /phones/{phone}/delivery-codes` | `start`, `end` | `{ phone, delivery_codes[] }` | 5 min |
| `GET /recent` | `start`, `end`, `limit` | `{ recent_transactions[] }` | 1 min |
| `GET /lifetime` | `phones[]=...` (batch) | `{ by_phone: { [phone]: lifetime stats } }` | 5 min |

- **Source** : tables agrégées `timwe_diagnostic_daily_summary`, `_phone`, `_delivery`. Si aucune donnée pour la période, `success: false` et `error: 'no_aggregates'`.
- **Recent** : sur périodes > 14 jours, seules les **7 derniers jours** sont scannés dans `transactions_history` (limite 500).
- **Phones** : une seule page par requête (ex. 200 ou 500 lignes), sans `delivery_codes` dans le JSON pour garder le payload léger.

## Flux frontend

1. **Chargement initial** (en parallèle) :
   - `summary` + `delivery` + `phones?page=1&per_page=200` + `recent?limit=100`
2. **Lifetime** : après réception de la page phones, un seul appel `lifetime?phones[]=...` avec les numéros de la page courante (pas toute la période).
3. **Pagination** : changement de page côté “numéros” → nouvel appel `phones?page=N` puis `lifetime` pour cette page.
4. **Détails d’un numéro** : clic “Détails” → chargement des transactions lifetime via l’endpoint existant `/admin/timwe-diagnostic/phone/{phone}/transactions`. Optionnel : appel à `phones/{phone}/delivery-codes` pour afficher les codes sur la période (lazy).

## Logs de timing (analyse performance)

- **Controller legacy** : dans `TimweDiagnosticController`, les blocs `getDiagnosticDataFromAggregates` loguent :
  - `summary`, `delivery_codes_chunk`, `phones`, `delivery_query`, `recent_tx`, `lifetime`
- **Service API** : `TimweDiagnosticApiService` logue pour chaque endpoint :
  - `block`, `ms`, `rows`, `memory_mb` (voir `Log::info('TimweDiagnostic timing', ...)`).

Tableau type en log :
```
block            | ms    | rows | memory_mb
summary          | 12.5  | 1    | 8.2
delivery         | 18.3  | 5    | 8.2
phones           | 45.1  | 200  | 10.1
recent           | 32.0  | 100  | 10.5
lifetime         | 88.0  | 200  | 12.0
```

## Cache et invalidation

- **Clés** : `timwe_diag:{endpoint}:{start}:{end}:{filters}:{page}:{perPage}` (selon endpoint).
- **Invalidation** : quand le backfill ou l’observer met à jour une date `D`, `TimweDiagnosticApiService::invalidateForDate($D)` est appelé (hook dans `TimweDiagnosticAggregateService::recalculateForDate`). Sans tags Redis, l’invalidation fine par plage n’est pas implémentée ; on s’appuie sur les TTL courts (1–15 min).

## Index SQL

- `timwe_diagnostic_daily_summary(stat_date)` (déjà en place).
- `timwe_diagnostic_daily_delivery(stat_date, delivery_code)` (migration `2026_02_03_100000_add_timwe_diagnostic_indexes`).
- `timwe_diagnostic_daily_phone` : `unique(stat_date, client_telephone)` + `index(stat_date)` + `index(client_telephone)`.

## Critères de succès

- Sur 365 jours (agrégats présents) : **summary < 50 ms**, **delivery < 50 ms**, **phones (page 500) < 200 ms**, JSON < 500 KB.
- Aucune requête ne charge plus de 5k lignes sans `LIMIT`.
- Aucun traitement PHP O(n) sur des dizaines de milliers de numéros.
