# ✅ Configuration CRON - Diagnostic Timwe

## 📋 Problème Résolu

La page "Diagnostic des Notifications Timwe" n'affichait aucune donnée car la table `timwe_diagnostic_daily_summary` n'était pas mise à jour quotidiennement.

---

## 🔧 Solution Mise en Place

### 1. Tables de Diagnostic

Le diagnostic Timwe utilise 3 tables pré-agrégées pour une lecture rapide :

- **`timwe_diagnostic_daily_summary`** : Résumé global par jour
- **`timwe_diagnostic_daily_phone`** : Détail par téléphone et par jour
- **`timwe_diagnostic_daily_delivery`** : Détail par code de livraison (DELIVERED, NO_BALANCE, NOT_DELIVERED)

### 2. Commande de Backfill

**Commande** : `php artisan timwe:diagnostic-backfill`

**Options** :
```bash
--start-date=YYYY-MM-DD  # Date de début
--end-date=YYYY-MM-DD    # Date de fin
--force                  # Recalculer même si données existent
--only-empty             # Recalculer uniquement les dates avec 0 transaction
--analyze                # Analyser les dates insérées
--dry-run                # Compter sans écrire
```

**Exemples** :
```bash
# Remplir une période
php artisan timwe:diagnostic-backfill --start-date=2026-02-01 --end-date=2026-02-08 --force

# Remplir seulement hier
php artisan timwe:diagnostic-backfill --start-date=2026-02-08 --end-date=2026-02-08 --force

# Remplir les 30 derniers jours (dates vides uniquement)
php artisan timwe:diagnostic-backfill --start-date=2026-01-10 --end-date=2026-02-09 --only-empty
```

### 3. CRON Quotidien Ajouté

**Fichier** : `app/Console/Kernel.php`

**Configuration** :
```php
// Calcul du diagnostic Timwe quotidien - Chaque jour à 2h35 du matin
$yesterday = \Carbon\Carbon::yesterday()->format('Y-m-d');
$schedule->command("timwe:diagnostic-backfill --start-date={$yesterday} --end-date={$yesterday} --force")
    ->dailyAt('02:35')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/timwe-diagnostic.log'));
```

**Planification** :
- ⏰ **Heure** : Tous les jours à **2h35 du matin**
- 📅 **Calcul** : Données de la veille (hier)
- 🔒 **Protection** : `withoutOverlapping()` empêche les exécutions simultanées
- 📝 **Logs** : `storage/logs/timwe-diagnostic.log`

**Ordre d'exécution des CRONs Timwe** :
1. `2h30` : `timwe:calculate-daily` (statistiques Timwe générales)
2. `2h35` : `timwe:diagnostic-backfill` (diagnostic Timwe)
3. `2h45` : `ooredoo:update-daily-stats` (statistiques Ooredoo/DGV)

---

## 📊 Données Remplies

### Backfill Initial

**Période** : 02/02/2026 → 08/02/2026 (7 jours)  
**Durée** : 61 secondes (~9 secondes par jour)  
**Résultat** : ✅ 7 jours recalculés

### Statistiques de Février 2026

| Date | Transactions | Facturés | Taux | Revenu (TND) |
|------|--------------|----------|------|--------------|
| 08/02 | 2,105 | 160 | 7.6% | 480.00 |
| 07/02 | 2,386 | 130 | 5.4% | 390.00 |
| 06/02 | 2,677 | 145 | 5.4% | 435.00 |
| 05/02 | 2,380 | 146 | 6.1% | 438.00 |
| 04/02 | 2,327 | 136 | 5.8% | 408.00 |
| 03/02 | 11,214 | 112 | 1.0% | 336.00 |
| 02/02 | 7,306 | 195 | 2.7% | 585.00 |
| 01/02 | 2,996 | 166 | 5.5% | 498.00 |

**Total** : 33,391 transactions, 1,190 facturés, 3,570 TND

