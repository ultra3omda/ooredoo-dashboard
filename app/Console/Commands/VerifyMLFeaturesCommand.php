<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VerifyMLFeaturesCommand extends Command
{
    protected $signature = 'ml:verify-features
                            {--date= : Vérifier une date précise (YYYY-MM-DD)}
                            {--last-dates=5 : Nombre de dates à résumer (si --date non fourni)}';

    protected $description = 'Vérifie rapidement le contenu de ml_client_features (lignes, activité par opérateur, timestamps)';

    public function handle()
    {
        $this->info('═══════════════════════════════════════════════════════════');
        $this->info('  VÉRIFICATION ml_client_features');
        $this->info('═══════════════════════════════════════════════════════════');
        $this->newLine();

        $total = DB::table('ml_client_features')->count();
        if ($total === 0) {
            $this->warn('La table est vide. Lancez : php artisan ml:extract-multi --start-date=... --end-date=...');
            return 0;
        }

        $dateStr = $this->option('date');
        $cols = Schema::getColumnListing('ml_client_features');
        $hasTimwe = in_array('timwe_total_attempts', $cols);
        $hasEklektik = in_array('eklektik_total_attempts', $cols);
        $hasOoredoo = in_array('ooredoo_total_attempts', $cols);
        $hasCreatedAt = in_array('created_at', $cols);
        $hasUpdatedAt = in_array('updated_at', $cols);

        if ($dateStr) {
            $count = DB::table('ml_client_features')->where('calculation_date', $dateStr)->count();
            $this->table(['Métrique', 'Valeur'], [
                ['Date', $dateStr],
                ['Lignes pour cette date', number_format($count)],
            ]);
            if ($count === 0) {
                $this->warn("Aucune ligne pour {$dateStr}. Vérifiez la période d'extraction.");
                return 0;
            }
            $withTimwe = $hasTimwe ? DB::table('ml_client_features')->where('calculation_date', $dateStr)->where('timwe_total_attempts', '>', 0)->count() : 0;
            $withEklektik = $hasEklektik ? DB::table('ml_client_features')->where('calculation_date', $dateStr)->where('eklektik_total_attempts', '>', 0)->count() : 0;
            $withOoredoo = $hasOoredoo ? DB::table('ml_client_features')->where('calculation_date', $dateStr)->where('ooredoo_total_attempts', '>', 0)->count() : 0;
            $withTs = ($hasCreatedAt && $hasUpdatedAt)
                ? DB::table('ml_client_features')->where('calculation_date', $dateStr)->whereNotNull('created_at')->whereNotNull('updated_at')->count()
                : null;

            $rows = [
                ['Total lignes (cette date)', number_format($count)],
                ['Avec activité Timwe', $hasTimwe ? number_format($withTimwe) : 'N/A'],
                ['Avec activité Eklektik', $hasEklektik ? number_format($withEklektik) : 'N/A'],
                ['Avec activité Ooredoo/DGV', $hasOoredoo ? number_format($withOoredoo) : 'N/A'],
            ];
            if ($withTs !== null) {
                $rows[] = ['Avec created_at + updated_at renseignés', number_format($withTs) . ' / ' . number_format($count)];
            }
            $this->table(['Métrique', 'Valeur'], $rows);
            return 0;
        }

        $lastDates = (int) $this->option('last-dates');
        $byDate = DB::table('ml_client_features')
            ->selectRaw('calculation_date, COUNT(*) as cnt')
            ->groupBy('calculation_date')
            ->orderByDesc('calculation_date')
            ->limit($lastDates)
            ->get();

        $withTimwe = $hasTimwe ? DB::table('ml_client_features')->where('timwe_total_attempts', '>', 0)->count() : 0;
        $withEklektik = $hasEklektik ? DB::table('ml_client_features')->where('eklektik_total_attempts', '>', 0)->count() : 0;
        $withOoredoo = $hasOoredoo ? DB::table('ml_client_features')->where('ooredoo_total_attempts', '>', 0)->count() : 0;
        $withAny = DB::table('ml_client_features')
            ->where(function ($q) use ($hasTimwe, $hasEklektik, $hasOoredoo) {
                if ($hasTimwe) $q->orWhere('timwe_total_attempts', '>', 0);
                if ($hasEklektik) $q->orWhere('eklektik_total_attempts', '>', 0);
                if ($hasOoredoo) $q->orWhere('ooredoo_total_attempts', '>', 0);
            })
            ->count();
        $withTs = ($hasCreatedAt && $hasUpdatedAt)
            ? DB::table('ml_client_features')->whereNotNull('created_at')->whereNotNull('updated_at')->count()
            : null;

        $summary = [
            ['Total lignes', number_format($total)],
            ['Avec activité Timwe', $hasTimwe ? number_format($withTimwe) : 'N/A'],
            ['Avec activité Eklektik', $hasEklektik ? number_format($withEklektik) : 'N/A'],
            ['Avec activité Ooredoo/DGV', $hasOoredoo ? number_format($withOoredoo) : 'N/A'],
            ['Avec au moins un opérateur actif', number_format($withAny)],
        ];
        if ($withTs !== null) {
            $summary[] = ['Lignes avec created_at + updated_at', number_format($withTs) . ' / ' . number_format($total)];
        }
        $this->table(['Métrique', 'Valeur'], $summary);

        $this->newLine();
        $this->line('Dernières dates (nombre de lignes) :');
        $rows = $byDate->map(fn ($r) => [$r->calculation_date, number_format($r->cnt)])->toArray();
        $this->table(['calculation_date', 'lignes'], $rows);

        $this->newLine();
        $this->line('Commandes utiles :');
        $this->line('  • Extraction (sans ré-insérer les dates déjà faites) : php artisan ml:extract-multi --start-date=YYYY-MM-DD --end-date=YYYY-MM-DD');
        $this->line('  • Forcer une date : php artisan ml:extract-multi --start-date=YYYY-MM-DD --end-date=YYYY-MM-DD --force');
        $this->line('  • Vider et ré-extraire : php artisan ml:reset-and-extract --start-date=YYYY-MM-DD --end-date=YYYY-MM-DD');
        $this->line('  • Vérifier une date : php artisan ml:verify-features --date=YYYY-MM-DD');
        $this->line('  • Diagnostic détaillé (un client) : php artisan ml:diagnose-features --client-id=XXX --date=YYYY-MM-DD');

        return 0;
    }
}
