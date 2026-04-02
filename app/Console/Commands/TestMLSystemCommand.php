<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\MLFeatureExtractionService;
use App\Services\MLPredictionService;
use App\Services\MLRecommendationService;
use App\Models\MLClientFeature;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TestMLSystemCommand extends Command
{
    protected $signature = 'ml:test-system {--client-id= : ID d\'un client spécifique à tester}';
    protected $description = 'Teste le système ML complet avec des données réelles';

    private MLFeatureExtractionService $featureService;
    private MLPredictionService $predictionService;  
    private MLRecommendationService $recommendationService;

    public function __construct(
        MLFeatureExtractionService $featureService,
        MLPredictionService $predictionService,
        MLRecommendationService $recommendationService
    ) {
        parent::__construct();
        $this->featureService = $featureService;
        $this->predictionService = $predictionService;
        $this->recommendationService = $recommendationService;
    }

    public function handle()
    {
        $this->info('🧪 Test du système ML Timwe...');
        
        // 1. Vérifier les données de base
        $this->checkDataAvailability();
        
        // 2. Test d'extraction de features
        $this->testFeatureExtraction();
        
        // 3. Test de prédiction
        $this->testPredictions();
        
        // 4. Test de recommandations
        $this->testRecommendations();
        
        // 5. Test complet sur un client
        if ($this->option('client-id')) {
            $this->testSpecificClient($this->option('client-id'));
        } else {
            $this->testRandomClient();
        }
        
        $this->info('✅ Tests terminés !');
        
        return 0;
    }

    private function checkDataAvailability()
    {
        $this->info("\n📊 1. Vérification des données disponibles...");
        
        // Vérifier les transactions Timwe
        $timweTransactions = DB::table('transactions_history')
            ->where('status', 'LIKE', '%TIMWE_%')
            ->count();
        
        $this->line("   Transactions Timwe totales: " . number_format($timweTransactions));
        
        // Vérifier les clients avec abonnements Timwe
        $timweOperatorIds = DB::table('country_payments_methods')
            ->whereRaw("TRIM(country_payments_methods_name) LIKE '%timwe%'")
            ->pluck('country_payments_methods_id');
        
        $timweClients = DB::table('client_abonnement')
            ->whereIn('country_payments_methods_id', $timweOperatorIds)
            ->distinct('client_id')
            ->count();
        
        $this->line("   Clients Timwe: " . number_format($timweClients));
        
        // Vérifier les features ML
        $featuresCount = DB::table('ml_client_features')->count();
        $this->line("   Features ML extraites: " . number_format($featuresCount));
        
        if ($featuresCount == 0) {
            $this->warn("   ⚠️  Aucune feature ML trouvée. L'extraction est-elle terminée ?");
        }
        
        // Dates de données disponibles
        $dateRange = DB::table('transactions_history')
            ->where('status', 'LIKE', '%TIMWE_%')
            ->selectRaw('MIN(created_at) as min_date, MAX(created_at) as max_date')
            ->first();
        
        $this->line("   Période des données: {$dateRange->min_date} → {$dateRange->max_date}");
    }

    private function testFeatureExtraction()
    {
        $this->info("\n🔧 2. Test d'extraction de features...");
        
        try {
            // Test sur une date récente
            $testDate = Carbon::yesterday();
            $this->line("   Test d'extraction pour le {$testDate->toDateString()}");
            
            $extractedCount = $this->featureService->extractAndStoreFeaturesForDate($testDate);
            
            $this->line("   ✅ Features extraites pour $extractedCount clients");
            
            // Vérifier la qualité des features
            if ($extractedCount > 0) {
                $sample = DB::table('ml_client_features')
                    ->where('calculation_date', $testDate)
                    ->first();
                
                $this->line("   📋 Exemple de features:");
                $this->line("      - Taux succès: " . round($sample->payment_success_rate * 100, 2) . "%");
                $this->line("      - Échecs consécutifs: $sample->consecutive_failures");
                $this->line("      - Segment: $sample->client_segment");
                $this->line("      - Score fiabilité: " . round($sample->payment_reliability_score * 100, 2) . "%");
            }
            
        } catch (\Exception $e) {
            $this->error("   ❌ Erreur extraction: " . $e->getMessage());
        }
    }

    private function testPredictions()
    {
        $this->info("\n🔮 3. Test de prédictions...");
        
        try {
            // Trouver un client avec des features
            $testClient = DB::table('ml_client_features')
                ->orderBy('id', 'desc')
                ->first();
            
            if (!$testClient) {
                $this->warn("   ⚠️  Aucune feature disponible pour tester les prédictions");
                return;
            }
            
            $this->line("   Test prédiction pour client {$testClient->client_id}");
            
            $prediction = $this->predictionService->predictPaymentSuccess($testClient->client_id);
            
            $this->line("   📈 Résultats de prédiction:");
            $this->line("      - Probabilité succès: " . round($prediction['payment_success_probability'] * 100, 2) . "%");
            $this->line("      - Confiance: " . round($prediction['success_confidence'] * 100, 2) . "%");
            $this->line("      - Prix optimal: {$prediction['optimal_price']} TND");
            $this->line("      - Fréquence optimale: {$prediction['optimal_frequency']}");
            $this->line("      - Timing optimal: {$prediction['optimal_billing_time']}");
            $this->line("      - Segment: {$prediction['client_segment']}");
            
            // Test prédictions en batch
            $sampleClients = DB::table('ml_client_features')
                ->distinct('client_id')
                ->limit(5)
                ->pluck('client_id')
                ->toArray();
            
            if (!empty($sampleClients)) {
                $this->line("   Test prédictions en batch pour " . count($sampleClients) . " clients...");
                $batchPredictions = $this->predictionService->batchPredictPaymentSuccess($sampleClients);
                $avgSuccess = collect($batchPredictions)->avg('payment_success_probability');
                $this->line("      - Probabilité moyenne: " . round($avgSuccess * 100, 2) . "%");
            }
            
        } catch (\Exception $e) {
            $this->error("   ❌ Erreur prédiction: " . $e->getMessage());
        }
    }

    private function testRecommendations()
    {
        $this->info("\n💡 4. Test de recommandations...");
        
        try {
            $recommendations = $this->recommendationService->generateRecommendations();
            
            $totalRecs = 0;
            foreach ($recommendations as $category => $categoryRecs) {
                $count = count($categoryRecs);
                $totalRecs += $count;
                $this->line("   📋 $category: $count recommandation(s)");
                
                if ($count > 0) {
                    $firstRec = $categoryRecs[0];
                    $this->line("      Ex: {$firstRec['recommended_strategy']} (+{$firstRec['expected_impact_percentage']}%)");
                }
            }
            
            $this->line("   ✅ Total: $totalRecs recommandations générées");
            
            // Test récupération des recommandations prioritaires
            $priorityRecs = $this->recommendationService->getPriorityRecommendations(3);
            $this->line("   🎯 Recommandations prioritaires: " . count($priorityRecs['recommendations']));
            
        } catch (\Exception $e) {
            $this->error("   ❌ Erreur recommandations: " . $e->getMessage());
        }
    }

    private function testRandomClient()
    {
        $this->info("\n👤 5. Test complet sur un client aléatoire...");
        
        // Trouver un client avec des données intéressantes
        $testClient = DB::table('ml_client_features as f')
            ->join('client as c', 'f.client_id', '=', 'c.client_id')
            ->where('f.total_attempts', '>', 5) // Client avec historique
            ->orderBy('f.calculation_date', 'desc')
            ->select('f.client_id', 'c.client_telephone', 'f.*')
            ->first();
        
        if (!$testClient) {
            $this->warn("   ⚠️  Aucun client avec historique trouvé");
            return;
        }
        
        $this->testSpecificClient($testClient->client_id, $testClient);
    }

    private function testSpecificClient($clientId, $clientData = null)
    {
        $this->info("\n🔍 Test complet pour client $clientId...");
        
        if (!$clientData) {
            $clientData = DB::table('ml_client_features as f')
                ->join('client as c', 'f.client_id', '=', 'c.client_id')
                ->where('f.client_id', $clientId)
                ->orderBy('f.calculation_date', 'desc')
                ->select('f.*', 'c.client_telephone')
                ->first();
        }
        
        if (!$clientData) {
            $this->error("   ❌ Client $clientId non trouvé dans les features ML");
            return;
        }
        
        $this->line("   📞 Téléphone: {$clientData->client_telephone}");
        $this->line("   📊 Profil client:");
        $this->line("      - Segment: {$clientData->client_segment}");
        $this->line("      - Taux succès historique: " . round($clientData->payment_success_rate * 100, 2) . "%");
        $this->line("      - Total tentatives: {$clientData->total_attempts}");
        $this->line("      - Total paiements: {$clientData->total_payments}");
        $this->line("      - Échecs consécutifs: {$clientData->consecutive_failures}");
        $this->line("      - Risque churn: " . round($clientData->churn_probability * 100, 2) . "%");
        $this->line("      - Client de valeur: " . ($clientData->is_high_value_client ? 'Oui' : 'Non'));
        
        // Générer une prédiction
        try {
            $prediction = $this->predictionService->predictPaymentSuccess($clientId);
            
            $this->line("   🔮 Prédiction ML:");
            $this->line("      - Probabilité succès: " . round($prediction['payment_success_probability'] * 100, 2) . "%");
            $this->line("      - Confiance: " . round($prediction['success_confidence'] * 100, 2) . "%");
            $this->line("      - Stratégie recommandée:");
            $this->line("        • Prix: {$prediction['optimal_price']} TND");
            $this->line("        • Fréquence: {$prediction['optimal_frequency']}");
            $this->line("        • Timing: {$prediction['optimal_billing_time']}");
            
            // Analyser l'amélioration potentielle
            $currentRate = $clientData->payment_success_rate;
            $predictedRate = $prediction['payment_success_probability'];
            $improvement = (($predictedRate - $currentRate) / $currentRate) * 100;
            
            if ($improvement > 0) {
                $this->line("      🚀 Amélioration attendue: +" . round($improvement, 1) . "%");
            } else {
                $this->line("      ⚠️  Prédiction conservatrice: " . round($improvement, 1) . "%");
            }
            
        } catch (\Exception $e) {
            $this->error("   ❌ Erreur prédiction client: " . $e->getMessage());
        }
    }
}