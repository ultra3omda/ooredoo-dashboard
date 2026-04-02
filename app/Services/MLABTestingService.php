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
    public function assignToGroup(int $clientId, string $testId): string
    {
        $hash = crc32($clientId . '_' . $testId);
        $group = ($hash % 100) < 50 ? 'control' : 'treatment';
        
        $existing = DB::table('ml_ab_test_participants')
            ->where('test_id', $testId)
            ->where('client_id', $clientId)
            ->first();
            
        if ($existing) {
            return $existing->test_group;
        }
        
        DB::table('ml_ab_test_participants')->insert([
            'test_id' => $testId,
            'client_id' => $clientId,
            'test_group' => $group,
            'assigned_at' => now(),
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        return $group;
    }

    /**
     * Détermine si un client doit utiliser la prédiction ML (vs rule-based)
     */
    public function shouldUseMLPrediction(int $clientId): bool
    {
        $test = DB::table('ml_ab_tests')
            ->where('status', 'running')
            ->where('test_name', 'ml_prediction_rollout')
            ->first();
            
        if (!$test) {
            return false;
        }
        
        $group = $this->assignToGroup($clientId, $test->test_id);
        return $group === 'treatment';
    }

    /**
     * Crée un nouveau test A/B pour le rollout ML
     */
    public function createMLRolloutTest(array $config): string
    {
        $testId = 'ml_rollout_' . date('Ymd_His');
        $durationDays = $config['duration_days'] ?? 14;
        
        // Terminer les tests existants du même type
        DB::table('ml_ab_tests')
            ->where('test_name', 'ml_prediction_rollout')
            ->where('status', 'running')
            ->update([
                'status' => 'stopped',
                'end_date' => now()->toDateString(),
                'end_reason' => 'Remplacé par nouveau test',
                'updated_at' => now()
            ]);
        
        DB::table('ml_ab_tests')->insert([
            'test_id' => $testId,
            'test_name' => $config['name'] ?? 'ml_prediction_rollout',
            'test_description' => $config['description'] ?? 'Test A/B pour déploiement modèle ML vs rule-based',
            'status' => 'running',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays($durationDays)->toDateString(),
            'total_participants' => $config['target_group_size'] ?? 1000,
            'traffic_allocation' => 0.5000,
            'control_strategy' => json_encode(['type' => 'rule_based', 'version' => 'v1.0']),
            'treatment_strategy' => json_encode(['type' => 'lightgbm', 'version' => 'v1']),
            'primary_metric' => 'billing_success_rate',
            'secondary_metrics' => json_encode(['revenue_per_user', 'churn_rate']),
            'minimum_detectable_effect' => 0.0200,
            'significance_level' => 0.0500,
            'current_participants' => 0,
            'current_lift' => 0,
            'is_significant' => false,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        Log::info("MLABTestingService - Nouveau test A/B créé", [
            'test_id' => $testId,
            'duration_days' => $durationDays
        ]);
        
        return $testId;
    }

    /**
     * Enregistre le résultat d'une prédiction pour l'A/B testing
     */
    public function recordPredictionOutcome(int $clientId, string $testId, string $group, array $outcome): void
    {
        DB::table('ml_ab_test_participants')
            ->where('test_id', $testId)
            ->where('client_id', $clientId)
            ->update([
                'outcome_success' => $outcome['success'] ?? null,
                'outcome_amount' => $outcome['amount'] ?? null,
                'outcome_date' => now(),
                'outcome_details' => json_encode($outcome),
                'updated_at' => now()
            ]);
    }

    /**
     * Calcule les résultats d'un test A/B
     */
    public function calculateTestResults(string $testId): array
    {
        $test = DB::table('ml_ab_tests')->where('test_id', $testId)->first();
        
        if (!$test) {
            throw new Exception("Test A/B non trouvé: $testId");
        }
        
        $controlResults = DB::table('ml_ab_test_participants')
            ->where('test_id', $testId)
            ->where('test_group', 'control')
            ->whereNotNull('outcome_success')
            ->select(
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(outcome_success) as successes'),
                DB::raw('AVG(outcome_amount) as avg_amount')
            )
            ->first();
            
        $treatmentResults = DB::table('ml_ab_test_participants')
            ->where('test_id', $testId)
            ->where('test_group', 'treatment')
            ->whereNotNull('outcome_success')
            ->select(
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(outcome_success) as successes'),
                DB::raw('AVG(outcome_amount) as avg_amount')
            )
            ->first();
        
        $controlRate = $controlResults->total > 0 ? $controlResults->successes / $controlResults->total : 0;
        $treatmentRate = $treatmentResults->total > 0 ? $treatmentResults->successes / $treatmentResults->total : 0;
        
        $lift = $controlRate > 0 ? ($treatmentRate - $controlRate) / $controlRate : 0;
        
        // Mise à jour du test avec les résultats
        DB::table('ml_ab_tests')->where('test_id', $testId)->update([
            'current_participants' => ($controlResults->total ?? 0) + ($treatmentResults->total ?? 0),
            'current_lift' => round($lift, 4),
            'updated_at' => now()
        ]);
        
        return [
            'test_id' => $testId,
            'test_name' => $test->test_name,
            'status' => $test->status,
            'start_date' => $test->start_date,
            'end_date' => $test->end_date,
            'control' => [
                'total' => $controlResults->total ?? 0,
                'successes' => $controlResults->successes ?? 0,
                'success_rate' => round($controlRate * 100, 2),
                'avg_amount' => round($controlResults->avg_amount ?? 0, 2)
            ],
            'treatment' => [
                'total' => $treatmentResults->total ?? 0,
                'successes' => $treatmentResults->successes ?? 0,
                'success_rate' => round($treatmentRate * 100, 2),
                'avg_amount' => round($treatmentResults->avg_amount ?? 0, 2)
            ],
            'lift' => round($lift * 100, 2),
            'is_significant' => $test->is_significant ?? false,
            'total_participants' => ($controlResults->total ?? 0) + ($treatmentResults->total ?? 0)
        ];
    }

    /**
     * Termine un test A/B
     */
    public function endTest(string $testId, string $reason = null): bool
    {
        return DB::table('ml_ab_tests')
            ->where('test_id', $testId)
            ->update([
                'status' => 'completed',
                'end_date' => now()->toDateString(),
                'end_reason' => $reason ?? 'Terminé manuellement',
                'updated_at' => now()
            ]) > 0;
    }

    /**
     * Liste des tests A/B actifs
     */
    public function getActiveTests(): array
    {
        return DB::table('ml_ab_tests')
            ->where('status', 'running')
            ->orderBy('start_date', 'desc')
            ->get()
            ->toArray();
    }
}
