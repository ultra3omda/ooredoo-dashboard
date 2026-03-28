<?php

namespace App\Services\Dashboard;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Traits\TransactionHelper;
use App\Models\TimweDailyStat;
use App\Services\TimweStatsService;

class StatisticsService
{
    use TransactionHelper;

    protected TimweStatsService $timweStatsService;

    public function __construct(TimweStatsService $timweStatsService)
    {
        $this->timweStatsService = $timweStatsService;
    }

    public function getOoredooDailyStatistics(Carbon $startBound, Carbon $endExclusive): array
    {
        try {
            $endDate = $endExclusive->copy()->subDay();
            $periodDays = $startBound->diffInDays($endDate) + 1;
            
            Log::info("getOoredooDailyStatistics - Récupération depuis cache", [
                'period_days' => $periodDays,
                'start' => $startBound->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d')
            ]);
            
            $stats = \App\Models\OoredooDailyStat::getStatsForPeriod($startBound, $endDate);

            if ($stats->isEmpty()) {
                Log::warning("getOoredooDailyStatistics - Aucune donnée trouvée");
                return [];
            }

            Log::info("getOoredooDailyStatistics - Stats récupérées: " . $stats->count() . " jours");
            return $stats->toArray();
        } catch (\Exception $e) {
            Log::error("getOoredooDailyStatistics - Erreur: " . $e->getMessage());
            return [];
        }
    }

