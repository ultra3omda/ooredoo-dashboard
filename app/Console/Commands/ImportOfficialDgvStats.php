<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ImportOfficialDgvStats extends Command
{
    protected $signature = 'ooredoo:import-official-dgv';
    protected $description = 'Importer les statistiques officielles DGV depuis l\'Excel';

    public function handle()
    {
        $this->info('🚀 Début de l\'import des statistiques officielles DGV...');

        // Données officielles DGV extraites du fichier Excel
        // Format: [mois => [nombre, prix, ca_global, statut]]
        $dgvOfficialData = [
            // 2021
            '2021-06' => ['nombre' => 1632, 'prix' => 0.300, 'ca_global' => 489600],
            '2021-07' => ['nombre' => 2269, 'prix' => 0.300, 'ca_global' => 680700],
            '2021-08' => ['nombre' => 2399, 'prix' => 0.300, 'ca_global' => 719700],
            '2021-09' => ['nombre' => 3047, 'prix' => 0.300, 'ca_global' => 914100],
            '2021-10' => ['nombre' => 5106, 'prix' => 0.300, 'ca_global' => 4531800],  // Notation #####
            '2021-11' => ['nombre' => 0, 'prix' => 0.300, 'ca_global' => 6242100],      // Notation #####
            '2021-12' => ['nombre' => 0, 'prix' => 0.300, 'ca_global' => 7689900],      // Notation #####
            
            // 2022
            '2022-01' => ['nombre' => 28391, 'prix' => 0.300, 'ca_global' => 8517300],
            '2022-02' => ['nombre' => 30561, 'prix' => 0.300, 'ca_global' => 9168300],
            '2022-03' => ['nombre' => 34119, 'prix' => 0.300, 'ca_global' => 10235700],
            '2022-04' => ['nombre' => 32136, 'prix' => 0.300, 'ca_global' => 9640800],
            '2022-05' => ['nombre' => 34160, 'prix' => 0.300, 'ca_global' => 10248000],
            '2022-06' => ['nombre' => 0, 'prix' => 0.300, 'ca_global' => 9257400],      // Notation #####
            '2022-07' => ['nombre' => 0, 'prix' => 0.300, 'ca_global' => 10033500],     // Notation #####
            '2022-08' => ['nombre' => 35671, 'prix' => 0.300, 'ca_global' => 10701300],
            '2022-09' => ['nombre' => 0, 'prix' => 0.300, 'ca_global' => 9989700],      // Notation #####
            '2022-10' => ['nombre' => 0, 'prix' => 0.300, 'ca_global' => 9925500],      // Notation #####
            '2022-11' => ['nombre' => 0, 'prix' => 0.300, 'ca_global' => 9191700],      // Notation #####
            '2022-12' => ['nombre' => 0, 'prix' => 0.300, 'ca_global' => 9253800],      // Notation #####
            
            // 2023
            '2023-01' => ['nombre' => 29104, 'prix' => 0.300, 'ca_global' => 8731200],
            '2023-02' => ['nombre' => 0, 'prix' => 0.300, 'ca_global' => 7522200],      // Notation #####
            '2023-03' => ['nombre' => 0, 'prix' => 0.300, 'ca_global' => 8058000],      // Notation #####
            '2023-04' => ['nombre' => 0, 'prix' => 0.300, 'ca_global' => 7641900],      // Notation #####
            '2023-05' => ['nombre' => 0, 'prix' => 0.300, 'ca_global' => 8181900],      // Notation #####
            '2023-06' => ['nombre' => 26917, 'prix' => 0.300, 'ca_global' => 8075100],
            '2023-07' => ['nombre' => 27104, 'prix' => 0.300, 'ca_global' => 8131200],
            '2023-08' => ['nombre' => 26616, 'prix' => 0.300, 'ca_global' => 7984800],
            '2023-09' => ['nombre' => 0, 'prix' => 0.300, 'ca_global' => 9170100],      // Notation #####
            '2023-10' => ['nombre' => 0, 'prix' => 0.300, 'ca_global' => 7460100],      // Notation #####
            '2023-11' => ['nombre' => 0, 'prix' => 0.300, 'ca_global' => 6923400],      // Notation #####
            '2023-12' => ['nombre' => 15100, 'prix' => 0.300, 'ca_global' => 4545000],
            
            // 2024
            '2024-01' => ['nombre' => 15239, 'prix' => 0.300, 'ca_global' => 4571700],
            '2024-02' => ['nombre' => 13791, 'prix' => 0.300, 'ca_global' => 4137300],
            '2024-03' => ['nombre' => 13208, 'prix' => 0.300, 'ca_global' => 3962400],
            '2024-04' => ['nombre' => 13589, 'prix' => 0.300, 'ca_global' => 4076700],
            '2024-05' => ['nombre' => 13863, 'prix' => 0.300, 'ca_global' => 4160700],
            '2024-06' => ['nombre' => 14093, 'prix' => 0.300, 'ca_global' => 4227900],
            '2024-07' => ['nombre' => 16781, 'prix' => 0.300, 'ca_global' => 5034300],
            '2024-08' => ['nombre' => 18393, 'prix' => 0.300, 'ca_global' => 5519700],
            '2024-09' => ['nombre' => 19705, 'prix' => 0.300, 'ca_global' => 5911500],
            '2024-10' => ['nombre' => 21529, 'prix' => 0.300, 'ca_global' => 6458700],
            '2024-11' => ['nombre' => 29411, 'prix' => 0.300, 'ca_global' => 8820300],
            '2024-12' => ['nombre' => 47301, 'prix' => 0.300, 'ca_global' => 14190300],
            
            // 2025
            '2025-01' => ['nombre' => 52435, 'prix' => 0.300, 'ca_global' => 15730500],
            '2025-02' => ['nombre' => 47410, 'prix' => 0.300, 'ca_global' => 14223000],
            '2025-03' => ['nombre' => 50813, 'prix' => 0.300, 'ca_global' => 15243900],
        ];

        $this->info('📊 Données à importer : ' . count($dgvOfficialData) . ' mois');
        
        $bar = $this->output->createProgressBar(count($dgvOfficialData));
        $bar->start();

        $imported = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($dgvOfficialData as $month => $data) {
            $year = substr($month, 0, 4);
            $monthNum = substr($month, 5, 2);
            $daysInMonth = Carbon::create($year, $monthNum, 1)->daysInMonth;
            
            // Calculer les moyennes quotidiennes
            $avgBillings = $data['nombre'] > 0 ? round($data['nombre'] / $daysInMonth) : 0;
            $avgRevenue = round(($data['ca_global'] / 1000) / $daysInMonth, 2); // Convertir millimes en TND

            // Insérer ou mettre à jour chaque jour du mois
            for ($day = 1; $day <= $daysInMonth; $day++) {
                $date = Carbon::create($year, $monthNum, $day)->format('Y-m-d');
                
                // Vérifier si la date existe déjà
                $exists = DB::table('ooredoo_daily_stats')
                    ->where('stat_date', $date)
                    ->exists();

                if ($exists) {
                    // Mettre à jour avec les données officielles
                    DB::table('ooredoo_daily_stats')
                        ->where('stat_date', $date)
                        ->update([
                            'total_billings' => $avgBillings,
                            'revenue_tnd' => $avgRevenue,
                            'data_source' => 'officiel_dgv',
                            'updated_at' => now(),
                        ]);
                    $updated++;
                } else {
                    // Insérer avec valeurs par défaut pour les autres colonnes
                    DB::table('ooredoo_daily_stats')->insert([
                        'stat_date' => $date,
                        'new_subscriptions' => 0,
                        'unsubscriptions' => 0,
                        'active_subscriptions' => 0,
                        'total_billings' => $avgBillings,
                        'revenue_tnd' => $avgRevenue,
                        'billing_rate' => 0,
                        'data_source' => 'officiel_dgv',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $imported++;
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("✅ Import terminé !");
        $this->table(
            ['Action', 'Nombre'],
            [
                ['Jours importés', $imported],
                ['Jours mis à jour', $updated],
                ['Jours ignorés', $skipped],
                ['TOTAL', $imported + $updated + $skipped],
            ]
        );

        $this->info('💡 Note : Les données sont réparties uniformément sur chaque jour du mois.');
        $this->info('📋 Source des données : Fichier Excel "Situation Bigdeal Club Privilège"');

        return Command::SUCCESS;
    }
}

