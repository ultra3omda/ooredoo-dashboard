# Arrete les processus PHP qui executent le backfill Timwe
Get-CimInstance Win32_Process -Filter "name='php.exe'" | Where-Object {
    $_.CommandLine -match 'timwe.*backfill|diagnostic-backfill'
} | ForEach-Object {
    Write-Host "Arret du PID $($_.ProcessId)"
    Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue
}
