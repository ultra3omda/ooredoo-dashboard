#!/bin/bash

# 🚀 Script de Déploiement Automatique - Ooredoo Dashboard
# Version: 1.0
# Usage: chmod +x deploy.sh && ./deploy.sh

set -e  # Arrêter le script en cas d'erreur

# Couleurs pour l'affichage
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Variables de configuration
PROJECT_DIR="/var/www/html/ooredoo-dashboard"
BACKUP_DIR="/var/backups/ooredoo-dashboard"
DB_NAME="ooredoo_dashboard"
WEB_USER="www-data"

echo -e "${BLUE}🚀 Démarrage du déploiement Ooredoo Dashboard${NC}"
echo "=========================================="

# Fonction pour afficher les messages
log_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Vérifier si on est root
if [ "$EUID" -ne 0 ]; then 
    log_error "Ce script doit être exécuté en tant que root"
    exit 1
fi

# Fonction de vérification des prérequis
check_prerequisites() {
    log_info "Vérification des prérequis..."
    
    # Vérifier PHP
    if ! command -v php &> /dev/null; then
        log_error "PHP n'est pas installé"
        exit 1
    fi
    
    PHP_VERSION=$(php -v | head -n1 | cut -d' ' -f2 | cut -d'.' -f1,2)
    if [ "$(echo "$PHP_VERSION >= 8.1" | bc)" -eq 0 ]; then
        log_error "PHP 8.1 ou supérieur requis. Version détectée: $PHP_VERSION"
        exit 1
    fi
    
    # Vérifier MySQL
    if ! command -v mysql &> /dev/null; then
        log_error "MySQL n'est pas installé"
        exit 1
    fi
    
    # Vérifier Composer
    if ! command -v composer &> /dev/null; then
        log_error "Composer n'est pas installé"
        exit 1
    fi
    
    # Vérifier Apache
    if ! systemctl is-active --quiet apache2; then
        log_error "Apache2 n'est pas en cours d'exécution"
        exit 1
    fi
    
    log_info "✅ Tous les prérequis sont satisfaits"
}

# Fonction de sauvegarde
backup_current() {
    log_info "Création de la sauvegarde..."
    
    # Créer le dossier de sauvegarde
    mkdir -p $BACKUP_DIR
    
    # Sauvegarder les fichiers
    if [ -d "$PROJECT_DIR" ]; then
        BACKUP_FILE="$BACKUP_DIR/backup_$(date +%Y%m%d_%H%M%S).tar.gz"
        tar -czf $BACKUP_FILE -C $(dirname $PROJECT_DIR) $(basename $PROJECT_DIR)
        log_info "Sauvegarde des fichiers créée: $BACKUP_FILE"
    fi
    
    # Sauvegarder la base de données
    if mysql -e "USE $DB_NAME;" 2>/dev/null; then
        DB_BACKUP="$BACKUP_DIR/db_backup_$(date +%Y%m%d_%H%M%S).sql"
        mysqldump $DB_NAME > $DB_BACKUP
        log_info "Sauvegarde de la base de données créée: $DB_BACKUP"
    fi
}

# Fonction d'installation des dépendances
install_dependencies() {
    log_info "Installation des dépendances PHP..."
    
    cd $PROJECT_DIR
    
    # Installer les dépendances Composer
    sudo -u $WEB_USER composer install --optimize-autoloader --no-dev --no-interaction
    
    log_info "✅ Dépendances installées"
}

# Fonction de configuration
configure_application() {
    log_info "Configuration de l'application..."
    
    cd $PROJECT_DIR
    
    # Copier .env si il n'existe pas
    if [ ! -f ".env" ]; then
        cp .env.example .env
        log_warning "Fichier .env créé depuis .env.example - CONFIGURER MANUELLEMENT !"
    fi
    
    # Générer la clé d'application si nécessaire
    if ! grep -q "APP_KEY=base64:" .env; then
        sudo -u $WEB_USER php artisan key:generate --no-interaction
        log_info "Clé d'application générée"
    fi
    
    # Optimiser pour la production
    sudo -u $WEB_USER php artisan config:cache
    sudo -u $WEB_USER php artisan route:cache
    sudo -u $WEB_USER php artisan view:cache
    
    log_info "✅ Application configurée"
}

