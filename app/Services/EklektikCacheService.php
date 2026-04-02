<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\EklektikStatsDaily;

class EklektikCacheService
{
    private $cachePrefix = 'eklektik_stats_';
    
    /**
     * Calculer le TTL adaptatif selon la période
     */
    private function getCacheTTL($startDate, $endDate): int
    {
        $periodDays = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate)) + 1;
        
        // TTL adaptatif : plus la période est longue, plus le cache dure
        return match(true) {
            $periodDays <= 7 => 300,      // 5 minutes pour période courte
            $periodDays <= 30 => 600,      // 10 minutes pour période moyenne
            $periodDays <= 90 => 1800,     // 30 minutes pour période longue
            default => 3600                // 1 heure pour très longue période
        };
    }

    /**
     * Récupérer les KPIs Eklektik avec cache adaptatif
     */
    public function getCachedKPIs($startDate, $endDate, $operator = null)
    {
        $cacheKey = $this->cachePrefix . 'kpis_' . md5($startDate . $endDate . $operator);
        $ttl = $this->getCacheTTL($startDate, $endDate);
        
        return Cache::remember($cacheKey, $ttl, function () use ($startDate, $endDate, $operator) {
            return $this->calculateKPIs($startDate, $endDate, $operator);
        });
    }

    /**
     * Récupérer les statistiques détaillées avec cache adaptatif
     */
    public function getCachedDetailedStats($startDate, $endDate, $operator = null)
    {
        $cacheKey = $this->cachePrefix . 'detailed_' . md5($startDate . $endDate . $operator);
        $ttl = $this->getCacheTTL($startDate, $endDate);
        
        return Cache::remember($cacheKey, $ttl, function () use ($startDate, $endDate, $operator) {
            return $this->getDetailedStats($startDate, $endDate, $operator);
        });
    }

    /**
     * Récupérer la répartition par opérateur avec cache adaptatif
     */
    public function getCachedOperatorsDistribution($startDate, $endDate)
    {
        $cacheKey = $this->cachePrefix . 'operators_' . md5($startDate . $endDate);
        $ttl = $this->getCacheTTL($startDate, $endDate);
        
        return Cache::remember($cacheKey, $ttl, function () use ($startDate, $endDate) {
            return $this->getOperatorsDistribution($startDate, $endDate);
        });
    }

    /**
     * Récupérer les revenus BigDeal avec cache adaptatif
     */
    public function getCachedBigDealRevenue($startDate, $endDate, $operator = null)
    {
        $cacheKey = $this->cachePrefix . 'bigdeal_' . md5($startDate . $endDate . $operator);
        $ttl = $this->getCacheTTL($startDate, $endDate);
        
        return Cache::remember($cacheKey, $ttl, function () use ($startDate, $endDate, $operator) {
            return $this->getBigDealRevenue($startDate, $endDate, $operator);
        });
    }

    /**
     * Calculer les KPIs Eklektik
     */
    private function calculateKPIs($startDate, $endDate, $operator = null)
    {
        $query = DB::table('eklektik_stats_daily')
            ->whereBetween('date', [$startDate, $endDate]);

        if ($operator && $operator !== 'ALL') {
            $query->where('operator', $operator);
        }

        $stats = $query->get();

        if ($stats->isEmpty()) {
            return [
                'total_new_subscriptions' => 0,
                'total_unsubscriptions' => 0,
                'total_simchurn' => 0,
                'total_facturation' => 0,
                'total_revenue_ttc' => 0,
                'total_revenue_ht' => 0,
                'total_ca_operateur' => 0,
                'total_ca_agregateur' => 0,
                'total_ca_bigdeal' => 0,
                'average_billing_rate' => 0,
                'total_active_subscribers' => 0,
                'operators_distribution' => []
            ];
        }

        // Déterminer le snapshot de fin de période pour Active Subs (ou dernier jour disponible)
        $endDayRows = $stats->where('date', $endDate);
        if ($endDayRows->isEmpty()) {
            $latestDate = optional($stats->sortBy('date')->last())->date;
            $endDayRows = $latestDate ? $stats->where('date', $latestDate) : collect();
        }
        $activeByOperator = $endDayRows->groupBy('operator')->map(function ($rows) {
            return $rows->sum('active_subscribers');
        })->toArray();

        $kpis = [
            'total_new_subscriptions' => $stats->sum('new_subscriptions'),
            'total_unsubscriptions' => $stats->sum('unsubscriptions'),
            'total_simchurn' => $stats->sum('simchurn'),
            'total_facturation' => $stats->sum('nb_facturation'),
            'total_revenue_ttc' => $stats->sum('revenu_ttc_tnd'),
            'total_revenue_ht' => $stats->sum('montant_total_ht'),
            'total_ca_operateur' => $stats->sum('ca_operateur'),
            'total_ca_agregateur' => $stats->sum('ca_agregateur'),
            'total_ca_bigdeal' => $stats->sum('ca_bigdeal'),
            'average_billing_rate' => $stats->avg('billing_rate'),
            // Active subs = somme du snapshot de fin
            'total_active_subscribers' => $endDayRows->sum('active_subscribers'),
            'active_subscribers_by_operator' => $activeByOperator,
            'operators_distribution' => []
        ];

        // Calculer la répartition par opérateur
        $operators = $stats->groupBy('operator');
        foreach ($operators as $operatorName => $operatorStats) {
            $kpis['operators_distribution'][$operatorName] = [
                'total_records' => $operatorStats->count(),
                'new_subscriptions' => $operatorStats->sum('new_subscriptions'),
                'unsubscriptions' => $operatorStats->sum('unsubscriptions'),
                'simchurn' => $operatorStats->sum('simchurn'),
                'facturation' => $operatorStats->sum('nb_facturation'),
                'active_subscribers' => $operatorStats->where('date', $endDayRows->first()->date ?? $endDate)->sum('active_subscribers'),
                'revenue_ttc' => $operatorStats->sum('revenu_ttc_tnd'),
                'revenue_ht' => $operatorStats->sum('montant_total_ht'),
                'ca_operateur' => $operatorStats->sum('ca_operateur'),
                'ca_agregateur' => $operatorStats->sum('ca_agregateur'),
                'ca_bigdeal' => $operatorStats->sum('ca_bigdeal')
            ];
        }

        return $kpis;
    }

    /**
     * Récupérer les statistiques détaillées (optimisé avec agrégation SQL)
     */
    private function getDetailedStats($startDate, $endDate, $operator = null)
    {
        // OPTIMISATION: Utiliser une agrégation SQL au lieu de charger toutes les lignes puis grouper
        $query = DB::table('eklektik_stats_daily')
            ->whereBetween('date', [$startDate, $endDate]);

        if ($operator && $operator !== 'ALL') {
            $query->where('operator', $operator);
        }

        // OPTIMISATION: Agréger directement en SQL par date
        $dailyAggregated = $query
            ->select(
                'date',
                DB::raw('SUM(active_subscribers) as total_active_subscribers'),
                DB::raw('SUM(new_subscriptions) as total_new_subscriptions'),
                DB::raw('SUM(unsubscriptions) as total_unsubscriptions'),
                DB::raw('SUM(simchurn) as total_simchurn'),
                DB::raw('SUM(nb_facturation) as total_facturation'),
                DB::raw('SUM(revenu_ttc_tnd) as total_revenue_ttc'),
                DB::raw('SUM(montant_total_ht) as total_revenue_ht'),
                DB::raw('SUM(ca_operateur) as total_ca_operateur'),
                DB::raw('SUM(ca_agregateur) as total_ca_agregateur'),
                DB::raw('SUM(ca_bigdeal) as total_ca_bigdeal'),
                DB::raw('AVG(billing_rate) as average_billing_rate')
            )
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->get();

        // Pour les détails par opérateur, charger seulement les données nécessaires
        $operatorsQuery = DB::table('eklektik_stats_daily')
            ->whereBetween('date', [$startDate, $endDate]);

        if ($operator && $operator !== 'ALL') {
            $operatorsQuery->where('operator', $operator);
        }

        $operatorsStats = $operatorsQuery
            ->select('date', 'operator', 'offre_id', 'offer_name', 
                     'new_subscriptions', 'unsubscriptions', 'simchurn', 
                     'nb_facturation', 'revenu_ttc_tnd', 'montant_total_ht',
                     'ca_operateur', 'ca_agregateur', 'ca_bigdeal')
            ->orderBy('date', 'desc')
            ->get()
            ->groupBy('date');

        // Log pour debug
        \Log::info('EklektikCacheService::getDetailedStats - Stats récupérés:', [
            'count' => $dailyAggregated->count(),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'operator' => $operator
        ]);

        // Construire le résultat avec agrégation par date
        $dailyStats = $dailyAggregated->map(function ($dayStat) use ($operatorsStats) {
            $date = $dayStat->date;
            $dayOperators = $operatorsStats->get($date, collect());
            
            return [
                'date' => $date,
                'total_active_subscribers' => (float)$dayStat->total_active_subscribers,
                'total_new_subscriptions' => (int)$dayStat->total_new_subscriptions,
                'total_unsubscriptions' => (int)$dayStat->total_unsubscriptions,
                'total_simchurn' => (int)$dayStat->total_simchurn,
                'total_facturation' => (int)$dayStat->total_facturation,
                'total_revenue_ttc' => (float)$dayStat->total_revenue_ttc,
                'total_revenue_ht' => (float)$dayStat->total_revenue_ht,
                'total_ca_operateur' => (float)$dayStat->total_ca_operateur,
                'total_ca_agregateur' => (float)$dayStat->total_ca_agregateur,
                'total_ca_bigdeal' => (float)$dayStat->total_ca_bigdeal,
                'average_billing_rate' => (float)$dayStat->average_billing_rate,
                'operators' => $dayOperators->map(function ($stat) {
                    return [
                        'operator' => $stat->operator,
                        'offre_id' => $stat->offre_id,
                        'offer_name' => $stat->offer_name,
                        'new_subscriptions' => $stat->new_subscriptions,
                        'unsubscriptions' => $stat->unsubscriptions,
                        'simchurn' => $stat->simchurn,
                        'facturation' => $stat->nb_facturation,
                        'revenue_ttc' => $stat->revenu_ttc_tnd,
                        'revenue_ht' => $stat->montant_total_ht,
                        'ca_operateur' => $stat->ca_operateur,
                        'ca_agregateur' => $stat->ca_agregateur,
                        'ca_bigdeal' => $stat->ca_bigdeal
                    ];
                })->values()
            ];
        })->values();

        return $dailyStats;
    }

    /**
     * Récupérer la répartition par opérateur (optimisé avec agrégation SQL)
     */
    private function getOperatorsDistribution($startDate, $endDate)
    {
        // OPTIMISATION: Agréger directement en SQL au lieu de charger toutes les lignes
        $operatorsAggregated = DB::table('eklektik_stats_daily')
            ->whereBetween('date', [$startDate, $endDate])
            ->select(
                'operator',
                DB::raw('COUNT(*) as total_records'),
                DB::raw('SUM(new_subscriptions) as new_subscriptions'),
                DB::raw('SUM(unsubscriptions) as unsubscriptions'),
                DB::raw('SUM(simchurn) as simchurn'),
                DB::raw('SUM(nb_facturation) as facturation'),
                DB::raw('SUM(revenu_ttc_tnd) as revenue_ttc'),
                DB::raw('SUM(montant_total_ht) as revenue_ht'),
                DB::raw('SUM(ca_operateur) as ca_operateur'),
                DB::raw('SUM(ca_agregateur) as ca_agregateur'),
                DB::raw('SUM(ca_bigdeal) as ca_bigdeal')
            )
            ->groupBy('operator')
            ->get();

        // Pour les détails par offre, charger seulement les données nécessaires
        $offersStats = DB::table('eklektik_stats_daily')
            ->whereBetween('date', [$startDate, $endDate])
            ->select('operator', 'offre_id', 'offer_name', 'offer_type',
                     'new_subscriptions', 'unsubscriptions', 'simchurn',
                     'nb_facturation', 'revenu_ttc_tnd', 'montant_total_ht',
                     'ca_operateur', 'ca_agregateur', 'ca_bigdeal')
            ->get()
            ->groupBy(['operator', 'offre_id']);

        $distribution = [];
        foreach ($operatorsAggregated as $operatorStat) {
            $operatorName = $operatorStat->operator;
            $operatorOffers = $offersStats->get($operatorName, collect());
            
            $distribution[$operatorName] = [
                'total_records' => (int)$operatorStat->total_records,
                'new_subscriptions' => (int)$operatorStat->new_subscriptions,
                'unsubscriptions' => (int)$operatorStat->unsubscriptions,
                'simchurn' => (int)$operatorStat->simchurn,
                'facturation' => (int)$operatorStat->facturation,
                'revenue_ttc' => (float)$operatorStat->revenue_ttc,
                'revenue_ht' => (float)$operatorStat->revenue_ht,
                'ca_operateur' => (float)$operatorStat->ca_operateur,
                'ca_agregateur' => (float)$operatorStat->ca_agregateur,
                'ca_bigdeal' => (float)$operatorStat->ca_bigdeal,
                'offers' => $operatorOffers->map(function ($offerStats) {
                    $offer = $offerStats->first();
                    return [
                        'offre_id' => $offer->offre_id,
                        'offer_name' => $offer->offer_name,
                        'offer_type' => $offer->offer_type,
                        'new_subscriptions' => $offerStats->sum('new_subscriptions'),
                        'unsubscriptions' => $offerStats->sum('unsubscriptions'),
                        'simchurn' => $offerStats->sum('simchurn'),
                        'facturation' => $offerStats->sum('nb_facturation'),
                        'revenue_ttc' => $offerStats->sum('revenu_ttc_tnd'),
                        'revenue_ht' => $offerStats->sum('montant_total_ht'),
                        'ca_operateur' => $offerStats->sum('ca_operateur'),
                        'ca_agregateur' => $offerStats->sum('ca_agregateur'),
                        'ca_bigdeal' => $offerStats->sum('ca_bigdeal')
                    ];
                })->values()
            ];
        }

        return $distribution;
    }

    /**
     * Récupérer les revenus BigDeal
     */
    private function getBigDealRevenue($startDate, $endDate, $operator = null)
    {
        $query = DB::table('eklektik_stats_daily')
            ->whereBetween('date', [$startDate, $endDate]);

        if ($operator && $operator !== 'ALL') {
            $query->where('operator', $operator);
        }

        $stats = $query->get();

        return [
            'total_ca_bigdeal' => $stats->sum('ca_bigdeal'),
            'total_revenue_ht' => $stats->sum('montant_total_ht'),
            'bigdeal_percentage' => $stats->sum('montant_total_ht') > 0 ? 
                ($stats->sum('ca_bigdeal') / $stats->sum('montant_total_ht')) * 100 : 0,
            'by_operator' => $stats->groupBy('operator')->map(function ($operatorStats) {
                return [
                    'ca_bigdeal' => $operatorStats->sum('ca_bigdeal'),
                    'revenue_ht' => $operatorStats->sum('montant_total_ht'),
                    'percentage' => $operatorStats->sum('montant_total_ht') > 0 ? 
                        ($operatorStats->sum('ca_bigdeal') / $operatorStats->sum('montant_total_ht')) * 100 : 0
                ];
            })
        ];
    }

    /**
     * Vider le cache Eklektik
     */
    public function clearCache()
    {
        // Vider le cache en supprimant les clés connues
        $knownKeys = [
            $this->cachePrefix . 'kpis_',
            $this->cachePrefix . 'detailed_',
            $this->cachePrefix . 'operators_',
            $this->cachePrefix . 'bigdeal_'
        ];
        
        $clearedCount = 0;
        foreach ($knownKeys as $keyPattern) {
            // Pour le cache de fichiers, on ne peut pas facilement lister les clés
            // On va simplement vider le cache complet
            Cache::flush();
            $clearedCount = 1; // Indique qu'on a vidé le cache
            break;
        }
        
        return $clearedCount;
    }

    /**
     * Obtenir les statistiques de cache
     */
    public function getCacheStats()
    {
        // Pour le cache de fichiers, on ne peut pas facilement obtenir les statistiques
        // On retourne des informations basiques
        return [
            [
                'key' => 'eklektik_cache_info',
                'ttl' => $this->cacheDuration,
                'expires_in' => Carbon::now()->addSeconds($this->cacheDuration)->diffForHumans(),
                'note' => 'Cache de fichiers - statistiques limitées'
            ]
        ];
    }

    /**
     * Récupérer l'évolution des revenus par opérateur
     */
    public function getCachedOperatorsRevenueEvolution($startDate, $endDate)
    {
        $cacheKey = "eklektik_operators_revenue_evolution_{$startDate}_{$endDate}";
        $ttl = $this->getCacheTTL($startDate, $endDate);
        
        return Cache::remember($cacheKey, $ttl, function() use ($startDate, $endDate) {
            $stats = EklektikStatsDaily::whereBetween('date', [$startDate, $endDate])
                ->selectRaw('
                    date,
                    SUM(CASE WHEN operator = "TT" THEN ca_bigdeal ELSE 0 END) as tt_revenue,
                    SUM(CASE WHEN operator = "Taraji" THEN ca_bigdeal ELSE 0 END) as taraji_revenue,
                    SUM(CASE WHEN operator = "Orange" THEN ca_bigdeal ELSE 0 END) as orange_revenue,
                    SUM(ca_bigdeal) as total_ca_bigdeal
                ')
                ->groupBy('date')
                ->orderBy('date')
                ->get();

            return $stats->map(function($stat) {
                return [
                    'date' => $stat->date,
                    'tt_revenue' => (float) $stat->tt_revenue,
                    'taraji_revenue' => (float) $stat->taraji_revenue,
                    'orange_revenue' => (float) $stat->orange_revenue,
                    'total_ca_bigdeal' => (float) $stat->total_ca_bigdeal,
                ];
            });
        });
    }
}
