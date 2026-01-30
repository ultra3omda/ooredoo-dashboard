# Script PowerShell pour creer l'archive de deploiement Ooredoo Dashboard

Write-Host "Creation de l'archive de deploiement Ooredoo Dashboard..." -ForegroundColor Green

# Definir les chemins
$sourceDir = "."
$archiveName = "..\ooredoo-dashboard-production.zip"

Write-Host "Collecte des fichiers a archiver..." -ForegroundColor Yellow

# Supprimer l'archive existante si elle existe
if (Test-Path $archiveName) {
    Remove-Item $archiveName -Force
    Write-Host "Archive existante supprimee" -ForegroundColor Gray
}

# Creer l'archive avec une methode simple
try {
    # Copier le projet dans un dossier temporaire
    $tempDir = "temp_archive"
    if (Test-Path $tempDir) {
        Remove-Item $tempDir -Recurse -Force
    }
    
    # Creer le dossier temporaire
    New-Item -ItemType Directory -Path $tempDir -Force | Out-Null
    
    # Copier tous les fichiers sauf les exclusions
    Write-Host "Copie des fichiers..." -ForegroundColor Yellow
    
    # Copier les dossiers principaux
    $mainFolders = @("app", "bootstrap", "config", "database", "public", "resources", "routes", "storage")
    foreach ($folder in $mainFolders) {
        if (Test-Path $folder) {
            Copy-Item $folder -Destination "$tempDir\$folder" -Recurse -Force
        }
    }
    
    # Copier les fichiers principaux
    $mainFiles = @("artisan", "composer.json", "composer.lock", ".env.example", "*.md", "*.sh", "*.php")
    foreach ($filePattern in $mainFiles) {
        Get-ChildItem -Path $filePattern -ErrorAction SilentlyContinue | Copy-Item -Destination $tempDir -Force
    }
    
    # Nettoyer les fichiers non desires
    if (Test-Path "$tempDir\storage\logs") {
        Get-ChildItem "$tempDir\storage\logs\*" | Remove-Item -Force -ErrorAction SilentlyContinue
    }
    
    # Creer l'archive
    Write-Host "Creation de l'archive ZIP..." -ForegroundColor Yellow
    Compress-Archive -Path $tempDir -DestinationPath $archiveName -Force
    
    # Nettoyer le dossier temporaire
    Remove-Item $tempDir -Recurse -Force
    
    Write-Host "Archive creee avec succes: $archiveName" -ForegroundColor Green
    
    # Afficher la taille
    $archiveInfo = Get-Item $archiveName
    $sizeMB = [math]::Round($archiveInfo.Length / 1MB, 2)
    Write-Host "Taille de l'archive: $sizeMB MB" -ForegroundColor Green
    
}
catch {
    Write-Error "Erreur lors de la creation de l'archive: $($_.Exception.Message)"
    exit 1
}

Write-Host ""
Write-Host "Archive de deploiement prete!" -ForegroundColor Green
Write-Host "Fichier: $archiveName" -ForegroundColor Cyan
Write-Host ""
