#!/bin/bash
# Script pour nettoyer les anciens logs et optimiser l'espace disque

echo "🧹 Nettoyage des logs..."

# Supprimer les logs de plus de 7 jours
find storage/logs -name "*.log" -type f -mtime +7 -delete

# Compresser les logs de plus de 3 jours
find storage/logs -name "*.log" -type f -mtime +3 -exec gzip {} \;

# Nettoyer le cache Laravel
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

echo "✅ Nettoyage terminé"








