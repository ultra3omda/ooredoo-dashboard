@echo off
echo 🚀 Redémarrage du projet Laravel...
echo.

echo 📋 Nettoyage du cache...
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
php artisan optimize:clear

echo.
echo ✅ Cache nettoyé
echo.

echo 🔄 Mise en cache de la configuration...
php artisan config:cache
php artisan route:cache

echo.
echo ✅ Configuration mise en cache
echo.

echo 🗄️ Vérification de la connexion à la base de données...
php artisan db:show

echo.
echo ✅ Redémarrage terminé!
echo.
echo Vous pouvez maintenant tester l'application.

pause








