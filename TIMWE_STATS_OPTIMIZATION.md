# Optimisation des Statistiques Timwe

## 📋 Vue d'ensemble

Ce document explique le nouveau système optimisé pour le calcul et l'affichage des statistiques Timwe dans le dashboard.

### Problème résolu

Avant cette optimisation, le calcul des statistiques Timwe se faisait en temps réel à chaque chargement du dashboard, ce qui causait :
- **Timeouts** pour les périodes > 90 jours
- **Temps de réponse lents** (jusqu'à 30 secondes)
- **Charge importante** sur la base de données
- **Impossibilité** d'afficher les données historiques longues

### Solution implémentée

Un système de **pré-calcul quotidien** avec stockage des résultats dans une table dédiée :

1. ✅ **Table de cache** `timwe_daily_stats` : stocke les statistiques quotidiennes
2. ✅ **Service dédié** `TimweStatsService` : gère le calcul et le stockage
3. ✅ **Commandes Artisan** : pour calculer les données historiques et quotidiennes
4. ✅ **Cron job automatique** : calcul quotidien à 2h30 du matin
5. ✅ **DashboardService optimisé** : utilise la table de cache au lieu de calculs en temps réel

---

## 🗄️ Structure de la table `timwe_daily_stats`

```sql
CREATE TABLE `timwe_daily_stats` (
  `id` BIGINT PRIMARY KEY AUTO_INCREMENT,
  `stat_date` DATE UNIQUE NOT NULL,
  `new_subscriptions` INT DEFAULT 0,
  `unsubscriptions` INT DEFAULT 0,
  `simchurn` INT DEFAULT 0,
  `simchurn_revenue` DECIMAL(15,3) DEFAULT 0,
  `active_subscriptions` INT DEFAULT 0,
  `total_billings` INT DEFAULT 0,
  `billing_rate` DECIMAL(8,2) DEFAULT 0,
  `revenue_tnd` DECIMAL(15,3) DEFAULT 0,
  `revenue_usd` DECIMAL(15,3) DEFAULT 0,
  `total_clients` INT DEFAULT 0,
  `offers_breakdown` JSON NULL,
  `calculated_at` TIMESTAMP NOT NULL,
  `created_at` TIMESTAMP,
  `updated_at` TIMESTAMP
);
```

### Colonnes principales

| Colonne | Type | Description |
|---------|------|-------------|
| `stat_date` | DATE | Date de la statistique (unique) |
| `new_subscriptions` | INT | Nouveaux abonnements ce jour |
| `unsubscriptions` | INT | Désabonnements ce jour |
| `simchurn` | INT | Abonnements créés et expirés le même jour |
| `simchurn_revenue` | DECIMAL | Revenu des simchurn (TND) |
| `active_subscriptions` | INT | Abonnements actifs à la fin de ce jour |
| `total_billings` | INT | Nombre de facturations ce jour |
| `billing_rate` | DECIMAL | Taux de facturation (%) |
| `revenue_tnd` | DECIMAL | Revenu total en TND |
| `revenue_usd` | DECIMAL | Revenu total en USD |
| `total_clients` | INT | Nombre de clients actifs |
| `offers_breakdown` | JSON | Détail par offre |

---

## 🚀 Installation et Première utilisation

### 1. Exécuter la migration

```bash
cd ooredoo-dashboard
php artisan migrate
```

Cela créera la table `timwe_daily_stats`.

### 2. Calculer les données historiques

**Option A : Calcul complet depuis la première donnée**

```bash
php artisan timwe:calculate-historical
```

Cette commande va :
- Trouver automatiquement la date la plus ancienne dans `client_abonnement` pour Timwe
- Calculer les stats pour chaque jour jusqu'à hier
- Afficher une barre de progression

**Option B : Calcul pour une période spécifique**

```bash
php artisan timwe:calculate-historical --from=2024-01-01 --to=2024-12-31
```

**Option C : Forcer le recalcul (même si les données existent)**

```bash
php artisan timwe:calculate-historical --force
```

### 3. Vérifier que le cron job est actif

Le cron job est déjà configuré dans `app/Console/Kernel.php` :

```php
$schedule->command('timwe:calculate-daily')
    ->dailyAt('02:30')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/timwe-stats.log'));
```

**Pour Laravel Forge ou serveurs de production :**
- Le scheduler Laravel doit être actif : `* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1`

**Pour tester localement :**
```bash
php artisan schedule:run
```

---

## 📊 Commandes Artisan disponibles

### `timwe:calculate-historical`

Calcule les statistiques historiques pour une période donnée.

**Syntaxe :**
```bash
php artisan timwe:calculate-historical [--from=DATE] [--to=DATE] [--force]
```

**Options :**
- `--from=DATE` : Date de début (format Y-m-d), par défaut la date la plus ancienne dans la base
- `--to=DATE` : Date de fin (format Y-m-d), par défaut hier
- `--force` : Recalculer même si les données existent déjà

**Exemples :**

```bash
# Calculer depuis le début jusqu'à hier
php artisan timwe:calculate-historical

# Calculer pour janvier 2024
php artisan timwe:calculate-historical --from=2024-01-01 --to=2024-01-31

# Recalculer les 30 derniers jours
php artisan timwe:calculate-historical --from=2024-11-16 --force

# Calculer pour une année complète
php artisan timwe:calculate-historical --from=2024-01-01 --to=2024-12-31
```

**Sortie attendue :**
```
🚀 Début du calcul des statistiques historiques Timwe...
📅 Date de début automatique: 2023-01-01
📊 Période: du 2023-01-01 au 2024-12-15 (715 jours)
Confirmer le calcul? (yes/no) [yes]:
> yes

 715/715 [████████████████████████████] 100%

✅ Calcul terminé!
+---------------------------+-------+
| Statistique               | Valeur|
+---------------------------+-------+
| Total de jours            | 715   |
| Calculés                  | 715   |
| Ignorés (déjà existants)  | 0     |
| Erreurs                   | 0     |
+---------------------------+-------+
```

---

### `timwe:calculate-daily`

Calcule les statistiques pour une date spécifique (par défaut hier = J-1).

**Syntaxe :**
```bash
php artisan timwe:calculate-daily [--date=DATE]
```

**Options :**
- `--date=DATE` : Date à calculer (format Y-m-d), par défaut hier

**Exemples :**

```bash
# Calculer pour hier (par défaut)
php artisan timwe:calculate-daily

# Calculer pour une date spécifique
php artisan timwe:calculate-daily --date=2024-12-15

# Calculer pour aujourd'hui (déconseillé, les données sont incomplètes)
php artisan timwe:calculate-daily --date=$(date +%Y-%m-%d)
```

**Sortie attendue :**
```
🔄 Calcul des statistiques Timwe pour le 2024-12-15...
✅ Statistiques calculées avec succès!

+-----------------------------+-----------+
| Métrique                    | Valeur    |
+-----------------------------+-----------+
| Date                        | 2024-12-15|
| Nouveaux abonnements        | 142       |
| Désabonnements              | 89        |
| Simchurn                    | 12        |
| Abonnements actifs          | 4,274     |
| Total facturations          | 1,823     |
| Taux de facturation         | 42.68%    |
| Revenu TND                  | 9,115.000 |
| Revenu USD                  | 2,940.323 |
| Total clients               | 4,274     |
+-----------------------------+-----------+
```

---

## 🔄 Fonctionnement du système

### Flux de données

```
┌─────────────────────────────────────────────────────────────┐
│  1. CRON JOB QUOTIDIEN (2h30 AM)                            │
│     php artisan timwe:calculate-daily                       │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│  2. TimweStatsService::calculateAndStoreStatsForDate()      │
│     - Calcule les KPIs pour J-1                             │
│     - Stocke dans timwe_daily_stats                         │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│  3. UTILISATEUR charge le Dashboard                         │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│  4. DashboardService::calculateTimweBillingRate()           │
│     - Vérifie si les données existent dans le cache         │
│     - Si OUI : retourne instantanément (< 100ms)            │
│     - Si NON et période < 90j : calcule à la volée          │
│     - Si NON et période > 90j : retourne 0                  │
└─────────────────────────────────────────────────────────────┘
```

### Logique de cache

Le `DashboardService` utilise cette logique :

1. **Essayer le cache d'abord** : Chercher les données dans `timwe_daily_stats`
2. **Si trouvé** : Retourner instantanément (temps de réponse < 100ms)
3. **Si pas trouvé et période < 90 jours** : Calculer à la volée (compatibilité)
4. **Si pas trouvé et période > 90 jours** : Retourner 0 avec message de log

### Avantages

✅ **Performance** : Temps de réponse < 100ms (au lieu de 5-30 secondes)
✅ **Scalabilité** : Fonctionne pour des périodes de plusieurs années
✅ **Fiabilité** : Pas de timeouts, même pour de longues périodes
✅ **Maintenance** : Calculs effectués une seule fois, réutilisés ensuite
✅ **Historique** : Les données peuvent être recalculées à tout moment

---

## 🛠️ Maintenance

### Vérifier les logs

**Logs du cron job quotidien :**
```bash
tail -f storage/logs/timwe-stats.log
```

**Logs de l'application :**
```bash
tail -f storage/logs/laravel.log | grep -i timwe
```

### Recalculer des données incorrectes

Si vous détectez des données incorrectes pour une période :

```bash
# Recalculer un jour spécifique
php artisan timwe:calculate-daily --date=2024-12-15

# Recalculer une semaine
php artisan timwe:calculate-historical --from=2024-12-09 --to=2024-12-15 --force

# Recalculer un mois
php artisan timwe:calculate-historical --from=2024-12-01 --to=2024-12-31 --force
```

### Vider le cache

Si vous modifiez le code de calcul, pensez à vider le cache Laravel :

```bash
php artisan cache:clear
php artisan config:clear
```

### Supprimer et recalculer toutes les données

**⚠️ ATTENTION : Cela supprimera toutes les stats Timwe !**

```bash
# Supprimer toutes les données de la table
php artisan tinker
>>> DB::table('timwe_daily_stats')->truncate();
>>> exit

# Recalculer depuis le début
php artisan timwe:calculate-historical --force
```

---

## 📈 Monitoring et alertes

### Vérifier que les données sont à jour

```bash
# Vérifier la dernière date calculée
php artisan tinker
>>> \App\Models\TimweDailyStat::orderBy('stat_date', 'desc')->first()
>>> exit
```

**Résultat attendu :**
```php
=> App\Models\TimweDailyStat {
     id: 715,
     stat_date: "2024-12-15",
     new_subscriptions: 142,
     active_subscriptions: 4274,
     ...
   }
```

### Alertes à mettre en place

1. **Cron job n'a pas tourné** : Si `stat_date` la plus récente < J-1
2. **Erreurs de calcul** : Surveiller `storage/logs/timwe-stats.log` pour les erreurs
3. **Données manquantes** : Compter les jours dans la table vs jours attendus

---

## 🔧 Dépannage

### Problème : Le dashboard affiche toujours 0

**Cause probable** : Les données ne sont pas dans le cache

**Solution** :
```bash
# Vérifier si la table contient des données
php artisan tinker
>>> \App\Models\TimweDailyStat::count()

# Si 0, calculer les données historiques
php artisan timwe:calculate-historical
```

---

### Problème : Le cron job ne se lance pas automatiquement

**Cause probable** : Le scheduler Laravel n'est pas configuré

**Solution sur serveur de production** :

Ajouter cette ligne au crontab du serveur :
```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

Pour éditer le crontab :
```bash
crontab -e
```

**Tester le scheduler** :
```bash
php artisan schedule:list
php artisan schedule:run
```

---

### Problème : Timeouts lors du calcul historique

**Cause** : Trop de données à calculer d'un coup

**Solution** : Diviser en périodes plus petites

```bash
# Au lieu de calculer 2 ans d'un coup
php artisan timwe:calculate-historical --from=2023-01-01 --to=2024-12-31

# Diviser par année
php artisan timwe:calculate-historical --from=2023-01-01 --to=2023-12-31
php artisan timwe:calculate-historical --from=2024-01-01 --to=2024-12-31
```

---

## 📚 Référence API

### Modèle `TimweDailyStat`

**Méthodes statiques :**

```php
// Récupérer les stats pour une période
TimweDailyStat::getStatsForPeriod(Carbon $startDate, Carbon $endDate): Collection

// Vérifier si les stats existent pour une date
TimweDailyStat::hasStatsForDate(Carbon $date): bool

// Supprimer les stats pour une date (pour recalcul)
TimweDailyStat::deleteStatsForDate(Carbon $date): void
```

---

### Service `TimweStatsService`

**Méthodes publiques :**

```php
// Calculer et stocker les stats pour une date
calculateAndStoreStatsForDate(Carbon $date): bool

// Calculer les stats pour une période
calculateStatsForPeriod(Carbon $startDate, Carbon $endDate): int

// Récupérer les stats agrégées pour une période
getAggregatedStats(Carbon $startDate, Carbon $endDate): array
```

**Exemple d'utilisation :**

```php
use App\Services\TimweStatsService;
use Carbon\Carbon;

$service = app(TimweStatsService::class);

// Calculer pour hier
$success = $service->calculateAndStoreStatsForDate(Carbon::yesterday());

// Calculer pour une période
$calculated = $service->calculateStatsForPeriod(
    Carbon::parse('2024-01-01'),
    Carbon::parse('2024-01-31')
);

// Récupérer les stats agrégées
$stats = $service->getAggregatedStats(
    Carbon::parse('2024-01-01'),
    Carbon::parse('2024-01-31')
);
```

---

## 🎯 Prochaines améliorations possibles

1. **Interface d'administration** : Ajouter une page pour recalculer les stats depuis le dashboard
2. **Alertes automatiques** : Envoyer un email si le cron job échoue
3. **Cache pour les opérateurs** : Étendre le système aux autres opérateurs
4. **Optimisation des offres** : Améliorer le détail par offre dans `offers_breakdown`
5. **Export des données** : Ajouter une fonctionnalité d'export Excel des stats historiques

---

## 📞 Support

En cas de problème, vérifier :
1. Les logs : `storage/logs/laravel.log` et `storage/logs/timwe-stats.log`
2. La structure de la base de données : table `timwe_daily_stats`
3. La configuration du cron job : `php artisan schedule:list`
4. Les données dans la table : `php artisan tinker` puis `TimweDailyStat::count()`

**Date de création** : 16 Décembre 2024
**Version** : 1.0

