# 📋 GUIDE D'UTILISATION - SYSTÈME OOREDOO/DGV

## ✅ Installation Complète

### 🎯 Résumé du Système

Le système Ooredoo/DGV combine deux sources de données :

1. **Données Officielles DGV** (juin 2021 → mars 2025)
   - 1,400 jours de données importées
   - 1,146,343 facturations totales
   - 346,931.31 TND de revenus
   - Source : Fichier Excel officiel de DGV

2. **Données Calculées** (avril 2025 → aujourd'hui)
   - 263 jours calculés depuis `transactions_history`
   - 164,999 facturations
   - 49,499.70 TND de revenus
   - Logique adaptative selon les périodes

---

## 🚀 Commandes Disponibles

### 1️⃣ **Mise à Jour Quotidienne** (CRON)

```bash
php artisan ooredoo:update-daily-stats
```

- **Fonction** : Met à jour les statistiques pour J-1 (hier)
- **Planification** : Automatique chaque jour à **2h45** du matin
- **Options** :
  - `--date=YYYY-MM-DD` : Traiter une date spécifique
  - `--force` : Forcer le recalcul même pour les données officielles DGV

**Exemples** :
```bash
# Mise à jour pour hier (par défaut)
php artisan ooredoo:update-daily-stats

# Mise à jour pour une date spécifique
php artisan ooredoo:update-daily-stats --date=2025-12-18

# Forcer le recalcul d'une date dans la période DGV
php artisan ooredoo:update-daily-stats --date=2025-01-15 --force
```

---

### 2️⃣ **Import des Données Officielles DGV**

```bash
php artisan ooredoo:import-dgv-official
```

- **Fonction** : Importe les données mensuelles officielles DGV (juin 2021 → mars 2025)
- **Durée** : ~2-3 secondes
- **Résultat** : 1,400 jours importés (46 mois)

⚠️ **IMPORTANT** : Ne lancer qu'une seule fois ou après avoir vidé la table.

---

### 3️⃣ **Calcul Historique**

```bash
php artisan ooredoo:calculate-historical --start-date=YYYY-MM-DD --end-date=YYYY-MM-DD
```

- **Fonction** : Calcule les statistiques pour une période donnée
- **Durée** : ~5-10 secondes par jour
- **Utilisation** : Pour calculer les données d'avril 2025 à aujourd'hui

**Exemple** :
```bash
php artisan ooredoo:calculate-historical --start-date=2025-04-01 --end-date=2025-12-19
```

---

### 4️⃣ **Réimport Complet**

```bash
php artisan ooredoo:reimport-all --clean
```

- **Fonction** : Vide la table et réimporte tout (DGV officiel + calculs)
- **Durée** : ~10-15 minutes pour tout
- **Options** :
  - `--clean` : Supprime les données existantes avant réimport

---

## 📊 Structure de la Table `ooredoo_daily_stats`

| Colonne | Type | Description |
|---------|------|-------------|
| `stat_date` | DATE | Date de la statistique |
| `new_subscriptions` | INT | Nouvelles inscriptions (OOREDOO_PAYMENT_SUCCESS) |
| `unsubscriptions` | INT | Désabonnements |
| `active_subscriptions` | INT | Abonnements actifs cumulés |
| `total_clients` | INT | Nombre de clients actifs uniques |
| `total_billings` | INT | Nombre de facturations |
| `billing_rate` | DECIMAL | Taux de facturation (%) |
| `revenue_tnd` | DECIMAL | Revenus en TND |
| `offers_breakdown` | JSON | Répartition par offre |
| `data_source` | ENUM | `officiel_dgv` ou `calculé` |

---

## 🔄 Logique de Calcul

### Période **AVANT** Septembre 2025
- **Facturations** : `OOREDOO_PAYMENT_OFFLINE`
- **Revenus** : Prix depuis `abonnement_tarifs` (défaut: 0.3 TND)

### Période **APRÈS** Septembre 2025
- **Facturations** : `OOREDOO_PAYMENT_OFFLINE_INIT` avec `type=INVOICE` et `status=SUCCESS`
- **Revenus** : `invoice.price` depuis le JSON `result`

---

## ⏰ Configuration CRON

Le CRON est déjà configuré dans `app/Console/Kernel.php` :

```php
$schedule->command('ooredoo:update-daily-stats')
    ->dailyAt('02:45')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/ooredoo-stats.log'));
```

**Logs** : Consultez `storage/logs/ooredoo-stats.log` pour suivre les exécutions quotidiennes.

---

## 🛠️ Maintenance

### Vérifier les Statistiques Globales

```bash
php verify_ooredoo_data.php
```

Affiche :
- Nombre de jours par source de données
- Total facturations et revenus
- Échantillons des premiers et derniers jours

### Vérifier une Date Spécifique

```bash
php artisan tinker
```

```php
\App\Models\OoredooDailyStat::where('stat_date', '2025-12-19')->first();
```

---

## 📈 Dashboard

Les données sont automatiquement affichées dans la section **"Ooredoo/DGV"** du dashboard :

- **KPIs** : Taux de facturation, Total clients, Facturations, Revenus
- **Graphiques** : Évolution des facturations et revenus
- **Tableau** : Statistiques quotidiennes détaillées

---

## ⚠️ Points d'Attention

1. **Ne PAS écraser les données officielles DGV** (juin 2021 → mars 2025) sauf avec `--force`
2. **Le CRON tourne automatiquement** chaque nuit à 2h45
3. **Les logs sont dans** `storage/logs/ooredoo-stats.log`
4. **La durée de calcul** est d'environ 17 secondes par jour

---

## 🎯 Résumé des Données Actuelles

```
═══════════════════════════════════════════════════════════════════
📊 DONNÉES OFFICIELLES DGV
   Période: 2021-06-01 → 2025-03-31 (1,400 jours)
   Facturations: 1,146,343
   Revenus: 346,931.31 TND

📊 DONNÉES CALCULÉES
   Période: 2025-04-01 → 2025-12-19 (263 jours)
   Facturations: 164,999
   Revenus: 49,499.70 TND

🎯 TOTAL GÉNÉRAL
   Période: 2021-06-01 → 2025-12-19 (1,663 jours)
   Facturations: 1,311,342
   Revenus: 396,431.01 TND
═══════════════════════════════════════════════════════════════════
```

---

## 📞 Support

En cas de problème :
1. Vérifier les logs : `storage/logs/ooredoo-stats.log`
2. Vérifier les données : `php verify_ooredoo_data.php`
3. Relancer le CRON manuellement : `php artisan ooredoo:update-daily-stats --date=YYYY-MM-DD`

---

**Dernière mise à jour** : 19 décembre 2025
**Version** : 1.0.0

