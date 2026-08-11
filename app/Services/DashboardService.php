<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Traits\OperatorHelper;
use App\Services\Dashboard\KPIService;
use App\Services\Dashboard\MerchantService;
use App\Services\Dashboard\TransactionService;
use App\Services\Dashboard\SubscriptionService;
use App\Services\Dashboard\StatisticsService;

/**
 * Facade DashboardService - Délègue aux services de domaine spécialisés.
 * 
 * Services:
 * - KPIService: Calcul des KPIs (abonnements, transactions, facturation)
 * - MerchantService: Données marchands, catégories, insights
 * - TransactionService: Volume de transactions, analytiques
 * - SubscriptionService: Abonnements, rétention, cohortes
 * - StatisticsService: Statistiques quotidiennes Timwe/Ooredoo
 */
class DashboardService
{
    use OperatorHelper;

    protected KPIService $kpiService;
    protected MerchantService $merchantService;
    protected TransactionService $transactionService;
    protected SubscriptionService $subscriptionService;
    protected StatisticsService $statisticsService;

    public function __construct(
        KPIService $kpiService,
        MerchantService $merchantService,
        TransactionService $transactionService,
        SubscriptionService $subscriptionService,
        StatisticsService $statisticsService
    ) {
        $this->kpiService = $kpiService;
        $this->merchantService = $merchantService;
        $this->transactionService = $transactionService;
        $this->subscriptionService = $subscriptionService;
        $this->statisticsService = $statisticsService;
    }

    /**
     * Point d'entrée principal du dashboard - Récupère toutes les données
     */
    public function getDashboardData(string $period = '14d', string $selectedOperator = 'ALL'): array
    {
        $methodStart = microtime(true);
        $now = Carbon::now();

        // Calcul des dates selon la période
        switch ($period) {
            case '7d':
                $startBound = $now->copy()->subDays(7)->startOfDay();
                $compStartBound = $now->copy()->subDays(14)->startOfDay();
                break;
            case '30d':
                $startBound = $now->copy()->subDays(30)->startOfDay();
                $compStartBound = $now->copy()->subDays(60)->startOfDay();
                break;
            case '90d':
                $startBound = $now->copy()->subDays(90)->startOfDay();
                $compStartBound = $now->copy()->subDays(180)->startOfDay();
                break;
            case 'lifetime':
                $startBound = $now->copy()->subYears(6)->startOfDay();
                $compStartBound = $now->copy()->subDays(365)->startOfDay();
                break;
            default: // 14d
                $startBound = $now->copy()->subDays(14)->startOfDay();
                $compStartBound = $now->copy()->subDays(28)->startOfDay();
        }

        $endExclusive = $now->copy()->startOfDay()->addDay();
        $compEndExclusive = $startBound->copy();

        $periodDays = $startBound->diffInDays($endExclusive);
        $cacheTTL = $this->getCacheTTL($periodDays);
        $cacheKey = "dashboard_v2:{$period}:{$selectedOperator}";

        $cached = Cache::get($cacheKey);
        if ($cached) {
            Log::info("Dashboard cache HIT: {$cacheKey}");
            return $cached;
        }

        Log::info("Dashboard compute START: {$period}, operator={$selectedOperator}");

        $kpis = $this->kpiService->getKPIs($startBound, $endExclusive, $compStartBound, $compEndExclusive, $selectedOperator);
        $merchants = $this->merchantService->getMerchants($startBound, $endExclusive, $compStartBound, $compEndExclusive, $selectedOperator);
        $transactions = $this->transactionService->getTransactions($startBound, $endExclusive, $selectedOperator);
        $subscriptions = $this->subscriptionService->getSubscriptions($startBound, $endExclusive, $selectedOperator, $compStartBound, $compEndExclusive);

        $insights = $this->merchantService->generateInsights($kpis, $merchants['data'] ?? []);

        $result = [
            'kpis' => $kpis,
            'merchants' => $merchants,
            'transactions' => $transactions,
            'subscriptions' => $subscriptions,
            'insights' => $insights,
            'meta' => [
                'period' => $period,
                'start_date' => $startBound->toDateString(),
                'end_date' => $endExclusive->copy()->subDay()->toDateString(),
                'comparison_start' => $compStartBound->toDateString(),
                'comparison_end' => $compEndExclusive->copy()->subDay()->toDateString(),
                'operator' => $selectedOperator,
                'execution_time_ms' => round((microtime(true) - $methodStart) * 1000),
            ]
        ];

        Cache::put($cacheKey, $result, $cacheTTL);
        Log::info("Dashboard compute DONE in " . round((microtime(true) - $methodStart) * 1000) . "ms");

        return $result;
    }

    // ===== Méthodes publiques déléguées (gardent l'API identique pour le Controller et Warmup) =====

    public function getKPIsOptimizedPublic(Carbon $startBound, Carbon $endExclusive, Carbon $compStartBound, Carbon $compEndExclusive, string $selectedOperator): array
    {
        return $this->kpiService->getKPIs($startBound, $endExclusive, $compStartBound, $compEndExclusive, $selectedOperator);
    }

    public function getMerchantsOptimizedPublic(Carbon $startBound, Carbon $endExclusive, Carbon $compStartBound, Carbon $compEndExclusive, string $selectedOperator): array
    {
        return $this->merchantService->getMerchants($startBound, $endExclusive, $compStartBound, $compEndExclusive, $selectedOperator);
    }

    public function getTransactionsDataPublic(Carbon $startBound, Carbon $endExclusive, string $selectedOperator): array
    {
        return $this->transactionService->getTransactions($startBound, $endExclusive, $selectedOperator);
    }

    public function getSubscriptionsDataPublic(Carbon $startBound, Carbon $endExclusive, string $selectedOperator, ?Carbon $compStartBound = null, ?Carbon $compEndExclusive = null): array
    {
        return $this->subscriptionService->getSubscriptions($startBound, $endExclusive, $selectedOperator, $compStartBound, $compEndExclusive);
    }

    /**
     * Flux intégral des abonnements de la période, sans plafond, pour l'export CSV.
     *
     * @return \Generator<object>
     */
    public function streamSubscriptionDetailsPublic(Carbon $startBound, Carbon $endExclusive, string $selectedOperator): \Generator
    {
        return $this->subscriptionService->streamSubscriptionDetails($startBound, $endExclusive, $selectedOperator);
    }

    /**
     * Une page du tableau des abonnements, paginée côté serveur.
     */
    public function paginateSubscriptionDetailsPublic(Carbon $startBound, Carbon $endExclusive, string $selectedOperator, int $page, int $perPage): array
    {
        return $this->subscriptionService->paginateSubscriptionDetails($startBound, $endExclusive, $selectedOperator, $page, $perPage);
    }

    public function getUserSubscriptions(int $clientId): array
    {
        return $this->subscriptionService->getUserSubscriptions($clientId);
    }

    public function getDailyStatistics(Carbon $startBound, Carbon $endExclusive, string $selectedOperator): array
    {
        return $this->statisticsService->getDailyStatistics($startBound, $endExclusive, $selectedOperator);
    }

    public function groupTimweStatsByMonth(array $dailyStats): array
    {
        return $this->statisticsService->groupTimweStatsByMonth($dailyStats);
    }

    public function getOoredooDailyStatisticsPublic(Carbon $startBound, Carbon $endExclusive): array
    {
        return $this->statisticsService->getOoredooDailyStatistics($startBound, $endExclusive);
    }

    public function groupOoredooStatsByMonthPublic(array $dailyStats): array
    {
        return $this->statisticsService->groupOoredooStatsByMonth($dailyStats);
    }
}
