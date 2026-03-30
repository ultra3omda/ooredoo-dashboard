<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\MLPredictionService;
use App\Services\MLPredictionServiceV2;
use App\Services\MLRecommendationService;
use App\Services\MLFeatureExtractionService;
use App\Services\MLABTestingService;
use App\Services\MLModelTrainingService;
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

    public function __construct(
        MLPredictionService $predictionService,
        MLPredictionServiceV2 $predictionServiceV2,
        MLRecommendationService $recommendationService,
        MLFeatureExtractionService $featureService,
        MLABTestingService $abTestingService,
        MLModelTrainingService $modelTrainingService
    ) {
        $this->predictionService = $predictionService;
        $this->predictionServiceV2 = $predictionServiceV2;
        $this->recommendationService = $recommendationService;
        $this->featureService = $featureService;
        $this->abTestingService = $abTestingService;
        $this->modelTrainingService = $modelTrainingService;
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
            $startDate = Carbon::parse($request->input('start_date'));
            $endDate = Carbon::parse($request->input('end_date'));
            
            $totalProcessed = 0;
            $currentDate = $startDate->copy();
            
            while ($currentDate->lte($endDate)) {
                $processedCount = $this->featureService->extractAndStoreFeaturesForDate($currentDate);
                $totalProcessed += $processedCount;
                $currentDate->addDay();
            }

            return response()->json([
                'success' => true,
                'message' => "Features extraites pour la période",
                'period' => "{$startDate->toDateString()} à {$endDate->toDateString()}",
                'total_processed' => $totalProcessed
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'extraction des features',
                'error' => $e->getMessage()
            ], 500);
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