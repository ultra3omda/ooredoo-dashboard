# 🔧 Configuration Production - Ooredoo Dashboard

## 📋 Checklist de Configuration

### ✅ Variables d'Environnement (.env)

```env
# === CONFIGURATION APPLICATION ===
APP_NAME="Ooredoo Dashboard"
APP_ENV=production
APP_KEY=base64:SERA_GENERE_AUTOMATIQUEMENT
APP_DEBUG=false
APP_URL=https://dashboard.ooredoo.com

# === CONFIGURATION BASE DE DONNÉES ===
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ooredoo_dashboard
DB_USERNAME=ooredoo_user
DB_PASSWORD=MOT_DE_PASSE_SECURISE

# === CONFIGURATION EMAIL ===
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=465
MAIL_ENCRYPTION=ssl
MAIL_USERNAME=assistant@clubprivileges.app
MAIL_PASSWORD="nltk qbof szsp qopq"
MAIL_FROM_ADDRESS=assistant@clubprivileges.app
MAIL_CONTACT_US=contact@clubprivileges.app
MAIL_NEW_PARTNER=team@clubprivileges.app
MAIL_FROM_NAME="Club Privilèges"

# === CONFIGURATION SESSION ===
SESSION_DRIVER=file
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

# === CONFIGURATION CACHE ===
CACHE_DRIVER=file
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync

# === CONFIGURATION LOGGING ===
LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=error

# === CONFIGURATION BROADCAST ===
BROADCAST_DRIVER=log
```

## 🗄️ Structure de Base de Données

### Tables Principales
- `users` - Utilisateurs du système
- `roles` - Rôles (super_admin, admin, collaborator)
- `user_operators` - Association utilisateurs-opérateurs
- `invitations` - Invitations en attente
- Toutes les tables de données existantes (client, history, partner, etc.)

### Comptes par Défaut

#### Super Administrateur
- **Email**: `superadmin@clubprivileges.app`
- **Mot de passe**: `SuperAdmin2024!`
- **Rôle**: Super Admin
- **Permissions**: Accès global, gestion complète

#### Comptes de Test (Optionnels)
- **Admin Timwe**: `admin.timwe@clubprivileges.app` / `AdminTimwe2024!`
- **Admin Carte Cadeaux**: `admin.carte@clubprivileges.app` / `AdminCarte2024!`

## 🔐 Sécurité en Production

### Configuration PHP (php.ini)
```ini
# Sécurité
expose_php = Off
display_errors = Off
display_startup_errors = Off
log_errors = On
error_log = /var/log/php_errors.log

# Fonctions dangereuses désactivées
disable_functions = exec,passthru,shell_exec,system,proc_open,popen,curl_exec,curl_multi_exec,parse_ini_file,show_source

# Limites de ressources
memory_limit = 256M
max_execution_time = 30
max_input_time = 60
upload_max_filesize = 10M
post_max_size = 10M
```

### Configuration Apache (.htaccess)
```apache
# Déjà inclus dans public/.htaccess
# Sécurité supplémentaire
<Files ".env">
    Order allow,deny
    Deny from all
</Files>

<Files "composer.*">
    Order allow,deny
    Deny from all
</Files>
```

### Pare-feu (UFW)
```bash
# Ouvrir seulement les ports nécessaires
sudo ufw allow 22/tcp    # SSH
sudo ufw allow 80/tcp    # HTTP
sudo ufw allow 443/tcp   # HTTPS
sudo ufw enable
```

## 📊 Monitoring et Logs

### Logs à Surveiller
```bash
# Logs Laravel
tail -f /var/www/html/ooredoo-dashboard/storage/logs/laravel.log

# Logs Apache
tail -f /var/log/apache2/ooredoo_error.log
tail -f /var/log/apache2/ooredoo_access.log

# Logs MySQL
tail -f /var/log/mysql/error.log
```

### Métriques Important à Surveiller
- Utilisation CPU/RAM
- Espace disque
- Connexions simultanées
- Temps de réponse
- Erreurs 5xx

## 🔄 Maintenance

### Tâches Quotidiennes
```bash
# Nettoyer les logs (optionnel)
php artisan log:clear

# Vérifier l'état de l'application
php artisan about

# Optimiser si nécessaire
php artisan optimize
```

### Tâches Hebdomadaires
```bash
# Sauvegarder la base de données
mysqldump -u root -p ooredoo_dashboard > backup_$(date +%Y%m%d).sql

# Nettoyer les anciens logs
find storage/logs -name "*.log" -mtime +30 -delete
```

### Tâches Mensuelles
- Mettre à jour les certificats SSL
- Vérifier les mises à jour de sécurité
- Analyser les métriques de performance

## 🚨 Procédures d'Urgence

### En cas de Problème

1. **Site inaccessible**:
   ```bash
   sudo systemctl restart apache2
   sudo systemctl restart mysql
   ```

2. **Erreurs de base de données**:
   ```bash
   # Vérifier le statut MySQL
   sudo systemctl status mysql
   
   # Restaurer depuis la sauvegarde
   mysql -u root -p ooredoo_dashboard < backup_latest.sql
   ```

3. **Erreurs de permissions**:
   ```bash
   sudo chown -R www-data:www-data /var/www/html/ooredoo-dashboard
   sudo chmod -R 755 /var/www/html/ooredoo-dashboard
   sudo chmod -R 775 /var/www/html/ooredoo-dashboard/storage
   ```

### Contacts d'Urgence
- **Équipe Technique**: [Votre contact]
- **Administrateur Base de Données**: [Contact DBA]
- **Support Serveur**: [Contact infrastructure]

## 📈 Optimisations Recommandées

### Cache Opcode PHP
```bash
# Installer OPcache
sudo apt install php8.1-opcache

# Configuration dans php.ini
opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=4000
opcache.revalidate_freq=60
```

### Compression Gzip
```apache
# Dans .htaccess ou configuration Apache
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/plain
    AddOutputFilterByType DEFLATE text/html
    AddOutputFilterByType DEFLATE text/xml
    AddOutputFilterByType DEFLATE text/css
    AddOutputFilterByType DEFLATE application/xml
    AddOutputFilterByType DEFLATE application/xhtml+xml
    AddOutputFilterByType DEFLATE application/rss+xml
    AddOutputFilterByType DEFLATE application/javascript
    AddOutputFilterByType DEFLATE application/x-javascript
</IfModule>
```

### Configuration MySQL Optimisée
```sql
# Dans my.cnf
[mysqld]
innodb_buffer_pool_size = 1G
innodb_log_file_size = 256M
query_cache_size = 256M
max_connections = 200
```

## 🎯 Points d'Attention Spécifiques

### 1. Données Sensibles
- Tous les mots de passe sont hashés
- Les sessions sont sécurisées
- Les emails d'invitation expirent après 48h

### 2. Performance
- Les graphiques utilisent Chart.js (côté client)
- Les requêtes DB sont optimisées avec des index
- Le cache Laravel est activé

### 3. Compatibilité
- Testé sur PHP 8.1+
- Compatible MySQL 8.0+
- Responsive design pour mobiles/tablettes

---
**Important**: Changer tous les mots de passe par défaut avant la mise en production !
