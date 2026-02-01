<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

class MLClientFeature extends Model
{
    use HasFactory;

    protected $table = 'ml_client_features';

    protected $fillable = [
        'client_id',
        'calculation_date',
        'payment_success_rate',
        'consecutive_failures',
        'days_since_last_payment',
        'avg_payment_amount',
        'payment_frequency',
        'total_payments',
        'total_attempts',
        'avg_balance',
        'balance_volatility',
        'recharge_frequency',
        'recharge_amount_avg',
        'days_since_recharge',
        'balance_trend',
        'best_billing_day_week',
        'best_billing_hour',
        'seasonal_pattern',
        'end_month_success_rate',
        'beginning_month_success_rate',
        'total_transactions',
        'avg_transactions_per_day',
        'unique_statuses_count',
        'status_distribution',
        'subscription_age_days',
        'region',
        'operator_type',
        'first_transaction',
        'last_transaction',
        'churn_probability',
        'has_recent_failures',
        'failure_streak',
        'is_high_value_client',
        'payment_reliability_score',
        'engagement_score',
        'lifetime_value_score',
        'client_segment',
        // Nouvelles features v2.0
        'morning_success_rate',
        'afternoon_success_rate', 
        'evening_success_rate',
        'recovery_after_failure_rate',
        'max_consecutive_successes',
        'payment_amount_std',
        'amount_flexibility',
        'no_balance_failure_rate',
        'not_delivered_failure_rate',
        // Features multi-opérateur v2.1
        'timwe_success_rate',
        'timwe_total_attempts',
        'timwe_has_activity',
        'eklektik_success_rate', 
        'eklektik_daily_consistency',
        'eklektik_has_activity',
        'ooredoo_success_rate',
        'ooredoo_monthly_consistency',
        'ooredoo_has_activity',
        'total_operators_used',
        'operator_diversity_score',
        'price_preference',
        'prefers_low_price',
        'prefers_high_price',
        'preferred_frequency',
        'prefers_daily_offers',
        'prefers_monthly_offers',
        'best_performing_operator',
    ];

    protected $casts = [
        'calculation_date' => 'date',
        'payment_success_rate' => 'decimal:4',
        'consecutive_failures' => 'integer',
        'days_since_last_payment' => 'integer',
        'avg_payment_amount' => 'decimal:3',
        'payment_frequency' => 'decimal:4',
        'total_payments' => 'integer',
        'total_attempts' => 'integer',
        'avg_balance' => 'decimal:3',
        'balance_volatility' => 'decimal:6',
        'recharge_frequency' => 'decimal:4',
        'recharge_amount_avg' => 'decimal:3',
        'days_since_recharge' => 'integer',
        'best_billing_day_week' => 'integer',
        'best_billing_hour' => 'integer',
        'seasonal_pattern' => 'array',
        'end_month_success_rate' => 'decimal:4',
        'beginning_month_success_rate' => 'decimal:4',
        'total_transactions' => 'integer',
        'avg_transactions_per_day' => 'decimal:4',
        'unique_statuses_count' => 'integer',
        'status_distribution' => 'array',
        'subscription_age_days' => 'integer',
        'first_transaction' => 'datetime',
        'last_transaction' => 'datetime',
        'churn_probability' => 'decimal:4',
        'has_recent_failures' => 'boolean',
        'failure_streak' => 'integer',
        'is_high_value_client' => 'boolean',
        'payment_reliability_score' => 'decimal:4',
        'engagement_score' => 'decimal:4',
        'lifetime_value_score' => 'decimal:4',
        // Nouvelles features v2.0
        'morning_success_rate' => 'decimal:4',
        'afternoon_success_rate' => 'decimal:4',
        'evening_success_rate' => 'decimal:4',
        'recovery_after_failure_rate' => 'decimal:4',
        'max_consecutive_successes' => 'integer',
        'payment_amount_std' => 'decimal:4',
        'amount_flexibility' => 'decimal:4',
        'no_balance_failure_rate' => 'decimal:4',
        'not_delivered_failure_rate' => 'decimal:4',
    ];

