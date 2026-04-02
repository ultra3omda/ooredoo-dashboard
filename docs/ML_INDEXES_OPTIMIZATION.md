# Index des tables pour l’extraction ML

Ce document liste les index **actuels** (d’après les migrations) sur `transactions_history`, `client_abonnement` et `abonnement_tarifs`, et indique les **index à ajouter** pour les requêtes d’agrégation du service d’extraction ML (`MLMultiOperatorFeatureService`).

---

## 1. Colonnes utilisées dans les requêtes ML

### `transactions_history` (alias `th`)
- **WHERE** : `client_id`, `created_at` (BETWEEN), `status` (LIKE agrégateurs), `result` (IS NOT NULL pour Timwe)
- **JOIN** : non (table principale des requêtes par client et de la liste des clients actifs)

### `client_abonnement` (alias `ca`)
- **WHERE** : `client_id`, `client_abonnement_creation`, `client_abonnement_expiration`, `country_payments_methods_id` (via whereIn), `client_id`
- **JOIN** : `country_payments_methods_id` → `country_payments_methods`, `tarif_id` → `abonnement_tarifs`

### `abonnement_tarifs`
- **JOIN** : `abonnement_tarifs_id` (clé de jointure avec `ca.tarif_id`), colonnes lues : `abonnement_tarifs_prix`, `_duration`, `_frequence`

### `country_payments_methods`
- **WHERE** : `country_payments_methods_name` (LIKE pour timwe/eklektik/ooredoo/dgv)
- Déjà indexé : `idx_cpm_name` sur `country_payments_methods_name`

---

## 2. Index actuels (d’après les migrations)

### `transactions_history`

| Index | Colonnes | Migration |
|-------|----------|-----------|
| `idx_th_created_status` | `(created_at, status)` | 2026_01_30_add_indexes_for_performance |
| `idx_th_client_created` | `(client_id, created_at)` | 2026_01_30_add_indexes_for_performance |
| `idx_eklektik_transactions_created_status` | `(created_at, status)` | 2025_09_23_011555_add_eklektik_indexes |
| `idx_eklektik_transactions_status_created` | `(status, created_at)` | 2025_09_23_011555_add_eklektik_indexes |
| `idx_eklektik_transactions_result` | `(transaction_history_id, created_at, result(100))` | 2025_09_23_011555_add_eklektik_indexes |

**Verdict** : Les requêtes par client utilisent `client_id` + `created_at` + `status` → **`idx_th_client_created`** est adapté. La requête globale “clients actifs” utilise `created_at` + `status` → **`idx_th_created_status`** ou les index Eklektik conviennent. **Aucun nouvel index nécessaire** pour `transactions_history` pour le service ML.

---

### `client_abonnement`

| Index | Colonnes | Migration |
|-------|----------|-----------|
| `idx_ca_client_id` | `(client_id)` | 2025_08_28_000900_add_perf_indexes |
| `idx_ca_cpm_id` | `(country_payments_methods_id)` | 2025_08_28_000900, 2026_01_28_000001 |
| `idx_ca_creation` | `(client_abonnement_creation)` | 2025_08_28_000900, 2025_08_29_120000 |
| `idx_ca_expiration` | `(client_abonnement_expiration)` | 2025_08_28_000900, 2025_08_29_120000 |
| `idx_ca_creation_cpm` | `(client_abonnement_creation, country_payments_methods_id)` | 2026_01_30, 2025_08_29_120000 |
| `idx_ca_expiration_cpm` | `(client_abonnement_expiration, country_payments_methods_id)` | 2026_01_30 |
| `idx_ca_client_creation` | `(client_id, client_abonnement_creation)` | 2026_01_28_000001 |
| `idx_ca_client_expiration` | `(client_id, client_abonnement_expiration)` | 2026_01_28_000001 |
| `idx_client_abonnement_expiration` | `(client_abonnement_expiration)` | 2025_08_29_100118 |
| `idx_client_abonnement_creation_expiration` | `(client_abonnement_creation, client_abonnement_expiration)` | 2025_08_29_100118 |

**Manquant** : Aucun index sur **`tarif_id`**, utilisé dans le `LEFT JOIN abonnement_tarifs AS at ON ca.tarif_id = at.abonnement_tarifs_id`. Sans index sur `tarif_id`, chaque ligne de `client_abonnement` peut entraîner une recherche complète dans `abonnement_tarifs`.

**À ajouter** :  
- **`idx_ca_tarif_id`** sur `client_abonnement(tarif_id)` pour optimiser le LEFT JOIN avec `abonnement_tarifs`.

---

### `abonnement_tarifs`

Aucune migration dans le projet ne crée ou modifie cette table (table métier existante). Le modèle `AbonnementTarif` utilise `abonnement_tarifs_id` comme clé primaire, donc cette colonne est déjà indexée. **Aucune action à prévoir** pour les jointures ML.

---

## 3. Récapitulatif des actions

| Table | Action |
|-------|--------|
| `transactions_history` | Aucune – index existants suffisants pour les requêtes ML |
| `client_abonnement` | **Ajouter** `idx_ca_tarif_id` sur `(tarif_id)` |
| `abonnement_tarifs` | Aucune – PK sur `abonnement_tarifs_id` suffit |

---

## 4. Vérifier les index en base

Pour lister les index réellement présents en base (après toutes les migrations) :

```sql
-- transactions_history
SHOW INDEX FROM transactions_history;

-- client_abonnement
SHOW INDEX FROM client_abonnement;

-- abonnement_tarifs
SHOW INDEX FROM abonnement_tarifs;
```

Ou via Artisan (si une commande existe) ou un script PHP utilisant `Schema::getConnection()->getDoctrineSchemaManager()` / requêtes `information_schema`.

---

## 5. Migration créée

La migration **`2026_02_06_000002_add_client_abonnement_tarif_id_index.php`** ajoute l’index `idx_ca_tarif_id` sur `client_abonnement(tarif_id)` si la colonne existe et si l’index n’existe pas déjà.

Exécuter : `php artisan migrate` pour l’appliquer.
