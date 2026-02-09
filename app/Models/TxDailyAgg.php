<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class TxDailyAgg extends Model
{
    protected $table = 'tx_daily_agg';
    
    // Clé primaire composite : Laravel ne supporte pas nativement, on désactive l'auto-increment
    public $incrementing = false;
    protected $primaryKey = null;

    protected $fillable = [
        'day',
        'client_id',
        'status',
        'tx_count',
        'amount_sum',
        'amount_avg',
        'last_tx_id',
        'last_tx_at',
    ];

    protected $casts = [
        'day' => 'date',
        'client_id' => 'integer',
        'tx_count' => 'integer',
        'amount_sum' => 'decimal:3',
        'amount_avg' => 'decimal:3',
        'last_tx_id' => 'integer',
        'last_tx_at' => 'datetime',
    ];

    /**
     * Incrémente ou crée une agrégation journalière.
     * Utilise ON DUPLICATE KEY UPDATE pour l'upsert.
     */
    public static function upsertBatch(array $aggregations): int
    {
        if (empty($aggregations)) {
            return 0;
        }

        // Préparer les données pour l'insert
        $values = [];
        $bindings = [];
        
        foreach ($aggregations as $agg) {
            $values[] = '(?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())';
            $bindings[] = $agg['day'];
            $bindings[] = $agg['client_id'];
            $bindings[] = $agg['status'];
            $bindings[] = $agg['tx_count'];
            $bindings[] = $agg['amount_sum'];
            $bindings[] = $agg['amount_avg'];
            $bindings[] = $agg['last_tx_id'];
            $bindings[] = $agg['last_tx_at'];
        }

        $valuesStr = implode(',', $values);
        
        $sql = "
            INSERT INTO tx_daily_agg 
                (day, client_id, status, tx_count, amount_sum, amount_avg, last_tx_id, last_tx_at, created_at, updated_at)
            VALUES {$valuesStr}
            ON DUPLICATE KEY UPDATE
                tx_count = tx_count + VALUES(tx_count),
                amount_sum = amount_sum + VALUES(amount_sum),
                last_tx_id = GREATEST(last_tx_id, VALUES(last_tx_id)),
                last_tx_at = GREATEST(last_tx_at, VALUES(last_tx_at)),
                amount_avg = amount_sum / tx_count,
                updated_at = NOW()
        ";

        return \DB::affectingStatement($sql, $bindings);
    }

    /**
     * Récupère les agrégations pour un client sur N jours.
     */
    public static function getClientStats(int $clientId, int $days = 90): array
    {
        $cutoffDate = Carbon::now()->subDays($days);
        
        return self::where('client_id', $clientId)
            ->where('day', '>=', $cutoffDate)
            ->get()
            ->toArray();
    }

    /**
     * Récupère les agrégations pour plusieurs clients sur N jours.
     * Retourne un tableau groupé par client_id et status.
     */
    public static function getMultiClientStats(array $clientIds, int $days = 90): array
    {
        if (empty($clientIds)) {
            return [];
        }

        $cutoffDate = Carbon::now()->subDays($days);
        
        $results = \DB::table('tx_daily_agg')
            ->selectRaw('
                client_id,
                status,
                SUM(tx_count) as tx_count,
                SUM(amount_sum) as amount_sum,
                AVG(amount_avg) as amount_avg,
                MAX(last_tx_at) as last_tx_at
            ')
            ->whereIn('client_id', $clientIds)
            ->where('day', '>=', $cutoffDate)
            ->groupBy(['client_id', 'status'])
            ->get();

        // Grouper par client_id puis par status
        $grouped = [];
        foreach ($results as $row) {
            if (!isset($grouped[$row->client_id])) {
                $grouped[$row->client_id] = [];
            }
            $grouped[$row->client_id][$row->status] = (array) $row;
        }

        return $grouped;
    }

    /**
     * Nettoie les données anciennes (> N jours).
     */
    public static function cleanup(int $daysToKeep = 120): int
    {
        $cutoffDate = Carbon::now()->subDays($daysToKeep);
        
        return self::where('day', '<', $cutoffDate)->delete();
    }
}
