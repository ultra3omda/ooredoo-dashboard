<?php

namespace App\Console\Commands;

use App\Services\MLPythonBridgeService;
use Illuminate\Console\Command;

/**
 * Lance l'entraînement du modèle LightGBM via le script Python Phase 3 (ml_models/train_model.py).
 * Nécessite au moins ~100 enregistrements dans ml_client_features.
 */
class TrainPythonModelCommand extends Command
{
    protected $signature = 'ml:train-python';

    protected $description = 'Entraîne le modèle LightGBM Phase 3 (script Python ml_models/train_model.py)';

    public function __construct(
        private MLPythonBridgeService $mlBridge
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('🤖 Entraînement du modèle ML (Phase 3 - LightGBM via Python)...');

        $count = \DB::table('ml_client_features')->count();
        if ($count < 50) {
            $this->error("❌ Pas assez de features ML ({$count}). Lancez d’abord: php artisan ml:extract-features --start-date=... --end-date=...");

            return Command::FAILURE;
        }
        $this->info("✅ Features ML en base: " . number_format($count));

        try {
            $result = $this->mlBridge->trainNewModel();
            $this->info('✅ Entraînement terminé avec succès.');
            $this->line($result['output'] ?? '');

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('❌ Erreur: ' . $e->getMessage());
            $this->line('💡 Vérifiez: Python installé, pip install -r ml_models/requirements.txt, .env (DB_*) pour le script Python.');

            return Command::FAILURE;
        }
    }
}
