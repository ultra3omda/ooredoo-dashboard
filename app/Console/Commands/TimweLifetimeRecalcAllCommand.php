<?php

namespace App\Console\Commands;

use App\Services\TimweLifetimeAggregateService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class TimweLifetimeRecalcAllCommand extends Command
{
    protected $signature = 'timwe:lifetime-recalc-all';

    protected $description = 'Recalcule toute la table timwe_phone_lifetime_stats via GROUP BY (un seul passage SQL agrégé, pas de foreach > 5000).';

    public function handle(): int
    {
        if (!Schema::hasTable('timwe_phone_lifetime_stats')) {
            $this->error('La table timwe_phone_lifetime_stats n\'existe pas. Exécutez les migrations.');
            return self::FAILURE;
        }
        $this->info('Recalcul lifetime (GROUP BY client_telephone)...');
        (new TimweLifetimeAggregateService())->recalcAll();
        $this->info('Terminé.');
        return self::SUCCESS;
    }
}
