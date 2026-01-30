<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\DataController;
use App\Http\Controllers\Api\DataControllerOptimized;
use App\Http\Controllers\Api\EklektikController;
use App\Http\Controllers\Api\EklektikStatsController;
use App\Http\Controllers\Api\EklektikDashboardController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// API pour récupérer les opérateurs
Route::middleware('auth')->get('/operators', [\App\Http\Controllers\Api\OperatorsController::class, 'getOperators'])->name('api.operators');

// Dashboard API routes
Route::prefix('dashboard')->name('api.dashboard.')->group(function () {
    Route::get('/data', [DataControllerOptimized::class, 'getDashboardData'])->name('data');
    Route::get('/subscriptions/{clientId}', [DataControllerOptimized::class, 'getUserSubscriptions'])->name('user.subscriptions');
    Route::get('/operators', [DataController::class, 'getUserOperators'])->name('operators');
    Route::get('/partners', [DataController::class, 'getPartnersList'])->name('partners');
    Route::get('/kpis', [DataController::class, 'getKpis'])->name('kpis');
    Route::get('/merchants', [DataController::class, 'getMerchants'])->name('merchants');
    Route::get('/transactions', [DataController::class, 'getTransactions'])->name('transactions');
    Route::get('/subscriptions', [DataController::class, 'getSubscriptions'])->name('subscriptions');
});

// Eklektik API routes - Contrôleur consolidé (sans auth pour test)
Route::prefix('eklektik')->name('api.eklektik.')->group(function () {
    Route::get('/numbers', [EklektikController::class, 'getDashboardData'])->name('numbers');
    Route::get('/dashboard-data', [EklektikController::class, 'getDashboardData'])->name('dashboard-data');
    Route::get('/test', [EklektikController::class, 'getDashboardData'])->name('test');
    
    // Nouvelles routes pour les statistiques Eklektik
    Route::get('/stats/overview', [EklektikController::class, 'getEklektikStats'])->name('stats.overview');
    Route::get('/stats/billing-rate', [EklektikController::class, 'getBillingRate'])->name('stats.billing-rate');
    Route::get('/stats/revenue', [EklektikController::class, 'getRevenue'])->name('stats.revenue');
    Route::get('/stats/active-subscriptions', [EklektikController::class, 'getActiveSubscriptions'])->name('stats.active-subscriptions');
    Route::get('/stats/new-subscriptions', [EklektikController::class, 'getNewSubscriptions'])->name('stats.new-subscriptions');
    Route::get('/stats/unsubscriptions', [EklektikController::class, 'getUnsubscriptions'])->name('stats.unsubscriptions');
    Route::get('/stats/billed-clients', [EklektikController::class, 'getBilledClients'])->name('stats.billed-clients');
    Route::get('/stats/renewals', [EklektikController::class, 'getRenewals'])->name('stats.renewals');
    Route::get('/stats/operators-distribution', [EklektikController::class, 'getOperatorsDistribution'])->name('stats.operators-distribution');
});

// Nouvelles routes pour les statistiques Eklektik locales
Route::prefix('eklektik-stats')->name('api.eklektik-stats.')->group(function () {
    Route::get('/dashboard', [EklektikStatsController::class, 'getDashboardStats'])->name('dashboard');
    Route::get('/kpis', [EklektikStatsController::class, 'getKPIs'])->name('kpis');
    Route::get('/operators-distribution', [EklektikStatsController::class, 'getOperatorsDistribution'])->name('operators-distribution');
    Route::get('/detailed', [EklektikStatsController::class, 'getDetailedStats'])->name('detailed');
    Route::post('/sync', [EklektikStatsController::class, 'syncStats'])->name('sync');
});

// Routes pour le dashboard Eklektik intégré
Route::prefix('eklektik-dashboard')->name('api.eklektik-dashboard.')->group(function () {
    Route::get('/kpis', [EklektikDashboardController::class, 'getKPIs'])->name('kpis');
    Route::get('/bigdeal-revenue', [EklektikDashboardController::class, 'getBigDealRevenue'])->name('bigdeal-revenue');
    Route::get('/revenue-evolution', [EklektikDashboardController::class, 'getRevenueEvolution'])->name('revenue-evolution');
    Route::get('/revenue-distribution', [EklektikDashboardController::class, 'getRevenueDistribution'])->name('revenue-distribution');
    Route::get('/overview-chart', [EklektikDashboardController::class, 'getOverviewChart'])->name('overview-chart');
    Route::get('/subs-evolution', [EklektikDashboardController::class, 'getSubsEvolution'])->name('subs-evolution');
    Route::get('/sync-status', [EklektikDashboardController::class, 'getSyncStatus'])->name('sync-status');
    Route::post('/clear-cache', [EklektikDashboardController::class, 'clearCache'])->name('clear-cache');
});

// Routes optimisées additionnelles si présentes
if (file_exists(base_path('routes/api_optimized.php'))) {
    require base_path('routes/api_optimized.php');
}
