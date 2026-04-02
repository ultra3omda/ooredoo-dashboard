# Script de relance des trimestres échoués
# Chaque trimestre sera traité séquentiellement

$trimestres = @(
    @{start="2022-07-01"; end="2022-09-30"; nom="Q3 2022"},
    @{start="2022-10-01"; end="2022-12-31"; nom="Q4 2022"},
    @{start="2023-01-01"; end="2023-03-31"; nom="Q1 2023"},
    @{start="2023-04-01"; end="2023-06-30"; nom="Q2 2023"},
    @{start="2023-07-01"; end="2023-09-30"; nom="Q3 2023"},
    @{start="2023-10-01"; end="2023-12-31"; nom="Q4 2023"},
    @{start="2024-01-01"; end="2024-03-31"; nom="Q1 2024"},
    @{start="2024-04-01"; end="2024-06-30"; nom="Q2 2024"},
    @{start="2024-07-01"; end="2024-09-30"; nom="Q3 2024"},
    @{start="2024-10-01"; end="2024-12-31"; nom="Q4 2024"},
    @{start="2025-01-01"; end="2025-03-31"; nom="Q1 2025"},
    @{start="2025-04-01"; end="2025-06-30"; nom="Q2 2025"},
    @{start="2025-07-01"; end="2025-09-30"; nom="Q3 2025"},
    @{start="2025-10-01"; end="2025-12-31"; nom="Q4 2025"},
    @{start="2026-01-01"; end="2026-02-05"; nom="Q1 2026 (partiel)"}
)

$logFile = "storage\logs\relance-trimestres-$(Get-Date -Format 'yyyyMMdd-HHmmss').log"
$totalTrimestres = $trimestres.Count
$compteur = 0

Write-Host ""
Write-Host "======================================================================" -ForegroundColor Cyan
Write-Host "🔄 Relance des trimestres échoués" -ForegroundColor Cyan
Write-Host "======================================================================" -ForegroundColor Cyan
Write-Host "📅 Total: $totalTrimestres trimestres" -ForegroundColor Yellow
Write-Host "📝 Log: $logFile" -ForegroundColor Gray
Write-Host ""

Add-Content -Path $logFile -Value ""
Add-Content -Path $logFile -Value "======================================================================"
Add-Content -Path $logFile -Value "🔄 Relance des trimestres échoués - $(Get-Date)"
Add-Content -Path $logFile -Value "======================================================================"
Add-Content -Path $logFile -Value ""

foreach ($trimestre in $trimestres) {
    $compteur++
    
    Write-Host ""
    Write-Host "======================================================================" -ForegroundColor Green
    Write-Host "📦 Trimestre $compteur/$totalTrimestres : $($trimestre.nom)" -ForegroundColor Green
    Write-Host "   Période: $($trimestre.start) → $($trimestre.end)" -ForegroundColor Gray
    Write-Host "======================================================================" -ForegroundColor Green
    Write-Host ""
    
    Add-Content -Path $logFile -Value ""
    Add-Content -Path $logFile -Value "======================================================================"
    Add-Content -Path $logFile -Value "📦 Trimestre $compteur/$totalTrimestres : $($trimestre.nom)"
    Add-Content -Path $logFile -Value "   Période: $($trimestre.start) → $($trimestre.end)"
    Add-Content -Path $logFile -Value "======================================================================"
    Add-Content -Path $logFile -Value ""
    
    $startTime = Get-Date
    
    # Exécuter la commande
    $output = php artisan ml:build-historical-features `
        --start-date=$($trimestre.start) `
        --end-date=$($trimestre.end) `
        --chunk=500 `
        --batch-dates=30 2>&1
    
    $endTime = Get-Date
    $duration = $endTime - $startTime
    
    # Ajouter la sortie au log
    Add-Content -Path $logFile -Value $output
    
    # Vérifier le code de sortie
    if ($LASTEXITCODE -eq 0) {
        Write-Host "✅ Trimestre $($trimestre.nom) terminé avec succès" -ForegroundColor Green
        Write-Host "⏱️  Durée: $($duration.ToString('hh\:mm\:ss'))" -ForegroundColor Gray
        
        Add-Content -Path $logFile -Value ""
        Add-Content -Path $logFile -Value "✅ Trimestre $($trimestre.nom) terminé avec succès"
        Add-Content -Path $logFile -Value "⏱️  Durée: $($duration.ToString('hh\:mm\:ss'))"
    } else {
        Write-Host "❌ Trimestre $($trimestre.nom) a échoué (code: $LASTEXITCODE)" -ForegroundColor Red
        
        Add-Content -Path $logFile -Value ""
        Add-Content -Path $logFile -Value "❌ Trimestre $($trimestre.nom) a échoué (code: $LASTEXITCODE)"
    }
}

Write-Host ""
Write-Host "======================================================================" -ForegroundColor Cyan
Write-Host "✅ Relance terminée !" -ForegroundColor Cyan
Write-Host "======================================================================" -ForegroundColor Cyan
Write-Host "📝 Log complet: $logFile" -ForegroundColor Gray
Write-Host ""
Write-Host "💡 Pour vérifier le nombre de dates:" -ForegroundColor Yellow
Write-Host "   php artisan tinker" -ForegroundColor Gray
Write-Host "   >>> DB::table('ml_client_features')->distinct('calculation_date')->count('calculation_date')" -ForegroundColor Gray
Write-Host ""
