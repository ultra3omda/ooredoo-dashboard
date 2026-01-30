<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class TimweDailyStat extends Model
{
    use HasFactory;

    protected $table = 'timwe_daily_stats';

    protected $fillable = [
        'stat_date',
        'new_subscriptions',
        'unsubscriptions',
        'simchurn',
        'simchurn_revenue',
        'active_subscriptions',
        'total_billings',
        'billing_rate',
        'revenue_tnd',
        'revenue_usd',
        'total_clients',
        'offers_breakdown',
        'calculated_at',
    ];

    protected $casts = [
        'stat_date' => 'date',
        'simchurn_revenue' => 'decimal:3',
        'billing_rate' => 'decimal:2',
        'revenue_tnd' => 'decimal:3',
        'revenue_usd' => 'decimal:3',
        'offers_breakdown' => 'array',
        'calculated_at' => 'datetime',
    ];

    /**
     * Récupérer les stats pour une période
     */
    public static function getStatsForPeriod(Carbon $startDate, Carbon $endDate): \Illuminate\Support\Collection
    {
        return self::whereBetween('stat_date', [
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d')
        ])
        ->orderBy('stat_date', 'asc')
        ->get();
    }

    /**
     * Vérifier si les stats existent pour une date
     */
    public static function hasStatsForDate(Carbon $date): bool
    {
        return self::where('stat_date', $date->format('Y-m-d'))->exists();
    }

    /**
     * Supprimer les stats pour une date (pour recalcul)
     */
    public static function deleteStatsForDate(Carbon $date): void
    {
        self::where('stat_date', $date->format('Y-m-d'))->delete();
    }
}

