# Script PowerShell pour vider le cache Laravel
param(
    [string]$PhpPath = ""
)

Write-Host "🧹 Nettoyage du cache Laravel..." -ForegroundColor Cyan
Write-Host ""

# Si le chemin PHP n'est pas fourni, essayer de le trouver
if ([string]::IsNullOrEmpty($PhpPath)) {
    # Essayer les emplacements courants
    $commonPaths = @(
        "C:\php\php.exe",
        "C:\xampp\php\php.exe",
        "C:\laragon\bin\php\php-8.3\php.exe",
        "C:\laragon\bin\php\php-8.2\php.exe",
        "C:\laragon\bin\php\php-8.1\php.exe",
        "C:\Program Files\PHP\php.exe",
        "C:\wamp64\bin\php\php8.3.0\php.exe",
        "C:\wamp64\bin\php\php8.2.0\php.exe"
    )
    
    foreach ($path in $commonPaths) {
        if (Test-Path $path) {
            $PhpPath = $path
            Write-Host "✅ PHP trouvé: $PhpPath" -ForegroundColor Green
            break
        }
    }
    
    # Si toujours pas trouvé, demander à l'utilisateur
    if ([string]::IsNullOrEmpty($PhpPath)) {
        Write-Host "❌ PHP non trouvé automatiquement" -ForegroundColor Red
        Write-Host ""
        Write-Host "💡 Veuillez spécifier le chemin de PHP:" -ForegroundColor Yellow
        Write-Host "   .\clear-cache.ps1 -PhpPath 'C:\chemin\vers\php.exe'" -ForegroundColor Yellow
        Write-Host ""
        Write-Host "Ou ajoutez PHP au PATH de Windows" -ForegroundColor Yellow
        exit 1
    }
} else {
    if (-not (Test-Path $PhpPath)) {
        Write-Host "❌ Le chemin PHP spécifié n'existe pas: $PhpPath" -ForegroundColor Red
        exit 1
    }
}

Write-Host ""
Write-Host "📋 Nettoyage du cache..." -ForegroundColor Yellow
& $PhpPath artisan config:clear
& $PhpPath artisan cache:clear
& $PhpPath artisan route:clear
& $PhpPath artisan view:clear
& $PhpPath artisan optimize:clear

Write-Host ""
Write-Host "✅ Cache nettoyé avec succès!" -ForegroundColor Green
Write-Host ""
Write-Host "Vous pouvez maintenant recharger le dashboard." -ForegroundColor Cyan