# Fonction de migration de base de données
migrate_database() {
    log_info "Migration de la base de données..."
    
    cd $PROJECT_DIR
    
    # Exécuter les migrations
    sudo -u $WEB_USER php artisan migrate --force --no-interaction
    
    # Exécuter les seeders si c'est une nouvelle installation
    if ! mysql -e "SELECT COUNT(*) FROM $DB_NAME.users;" 2>/dev/null | grep -q "1"; then
        log_info "Nouvelle installation détectée - Exécution des seeders..."
        sudo -u $WEB_USER php artisan db:seed --class=SuperAdminSeeder --force
        sudo -u $WEB_USER php artisan db:seed --class=RolesSeeder --force
    fi
    
    log_info "✅ Base de données migrée"
}

# Fonction de configuration des permissions
set_permissions() {
    log_info "Configuration des permissions..."
    
    # Permissions générales
    chown -R $WEB_USER:$WEB_USER $PROJECT_DIR
    chmod -R 755 $PROJECT_DIR
    
    # Permissions spéciales pour storage et cache
    chmod -R 775 $PROJECT_DIR/storage
    chmod -R 775 $PROJECT_DIR/bootstrap/cache
    
    log_info "✅ Permissions configurées"
}

# Fonction de test de l'installation
test_installation() {
    log_info "Test de l'installation..."
    
    cd $PROJECT_DIR
    
    # Test de la connexion à la base de données
    if sudo -u $WEB_USER php artisan tinker --execute="dd(DB::connection()->getPdo());" 2>/dev/null; then
        log_info "✅ Connexion à la base de données OK"
    else
        log_error "❌ Problème de connexion à la base de données"
        return 1
    fi
    
    # Test des routes
    if sudo -u $WEB_USER php artisan route:list > /dev/null; then
        log_info "✅ Routes chargées correctement"
    else
        log_error "❌ Problème avec les routes"
        return 1
    fi
    
    log_info "✅ Installation testée avec succès"
}

# Fonction de redémarrage des services
restart_services() {
    log_info "Redémarrage des services..."
    
    systemctl reload apache2
    
    log_info "✅ Services redémarrés"
}

# Fonction principale
main() {
    echo "Démarrage du déploiement..."
    
    check_prerequisites
    backup_current
    install_dependencies
    configure_application
    migrate_database
    set_permissions
    test_installation
    restart_services
    
    echo ""
    echo -e "${GREEN}🎉 DÉPLOIEMENT TERMINÉ AVEC SUCCÈS !${NC}"
    echo "=========================================="
    echo -e "${BLUE}Informations importantes :${NC}"
    echo "• Application installée dans: $PROJECT_DIR"
    echo "• Sauvegardes dans: $BACKUP_DIR"
    echo "• Logs Laravel: $PROJECT_DIR/storage/logs/laravel.log"
    echo ""
    echo -e "${YELLOW}Actions à effectuer manuellement :${NC}"
    echo "1. Configurer le fichier .env avec vos paramètres"
    echo "2. Configurer le virtual host Apache/Nginx"
    echo "3. Configurer SSL si nécessaire"
    echo "4. Tester l'accès depuis le navigateur"
    echo ""
    echo -e "${GREEN}Compte Super Admin par défaut :${NC}"
    echo "• Email: superadmin@clubprivileges.app"
    echo "• Mot de passe: SuperAdmin2024!"
    echo ""
    echo -e "${RED}⚠️  CHANGER LE MOT DE PASSE PAR DÉFAUT IMMÉDIATEMENT !${NC}"
}

# Gestion des arguments
case "${1:-deploy}" in
    "deploy")
        main
        ;;
    "backup")
        backup_current
        ;;
    "test")
        test_installation
        ;;
    "permissions")
        set_permissions
        ;;
    "help")
        echo "Usage: $0 [deploy|backup|test|permissions|help]"
        echo "  deploy      - Déploiement complet (défaut)"
        echo "  backup      - Sauvegarde uniquement"
        echo "  test        - Test de l'installation"
        echo "  permissions - Configuration des permissions"
        echo "  help        - Afficher cette aide"
        ;;
    *)
        log_error "Option inconnue: $1"
        echo "Utilisez '$0 help' pour voir les options disponibles"
        exit 1
        ;;
esac
