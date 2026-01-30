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
            
            // Calcul des statistiques Ooredoo/DGV quotidiennes - Chaque jour à 2h45 du matin
            $schedule->command('ooredoo:update-daily-stats')
                ->dailyAt('02:45')
                ->withoutOverlapping()
                ->runInBackground()
                ->appendOutputTo(storage_path('logs/ooredoo-stats.log'));
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
