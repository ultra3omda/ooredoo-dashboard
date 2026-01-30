# 🔄 Guide de Redémarrage - Ooredoo Dashboard

## Après modification du fichier .env

### Option 1 : Script PowerShell (Recommandé)

Exécutez dans PowerShell :
```powershell
.\restart.ps1
```

### Option 2 : Script Batch

Double-cliquez sur `restart.bat` ou exécutez :
```cmd
restart.bat
```

### Option 3 : Commandes manuelles

Si PHP n'est pas dans votre PATH, utilisez le chemin complet vers PHP :

```powershell
# Nettoyer le cache
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
php artisan optimize:clear

# Mettre en cache la nouvelle configuration
php artisan config:cache
php artisan route:cache

# Vérifier la connexion à la base de données
php artisan db:show
```

## Vérification

1. **Vérifier la connexion DB** :
   ```powershell
   php artisan db:show
   ```

2. **Tester une route** :
   Ouvrez votre navigateur et accédez à l'application

3. **Vérifier les logs** :
   ```powershell
   Get-Content storage\logs\laravel-$(Get-Date -Format 'yyyy-MM-dd').log -Tail 20
   ```

## Si vous utilisez un serveur de développement

Si vous utilisez `php artisan serve`, redémarrez-le :
```powershell
# Arrêter (Ctrl+C)
# Puis redémarrer
php artisan serve
```

## Notes

- Après modification de `.env`, **toujours** exécuter `php artisan config:clear` puis `php artisan config:cache`
- Le cache de configuration doit être régénéré pour que les changements soient pris en compte
- Les erreurs de connexion DB apparaîtront dans les logs si la configuration est incorrecte








