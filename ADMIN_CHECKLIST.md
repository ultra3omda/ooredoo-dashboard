# ✅ Checklist Administrateur Système - Ooredoo Dashboard

## 📋 Checklist Pré-Déploiement

### 🖥️ Préparation Serveur

- [ ] **Serveur configuré** avec les spécifications minimales
  - [ ] Ubuntu 20.04+ / CentOS 8+ / Debian 11+
  - [ ] 4GB RAM minimum (8GB recommandé)
  - [ ] 20GB espace disque minimum
  - [ ] Connexion Internet stable

- [ ] **PHP 8.1+ installé** avec extensions requises
  ```bash
  php -v  # Vérifier version
  php -m | grep -E "(mysql|mbstring|xml|curl|zip|gd|json|tokenizer|fileinfo)"
  ```

- [ ] **MySQL 8.0+ configuré**
  - [ ] Service MySQL démarré
  - [ ] Compte root accessible
  - [ ] Port 3306 ouvert (ou configuré)

- [ ] **Apache/Nginx installé et configuré**
  - [ ] mod_rewrite activé (Apache)
  - [ ] Service web démarré
  - [ ] Ports 80/443 ouverts

- [ ] **Composer installé**
  ```bash
  composer --version
  ```

## 📦 Déploiement

### Étape 1: Installation
- [ ] **Archive extraite** dans `/var/www/html/ooredoo-dashboard/`
- [ ] **Permissions vérifiées**
  ```bash
  ls -la /var/www/html/ooredoo-dashboard/
  ```

### Étape 2: Configuration Base de Données
- [ ] **Base de données créée**
  ```sql
  CREATE DATABASE ooredoo_dashboard CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  ```

- [ ] **Utilisateur créé** avec permissions
  ```sql
  CREATE USER 'ooredoo_user'@'localhost' IDENTIFIED BY 'MotDePasseSecurise123!';
  GRANT ALL PRIVILEGES ON ooredoo_dashboard.* TO 'ooredoo_user'@'localhost';
  FLUSH PRIVILEGES;
  ```

- [ ] **Connexion testée**
  ```bash
  mysql -u ooredoo_user -p ooredoo_dashboard
  ```

### Étape 3: Configuration Application
- [ ] **Fichier .env configuré**
  - [ ] `cp env.production.example .env`
  - [ ] Variables DB modifiées (host, user, password, database)
  - [ ] APP_URL configuré avec le bon domaine
  - [ ] APP_ENV=production
  - [ ] APP_DEBUG=false

- [ ] **Dépendances installées**
  ```bash
  cd /var/www/html/ooredoo-dashboard
  composer install --optimize-autoloader --no-dev
  ```

- [ ] **Clé application générée**
  ```bash
  php artisan key:generate
  ```

### Étape 4: Base de Données
- [ ] **Migrations exécutées**
  ```bash
  php artisan migrate --force
  ```

- [ ] **Seeders exécutés**
  ```bash
  php artisan db:seed --class=SuperAdminSeeder --force
  php artisan db:seed --class=RolesSeeder --force
  ```

- [ ] **Tables créées vérifiées**
  ```sql
  SHOW TABLES IN ooredoo_dashboard;
  ```

### Étape 5: Permissions
- [ ] **Propriétaire configuré**
  ```bash
  chown -R www-data:www-data /var/www/html/ooredoo-dashboard
  ```

- [ ] **Permissions réglées**
  ```bash
  chmod -R 755 /var/www/html/ooredoo-dashboard
  chmod -R 775 /var/www/html/ooredoo-dashboard/storage
  chmod -R 775 /var/www/html/ooredoo-dashboard/bootstrap/cache
  ```

### Étape 6: Configuration Serveur Web

#### Apache
- [ ] **Virtual Host créé**
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

- [ ] **Site activé**
  ```bash
  a2ensite dashboard.ooredoo.com
  systemctl reload apache2
  ```

#### Nginx (Alternative)
- [ ] **Configuration créée** dans `/etc/nginx/sites-available/dashboard.ooredoo.com`
- [ ] **Lien symbolique créé** dans `/etc/nginx/sites-enabled/`
- [ ] **Nginx redémarré**

### Étape 7: SSL/HTTPS (Recommandé)
- [ ] **Certificat SSL installé**
  ```bash
  # Option 1: Let's Encrypt
  certbot --apache -d dashboard.ooredoo.com
  
  # Option 2: Certificat existant
  # Configurer dans le virtual host
  ```