    public function getDailyStatistics(Carbon $startBound, Carbon $endExclusive, string $selectedOperator): array
    {
        try {
            $endDate = $endExclusive->copy()->subDay();
            $periodDays = $startBound->diffInDays($endDate) + 1;
            
            Log::info("getDailyStatistics - Récupération depuis cache Timwe", [
                'period_days' => $periodDays,
                'start' => $startBound->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d')
            ]);
            
            $stats = TimweDailyStat::getStatsForPeriod($startBound, $endDate);
            $missingDays = $periodDays - $stats->count();
            
            if ($missingDays > 0 && $missingDays <= 7 && $periodDays <= 30) {
                Log::info("getDailyStatistics - Calcul des jours manquants", ['missing_days' => $missingDays]);
                
                $existingDates = $stats->pluck('stat_date')->map(fn($date) => $date->format('Y-m-d'))->toArray();
                
                $currentDate = $startBound->copy();
                while ($currentDate->lte($endDate)) {
                    if (!in_array($currentDate->format('Y-m-d'), $existingDates)) {
                        $this->timweStatsService->calculateAndStoreStatsForDate($currentDate);
                    }
                    $currentDate->addDay();
                }
                
                $stats = TimweDailyStat::getStatsForPeriod($startBound, $endDate);
            } elseif ($missingDays > 0) {
                Log::warning("getDailyStatistics - Données Timwe incomplètes", [
                    'missing_days' => $missingDays,
                    'found_days' => $stats->count(),
                    'expected_days' => $periodDays
                ]);
            }

            if ($stats->isNotEmpty()) {
                $dailyStats = [];
                
                foreach ($stats as $stat) {
                    $offersBreakdown = $stat->offers_breakdown ?? [];
                    
                    if (!empty($offersBreakdown)) {
                        foreach ($offersBreakdown as $offer) {
                            $dailyStats[] = [
                                'dimension' => $stat->stat_date->format('Y-m-d'),
                                'offre' => $offer->offre_name ?? 'N/A',
                                'new_sub' => $offer->count ?? 0,
                                'unsub' => 0,
                                'simchurn' => 0,
                                'rev_simchurn' => 0,
                                'active_sub' => $stat->active_subscriptions,
                                'nb_facturation' => $stat->total_billings,
                                'taux_facturation' => $stat->billing_rate,
                                'revenu_ttc_local' => $stat->revenue_tnd,
                                'revenu_ttc_usd' => $stat->revenue_usd,
                                'revenu_ttc_tnd' => $stat->revenue_tnd
                            ];
                        }
                    } else {
                        $dailyStats[] = [
                            'dimension' => $stat->stat_date->format('Y-m-d'),
                            'offre' => 'Timwe (Total)',
                            'new_sub' => $stat->new_subscriptions,
                            'unsub' => $stat->unsubscriptions,
                            'simchurn' => $stat->simchurn,
                            'rev_simchurn' => $stat->simchurn_revenue,
                            'active_sub' => $stat->active_subscriptions,
                            'nb_facturation' => $stat->total_billings,
                            'taux_facturation' => $stat->billing_rate,
                            'revenu_ttc_local' => $stat->revenue_tnd,
                            'revenu_ttc_usd' => $stat->revenue_usd,
                            'revenu_ttc_tnd' => $stat->revenue_tnd
                        ];
                    }
                }

                return $dailyStats;
            }

            $periodDays = $startBound->diffInDays($endExclusive);
            if ($periodDays > 90) return [];

            return $this->computeDailyStatisticsLive($startBound, $endExclusive);
        } catch (\Exception $e) {
            Log::error("Erreur lors de la récupération des statistiques quotidiennes: " . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return [];
        }
    }

    private function computeDailyStatisticsLive(Carbon $startBound, Carbon $endExclusive): array
    {
        $billingPpid = env('TIMWE_BILLING_PPID', '63980');
        
        $timweOperatorIds = DB::table('country_payments_methods')
            ->whereRaw("LOWER(country_payments_methods_name) LIKE ?", ['%timwe%'])
            ->pluck('country_payments_methods_id')
            ->toArray();
        
        if (empty($timweOperatorIds)) return [];
        
        // 1. New subs
        $newSubsByDay = [];
        $rows = DB::table('client_abonnement as ca')
            ->whereIn('ca.country_payments_methods_id', $timweOperatorIds)
            ->whereBetween('ca.client_abonnement_creation', [$startBound, $endExclusive->copy()->subSecond()])
            ->select(DB::raw('DATE(ca.client_abonnement_creation) as date'), DB::raw('COUNT(*) as count'))
            ->groupBy(DB::raw('DATE(ca.client_abonnement_creation)'))
            ->get();
        foreach ($rows as $row) {
            $newSubsByDay[Carbon::parse($row->date)->format('Y-m-d')] = (int)$row->count;
        }
        
        // 2. Unsubs
        $unsubsByDay = [];
        $rows = DB::table('client_abonnement as ca')
            ->whereIn('ca.country_payments_methods_id', $timweOperatorIds)
            ->whereNotNull('ca.client_abonnement_expiration')
            ->whereBetween('ca.client_abonnement_expiration', [$startBound, $endExclusive->copy()->subSecond()])
            ->select(DB::raw('DATE(ca.client_abonnement_expiration) as date'), DB::raw('COUNT(*) as count'))
            ->groupBy(DB::raw('DATE(ca.client_abonnement_expiration)'))
            ->get();
        foreach ($rows as $row) {
            $unsubsByDay[Carbon::parse($row->date)->format('Y-m-d')] = (int)$row->count;
        }
        
        // 3. Simchurn
        $simchurnByDay = [];
        $simchurnRevenueByDay = [];
        $simchurnRows = DB::table('client_abonnement as ca')
            ->whereIn('ca.country_payments_methods_id', $timweOperatorIds)
            ->whereBetween('ca.client_abonnement_creation', [$startBound, $endExclusive->copy()->subSecond()])
            ->whereNotNull('ca.client_abonnement_expiration')
            ->whereColumn(DB::raw('DATE(ca.client_abonnement_creation)'), DB::raw('DATE(ca.client_abonnement_expiration)'))
            ->select(DB::raw('DATE(ca.client_abonnement_creation) as date'), 'ca.client_abonnement_id', 'ca.client_id')
            ->get();
        
        foreach ($simchurnRows as $row) {
            $dateKey = Carbon::parse($row->date)->format('Y-m-d');
            $simchurnByDay[$dateKey] = ($simchurnByDay[$dateKey] ?? 0) + 1;
            if (!isset($simchurnRevenueByDay[$dateKey])) $simchurnRevenueByDay[$dateKey] = 0;
            
            $tx = DB::table('transactions_history as th')
                ->where('th.client_id', $row->client_id)
                ->where(function($q) { $q->where('th.status', 'LIKE', '%TIMWE_RENEWED_NOTIF%')->orWhere('th.status', 'LIKE', '%TIMWE_CHARGE_DELIVERED%'); })
                ->whereDate('th.created_at', $dateKey)
                ->orderBy('th.created_at', 'desc')
                ->first();
            
            if ($tx && $tx->result) {
                $ppid = $this->extractPricepointId($tx->result);
                $isDelivered = $this->isTransactionDelivered($tx->result);
                $totalCharged = $this->extractTotalCharged($tx->result);
                if ($ppid === $billingPpid && $isDelivered && $totalCharged > 0) {
                    $simchurnRevenueByDay[$dateKey] += $totalCharged;
                }
            }
        }
        
        // 4. Billings
        $billingsByDay = [];
        $revenueByDay = [];
        $chunkSize = 500;
        $hasMore = true;
        $lastId = 0;
        
        while ($hasMore) {
            $billingsRaw = DB::table('transactions_history as th')
                ->join('client_abonnement as ca', 'th.client_id', '=', 'ca.client_id')
                ->leftJoin('abonnement_tarifs as at', 'ca.tarif_id', '=', 'at.abonnement_tarifs_id')
                ->whereIn('ca.country_payments_methods_id', $timweOperatorIds)
                ->whereBetween('th.created_at', [$startBound, $endExclusive->copy()->subSecond()])
                ->where('th.transaction_history_id', '>', $lastId)
                ->where(function($q) {
                    $q->where('th.status', 'LIKE', '%TIMWE_RENEWED_NOTIF%')
                      ->orWhere('th.status', 'LIKE', '%TIMWE_CHARGE_DELIVERED%')
                      ->orWhere('th.status', 'LIKE', '%RENEWED%')
                      ->orWhere('th.status', 'LIKE', '%CHARGE_DELIVERED%');
                })
                ->select('th.transaction_history_id', DB::raw('DATE(th.created_at) as date'), 'th.result', 'at.abonnement_tarifs_prix as tarif_prix')
                ->orderBy('th.transaction_history_id', 'asc')
                ->limit($chunkSize)
                ->get();
            
            if ($billingsRaw->isEmpty()) { $hasMore = false; break; }
            
            foreach ($billingsRaw as $billing) {
                $lastId = $billing->transaction_history_id;
                $ppid = $this->extractPricepointId($billing->result);
                $isDelivered = $this->isTransactionDelivered($billing->result);
                $totalCharged = $this->extractTotalCharged($billing->result);
                
                if ($ppid === $billingPpid && $isDelivered && $totalCharged > 0) {
                    $date = Carbon::parse($billing->date)->format('Y-m-d');
                    $billingsByDay[$date] = ($billingsByDay[$date] ?? 0) + 1;
                    $revenueByDay[$date] = ($revenueByDay[$date] ?? 0) + $totalCharged;
                }
            }
            
            $count = $billingsRaw->count();
            unset($billingsRaw);
            if ($count < $chunkSize) $hasMore = false;
        }
        
        // 5. Active subs by day
        $endDate = $endExclusive->copy()->subDay();
        $activeSubsByDayRaw = [];
        $currentDateForActive = $startBound->copy();
        while ($currentDateForActive->lte($endDate)) {
            $dateStr = $currentDateForActive->format('Y-m-d');
            $endOfDay = $currentDateForActive->copy()->endOfDay();
            $activeCount = DB::table('client_abonnement as ca')
                ->whereIn('ca.country_payments_methods_id', $timweOperatorIds)
                ->where('ca.client_abonnement_creation', '<=', $endOfDay)
                ->where(function($q) use ($endOfDay) { $q->whereNull('ca.client_abonnement_expiration')->orWhere('ca.client_abonnement_expiration', '>', $endOfDay); })
                ->count();
            $activeSubsByDayRaw[$dateStr] = (int)$activeCount;
            $currentDateForActive->addDay();
        }
        
        // 6. Offers by day
        $offersByDay = [];
        $offerRows = DB::table('client_abonnement as ca')
            ->leftJoin('abonnement_tarifs as at', 'ca.tarif_id', '=', 'at.abonnement_tarifs_id')
            ->leftJoin('abonnement as a', 'at.abonnement_id', '=', 'a.abonnement_id')
            ->whereIn('ca.country_payments_methods_id', $timweOperatorIds)
            ->whereBetween('ca.client_abonnement_creation', [$startBound, $endExclusive->copy()->subSecond()])
            ->select(DB::raw('DATE(ca.client_abonnement_creation) as date'), DB::raw('MAX(a.abonnement_nom) as offer_name'))
            ->groupBy(DB::raw('DATE(ca.client_abonnement_creation)'))
            ->get();
        foreach ($offerRows as $row) {
            $offersByDay[Carbon::parse($row->date)->format('Y-m-d')] = $row->offer_name ?? 'N/A';
        }
        
        // Build final
        $statistics = [];
        $currentDate = $startBound->copy();
        $loopStartTs = microtime(true);
        
        while ($currentDate->lte($endDate)) {
            $dateStr = $currentDate->format('Y-m-d');
            $activeSubs = $activeSubsByDayRaw[$dateStr] ?? 0;
            $nbFacturation = $billingsByDay[$dateStr] ?? 0;
            $revenuTTC = $revenueByDay[$dateStr] ?? 0;
            $revSimchurn = $simchurnRevenueByDay[$dateStr] ?? 0;
            
            $statistics[] = [
                'dimension' => $dateStr,
                'offre' => $offersByDay[$dateStr] ?? 'N/A',
                'new_sub' => (int)($newSubsByDay[$dateStr] ?? 0),
                'unsub' => (int)($unsubsByDay[$dateStr] ?? 0),
                'simchurn' => (int)($simchurnByDay[$dateStr] ?? 0),
                'rev_simchurn' => round($revSimchurn, 2),
                'active_sub' => (int)$activeSubs,
                'nb_facturation' => (int)$nbFacturation,
                'taux_facturation' => $activeSubs > 0 ? round(($nbFacturation / $activeSubs) * 100, 2) : 0,
                'revenu_ttc_local' => round($revenuTTC, 2),
                'revenu_ttc_usd' => round($revenuTTC * 0.343, 2),
                'revenu_ttc_tnd' => round($revenuTTC, 2)
            ];
            
            $currentDate->addDay();
            if ((microtime(true) - $loopStartTs) > 10) break;
        }
        
        return $statistics;
    }

    public function groupTimweStatsByMonth(array $dailyStats): array
    {
        if (empty($dailyStats)) return [];
        
        $grouped = [];
        $totalStats = count($dailyStats);
        $includeDetails = $totalStats < 500;
        
        foreach ($dailyStats as $stat) {
            $date = Carbon::parse($stat['dimension']);
            $monthKey = $date->format('Y-m');
            $monthLabel = $date->locale('fr')->isoFormat('MMMM YYYY');
            
            if (!isset($grouped[$monthKey])) {
                $grouped[$monthKey] = [
                    'month_key' => $monthKey, 'month_label' => $monthLabel,
                    'year' => $date->year, 'month_num' => $date->month, 'daily_details' => [],
                    'total_new_sub' => 0, 'total_unsub' => 0, 'total_simchurn' => 0,
                    'total_rev_simchurn' => 0, 'total_active_sub' => 0,
                    'total_nb_facturation' => 0, 'total_taux_facturation' => 0,
                    'sum_taux_facturation' => 0, 'total_revenu_ttc_tnd' => 0,
                    'ca_bigdeal_ht' => 0, 'days_count' => 0
                ];
            }
            
            if ($includeDetails) $grouped[$monthKey]['daily_details'][] = $stat;
            
            $grouped[$monthKey]['total_new_sub'] += floatval($stat['new_sub'] ?? 0);
            $grouped[$monthKey]['total_unsub'] += floatval($stat['unsub'] ?? 0);
            $grouped[$monthKey]['total_simchurn'] += floatval($stat['simchurn'] ?? 0);
            $grouped[$monthKey]['total_rev_simchurn'] += floatval($stat['rev_simchurn'] ?? 0);
            $grouped[$monthKey]['total_nb_facturation'] += floatval($stat['nb_facturation'] ?? 0);
            $grouped[$monthKey]['sum_taux_facturation'] += floatval($stat['taux_facturation'] ?? 0);
            $grouped[$monthKey]['total_revenu_ttc_tnd'] += floatval($stat['revenu_ttc_tnd'] ?? 0);
            $grouped[$monthKey]['days_count']++;
            $grouped[$monthKey]['total_active_sub'] = floatval($stat['active_sub'] ?? 0);
        }
        
        foreach ($grouped as $monthKey => &$month) {
            if ($month['days_count'] > 0) {
                $month['total_taux_facturation'] = $month['sum_taux_facturation'] / $month['days_count'];
            }
            
            $nbFacturation = $month['total_nb_facturation'];
            if ($nbFacturation < 100000) $month['ca_bigdeal_ht'] = $nbFacturation * 1.2;
            elseif ($nbFacturation < 250000) $month['ca_bigdeal_ht'] = $nbFacturation * 1.0;
            else $month['ca_bigdeal_ht'] = 250000;
            
            $month['display_label'] = $month['month_label'] . ' (' . $month['days_count'] . ')';
            unset($month['sum_taux_facturation']);
        }
        
        krsort($grouped);
        return array_values($grouped);
    }

    public function groupOoredooStatsByMonth(array $dailyStats): array
    {
        if (empty($dailyStats)) return [];
        
        $grouped = [];
        $totalStats = count($dailyStats);
        $includeDetails = $totalStats < 500;
        
        foreach ($dailyStats as $stat) {
            $date = Carbon::parse($stat['stat_date']);
            $monthKey = $date->format('Y-m');
            $monthLabel = $date->locale('fr')->isoFormat('MMMM YYYY');
            
            if (!isset($grouped[$monthKey])) {
                $grouped[$monthKey] = [
                    'month_key' => $monthKey, 'month_label' => $monthLabel,
                    'year' => $date->year, 'month_num' => $date->month, 'daily_details' => [],
                    'total_new_sub' => 0, 'total_unsub' => 0, 'total_active_sub' => 0,
                    'total_nb_facturation' => 0, 'total_taux_facturation' => 0,
                    'sum_taux_facturation' => 0, 'total_revenu_tnd' => 0, 'days_count' => 0
                ];
            }
            
            if ($includeDetails) $grouped[$monthKey]['daily_details'][] = $stat;
            
            $grouped[$monthKey]['total_new_sub'] += floatval($stat['new_subscriptions'] ?? 0);
            $grouped[$monthKey]['total_unsub'] += floatval($stat['unsubscriptions'] ?? 0);
            $grouped[$monthKey]['total_nb_facturation'] += floatval($stat['total_billings'] ?? 0);
            $grouped[$monthKey]['total_revenu_tnd'] += floatval($stat['revenue_tnd'] ?? 0);
            $grouped[$monthKey]['sum_taux_facturation'] += floatval($stat['billing_rate'] ?? 0);
            $grouped[$monthKey]['total_active_sub'] = floatval($stat['active_subscriptions'] ?? 0);
            $grouped[$monthKey]['days_count']++;
        }
        
        foreach ($grouped as $monthKey => &$month) {
            if ($month['days_count'] > 0) {
                $month['total_taux_facturation'] = $month['sum_taux_facturation'] / $month['days_count'];
            }
            $month['display_label'] = $month['month_label'] . ' (' . $month['days_count'] . ')';
            unset($month['sum_taux_facturation']);
        }
        
        krsort($grouped);
        return array_values($grouped);
    }
}
