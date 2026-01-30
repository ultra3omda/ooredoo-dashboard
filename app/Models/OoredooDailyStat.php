<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class OoredooDailyStat extends Model
{
    protected $table = 'ooredoo_daily_stats';

    protected $fillable = [
        'stat_date',
        'new_subscriptions',
        'unsubscriptions',
        'active_subscriptions',
        'total_billings',
        'billing_rate',
        'revenue_tnd',
        'total_clients',
        'offers_breakdown',
    ];

    protected $casts = [
        'stat_date' => 'date',
        'offers_breakdown' => 'array',
        'new_subscriptions' => 'integer',
        'unsubscriptions' => 'integer',
        'active_subscriptions' => 'integer',
        'total_billings' => 'integer',
        'billing_rate' => 'decimal:2',
        'revenue_tnd' => 'decimal:2',
        'total_clients' => 'integer',
    ];

    /**
     * Récupérer les statistiques pour une période donnée
     */
    public static function getStatsForPeriod(Carbon $startDate, Carbon $endDate)
    {
        return self::whereBetween('stat_date', [$startDate, $endDate])
            ->orderBy('stat_date', 'asc')
            ->get();
    }

    /**
     * Récupérer ou créer une statistique pour une date donnée
     */
    public static function getOrCreateForDate(Carbon $date)
    {
        return self::firstOrCreate(
            ['stat_date' => $date->format('Y-m-d')],
            [
                'new_subscriptions' => 0,
                'unsubscriptions' => 0,
                'active_subscriptions' => 0,
                'total_billings' => 0,
                'billing_rate' => 0,
                'revenue_tnd' => 0,
                'total_clients' => 0,
                'offers_breakdown' => [],
            ]
        );
    }
}

