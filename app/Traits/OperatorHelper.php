<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

trait OperatorHelper
{
    protected function getOperatorId($operator): ?int
    {
        if (is_numeric($operator)) {
            return (int)$operator;
        }
        
        return Cache::remember('op_id:' . md5($operator), 3600, function() use ($operator) {
            $operatorId = DB::table('country_payments_methods')
                ->whereRaw("TRIM(country_payments_methods_name) = ?", [trim($operator)])
                ->value('country_payments_methods_id');
            
            if ($operatorId) {
                return (int)$operatorId;
            }
            
            $operatorId = DB::table('country_payments_methods')
                ->whereRaw("TRIM(country_payments_methods_name) LIKE ?", ['%' . trim($operator) . '%'])
                ->value('country_payments_methods_id');
            
            return $operatorId ? (int)$operatorId : null;
        });
    }
    
    protected function applyOperatorFilter($query, string $selectedOperator, string $tableAlias = 'cpm'): void
    {
        if ($selectedOperator !== 'ALL' && !empty($selectedOperator)) {
            $operatorId = $this->getOperatorId($selectedOperator);
            
            if ($operatorId) {
                $query->where("{$tableAlias}.country_payments_methods_id", $operatorId);
            } else {
                $query->whereRaw("TRIM({$tableAlias}.country_payments_methods_name) = ?", [trim($selectedOperator)]);
            }
        }
    }
    
    protected function applyOperatorJoinAndFilter($query, string $selectedOperator, string $joinTable = 'ca', string $tableAlias = 'cpm'): bool
    {
        if ($selectedOperator !== 'ALL' && !empty($selectedOperator)) {
            $query->join("country_payments_methods as {$tableAlias}", "{$joinTable}.country_payments_methods_id", '=', "{$tableAlias}.country_payments_methods_id");
            $this->applyOperatorFilter($query, $selectedOperator, $tableAlias);
            return true;
        }
        return false;
    }
    
    protected function calculatePercentageChange($current, $previous): float
    {
        if ($previous == 0) {
            return $current > 0 ? 100.0 : 0.0;
        }
        return round((($current - $previous) / $previous) * 100, 1);
    }
    
    protected function getCacheTTL(int $periodDays): int
    {
        if ($periodDays <= 7) {
            return 1800;
        } elseif ($periodDays <= 30) {
            return 3600;
        } elseif ($periodDays <= 90) {
            return 7200;
        } else {
            return 21600;
        }
    }
}
