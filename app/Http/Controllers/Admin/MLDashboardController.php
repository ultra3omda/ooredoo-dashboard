<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\MLPredictionService;
use App\Services\MLPredictionServiceV2;
use App\Services\MLRecommendationService;
use App\Services\MLFeatureExtractionService;
use App\Services\MLABTestingService;
use App\Services\MLModelTrainingService;
use App\Services\MLAsyncTaskService;
use App\Models\MLClientFeature;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MLDashboardController extends Controller
{
    private MLPredictionService $predictionService;
    private MLPredictionServiceV2 $predictionServiceV2;
    private MLRecommendationService $recommendationService;
    private MLFeatureExtractionService $featureService;
    private MLABTestingService $abTestingService;
    private MLModelTrainingService $modelTrainingService;
    private MLAsyncTaskService $asyncTaskService;

    public function __construct(
        MLPredictionService $predictionService,
        MLPredictionServiceV2 $predictionServiceV2,
        MLRecommendationService $recommendationService,
        MLFeatureExtractionService $featureService,
        MLABTestingService $abTestingService,
        MLModelTrainingService $modelTrainingService,
        MLAsyncTaskService $asyncTaskService
    ) {
        $this->predictionService = $predictionService;
        $this->predictionServiceV2 = $predictionServiceV2;
        $this->recommendationService = $recommendationService;
        $this->featureService = $featureService;
        $this->abTestingService = $abTestingService;
        $this->modelTrainingService = $modelTrainingService;
        $this->asyncTaskService = $asyncTaskService;
    }

    /**
     * Page principale du dashboard ML
     */
    public function index(): View
    {
        try {
            // Statistiques générales
            $portfolioStats = MLClientFeature::getPortfolioPerformance();
            $segmentStats = MLClientFeature::getSegmentStats();
            $recommendations = $this->recommendationService->getPriorityRecommendations(5);
            $predictions = $this->predictionService->getDashboardPredictions(10);
            
            // Données pour les graphiques
            $trendData = $this->getTrendData();
            
            // Real model metrics from trained model
            $modelMetrics = $this->loadModelMetrics();
            
            return view('admin.ml-dashboard', compact(
                'portfolioStats',
                'segmentStats', 
                'recommendations',
                'predictions',
                'trendData',
                'modelMetrics'
            ));

        } catch (\Exception $e) {
            \Log::error("MLDashboardController - Erreur chargement dashboard", [
                'error' => $e->getMessage()
            ]);
            
            return view('admin.ml-dashboard')->withErrors(['Erreur de chargement des données ML']);
        }
    }

    /**
     * API pour les données du dashboard
     */
    public function getDashboardData(Request $request): JsonResponse
    {
        try {
            $date = $request->get('date') ? Carbon::parse($request->get('date')) : null;
            
            $data = [
                'portfolio' => MLClientFeature::getPortfolioPerformance($date),
                'segments' => MLClientFeature::getSegmentStats($date),
                'recommendations' => $this->recommendationService->getPriorityRecommendations(10),
                'predictions' => $this->predictionService->getDashboardPredictions(20),
                'trends' => $this->getTrendData(30),
                'model_performance' => $this->getModelPerformanceAdvanced(),
                'ab_tests' => $this->getABTestsData(),
                'feature_importance' => $this->getFeatureImportance(),
                'data_quality' => $this->getDataQualityMetrics(),
                'operator_comparison' => $this->getOperatorComparison(),
                'offer_type_analysis' => $this->getOfferTypeAnalysis()
            ];

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des données',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Génère une nouvelle prédiction pour un client
     */
    public function predictClient(Request $request): JsonResponse
    {
        $request->validate([
            'client_id' => 'required|integer',
            'prediction_date' => 'nullable|date'
        ]);

        try {
            $clientId = $request->input('client_id');
            $predictionDate = $request->input('prediction_date') 
                ? Carbon::parse($request->input('prediction_date'))
                : Carbon::now();

            // Utiliser le nouveau service V2 avec A/B testing
            $prediction = $this->predictionServiceV2->predictPaymentSuccess($clientId, $predictionDate);

            return response()->json([
                'success' => true,
                'prediction' => $prediction
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la prédiction',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Génère de nouvelles recommandations
     */
    public function generateRecommendations(Request $request): JsonResponse
    {
        try {
            $analysisDate = $request->input('date') 
                ? Carbon::parse($request->input('date'))
                : Carbon::today();

            $recommendations = $this->recommendationService->generateRecommendations($analysisDate);

            return response()->json([
                'success' => true,
                'recommendations' => $recommendations,
                'generated_at' => $analysisDate->toDateTimeString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la génération des recommandations',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Met à jour le statut d'une recommandation
     */
    public function updateRecommendationStatus(Request $request): JsonResponse
    {
        $request->validate([
            'recommendation_id' => 'required|integer',
            'status' => 'required|in:pending,approved,implemented,rejected,expired'
        ]);

        try {
            DB::table('ml_recommendations')
                ->where('id', $request->input('recommendation_id'))
                ->update([
                    'status' => $request->input('status'),
                    'updated_at' => Carbon::now()
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Statut de la recommandation mis à jour'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Extrait les features pour une période
     */
    public function extractFeatures(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date'
        ]);

        try {
            $taskId = $this->asyncTaskService->startTask('extract_features', [
                'start_date' => $request->input('start_date'),
                'end_date' => $request->input('end_date'),
            ]);

            return response()->json([
                'success' => true,
                'message' => "Extraction lancée en arrière-plan",
                'task_id' => $taskId,
                'async' => true
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du lancement de l\'extraction',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Entraîner le modèle ML en arrière-plan
     */
    public function trainModel(Request $request): JsonResponse
    {
        try {
            $taskId = $this->asyncTaskService->startTask('train_model', [
                'model_name' => $request->input('model_name', 'lightgbm_v1'),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Entraînement lancé en arrière-plan',
                'task_id' => $taskId,
                'async' => true
            ]);

        } catch (\Exception $e) {
            Log::error("MLDashboard trainModel error: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du lancement de l\'entraînement',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Vérifier le statut d'une tâche async
     */
    public function getTaskStatus(Request $request): JsonResponse
    {
        $taskId = $request->input('task_id');
        if (!$taskId) {
            $extract = $this->asyncTaskService->getLatestTaskOfType('extract_features');
            $train = $this->asyncTaskService->getLatestTaskOfType('train_model');
            return response()->json([
                'success' => true,
                'extract_features' => $extract,
                'train_model' => $train,
            ]);
        }
        
        $status = $this->asyncTaskService->getTaskStatus($taskId);
        if (!$status) {
            return response()->json(['success' => false, 'message' => 'Tâche non trouvée'], 404);
        }
        return response()->json(['success' => true, 'task' => $status]);
    }

    /**
     * Insights ML rapides pour le widget Overview
     */
    public function getMLInsights(): JsonResponse
    {
        try {
            // Métriques du modèle depuis le fichier JSON
            $metricsFile = base_path('ml_models/model_metrics.json');
            $modelMetrics = file_exists($metricsFile) ? json_decode(file_get_contents($metricsFile), true) : null;
            
            // Clients à risque de churn (features récentes)
            $churnRiskCount = DB::table('ml_client_features')
                ->where('churn_probability', '>', 0.6)
                ->where('calculation_date', '>=', now()->subDays(60))
                ->distinct('client_id')
                ->count('client_id');
            
            // Taux de succès moyen des prédictions
            $avgSuccessRate = DB::table('ml_client_features')
                ->where('calculation_date', '>=', now()->subDays(60))
                ->where('payment_success_rate', '>', 0)
                ->avg('payment_success_rate') ?? 0;
            
            return response()->json([
                'success' => true,
                'accuracy' => $modelMetrics['accuracy'] ?? null,
                'f1_score' => $modelMetrics['f1'] ?? null,
                'churn_risk_count' => $churnRiskCount,
                'avg_success_rate' => round($avgSuccessRate * 100, 1),
                'trained_at' => $modelMetrics['trained_at'] ?? null,
                'samples_train' => $modelMetrics['samples_train'] ?? 0,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Lancer un test A/B pour valider les prédictions ML
     */
    public function startABTest(Request $request): JsonResponse
    {
        try {
            $config = [
                'name' => $request->input('name', 'ML Prediction Test'),
                'description' => $request->input('description', 'Test A/B pour valider les prédictions ML'),
                'target_group_size' => $request->input('target_group_size', 1000),
                'duration_days' => $request->input('duration_days', 14),
            ];
            
            $testId = $this->abTestingService->createMLRolloutTest($config);

            return response()->json([
                'success' => true,
                'message' => 'Test A/B lancé avec succès',
                'test_id' => $testId
            ]);

        } catch (\Exception $e) {
            Log::error("MLDashboard startABTest error: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du lancement du test A/B',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Générer un rapport IA hebdomadaire
     */
    public function generateReport(Request $request): JsonResponse
    {
        try {
            $taskId = $this->asyncTaskService->startTask('generate_report', [
                'type' => 'weekly',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Génération du rapport lancée en arrière-plan',
                'task_id' => $taskId,
                'async' => true
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du lancement du rapport',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupérer le dernier rapport IA généré
     */
    public function getLatestReport(): JsonResponse
    {
        try {
            $reportsDir = storage_path('app/ml_reports');
            $reports = glob($reportsDir . '/weekly_report_*.json');
            
            if (empty($reports)) {
                return response()->json([
                    'success' => true,
                    'report' => null,
                    'message' => 'Aucun rapport disponible'
                ]);
            }
            
            sort($reports);
            $latestFile = end($reports);
            $data = json_decode(file_get_contents($latestFile), true);
            
            return response()->json([
                'success' => true,
                'report' => $data['report'] ?? null,
                'generated_at' => $data['generated_at'] ?? null,
                'filename' => basename($latestFile)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Résultats d'un test A/B
     */
    public function getABTestResults(string $testId): JsonResponse
    {
        try {
            $results = $this->abTestingService->calculateTestResults($testId);
            return response()->json(['success' => true, 'results' => $results]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Terminer un test A/B
     */
    public function endABTest(string $testId): JsonResponse
    {
        try {
            $this->abTestingService->endTest($testId, 'Manual end from dashboard');
            return response()->json(['success' => true, 'message' => 'Test A/B terminé']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Données de tendance pour les graphiques
     */
    private function getTrendData(int $days = 30): array
    {
        $startDate = Carbon::now()->subDays($days);
        $endDate = Carbon::now();

        // Tendance du taux de succès global par jour
        $dailyTrends = DB::table('ml_client_features')
            ->whereBetween('calculation_date', [$startDate, $endDate])
            ->select(
                'calculation_date',
                DB::raw('AVG(payment_success_rate) * 100 as avg_success_rate'),
                DB::raw('COUNT(*) as total_clients'),
                DB::raw('AVG(churn_probability) * 100 as avg_churn_risk'),
                DB::raw('SUM(total_payments) as total_payments')
            )
            ->groupBy('calculation_date')
            ->orderBy('calculation_date')
            ->get();

        // Tendance par segment
        $segmentTrends = DB::table('ml_client_features')
            ->whereBetween('calculation_date', [$startDate, $endDate])
            ->select(
                'calculation_date',
                'client_segment',
                DB::raw('AVG(payment_success_rate) * 100 as avg_success_rate'),
                DB::raw('COUNT(*) as client_count')
            )
            ->groupBy('calculation_date', 'client_segment')
            ->orderBy('calculation_date')
            ->get()
            ->groupBy('client_segment');

        return [
            'daily_trends' => $dailyTrends->toArray(),
            'segment_trends' => $segmentTrends->toArray(),
            'period' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
                'days' => $days
            ]
        ];
    }

    /**
     * Performance des modèles ML
     */
    private function getModelPerformance(): array
    {
        // Pour l'instant, données simulées
        // À remplacer par de vraies métriques quand les modèles ML seront implémentés
        
        return [
            'payment_success_predictor' => [
                'model_name' => 'Payment Success Predictor',
                'version' => 'rule_based_v1.0',
                'accuracy' => 0.72,
                'precision' => 0.68,
                'recall' => 0.75,
                'f1_score' => 0.71,
                'last_evaluation' => Carbon::now()->subDays(1)->toDateString(),
                'status' => 'active'
            ],
            'timing_optimizer' => [
                'model_name' => 'Optimal Timing Predictor',
                'version' => 'v1.0',
                'accuracy' => 0.65,
                'precision' => 0.60,
                'recall' => 0.70,
                'f1_score' => 0.65,
                'last_evaluation' => Carbon::now()->subDays(1)->toDateString(),
                'status' => 'in_development'
            ],
            'churn_predictor' => [
                'model_name' => 'Churn Risk Classifier',
                'version' => 'v1.0',
                'accuracy' => 0.78,
                'precision' => 0.75,
                'recall' => 0.82,
                'f1_score' => 0.78,
                'last_evaluation' => Carbon::now()->subDays(1)->toDateString(),
                'status' => 'in_development'
            ]
        ];
    }

    /**
     * Détails d'un client spécifique
     */
    public function getClientDetails(Request $request): JsonResponse
    {
        $request->validate([
            'client_id' => 'required|integer'
        ]);

        try {
            $clientId = $request->input('client_id');
            
            // Features récentes
            $latestFeatures = MLClientFeature::getLatestForClient($clientId);
            
            // Tendances du client (3 derniers mois)
            $trends = MLClientFeature::getClientTrends(
                $clientId, 
                Carbon::now()->subMonths(3), 
                Carbon::now()
            );
            
            // Prédiction actuelle
            $prediction = $this->predictionService->predictPaymentSuccess($clientId);
            
            // Recommandations spécifiques au client
            $clientRecommendations = DB::table('ml_recommendations')
                ->where('client_id', $clientId)
                ->where('status', 'pending')
                ->orderBy('priority')
                ->get();

            return response()->json([
                'success' => true,
                'client' => [
                    'client_id' => $clientId,
                    'features' => $latestFeatures,
                    'trends' => $trends,
                    'prediction' => $prediction,
                    'recommendations' => $clientRecommendations
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des détails client',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Simulation d'impact des recommandations
     */
    public function simulateRecommendationImpact(Request $request): JsonResponse
    {
        $request->validate([
            'recommendation_id' => 'required|integer'
        ]);

        try {
            $recommendation = DB::table('ml_recommendations')
                ->find($request->input('recommendation_id'));

            if (!$recommendation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Recommandation non trouvée'
                ], 404);
            }

            // Simulation simplifiée de l'impact
            $simulation = [
                'recommendation_id' => $recommendation->id,
                'type' => $recommendation->recommendation_type,
                'expected_improvement' => $recommendation->expected_improvement_percentage,
                'current_metrics' => [
                    'success_rate' => 10.15, // Taux actuel
                    'monthly_revenue' => 15600, // TND
                    'active_clients' => 12814
                ],
                'projected_metrics' => [
                    'success_rate' => 10.15 * (1 + $recommendation->expected_improvement_percentage/100),
                    'monthly_revenue' => 15600 * (1 + $recommendation->expected_improvement_percentage/100),
                    'active_clients' => 12814 // Même nombre de clients
                ],
                'timeline' => [
                    'implementation_time' => '2-4 semaines',
                    'full_impact_time' => '2-3 mois',
                    'measurement_period' => '6 mois'
                ]
            ];

            return response()->json([
                'success' => true,
                'simulation' => $simulation
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la simulation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Load real model metrics from the trained model's JSON file
     */
    private function loadModelMetrics(): array
    {
        $metricsPath = base_path('ml_models/model_metrics.json');
        if (file_exists($metricsPath)) {
            $json = json_decode(file_get_contents($metricsPath), true);
            if ($json) return $json;
        }
        return [
            'accuracy' => 0, 'precision' => 0, 'recall' => 0, 'f1' => 0,
            'auc_roc' => 0, 'samples_train' => 0, 'samples_test' => 0,
            'feature_importance' => [], 'trained_at' => null
        ];
    }
}