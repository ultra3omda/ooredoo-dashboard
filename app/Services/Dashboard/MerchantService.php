<?php

namespace App\Services\Dashboard;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Traits\OperatorHelper;

class MerchantService
{
    use OperatorHelper;

    public function getMerchants(Carbon $startBound, Carbon $endExclusive, Carbon $compStartBound, Carbon $compEndExclusive, string $selectedOperator): array
    {
        $merchantsQuery = DB::table('history as h')
            ->join('client_abonnement as ca', 'h.client_abonnement_id', '=', 'ca.client_abonnement_id')
            ->join('promotion as p', 'h.promotion_id', '=', 'p.promotion_id')
            ->join('partner as pt', 'p.partner_id', '=', 'pt.partner_id')
            ->selectRaw(
                "pt.partner_name as name, pt.partner_id,
                 COUNT(CASE WHEN h.time >= ? AND h.time < ? THEN 1 END) as `current`,
                 COUNT(CASE WHEN h.time >= ? AND h.time < ? THEN 1 END) as previous",
                [$startBound, $endExclusive, $compStartBound, $compEndExclusive]
            )
            ->whereNotNull('h.promotion_id');
        
        $this->applyOperatorJoinAndFilter($merchantsQuery, $selectedOperator, 'ca');
        
        $merchants = $merchantsQuery
            ->groupBy('pt.partner_name', 'pt.partner_id')
            ->having('current', '>', 0)
            ->orderBy('current', 'DESC')
            ->limit(50)
            ->get();
        
        $totalTransactions = $merchants->sum('current');
        $partnerIds = $merchants->pluck('partner_id')->toArray();
        $realCategories = $this->getPartnerCategoriesBatch($partnerIds);
        
        $enrichedMerchants = $merchants->map(function($merchant) use ($totalTransactions, $realCategories) {
            $category = $realCategories[$merchant->partner_id] ?? $this->categorizePartner($merchant->name ?? 'Unknown');
            $share = $totalTransactions > 0 ? round(($merchant->current / $totalTransactions) * 100, 1) : 0;
            
            return [
                'name' => $merchant->name ?? 'Unknown',
                'category' => $category,
                'current' => (int)$merchant->current,
                'previous' => (int)$merchant->previous,
                'share' => $share,
                'partner_id' => $merchant->partner_id
            ];
        })->toArray();
        
        $categoryDistribution = $this->calculateCategoryDistribution($enrichedMerchants, $totalTransactions);
        
        return [
            'data' => $enrichedMerchants,
            'categories' => $categoryDistribution
        ];
    }

