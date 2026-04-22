# Guide de Déploiement - Synchronisation Club Privilèges

> Dernière mise à jour : 13 avril 2026 - CI/CD Production activé

## 📋 Vue d'ensemble

Ce guide décrit le déploiement de la nouvelle fonctionnalité de synchronisation automatique Club Privilèges sur le serveur Ubuntu de préproduction.

## 🚀 Fonctionnalités déployées

### 1. Synchronisation automatique Club Privilèges
- **Commande** : `php artisan cp:visit-sync`
- **Fréquence** : Toutes les heures (00:00, 01:00, 02:00, etc.)
- **URL** : `https://clubprivileges.app/sync-dashboard-data`
- **Authentification** : Double authentification (serveur + backend)

### 2. Interface web de gestion
- **URL** : `/admin/cp-sync`
- **Fonctionnalités** :
  - Visite manuelle du lien de synchronisation
  - Test de connexion
  - Historique des visites
  - Statut en temps réel

### 3. Configuration via variables d'environnement
- `CP_SYNC_SERVER_USERNAME` : Identifiant serveur
- `CP_SYNC_SERVER_PASSWORD` : Mot de passe serveur
- `CP_SYNC_USERNAME` : Identifiant backend
- `CP_SYNC_PASSWORD` : Mot de passe backend
- `CP_SYNC_ENABLED` : Activation/désactivation
- `CP_SYNC_SCHEDULE_ENABLED` : Activation du scheduler

## 🔧 Étapes de déploiement

### 1. Connexion au serveur
```bash
ssh user@preprod-server
cd /path/to/dashboard
```

### 2. Sauvegarde de la base de données
```bash
# Sauvegarde complète
mysqldump -u username -p database_name > backup_$(date +%Y%m%d_%H%M%S).sql

# Ou sauvegarde des tables critiques uniquement
mysqldump -u username -p database_name \
  client client_abonnement history promotion_pass_orders \
  promotion_pass_vendu transactions_history > backup_cp_tables_$(date +%Y%m%d_%H%M%S).sql
```

### 3. Mise à jour du code
```bash
# Récupération des dernières modifications
git fetch origin
git checkout develop
git pull origin develop

# Vérification des fichiers modifiés
git log --oneline -10
```

### 4. Installation des dépendances
```bash
# Mise à jour des dépendances Composer
composer install --no-dev --optimize-autoloader

# Mise à jour des dépendances NPM (si nécessaire)
npm install --production
npm run build
```

### 5. Configuration des variables d'environnement
```bash
# Édition du fichier .env
nano .env

# Ajout des variables Club Privilèges
CP_SYNC_SERVER_USERNAME=BiGHellO
CP_SYNC_SERVER_PASSWORD=EMQLj3EuDrjS22aNkj
CP_SYNC_USERNAME=imed@clubprivileges.app
CP_SYNC_PASSWORD=Taraji1919
CP_SYNC_ENABLED=true
CP_SYNC_SCHEDULE_ENABLED=true
```

### 6. Exécution des migrations
```bash
# Vérification des migrations en attente
php artisan migrate:status

# Exécution des migrations (si nécessaire)
php artisan migrate --force
```

### 7. Configuration du cache
```bash
# Nettoyage du cache
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Régénération du cache
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 8. Configuration du cron système
```bash
# Édition du crontab
crontab -e

# Ajout de la ligne pour exécuter le scheduler Laravel chaque minute
* * * * * cd /path/to/dashboard && php artisan schedule:run >> /dev/null 2>&1

# Vérification du crontab
crontab -l
```

### 9. Test de la configuration
```bash
# Test de la commande de synchronisation
php artisan cp:visit-sync --force

# Vérification du scheduler
php artisan schedule:list

# Test de l'interface web
curl -I http://localhost/admin/cp-sync
```

### 10. Redémarrage des services
```bash
# Redémarrage du serveur web
sudo systemctl restart nginx
# ou
sudo systemctl restart apache2

# Redémarrage de PHP-FPM (si applicable)
sudo systemctl restart php8.1-fpm
```

## 🔍 Vérification post-déploiement

### 1. Vérification des logs
```bash
# Logs Laravel
tail -f storage/logs/laravel.log

# Logs de synchronisation Club Privilèges
tail -f storage/logs/cp-sync.log

# Logs du système
tail -f /var/log/syslog | grep cron
```

### 2. Test de l'interface web
- Accéder à `https://preprod-domain.com/admin/cp-sync`
- Vérifier l'affichage de l'interface
- Tester le bouton "Tester la Connexion"
- Vérifier l'historique des visites

### 3. Test de la synchronisation automatique
```bash
# Vérification du scheduler
php artisan schedule:list

# Test manuel de la synchronisation
php artisan cp:visit-sync --force

# Vérification des données synchronisées
php artisan tinker
>>> DB::table('client')->count();
>>> DB::table('transactions_history')->count();
```

### 4. Surveillance des performances
```bash
# Vérification de l'utilisation CPU/Mémoire
htop

# Vérification de l'espace disque
df -h

# Vérification des processus PHP
ps aux | grep php
```

## 🚨 Gestion des erreurs

### Erreurs courantes et solutions

#### 1. Erreur 401 Unauthorized
```bash
# Vérifier les variables d'environnement
php artisan tinker
>>> config('cp_sync.server_username');
>>> config('cp_sync.username');

# Tester la connexion manuellement
curl -u "BiGHellO:EMQLj3EuDrjS22aNkj" https://clubprivileges.app/sync-dashboard-data
```

#### 2. Erreur de permissions
```bash
# Vérifier les permissions des fichiers
ls -la storage/logs/
chmod 755 storage/logs/
chown www-data:www-data storage/logs/
```

#### 3. Erreur de cron
```bash
# Vérifier le crontab
crontab -l

# Vérifier les logs du système
grep CRON /var/log/syslog

# Test manuel du cron
cd /path/to/dashboard && php artisan schedule:run
```

#### 4. Erreur de base de données
```bash
# Vérifier la connexion à la base
php artisan tinker
>>> DB::connection()->getPdo();

# Vérifier les tables
>>> DB::select('SHOW TABLES');
```

## 📊 Monitoring et maintenance

### 1. Surveillance quotidienne
- Vérifier les logs de synchronisation
- Contrôler l'historique des visites
- Surveiller les performances du serveur

### 2. Maintenance hebdomadaire
- Nettoyage des anciens logs
- Vérification de l'espace disque
- Test de la synchronisation manuelle

### 3. Maintenance mensuelle
- Mise à jour des dépendances
- Sauvegarde complète de la base
- Révision de la configuration

## 🔐 Sécurité

### 1. Protection des identifiants
- Variables d'environnement sécurisées
- Accès restreint au fichier .env
- Rotation régulière des mots de passe

### 2. Surveillance des accès
- Logs d'accès au serveur
- Monitoring des tentatives de connexion
- Alertes en cas d'anomalie

## 📞 Support et contacts

### En cas de problème
1. Vérifier les logs d'erreur
2. Tester la synchronisation manuelle
3. Vérifier la configuration du cron
4. Contacter l'équipe de développement

### Informations de contact
- **Développeur** : [Nom du développeur]
- **Email** : [email@domain.com]
- **Téléphone** : [Numéro de téléphone]

---

**Date de création** : 27/09/2025  
**Version** : 1.0  
**Dernière mise à jour** : 27/09/2025