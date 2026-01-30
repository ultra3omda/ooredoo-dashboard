<?php

namespace App\Console\Commands;

use App\Services\TimweStatsService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RecalculateTimweStatsOctober2025 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'timwe:recalculate-october-2025';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculer les statistiques Timwe pour octobre 2025 avec la nouvelle logique (totalCharged > 0)';

    protected TimweStatsService $timweStatsService;

    public function __construct(TimweStatsService $timweStatsService)
    {
        parent::__construct();
        $this->timweStatsService = $timweStatsService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info("🔄 Recalcul des statistiques Timwe pour octobre 2025...");
        $this->info("📋 Nouvelle logique: pricepointId=63980 AND mnoDeliveryCode=DELIVERED AND totalCharged > 0");
        $this->newLine();

        $startDate = Carbon::parse('2025-10-01')->startOfDay();
        $endDate = Carbon::parse('2025-10-31')->endOfDay();
        
        $currentDate = $startDate->copy();
        $successCount = 0;
        $errorCount = 0;
        $results = [];

        while ($currentDate->lte($endDate)) {
            $dateStr = $currentDate->format('Y-m-d');
            $this->info("📅 Calcul pour {$dateStr}...", false);
            
            if ($this->timweStatsService->calculateAndStoreStatsForDate($currentDate)) {
                $stat = \App\Models\TimweDailyStat::where('stat_date', $dateStr)->first();
                if ($stat) {
                    $results[] = [
                        'date' => $dateStr,
                        'billings' => $stat->total_billings,
                        'revenue_tnd' => $stat->revenue_tnd,
                        'status' => '✅'
                    ];
                    $this->info(" ✅ ({$stat->total_billings} facturations)");
                    $successCount++;
                } else {
                    $this->error(" ❌ (Stat non trouvée après calcul)");
                    $errorCount++;
                }
            } else {
                $this->error(" ❌ (Échec du calcul)");
                $errorCount++;
            }
            
            $currentDate->addDay();
        }

        $this->newLine();
        $this->info("📊 Résumé:");
        $this->table(
            ['Date', 'Facturations', 'Revenu TND', 'Status'],
            array_map(function($r) {
                return [
                    $r['date'],
                    number_format($r['billings']),
                    number_format($r['revenue_tnd'], 3),
                    $r['status']
                ];
            }, $results)
        );

        $this->newLine();
        $this->info("✅ Succès: {$successCount} dates");
        if ($errorCount > 0) {
            $this->error("❌ Erreurs: {$errorCount} dates");
        }

        // Comparaison avec les CSV de référence
        $this->newLine();
        $this->info("📋 Comparaison avec les CSV de référence:");
        $csvTotals = [
            '2025-10-01' => 273, '2025-10-02' => 149, '2025-10-03' => 166, '2025-10-04' => 133,
            '2025-10-05' => 189, '2025-10-06' => 154, '2025-10-07' => 158, '2025-10-08' => 113,
            '2025-10-09' => 135, '2025-10-10' => 146, '2025-10-11' => 126, '2025-10-12' => 116,
            '2025-10-13' => 104, '2025-10-14' => 81, '2025-10-15' => 80, '2025-10-16' => 80,
            '2025-10-17' => 64, '2025-10-18' => 69, '2025-10-19' => 49, '2025-10-20' => 78,
            '2025-10-21' => 92, '2025-10-22' => 100, '2025-10-23' => 92, '2025-10-24' => 74,
            '2025-10-25' => 144, '2025-10-26' => 125, '2025-10-27' => 167, '2025-10-28' => 111,
            '2025-10-29' => 121, '2025-10-30' => 141, '2025-10-31' => 119
        ];

        $comparison = [];
        foreach ($results as $result) {
            $csvTotal = $csvTotals[$result['date']] ?? null;
            if ($csvTotal !== null) {
                $diff = $result['billings'] - $csvTotal;
                $match = $diff === 0 ? '✅' : '⚠️';
                $comparison[] = [
                    'date' => $result['date'],
                    'calculé' => $result['billings'],
                    'csv' => $csvTotal,
                    'diff' => $diff,
                    'match' => $match
                ];
            }
        }

        if (!empty($comparison)) {
            $this->table(
                ['Date', 'Calculé', 'CSV', 'Diff', 'Match'],
                array_map(function($c) {
                    return [
                        $c['date'],
                        number_format($c['calculé']),
                        number_format($c['csv']),
                        $c['diff'] > 0 ? '+' . $c['diff'] : (string)$c['diff'],
                        $c['match']
                    ];
                }, $comparison)
            );
        }

        return Command::SUCCESS;
    }
}