    public function calculateMerchantKPIs(Carbon $startBound, Carbon $endExclusive, Carbon $compStartBound, Carbon $compEndExclusive, string $selectedOperator, int $transactionsCurrent, int $transactionsComparison): array
    {
        // Pré-calculer les sets de partenaires éligibles (avec promo active + location)
        $promoActivePartners = DB::table('promotion')->where('promotion_active', 1)->distinct()->pluck('partner_id');
        $locationPartners = DB::table('partner_location')->distinct()->pluck('partner_id');
        
        $activeMerchantsQuery = DB::table('history as h')
            ->join('client_abonnement as ca', 'h.client_abonnement_id', '=', 'ca.client_abonnement_id')
            ->join('promotion as p', 'h.promotion_id', '=', 'p.promotion_id')
            ->join('partner as pt', 'p.partner_id', '=', 'pt.partner_id')
            ->where('h.time', '>=', $startBound)
            ->where('h.time', '<', $endExclusive)
            ->whereNotNull('h.promotion_id')
            ->whereIn('pt.partner_id', $promoActivePartners)
            ->whereIn('pt.partner_id', $locationPartners);
        $this->applyOperatorJoinAndFilter($activeMerchantsQuery, $selectedOperator, 'ca');
        $activeMerchants = $activeMerchantsQuery->distinct('pt.partner_id')->count('pt.partner_id');
        
        $activeMerchantsComparisonQuery = DB::table('history as h')
            ->join('client_abonnement as ca', 'h.client_abonnement_id', '=', 'ca.client_abonnement_id')
            ->join('promotion as p', 'h.promotion_id', '=', 'p.promotion_id')
            ->join('partner as pt', 'p.partner_id', '=', 'pt.partner_id')
            ->where('h.time', '>=', $compStartBound)
            ->where('h.time', '<', $compEndExclusive)
            ->whereNotNull('h.promotion_id')
            ->whereIn('pt.partner_id', $promoActivePartners)
            ->whereIn('pt.partner_id', $locationPartners);
        $this->applyOperatorJoinAndFilter($activeMerchantsComparisonQuery, $selectedOperator, 'ca');
        $activeMerchantsComparison = $activeMerchantsComparisonQuery->distinct('pt.partner_id')->count('pt.partner_id');
        
        $totalActivePartnersDB = DB::table('partner')->where('partener_active', 1)->count();
        // Total Merchants = Partenaires avec au moins 1 promotion active ET au moins 1 point de vente
        // Aligné avec la logique de clubprivileges.app
        $totalAllPartners = DB::table('promotion')
            ->where('promotion_active', 1)
            ->whereIn('partner_id', DB::table('partner_location')->distinct()->pluck('partner_id'))
            ->distinct('partner_id')
            ->count('partner_id');
        $totalPartners = max($totalAllPartners, $activeMerchants);
        
        $totalMerchantsEverActive = DB::table('history as h')
            ->join('promotion as p', 'h.promotion_id', '=', 'p.promotion_id')
            ->distinct('p.partner_id')
            ->count('p.partner_id');
        
        $allTransactionsPeriod = DB::table('history')
            ->where('time', '>=', $startBound)
            ->where('time', '<', $endExclusive)
            ->count();
        
        $totalLocationsActive = 0;
        try {
            // Compter les POS des partenaires avec au moins 1 promotion active
            $totalLocationsActive = DB::table('partner_location as pl')
                ->whereIn('pl.partner_id', DB::table('promotion')->where('promotion_active', 1)->distinct()->pluck('partner_id'))
                ->count();
        } catch (\Exception $e) {
            Log::warning('Impossible de calculer totalLocationsActive', ['error' => $e->getMessage()]);
        }
        
        $transactionsPerMerchant = $activeMerchants > 0 ? round($transactionsCurrent / $activeMerchants, 1) : 0;
        $transactionsPerMerchantComparison = $activeMerchantsComparison > 0 ? round($transactionsComparison / $activeMerchantsComparison, 1) : 0;
        
        return [
            "activeMerchants" => ["current" => $activeMerchants, "previous" => $activeMerchantsComparison, "change" => $this->calculatePercentageChange($activeMerchants, $activeMerchantsComparison)],
            "activeMerchantRatio" => [
                "current" => $totalPartners > 0 ? round(($activeMerchants / $totalPartners) * 100, 1) : 0,
                "previous" => $totalPartners > 0 ? round(($activeMerchantsComparison / $totalPartners) * 100, 1) : 0,
                "change" => $this->calculatePercentageChange(
                    $totalPartners > 0 ? round(($activeMerchants / $totalPartners) * 100, 1) : 0,
                    $totalPartners > 0 ? round(($activeMerchantsComparison / $totalPartners) * 100, 1) : 0
                )
            ],
            "totalPartners" => ["current" => $totalPartners, "previous" => $totalPartners, "change" => 0.0],
            "totalActivePartnersDB" => ["current" => $totalActivePartnersDB, "previous" => $totalActivePartnersDB, "change" => 0.0],
            "totalLocationsActive" => ["current" => $totalLocationsActive, "previous" => $totalLocationsActive, "change" => 0.0],
            "totalMerchantsEverActive" => $totalMerchantsEverActive,
            "allTransactionsPeriod" => $allTransactionsPeriod,
            "transactionsPerMerchant" => ["current" => $transactionsPerMerchant, "previous" => $transactionsPerMerchantComparison, "change" => $this->calculatePercentageChange($transactionsPerMerchant, $transactionsPerMerchantComparison)]
        ];
    }

    public function generateInsights(array $kpis, array $merchants): array
    {
        $positive = [];
        $challenges = [];
        $recommendations = [];
        
        if ($kpis['activatedSubscriptions']['change'] > 50) {
            $positive[] = "Excellente croissance des abonnements (+{$kpis['activatedSubscriptions']['change']}%)";
        }
        if ($kpis['retentionRate']['current'] > 80) {
            $positive[] = "Taux de rétention élevé de {$kpis['retentionRate']['current']}%";
        }
        
        if ($kpis['conversionRate']['current'] < 10) {
            $challenges[] = "Taux de conversion faible ({$kpis['conversionRate']['current']}%) à améliorer";
        }
        if (count($merchants) < 5) {
            $challenges[] = "Réseau de marchands limité (" . count($merchants) . " actifs)";
        }
        
        $recommendations[] = "Optimiser l'expérience utilisateur pour améliorer la conversion";
        $recommendations[] = "Développer le réseau de partenaires marchands";
        
        return [
            "positive" => $positive,
            "challenges" => $challenges,
            "recommendations" => $recommendations,
            "nextSteps" => ["Analyser les parcours utilisateurs", "Lancer des campagnes d'engagement"]
        ];
    }