    /**
     * Relation avec le client
     */
    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id', 'client_id');
    }

    /**
     * Récupère les features les plus récentes pour un client
     */
    public static function getLatestForClient(int $clientId): ?self
    {
        return self::where('client_id', $clientId)
            ->orderBy('calculation_date', 'desc')
            ->first();
    }

    /**
     * Récupère les features pour une date spécifique
     */
    public static function getForDate(Carbon $date): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('calculation_date', $date->toDateString())
            ->orderBy('client_id')
            ->get();
    }

    /**
     * Récupère les clients par segment
     */
    public static function getClientsBySegment(string $segment, Carbon $date = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = self::where('client_segment', $segment);
        
        if ($date) {
            $query->where('calculation_date', $date->toDateString());
        } else {
            // Prendre la date la plus récente pour chaque client
            $query->whereIn('id', function($subQuery) {
                $subQuery->select(\DB::raw('MAX(id)'))
                    ->from('ml_client_features')
                    ->where('client_segment', $segment)
                    ->groupBy('client_id');
            });
        }
        
        return $query->orderBy('payment_reliability_score', 'desc')->get();
    }

    /**
     * Statistiques des segments
     */
    public static function getSegmentStats(Carbon $date = null): array
    {
        $query = self::select(
            'client_segment',
            \DB::raw('COUNT(*) as count'),
            \DB::raw('AVG(payment_success_rate) as avg_success_rate'),
            \DB::raw('AVG(payment_reliability_score) as avg_reliability'),
            \DB::raw('AVG(lifetime_value_score) as avg_lifetime_value'),
            \DB::raw('SUM(total_payments) as total_payments'),
            \DB::raw('SUM(CASE WHEN is_high_value_client = 1 THEN 1 ELSE 0 END) as high_value_count')
        );

        if ($date) {
            $query->where('calculation_date', $date->toDateString());
        } else {
            // Prendre la date la plus récente
            $latestDate = self::max('calculation_date');
            if ($latestDate) {
                $query->where('calculation_date', $latestDate);
            }
        }

        return $query->groupBy('client_segment')
            ->orderBy('avg_reliability', 'desc')
            ->get()
            ->map(function ($segment) {
                return [
                    'segment' => $segment->client_segment,
                    'count' => $segment->count,
                    'avg_success_rate' => round($segment->avg_success_rate * 100, 2),
                    'avg_reliability' => round($segment->avg_reliability * 100, 2),
                    'avg_lifetime_value' => round($segment->avg_lifetime_value * 100, 2),
                    'total_payments' => $segment->total_payments,
                    'high_value_count' => $segment->high_value_count,
                    'high_value_percentage' => $segment->count > 0 ? round(($segment->high_value_count / $segment->count) * 100, 1) : 0,
                ];
            })
            ->toArray();
    }

    /**
     * Récupère les tendances d'un client sur une période
     */
    public static function getClientTrends(int $clientId, Carbon $startDate, Carbon $endDate): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('client_id', $clientId)
            ->whereBetween('calculation_date', [$startDate, $endDate])
            ->orderBy('calculation_date')
            ->get();
    }

    /**
     * Trouve les clients à risque de churn
     */
    public static function getChurnRiskClients(float $threshold = 0.5, int $limit = 100): \Illuminate\Database\Eloquent\Collection
    {
        $latestDate = self::max('calculation_date');
        
        return self::where('calculation_date', $latestDate)
            ->where('churn_probability', '>=', $threshold)
            ->where('is_high_value_client', true) // Priorité aux clients de valeur
            ->orderBy('churn_probability', 'desc')
            ->orderBy('lifetime_value_score', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Performance globale du portefeuille
     */
    public static function getPortfolioPerformance(Carbon $date = null): array
    {
        if (!$date) {
            $date = Carbon::parse(self::max('calculation_date'));
        }

        $stats = self::where('calculation_date', $date->toDateString())
            ->selectRaw('
                COUNT(*) as total_clients,
                AVG(payment_success_rate) as avg_success_rate,
                AVG(payment_reliability_score) as avg_reliability,
                AVG(churn_probability) as avg_churn_risk,
                SUM(total_payments) as total_payments_portfolio,
                SUM(CASE WHEN is_high_value_client = 1 THEN 1 ELSE 0 END) as high_value_clients,
                AVG(lifetime_value_score) as avg_lifetime_value
            ')
            ->first();

        return [
            'date' => $date->toDateString(),
            'total_clients' => $stats->total_clients ?? 0,
            'avg_success_rate' => round(($stats->avg_success_rate ?? 0) * 100, 2),
            'avg_reliability' => round(($stats->avg_reliability ?? 0) * 100, 2),
            'avg_churn_risk' => round(($stats->avg_churn_risk ?? 0) * 100, 2),
            'total_payments_portfolio' => $stats->total_payments_portfolio ?? 0,
            'high_value_clients' => $stats->high_value_clients ?? 0,
            'high_value_percentage' => $stats->total_clients > 0 ? round((($stats->high_value_clients ?? 0) / $stats->total_clients) * 100, 1) : 0,
            'avg_lifetime_value' => round(($stats->avg_lifetime_value ?? 0) * 100, 2),
        ];
    }
}