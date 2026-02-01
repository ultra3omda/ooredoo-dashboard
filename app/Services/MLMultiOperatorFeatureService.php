<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class MLMultiOperatorFeatureService
{
    /**
     * Extrait les features pour tous les opérateurs (Timwe, Eklektik, Ooredoo/DGV)
     */
    public function extractClientFeatures(int $clientId, Carbon $calculationDate): array
    {
        Log::info("MLMultiOperatorFeatureService - Extraction multi-opérateur pour client $clientId");

        $features = [
            'client_id' => $clientId,
            'calculation_date' => $calculationDate->toDateString(),
        ];

        $startDate = $calculationDate->copy()->subMonths(6);

        try {
            // === 1. Features par Opérateur ===
            $timweFeatures = $this->extractTimweFeatures($clientId, $startDate, $calculationDate);
            $eklektikFeatures = $this->extractEklektikFeatures($clientId, $startDate, $calculationDate);
            $ooredooFeatures = $this->extractOoredooFeatures($clientId, $startDate, $calculationDate);
            
            // === 2. Features Cross-Opérateur ===
            $crossFeatures = $this->extractCrossOperatorFeatures($clientId, $startDate, $calculationDate);
            
            // === 3. Features par Type d'Offre ===
            $offerTypeFeatures = $this->extractOfferTypeFeatures($clientId, $startDate, $calculationDate);
            
            // === 4. Features de Préférence Client ===
            $preferenceFeatures = $this->extractClientPreferences($clientId, $startDate, $calculationDate);

            // Fusionner toutes les features
            $features = array_merge(
                $features,
                $timweFeatures,
                $eklektikFeatures, 
                $ooredooFeatures,
                $crossFeatures,
                $offerTypeFeatures,
                $preferenceFeatures
            );

        } catch (\Exception $e) {
            Log::error("MLMultiOperatorFeatureService - Erreur extraction client $clientId", [
                'error' => $e->getMessage()
            ]);
            
            $features = $this->getDefaultMultiOperatorFeatures($clientId, $calculationDate);
        }

        return $features;
    }

    /**
     * Features spécifiques Timwe (mensuel 3.0 TND)
     */
    private function extractTimweFeatures(int $clientId, Carbon $startDate, Carbon $endDate): array
    {
        $billingPpid = env('TIMWE_BILLING_PPID', '63980');
        
        $transactions = DB::table('transactions_history as th')
            ->where('th.client_id', $clientId)
            ->where(function($q) {
                $q->where('th.status', 'LIKE', '%TIMWE_RENEWED_NOTIF%')
                  ->orWhere('th.status', 'LIKE', '%TIMWE_CHARGE_DELIVERED%');
            })
            ->whereBetween('th.created_at', [$startDate, $endDate])
            ->whereNotNull('th.result')
            ->get();

        $successes = 0;
        $attempts = count($transactions);
        $totalRevenue = 0;
        $noBalanceCount = 0;
        $notDeliveredCount = 0;

        foreach ($transactions as $transaction) {
            $result = json_decode($transaction->result, true);
            if (!is_array($result)) continue;
            
            $ppid = $result['pricepointId'] ?? null;
            $delivery = $result['mnoDeliveryCode'] ?? null;
            $charged = isset($result['totalCharged']) ? (int)$result['totalCharged'] : 0;
            
            if ((string)$ppid === (string)$billingPpid && $delivery === 'DELIVERED' && $charged > 0) {
                $successes++;
                $totalRevenue += $charged / 1000; // Millimes → TND
            }
            
            if ($delivery === 'NO_BALANCE') $noBalanceCount++;
            if ($delivery === 'NOT_DELIVERED') $notDeliveredCount++;
        }

        $successRate = $attempts > 0 ? $successes / $attempts : 0;
        $avgRevenue = $successes > 0 ? $totalRevenue / $successes : 0;
        $noBalanceRate = $attempts > 0 ? $noBalanceCount / $attempts : 0;

        return [
            'timwe_success_rate' => round($successRate, 4),
            'timwe_total_attempts' => $attempts,
            'timwe_total_successes' => $successes,
            'timwe_avg_revenue_per_success' => round($avgRevenue, 3),
            'timwe_no_balance_rate' => round($noBalanceRate, 4),
            'timwe_not_delivered_rate' => round($attempts > 0 ? $notDeliveredCount / $attempts : 0, 4),
            'timwe_has_activity' => $attempts > 0 ? 1 : 0,
        ];
    }

    /**
     * Features spécifiques Eklektik (quotidien 0.3 TND Club Privilèges)
     */
    private function extractEklektikFeatures(int $clientId, Carbon $startDate, Carbon $endDate): array
    {
        // Vérifier dans eklektik_stats_daily et transactions history pour Eklektik
        $eklektikTransactions = DB::table('transactions_history as th')
            ->where('th.client_id', $clientId)
            ->where(function($q) {
                $q->where('th.status', 'LIKE', '%EKLEKTIK%')
                  ->orWhere('th.status', 'LIKE', '%CLUB_PRIVILEGE%')
                  ->orWhere('th.status', 'LIKE', '%DAILY%');
            })
            ->whereBetween('th.created_at', [$startDate, $endDate])
            ->get();

        // Aussi vérifier les abonnements Eklektik 
        $eklektikSubscriptions = DB::table('client_abonnement as ca')
            ->join('country_payments_methods as cpm', 'ca.country_payments_methods_id', '=', 'cpm.country_payments_methods_id')
            ->where('ca.client_id', $clientId)
            ->where(function($q) {
                $q->whereRaw("LOWER(cpm.country_payments_methods_name) LIKE '%eklektik%'")
                  ->orWhere('ca.client_abonnement_prix', '0.300')
                  ->orWhere('ca.client_abonnement_prix', '300'); // En millimes
            })
            ->whereBetween('ca.client_abonnement_creation', [$startDate, $endDate])
            ->count();

        $attempts = count($eklektikTransactions);
        $successes = 0;
        $dailySuccesses = [];

        foreach ($eklektikTransactions as $transaction) {
            $result = json_decode($transaction->result, true);
            if (is_array($result) && isset($result['success']) && $result['success']) {
                $successes++;
                $date = Carbon::parse($transaction->created_at)->toDateString();
                $dailySuccesses[$date] = ($dailySuccesses[$date] ?? 0) + 1;
            }
        }

        $successRate = $attempts > 0 ? $successes / $attempts : 0;
        $avgDailySuccesses = count($dailySuccesses) > 0 ? array_sum($dailySuccesses) / count($dailySuccesses) : 0;
        $consistencyScore = count($dailySuccesses) > 7 ? min(count($dailySuccesses) / 30, 1) : 0;

        return [
            'eklektik_success_rate' => round($successRate, 4),
            'eklektik_total_attempts' => $attempts,
            'eklektik_total_subscriptions' => $eklektikSubscriptions,
            'eklektik_avg_daily_successes' => round($avgDailySuccesses, 2),
            'eklektik_daily_consistency' => round($consistencyScore, 4),
            'eklektik_has_activity' => $attempts > 0 || $eklektikSubscriptions > 0 ? 1 : 0,
        ];
    }

    /**
     * Features spécifiques Ooredoo/DGV (mensuel 3.0 TND)
     */
    private function extractOoredooFeatures(int $clientId, Carbon $startDate, Carbon $endDate): array
    {
        // Transactions Ooredoo/DGV
        $ooredooTransactions = DB::table('transactions_history as th')
            ->where('th.client_id', $clientId)
            ->where(function($q) {
                $q->where('th.status', 'LIKE', '%OOREDOO%')
                  ->orWhere('th.status', 'LIKE', '%DGV%')
                  ->orWhere('th.status', 'LIKE', '%MONTHLY%');
            })
            ->whereBetween('th.created_at', [$startDate, $endDate])
            ->get();

        // Abonnements Ooredoo/DGV
        $ooredooSubscriptions = DB::table('client_abonnement as ca')
            ->join('country_payments_methods as cpm', 'ca.country_payments_methods_id', '=', 'cpm.country_payments_methods_id')
            ->where('ca.client_id', $clientId)
            ->where(function($q) {
                $q->whereRaw("LOWER(cpm.country_payments_methods_name) LIKE '%ooredoo%'")
                  ->orWhereRaw("LOWER(cpm.country_payments_methods_name) LIKE '%dgv%'")
                  ->orWhere('ca.client_abonnement_prix', '3.000')
                  ->orWhere('ca.client_abonnement_prix', '3000'); // En millimes
            })
            ->whereBetween('ca.client_abonnement_creation', [$startDate, $endDate])
            ->count();

        $attempts = count($ooredooTransactions);
        $successes = 0;
        $monthlyPattern = [];

        foreach ($ooredooTransactions as $transaction) {
            $result = json_decode($transaction->result, true);
            if (is_array($result) && isset($result['success']) && $result['success']) {
                $successes++;
                $month = Carbon::parse($transaction->created_at)->format('Y-m');
                $monthlyPattern[$month] = ($monthlyPattern[$month] ?? 0) + 1;
            }
        }

        $successRate = $attempts > 0 ? $successes / $attempts : 0;
        $monthlyConsistency = count($monthlyPattern) > 0 ? min(count($monthlyPattern) / 6, 1) : 0; // Sur 6 mois
        $avgMonthlySuccesses = count($monthlyPattern) > 0 ? array_sum($monthlyPattern) / count($monthlyPattern) : 0;

        return [
            'ooredoo_success_rate' => round($successRate, 4),
            'ooredoo_total_attempts' => $attempts,
            'ooredoo_total_subscriptions' => $ooredooSubscriptions,
            'ooredoo_avg_monthly_successes' => round($avgMonthlySuccesses, 2),
            'ooredoo_monthly_consistency' => round($monthlyConsistency, 4),
            'ooredoo_has_activity' => $attempts > 0 || $ooredooSubscriptions > 0 ? 1 : 0,
        ];
    }

    /**
     * Features cross-opérateur pour analyser les préférences
     */
    private function extractCrossOperatorFeatures(int $clientId, Carbon $startDate, Carbon $endDate): array
    {
        // Récupérer l'activité sur tous les opérateurs
        $allOperators = DB::table('client_abonnement as ca')
            ->join('country_payments_methods as cpm', 'ca.country_payments_methods_id', '=', 'cpm.country_payments_methods_id')
            ->where('ca.client_id', $clientId)
            ->whereBetween('ca.client_abonnement_creation', [$startDate, $endDate])
            ->select('cpm.country_payments_methods_name', 'ca.client_abonnement_prix')
            ->get();

        $operatorCount = $allOperators->pluck('country_payments_methods_name')->unique()->count();
        $pricePoints = $allOperators->pluck('client_abonnement_prix')->unique()->values()->toArray();
        
        // Préférence prix (bas vs élevé)
        $lowPriceCount = $allOperators->where('client_abonnement_prix', '<=', 1.0)->count();
        $highPriceCount = $allOperators->where('client_abonnement_prix', '>', 1.0)->count();
        $pricePreference = $lowPriceCount > $highPriceCount ? 'low' : ($highPriceCount > $lowPriceCount ? 'high' : 'mixed');

        // Diversité des opérateurs
        $operatorDiversity = min($operatorCount / 3, 1); // Normalisation sur 3 opérateurs max

        return [
            'total_operators_used' => $operatorCount,
            'operator_diversity_score' => round($operatorDiversity, 4),
            'price_preference' => $pricePreference,
            'unique_price_points' => count($pricePoints),
            'prefers_low_price' => $pricePreference === 'low' ? 1 : 0,
            'prefers_high_price' => $pricePreference === 'high' ? 1 : 0,
            'is_multi_operator_user' => $operatorCount > 1 ? 1 : 0,
        ];
    }

    /**
     * Features par type d'offre (quotidien vs mensuel)
     */
    private function extractOfferTypeFeatures(int $clientId, Carbon $startDate, Carbon $endDate): array
    {
        // Analyser le comportement quotidien (Eklektik 0.3 TND)
        $dailyOffers = DB::table('client_abonnement as ca')
            ->join('country_payments_methods as cpm', 'ca.country_payments_methods_id', '=', 'cpm.country_payments_methods_id')
            ->where('ca.client_id', $clientId)
            ->where(function($q) {
                $q->where('ca.client_abonnement_prix', '<=', 1.0) // Prix bas = offres quotidiennes
                  ->orWhereRaw("LOWER(cpm.country_payments_methods_name) LIKE '%eklektik%'");
            })
            ->whereBetween('ca.client_abonnement_creation', [$startDate, $endDate])
            ->count();

        // Analyser le comportement mensuel (Timwe, Ooredoo 3.0 TND)
        $monthlyOffers = DB::table('client_abonnement as ca')
            ->join('country_payments_methods as cpm', 'ca.country_payments_methods_id', '=', 'cpm.country_payments_methods_id')  
            ->where('ca.client_id', $clientId)
            ->where(function($q) {
                $q->where('ca.client_abonnement_prix', '>', 1.0) // Prix élevé = offres mensuelles
                  ->orWhereRaw("LOWER(cpm.country_payments_methods_name) LIKE '%timwe%'")
                  ->orWhereRaw("LOWER(cpm.country_payments_methods_name) LIKE '%ooredoo%'");
            })
            ->whereBetween('ca.client_abonnement_creation', [$startDate, $endDate])
            ->count();

        $totalOffers = $dailyOffers + $monthlyOffers;
        
        // Patterns de préférence d'engagement
        $dailyEngagementRate = $totalOffers > 0 ? $dailyOffers / $totalOffers : 0;
        $monthlyEngagementRate = $totalOffers > 0 ? $monthlyOffers / $totalOffers : 0;

        // Analyse de la fréquence optimale
        $preferredFrequency = 'unknown';
        if ($dailyOffers > $monthlyOffers * 2) {
            $preferredFrequency = 'daily';
        } elseif ($monthlyOffers > $dailyOffers * 2) {
            $preferredFrequency = 'monthly';
        } elseif ($totalOffers > 0) {
            $preferredFrequency = 'mixed';
        }

        return [
            'daily_offers_count' => $dailyOffers,
            'monthly_offers_count' => $monthlyOffers,
            'total_offers_count' => $totalOffers,
            'daily_engagement_rate' => round($dailyEngagementRate, 4),
            'monthly_engagement_rate' => round($monthlyEngagementRate, 4),
            'preferred_frequency' => $preferredFrequency,
            'prefers_daily_offers' => $preferredFrequency === 'daily' ? 1 : 0,
            'prefers_monthly_offers' => $preferredFrequency === 'monthly' ? 1 : 0,
            'is_frequency_flexible' => $preferredFrequency === 'mixed' ? 1 : 0,
        ];
    }

    /**
     * Features de préférences client basées sur l'historique multi-opérateur
     */
    private function extractClientPreferences(int $clientId, Carbon $startDate, Carbon $endDate): array
    {
        // Analyser les patterns de succès par opérateur pour détecter les préférences
        $operatorSuccessRates = [];
        
        $operators = [
            'timwe' => ['%TIMWE%'],
            'eklektik' => ['%EKLEKTIK%', '%CLUB_PRIVILEGE%'],
            'ooredoo' => ['%OOREDOO%', '%DGV%']
        ];

        foreach ($operators as $operatorName => $patterns) {
            $transactions = DB::table('transactions_history')
                ->where('client_id', $clientId)
                ->where(function($q) use ($patterns) {
                    foreach ($patterns as $pattern) {
                        $q->orWhere('status', 'LIKE', $pattern);
                    }
                })
                ->whereBetween('created_at', [$startDate, $endDate])
                ->get();

            $successes = 0;
            $attempts = count($transactions);
            
            foreach ($transactions as $transaction) {
                $result = json_decode($transaction->result, true);
                if (is_array($result)) {
                    // Détecter le succès selon le type d'opérateur
                    if (isset($result['success']) && $result['success']) {
                        $successes++;
                    } elseif (isset($result['mnoDeliveryCode']) && $result['mnoDeliveryCode'] === 'DELIVERED') {
                        $successes++;
                    }
                }
            }

            $successRate = $attempts > 0 ? $successes / $attempts : 0;
            $operatorSuccessRates[$operatorName] = $successRate;
        }

        // Déterminer l'opérateur préféré
        $bestOperator = 'none';
        $maxSuccessRate = 0;
        foreach ($operatorSuccessRates as $op => $rate) {
            if ($rate > $maxSuccessRate) {
                $maxSuccessRate = $rate;
                $bestOperator = $op;
            }
        }

        return [
            'best_performing_operator' => $bestOperator,
            // Note: best_operator_success_rate retiré car pas dans migration
            // 'timwe_preference_score' => round($operatorSuccessRates['timwe'] ?? 0, 4),
            // 'eklektik_preference_score' => round($operatorSuccessRates['eklektik'] ?? 0, 4), 
            // 'ooredoo_preference_score' => round($operatorSuccessRates['ooredoo'] ?? 0, 4),
            // 'is_operator_specialist' => $maxSuccessRate > 0.5 ? 1 : 0,
        ];
    }

    /**
     * Extrait toutes les features pour tous les clients actifs (multi-opérateur)
     */
    public function extractAndStoreFeaturesForDate(Carbon $calculationDate): int
    {
        Log::info("MLMultiOperatorFeatureService - Début extraction multi-opérateur pour {$calculationDate->toDateString()}");

        // Récupérer TOUS les clients actifs (tous opérateurs)
        $allOperatorIds = DB::table('country_payments_methods')
            ->whereRaw("TRIM(LOWER(country_payments_methods_name)) LIKE '%timwe%'")
            ->orWhereRaw("TRIM(LOWER(country_payments_methods_name)) LIKE '%eklektik%'")
            ->orWhereRaw("TRIM(LOWER(country_payments_methods_name)) LIKE '%ooredoo%'")
            ->orWhereRaw("TRIM(LOWER(country_payments_methods_name)) LIKE '%dgv%'")
            ->pluck('country_payments_methods_id')
            ->toArray();

        if (empty($allOperatorIds)) {
            Log::warning("MLMultiOperatorFeatureService - Aucun opérateur trouvé");
            return 0;
        }

        Log::info("MLMultiOperatorFeatureService - Opérateurs trouvés: " . count($allOperatorIds));

        // Clients actifs (avec abonnement récent ou transactions)
        $activeClients = DB::table('client_abonnement as ca')
            ->whereIn('ca.country_payments_methods_id', $allOperatorIds)
            ->where('ca.client_abonnement_creation', '<=', $calculationDate)
            ->where(function($q) use ($calculationDate) {
                $q->whereNull('ca.client_abonnement_expiration')
                  ->orWhere('ca.client_abonnement_expiration', '>=', $calculationDate);
            })
            ->distinct()
            ->pluck('ca.client_id')
            ->toArray();

        Log::info("MLMultiOperatorFeatureService - Clients actifs multi-opérateur: " . count($activeClients));

        $processedCount = 0;
        $batchSize = 50; // Réduit pour traiter multi-opérateur

        foreach (array_chunk($activeClients, $batchSize) as $batch) {
            $featuresData = [];
            
            foreach ($batch as $clientId) {
                try {
                    $features = $this->extractClientFeatures($clientId, $calculationDate);
                    $featuresData[] = $features;
                    $processedCount++;
                } catch (\Exception $e) {
                    Log::error("MLMultiOperatorFeatureService - Erreur client $clientId: " . $e->getMessage());
                }
            }
            
            // Insérer en base avec upsert sur nouvelles colonnes
            if (!empty($featuresData)) {
                DB::table('ml_client_features')->upsert(
                    $featuresData,
                    ['client_id', 'calculation_date'],
                    array_keys($featuresData[0])
                );
            }
            
            Log::info("MLMultiOperatorFeatureService - Batch traité, total: $processedCount");
        }

        Log::info("MLMultiOperatorFeatureService - Extraction terminée", [
            'clients_processed' => $processedCount,
            'date' => $calculationDate->toDateString()
        ]);

        return $processedCount;
    }

    /**
     * Features par défaut multi-opérateur
     */
    private function getDefaultMultiOperatorFeatures(int $clientId, Carbon $calculationDate): array
    {
        return [
            'client_id' => $clientId,
            'calculation_date' => $calculationDate->toDateString(),
            
            // Timwe features
            'timwe_success_rate' => 0,
            'timwe_total_attempts' => 0,
            'timwe_total_successes' => 0,
            'timwe_avg_revenue_per_success' => 0,
            'timwe_no_balance_rate' => 0,
            'timwe_not_delivered_rate' => 0,
            'timwe_has_activity' => 0,
            
            // Eklektik features  
            'eklektik_success_rate' => 0,
            'eklektik_total_attempts' => 0,
            'eklektik_total_subscriptions' => 0,
            'eklektik_avg_daily_successes' => 0,
            'eklektik_daily_consistency' => 0,
            'eklektik_has_activity' => 0,
            
            // Ooredoo features
            'ooredoo_success_rate' => 0,
            'ooredoo_total_attempts' => 0,
            'ooredoo_total_subscriptions' => 0,
            'ooredoo_avg_monthly_successes' => 0,
            'ooredoo_monthly_consistency' => 0,
            'ooredoo_has_activity' => 0,
            
            // Cross-operator features
            'total_operators_used' => 0,
            'operator_diversity_score' => 0,
            'price_preference' => 'unknown',
            'unique_price_points' => 0,
            'prefers_low_price' => 0,
            'prefers_high_price' => 0,
            'is_multi_operator_user' => 0,
            
            // Offer type features
            'daily_offers_count' => 0,
            'monthly_offers_count' => 0,
            'total_offers_count' => 0,
            'daily_engagement_rate' => 0,
            'monthly_engagement_rate' => 0,
            'preferred_frequency' => 'unknown',
            'prefers_daily_offers' => 0,
            'prefers_monthly_offers' => 0,
            'is_frequency_flexible' => 0,
            
            // Preference features
            'best_performing_operator' => 'none',
        ];
    }
}