    public function categorizePartner(string $partnerName): string
    {
        $name = strtoupper($partnerName);
        
        if (str_contains($name, 'KFC') || str_contains($name, 'RESTAURANT') || str_contains($name, 'PIZZA')) return 'Food & Beverage';
        if (str_contains($name, 'BEAUTY') || str_contains($name, 'SPA') || str_contains($name, 'SALON')) return 'Beauty & Wellness';
        if (str_contains($name, 'CLUB') || str_contains($name, 'BAR') || str_contains($name, 'LOUNGE')) return 'Entertainment';
        if (str_contains($name, 'GYM') || str_contains($name, 'FITNESS') || str_contains($name, 'SPORT')) return 'Fitness & Sports';
        if (str_contains($name, 'SHOP') || str_contains($name, 'STORE') || str_contains($name, 'CENTER')) return 'Retail';
        
        return 'Others';
    }

    private function getPartnerCategoriesBatch(array $partnerIds): array
    {
        if (empty($partnerIds)) return [];
        
        $categories = [];
        
        try {
            if (Schema::hasColumn('partner', 'partner_category_id') && 
                Schema::hasTable('partner_category') && 
                Schema::hasColumn('partner_category', 'partner_category_name')) {
                
                $results = DB::table('partner')
                    ->leftJoin('partner_category', 'partner.partner_category_id', '=', 'partner_category.partner_category_id')
                    ->whereIn('partner.partner_id', $partnerIds)
                    ->select('partner.partner_id', 'partner_category.partner_category_name as category')
                    ->get();
                
                foreach ($results as $result) {
                    if ($result->category && trim($result->category) !== '') {
                        $categories[$result->partner_id] = trim($result->category);
                    }
                }
            }
            
            $missingIds = array_diff($partnerIds, array_keys($categories));
            if (!empty($missingIds)) {
                foreach (['partner_category', 'category', 'business_category', 'sector', 'industry', 'partner_type'] as $column) {
                    if (Schema::hasColumn('partner', $column)) {
                        $results = DB::table('partner')
                            ->whereIn('partner_id', $missingIds)
                            ->select('partner_id', $column . ' as category')
                            ->get();
                        
                        foreach ($results as $result) {
                            if ($result->category && trim($result->category) !== '' && !isset($categories[$result->partner_id])) {
                                $categories[$result->partner_id] = trim($result->category);
                            }
                        }
                        
                        $missingIds = array_diff($missingIds, array_keys($categories));
                        if (empty($missingIds)) break;
                    }
                }
            }
            
            $missingIds = array_diff($partnerIds, array_keys($categories));
            if (!empty($missingIds)) {
                $partners = DB::table('partner')->whereIn('partner_id', $missingIds)->select('partner_id', 'partner_name')->get();
                foreach ($partners as $partner) {
                    $categories[$partner->partner_id] = $this->categorizePartner($partner->partner_name ?? 'Unknown');
                }
            }
        } catch (\Exception $e) {
            Log::warning("Erreur lors de la récupération des catégories batch: " . $e->getMessage());
            $partners = DB::table('partner')->whereIn('partner_id', $partnerIds)->select('partner_id', 'partner_name')->get();
            foreach ($partners as $partner) {
                $categories[$partner->partner_id] = $this->categorizePartner($partner->partner_name ?? 'Unknown');
            }
        }
        
        return $categories;
    }

    private function calculateCategoryDistribution(array $merchants, int $totalTransactions): array
    {
        $categories = [];
        
        foreach ($merchants as $merchant) {
            $category = $merchant['category'] ?? 'Others';
            if (!isset($categories[$category])) {
                $categories[$category] = ['transactions' => 0, 'merchants' => 0];
            }
            $categories[$category]['transactions'] += (int)($merchant['current'] ?? 0);
            $categories[$category]['merchants']++;
        }
        
        $distribution = [];
        foreach ($categories as $category => $data) {
            $percentage = $totalTransactions > 0 ? round(($data['transactions'] / $totalTransactions) * 100, 1) : 0;
            $distribution[] = [
                'category' => $category,
                'transactions' => (int)$data['transactions'],
                'merchants' => (int)$data['merchants'],
                'percentage' => $percentage
            ];
        }
        
        usort($distribution, fn($a, $b) => $b['transactions'] - $a['transactions']);
        
        return $distribution;
    }
}
