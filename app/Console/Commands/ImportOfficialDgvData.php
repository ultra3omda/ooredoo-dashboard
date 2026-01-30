<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ImportOfficialDgvData extends Command
{
    protected $signature = 'ooredoo:import-dgv-official';
    protected $description = 'Import official DGV monthly data and distribute to daily stats';

    // Données officielles DGV depuis le fichier Excel
    private $dgvOfficialData = [
        // Année 2021
        '2021-06' => ['nombre' => 1632, 'prix' => 0.300, 'ca_global_millimes' => 489600],
        '2021-07' => ['nombre' => 2269, 'prix' => 0.300, 'ca_global_millimes' => 680700],
        '2021-08' => ['nombre' => 2399, 'prix' => 0.300, 'ca_global_millimes' => 719700],
        '2021-09' => ['nombre' => 3047, 'prix' => 0.300, 'ca_global_millimes' => 914100],
        '2021-10' => ['nombre' => 5106, 'prix' => 0.300, 'ca_global_millimes' => 4531800],
        '2021-11' => ['nombre' => null, 'prix' => 0.300, 'ca_global_millimes' => 6242100],
        '2021-12' => ['nombre' => null, 'prix' => 0.300, 'ca_global_millimes' => 7689900],
        
        // Année 2022
        '2022-01' => ['nombre' => 28391, 'prix' => 0.300, 'ca_global_millimes' => 8517300],
        '2022-02' => ['nombre' => 30561, 'prix' => 0.300, 'ca_global_millimes' => 9168300],
        '2022-03' => ['nombre' => 34119, 'prix' => 0.300, 'ca_global_millimes' => 10235700],
        '2022-04' => ['nombre' => 32136, 'prix' => 0.300, 'ca_global_millimes' => 9640800],
        '2022-05' => ['nombre' => 34160, 'prix' => 0.300, 'ca_global_millimes' => 10248000],
        '2022-06' => ['nombre' => null, 'prix' => 0.300, 'ca_global_millimes' => 9257400],
        '2022-07' => ['nombre' => null, 'prix' => 0.300, 'ca_global_millimes' => 10033500],
        '2022-08' => ['nombre' => 35671, 'prix' => 0.300, 'ca_global_millimes' => 10701300],
        '2022-09' => ['nombre' => null, 'prix' => 0.300, 'ca_global_millimes' => 9989700],
        '2022-10' => ['nombre' => null, 'prix' => 0.300, 'ca_global_millimes' => 9925500],
        '2022-11' => ['nombre' => null, 'prix' => 0.300, 'ca_global_millimes' => 9191700],
        '2022-12' => ['nombre' => null, 'prix' => 0.300, 'ca_global_millimes' => 9253800],
        
        // Année 2023
        '2023-01' => ['nombre' => 29104, 'prix' => 0.300, 'ca_global_millimes' => 8731200],
        '2023-02' => ['nombre' => null, 'prix' => 0.300, 'ca_global_millimes' => 7522200],
        '2023-03' => ['nombre' => null, 'prix' => 0.300, 'ca_global_millimes' => 8058000],
        '2023-04' => ['nombre' => null, 'prix' => 0.300, 'ca_global_millimes' => 7641900],
        '2023-05' => ['nombre' => null, 'prix' => 0.300, 'ca_global_millimes' => 8181900],
        '2023-06' => ['nombre' => 26917, 'prix' => 0.300, 'ca_global_millimes' => 8075100],
        '2023-07' => ['nombre' => 27104, 'prix' => 0.300, 'ca_global_millimes' => 8131200],
        '2023-08' => ['nombre' => 26616, 'prix' => 0.300, 'ca_global_millimes' => 7984800],
        '2023-09' => ['nombre' => null, 'prix' => 0.300, 'ca_global_millimes' => 9170100],
        '2023-10' => ['nombre' => null, 'prix' => 0.300, 'ca_global_millimes' => 7460100],
        '2023-11' => ['nombre' => null, 'prix' => 0.300, 'ca_global_millimes' => 6923400],
        '2023-12' => ['nombre' => 15100, 'prix' => 0.300, 'ca_global_millimes' => 4545000],
        
        // Année 2024
        '2024-01' => ['nombre' => 15239, 'prix' => 0.300, 'ca_global_millimes' => 4571700],
        '2024-02' => ['nombre' => 13791, 'prix' => 0.300, 'ca_global_millimes' => 4137300],
        '2024-03' => ['nombre' => 13208, 'prix' => 0.300, 'ca_global_millimes' => 3962400],
        '2024-04' => ['nombre' => 13589, 'prix' => 0.300, 'ca_global_millimes' => 4076700],
        '2024-05' => ['nombre' => 13863, 'prix' => 0.300, 'ca_global_millimes' => 4160700],
        '2024-06' => ['nombre' => 16781, 'prix' => 0.300, 'ca_global_millimes' => 5034300],
        '2024-07' => ['nombre' => 16781, 'prix' => 0.300, 'ca_global_millimes' => 5034300],
        '2024-08' => ['nombre' => 18393, 'prix' => 0.300, 'ca_global_millimes' => 5519700],
        '2024-09' => ['nombre' => 19705, 'prix' => 0.300, 'ca_global_millimes' => 5911500],
        '2024-10' => ['nombre' => 21529, 'prix' => 0.300, 'ca_global_millimes' => 6458700],
        '2024-11' => ['nombre' => 29411, 'prix' => 0.300, 'ca_global_millimes' => 8820300],
        '2024-12' => ['nombre' => 47301, 'prix' => 0.300, 'ca_global_millimes' => 14190300],
        
        // Année 2025
        '2025-01' => ['nombre' => 52435, 'prix' => 0.300, 'ca_global_millimes' => 15730500],
        '2025-02' => ['nombre' => 47410, 'prix' => 0.300, 'ca_global_millimes' => 14223000],
        '2025-03' => ['nombre' => 50813, 'prix' => 0.300, 'ca_global_millimes' => 15243900],
    ];

    public function handle()
    {
        $this->info("🚀 Début de l'import des données officielles DGV...\n");

        DB::beginTransaction();
        
        try {
            $totalMonths = count($this->dgvOfficialData);
            $processedMonths = 0;

            foreach ($this->dgvOfficialData as $month => $data) {
                $processedMonths++;
                $this->info("📅 Traitement de $month ($processedMonths/$totalMonths)...");

                $this->importMonthlyData($month, $data);
            }

            DB::commit();
            $this->info("\n✅ Import terminé avec succès !");
            $this->info("📊 Total: $processedMonths mois importés");

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("\n❌ Erreur lors de l'import: " . $e->getMessage());
            $this->error($e->getTraceAsString());
            return 1;
        }

        return 0;
    }

    private function importMonthlyData($month, $data)
    {
        $date = Carbon::parse($month . '-01');
        $daysInMonth = $date->daysInMonth;

        // Calculer les valeurs moyennes par jour
        $nombre = $data['nombre'] ?? ($data['ca_global_millimes'] / 1000 / $data['prix']);
        $ca_tnd = $data['ca_global_millimes'] / 1000;
        
        $billings_per_day = round($nombre / $daysInMonth);
        $revenue_per_day = $ca_tnd / $daysInMonth;

        $this->info("  → Nombre total: " . number_format($nombre));
        $this->info("  → CA Total: " . number_format($ca_tnd, 2) . " TND");
        $this->info("  → Par jour: ~" . number_format($billings_per_day) . " facturations, ~" . number_format($revenue_per_day, 2) . " TND");

        // Insérer ou mettre à jour chaque jour du mois
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $statDate = $date->copy()->day($day)->format('Y-m-d');

            // Calculer active_subscriptions (simplifié : nombre moyen de facturations par jour)
            $active_subscriptions = $billings_per_day;

            DB::table('ooredoo_daily_stats')->updateOrInsert(
                ['stat_date' => $statDate],
                [
                    'new_subscriptions' => 0, // Non disponible dans les données DGV
                    'unsubscriptions' => 0, // Non disponible dans les données DGV
                    'total_billings' => $billings_per_day,
                    'revenue_tnd' => $revenue_per_day,
                    'active_subscriptions' => $active_subscriptions,
                    'total_clients' => $active_subscriptions,
                    'billing_rate' => 100.0, // Hypothèse : tous les clients actifs sont facturés
                    'offers_breakdown' => json_encode(['Club Privilèges' => $billings_per_day]),
                    'data_source' => 'officiel_dgv',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}

