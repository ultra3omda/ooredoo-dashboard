# 📦 Package de Déploiement - Ooredoo Dashboard

## 🎯 Contenu du Package

Ce package contient tout le nécessaire pour déployer le **Ooredoo Dashboard** sur votre serveur de production.

### 📋 Fichiers Inclus

```
📁 ooredoo-dashboard/
├── 📂 app/                     # Code source Laravel
├── 📂 bootstrap/               # Fichiers de démarrage
├── 📂 config/                  # Configuration Laravel
├── 📂 database/                # Migrations et seeders
│   ├── 📂 migrations/          # Scripts de création de tables
│   └── 📂 seeders/             # Données initiales
├── 📂 public/                  # Point d'entrée web
├── 📂 resources/               # Vues et assets
│   └── 📂 views/               # Templates Blade
├── 📂 routes/                  # Définition des routes
├── 📂 storage/                 # Stockage et logs
├── 📄 .env.example             # Configuration d'exemple
├── 📄 composer.json            # Dépendances PHP
├── 📄 DEPLOYMENT_GUIDE.md      # Guide détaillé de déploiement
├── 📄 PRODUCTION_CONFIG.md     # Configuration de production
├── 📄 deploy.sh               # Script automatique de déploiement
├── 📄 env.production.example  # Configuration production
└── 📄 README_DEPLOYMENT.md    # Ce fichier
```

## 🚀 Déploiement Rapide

### Option 1: Déploiement Automatique (Recommandé)
```bash
# 1. Extraire l'archive
sudo tar -xzf ooredoo-dashboard.tar.gz -C /var/www/html/

# 2. Aller dans le dossier
cd /var/www/html/ooredoo-dashboard/

# 3. Rendre le script exécutable
sudo chmod +x deploy.sh

# 4. Lancer le déploiement
sudo ./deploy.sh
```

### Option 2: Déploiement Manuel
Suivre le guide détaillé dans `DEPLOYMENT_GUIDE.md`

## ⚡ Configuration Rapide

### 1. Configuration Base de Données
```sql
-- Créer la base de données
CREATE DATABASE ooredoo_dashboard CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Créer l'utilisateur
CREATE USER 'ooredoo_user'@'localhost' IDENTIFIED BY 'VotreMotDePasseSecurise123!';
GRANT ALL PRIVILEGES ON ooredoo_dashboard.* TO 'ooredoo_user'@'localhost';
FLUSH PRIVILEGES;
```

### 2. Configuration .env
```bash
# Copier et éditer la configuration
cp env.production.example .env
nano .env

# Points importants à modifier :
# - DB_PASSWORD : Mot de passe de la base de données
# - APP_URL : URL de votre site
# - APP_KEY : sera généré automatiquement
```

#### Variables de synchronisation (Club Privilèges)
Ajoutez ces variables dans votre fichier `.env` pour activer la synchronisation incrémentale :

```env
# Sync API (pull incrémental)
SYNC_API_URL=https://clubprivileges.app/api/get-pending-sync-data
SYNC_API_TOKEN=remplacez_par_votre_token
SYNC_BATCH_SIZE=5000
SYNC_HTTP_TIMEOUT=30
SYNC_RETRY_TIMES=3
SYNC_RETRY_SLEEP_MS=1000
```

### 3. Virtual Host Apache
```apache
<VirtualHost *:80>
    ServerName dashboard.ooredoo.com
    DocumentRoot /var/www/html/ooredoo-dashboard/public
    
    <Directory /var/www/html/ooredoo-dashboard/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

## 🔐 Comptes par Défaut

### Super Administrateur
- **Email**: `superadmin@clubprivileges.app`
- **Mot de passe**: `SuperAdmin2024!`
- **Accès**: Vue globale, gestion complète

### ⚠️ Important
**CHANGER IMMÉDIATEMENT** le mot de passe par défaut après le premier login !

## ✅ Vérification de l'Installation

### Tests à Effectuer
1. **Accès au site** : `https://votre-domaine.com`
2. **Connexion Super Admin** : Tester le login
3. **Dashboard** : Vérifier l'affichage des données
4. **Fonctionnalités** :
   - ✅ Sélection d'opérateurs
   - ✅ Filtres de dates
   - ✅ Graphiques interactifs
   - ✅ Gestion des utilisateurs
   - ✅ Système d'invitations

