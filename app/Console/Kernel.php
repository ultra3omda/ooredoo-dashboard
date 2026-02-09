<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // DÉSACTIVÉ - Système sync:pull désactivé pour éviter les conflits
        // Le système Eklektik gère maintenant toute la synchronisation
        // $schedule->command('sync:pull')->everyThirtyMinutes()->withoutOverlapping();
        // $schedule->command('sync:pull')->dailyAt('02:00')->withoutOverlapping();
        
        // Synchronisation Eklektik - Configuration dynamique via interface
            if (\App\Models\EklektikCronConfig::isCronEnabled()) {
                $cronSchedule = \App\Models\EklektikCronConfig::getConfig('cron_schedule', '0 2 * * *');
                $schedule->command('eklektik:sync-stats --period=1 --force')
                    ->cron($cronSchedule)
                    ->withoutOverlapping()
                    ->runInBackground()
                    ->appendOutputTo(storage_path('logs/eklektik-sync.log'));
            }

            // Visite du lien de synchronisation Club Privilèges - Toutes les heures
            $schedule->command('cp:visit-sync')
                ->hourly()
                ->withoutOverlapping()
                ->runInBackground()
                ->appendOutputTo(storage_path('logs/cp-sync.log'));

            // Calcul des statistiques Timwe quotidiennes - Chaque jour à 2h30 du matin
            $schedule->command('timwe:calculate-daily')
                ->dailyAt('02:30')
                ->withoutOverlapping()
                ->runInBackground()
                ->appendOutputTo(storage_path('logs/timwe-stats.log'));

            // Calcul du diagnostic Timwe quotidien - Chaque jour à 2h35 du matin
            $yesterday = \Carbon\Carbon::yesterday()->format('Y-m-d');
            $schedule->command("timwe:diagnostic-backfill --start-date={$yesterday} --end-date={$yesterday} --force")
                ->dailyAt('02:35')
                ->withoutOverlapping()
                ->runInBackground()
                ->appendOutputTo(storage_path('logs/timwe-diagnostic.log'));
            
            // Calcul des statistiques Ooredoo/DGV quotidiennes - Chaque jour à 2h45 du matin
            $schedule->command('ooredoo:update-daily-stats')
                ->dailyAt('02:45')
                ->withoutOverlapping()
                ->runInBackground()
                ->appendOutputTo(storage_path('logs/ooredoo-stats.log'));

            // Métriques ML (ml_model_performance) - Chaque jour à 3h
            // Décommenter pour alimenter ml_model_performance automatiquement
            // $schedule->command('ml:log-performance')->dailyAt('03:00')->withoutOverlapping();

            // Cache intelligent (contexte agent IA, KPIs, features ML) - Tous les jours à 6h
            $schedule->command('cache:warmup')
                ->dailyAt('06:00')
                ->withoutOverlapping()
                ->runInBackground()
                ->appendOutputTo(storage_path('logs/cache-warmup.log'));

            // ============================================================
            // SYSTÈME ML INCRÉMENTAL - Architecture optimisée
            // ============================================================
            
            // Ingestion incrémentale des transactions vers tx_daily_agg
            // Toutes les 5 minutes pour minimiser le retard
            $schedule->command('ml:tx-daily-ingest --batch-size=100000 --max-batches=5')
                ->everyFiveMinutes()
                ->withoutOverlapping(30) // Timeout 30 min
                ->runInBackground()
                ->appendOutputTo(storage_path('logs/ml-ingest.log'));

            // Construction des features ML 90 jours depuis les agrégats
            // Toutes les 2 heures pour maintenir les features à jour
            $schedule->command('ml:build-90d-features --chunk=2000')
                ->everyTwoHours()
                ->withoutOverlapping(60) // Timeout 60 min
                ->runInBackground()
                ->appendOutputTo(storage_path('logs/ml-features-90d.log'));

            // Maintenance et nettoyage des agrégats (rétention 120 jours)
            // Une fois par semaine le dimanche à 4h
            $schedule->command('ml:tx-daily-maintenance --retention-days=120 --vacuum')
                ->weekly()
                ->sundays()
                ->at('04:00')
                ->withoutOverlapping()
                ->runInBackground()
                ->appendOutputTo(storage_path('logs/ml-maintenance.log'));
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
