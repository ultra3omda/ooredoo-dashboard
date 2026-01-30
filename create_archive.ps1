# Script PowerShell pour créer l'archive de déploiement Ooredoo Dashboard
# Utilisation: .\create_archive.ps1

Write-Host "🚀 Création de l'archive de déploiement Ooredoo Dashboard..." -ForegroundColor Green

# Définir les chemins
$sourceDir = "."
$archiveName = "..\ooredoo-dashboard-production.zip"

# Fichiers et dossiers à exclure
$excludePatterns = @(
    "*.env",
    "storage\logs\*",
    "vendor\*",
    "node_modules\*", 
    "bootstrap\cache\*",
    ".git\*",
    "*.log",
    "*.tmp"
)

Write-Host "📁 Collecte des fichiers à archiver..." -ForegroundColor Yellow

# Collecter tous les fichiers en excluant les patterns indésirables
$filesToArchive = Get-ChildItem -Path $sourceDir -Recurse -File | Where-Object {
    $file = $_
    $shouldExclude = $false
    
    foreach ($pattern in $excludePatterns) {
        if ($file.FullName -like "*$pattern*") {
            $shouldExclude = $true
            break
        }
    }
    
    return -not $shouldExclude
}

Write-Host "📦 Création de l'archive..." -ForegroundColor Yellow
Write-Host "   Fichiers à archiver: $($filesToArchive.Count)" -ForegroundColor Gray

# Supprimer l'archive existante si elle existe
if (Test-Path $archiveName) {
    Remove-Item $archiveName -Force
    Write-Host "   Archive existante supprimée" -ForegroundColor Gray
}

# Créer l'archive
try {
    # Créer une archive temporaire vide
    Add-Type -AssemblyName System.IO.Compression.FileSystem
    
    $zipFile = [System.IO.Compression.ZipFile]::Open($archiveName, [System.IO.Compression.ZipArchiveMode]::Create)
    
    foreach ($file in $filesToArchive) {
        $relativePath = $file.FullName.Substring($sourceDir.Length + 1)
        $relativePath = $relativePath.Replace('\', '/')
        
        # Ajouter le préfixe du dossier
        $entryName = "ooredoo-dashboard/$relativePath"
        
        try {
            $entry = $zipFile.CreateEntry($entryName)
            $entryStream = $entry.Open()
            $fileStream = [System.IO.File]::OpenRead($file.FullName)
            $fileStream.CopyTo($entryStream)
            $fileStream.Close()
            $entryStream.Close()
        }
        catch {
            Write-Warning "⚠️ Impossible d'ajouter: $($file.FullName) - $($_.Exception.Message)"
        }
    }
    
    $zipFile.Dispose()
    
    Write-Host "✅ Archive créée avec succès: $archiveName" -ForegroundColor Green
    
    # Afficher la taille de l'archive
    $archiveInfo = Get-Item $archiveName
    $sizeMB = [math]::Round($archiveInfo.Length / 1MB, 2)
    Write-Host "📏 Taille de l'archive: $sizeMB MB" -ForegroundColor Green
    
}
catch {
    Write-Error "❌ Erreur lors de la création de l'archive: $($_.Exception.Message)"
    exit 1
}

Write-Host ""
Write-Host "🎉 Archive de déploiement prête!" -ForegroundColor Green
Write-Host "📄 Fichier: $archiveName" -ForegroundColor Cyan
Write-Host "📧 Vous pouvez maintenant envoyer cette archive à l'administrateur système." -ForegroundColor Cyan
Write-Host ""
Write-Host "📋 Documents à inclure dans l'envoi:" -ForegroundColor Yellow
Write-Host "   • ooredoo-dashboard-production.zip" -ForegroundColor Gray
Write-Host "   • DEPLOYMENT_GUIDE.md" -ForegroundColor Gray  
Write-Host "   • PRODUCTION_CONFIG.md" -ForegroundColor Gray
Write-Host "   • README_DEPLOYMENT.md" -ForegroundColor Gray
Write-Host "   • ADMIN_CHECKLIST.md" -ForegroundColor Gray
Write-Host "   • INSTRUCTIONS_ENVOI.md" -ForegroundColor Gray
Write-Host ""
