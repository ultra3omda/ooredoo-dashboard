<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ExportMLDataForTrainingCommand extends Command
{
    protected $signature = 'ml:export-training-data 
                            {--source=training : Source (training ou production)}
                            {--output= : Fichier de sortie (défaut: ml_training_data.csv)}';

    protected $description = 'Exporte les données ML vers CSV pour entraînement Python (sans limite mémoire)';

    public function handle()
    {
        $this->info('📤 Export des données ML pour entraînement...');
        
        $source = $this->option('source');
        $sourceTable = $source === 'production' ? 'ml_client_features' : 'ml_client_features_training';
        $outputFile = $this->option('output') ?? storage_path('ml_training_data.csv');
        
        $this->info("📊 Source: {$sourceTable}");
        $this->info("💾 Destination: {$outputFile}");
        
        // Compter les lignes
        $totalRows = DB::table($sourceTable)->count();
        $this->info("📈 Total: " . number_format($totalRows) . " lignes");
        
        if ($totalRows == 0) {
            $this->error('❌ Aucune donnée à exporter');
            return Command::FAILURE;
        }
        
        // Créer le fichier CSV
        $fp = fopen($outputFile, 'w');
        if (!$fp) {
            $this->error("❌ Impossible de créer $outputFile");
            return Command::FAILURE;
        }
        
        // En-têtes CSV (features sélectionnées)
        $features = [
            'client_id', 'calculation_date',
            'timwe_success_rate', 'timwe_has_activity', 'timwe_total_attempts',
            'eklektik_success_rate', 'eklektik_has_activity', 'eklektik_total_attempts',
            'ooredoo_success_rate', 'ooredoo_has_activity', 'ooredoo_total_attempts',
            'total_90d_count', 'total_90d_sum', 'total_90d_avg',
            'total_operators_used', 'best_performing_operator',
            'price_preference', 'preferred_frequency', 'client_segment',
            'payment_reliability_score', 'engagement_score', 'lifetime_value_score'
        ];
        
        fputcsv($fp, $features);
        
        // Export par chunks (10000 lignes à la fois) pour éviter les problèmes mémoire
        $chunkSize = 10000;
        $exported = 0;
        
        $bar = $this->output->createProgressBar($totalRows);
        $bar->start();
        
        DB::table($sourceTable)
            ->select($features)
            ->orderBy('calculation_date')
            ->chunk($chunkSize, function ($rows) use ($fp, &$exported, $bar) {
                foreach ($rows as $row) {
                    $rowData = [];
                    foreach ((array)$row as $value) {
                        $rowData[] = $value;
                    }
                    fputcsv($fp, $rowData);
                    $exported++;
                    $bar->advance();
                }
            });
        
        fclose($fp);
        $bar->finish();
        
        $this->newLine(2);
        $this->info("✅ Export terminé !");
        $this->info("   • Lignes exportées: " . number_format($exported));
        $this->info("   • Fichier: $outputFile");
        $this->info("   • Taille: " . round(filesize($outputFile) / 1024 / 1024, 2) . " MB");
        
        $this->newLine();
        $this->info("🐍 Pour entraîner avec Python:");
        $this->line("   python ml_models/train_model.py --data=$outputFile");
        
        return Command::SUCCESS;
    }
}
