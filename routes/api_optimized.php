<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\DataControllerOptimized;
use App\Http\Middleware\DashboardPerformanceMiddleware;

/*
|--------------------------------------------------------------------------
| API Routes Optimisées - Dashboard CP
|--------------------------------------------------------------------------
|
| Routes optimisées pour le dashboard avec middleware de performance
| et gestion d'erreurs améliorée
|
*/

// Groupe de routes avec middleware de performance
Route::middleware(['auth:sanctum', DashboardPerformanceMiddleware::class])
    ->prefix('dashboard/v2')
    ->group(function () {
        
        // Route principale optimisée
        Route::get('/data', [DataControllerOptimized::class, 'getDashboardData'])
            ->name('api.dashboard.v2.data');
        
        // Routes utilitaires
        Route::get('/operators', [DataControllerOptimized::class, 'getAvailableOperators'])
            ->name('api.dashboard.v2.operators');
        
        // Health check
        Route::get('/health', [DataControllerOptimized::class, 'healthCheck'])
            ->name('api.dashboard.v2.health');
        
        // Gestion du cache
        Route::post('/cache/clear', [DataControllerOptimized::class, 'clearCache'])
            ->name('api.dashboard.v2.cache.clear');
        
        Route::post('/cache/warmup', [DataControllerOptimized::class, 'warmupCache'])
            ->name('api.dashboard.v2.cache.warmup');
    });

// Routes de monitoring (accès restreint aux admins)
Route::middleware(['auth:sanctum', 'role:super_admin'])
    ->prefix('dashboard/monitoring')
    ->group(function () {
        
        // Métriques de performance
        Route::get('/metrics', function (Request $request) {
            $monitoringService = app(\App\Services\MonitoringService::class);
            $hours = $request->input('hours', 24);
            return response()->json($monitoringService->getAggregatedMetrics($hours));
        })->name('api.dashboard.monitoring.metrics');
        
        // Rapport de santé
        Route::get('/health-report', function () {
            $monitoringService = app(\App\Services\MonitoringService::class);
            return response()->json($monitoringService->getHealthReport());
        })->name('api.dashboard.monitoring.health');
        
        // Tendances de performance
        Route::get('/trends', function (Request $request) {
            $monitoringService = app(\App\Services\MonitoringService::class);
            $days = $request->input('days', 7);
            return response()->json($monitoringService->getPerformanceTrends($days));
        })->name('api.dashboard.monitoring.trends');
        
        // Détection d'anomalies
        Route::get('/anomalies', function () {
            $monitoringService = app(\App\Services\MonitoringService::class);
            return response()->json($monitoringService->detectAnomalies());
        })->name('api.dashboard.monitoring.anomalies');
    });

// Routes de maintenance (accès restreint aux super admins)
Route::middleware(['auth:sanctum', 'role:super_admin'])
    ->prefix('dashboard/maintenance')
    ->group(function () {
        
        // Vérification des index
        Route::get('/check-indexes', function () {
            $requiredIndexes = config('dashboard.database.required_indexes');
            $results = [];
            
            foreach ($requiredIndexes as $table => $indexes) {
                foreach ($indexes as $index) {
                    try {
                        $exists = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$index]);
                        $results[$table][$index] = !empty($exists);
                    } catch (\Exception $e) {
                        $results[$table][$index] = false;
                    }
                }
            }
            
            return response()->json([
                'indexes' => $results,
                'missing_indexes' => collect($results)->flatMap(function($indexes, $table) {
                    return collect($indexes)->filter(fn($exists) => !$exists)->keys()->map(fn($index) => "{$table}.{$index}");
                })->values()
            ]);
        })->name('api.dashboard.maintenance.indexes');
        
        // Statistiques de la base de données
        Route::get('/db-stats', function () {
            $stats = [];
            
            $tables = ['history', 'client_abonnement', 'country_payments_methods', 'partner', 'promotion'];
            
            foreach ($tables as $table) {
                try {
                    $count = DB::table($table)->count();
                    $size = DB::select("SELECT ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?", [$table]);
                    
                    $stats[$table] = [
                        'row_count' => $count,
                        'size_mb' => $size[0]->size_mb ?? 0
                    ];
                } catch (\Exception $e) {
                    $stats[$table] = ['error' => $e->getMessage()];
                }
            }
            
            return response()->json($stats);
        })->name('api.dashboard.maintenance.db-stats');
        
        // Optimisation des tables
        Route::post('/optimize-tables', function () {
            $tables = ['history', 'client_abonnement', 'country_payments_methods'];
            $results = [];
            
            foreach ($tables as $table) {
                try {
                    DB::statement("OPTIMIZE TABLE {$table}");
                    $results[$table] = 'optimized';
                } catch (\Exception $e) {
                    $results[$table] = 'error: ' . $e->getMessage();
                }
            }
            
            return response()->json([
                'results' => $results,
                'timestamp' => now()->toISOString()
            ]);
        })->name('api.dashboard.maintenance.optimize');
    });

// Route de fallback pour la compatibilité
Route::get('/dashboard/data', function (Request $request) {
    return response()->json([
        'message' => 'Cette route est dépréciée. Utilisez /api/dashboard/v2/data',
        'new_endpoint' => '/api/dashboard/v2/data',
        'migration_guide' => 'Voir la documentation pour migrer vers la version optimisée'
    ], 301);
})->name('api.dashboard.deprecated');

