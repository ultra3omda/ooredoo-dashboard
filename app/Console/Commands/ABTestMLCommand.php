<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\MLABTestingService;

class ABTestMLCommand extends Command
{
    protected $signature = 'ml:ab-test {--test-id= : ID du test à analyser}
                                       {--list : Lister tous les tests}
                                       {--end= : Terminer un test}
                                       {--create : Créer un nouveau test}';

    protected $description = 'Gère les tests A/B pour les modèles ML';

    private MLABTestingService $abTestingService;

    public function __construct(MLABTestingService $abTestingService)
    {
        parent::__construct();
        $this->abTestingService = $abTestingService;
    }

    public function handle()
    {
        if ($this->option('list')) {
            $this->listTests();
        } elseif ($this->option('test-id')) {
            $this->showTestResults($this->option('test-id'));
        } elseif ($this->option('end')) {
            $this->endTest($this->option('end'));
        } elseif ($this->option('create')) {
            $this->createTest();
        } else {
            $this->showHelp();
        }
        
        return Command::SUCCESS;
    }

    private function listTests(): void
    {
        $this->info('📋 Tests A/B actifs:');
        
        $tests = $this->abTestingService->getActiveTests();
        
        if (empty($tests)) {
            $this->warn('Aucun test A/B actif');
            return;
        }
        
        $tableData = [];
        foreach ($tests as $test) {
            $tableData[] = [
                $test['test_id'],
                $test['test_name'],
                $test['control']['participants'] + $test['treatment']['participants'],
                $test['lift'] . '%',
                $test['is_significant'] ? 'Oui ✅' : 'Non ⏳',
                $test['recommendation']
            ];
        }
        
        $this->table(['ID', 'Nom', 'Participants', 'Lift', 'Significatif', 'Recommandation'], $tableData);
    }

    private function showTestResults(int $testId): void
    {
        $this->info("📊 Résultats du test A/B #$testId");
        
        $results = $this->abTestingService->calculateTestResults($testId);
        
        $this->table(['Métrique', 'Contrôle (Rule-based)', 'Traitement (ML)'], [
            ['Participants', $results['control']['participants'], $results['treatment']['participants']],
            ['Taux complétion', $results['control']['completion_rate'] . '%', $results['treatment']['completion_rate'] . '%'],
            ['Taux succès', $results['control']['success_rate'] . '%', $results['treatment']['success_rate'] . '%'],
            ['Confiance moy.', $results['control']['avg_confidence'] . '%', $results['treatment']['avg_confidence'] . '%']
        ]);
        
        $this->newLine();
        $this->info("📈 Lift: {$results['lift']}%");
        $this->info("🎯 Significatif: " . ($results['is_significant'] ? 'Oui ✅' : 'Non ⏳'));
        $this->info("💡 Recommandation: {$results['recommendation']}");
    }

    private function endTest(int $testId): void
    {
        $reason = $this->ask('Raison de fin de test?', 'Terminé manuellement');
        
        if ($this->abTestingService->endTest($testId, $reason)) {
            $this->info("✅ Test #$testId terminé");
        } else {
            $this->error("❌ Impossible de terminer le test #$testId");
        }
    }

    private function createTest(): void
    {
        $this->info('🧪 Création d\'un nouveau test A/B...');
        
        $name = $this->ask('Nom du test?', 'ml_experiment_' . date('Y_m_d'));
        $description = $this->ask('Description?', 'Test A/B modèle ML vs baseline');
        $participants = $this->ask('Nombre de participants?', '1000');
        $duration = $this->ask('Durée en jours?', '14');
        
        $config = [
            'test_name' => $name,
            'description' => $description,
            'target_participants' => (int)$participants,
            'duration_days' => (int)$duration,
            'treatment_percentage' => 50
        ];
        
        $testId = $this->abTestingService->createMLRolloutTest($config);
        
        $this->info("✅ Test créé avec l'ID: $testId");
    }

    private function showHelp(): void
    {
        $this->info('📚 Utilisation des tests A/B ML:');
        $this->line('');
        $this->line('  Lister les tests actifs:');
        $this->line('    php artisan ml:ab-test --list');
        $this->line('');
        $this->line('  Voir les résultats d\'un test:');
        $this->line('    php artisan ml:ab-test --test-id=1');
        $this->line('');
        $this->line('  Créer un nouveau test:');
        $this->line('    php artisan ml:ab-test --create');
        $this->line('');
        $this->line('  Terminer un test:');
        $this->line('    php artisan ml:ab-test --end=1');
    }
}