<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use App\Services\CacheService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class DataControllerOptimized extends Controller
{
    private DashboardService $dashboardService;
    private CacheService $cacheService;
    
    public function __construct(DashboardService $dashboardService, CacheService $cacheService)
    {
        $this->dashboardService = $dashboardService;
        $this->cacheService = $cacheService;
    }
    
    /**
     * Get complete dashboard data - VERSION OPTIMISÉE
     */
    public function getDashboardData(Request $request): JsonResponse
    {
        // Augmenter le temps d'exécution et la limite de mémoire pour les longues périodes
        set_time_limit(120); // 120 secondes
        ini_set('memory_limit', '512M'); // 512MB
        
        $startTime = microtime(true);
        
        try {
            Log::info("=== DÉBUT API getDashboardData OPTIMISÉE ===");
            
            // Validation et normalisation des paramètres
            $params = $this->validateAndNormalizeParams($request);
            
            // Vérification des permissions utilisateur
            $user = auth()->user();
            $params['operator'] = $this->validateOperatorAccess($user, $params['operator']);
            
            Log::info("Paramètres validés", $params);
            Log::info("Utilisateur: {$user->email} (Rôle: {$user->role->name})");
            
            // Récupération des données via le service optimisé
            $data = $this->dashboardService->getDashboardData(
                $params['start_date'],
                $params['end_date'],
                $params['comparison_start_date'],
                $params['comparison_end_date'],
                $params['operator']
            );
            
            // Ajout des métadonnées de performance
            $totalTime = round((microtime(true) - $startTime) * 1000, 2);
            $data['api_execution_time_ms'] = $totalTime;
            $data['optimized_version'] = true;
            
            // Enregistrer pour le monitoring
            $cacheHit = ($data['cache_mode'] ?? '') === 'standard_subcached' && $totalTime < 5000;
            $history = Cache::get('monitoring:api_history', []);
            $history[] = [
                'endpoint' => '/api/dashboard/data',
                'time_ms' => $totalTime,
                'cache_hit' => $cacheHit,
                'operator' => $params['operator'] ?? 'unknown',
                'timestamp' => now()->toISOString(),
            ];
            Cache::put('monitoring:api_history', array_slice($history, -100), 3600);
            
            return response()->json($data);
            
        } catch (\InvalidArgumentException $e) {
            Log::warning("Paramètres invalides: " . $e->getMessage());
            return response()->json([
                "error" => "Paramètres invalides",
                "message" => $e->getMessage()
            ], 400);
            
        } catch (\Exception $e) {
            Log::error("=== ERREUR API OPTIMISÉE ===");
            Log::error("Message: " . $e->getMessage());
            Log::error("Fichier: " . $e->getFile() . " ligne " . $e->getLine());
            
            // Ne jamais retourner de fallback - retourner une erreur claire
                return response()->json([
                "success" => false,
                    "error" => "Erreur système",
                    "message" => "Impossible de récupérer les données",
                "error_details" => $e->getMessage(),
                "data_source" => "error",
                    "timestamp" => now()->toISOString()
                ], 500);
        }
    }
    
    /**
     * Validation et normalisation des paramètres d'entrée
     */
    private function validateAndNormalizeParams(Request $request): array
    {
        $startDate = $request->input("start_date");
        $endDate = $request->input("end_date");
        $comparisonStartDate = $request->input("comparison_start_date");
        $comparisonEndDate = $request->input("comparison_end_date");
        $selectedOperator = $request->input("operator", "Timwe");
        
        // Validation des dates
        if ($startDate && !$this->isValidDate($startDate)) {
            throw new \InvalidArgumentException("Date de début invalide: {$startDate}");
        }
        if ($endDate && !$this->isValidDate($endDate)) {
            throw new \InvalidArgumentException("Date de fin invalide: {$endDate}");
        }
        
        // Dates par défaut si non fournies
        if (!$startDate || !$endDate) {
            $endDate = Carbon::now()->toDateString();
            $startDate = Carbon::now()->subDays(13)->toDateString();
        }
        
        // Période de comparaison par défaut
        if (!$comparisonStartDate || !$comparisonEndDate) {
            $comparisonEndDate = Carbon::parse($startDate)->subDay()->toDateString();
            $comparisonStartDate = Carbon::parse($comparisonEndDate)->subDays(13)->toDateString();
        }
        
        // Validation de la cohérence des dates
        if (Carbon::parse($startDate)->gt(Carbon::parse($endDate))) {
            throw new \InvalidArgumentException("La date de début doit être antérieure à la date de fin");
        }
        
        // Limitation de la période maximale (1 an)
        $periodDays = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate));
        if ($periodDays > 365) {
            throw new \InvalidArgumentException("Période maximale autorisée: 365 jours (demandé: {$periodDays} jours)");
        }
        
        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'comparison_start_date' => $comparisonStartDate,
            'comparison_end_date' => $comparisonEndDate,
            'operator' => $selectedOperator,
            'period_days' => $periodDays
        ];
    }
    
    /**
     * Validation de l'accès opérateur selon les permissions (gère les IDs et les noms)
     */
    private function validateOperatorAccess($user, string $requestedOperator): string
    {
        // Si c'est "ALL", autoriser uniquement pour SuperAdmin
        if ($requestedOperator === 'ALL' || $requestedOperator === '' || $requestedOperator === null) {
            if ($user->isSuperAdmin()) {
                return 'ALL';
            }
            // Pour Admin/Collaborateur, utiliser l'opérateur par défaut
            $primaryOperator = $user->primaryOperator();
            if ($primaryOperator) {
                $primaryOperatorId = DB::table('country_payments_methods')
                    ->whereRaw("TRIM(country_payments_methods_name) = ?", [trim($primaryOperator->operator_name)])
                    ->value('country_payments_methods_id');
                if ($primaryOperatorId) {
                    return (string)$primaryOperatorId;
                }
            }
            $firstOperator = $user->operators()->where('is_active', true)->first();
            if ($firstOperator) {
                $firstOperatorId = DB::table('country_payments_methods')
                    ->whereRaw("TRIM(country_payments_methods_name) = ?", [trim($firstOperator->operator_name)])
                    ->value('country_payments_methods_id');
                if ($firstOperatorId) {
                    return (string)$firstOperatorId;
                }
            }
            return 'S\'abonner via Timwe';
        }
        
        // Convertir l'ID en string si c'est un nombre
        $requestedOperatorId = is_numeric($requestedOperator) ? (string)$requestedOperator : $requestedOperator;
        
        if ($user->isSuperAdmin()) {
            // Super Admin peut accéder à tous les opérateurs
            if (is_numeric($requestedOperatorId)) {
                return $requestedOperatorId;
            }
            // Chercher l'ID correspondant au nom
            $operatorId = DB::table('country_payments_methods')
                ->whereRaw("TRIM(country_payments_methods_name) = ?", [trim($requestedOperatorId)])
                ->value('country_payments_methods_id');
            if ($operatorId) {
                return (string)$operatorId;
            }
            return $requestedOperatorId;
        }
        
        // Pour Admin/Collaborateur, vérifier les opérateurs assignés
        $allowedOperatorNames = $user->operators()
            ->where('is_active', true)
            ->pluck('operator_name')
            ->toArray();
        
        if (empty($allowedOperatorNames)) {
            return 'S\'abonner via Timwe';
        }
        
        // Récupérer les IDs des opérateurs autorisés
        $allowedOperatorIds = DB::table('country_payments_methods')
            ->whereIn(DB::raw('TRIM(country_payments_methods_name)'), array_map('trim', $allowedOperatorNames))
            ->pluck('country_payments_methods_id')
            ->map(function($id) { return (string)$id; })
            ->toArray();
        
        // Si l'opérateur demandé est un ID, vérifier s'il est dans la liste autorisée
        if (is_numeric($requestedOperatorId)) {
            if (in_array($requestedOperatorId, $allowedOperatorIds)) {
                return $requestedOperatorId;
            }
            // Si l'ID n'est pas autorisé, utiliser le premier opérateur assigné
            if (!empty($allowedOperatorIds)) {
                return $allowedOperatorIds[0];
            }
        }
        
        // Si c'est un nom, vérifier s'il est dans la liste autorisée
        if (in_array($requestedOperator, $allowedOperatorNames)) {
            // Convertir le nom en ID pour cohérence
            $operatorId = DB::table('country_payments_methods')
                ->whereRaw("TRIM(country_payments_methods_name) = ?", [trim($requestedOperator)])
                ->value('country_payments_methods_id');
            if ($operatorId) {
                return (string)$operatorId;
            }
            return $requestedOperator;
        }
        
        // Si l'opérateur n'est pas autorisé, utiliser le premier opérateur assigné
        $primaryOperator = $user->primaryOperator()->first();
        if ($primaryOperator) {
            $primaryOperatorId = DB::table('country_payments_methods')
                ->whereRaw("TRIM(country_payments_methods_name) = ?", [trim($primaryOperator->operator_name)])
                ->value('country_payments_methods_id');
            if ($primaryOperatorId) {
                return (string)$primaryOperatorId;
            }
        }
        
        if (!empty($allowedOperatorIds)) {
            return $allowedOperatorIds[0];
        }
        
        return 'S\'abonner via Timwe';
    }
    
    /**
     * Validation de date
     */
    private function isValidDate($date): bool
    {
        try {
            Carbon::parse($date);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    
    /**
     * Get available operators - VERSION OPTIMISÉE
     */
    public function getAvailableOperators(): JsonResponse
    {
        try {
            $cacheKey = $this->cacheService->generateKey(['operators', 'list', 'v2']);
            
            $operators = $this->cacheService->remember($cacheKey, 1, 'operators', function() {
                return DB::table('country_payments_methods')
                    ->select('country_payments_methods_name as name', DB::raw('COUNT(*) as count'))
                    ->whereNotNull('country_payments_methods_name')
                    ->where('country_payments_methods_name', '!=', '')
                    ->groupBy('country_payments_methods_name')
                    ->having('count', '>', 0)
                    ->orderBy('name')
                    ->get()
                    ->map(function($item) {
                        return [
                            'value' => $item->name,
                            'label' => $item->name . ' (' . $item->count . ' méthodes)',
                            'count' => $item->count
                        ];
                    });
            });

            return response()->json([
                'operators' => $operators->toArray(),
                'cached' => true
            ]);

        } catch (\Exception $e) {
            Log::error("Erreur lors de la récupération des opérateurs: " . $e->getMessage());

            return response()->json([
                "error" => "Erreur lors de la récupération des opérateurs",
                "operators" => []
            ], 500);
        }
    }
    
    /**
     * Health check endpoint
     */
    public function healthCheck(): JsonResponse
    {
        try {
            $startTime = microtime(true);
            
            // Test de connexion DB
            $dbStatus = 'ok';
            $dbTime = 0;
            try {
                $dbStart = microtime(true);
                DB::select('SELECT 1');
                $dbTime = round((microtime(true) - $dbStart) * 1000, 2);
            } catch (\Exception $e) {
                $dbStatus = 'error: ' . $e->getMessage();
            }
            
            // Test du cache
            $cacheStatus = 'ok';
            try {
                $testKey = 'health_check_' . time();
                $this->cacheService->putWithStale($testKey, 'test', 60);
                $this->cacheService->cleanup();
            } catch (\Exception $e) {
                $cacheStatus = 'error: ' . $e->getMessage();
            }
            
            $totalTime = round((microtime(true) - $startTime) * 1000, 2);
            
            return response()->json([
                'status' => 'healthy',
                'timestamp' => now()->toISOString(),
                'checks' => [
                    'database' => [
                        'status' => $dbStatus,
                        'response_time_ms' => $dbTime
                    ],
                    'cache' => [
                        'status' => $cacheStatus,
                        'stats' => $this->cacheService->getStats()
                    ]
                ],
                'total_response_time_ms' => $totalTime,
                'version' => 'optimized_v2'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString()
            ], 500);
        }
    }
    
    /**
     * Cache management endpoints
     */
    public function clearCache(Request $request): JsonResponse
    {
        try {
            $operator = $request->input('operator');
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');
            
            $clearedCount = 0;
            
            if ($operator) {
                $clearedCount += $this->cacheService->invalidateOperator($operator);
            } elseif ($startDate && $endDate) {
                $clearedCount += $this->cacheService->invalidatePeriod($startDate, $endDate);
            } else {
                // Nettoyage général
                $clearedCount += $this->cacheService->cleanup();
            }
            
            return response()->json([
                'success' => true,
                'cleared_entries' => $clearedCount,
                'timestamp' => now()->toISOString()
            ]);
            
        } catch (\Exception $e) {
            Log::error("Erreur lors du nettoyage du cache: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Cache warmup endpoint
     */
    public function warmupCache(Request $request): JsonResponse
    {
        try {
            $operators = $request->input('operators', ['ALL', 'Timwe']);
            $this->cacheService->warmup($operators);
            
            return response()->json([
                'success' => true,
                'message' => 'Cache préchauffé avec succès',
                'operators' => $operators,
                'timestamp' => now()->toISOString()
            ]);
            
        } catch (\Exception $e) {
            Log::error("Erreur lors du préchauffage du cache: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Récupère tous les abonnements d'un utilisateur spécifique
     */
    public function getUserSubscriptions(Request $request, int $clientId): JsonResponse
    {
        try {
            Log::info("Récupération des abonnements pour le client: {$clientId}");
            
            $subscriptions = $this->dashboardService->getUserSubscriptions($clientId);
            
            return response()->json($subscriptions);
            
        } catch (\Exception $e) {
            Log::error("Erreur lors de la récupération des abonnements du client {$clientId}: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'error' => 'Impossible de récupérer les abonnements',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * DÉSACTIVÉ POUR OPTIMISATION
     * Récupère les transactions Timwe d'un client spécifique
     */
    /*
    public function getClientTimweTransactions(Request $request, int $clientId): JsonResponse
    {
        try {
            $startDate = $request->query('start_date', now()->subDays(30)->format('Y-m-d'));
            $endDate = $request->query('end_date', now()->format('Y-m-d'));
            
            Log::info("Récupération des transactions Timwe pour le client: {$clientId}", [
                'start_date' => $startDate,
                'end_date' => $endDate
            ]);
            
            // Récupérer les transactions du client (exclure FROM_TIMWE_RENEWED_NOTIF qui ne sont pas utiles)
            $transactions = DB::table('transactions_history')
                ->where('client_id', $clientId)
                ->where(function($query) {
                    $query->where('reference', 'LIKE', '%TIMWE-OPTIN%')
                          ->orWhere('reference', 'LIKE', '%FROM_TIMWE_RENEWED%');
                })
                ->where(function($query) {
                    // Garder TIMWE_RENEWED_NOTIF mais pas FROM_TIMWE_RENEWED_NOTIF
                    $query->where(function($q) {
                        $q->where('status', 'LIKE', '%TIMWE_RENEWED_NOTIF%')
                          ->where('status', 'NOT LIKE', '%FROM_TIMWE_RENEWED_NOTIF%');
                    })
                    ->orWhere('status', 'LIKE', '%UNSUBSCRIPTION%');
                })
                ->whereBetween('created_at', [$startDate, $endDate])
                ->orderBy('created_at', 'DESC')
                ->select('transaction_history_id', 'reference', 'status', 'created_at', 'result')
                ->get();
            
            // Analyser le result de chaque transaction et ajouter un statut calculé
            $tentativeNB = 0;
            $facture = 0;
            $tentative = 0;
            
            foreach ($transactions as $transaction) {
                // Parser le JSON result
                $result = null;
                if ($transaction->result) {
                    $result = json_decode($transaction->result, true);
                }
                
                // Déterminer le statut selon la logique
                if ($result && isset($result['mnoDeliveryCode'])) {
                    if ($result['mnoDeliveryCode'] === 'NO_BALANCE' && 
                        isset($result['totalCharged']) && $result['totalCharged'] == 0) {
                        $transaction->billing_status = 'tentative_nb';
                        $transaction->billing_status_label = 'Tentative NB';
                        $tentativeNB++;
                    } elseif ($result['mnoDeliveryCode'] === 'DELIVERED' && 
                              isset($result['totalCharged']) && $result['totalCharged'] > 0) {
                        $transaction->billing_status = 'facture';
                        $transaction->billing_status_label = 'Facturé';
                        $transaction->amount_charged = $result['totalCharged'];
                        $facture++;
                    } else {
                        $transaction->billing_status = 'tentative';
                        $transaction->billing_status_label = 'Tentative';
                        $tentative++;
                    }
                } else {
                    $transaction->billing_status = 'tentative';
                    $transaction->billing_status_label = 'Tentative';
                    $tentative++;
                }
                
                // Ajouter les détails du result pour affichage
                $transaction->result_details = $result;
                $transaction->mno_delivery_code = $result['mnoDeliveryCode'] ?? '-';
                $transaction->total_charged = $result['totalCharged'] ?? 0;
            }
            
            // Calculer les statistiques
            $stats = [
                'total_transactions' => $transactions->count(),
                'renewals' => $transactions->filter(function($tx) {
                    return str_contains($tx->status, 'RENEWED');
                })->count(),
                'unsubscriptions' => $transactions->filter(function($tx) {
                    return str_contains($tx->status, 'UNSUBSCRIPTION');
                })->count(),
                'facture' => $facture,
                'tentative_nb' => $tentativeNB,
                'tentative' => $tentative,
                'first_transaction_date' => $transactions->min('created_at'),
                'last_transaction_date' => $transactions->max('created_at')
            ];
            
            return response()->json([
                'success' => true,
                'client_id' => $clientId,
                'transactions' => $transactions,
                'stats' => $stats
            ]);
            
        } catch (\Exception $e) {
            Log::error("Erreur lors de la récupération des transactions Timwe du client {$clientId}: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'error' => 'Impossible de récupérer les transactions',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    */
    
    // ==========================================
    // ENDPOINTS SPLIT POUR CHARGEMENT PROGRESSIF
    // ==========================================
    
    /**
     * KPIs seuls (rapide ~15s cold, ~1s cached)
     */
    public function getKpisSplit(Request $request): JsonResponse
    {
        set_time_limit(120);
        $startTime = microtime(true);
        try {
            $params = $this->validateAndNormalizeParams($request);
            $user = auth()->user();
            $params['operator'] = $this->validateOperatorAccess($user, $params['operator']);
            
            $startBound = Carbon::parse($params['start_date'])->startOfDay();
            $endExclusive = Carbon::parse($params['end_date'])->addDay()->startOfDay();
            $compStartBound = Carbon::parse($params['comparison_start_date'])->startOfDay();
            $compEndExclusive = Carbon::parse($params['comparison_end_date'])->addDay()->startOfDay();
            
            $cacheKey = 'split:kpis:' . md5(json_encode($params));
            $kpis = Cache::remember($cacheKey, 1800, function() use ($startBound, $endExclusive, $compStartBound, $compEndExclusive, $params) {
                return $this->dashboardService->getKPIsOptimizedPublic($startBound, $endExclusive, $compStartBound, $compEndExclusive, $params['operator']);
            });
            
            return response()->json([
                'success' => true,
                'section' => 'kpis',
                'data' => $kpis,
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000)
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'section' => 'kpis', 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Marchands seuls
     */
    public function getMerchantsSplit(Request $request): JsonResponse
    {
        set_time_limit(120);
        $startTime = microtime(true);
        try {
            $params = $this->validateAndNormalizeParams($request);
            $user = auth()->user();
            $params['operator'] = $this->validateOperatorAccess($user, $params['operator']);
            
            $startBound = Carbon::parse($params['start_date'])->startOfDay();
            $endExclusive = Carbon::parse($params['end_date'])->addDay()->startOfDay();
            $compStartBound = Carbon::parse($params['comparison_start_date'])->startOfDay();
            $compEndExclusive = Carbon::parse($params['comparison_end_date'])->addDay()->startOfDay();
            
            $cacheKey = 'split:merchants:' . md5(json_encode($params));
            $merchants = Cache::remember($cacheKey, 1800, function() use ($startBound, $endExclusive, $compStartBound, $compEndExclusive, $params) {
                return $this->dashboardService->getMerchantsOptimizedPublic($startBound, $endExclusive, $compStartBound, $compEndExclusive, $params['operator']);
            });
            
            return response()->json([
                'success' => true,
                'section' => 'merchants',
                'data' => $merchants['data'],
                'categoryDistribution' => $merchants['categories'],
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000)
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'section' => 'merchants', 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Transactions seules (rapide ~1s)
     */
    public function getTransactionsSplit(Request $request): JsonResponse
    {
        set_time_limit(60);
        $startTime = microtime(true);
        try {
            $params = $this->validateAndNormalizeParams($request);
            $user = auth()->user();
            $params['operator'] = $this->validateOperatorAccess($user, $params['operator']);
            
            $startBound = Carbon::parse($params['start_date'])->startOfDay();
            $endExclusive = Carbon::parse($params['end_date'])->addDay()->startOfDay();
            
            $cacheKey = 'split:transactions:' . md5(json_encode($params));
            $transactions = Cache::remember($cacheKey, 1800, function() use ($startBound, $endExclusive, $params) {
                return $this->dashboardService->getTransactionsDataPublic($startBound, $endExclusive, $params['operator']);
            });
            
            return response()->json([
                'success' => true,
                'section' => 'transactions',
                'data' => $transactions,
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000)
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'section' => 'transactions', 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Abonnements seuls (le plus lourd)
     */
    public function getSubscriptionsSplit(Request $request): JsonResponse
    {
        set_time_limit(180);
        $startTime = microtime(true);
        try {
            $params = $this->validateAndNormalizeParams($request);
            $user = auth()->user();
            $params['operator'] = $this->validateOperatorAccess($user, $params['operator']);
            
            $startBound = Carbon::parse($params['start_date'])->startOfDay();
            $endExclusive = Carbon::parse($params['end_date'])->addDay()->startOfDay();
            $compStartBound = Carbon::parse($params['comparison_start_date'])->startOfDay();
            $compEndExclusive = Carbon::parse($params['comparison_end_date'])->addDay()->startOfDay();
            
            $cacheKey = 'split:subscriptions:' . md5(json_encode($params));
            $subscriptions = Cache::remember($cacheKey, 1800, function() use ($startBound, $endExclusive, $params, $compStartBound, $compEndExclusive) {
                return $this->dashboardService->getSubscriptionsDataPublic($startBound, $endExclusive, $params['operator'], $compStartBound, $compEndExclusive);
            });
            
            return response()->json([
                'success' => true,
                'section' => 'subscriptions',
                'data' => $subscriptions,
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000)
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'section' => 'subscriptions', 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Ooredoo stats seuls (rapide ~1s)
     */
    public function getOoredooStatsSplit(Request $request): JsonResponse
    {
        set_time_limit(60);
        $startTime = microtime(true);
        try {
            $params = $this->validateAndNormalizeParams($request);
            
            $startBound = Carbon::parse($params['start_date'])->startOfDay();
            $endExclusive = Carbon::parse($params['end_date'])->addDay()->startOfDay();
            $compStartBound = Carbon::parse($params['comparison_start_date'])->startOfDay();
            $compEndExclusive = Carbon::parse($params['comparison_end_date'])->addDay()->startOfDay();
            
            $cacheKey = 'split:ooredoo:' . md5(json_encode($params));
            $ooredooStats = Cache::remember($cacheKey, 1800, function() use ($startBound, $endExclusive, $compStartBound, $compEndExclusive) {
                $daily = $this->dashboardService->getOoredooDailyStatisticsPublic($startBound, $endExclusive);
                $dailyComp = $this->dashboardService->getOoredooDailyStatisticsPublic($compStartBound, $compEndExclusive);
                return [
                    'daily_statistics' => $daily,
                    'daily_statistics_comparison' => $dailyComp,
                    'ooredoo_monthly_stats' => $this->dashboardService->groupOoredooStatsByMonthPublic($daily),
                    'ooredoo_monthly_stats_comparison' => $this->dashboardService->groupOoredooStatsByMonthPublic($dailyComp)
                ];
            });
            
            return response()->json([
                'success' => true,
                'section' => 'ooredoo_stats',
                'data' => $ooredooStats,
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000)
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'section' => 'ooredoo_stats', 'error' => $e->getMessage()], 500);
        }
    }
}