### Commandes de Diagnostic
```bash
# Vérifier l'état de l'application
php artisan about

# Vérifier les routes
php artisan route:list

# Tester la connexion DB
php artisan tinker
>>> DB::connection()->getPdo();

# Voir les logs
tail -f storage/logs/laravel.log
```

### Synchronisation des données
```bash
# Lancer une synchronisation manuelle (toutes les tables dans l'ordre)
php artisan sync:pull

# Synchroniser une seule table
php artisan sync:pull partner

# Scheduler (configuré par défaut)
# - everyFifteenMinutes: incrémental continu
# - dailyAt 02:00: rattrapage quotidien
```

## 📊 Fonctionnalités Principales

### 🎛️ Dashboard Multi-Opérateurs
- **Vue Globale** pour Super Admin
- **Vue Filtrée** par opérateur pour Admin/Collaborateur
- **Comparaison de périodes** avec dates flexibles
- **KPIs en temps réel** : utilisateurs, transactions, revenus, rétention

### 👥 Gestion des Utilisateurs
- **3 Rôles** : Super Admin, Admin, Collaborateur
- **Système d'invitations** par email avec OTP
- **Permissions granulaires** par opérateur

### 📈 Analytics Avancés
- **Merchants Analysis** : performance détaillée des marchands
- **Transaction Insights** : analyse des patterns de transaction
- **Retention Tracking** : suivi de la rétention utilisateurs
- **Export de données** en CSV

### 🎨 Interface Moderne
- **Design responsive** (Desktop/Tablette/Mobile)
- **Charte graphique Ooredoo** (Rouge #E30613)
- **Navigation intuitive** avec onglets
- **Graphiques interactifs** (Chart.js)

## 🔧 Maintenance

### Commandes Utiles
```bash
# Nettoyer les caches
php artisan cache:clear
php artisan config:clear
php artisan view:clear

# Optimiser pour la production
php artisan optimize

# Sauvegarder la DB
mysqldump ooredoo_dashboard > backup_$(date +%Y%m%d).sql
```

### Cron de production
Ajoutez ces entrées cron pour garantir l'exécution du scheduler Laravel :

```cron
* * * * * cd /var/www/html/ooredoo-dashboard && php artisan schedule:run >> /dev/null 2>&1
```

### Logs à Surveiller
- **Laravel** : `storage/logs/laravel.log`
- **Apache** : `/var/log/apache2/error.log`
- **MySQL** : `/var/log/mysql/error.log`

## 📞 Support

### En cas de Problème
1. **Consulter les logs** : `storage/logs/laravel.log`
2. **Vérifier la configuration** : `.env` et permissions
3. **Tester la connectivité** : base de données et email

### Informations Système
- **Framework** : Laravel 10.x
- **PHP** : 8.1+ requis
- **Base de données** : MySQL 8.0+
- **Serveur web** : Apache 2.4+ ou Nginx

### Contact Technique
Pour toute assistance technique, contacter l'équipe de développement avec :
- **Version PHP** : `php -v`
- **Logs d'erreur** : dernières lignes de `laravel.log`
- **Configuration** : fichier `.env` (sans mots de passe)

---

## 🎉 Félicitations !

Une fois le déploiement terminé, vous disposerez d'un **dashboard professionnel** avec :
- 📊 **Analytics en temps réel**
- 👥 **Gestion multi-utilisateurs**
- 🔐 **Sécurité renforcée** 
- 🎨 **Interface moderne et intuitive**

**Bon déploiement !** 🚀
