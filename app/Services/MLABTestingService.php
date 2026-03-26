<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class MLABTestingService
{
    /**
     * Assigne un client à un groupe de test A/B de manière déterministe
     */
    public function assignToGroup(int $clientId, int $testId): string
    {
        // Hash stable basé sur client_id + test_id pour assignation déterministe
        $hash = crc32($clientId . '_' . $testId);
        $group = ($hash % 100) < 50 ? 'control' : 'treatment';
        
        // Vérifier si le client est déjà assigné
        $existing = DB::table('ml_ab_test_participants')
            ->where('test_id', $testId)
            ->where('client_id', $clientId)
            ->first();
            
        if ($existing) {
            return $existing->group;
        }
        
        // Enregistrer l'assignation
        DB::table('ml_ab_test_participants')->insert([
            'test_id' => $testId,
            'client_id' => $clientId,
            'group' => $group,
            'assigned_at' => now(),
            'metadata' => json_encode(['hash' => $hash]),
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        Log::info("MLABTestingService - Client $clientId assigné au groupe $group pour test $testId");
        
        return $group;
    }

    /**
     * Détermine si un client doit utiliser la prédiction ML (vs rule-based)
     */
    public function shouldUseMLPrediction(int $clientId): bool
    {
        // Récupérer le test A/B actif pour les prédictions ML
        $test = DB::table('ml_ab_tests')
            ->where('status', 'active')
            ->where('test_name', 'ml_prediction_rollout')
            ->first();
            
        if (!$test) {
            return false; // Pas de test actif = utiliser rule-based
        }
        
        $group = $this->assignToGroup($clientId, $test->id);
        
        return $group === 'treatment'; // Treatment = ML, Control = rule-based
    }

    /**
     * Crée un nouveau test A/B pour le rollout ML
     */
    public function createMLRolloutTest(array $config): int
    {
        $testConfig = array_merge([
            'test_name' => 'ml_prediction_rollout',
            'description' => 'Test A/B pour déploiement modèle ML vs rule-based',
            'target_participants' => 1000,
            'duration_days' => 14,
            'treatment_percentage' => 50,
            'success_metric' => 'billing_success_rate',
            'minimum_effect_size' => 0.02 // 2 points de pourcentage minimum
        ], $config);
        
        // Terminer les tests existants du même type
        DB::table('ml_ab_tests')
            ->where('test_name', 'ml_prediction_rollout')
            ->where('status', 'active')
            ->update(['status' => 'terminated', 'ended_at' => now()]);
        
        $testId = DB::table('ml_ab_tests')->insertGetId([
            'test_name' => $testConfig['test_name'],
            'description' => $testConfig['description'],
            'status' => 'active',
            'control_strategy' => 'rule_based_v1.0',
            'treatment_strategy' => 'lightgbm_v1',
            'target_participants' => $testConfig['target_participants'],
            'duration_days' => $testConfig['duration_days'],
            'success_metric' => $testConfig['success_metric'],
            'minimum_effect_size' => $testConfig['minimum_effect_size'],
            'treatment_percentage' => $testConfig['treatment_percentage'],
            'started_at' => now(),
            'expected_end_at' => now()->addDays($testConfig['duration_days']),
            'created_by' => auth()->id() ?? 1,
            'test_config' => json_encode($testConfig),
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        Log::info("MLABTestingService - Nouveau test A/B créé", [
            'test_id' => $testId,
            'test_name' => $testConfig['test_name'],
            'duration_days' => $testConfig['duration_days'],
            'target_participants' => $testConfig['target_participants']
        ]);
        
        return $testId;
    }

    /**
     * Enregistre le résultat d'une prédiction pour l'A/B testing
     */
    public function recordPredictionOutcome(int $clientId, int $testId, string $group, array $outcome): void
    {
        DB::table('ml_ab_test_participants')
            ->where('test_id', $testId)
            ->where('client_id', $clientId)
            ->update([
                'outcome_recorded_at' => now(),
                'outcome_data' => json_encode($outcome),
                'updated_at' => now()
            ]);
            
        // Mise à jour des statistiques du test
        $this->updateTestStatistics($testId);
    }

    /**
     * Calcule les statistiques du test A/B
     */
    public function calculateTestResults(int $testId): array
    {
        $test = DB::table('ml_ab_tests')->find($testId);
        if (!$test) {
            throw new Exception("Test $testId non trouvé");
        }
        
        // Récupérer les résultats par groupe
        $results = DB::table('ml_ab_test_participants')
            ->select('group')
            ->selectRaw('COUNT(*) as participants')
            ->selectRaw('AVG(CASE WHEN outcome_data IS NOT NULL THEN 1 ELSE 0 END) as completion_rate')
            ->selectRaw('AVG(JSON_EXTRACT(outcome_data, "$.success")) as success_rate')
            ->selectRaw('AVG(JSON_EXTRACT(outcome_data, "$.confidence")) as avg_confidence')
            ->where('test_id', $testId)
            ->groupBy('group')
            ->get()
            ->keyBy('group');
            
        $control = $results['control'] ?? null;
        $treatment = $results['treatment'] ?? null;
        
        $lift = 0;
        $significance = false;
        
        if ($control && $treatment && $control->success_rate && $treatment->success_rate) {
            $lift = ($treatment->success_rate - $control->success_rate) / $control->success_rate;
            
            // Test de significativité (approximation)
            $minParticipants = max($control->participants, $treatment->participants);
            $significance = $minParticipants >= 100 && abs($lift) >= $test->minimum_effect_size;
        }
        
        return [
            'test_id' => $testId,
            'test_name' => $test->test_name,
            'status' => $test->status,
            'duration_days' => $test->duration_days,
            'control' => [
                'participants' => $control->participants ?? 0,
                'completion_rate' => round(($control->completion_rate ?? 0) * 100, 1),
                'success_rate' => round(($control->success_rate ?? 0) * 100, 1),
                'avg_confidence' => round(($control->avg_confidence ?? 0) * 100, 1)
            ],
            'treatment' => [
                'participants' => $treatment->participants ?? 0,
                'completion_rate' => round(($treatment->completion_rate ?? 0) * 100, 1),
                'success_rate' => round(($treatment->success_rate ?? 0) * 100, 1),
                'avg_confidence' => round(($treatment->avg_confidence ?? 0) * 100, 1)
            ],
            'lift' => round($lift * 100, 1), // En pourcentage
            'is_significant' => $significance,
            'recommendation' => $this->getTestRecommendation($lift, $significance, $test)
        ];
    }

    /**
     * Génère une recommandation basée sur les résultats du test
     */
    private function getTestRecommendation(float $lift, bool $significant, object $test): string
    {
        if (!$significant) {
            return 'Continuer le test - pas assez de données pour une décision';
        }
        
        if ($lift > 0.1) { // >10% d'amélioration
            return 'Déployer le modèle ML - amélioration significative détectée';
        } elseif ($lift < -0.05) { // >5% de dégradation
            return 'Arrêter le test - dégradation significative détectée';
        } else {
            return 'Performance similaire - analyser les autres métriques';
        }
    }

    /**
     * Met à jour les statistiques du test
     */
    private function updateTestStatistics(int $testId): void
    {
        $stats = $this->calculateTestResults($testId);
        
        DB::table('ml_ab_tests')
            ->where('id', $testId)
            ->update([
                'current_participants' => ($stats['control']['participants'] ?? 0) + ($stats['treatment']['participants'] ?? 0),
                'current_lift' => $stats['lift'] / 100, // Stocker en décimal
                'is_significant' => $stats['is_significant'],
                'updated_at' => now()
            ]);
    }

    /**
     * Récupère tous les tests actifs
     */
    public function getActiveTests(): array
    {
        return DB::table('ml_ab_tests')
            ->where('status', 'active')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function($test) {
                return $this->calculateTestResults($test->id);
            })
            ->toArray();
    }

    /**
     * Termine un test A/B
     */
    public function endTest(int $testId, string $reason = null): bool
    {
        $updated = DB::table('ml_ab_tests')
            ->where('id', $testId)
            ->update([
                'status' => 'completed',
                'ended_at' => now(),
                'end_reason' => $reason,
                'updated_at' => now()
            ]);
            
        if ($updated) {
            Log::info("MLABTestingService - Test $testId terminé", ['reason' => $reason]);
        }
        
        return $updated > 0;
    }
}