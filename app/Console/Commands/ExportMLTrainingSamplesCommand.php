<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExportMLTrainingSamplesCommand extends Command
{
    protected $signature = 'ml:export-training-samples 
                            {--output=storage/ml_training_samples.csv : Fichier CSV de sortie}
                            {--chunk-size=10000 : Taille des batchs}';

    protected $description = 'Exporte les samples ML vers CSV pour entraînement Python';

    public function handle()
    {
        $output = $this->option('output');
        $chunkSize = (int) $this->option('chunk-size');
        
        // Vérifier que la table existe et contient des données
        $total = DB::table('ml_training_samples')->count();
        
        if ($total == 0) {
            $this->error('❌ La table ml_training_samples est vide.');
            $this->info('   Exécutez d\'abord : php artisan ml:build-training-samples');
            return Command::FAILURE;
        }
        
        $this->info("📦 Export de {$total} samples vers CSV...");
        $this->newLine();
        
        // Définir les colonnes à exporter (sans les ID et timestamps)
        $features = [
            // Features historiques (input)
            'timwe_past_attempts', 'timwe_past_successes', 'timwe_past_failures',
            'timwe_past_avg_success_rate', 'timwe_days_since_last_success',
            'eklektik_past_attempts', 'eklektik_past_successes', 'eklektik_past_failures',
            'eklektik_past_avg_success_rate', 'eklektik_days_since_last_success',
            'ooredoo_past_attempts', 'ooredoo_past_successes', 'ooredoo_past_failures',
            'ooredoo_past_avg_success_rate', 'ooredoo_days_since_last_success',
            'total_past_attempts', 'total_past_successes', 'total_past_revenue',
            'consecutive_failures_before', 'days_since_any_success',
            'operators_used_count', 'dominant_operator', 'engagement_trend',
            'had_recent_activity_7d', 'had_recent_success_7d',
            // Label (target)
            'had_success_next_30d'
        ];
        
        // Ouvrir le fichier CSV
        $fullPath = base_path($output);
        $dir = dirname($fullPath);
        
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        $fp = fopen($fullPath, 'w');
        
        if (!$fp) {
            $this->error("❌ Impossible de créer le fichier : {$fullPath}");
            return Command::FAILURE;
        }
        
        // Écrire l'en-tête
        fputcsv($fp, $features);
        
        // Exporter par chunks
        $bar = $this->output->createProgressBar($total);
        $bar->start();
        
        $exported = 0;
        
        DB::table('ml_training_samples')
            ->select($features)
            ->orderBy('feature_date')
            ->chunk($chunkSize, function ($rows) use ($fp, &$exported, $bar, $features) {
                foreach ($rows as $row) {
                    $rowData = [];
                    
                    foreach ($features as $feature) {
                        $value = $row->{$feature} ?? null;
                        
                        // Encoder les valeurs catégorielles
                        if ($feature == 'dominant_operator') {
                            $value = match($value) {
                                'timwe' => 1,
                                'eklektik' => 2,
                                'ooredoo' => 3,
                                default => 0
                            };
                        } elseif ($feature == 'engagement_trend') {
                            $value = match($value) {
                                'increasing' => 1,
                                'stable' => 0,
                                'decreasing' => -1,
                                default => 0
                            };
                        } elseif (is_bool($value)) {
                            $value = $value ? 1 : 0;
                        }
                        
                        $rowData[] = $value;
                    }
                    
                    fputcsv($fp, $rowData);
                    $exported++;
                    $bar->advance();
                }
            });
        
        $bar->finish();
        fclose($fp);
        
        $this->newLine(2);
        $this->info('✅ Export terminé !');
        $this->info("   • Fichier : {$output}");
        $this->info("   • Samples exportés : " . number_format($exported));
        $this->info("   • Taille : " . $this->formatBytes(filesize($fullPath)));
        
        $this->newLine();
        $this->info('💡 Prochaine étape :');
        $this->info("   python ml_models/train_model_v2.py --data={$output}");
        
        return Command::SUCCESS;
    }
    
    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' bytes';
    }
}