- [ ] **Redirection HTTP vers HTTPS configurée**

## 🧪 Tests de Validation

### Tests Fonctionnels
- [ ] **Page d'accueil accessible**
  - URL: `https://dashboard.ooredoo.com`
  - Redirection vers login si non connecté ✓

- [ ] **Connexion Super Admin**
  - Email: `superadmin@clubprivileges.app`
  - Mot de passe: `SuperAdmin2024!`
  - Accès au dashboard ✓

- [ ] **Dashboard fonctionnel**
  - [ ] KPIs s'affichent
  - [ ] Graphiques se chargent
  - [ ] Sélecteur d'opérateur fonctionne
  - [ ] Filtres de dates fonctionnent

- [ ] **Gestion utilisateurs**
  - [ ] Liste des utilisateurs accessible
  - [ ] Création d'utilisateur fonctionne
  - [ ] Système d'invitation fonctionne

### Tests Techniques
- [ ] **Base de données connectée**
  ```bash
  php artisan tinker
  >>> DB::connection()->getPdo();
  ```

- [ ] **Routes chargées**
  ```bash
  php artisan route:list
  ```

- [ ] **Emails configurés**
  ```bash
  php artisan tinker
  >>> Mail::raw('Test', function($msg) { $msg->to('test@test.com'); });
  ```

- [ ] **Logs fonctionnels**
  ```bash
  tail -f storage/logs/laravel.log
  ```

### Tests Performance
- [ ] **Temps de réponse acceptable** (< 3 secondes)
- [ ] **Usage mémoire normal** 
- [ ] **Pas d'erreurs 5xx**

## 🔐 Sécurité

### Configuration Sécurisée
- [ ] **APP_DEBUG=false** en production
- [ ] **Fichiers sensibles protégés**
  - .env non accessible via web
  - Dossier storage protégé

- [ ] **Pare-feu configuré**
  ```bash
  ufw status
  # Ports 22, 80, 443 ouverts uniquement
  ```

- [ ] **Mots de passe par défaut changés**
  - [ ] Super Admin password modifié
  - [ ] Database user password sécurisé

### Monitoring
- [ ] **Logs configurés**
  - Apache/Nginx logs
  - Laravel logs
  - MySQL logs

- [ ] **Surveillance activée**
  - Espace disque
  - Usage CPU/RAM
  - Certificats SSL

## 📞 Support et Maintenance

### Informations de Contact
- [ ] **Contact développement** configuré
- [ ] **Accès administrateur** documenté
- [ ] **Procédures de sauvegarde** établies

### Documentation Remise
- [ ] **DEPLOYMENT_GUIDE.md** lu et compris
- [ ] **PRODUCTION_CONFIG.md** appliqué
- [ ] **Mots de passe** stockés en sécurité
- [ ] **URLs d'accès** documentées

## 🚨 Procédures d'Urgence

### En cas de Problème
1. **Site inaccessible**
   ```bash
   systemctl status apache2
   systemctl status mysql
   ```

2. **Erreurs applicatives**
   ```bash
   tail -100 /var/www/html/ooredoo-dashboard/storage/logs/laravel.log
   ```

3. **Base de données**
   ```bash
   systemctl status mysql
   mysql -u root -p -e "SHOW PROCESSLIST;"
   ```

### Contacts d'Urgence
- **Équipe Technique**: [À remplir]
- **Administrateur BDD**: [À remplir]  
- **Support Infrastructure**: [À remplir]

## ✅ Validation Finale

### Signature Déploiement
- [ ] **Tous les tests passés**
- [ ] **Documentation remise**
- [ ] **Équipe formée**
- [ ] **Monitoring activé**

**Administrateur**: ________________________  
**Date**: _______________  
**Signature**: ________________________

### Livraison Acceptée
- [ ] **Client formé** à l'utilisation
- [ ] **Accès administrateur** transféré
- [ ] **Support** transitionné
- [ ] **Garantie** activée

**Responsable Client**: ________________________  
**Date**: _______________  
**Signature**: ________________________

---

## 📝 Notes Supplémentaires

```
Espace pour notes spécifiques au déploiement:

_________________________________________________
_________________________________________________
_________________________________________________
_________________________________________________
```

**Version Checklist**: 1.0  
**Date Création**: $(date '+%Y-%m-%d')  
**Environnement**: Production