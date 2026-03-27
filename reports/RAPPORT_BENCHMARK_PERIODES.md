# Rapport de Benchmark - Temps de Réponse Dashboard
## Date: 27 Mars 2026

## Configuration
- Backend: Laravel 10, PHP 8.2, PHP-FPM (15 workers)
- Base: MySQL distante, Redis distant
- 6 endpoints parallèles (kpis, merchants, transactions, subscriptions, ooredoo, timwe)

## Temps de réponse COLD CACHE (sans Redis)

| Endpoint       | 1 mois (28j) | 6 mois (182j) | 12 mois (365j) | Lifetime (~5 ans) |
|----------------|:------------:|:--------------:|:---------------:|:-----------------:|
| KPIs           | 5 560 ms     | 4 670 ms       | 5 576 ms        | 3 698 ms          |
| Merchants      | 4 175 ms     | 6 403 ms       | 8 375 ms        | 11 244 ms         |
| Transactions   | 2 775 ms     | 8 523 ms       | 13 539 ms       | 18 866 ms         |
| Subscriptions  | 19 551 ms    | 26 069 ms      | 27 086 ms       | 19 982 ms         |
| Ooredoo/DGV    | 2 574 ms     | 2 699 ms       | 3 169 ms        | 2 297 ms          |
| Timwe          | 2 575 ms     | 2 944 ms       | 3 031 ms        | 3 295 ms          |
| **TOTAL seq.** | **37 210 ms**| **51 308 ms**  | **60 776 ms**   | **59 382 ms**     |
| **TOTAL //**   | **~19.5s**   | **~26s**       | **~27s**        | **~20s**          |

## Temps de réponse WARM CACHE (avec Redis)
Tous les endpoints: < 500ms

## Données par période

| Période  | Activated   | Active    | Retention | Transactions |
|----------|:-----------:|:---------:|:---------:|:------------:|
| 1 mois   | 28 349      | 28 041    | 99%       | 7 574        |
| 6 mois   | 133 113     | 39 551    | 30%       | 43 729       |
| 12 mois  | 224 821     | 50 144    | 22%       | 112 879      |
| Lifetime | 353 064     | 89 197    | 25%       | 228 503      |

## Vérification visuelle (tous les onglets)
- 10/10 onglets fonctionnels pour les 4 périodes
- KPIs correctement formatés (arrondis, séparateurs, unités)
- Graphiques et tableaux remplis avec données cohérentes
- Comparaisons période-sur-période fonctionnelles

## Goulot d'étranglement
- **Subscriptions** (~20-27s cold cache) est le plus lent en raison des multiples JOINs (cohorts, retention, etc.)
- Recommandation: matérialiser les subscriptions stats dans une table dédiée
