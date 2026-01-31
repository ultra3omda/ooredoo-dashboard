<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class ReimportAllEklektikDataCommand extends Command
{
    protected $signature = 'eklektik:reimport-all
                            {--period=365 : Nombre de jours à synchroniser (ex. 365 = 1 an)}
                            {--start-date= : Date de début (YYYY-MM-DD)}
                            {--end-date= : Date de fin (YYYY-MM-DD)}
                            {--force : Écraser les données existantes (recommandé pour un réimport)}';

    protected $description = 'Réimporter toutes les données Eklektik depuis l’API (délègue à eklektik:sync-stats)';

    public function handle(): int
    {
        $this->info('═══════════════════════════════════════════════════════════════════');
        $this->info('   RÉIMPORT COMPLET DES DONNÉES EKLEKTIK');
        $this->info('═══════════════════════════════════════════════════════════════════');
        $this->newLine();

        $startDate = $this->option('start-date');
        $endDate = $this->option('end-date');
        $period = (int) $this->option('period');
        $force = $this->option('force');

        $args = ['--force' => $force];

        if ($startDate && $endDate) {
            $this->info("📅 Période: {$startDate} → {$endDate}");
            $args['--start-date'] = $startDate;
            $args['--end-date'] = $endDate;
        } else {
            $this->info("📅 Derniers {$period} jours (jusqu’à hier)");
            $args['--period'] = $period;
        }

        $this->info('🔄 Lancement de la synchronisation depuis l’API Eklektik...');
        $this->newLine();

        $exitCode = Artisan::call('eklektik:sync-stats', $args);

        $this->newLine();
        if ($exitCode === 0) {
            $this->info('✅ Réimport Eklektik terminé.');
        } else {
            $this->error('❌ Le réimport a rencontré des erreurs. Vérifiez la config (config/eklektik.php, .env) et l’accès à l’API.');
        }

        return $exitCode;
    }
}
