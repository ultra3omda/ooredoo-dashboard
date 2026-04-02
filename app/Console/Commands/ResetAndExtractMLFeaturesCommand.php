<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\MLMultiOperatorFeatureService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ResetAndExtractMLFeaturesCommand extends Command
{
    protected $signature = 'ml:reset-and-extract
                            {--start-date=2026-01-01 : Date de début (YYYY-MM-DD)}
                            {--end-date=2026-01-10 : Date de fin (YYYY-MM-DD)}';

    protected $description = 'Vide ml_client_features, lance une nouvelle extraction multi-opérateur puis affiche une analyse des résultats';

    public function handle()
    {
        @ini_set('memory_limit', '512M');
        $startDate = Carbon::parse($this->option('start-date'));
        $endDate = Carbon::parse($this->option('end-date'));

        $this->warn('Cette commande va :');
        $this->line('  1. VIDER complètement la table ml_client_features');
        $this->line('  2. Lancer l\'extraction pour ' . $startDate->toDateString() . ' → ' . $endDate->toDateString());
        $this->line('  3. Afficher une analyse des features insérées.');
        $this->newLine();

        if (!$this->confirm('Continuer ?', true)) {
            return 1;
        }

        $this->info('🗑️  Vidage de la table ml_client_features...');
        $before = DB::table('ml_client_features')->count();
        DB::table('ml_client_features')->truncate();
        $this->info("   Supprimé: {$before} ligne(s).");
        $this->newLine();

        $this->info('🌐 Lancement de l\'extraction multi-opérateur (avec --force implicite)...');
        $featureService = app(MLMultiOperatorFeatureService::class);
        $totalProcessed = 0;
        $currentDate = $startDate->copy();
        while ($currentDate->lte($endDate)) {
            $this->info("📊 Extraction pour {$currentDate->toDateString()}...");
            $processed = $featureService->extractAndStoreFeaturesForDate($currentDate->copy());
            $totalProcessed += $processed;
            $this->line("   ✅ {$processed} clients traités.");
            $currentDate->addDay();
        }
        $this->newLine();
        $this->info("🎉 Extraction terminée. Total: {$totalProcessed} enregistrements traités.");
        $this->newLine();

        $this->printAnalysis($startDate, $endDate);
        return 0;
    }

    private function printAnalysis(Carbon $startDate, Carbon $endDate): void
    {
        $this->info('═══════════════════════════════════════════════════════════');
        $this->info('  ANALYSE DES RÉSULTATS (ml_client_features)');
        $this->info('═══════════════════════════════════════════════════════════');
        $this->newLine();

        $total = DB::table('ml_client_features')->count();
        $byDate = DB::table('ml_client_features')
            ->selectRaw('calculation_date, COUNT(*) as cnt')
            ->groupBy('calculation_date')
            ->orderBy('calculation_date')
            ->get();

        $this->table(
            ['Métrique', 'Valeur'],
            [
                ['Total lignes', number_format($total)],
                ['Dates couvertes', $byDate->pluck('calculation_date')->map(fn ($d) => $d)->implode(', ')],
            ]
        );
        $this->newLine();

        $cols = \Illuminate\Support\Facades\Schema::getColumnListing('ml_client_features');
        $hasTimwe = in_array('timwe_total_attempts', $cols);
        $hasEklektik = in_array('eklektik_total_attempts', $cols);
        $hasOoredoo = in_array('ooredoo_total_attempts', $cols);

        if ($hasTimwe) {
            $withTimwe = DB::table('ml_client_features')->where('timwe_total_attempts', '>', 0)->count();
            $avgTimweRate = DB::table('ml_client_features')->where('timwe_total_attempts', '>', 0)->avg('timwe_success_rate');
            $this->line("📌 Timwe: {$withTimwe} ligne(s) avec activité (timwe_total_attempts > 0)");
            if ($withTimwe > 0) {
                $this->line("   Taux succès moyen (quand activité): " . round(($avgTimweRate ?? 0) * 100, 2) . '%');
            }
        }
        if ($hasEklektik) {
            $withEklektik = DB::table('ml_client_features')->where('eklektik_total_attempts', '>', 0)->count();
            $this->line("📌 Eklektik: {$withEklektik} ligne(s) avec activité (eklektik_total_attempts > 0)");
        }
        if ($hasOoredoo) {
            $withOoredoo = DB::table('ml_client_features')->where('ooredoo_total_attempts', '>', 0)->count();
            $this->line("📌 Ooredoo/DGV: {$withOoredoo} ligne(s) avec activité (ooredoo_total_attempts > 0)");
        }

        $withAny = DB::table('ml_client_features')
            ->where(function ($q) use ($hasTimwe, $hasEklektik, $hasOoredoo) {
                if ($hasTimwe) $q->orWhere('timwe_total_attempts', '>', 0);
                if ($hasEklektik) $q->orWhere('eklektik_total_attempts', '>', 0);
                if ($hasOoredoo) $q->orWhere('ooredoo_total_attempts', '>', 0);
            })
            ->count();
        $this->line("📌 Au moins un opérateur avec activité: {$withAny} ligne(s)");
        $this->newLine();

        $select = array_intersect([
            'client_id', 'calculation_date',
            'timwe_success_rate', 'timwe_total_attempts', 'timwe_has_activity',
            'eklektik_total_attempts', 'eklektik_has_activity',
            'ooredoo_total_attempts', 'ooredoo_has_activity',
        ], $cols);
        if (empty($select)) {
            $select = ['client_id', 'calculation_date', 'timwe_total_attempts', 'timwe_has_activity'];
        }
        $sample = DB::table('ml_client_features')
            ->where(function ($q) use ($hasTimwe, $hasEklektik, $hasOoredoo) {
                if ($hasTimwe) $q->orWhere('timwe_total_attempts', '>', 0);
                if ($hasEklektik) $q->orWhere('eklektik_total_attempts', '>', 0);
                if ($hasOoredoo) $q->orWhere('ooredoo_total_attempts', '>', 0);
            })
            ->select($select)
            ->orderByDesc('timwe_total_attempts')
            ->limit(10)
            ->get();

        $this->info('Exemple (10 lignes avec activité, tri par timwe_total_attempts décroissant):');
        $rows = $sample->map(fn ($r) => array_map(fn ($k) => $r->$k ?? '', $select))->toArray();
        $this->table($select, $rows);
    }
}
