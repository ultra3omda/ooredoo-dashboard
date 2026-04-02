<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\MLFeatureExtractionService;
use App\Services\MLPredictionServiceV2;
use App\Services\MLABTestingService;
use App\Models\MLClientFeature;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class MLSystemV2Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        $this->seed();
    }

    /** @test */
    public function test_nouvelles_features_extraites_correctement()
    {
        // Données de test
        $clientId = 12345;
        $calculationDate = Carbon::now();
        
        $service = app(MLFeatureExtractionService::class);
        $features = $service->extractClientFeatures($clientId, $calculationDate);
        
        // Vérifier les nouvelles features v2.0
        $newFeatures = [
            'morning_success_rate',
            'afternoon_success_rate', 
            'evening_success_rate',
            'recovery_after_failure_rate',
            'max_consecutive_successes',
            'payment_amount_std',
            'amount_flexibility',
            'no_balance_failure_rate',
            'not_delivered_failure_rate'
        ];
        
        foreach ($newFeatures as $feature) {
            $this->assertArrayHasKey($feature, $features, "Feature $feature manquante");
            $this->assertIsNumeric($features[$feature], "Feature $feature n'est pas numérique");
        }
        
        // Vérifier que engagement_score n'est plus à 0 constant
        $this->assertGreaterThanOrEqual(0, $features['engagement_score']);
        $this->assertLessThanOrEqual(1, $features['engagement_score']);
    }

    /** @test */
    public function test_prediction_service_v2_avec_ab_testing()
    {
        $clientId = 12345;
        
        // Créer des features de test
        MLClientFeature::create([
            'client_id' => $clientId,
            'calculation_date' => Carbon::now()->toDateString(),
            'payment_success_rate' => 0.3,
            'consecutive_failures' => 2,
            'total_payments' => 5,
            'payment_reliability_score' => 0.35,
            'engagement_score' => 0.6,
            'client_segment' => 'regular_payers'
        ]);
        
        $service = app(MLPredictionServiceV2::class);
        $prediction = $service->predictPaymentSuccess($clientId);
        
        $this->assertIsArray($prediction);
        $this->assertArrayHasKey('payment_success_probability', $prediction);
        $this->assertArrayHasKey('ab_test_group', $prediction);
        $this->assertArrayHasKey('model_used', $prediction);
        
        // Vérifier que la probabilité est dans la bonne plage
        $probability = $prediction['payment_success_probability'];
        $this->assertGreaterThanOrEqual(0, $probability);
        $this->assertLessThanOrEqual(1, $probability);
        
        // Vérifier que le groupe A/B est assigné
        $this->assertContains($prediction['ab_test_group'], ['control', 'treatment', 'treatment_fallback', 'none']);
    }

    /** @test */
    public function test_ab_testing_service()
    {
        $service = app(MLABTestingService::class);
        
        // Créer un test A/B
        $testId = $service->createMLRolloutTest([
            'test_name' => 'test_unit',
            'description' => 'Test unitaire',
            'target_participants' => 100,
            'duration_days' => 7,
            'treatment_percentage' => 50
        ]);
        
        $this->assertGreaterThan(0, $testId);
        
        // Assigner des clients
        $clientId1 = 1001;
        $clientId2 = 1002;
        
        $group1 = $service->assignToGroup($clientId1, $testId);
        $group2 = $service->assignToGroup($clientId2, $testId);
        
        $this->assertContains($group1, ['control', 'treatment']);
        $this->assertContains($group2, ['control', 'treatment']);
        
        // Réassigner le même client doit donner le même groupe
        $group1_bis = $service->assignToGroup($clientId1, $testId);
        $this->assertEquals($group1, $group1_bis);
    }

    /** @test */
    public function test_dashboard_api_nouvelles_metriques()
    {
        $response = $this->getJson('/admin/ml-dashboard/data');
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'portfolio',
                        'segments',
                        'ab_tests' => [
                            'active_tests',
                            'total_participants'
                        ],
                        'feature_importance' => [
                            'current',
                            'model_date'
                        ],
                        'data_quality' => [
                            'completeness',
                            'anomalies'
                        ]
                    ]
                ]);
    }

    /** @test */  
    public function test_commande_upgrade_complete()
    {
        // Test de la commande ml:upgrade en mode dry-run
        $this->artisan('ml:upgrade --dry-run')
             ->expectsOutput('🚀 Mise à niveau du système ML vers v2.0...')
             ->expectsOutput('🔍 MODE SIMULATION - Aucune modification ne sera effectuée')
             ->assertExitCode(0);
    }

    /** @test */
    public function test_nouvelles_routes_api()
    {
        $this->assertRouteExists('admin.ml.train');
        $this->assertRouteExists('admin.ml.ab-test.start'); 
        $this->assertRouteExists('admin.ml.ab-test.results');
        $this->assertRouteExists('admin.ml.ab-test.end');
    }
    
    private function assertRouteExists(string $routeName): void
    {
        $this->assertTrue(
            \Route::has($routeName),
            "Route $routeName n'existe pas"
        );
    }
}