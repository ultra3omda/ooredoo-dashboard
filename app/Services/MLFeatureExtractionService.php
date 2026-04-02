<?php

namespace App\Services;

use App\Jobs\ExtractClientFeaturesJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class MLFeatureExtractionService
{
    /**
     * Extrait et calcule toutes les features pour un client donné
     */
    public function extractClientFeatures(int $clientId, Carbon $calculationDate): array
    {
        Log::info("MLFeatureExtractionService - Extraction features pour client $clientId à la date {$calculationDate->toDateString()}");

        $features = [
            'client_id' => $clientId,
            'calculation_date' => $calculationDate->toDateString(),
        ];

        // Période d'analyse : 6 mois avant la date de calcul
        $startDate = $calculationDate->copy()->subMonths(6);
        
        try {
            // === 1. Historique de Paiement ===
            $paymentFeatures = $this->calculatePaymentFeatures($clientId, $startDate, $calculationDate);
            $features = array_merge($features, $paymentFeatures);
            
            // === 2. Patterns de Solde ===
            $balanceFeatures = $this->calculateBalanceFeatures($clientId, $startDate, $calculationDate);
            $features = array_merge($features, $balanceFeatures);
            
            // === 3. Patterns Temporels ===
            $temporalFeatures = $this->calculateTemporalFeatures($clientId, $startDate, $calculationDate);
            $features = array_merge($features, $temporalFeatures);
            
            // === 4. Comportement Usage ===
            $usageFeatures = $this->calculateUsageFeatures($clientId, $startDate, $calculationDate);
            $features = array_merge($features, $usageFeatures);
            
            // === 5. Démographiques ===
            $demographicFeatures = $this->calculateDemographicFeatures($clientId, $calculationDate);
            $features = array_merge($features, $demographicFeatures);
            
            // === 6. Risk Indicators ===
            $riskFeatures = $this->calculateRiskIndicators($clientId, $startDate, $calculationDate);
            $features = array_merge($features, $riskFeatures);
            
            // === 7. Computed Scores ===
            $computedScores = $this->calculateComputedScores($features);
            $features = array_merge($features, $computedScores);

            // === 8. Advanced Features (NOUVEAU v2.0) ===
            $advancedFeatures = $this->calculateAdvancedFeatures($clientId, $startDate, $calculationDate);
            $features = array_merge($features, $advancedFeatures);

        } catch (\Exception $e) {
            Log::error("MLFeatureExtractionService - Erreur extraction features client $clientId", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Retourner des valeurs par défaut en cas d'erreur
            $features = $this->getDefaultFeatures($clientId, $calculationDate);
        }

        return $features;
    }

    /**
     * Cache Redis/Laravel pour les stats de transactions (évite requêtes répétées)
     */
    private function getCachedTransactionStats(int $clientId, Carbon $startDate, Carbon $endDate): array
    {
        $cacheKey = 'ml_trans_stats_' . $clientId . '_' . $startDate->format('Ymd') . '_' . $endDate->format('Ymd');
        return Cache::remember($cacheKey, 3600, function () use ($clientId, $startDate, $endDate) {
            return $this->fetchRawTransactionsForPeriod($clientId, $startDate, $endDate);
        });
    }

    /**
     * Requête brute des transactions pour une période (utilisée par le cache)
     */
    private function fetchRawTransactionsForPeriod(int $clientId, Carbon $startDate, Carbon $endDate): array
    {
        $rows = DB::table('transactions_history as th')
            ->where('th.client_id', $clientId)
            ->where(function ($q) {
                $q->where('th.status', 'LIKE', '%TIMWE_RENEWED_NOTIF%')
                    ->orWhere('th.status', 'LIKE', '%TIMWE_CHARGE_DELIVERED%');
            })
            ->whereBetween('th.created_at', [$startDate, $endDate])
            ->whereNotNull('th.result')
            ->orderBy('th.created_at')
            ->get(['th.transaction_history_id', 'th.created_at', 'th.status', 'th.result']);
        return $rows->toArray();
    }

    /**
     * Calcule les features liées aux paiements
     */
    private function calculatePaymentFeatures(int $clientId, Carbon $startDate, Carbon $endDate): array
    {
        $billingPpid = env('TIMWE_BILLING_PPID', '63980');
        $transactions = $this->getCachedTransactionStats($clientId, $startDate, $endDate);
        $transactions = collect($transactions);

        $totalAttempts = 0;
        $totalPayments = 0;
        $totalAmount = 0;
        $consecutiveFailures = 0;
        $lastPaymentDate = null;
        $failures = [];

        foreach ($transactions as $transaction) {
            $resultRaw = is_array($transaction) ? ($transaction['result'] ?? null) : $transaction->result;
            $result = is_string($resultRaw) ? json_decode($resultRaw, true) : $resultRaw;
            if (!is_array($result)) continue;

            $ppid = $result['pricepointId'] ?? null;
            $delivery = $result['mnoDeliveryCode'] ?? null;
            $totalCharged = isset($result['totalCharged']) ? (int) $result['totalCharged'] : 0;

            $totalAttempts++;

            $createdAt = is_array($transaction) ? ($transaction['created_at'] ?? null) : $transaction->created_at;
            if ((string) $ppid === (string) $billingPpid && $delivery === 'DELIVERED' && $totalCharged > 0) {
                $totalPayments++;
                $totalAmount += $totalCharged / 1000;
                $consecutiveFailures = 0;
                $lastPaymentDate = $createdAt;
            } else {
                $consecutiveFailures++;
                $failures[] = $createdAt;
            }
        }

        $paymentSuccessRate = $totalAttempts > 0 ? $totalPayments / $totalAttempts : 0;
        $avgPaymentAmount = $totalPayments > 0 ? $totalAmount / $totalPayments : 0;
        $daysSinceLastPayment = $lastPaymentDate ? Carbon::parse($lastPaymentDate)->diffInDays($endDate) : null;
        
        // Fréquence de paiement (paiements par jour)
        $periodDays = $startDate->diffInDays($endDate);
        $paymentFrequency = $periodDays > 0 ? $totalPayments / $periodDays : 0;

        return [
            'payment_success_rate' => round($paymentSuccessRate, 4),
            'consecutive_failures' => $consecutiveFailures,
            'days_since_last_payment' => $daysSinceLastPayment,
            'avg_payment_amount' => round($avgPaymentAmount, 3),
            'payment_frequency' => round($paymentFrequency, 4),
            'total_payments' => $totalPayments,
            'total_attempts' => $totalAttempts,
        ];
    }

    /**
     * Calcule les features liées aux patterns de solde
     */
    private function calculateBalanceFeatures(int $clientId, Carbon $startDate, Carbon $endDate): array
    {
        // Pour l'instant, on ne peut pas calculer les patterns de solde exact sans API d'historique de solde
        // Mais on peut approximer en utilisant les patterns de recharge et les résultats NO_BALANCE
        
        $noBalanceCount = DB::table('transactions_history as th')
            ->where('th.client_id', $clientId)
            ->where('th.status', 'LIKE', '%TIMWE_%')
            ->whereBetween('th.created_at', [$startDate, $endDate])
            ->where(function($q) {
                $q->where('th.result', 'LIKE', '%NO_BALANCE%')
                  ->orWhereRaw("JSON_EXTRACT(th.result, '$.mnoDeliveryCode') = 'NO_BALANCE'");
            })
            ->count();

        $totalTransactions = DB::table('transactions_history')
            ->where('client_id', $clientId)
            ->where('status', 'LIKE', '%TIMWE_%')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        // Approximation du pattern de solde basé sur la fréquence de NO_BALANCE
        $balanceTrend = 'unknown';
        if ($totalTransactions > 0) {
            $noBalanceRate = $noBalanceCount / $totalTransactions;
            if ($noBalanceRate > 0.8) {
                $balanceTrend = 'decreasing';
            } elseif ($noBalanceRate < 0.3) {
                $balanceTrend = 'stable';
            } else {
                $balanceTrend = 'increasing';
            }
        }

        return [
            'avg_balance' => null, // Non disponible
            'balance_volatility' => 0,
            'recharge_frequency' => 0,
            'recharge_amount_avg' => 0,
            'days_since_recharge' => null,
            'balance_trend' => $balanceTrend,
        ];
    }

    /**
     * Calcule les features temporelles
     */
    private function calculateTemporalFeatures(int $clientId, Carbon $startDate, Carbon $endDate): array
    {
        $billingPpid = env('TIMWE_BILLING_PPID', '63980');
        
        // Analyser les patterns de succès par jour de semaine et heure
        $successfulTransactions = DB::table('transactions_history as th')
            ->where('th.client_id', $clientId)
            ->where(function($q) {
                $q->where('th.status', 'LIKE', '%TIMWE_RENEWED_NOTIF%')
                  ->orWhere('th.status', 'LIKE', '%TIMWE_CHARGE_DELIVERED%');
            })
            ->whereBetween('th.created_at', [$startDate, $endDate])
            ->whereNotNull('th.result')
            ->select('th.created_at', 'th.result')
            ->get()
            ->filter(function($transaction) use ($billingPpid) {
                $result = json_decode($transaction->result, true);
                if (!is_array($result)) return false;
                
                $ppid = $result['pricepointId'] ?? null;
                $delivery = $result['mnoDeliveryCode'] ?? null;
                $totalCharged = isset($result['totalCharged']) ? (int)$result['totalCharged'] : 0;
                
                return (string)$ppid === (string)$billingPpid && $delivery === 'DELIVERED' && $totalCharged > 0;
            });

        $successByDayOfWeek = [];
        $successByHour = [];
        $endMonthSuccesses = 0;
        $beginningMonthSuccesses = 0;
        $totalSuccesses = count($successfulTransactions);

        foreach ($successfulTransactions as $transaction) {
            $date = Carbon::parse($transaction->created_at);
            $dayOfWeek = $date->dayOfWeek; // 0 = dimanche
            $hour = $date->hour;
            $dayOfMonth = $date->day;
            
            // Compter par jour de semaine (1-7, lundi = 1)
            $dayOfWeek = $dayOfWeek == 0 ? 7 : $dayOfWeek;
            $successByDayOfWeek[$dayOfWeek] = ($successByDayOfWeek[$dayOfWeek] ?? 0) + 1;
            
            // Compter par heure
            $successByHour[$hour] = ($successByHour[$hour] ?? 0) + 1;
            
            // Fin de mois vs début de mois
            if ($dayOfMonth > 25) {
                $endMonthSuccesses++;
            } elseif ($dayOfMonth <= 5) {
                $beginningMonthSuccesses++;
            }
        }

        $bestBillingDayWeek = null;
        $bestBillingHour = null;

        if (!empty($successByDayOfWeek)) {
            $bestBillingDayWeek = array_keys($successByDayOfWeek, max($successByDayOfWeek))[0];
        }
        
        if (!empty($successByHour)) {
            $bestBillingHour = array_keys($successByHour, max($successByHour))[0];
        }

        $endMonthSuccessRate = $totalSuccesses > 0 && $endMonthSuccesses > 0 ? $endMonthSuccesses / $totalSuccesses : 0;
        $beginningMonthSuccessRate = $totalSuccesses > 0 && $beginningMonthSuccesses > 0 ? $beginningMonthSuccesses / $totalSuccesses : 0;

        // Pattern saisonnier (simplifié par trimestre)
        $seasonalPattern = [];
        $quarters = [
            'Q1' => [1, 2, 3],
            'Q2' => [4, 5, 6],
            'Q3' => [7, 8, 9],
            'Q4' => [10, 11, 12]
        ];
        
        foreach ($quarters as $quarter => $months) {
            $quarterSuccesses = $successfulTransactions->filter(function($transaction) use ($months) {
                return in_array(Carbon::parse($transaction->created_at)->month, $months);
            })->count();
            
            $seasonalPattern[$quarter] = $totalSuccesses > 0 ? round($quarterSuccesses / $totalSuccesses, 4) : 0;
        }

        return [
            'best_billing_day_week' => $bestBillingDayWeek,
            'best_billing_hour' => $bestBillingHour,
            'seasonal_pattern' => json_encode($seasonalPattern),
            'end_month_success_rate' => round($endMonthSuccessRate, 4),
            'beginning_month_success_rate' => round($beginningMonthSuccessRate, 4),
        ];
    }

    /**
     * Calcule les features d'usage
     */
    private function calculateUsageFeatures(int $clientId, Carbon $startDate, Carbon $endDate): array
    {
        $totalTransactions = DB::table('transactions_history')
            ->where('client_id', $clientId)
            ->where('status', 'LIKE', '%TIMWE_%')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        $periodDays = $startDate->diffInDays($endDate) ?: 1;
        $avgTransactionsPerDay = $totalTransactions / $periodDays;

        // Analyser la distribution des statuts
        $statusDistribution = DB::table('transactions_history')
            ->where('client_id', $clientId)
            ->where('status', 'LIKE', '%TIMWE_%')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status')
            ->toArray();

        $uniqueStatusesCount = count($statusDistribution);

        return [
            'total_transactions' => $totalTransactions,
            'avg_transactions_per_day' => round($avgTransactionsPerDay, 4),
            'unique_statuses_count' => $uniqueStatusesCount,
            'status_distribution' => json_encode($statusDistribution),
        ];
    }

    /**
     * Calcule les features démographiques
     */
    private function calculateDemographicFeatures(int $clientId, Carbon $calculationDate): array
    {
        // Récupérer les informations client depuis les abonnements
        $subscription = DB::table('client_abonnement as ca')
            ->join('country_payments_methods as cpm', 'ca.country_payments_methods_id', '=', 'cpm.country_payments_methods_id')
            ->where('ca.client_id', $clientId)
            ->whereRaw("TRIM(cpm.country_payments_methods_name) LIKE '%timwe%'")
            ->orderBy('ca.client_abonnement_creation')
            ->select(
                'ca.client_abonnement_creation',
                'cpm.country_payments_methods_name'
            )
            ->first();

        $subscriptionAgeDays = 0;
        $operatorType = 'unknown';
        $firstTransaction = null;
        
        if ($subscription) {
            $subscriptionAgeDays = Carbon::parse($subscription->client_abonnement_creation)->diffInDays($calculationDate);
            $operatorType = 'timwe';
        }

        // Première et dernière transaction
        $firstTransaction = DB::table('transactions_history')
            ->where('client_id', $clientId)
            ->where('status', 'LIKE', '%TIMWE_%')
            ->orderBy('created_at')
            ->value('created_at');

        $lastTransaction = DB::table('transactions_history')
            ->where('client_id', $clientId)
            ->where('status', 'LIKE', '%TIMWE_%')
            ->orderBy('created_at', 'desc')
            ->value('created_at');

        return [
            'subscription_age_days' => $subscriptionAgeDays,
            'region' => null, // Non disponible dans les données actuelles
            'operator_type' => $operatorType,
            'first_transaction' => $firstTransaction,
            'last_transaction' => $lastTransaction,
        ];
    }

    /**
     * Calcule les indicateurs de risque
     */
    private function calculateRiskIndicators(int $clientId, Carbon $startDate, Carbon $endDate): array
    {
        // Analyser les échecs récents (30 derniers jours)
        $recentStartDate = $endDate->copy()->subDays(30);
        
        $recentFailures = DB::table('transactions_history as th')
            ->where('th.client_id', $clientId)
            ->where(function($q) {
                $q->where('th.status', 'LIKE', '%TIMWE_RENEWED_NOTIF%')
                  ->orWhere('th.status', 'LIKE', '%TIMWE_CHARGE_DELIVERED%');
            })
            ->whereBetween('th.created_at', [$recentStartDate, $endDate])
            ->where(function($q) {
                $q->where('th.result', 'LIKE', '%NO_BALANCE%')
                  ->orWhereRaw("JSON_EXTRACT(th.result, '$.mnoDeliveryCode') = 'NO_BALANCE'")
                  ->orWhereRaw("JSON_EXTRACT(th.result, '$.mnoDeliveryCode') = 'NOT_DELIVERED'");
            })
            ->count();

        $recentTotal = DB::table('transactions_history')
            ->where('client_id', $clientId)
            ->where(function($q) {
                $q->where('status', 'LIKE', '%TIMWE_RENEWED_NOTIF%')
                  ->orWhere('status', 'LIKE', '%TIMWE_CHARGE_DELIVERED%');
            })
            ->whereBetween('created_at', [$recentStartDate, $endDate])
            ->count();

        $hasRecentFailures = $recentFailures > 0;
        
        // Calculer la série d'échecs consécutifs actuels
        $failureStreak = $this->calculateCurrentFailureStreak($clientId, $endDate);
        
        // Estimation de la probabilité de churn (basique)
        $churnProbability = 0;
        if ($recentTotal > 0) {
            $recentFailureRate = $recentFailures / $recentTotal;
            if ($recentFailureRate > 0.9) {
                $churnProbability = 0.8;
            } elseif ($recentFailureRate > 0.7) {
                $churnProbability = 0.5;
            } elseif ($recentFailureRate > 0.5) {
                $churnProbability = 0.3;
            }
        }

        // Client de grande valeur (basé sur le nombre de paiements réussis)
        $totalSuccessfulPayments = DB::table('transactions_history as th')
            ->where('th.client_id', $clientId)
            ->where(function($q) {
                $q->where('th.status', 'LIKE', '%TIMWE_RENEWED_NOTIF%')
                  ->orWhere('th.status', 'LIKE', '%TIMWE_CHARGE_DELIVERED%');
            })
            ->whereBetween('th.created_at', [$startDate, $endDate])
            ->whereRaw("JSON_EXTRACT(th.result, '$.mnoDeliveryCode') = 'DELIVERED'")
            ->whereRaw("JSON_EXTRACT(th.result, '$.totalCharged') > 0")
            ->count();

        $isHighValueClient = $totalSuccessfulPayments >= 10; // 10+ paiements = client important

        return [
            'churn_probability' => round($churnProbability, 4),
            'has_recent_failures' => $hasRecentFailures,
            'failure_streak' => $failureStreak,
            'is_high_value_client' => $isHighValueClient,
        ];
    }

    /**
     * Calcule la série d'échecs consécutifs actuelle
     */
    private function calculateCurrentFailureStreak(int $clientId, Carbon $endDate): int
    {
        $billingPpid = env('TIMWE_BILLING_PPID', '63980');
        
        // Récupérer les dernières tentatives de facturation (30 dernières)
        $recentTransactions = DB::table('transactions_history as th')
            ->where('th.client_id', $clientId)
            ->where(function($q) {
                $q->where('th.status', 'LIKE', '%TIMWE_RENEWED_NOTIF%')
                  ->orWhere('th.status', 'LIKE', '%TIMWE_CHARGE_DELIVERED%');
            })
            ->where('th.created_at', '<=', $endDate)
            ->orderBy('th.created_at', 'desc')
            ->limit(30)
            ->get();

        $streak = 0;
        foreach ($recentTransactions as $transaction) {
            $result = json_decode($transaction->result, true);
            if (!is_array($result)) continue;
            
            $ppid = $result['pricepointId'] ?? null;
            $delivery = $result['mnoDeliveryCode'] ?? null;
            $totalCharged = isset($result['totalCharged']) ? (int)$result['totalCharged'] : 0;
            
            // Si c'est un succès, arrêter le comptage
            if ((string)$ppid === (string)$billingPpid && $delivery === 'DELIVERED' && $totalCharged > 0) {
                break;
            }
            
            // Sinon, c'est un échec
            $streak++;
        }

        return $streak;
    }

    /**
     * Calcule les scores composés (VERSION CORRIGÉE v2.0)
     */
    private function calculateComputedScores(array $features): array
    {
        $paymentReliabilityScore = $features['payment_success_rate'] ?? 0;
        
        // FIX: Score d'engagement amélioré (multi-factoriel)
        $engagementScore = $this->calculateEngagementScore($features);
        
        // FIX: Score de valeur client amélioré
        $lifetimeValueScore = $this->calculateLifetimeValueScore($features);

        // Segmentation améliorée avec seuils optimisés
        $segment = $this->determineClientSegment($paymentReliabilityScore, $engagementScore, $lifetimeValueScore, $features);

        return [
            'payment_reliability_score' => round($paymentReliabilityScore, 4),
            'engagement_score' => round($engagementScore, 4),
            'lifetime_value_score' => round($lifetimeValueScore, 4),
            'client_segment' => $segment,
        ];
    }

    /**
     * Calcule un score d'engagement multi-factoriel (FIX variance = 0)
     */
    private function calculateEngagementScore(array $features): float
    {
        $score = 0;
        
        // Facteur 1: Fréquence d'usage (40%)
        $transactionFrequency = ($features['avg_transactions_per_day'] ?? 0);
        $freqScore = min($transactionFrequency / 2, 1.0); // Normalisation sur 2 trans/jour
        
        // Facteur 2: Consistance des paiements (30%)
        $consecutiveFailures = ($features['consecutive_failures'] ?? 0);
        $consistencyScore = max(0, 1 - $consecutiveFailures / 10); // Max 10 échecs
        
        // Facteur 3: Activité récente (30%)
        $daysSinceLastPayment = ($features['days_since_last_payment'] ?? 999);
        $recencyScore = 0;
        if ($daysSinceLastPayment <= 7) {
            $recencyScore = 1.0;
        } elseif ($daysSinceLastPayment <= 30) {
            $recencyScore = 0.7;
        } elseif ($daysSinceLastPayment <= 90) {
            $recencyScore = 0.3;
        }
        
        $score = $freqScore * 0.4 + $consistencyScore * 0.3 + $recencyScore * 0.3;
        
        return max(0, min(1, $score));
    }

    /**
     * Calcule un score de valeur client amélioré (FIX variance faible)
     */
    private function calculateLifetimeValueScore(array $features): float
    {
        $score = 0;
        
        // Facteur 1: Revenus totaux (50%)
        $totalPayments = ($features['total_payments'] ?? 0);
        $avgAmount = ($features['avg_payment_amount'] ?? 0);
        $totalRevenue = $totalPayments * $avgAmount;
        $revenueScore = min($totalRevenue / 500, 1.0); // Normalisation sur 500 TND
        
        // Facteur 2: Fréquence de paiement (30%)
        $frequency = ($features['payment_frequency'] ?? 0);
        $frequencyScore = min($frequency * 30, 1.0); // Normalisation sur 1 paiement/mois
        
        // Facteur 3: Stabilité (20%)
        $subscriptionAge = ($features['subscription_age_days'] ?? 0);
        $stabilityScore = min($subscriptionAge / 365, 1.0); // Normalisation sur 1 an
        
        $score = $revenueScore * 0.5 + $frequencyScore * 0.3 + $stabilityScore * 0.2;
        
        return max(0, min(1, $score));
    }

    /**
     * Détermine le segment client avec logique améliorée
     */
    private function determineClientSegment(float $reliability, float $engagement, float $lifetime, array $features): string
    {
        $totalPayments = ($features['total_payments'] ?? 0);
        $avgAmount = ($features['avg_payment_amount'] ?? 0);
        $churnProba = ($features['churn_probability'] ?? 0);
        
        // Premium: haute fiabilité + haute valeur
        if ($reliability >= 0.7 && $lifetime >= 0.6 && $totalPayments >= 5) {
            return 'premium_payers';
        }
        
        // Regular: fiabilité correcte + engagement ok
        if ($reliability >= 0.3 && $engagement >= 0.4 && $totalPayments >= 2) {
            return 'regular_payers';
        }
        
        // Struggling: quelques paiements mais difficultés
        if ($reliability >= 0.05 && $totalPayments >= 1) {
            return 'struggling_payers';
        }
        
        // Churn risk: engagé mais performance dégradée
        if ($churnProba > 0.6 || ($engagement >= 0.3 && $reliability < 0.1)) {
            return 'churn_risk';
        }
        
        // High risk: très peu ou pas de succès
        return 'high_risk';
    }

    /**
     * Retourne des features par défaut en cas d'erreur
     */
    private function getDefaultFeatures(int $clientId, Carbon $calculationDate): array
    {
        return [
            'client_id' => $clientId,
            'calculation_date' => $calculationDate->toDateString(),
            'payment_success_rate' => 0,
            'consecutive_failures' => 0,
            'days_since_last_payment' => null,
            'avg_payment_amount' => 0,
            'payment_frequency' => 0,
            'total_payments' => 0,
            'total_attempts' => 0,
            'avg_balance' => null,
            'balance_volatility' => 0,
            'recharge_frequency' => 0,
            'recharge_amount_avg' => 0,
            'days_since_recharge' => null,
            'balance_trend' => 'unknown',
            'best_billing_day_week' => null,
            'best_billing_hour' => null,
            'seasonal_pattern' => null,
            'end_month_success_rate' => 0,
            'beginning_month_success_rate' => 0,
            'total_transactions' => 0,
            'avg_transactions_per_day' => 0,
            'unique_statuses_count' => 0,
            'status_distribution' => null,
            'subscription_age_days' => 0,
            'region' => null,
            'operator_type' => 'unknown',
            'first_transaction' => null,
            'last_transaction' => null,
            'churn_probability' => 0,
            'has_recent_failures' => false,
            'failure_streak' => 0,
            'is_high_value_client' => false,
            'payment_reliability_score' => 0,
            'engagement_score' => 0,
            'lifetime_value_score' => 0,
            'client_segment' => 'unknown',
            // Advanced features v2.0
            'morning_success_rate' => 0,
            'afternoon_success_rate' => 0,
            'evening_success_rate' => 0,
            'recovery_after_failure_rate' => 0,
            'max_consecutive_successes' => 0,
            'payment_amount_std' => 0,
            'amount_flexibility' => 0,
            'no_balance_failure_rate' => 0,
            'not_delivered_failure_rate' => 0,
        ];
    }

    /**
     * Retourne les IDs des clients actifs pour une date de calcul (pour queues ou batch)
     */
    public function getActiveClientIds(Carbon $calculationDate): array
    {
        $timweOperatorIds = DB::table('country_payments_methods')
            ->whereRaw("TRIM(country_payments_methods_name) LIKE '%timwe%'")
            ->pluck('country_payments_methods_id')
            ->toArray();

        if (empty($timweOperatorIds)) {
            return [];
        }

        return DB::table('client_abonnement as ca')
            ->whereIn('ca.country_payments_methods_id', $timweOperatorIds)
            ->where('ca.client_abonnement_creation', '<=', $calculationDate)
            ->where(function ($q) use ($calculationDate) {
                $q->whereNull('ca.client_abonnement_expiration')
                    ->orWhere('ca.client_abonnement_expiration', '>=', $calculationDate);
            })
            ->distinct()
            ->pluck('ca.client_id')
            ->toArray();
    }

    /**
     * Extrait les features pour tous les clients actifs et les sauvegarde.
     * Si $useQueue = true, dispatch des jobs en chunks de 500 sur la queue ml-extraction.
     */
    public function extractAndStoreFeaturesForDate(Carbon $calculationDate, bool $useQueue = false): int
    {
        Log::info("MLFeatureExtractionService - Début extraction pour la date {$calculationDate->toDateString()}", [
            'use_queue' => $useQueue,
        ]);

        $activeClients = $this->getActiveClientIds($calculationDate);
        $total = count($activeClients);

        if ($total === 0) {
            Log::warning("MLFeatureExtractionService - Aucun client actif trouvé");
            return 0;
        }

        if ($useQueue) {
            $chunkSize = (int) env('ML_EXTRACTION_CHUNK_SIZE', 500);
            $chunks = array_chunk($activeClients, $chunkSize);
            foreach ($chunks as $chunk) {
                ExtractClientFeaturesJob::dispatch($chunk, $calculationDate)->onQueue('ml-extraction');
            }
            Log::info("MLFeatureExtractionService - {$total} clients dispatchés en " . count($chunks) . " jobs (queue ml-extraction)");
            return $total;
        }

        $processedCount = 0;
        $batchSize = 100;

        foreach (array_chunk($activeClients, $batchSize) as $batch) {
            $featuresData = [];
            foreach ($batch as $clientId) {
                try {
                    $features = $this->extractClientFeatures((int) $clientId, $calculationDate);
                    $featuresData[] = $features;
                    $processedCount++;
                } catch (\Exception $e) {
                    Log::error("MLFeatureExtractionService - Erreur pour client $clientId", [
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            if (!empty($featuresData)) {
                DB::table('ml_client_features')->upsert(
                    $featuresData,
                    ['client_id', 'calculation_date'],
                    array_keys($featuresData[0])
                );
            }
            Log::info("MLFeatureExtractionService - Batch traité, total: $processedCount");
        }

        Log::info("MLFeatureExtractionService - Extraction terminée pour {$calculationDate->toDateString()}", [
            'clients_processed' => $processedCount,
        ]);

        return $processedCount;
    }

    /**
     * ========================================================
     * BATCH EXTRACTION v2.0 - Optimisation majeure
     * Réduit de ~13 requêtes/client à ~3 requêtes/500 clients
     * ========================================================
     */
    public function extractAndStoreFeaturesForDateBatch(Carbon $calculationDate, ?callable $progressCallback = null): int
    {
        $activeClients = $this->getActiveClientIds($calculationDate);
        $total = count($activeClients);

        if ($total === 0) {
            Log::warning("MLFeatureExtractionService Batch - Aucun client actif");
            return 0;
        }

        Log::info("MLFeatureExtractionService Batch - Début pour {$calculationDate->toDateString()}, {$total} clients");

        $startDate = $calculationDate->copy()->subMonths(6);
        $recentStart = $calculationDate->copy()->subDays(30);
        $billingPpid = env('TIMWE_BILLING_PPID', '63980');
        $periodDays = max(1, $startDate->diffInDays($calculationDate));
        $processedCount = 0;
        $chunkSize = 500;
        $chunks = array_chunk($activeClients, $chunkSize);
        $totalChunks = count($chunks);
        $chunkIndex = 0;

        foreach ($chunks as $clientChunk) {
            $chunkIndex++;
            try {
                // BATCH QUERY 1: Billing transactions (RENEWED_NOTIF + CHARGE_DELIVERED) for 6 months
                $billingTransactions = DB::table('transactions_history as th')
                    ->whereIn('th.client_id', $clientChunk)
                    ->where(function ($q) {
                        $q->where('th.status', 'LIKE', '%TIMWE_RENEWED_NOTIF%')
                          ->orWhere('th.status', 'LIKE', '%TIMWE_CHARGE_DELIVERED%');
                    })
                    ->whereBetween('th.created_at', [$startDate, $calculationDate])
                    ->whereNotNull('th.result')
                    ->orderBy('th.created_at')
                    ->get(['th.client_id', 'th.created_at', 'th.status', 'th.result'])
                    ->groupBy('client_id');

                // BATCH QUERY 2: All TIMWE transactions (for usage counts, status distribution)
                $allTimweTrans = DB::table('transactions_history')
                    ->whereIn('client_id', $clientChunk)
                    ->where('status', 'LIKE', '%TIMWE_%')
                    ->whereBetween('created_at', [$startDate, $calculationDate])
                    ->get(['client_id', 'status', 'result', 'created_at'])
                    ->groupBy('client_id');

                // BATCH QUERY 3: Subscription info
                $subscriptions = DB::table('client_abonnement as ca')
                    ->join('country_payments_methods as cpm', 'ca.country_payments_methods_id', '=', 'cpm.country_payments_methods_id')
                    ->whereIn('ca.client_id', $clientChunk)
                    ->whereRaw("TRIM(cpm.country_payments_methods_name) LIKE '%timwe%'")
                    ->select('ca.client_id', 'ca.client_abonnement_creation', 'cpm.country_payments_methods_name')
                    ->orderBy('ca.client_abonnement_creation')
                    ->get()
                    ->unique('client_id')
                    ->keyBy('client_id');

                // Process each client from pre-loaded data
                $featuresData = [];
                foreach ($clientChunk as $clientId) {
                    try {
                        $clientBilling = $billingTransactions->get($clientId, collect());
                        $clientTimwe = $allTimweTrans->get($clientId, collect());
                        $subscription = $subscriptions->get($clientId);

                        $features = $this->computeFeaturesFromBatchData(
                            (int)$clientId, $calculationDate, $startDate, $recentStart,
                            $clientBilling, $clientTimwe, $subscription,
                            $billingPpid, $periodDays
                        );
                        $featuresData[] = $features;
                    } catch (\Exception $e) {
                        $featuresData[] = $this->getDefaultFeatures((int)$clientId, $calculationDate);
                    }
                    $processedCount++;
                }

                if (!empty($featuresData)) {
                    DB::table('ml_client_features')->upsert(
                        $featuresData,
                        ['client_id', 'calculation_date'],
                        array_keys($featuresData[0])
                    );
                }

                if ($progressCallback) {
                    $progressCallback($chunkIndex, $totalChunks, $processedCount, $total);
                }

                Log::info("MLFeatureExtractionService Batch - Chunk {$chunkIndex}/{$totalChunks}, total: {$processedCount}");

            } catch (\Exception $e) {
                Log::error("MLFeatureExtractionService Batch - Erreur chunk {$chunkIndex}", [
                    'error' => $e->getMessage()
                ]);
            }
        }

        Log::info("MLFeatureExtractionService Batch - Terminé: {$processedCount} features pour {$calculationDate->toDateString()}");
        return $processedCount;
    }

    /**
     * Calcule TOUTES les features d'un client à partir de données pré-chargées (0 requêtes DB)
     */
    private function computeFeaturesFromBatchData(
        int $clientId, Carbon $calculationDate, Carbon $startDate, Carbon $recentStart,
        $billingTrans, $timweTrans, $subscription,
        string $billingPpid, int $periodDays
    ): array {
        $features = [
            'client_id' => $clientId,
            'calculation_date' => $calculationDate->toDateString(),
        ];

        // === Parse billing transactions once ===
        $totalAttempts = 0;
        $totalPayments = 0;
        $totalAmount = 0;
        $consecutiveFailures = 0;
        $lastPaymentDate = null;
        $successes = [];
        $failures = [];
        $amounts = [];
        $successByDayOfWeek = [];
        $successByHour = [];
        $endMonthSuccesses = 0;
        $beginningMonthSuccesses = 0;
        $recentFailureCount = 0;
        $recentTotalCount = 0;
        $recentSuccessfulPayments = 0;
        $failureStreak = 0;
        $failureStreakCounting = true;

        // Sort descending for failure streak calculation
        $billingDesc = $billingTrans->sortByDesc('created_at');
        // Sort ascending for normal processing
        $billingAsc = $billingTrans->sortBy('created_at');

        // Calculate failure streak (from most recent)
        foreach ($billingDesc as $transaction) {
            $result = is_string($transaction->result) ? json_decode($transaction->result, true) : $transaction->result;
            if (!is_array($result)) continue;
            $ppid = $result['pricepointId'] ?? null;
            $delivery = $result['mnoDeliveryCode'] ?? null;
            $totalCharged = isset($result['totalCharged']) ? (int)$result['totalCharged'] : 0;
            if ((string)$ppid === $billingPpid && $delivery === 'DELIVERED' && $totalCharged > 0) {
                break;
            }
            $failureStreak++;
        }

        // Process all billing transactions (ascending)
        foreach ($billingAsc as $transaction) {
            $resultRaw = $transaction->result;
            $result = is_string($resultRaw) ? json_decode($resultRaw, true) : $resultRaw;
            if (!is_array($result)) continue;

            $ppid = $result['pricepointId'] ?? null;
            $delivery = $result['mnoDeliveryCode'] ?? null;
            $totalCharged = isset($result['totalCharged']) ? (int)$result['totalCharged'] : 0;
            $createdAt = $transaction->created_at;
            $date = Carbon::parse($createdAt);
            $totalAttempts++;

            $isRecent = $date->gte($recentStart);
            if ($isRecent) $recentTotalCount++;

            $isSuccess = (string)$ppid === $billingPpid && $delivery === 'DELIVERED' && $totalCharged > 0;

            if ($isSuccess) {
                $totalPayments++;
                $totalAmount += $totalCharged / 1000;
                $consecutiveFailures = 0;
                $lastPaymentDate = $createdAt;
                $amounts[] = $totalCharged / 1000;
                $successes[] = ['date' => $date, 'amount' => $totalCharged / 1000];

                // Temporal features
                $dayOfWeek = $date->dayOfWeek == 0 ? 7 : $date->dayOfWeek;
                $successByDayOfWeek[$dayOfWeek] = ($successByDayOfWeek[$dayOfWeek] ?? 0) + 1;
                $successByHour[$date->hour] = ($successByHour[$date->hour] ?? 0) + 1;
                if ($date->day > 25) $endMonthSuccesses++;
                elseif ($date->day <= 5) $beginningMonthSuccesses++;

                if ($isRecent) $recentSuccessfulPayments++;
            } else {
                $consecutiveFailures++;
                $failures[] = ['date' => $date, 'delivery' => $delivery];
                if ($isRecent) {
                    if ($delivery === 'NO_BALANCE' || $delivery === 'NOT_DELIVERED') {
                        $recentFailureCount++;
                    }
                }
            }
        }

        // === 1. Payment Features ===
        $paymentSuccessRate = $totalAttempts > 0 ? $totalPayments / $totalAttempts : 0;
        $avgPaymentAmount = $totalPayments > 0 ? $totalAmount / $totalPayments : 0;
        $daysSinceLastPayment = $lastPaymentDate ? Carbon::parse($lastPaymentDate)->diffInDays($calculationDate) : null;
        $paymentFrequency = $periodDays > 0 ? $totalPayments / $periodDays : 0;

        $features['payment_success_rate'] = round($paymentSuccessRate, 4);
        $features['consecutive_failures'] = $consecutiveFailures;
        $features['days_since_last_payment'] = $daysSinceLastPayment;
        $features['avg_payment_amount'] = round($avgPaymentAmount, 3);
        $features['payment_frequency'] = round($paymentFrequency, 4);
        $features['total_payments'] = $totalPayments;
        $features['total_attempts'] = $totalAttempts;

        // === 2. Balance Features (from TIMWE transactions) ===
        $noBalanceCount = 0;
        $totalTimweCount = $timweTrans->count();
        foreach ($timweTrans as $t) {
            $r = is_string($t->result) ? json_decode($t->result, true) : null;
            if (is_array($r) && ($r['mnoDeliveryCode'] ?? '') === 'NO_BALANCE') {
                $noBalanceCount++;
            } elseif (is_string($t->result) && str_contains($t->result, 'NO_BALANCE')) {
                $noBalanceCount++;
            }
        }
        $balanceTrend = 'unknown';
        if ($totalTimweCount > 0) {
            $noBalanceRate = $noBalanceCount / $totalTimweCount;
            $balanceTrend = $noBalanceRate > 0.8 ? 'decreasing' : ($noBalanceRate < 0.3 ? 'stable' : 'increasing');
        }

        $features['avg_balance'] = null;
        $features['balance_volatility'] = 0;
        $features['recharge_frequency'] = 0;
        $features['recharge_amount_avg'] = 0;
        $features['days_since_recharge'] = null;
        $features['balance_trend'] = $balanceTrend;

        // === 3. Temporal Features ===
        $totalSuccesses = count($successes);
        $bestBillingDayWeek = !empty($successByDayOfWeek) ? array_keys($successByDayOfWeek, max($successByDayOfWeek))[0] : null;
        $bestBillingHour = !empty($successByHour) ? array_keys($successByHour, max($successByHour))[0] : null;
        $endMonthSuccessRate = $totalSuccesses > 0 ? $endMonthSuccesses / $totalSuccesses : 0;
        $beginningMonthSuccessRate = $totalSuccesses > 0 ? $beginningMonthSuccesses / $totalSuccesses : 0;

        $seasonalPattern = [];
        $quarters = ['Q1' => [1,2,3], 'Q2' => [4,5,6], 'Q3' => [7,8,9], 'Q4' => [10,11,12]];
        foreach ($quarters as $quarter => $months) {
            $qCount = collect($successes)->filter(fn($s) => in_array($s['date']->month, $months))->count();
            $seasonalPattern[$quarter] = $totalSuccesses > 0 ? round($qCount / $totalSuccesses, 4) : 0;
        }

        $features['best_billing_day_week'] = $bestBillingDayWeek;
        $features['best_billing_hour'] = $bestBillingHour;
        $features['seasonal_pattern'] = json_encode($seasonalPattern);
        $features['end_month_success_rate'] = round($endMonthSuccessRate, 4);
        $features['beginning_month_success_rate'] = round($beginningMonthSuccessRate, 4);

        // === 4. Usage Features (from TIMWE transactions) ===
        $statusDistribution = [];
        foreach ($timweTrans as $t) {
            $s = $t->status;
            $statusDistribution[$s] = ($statusDistribution[$s] ?? 0) + 1;
        }

        $features['total_transactions'] = $totalTimweCount;
        $features['avg_transactions_per_day'] = round($totalTimweCount / max(1, $periodDays), 4);
        $features['unique_statuses_count'] = count($statusDistribution);
        $features['status_distribution'] = json_encode($statusDistribution);

        // === 5. Demographic Features ===
        $subscriptionAgeDays = 0;
        $operatorType = 'unknown';
        if ($subscription) {
            $subscriptionAgeDays = Carbon::parse($subscription->client_abonnement_creation)->diffInDays($calculationDate);
            $operatorType = 'timwe';
        }
        $firstTrans = $timweTrans->sortBy('created_at')->first();
        $lastTrans = $timweTrans->sortByDesc('created_at')->first();

        $features['subscription_age_days'] = $subscriptionAgeDays;
        $features['region'] = null;
        $features['operator_type'] = $operatorType;
        $features['first_transaction'] = $firstTrans ? $firstTrans->created_at : null;
        $features['last_transaction'] = $lastTrans ? $lastTrans->created_at : null;

        // === 6. Risk Indicators ===
        $hasRecentFailures = $recentFailureCount > 0;
        $churnProbability = 0;
        if ($recentTotalCount > 0) {
            $rfRate = $recentFailureCount / $recentTotalCount;
            $churnProbability = $rfRate > 0.9 ? 0.8 : ($rfRate > 0.7 ? 0.5 : ($rfRate > 0.5 ? 0.3 : 0));
        }
        $isHighValueClient = $recentSuccessfulPayments >= 10;

        $features['churn_probability'] = round($churnProbability, 4);
        $features['has_recent_failures'] = $hasRecentFailures;
        $features['failure_streak'] = $failureStreak;
        $features['is_high_value_client'] = $isHighValueClient;

        // === 7. Computed Scores ===
        $features['payment_reliability_score'] = round($paymentSuccessRate, 4);
        $features['engagement_score'] = round($this->calculateEngagementScore($features), 4);
        $features['lifetime_value_score'] = round($this->calculateLifetimeValueScore($features), 4);
        $features['client_segment'] = $this->determineClientSegment(
            $features['payment_reliability_score'],
            $features['engagement_score'],
            $features['lifetime_value_score'],
            $features
        );

        // === 8. Advanced Features v2.0 ===
        $morningSuccesses = collect($successes)->filter(fn($s) => $s['date']->hour >= 6 && $s['date']->hour < 12)->count();
        $afternoonSuccesses = collect($successes)->filter(fn($s) => $s['date']->hour >= 12 && $s['date']->hour < 18)->count();
        $eveningSuccesses = collect($successes)->filter(fn($s) => $s['date']->hour >= 18 && $s['date']->hour < 22)->count();

        $features['morning_success_rate'] = $totalSuccesses > 0 ? round($morningSuccesses / $totalSuccesses, 4) : 0;
        $features['afternoon_success_rate'] = $totalSuccesses > 0 ? round($afternoonSuccesses / $totalSuccesses, 4) : 0;
        $features['evening_success_rate'] = $totalSuccesses > 0 ? round($eveningSuccesses / $totalSuccesses, 4) : 0;

        // Recovery after failure
        $totalFailures = count($failures);
        $recoveryCount = 0;
        for ($i = 0; $i < $totalFailures; $i++) {
            $failDate = $failures[$i]['date'];
            $hasRecovery = collect($successes)->contains(fn($s) => $s['date']->gt($failDate) && $s['date']->diffInDays($failDate) <= 7);
            if ($hasRecovery) $recoveryCount++;
        }
        $features['recovery_after_failure_rate'] = $totalFailures > 0 ? round($recoveryCount / $totalFailures, 4) : 0;
        $features['max_consecutive_successes'] = $this->calculateMaxConsecutiveSuccesses($successes, $failures);

        // Amount stability
        $features['payment_amount_std'] = count($amounts) > 1 ? round($this->standardDeviation($amounts), 4) : 0;
        $uniqueAmounts = collect($successes)->pluck('amount')->unique()->count();
        $features['amount_flexibility'] = $totalSuccesses > 0 ? round(min($uniqueAmounts / $totalSuccesses, 1.0), 4) : 0;

        // Failure patterns
        $noBalanceFailures = collect($failures)->filter(fn($f) => $f['delivery'] === 'NO_BALANCE')->count();
        $notDeliveredFailures = collect($failures)->filter(fn($f) => $f['delivery'] === 'NOT_DELIVERED')->count();
        $features['no_balance_failure_rate'] = $totalFailures > 0 ? round($noBalanceFailures / $totalFailures, 4) : 0;
        $features['not_delivered_failure_rate'] = $totalFailures > 0 ? round($notDeliveredFailures / $totalFailures, 4) : 0;

        return $features;
    }

    /**
     * Nettoie les anciennes données de features (garde 1 an)
     */
    public function cleanOldFeatures(): int
    {
        $cutoffDate = Carbon::now()->subYear();
        
        $deletedCount = DB::table('ml_client_features')
            ->where('calculation_date', '<', $cutoffDate)
            ->delete();

        Log::info("MLFeatureExtractionService - Nettoyage terminé", [
            'deleted_count' => $deletedCount,
            'cutoff_date' => $cutoffDate->toDateString()
        ]);

        return $deletedCount;
    }

    /**
     * NOUVEAU v2.0: Calcule des features avancées pour améliorer la discrimination
     */
    private function calculateAdvancedFeatures(int $clientId, Carbon $startDate, Carbon $endDate): array
    {
        $billingPpid = env('TIMWE_BILLING_PPID', '63980');
        $rawTransactions = $this->getCachedTransactionStats($clientId, $startDate, $endDate);
        $allTransactions = collect($rawTransactions);

        $successes = [];
        $failures = [];
        $amounts = [];

        foreach ($allTransactions as $transaction) {
            $resultRaw = is_array($transaction) ? ($transaction['result'] ?? null) : $transaction->result;
            $result = is_string($resultRaw) ? json_decode($resultRaw, true) : $resultRaw;
            if (!is_array($result)) continue;

            $ppid = $result['pricepointId'] ?? null;
            $delivery = $result['mnoDeliveryCode'] ?? null;
            $totalCharged = isset($result['totalCharged']) ? (int) $result['totalCharged'] : 0;
            $createdAt = is_array($transaction) ? ($transaction['created_at'] ?? null) : $transaction->created_at;
            $date = Carbon::parse($createdAt);
            
            $isSuccess = (string)$ppid === (string)$billingPpid && $delivery === 'DELIVERED' && $totalCharged > 0;
            
            if ($isSuccess) {
                $successes[] = ['date' => $date, 'amount' => $totalCharged / 1000];
            } else {
                $failures[] = ['date' => $date, 'delivery' => $delivery];
            }
            
            $amounts[] = $totalCharged / 1000;
        }

        // 1. Patterns temporels avancés
        $morningSuccesses = collect($successes)->filter(fn($s) => $s['date']->hour >= 6 && $s['date']->hour < 12)->count();
        $afternoonSuccesses = collect($successes)->filter(fn($s) => $s['date']->hour >= 12 && $s['date']->hour < 18)->count();
        $eveningSuccesses = collect($successes)->filter(fn($s) => $s['date']->hour >= 18 && $s['date']->hour < 22)->count();
        $totalSuccesses = count($successes);

        $morningSuccessRate = $totalSuccesses > 0 ? $morningSuccesses / $totalSuccesses : 0;
        $afternoonSuccessRate = $totalSuccesses > 0 ? $afternoonSuccesses / $totalSuccesses : 0;
        $eveningSuccessRate = $totalSuccesses > 0 ? $eveningSuccesses / $totalSuccesses : 0;

        // 2. Patterns de récupération après échec
        $recoveryAfterFailureCount = 0;
        $totalFailures = count($failures);
        
        for ($i = 0; $i < count($failures) - 1; $i++) {
            $failureDate = $failures[$i]['date'];
            // Chercher un succès dans les 7 jours suivants
            $hasRecovery = collect($successes)->filter(function($s) use ($failureDate) {
                return $s['date']->gt($failureDate) && $s['date']->diffInDays($failureDate) <= 7;
            })->isNotEmpty();
            
            if ($hasRecovery) {
                $recoveryAfterFailureCount++;
            }
        }

        $recoveryAfterFailureRate = $totalFailures > 0 ? $recoveryAfterFailureCount / $totalFailures : 0;

        // 3. Stabilité des montants
        $paymentAmountStd = count($amounts) > 1 ? $this->standardDeviation($amounts) : 0;

        // 4. Patterns d'échec spécifiques  
        $noBalanceFailures = collect($failures)->filter(fn($f) => $f['delivery'] === 'NO_BALANCE')->count();
        $notDeliveredFailures = collect($failures)->filter(fn($f) => $f['delivery'] === 'NOT_DELIVERED')->count();
        $noBalanceRate = $totalFailures > 0 ? $noBalanceFailures / $totalFailures : 0;
        $notDeliveredRate = $totalFailures > 0 ? $notDeliveredFailures / $totalFailures : 0;

        // 5. Séquences de succès maximales
        $maxConsecutiveSuccesses = $this->calculateMaxConsecutiveSuccesses($successes, $failures);

        // 6. Indicateur de flexibilité (succès avec montants différents)
        $uniqueSuccessAmounts = collect($successes)->pluck('amount')->unique()->count();
        $amountFlexibility = $totalSuccesses > 0 ? min($uniqueSuccessAmounts / $totalSuccesses, 1.0) : 0;

        return [
            // Patterns temporels fins
            'morning_success_rate' => round($morningSuccessRate, 4),
            'afternoon_success_rate' => round($afternoonSuccessRate, 4),
            'evening_success_rate' => round($eveningSuccessRate, 4),
            
            // Patterns de récupération
            'recovery_after_failure_rate' => round($recoveryAfterFailureRate, 4),
            'max_consecutive_successes' => $maxConsecutiveSuccesses,
            
            // Stabilité comportementale
            'payment_amount_std' => round($paymentAmountStd, 4),
            'amount_flexibility' => round($amountFlexibility, 4),
            
            // Patterns d'échec spécifiques
            'no_balance_failure_rate' => round($noBalanceRate, 4),
            'not_delivered_failure_rate' => round($notDeliveredRate, 4),
        ];
    }

    /**
     * Calcule l'écart-type d'un tableau de valeurs
     */
    private function standardDeviation(array $values): float
    {
        if (count($values) < 2) return 0;
        
        $mean = array_sum($values) / count($values);
        $squaredDiffs = array_map(fn($x) => pow($x - $mean, 2), $values);
        $variance = array_sum($squaredDiffs) / count($values);
        
        return sqrt($variance);
    }

    /**
     * Calcule la plus longue séquence de succès consécutifs
     */
    private function calculateMaxConsecutiveSuccesses(array $successes, array $failures): int
    {
        if (empty($successes)) return 0;
        
        // Merger et trier toutes les transactions par date
        $allEvents = [];
        foreach ($successes as $s) {
            $allEvents[] = ['date' => $s['date'], 'type' => 'success'];
        }
        foreach ($failures as $f) {
            $allEvents[] = ['date' => $f['date'], 'type' => 'failure'];
        }
        
        usort($allEvents, fn($a, $b) => $a['date']->timestamp - $b['date']->timestamp);
        
        $maxStreak = 0;
        $currentStreak = 0;
        
        foreach ($allEvents as $event) {
            if ($event['type'] === 'success') {
                $currentStreak++;
                $maxStreak = max($maxStreak, $currentStreak);
            } else {
                $currentStreak = 0;
            }
        }
        
        return $maxStreak;
    }
}