---

## 🧪 Vérification et Tests

### Vérifier les Données Existantes

```bash
# Via tinker
php artisan tinker
> DB::table('timwe_diagnostic_daily_summary')->orderBy('stat_date','desc')->take(7)->get(['stat_date','total_transactions','total_billed'])

# Via SQL direct
mysql> SELECT stat_date, total_transactions, total_billed, total_revenue_tnd 
       FROM timwe_diagnostic_daily_summary 
       WHERE stat_date >= '2026-02-01' 
       ORDER BY stat_date DESC;
```

### Vérifier les Jours Manquants

```bash
php artisan timwe:diagnostic-backfill --analyze
```

### Vérifier le CRON

```bash
# Lister tous les crons planifiés
php artisan schedule:list

# Tester l'exécution du CRON (sans attendre l'heure)
php artisan schedule:run

# Voir les logs
tail -f storage/logs/timwe-diagnostic.log
```

---

## 🚀 Prochaines Exécutions Automatiques

| Prochaine exécution | Action |
|---------------------|--------|
| **10/02/2026 à 2h35** | Calcul diagnostic pour le 09/02 |
| **11/02/2026 à 2h35** | Calcul diagnostic pour le 10/02 |
| **...** | Tous les jours à 2h35 |

Les données du diagnostic Timwe seront désormais **automatiquement mises à jour chaque nuit** !

---

## 📱 Page de Diagnostic

**URL** : `/admin/timwe-diagnostic`

La page affiche maintenant les données correctement :
- ✅ KPIs globaux
- ✅ Performance technique (statuts DELIVERED, NO_BALANCE, NOT_DELIVERED)
- ✅ Graphiques d'évolution
- ✅ Filtres par date et numéro de téléphone

---

## 🔍 Maintenance

### Recalculer une Période Spécifique

Si vous détectez des anomalies sur certaines dates :

```bash
# Recalculer une seule date
php artisan timwe:diagnostic-backfill --start-date=2026-02-05 --end-date=2026-02-05 --force

# Recalculer un mois complet
php artisan timwe:diagnostic-backfill --start-date=2026-02-01 --end-date=2026-02-28 --force
```

### Analyser les Données

```bash
# Voir les dates avec 0 transaction vs >0
php artisan timwe:diagnostic-backfill --analyze
```

### Performance

- ⚡ **Lecture** : Très rapide (données pré-agrégées)
- 🔄 **Calcul** : ~9 secondes par jour (~2 heures pour 1 an)
- 💾 **Stockage** : Environ 365 lignes par an dans `timwe_diagnostic_daily_summary`

---

## ✅ Statut Final

| Élément | Statut |
|---------|--------|
| Tables créées | ✅ Oui |
| Données historiques | ✅ Remplies (février 2026) |
| CRON quotidien | ✅ Configuré (2h35) |
| Page de diagnostic | ✅ Fonctionnelle |
| Logs | ✅ `storage/logs/timwe-diagnostic.log` |

**Date de configuration** : 2026-02-09  
**Statut** : ✅ **OPÉRATIONNEL**

---

## 💡 Commandes Utiles

```bash
# Remplir les 30 derniers jours
php artisan timwe:diagnostic-backfill --start-date=$(date -d "30 days ago" +%Y-%m-%d) --end-date=$(date +%Y-%m-%d) --force

# Remplir seulement les dates vides
php artisan timwe:diagnostic-backfill --start-date=2026-01-01 --end-date=2026-02-09 --only-empty

# Dry run (compter sans écrire)
php artisan timwe:diagnostic-backfill --start-date=2026-02-08 --end-date=2026-02-08 --dry-run

# Voir les statistiques des tables
php artisan tinker
> DB::table('timwe_diagnostic_daily_summary')->count()
> DB::table('timwe_diagnostic_daily_phone')->count()
> DB::table('timwe_diagnostic_daily_delivery')->count()
```
