<?php

namespace App\Services;

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
     * Calcule les features liées aux paiements
     */
    private function calculatePaymentFeatures(int $clientId, Carbon $startDate, Carbon $endDate): array
    {
        $billingPpid = env('TIMWE_BILLING_PPID', '63980');
        
        // Récupérer toutes les tentatives de facturation
        $transactions = DB::table('transactions_history as th')
            ->where('th.client_id', $clientId)
            ->where(function($q) {
                $q->where('th.status', 'LIKE', '%TIMWE_RENEWED_NOTIF%')
                  ->orWhere('th.status', 'LIKE', '%TIMWE_CHARGE_DELIVERED%');
            })
            ->whereBetween('th.created_at', [$startDate, $endDate])
            ->whereNotNull('th.result')
            ->orderBy('th.created_at')
            ->get();

        $totalAttempts = 0;
        $totalPayments = 0;
        $totalAmount = 0;
        $consecutiveFailures = 0;
        $lastPaymentDate = null;
        $failures = [];

        foreach ($transactions as $transaction) {
            $result = json_decode($transaction->result, true);
            if (!is_array($result)) continue;
            
            $ppid = $result['pricepointId'] ?? null;
            $delivery = $result['mnoDeliveryCode'] ?? null;
            $totalCharged = isset($result['totalCharged']) ? (int)$result['totalCharged'] : 0;
            
            $totalAttempts++;
            
            if ((string)$ppid === (string)$billingPpid && $delivery === 'DELIVERED' && $totalCharged > 0) {
                // Paiement réussi
                $totalPayments++;
                $totalAmount += $totalCharged / 1000; // Convertir en TND
                $consecutiveFailures = 0; // Reset
                $lastPaymentDate = $transaction->created_at;
            } else {
                // Échec de paiement
                $consecutiveFailures++;
                $failures[] = $transaction->created_at;
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
     * Calcule les scores composés
     */
    private function calculateComputedScores(array $features): array
    {
        $paymentReliabilityScore = $features['payment_success_rate'] ?? 0;
        
        // Score d'engagement basé sur la fréquence d'usage
        $engagementScore = 0;
        if (($features['avg_transactions_per_day'] ?? 0) > 0) {
            $engagementScore = min(($features['avg_transactions_per_day'] ?? 0) / 5, 1.0); // Normalisé sur 5 transactions/jour
        }
        
        // Score de valeur client
        $lifetimeValueScore = 0;
        if (($features['total_payments'] ?? 0) > 0 && ($features['avg_payment_amount'] ?? 0) > 0) {
            $totalValue = ($features['total_payments'] ?? 0) * ($features['avg_payment_amount'] ?? 0);
            $lifetimeValueScore = min($totalValue / 100, 1.0); // Normalisé sur 100 TND
        }

        // Segmentation basique
        $segment = 'unknown';
        if ($paymentReliabilityScore >= 0.8) {
            $segment = 'premium_payers';
        } elseif ($paymentReliabilityScore >= 0.4) {
            $segment = 'regular_payers';
        } elseif ($paymentReliabilityScore >= 0.1) {
            $segment = 'struggling_payers';
        } elseif (($features['churn_probability'] ?? 0) > 0.5) {
            $segment = 'churn_risk';
        } else {
            $segment = 'high_risk';
        }

        return [
            'payment_reliability_score' => round($paymentReliabilityScore, 4),
            'engagement_score' => round($engagementScore, 4),
            'lifetime_value_score' => round($lifetimeValueScore, 4),
            'client_segment' => $segment,
        ];
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
        ];
    }

    /**
     * Extrait les features pour tous les clients actifs et les sauvegarde
     */
    public function extractAndStoreFeaturesForDate(Carbon $calculationDate): int
    {
        Log::info("MLFeatureExtractionService - Début extraction pour la date {$calculationDate->toDateString()}");

        // Récupérer tous les clients qui ont eu des transactions Timwe
        $timweOperatorIds = DB::table('country_payments_methods')
            ->whereRaw("TRIM(country_payments_methods_name) LIKE '%timwe%'")
            ->pluck('country_payments_methods_id')
            ->toArray();

        if (empty($timweOperatorIds)) {
            Log::warning("MLFeatureExtractionService - Aucun opérateur Timwe trouvé");
            return 0;
        }

        // Récupérer les clients actifs (avec abonnement ou transactions récentes)
        $activeClients = DB::table('client_abonnement as ca')
            ->whereIn('ca.country_payments_methods_id', $timweOperatorIds)
            ->where('ca.client_abonnement_creation', '<=', $calculationDate)
            ->where(function($q) use ($calculationDate) {
                $q->whereNull('ca.client_abonnement_expiration')
                  ->orWhere('ca.client_abonnement_expiration', '>=', $calculationDate);
            })
            ->distinct()
            ->pluck('ca.client_id')
            ->toArray();

        Log::info("MLFeatureExtractionService - {count} clients actifs trouvés", ['count' => count($activeClients)]);

        $processedCount = 0;
        $batchSize = 100;

        // Traiter par batches pour éviter la surcharge mémoire
        foreach (array_chunk($activeClients, $batchSize) as $batch) {
            $featuresData = [];
            
            foreach ($batch as $clientId) {
                try {
                    $features = $this->extractClientFeatures($clientId, $calculationDate);
                    $featuresData[] = $features;
                    $processedCount++;
                } catch (\Exception $e) {
                    Log::error("MLFeatureExtractionService - Erreur pour client $clientId", [
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            // Insérer le batch en base
            if (!empty($featuresData)) {
                DB::table('ml_client_features')->upsert(
                    $featuresData,
                    ['client_id', 'calculation_date'], // Clés uniques
                    array_keys($featuresData[0]) // Colonnes à mettre à jour
                );
            }
            
            Log::info("MLFeatureExtractionService - Batch traité, total: $processedCount");
        }

        Log::info("MLFeatureExtractionService - Extraction terminée pour {$calculationDate->toDateString()}", [
            'clients_processed' => $processedCount
        ]);

        return $processedCount;
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
}