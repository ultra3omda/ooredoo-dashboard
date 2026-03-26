@echo off
echo ======================================================================
echo Relance des 15 trimestres echoues
echo ======================================================================
echo.

set LOGFILE=storage\logs\relance-trimestres-%date:~-4%%date:~-7,2%%date:~-10,2%-%time:~0,2%%time:~3,2%%time:~6,2%.log
set LOGFILE=%LOGFILE: =0%

echo Trimestre 1/15: Q3 2022 (2022-07-01 - 2022-09-30)
php artisan ml:build-historical-features --start-date=2022-07-01 --end-date=2022-09-30 --chunk=500 --batch-dates=30
echo.

echo Trimestre 2/15: Q4 2022 (2022-10-01 - 2022-12-31)
php artisan ml:build-historical-features --start-date=2022-10-01 --end-date=2022-12-31 --chunk=500 --batch-dates=30
echo.

echo Trimestre 3/15: Q1 2023 (2023-01-01 - 2023-03-31)
php artisan ml:build-historical-features --start-date=2023-01-01 --end-date=2023-03-31 --chunk=500 --batch-dates=30
echo.

echo Trimestre 4/15: Q2 2023 (2023-04-01 - 2023-06-30)
php artisan ml:build-historical-features --start-date=2023-04-01 --end-date=2023-06-30 --chunk=500 --batch-dates=30
echo.

echo Trimestre 5/15: Q3 2023 (2023-07-01 - 2023-09-30)
php artisan ml:build-historical-features --start-date=2023-07-01 --end-date=2023-09-30 --chunk=500 --batch-dates=30
echo.

echo Trimestre 6/15: Q4 2023 (2023-10-01 - 2023-12-31)
php artisan ml:build-historical-features --start-date=2023-10-01 --end-date=2023-12-31 --chunk=500 --batch-dates=30
echo.

echo Trimestre 7/15: Q1 2024 (2024-01-01 - 2024-03-31)
php artisan ml:build-historical-features --start-date=2024-01-01 --end-date=2024-03-31 --chunk=500 --batch-dates=30
echo.

echo Trimestre 8/15: Q2 2024 (2024-04-01 - 2024-06-30)
php artisan ml:build-historical-features --start-date=2024-04-01 --end-date=2024-06-30 --chunk=500 --batch-dates=30
echo.

echo Trimestre 9/15: Q3 2024 (2024-07-01 - 2024-09-30)
php artisan ml:build-historical-features --start-date=2024-07-01 --end-date=2024-09-30 --chunk=500 --batch-dates=30
echo.

echo Trimestre 10/15: Q4 2024 (2024-10-01 - 2024-12-31)
php artisan ml:build-historical-features --start-date=2024-10-01 --end-date=2024-12-31 --chunk=500 --batch-dates=30
echo.

echo Trimestre 11/15: Q1 2025 (2025-01-01 - 2025-03-31)
php artisan ml:build-historical-features --start-date=2025-01-01 --end-date=2025-03-31 --chunk=500 --batch-dates=30
echo.

echo Trimestre 12/15: Q2 2025 (2025-04-01 - 2025-06-30)
php artisan ml:build-historical-features --start-date=2025-04-01 --end-date=2025-06-30 --chunk=500 --batch-dates=30
echo.

echo Trimestre 13/15: Q3 2025 (2025-07-01 - 2025-09-30)
php artisan ml:build-historical-features --start-date=2025-07-01 --end-date=2025-09-30 --chunk=500 --batch-dates=30
echo.

echo Trimestre 14/15: Q4 2025 (2025-10-01 - 2025-12-31)
php artisan ml:build-historical-features --start-date=2025-10-01 --end-date=2025-12-31 --chunk=500 --batch-dates=30
echo.

echo Trimestre 15/15: Q1 2026 partiel (2026-01-01 - 2026-02-05)
php artisan ml:build-historical-features --start-date=2026-01-01 --end-date=2026-02-05 --chunk=500 --batch-dates=30
echo.

echo ======================================================================
echo Relance terminee !
echo ======================================================================
pause
