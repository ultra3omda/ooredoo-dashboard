<?php

namespace App\Console\Commands;

use App\Services\IntelligentCacheService;
use Illuminate\Console\Command;

class WarmupIntelligentCacheCommand extends Command
{
    protected $signature = 'cache:warmup
                            {--stats : Afficher les statistiques du cache après le warmup}';

    protected $description = 'Préchauffe le cache intelligent (contexte agent IA, KPIs, features ML)';

    public function __construct(
        private IntelligentCacheService $cacheService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('🔥 Préchauffage du cache intelligent (contexte ML / agent IA)...');

        $this->cacheService->warmupCache();

        $this->info('✅ Warmup terminé.');

        if ($this->option('stats')) {
            $stats = $this->cacheService->getStats();
            $this->table(
                ['Métrique', 'Valeur'],
                [
                    ['Hits', $stats['hits']],
                    ['Misses', $stats['misses']],
                    ['Taux de hit', $stats['hit_rate'].' %'],
                    ['Mémoire (Redis)', $stats['memory_used']],
                ]
            );
        }

        return Command::SUCCESS;
    }
}
