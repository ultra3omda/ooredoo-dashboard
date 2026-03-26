# Récupération des tables Timwe / Ooredoo-DGV / Eklektik

## Diagnostic

Pour vérifier si les tables de données (Timwe, Ooredoo/DGV, Eklektik) existent et contiennent des données :

```bash
php artisan diagnose:data-tables
```

Options :
- `--json` : sortie JSON pour scripts.

---

## Les migrations ML ne suppriment pas ces tables

La migration **`2026_01_30_200000_create_ml_tables.php`** (tables ML) ne supprime **que** les tables `ml_*` dans sa méthode `down()` :

- `ml_client_segments`, `ml_ab_test_participants`, `ml_ab_tests`, `ml_model_performance`, `ml_recommendations`, `ml_predictions`, `ml_client_features`.

Elle **ne touche pas** à :

- `timwe_daily_stats`
- `ooredoo_daily_stats`
- `eklektik_stats_daily` (et tables Eklektik annexes).

Si ces tables ont disparu, la cause est ailleurs : **`migrate:fresh`**, **`migrate:rollback`**, ou une autre opération (sauvegarde/restauration, autre migration, etc.).

---

## Récupérer les tables manquantes

### 1. Recréer les tables (structure vide)

Une migration de secours recrée les tables si elles sont absentes, sans toucher aux tables existantes :

```bash
php artisan migrate --path=database/migrations/2026_01_31_120000_ensure_data_tables_timwe_ooredoo_eklektik.php
```

Ou lancer toutes les migrations en attente :

```bash
php artisan migrate
```

Tables concernées par les migrations de secours :
- **2026_01_31_120000** : `timwe_daily_stats`, `ooredoo_daily_stats`, `eklektik_stats_daily`, `eklektik_cron_config`, `eklektik_sync_tracking`, `eklektik_kpis_cache`
- **2026_01_31_130000** : `eklektik_transactions_tracking`, `eklektik_notifications_tracking`, `eklektik_stats_dailies`
- **2026_01_31_140000** : colonnes manquantes sur `eklektik_sync_tracking` (`server_info`, `memory_usage`, `execution_user`)

### 2. Remplir les tables (données)

Une fois les tables présentes, il faut **recalculer ou réimporter** les données (elles ne reviennent pas automatiquement) :

| Source | Commande(s) |
|--------|-------------|
| **Timwe** | `php artisan timwe:calculate-daily` (une date, par défaut hier)<br>Ou pour une période : `php artisan timwe:calculate-historical` |
| **Ooredoo/DGV** | `php artisan ooredoo:update-daily-stats` (une date)<br>Ou réimport complet : `php artisan ooredoo:reimport-all`<br>Ou import officiel DGV : `php artisan ooredoo:import-official-dgv` / `ooredoo:import-dgv-official` selon la commande disponible |
| **Eklektik** | `php artisan eklektik:sync-stats --period=30` (derniers 30 jours)<br>Ou via l’interface de configuration cron Eklektik |

Exemples :

```bash
# Timwe : calculer hier
php artisan timwe:calculate-daily

# Timwe : calculer une période (historique)
php artisan timwe:calculate-historical

# Ooredoo : mettre à jour les stats quotidiennes
php artisan ooredoo:update-daily-stats

# Eklektik : synchroniser les 30 derniers jours
php artisan eklektik:sync-stats --period=30
```

---

## Résumé

1. **Vérifier** : `php artisan diagnose:data-tables`
2. **Recréer les tables** si absentes : `php artisan migrate` ou la migration de secours avec `--path=...`
3. **Remplir les données** : lancer les commandes de calcul / import / sync pour Timwe, Ooredoo et Eklektik selon vos besoins.

Les migrations ML récentes ne sont pas la cause de la disparition des tables Timwe/Ooredoo/Eklektik ; la migration de secours et ces commandes permettent de recréer la structure et de récupérer les données.

---

## Commandes à lancer pour un remplissage complet (après recréation des tables)

**Une fois les tables recréées**, exécuter dans l’ordre (les commandes historiques peuvent être longues) :

```bash
# 1. Timwe – un jour (rapide)
php artisan timwe:calculate-daily

# 2. Timwe – période historique (ex. janvier 2026)
php artisan timwe:calculate-historical --from=2026-01-01 --to=2026-01-30 --force

# 3. Ooredoo – un jour (rapide)
php artisan ooredoo:update-daily-stats

# 4. Ooredoo – période historique (ex. 30 derniers jours)
php artisan ooredoo:calculate-historical --start-date=2026-01-01 --end-date=2026-01-30

# 5. Eklektik – synchronisation (nécessite API Eklektik configurée)
php artisan eklektik:sync-stats --period=30
```

**Alternative Ooredoo** (réimport complet officiel DGV + calcul des périodes restantes) :

```bash
php artisan ooredoo:reimport-all
```

Vérifier l’état des tables après remplissage :

```bash
php artisan diagnose:data-tables
```

---

## Réimporter toutes les données Eklektik

Les données Eklektik viennent de l’**API Eklektik** (`https://stats.eklectic.tn/getelements.php`). Il n’y a pas de fichier local à réimporter : il faut resynchroniser une période depuis l’API.

### 1. Vérifier la configuration

- Fichier **`config/eklektik.php`** : identifiants par opérateur (TT, Orange, Taraji) et `offers`.
- Variables **`.env`** (optionnel) : `EKLEKTIK_TT_USERNAME`, `EKLEKTIK_TT_PASSWORD`, `EKLEKTIK_ORANGE_*`, `EKLEKTIK_TARAJI_*`, `EKLEKTIK_SYNC_ENABLED=true`.

### 2. Commande pratique : réimport complet

```bash
# Derniers 365 jours (1 an), en écrasant les données existantes
php artisan eklektik:reimport-all --period=365 --force
```

Options :

- `--period=90` : 90 derniers jours (au lieu de 365).
- `--start-date=2025-01-01 --end-date=2026-01-30` : période précise.
- Sans `--force` : ne remplit que les jours où il n’y a pas encore de données.

### 3. Commande de base (sync manuelle)

```bash
# X derniers jours
php artisan eklektik:sync-stats --period=365 --force

# Période précise
php artisan eklektik:sync-stats --start-date=2025-01-01 --end-date=2026-01-30 --force

# Un seul opérateur (TT, Orange, Taraji)
php artisan eklektik:sync-stats --period=90 --operator=TT --force
```

### 4. Si la sync renvoie 0 enregistrement ou « 3 erreur(s) »

La commande affiche maintenant le détail des erreurs. Cause fréquente en local / Windows :

**Erreur SSL (cURL 60) :**  
`cURL error 60: SSL certificate problem: unable to get local issuer certificate`

- **Solution rapide** : dans `.env`, ajouter :  
  `EKLEKTIK_VERIFY_SSL=false`  
  puis relancer :  
  `php artisan eklektik:sync-stats --period=365 --force`
- En production, préférer configurer le bundle CA (php.ini : `curl.cainfo`, `openssl.cafile`) et laisser `EKLEKTIK_VERIFY_SSL` à `true` ou absent.

Autres causes possibles :
- Vérifier que l’API Eklektik est accessible (réseau, firewall).
- Vérifier identifiants et `offers` dans `config/eklektik.php` / `.env`.
- Consulter les logs : `storage/logs/laravel-*.log` (rechercher `[EKLEKTIK STATS]`).
