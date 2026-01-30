# Script PowerShell pour redémarrer le projet Laravel
param(
    [string]$PhpPath = "php"
)

Write-Host "🚀 Redémarrage du projet Laravel..." -ForegroundColor Cyan
Write-Host ""

# Vérifier si PHP est disponible
try {
    $phpVersion = & $PhpPath -v 2>&1
    Write-Host "✅ PHP trouvé: $($phpVersion[0])" -ForegroundColor Green
} catch {
    Write-Host "❌ PHP non trouvé dans le PATH" -ForegroundColor Red
    Write-Host "💡 Essayez de spécifier le chemin complet: .\restart.ps1 -PhpPath 'C:\path\to\php.exe'" -ForegroundColor Yellow
    exit 1
}

Write-Host ""
Write-Host "📋 Nettoyage du cache..." -ForegroundColor Yellow
& $PhpPath artisan config:clear
& $PhpPath artisan cache:clear
& $PhpPath artisan route:clear
& $PhpPath artisan view:clear
& $PhpPath artisan optimize:clear

Write-Host ""
Write-Host "✅ Cache nettoyé" -ForegroundColor Green
Write-Host ""

Write-Host "🔄 Mise en cache de la configuration..." -ForegroundColor Yellow
& $PhpPath artisan config:cache
& $PhpPath artisan route:cache

Write-Host ""
Write-Host "✅ Configuration mise en cache" -ForegroundColor Green
Write-Host ""

Write-Host "🗄️ Vérification de la connexion à la base de données..." -ForegroundColor Yellow
& $PhpPath artisan db:show

Write-Host ""
Write-Host "✅ Redémarrage terminé!" -ForegroundColor Green
Write-Host ""
Write-Host "Vous pouvez maintenant tester l'application." -ForegroundColor Cyan

