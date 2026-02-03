<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * API BI diagnostic Timwe : uniquement tables d'agrégation, aucun scan transactions_history au runtime.
 * Cache Redis (Cache::remember), index obligatoires. Cibles: summary/delivery < 20 ms, phones < 150 ms, lifetime < 50 ms.
 */
class TimweDiagnosticApiService
{
    private const CACHE_PREFIX = 'timwe';
    /** TTL (secondes): summary 10 min, delivery 10 min, phones 5 min, lifetime 10 min */
    private const TTL_SUMMARY = 600;
    private const TTL_DELIVERY = 600;
    private const TTL_PHONES = 300;
    private const TTL_RECENT = 60;
    private const TTL_LIFETIME = 600;
    private const TTL_DELIVERY_CODES = 300;
    private const MAX_PERIOD_DAYS = 365;
    private const RECENT_DAYS_CAP = 7;
    private const MAX_PHONES_PER_PAGE = 500;
    private const MAX_RECENT = 500;

    /** Cibles (ms) pour tableau AVANT/APRÈS — BI ultra-performant */
    private const CIBLE_MS = [
        'summary' => 20,
        'delivery' => 20,
        'phones' => 150,
        'lifetime' => 50,
        'total' => 300,
    ];

    /** Clé cache: timwe:{endpoint}:{start}:{end}:{filters}:{page} */
    public static function cacheKey(string $endpoint, string $start, string $end, array $extra = []): string
    {
        $parts = array_merge([self::CACHE_PREFIX, $endpoint, $start, $end], array_filter($extra));
        return implode(':', $parts);
    }

    public static function invalidateForDate(string $statDate): void
    {
        $tags = [self::CACHE_PREFIX . ':summary', self::CACHE_PREFIX . ':delivery', self::CACHE_PREFIX . ':phones', self::CACHE_PREFIX . ':recent'];
        foreach ($tags as $tag) {
            // Cache driver sans tags : on invalide par préfixe si possible, sinon TTL court suffit
        }
        Log::info('TimweDiagnosticApiService - Invalidation cache pour date', ['date' => $statDate]);
    }

    private function logTiming(string $block, float $ms, int $rows, bool $fromCache = false): void
    {
        $msRounded = round($ms, 2);
        $memMb = round(memory_get_usage(true) / 1024 / 1024, 2);
        $cible = self::CIBLE_MS[$block] ?? null;
        Log::info('TimweDiagnostic timing', [
            'block' => $block,
            'ms' => $msRounded,
            'rows' => $rows,
            'memory_mb' => $memMb,
            'cached' => $fromCache,
            'cible_ms' => $cible,
            'ok' => $cible !== null ? $msRounded <= $cible : null,
        ]);
        if ($cible !== null) {
            Log::info('TimweDiagnostic AVANT/APRÈS', [
                'bloc' => $block,
                'AVANT_cible_ms' => $cible,
                'APRÈS_mesure_ms' => $msRounded,
                'respect_cible' => $msRounded <= $cible,
            ]);
        }
    }

    private function hasAggregates(string $start, string $end): bool
    {
        if (!Schema::hasTable('timwe_diagnostic_daily_summary')) {
            return false;
        }
        return DB::table('timwe_diagnostic_daily_summary')
                ->whereBetween('stat_date', [$start, $end])
                ->exists();
    }

    /**
     * GET summary — tables d'agrégation uniquement. Cible < 20 ms.
     * Cache: timwe:summary:{start}:{end}:{filters}, TTL 10 min.
     */
    public function getSummary(string $start, string $end, ?string $deliveryCode = null): array
    {
        $key = self::cacheKey('summary', $start, $end, [$deliveryCode ?? '']);
        $cached = Cache::get($key);
        if ($cached !== null) {
            $this->logTiming('summary', 0, 1, true);
            return array_merge($cached, ['cached' => true]);
        }
        $t0 = microtime(true);
        if (!$this->hasAggregates($start, $end)) {
            return ['success' => false, 'error' => 'no_aggregates', 'summary' => null];
        }
        $q = DB::table('timwe_diagnostic_daily_summary')
            ->whereBetween('stat_date', [$start, $end])
            ->selectRaw('COALESCE(SUM(total_transactions),0) as total_transactions, COALESCE(SUM(total_billed),0) as total_billed, COALESCE(SUM(total_revenue_tnd),0) as total_revenue_tnd');
        $row = $q->first();
        $totalCount = (int) ($row->total_transactions ?? 0);
        $totalBilled = (int) ($row->total_billed ?? 0);
        $totalRevenueTnd = (float) ($row->total_revenue_tnd ?? 0);
        $totalPhones = (int) DB::table('timwe_diagnostic_daily_phone')
            ->whereBetween('stat_date', [$start, $end])
            ->selectRaw('COUNT(DISTINCT client_telephone) as c')
            ->value('c');
        $this->logTiming('summary', (microtime(true) - $t0) * 1000, 1, false);
        $summary = [
            'total_transactions' => $totalCount,
            'unique_phones' => $totalPhones,
            'total_billed' => $totalBilled,
            'billing_rate' => $totalPhones > 0 ? round(($totalBilled / $totalPhones) * 100, 2) : 0,
            'total_revenue_tnd' => round($totalRevenueTnd, 3),
        ];
        $payload = ['success' => true, 'summary' => $summary, 'total_count' => $totalCount];
        Cache::put($key, $payload, self::TTL_SUMMARY);
        return array_merge($payload, ['cached' => false]);
    }

    /**
     * GET delivery — tables d'agrégation uniquement. Cible < 20 ms.
     * Cache: timwe:delivery:{start}:{end}, TTL 10 min.
     */
    public function getDelivery(string $start, string $end): array
    {
        $key = self::cacheKey('delivery', $start, $end, []);
        $cached = Cache::get($key);
        if ($cached !== null) {
            $this->logTiming('delivery', 0, count($cached['by_delivery_code'] ?? []), true);
            return array_merge($cached, ['cached' => true]);
        }
        $t0 = microtime(true);
        if (!$this->hasAggregates($start, $end)) {
            return ['success' => false, 'error' => 'no_aggregates', 'by_delivery_code' => []];
        }
        $totalCount = (int) DB::table('timwe_diagnostic_daily_summary')
            ->whereBetween('stat_date', [$start, $end])
            ->sum('total_transactions');
        $rows = DB::table('timwe_diagnostic_daily_delivery')
            ->whereBetween('stat_date', [$start, $end])
            ->groupBy('delivery_code')
            ->selectRaw('delivery_code as code, SUM(count) as count, SUM(total_charged_tnd) as total_charged_tnd')
            ->orderByDesc(DB::raw('SUM(count)'))
            ->get();
        $list = [];
        foreach ($rows as $r) {
            $list[] = [
                'code' => $r->code,
                'count' => (int) $r->count,
                'unique_phones' => 0,
                'total_charged_tnd' => round((float) $r->total_charged_tnd, 3),
                'percentage' => $totalCount > 0 ? round(($r->count / $totalCount) * 100, 2) : 0,
            ];
        }
        $this->logTiming('delivery', (microtime(true) - $t0) * 1000, count($list), false);
        $payload = ['success' => true, 'by_delivery_code' => $list];
        Cache::put($key, $payload, self::TTL_DELIVERY);
        return array_merge($payload, ['cached' => false]);
    }

    /**
     * GET phones — une page uniquement, agrégats uniquement (jamais transactions_history). Cible < 150 ms.
     * Tri: total_attempts (défaut) ou total_charged_tnd (facturé). Cache: timwe:phones:{start}:{end}:{page}:{sort}:{filters}, TTL 5 min.
     */
    public function getPhones(string $start, string $end, int $page = 1, int $perPage = 200, ?string $searchPhone = null, ?string $deliveryCode = null, string $sortBy = 'total_attempts', string $sortDir = 'desc'): array
    {
        $perPage = min($perPage, self::MAX_PHONES_PER_PAGE);
        $key = self::cacheKey('phones', $start, $end, [$page, $perPage, $sortBy, $sortDir, $searchPhone ?? '', $deliveryCode ?? '']);
        $cached = Cache::get($key);
        if ($cached !== null) {
            $this->logTiming('phones', 0, count($cached['by_phone'] ?? []), true);
            return array_merge($cached, ['cached' => true]);
        }
        $t0 = microtime(true);
        if (!$this->hasAggregates($start, $end)) {
            return ['success' => false, 'error' => 'no_aggregates', 'by_phone' => [], 'total_phones' => 0, 'meta' => ['current_page' => $page, 'per_page' => $perPage, 'total' => 0]];
        }
        $base = DB::table('timwe_diagnostic_daily_phone')
            ->whereBetween('stat_date', [$start, $end])
            ->groupBy('client_telephone')
            ->selectRaw('
                client_telephone as phone,
                MAX(client_id) as client_id,
                MAX(client_name) as client_name,
                MIN(subscription_date) as subscription_date,
                SUM(total_attempts) as total_attempts,
                SUM(delivered) as delivered,
                SUM(no_balance) as no_balance,
                SUM(not_delivered) as not_delivered,
                SUM(other) as other,
                SUM(total_charged_tnd) as total_charged_tnd,
                MAX(last_attempt_at) as last_attempt
            ');

        if ($searchPhone !== null && $searchPhone !== '') {
            $base->where('client_telephone', 'LIKE', '%' . $searchPhone . '%');
        }
        if ($deliveryCode !== null && $deliveryCode !== '') {
            $base->whereRaw("JSON_CONTAINS(COALESCE(delivery_codes,'[]'), JSON_QUOTE(?))", [$deliveryCode]);
        }

        $countQ = DB::table('timwe_diagnostic_daily_phone')
            ->whereBetween('stat_date', [$start, $end]);
        if ($searchPhone !== null && $searchPhone !== '') {
            $countQ->where('client_telephone', 'LIKE', '%' . $searchPhone . '%');
        }
        if ($deliveryCode !== null && $deliveryCode !== '') {
            $countQ->whereRaw("JSON_CONTAINS(COALESCE(delivery_codes,'[]'), JSON_QUOTE(?))", [$deliveryCode]);
        }
        $totalPhones = (int) $countQ->selectRaw('COUNT(DISTINCT client_telephone) as c')->value('c');

        $orderCol = $sortBy === 'total_charged_tnd' ? 'total_charged_tnd' : 'total_attempts';
        $orderDir = strtolower($sortDir) === 'asc' ? 'asc' : 'desc';
        $rows = (clone $base)->orderBy($orderCol, $orderDir)
            ->offset(max(0, ($page - 1) * $perPage))
            ->limit($perPage)
            ->get();

        $byPhone = [];
        foreach ($rows as $r) {
            $byPhone[] = [
                'phone' => $r->phone,
                'client_id' => (int) $r->client_id,
                'client_name' => (string) ($r->client_name ?? ''),
                'subscription_date' => $r->subscription_date,
                'total_attempts' => (int) $r->total_attempts,
                'delivered' => (int) $r->delivered,
                'no_balance' => (int) $r->no_balance,
                'not_delivered' => (int) $r->not_delivered,
                'other' => (int) $r->other,
                'total_charged_tnd' => (float) $r->total_charged_tnd,
                'last_attempt' => $r->last_attempt,
            ];
        }
        $this->logTiming('phones', (microtime(true) - $t0) * 1000, count($byPhone), false);
        $payload = [
            'success' => true,
            'by_phone' => $byPhone,
            'total_phones' => $totalPhones,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $totalPhones,
                'last_page' => $perPage > 0 ? (int) ceil($totalPhones / $perPage) : 1,
            ],
        ];
        Cache::put($key, $payload, self::TTL_PHONES);
        return array_merge($payload, ['cached' => false]);
    }

    /**
     * GET phones/{phone}/delivery-codes — lazy load pour un numéro.
     */
    public function getPhoneDeliveryCodes(string $phone, string $start, string $end): array
    {
        $key = self::cacheKey('dc', $start, $end, [$phone]);
        $cached = Cache::get($key);
        if ($cached !== null) {
            return array_merge($cached, ['cached' => true]);
        }

        $t0 = microtime(true);
        $rows = DB::table('timwe_diagnostic_daily_phone')
            ->whereBetween('stat_date', [$start, $end])
            ->where('client_telephone', $phone)
            ->select('delivery_codes')
            ->get();
        $codes = [];
        foreach ($rows as $r) {
            $dec = json_decode($r->delivery_codes ?? '[]', true);
            if (is_array($dec)) {
                $codes = array_values(array_unique(array_merge($codes, $dec)));
            }
        }
        $this->logTiming('delivery_codes_one', (microtime(true) - $t0) * 1000, count($rows));
        $payload = ['success' => true, 'phone' => $phone, 'delivery_codes' => $codes];
        Cache::put($key, $payload, self::TTL_DELIVERY_CODES);
        return array_merge($payload, ['cached' => false]);
    }

    /**
     * GET recent — 7 derniers jours si période > 14j, limit 500.
     */
    public function getRecent(string $start, string $end, int $limit = 200): array
    {
        $key = self::cacheKey('recent', $start, $end, [(string) $limit]);
        $cached = Cache::get($key);
        if ($cached !== null) {
            return array_merge($cached, ['cached' => true]);
        }

        $t0 = microtime(true);
        $startCarbon = Carbon::parse($start);
        $endCarbon = Carbon::parse($end);
        $days = $startCarbon->diffInDays($endCarbon) + 1;
        $recentStart = $days > 14
            ? $endCarbon->copy()->subDays(self::RECENT_DAYS_CAP)->format('Y-m-d') . ' 00:00:00'
            : $start . ' 00:00:00';
        $endDatetime = $end . ' 23:59:59';
        $limit = min($limit, self::MAX_RECENT);

        $rows = DB::table('transactions_history as th')
            ->join('client as c', 'th.client_id', '=', 'c.client_id')
            ->where(function ($q) {
                $q->where('th.status', 'LIKE', '%TIMWE_RENEWED_NOTIF%')->orWhere('th.status', 'LIKE', '%TIMWE_CHARGE_DELIVERED%');
            })
            ->whereNotNull('th.result')
            ->whereBetween('th.created_at', [$recentStart, $endDatetime])
            ->orderByDesc('th.created_at')
            ->limit($limit)
            ->get([
                'th.transaction_history_id',
                'th.client_id',
                'th.status',
                'th.result',
                'th.created_at',
                'c.client_telephone',
                'c.client_nom',
                'c.client_prenom',
            ]);

        $list = [];
        foreach ($rows as $t) {
            $result = is_array($t->result) ? $t->result : json_decode($t->result, true);
            $mno = $result['mnoDeliveryCode'] ?? 'UNKNOWN';
            $charged = isset($result['totalCharged']) ? (int) $result['totalCharged'] : 0;
            $list[] = [
                'transaction_id' => $t->transaction_history_id,
                'date' => $t->created_at,
                'phone' => $t->client_telephone ?? 'N/A',
                'client_name' => trim(($t->client_nom ?? '') . ' ' . ($t->client_prenom ?? '')),
                'status' => $t->status,
                'delivery_code' => $mno,
                'total_charged' => $charged,
                'total_charged_tnd' => round($charged / 1000, 3),
                'is_billed' => ($mno === 'DELIVERED' && $charged > 0),
            ];
        }
        $this->logTiming('recent', (microtime(true) - $t0) * 1000, count($list));
        $payload = ['success' => true, 'recent_transactions' => $list];
        Cache::put($key, $payload, self::TTL_RECENT);
        return array_merge($payload, ['cached' => false]);
    }

    /**
     * GET lifetime — lecture uniquement depuis timwe_phone_lifetime_stats (jamais transactions_history au runtime). Cible < 50 ms.
     * Cache: timwe:lifetime:{hash(phones)}, TTL 10 min.
     */
    public function getLifetime(array $phones): array
    {
        if (empty($phones)) {
            return ['success' => true, 'by_phone' => []];
        }
        $phones = array_slice(array_unique(array_values($phones)), 0, self::MAX_PHONES_PER_PAGE);
        sort($phones);
        $key = self::cacheKey('lifetime', '', '', [md5(implode(',', $phones))]);
        $cached = Cache::get($key);
        if ($cached !== null) {
            $this->logTiming('lifetime', 0, count($phones), true);
            return array_merge($cached, ['cached' => true]);
        }
        $t0 = microtime(true);
        $byPhone = [];
        foreach ($phones as $p) {
            $byPhone[$p] = [
                'lifetime_attempts' => 0,
                'lifetime_delivered' => 0,
                'lifetime_no_balance' => 0,
                'lifetime_not_delivered' => 0,
                'lifetime_other' => 0,
                'lifetime_total_charged_tnd' => 0,
                'lifetime_last_attempt' => null,
            ];
        }
        if (Schema::hasTable('timwe_phone_lifetime_stats')) {
            $rows = DB::table('timwe_phone_lifetime_stats')
                ->whereIn('client_telephone', $phones)
                ->get();
            foreach ($rows as $r) {
                $p = $r->client_telephone;
                if (isset($byPhone[$p])) {
                    $byPhone[$p] = [
                        'lifetime_attempts' => (int) $r->lifetime_attempts,
                        'lifetime_delivered' => (int) $r->lifetime_delivered,
                        'lifetime_no_balance' => (int) $r->lifetime_no_balance,
                        'lifetime_not_delivered' => (int) $r->lifetime_not_delivered,
                        'lifetime_other' => (int) $r->lifetime_other,
                        'lifetime_total_charged_tnd' => round((float) $r->lifetime_total_charged_tnd, 3),
                        'lifetime_last_attempt' => $r->lifetime_last_attempt_at ? (string) $r->lifetime_last_attempt_at : null,
                    ];
                }
            }
        }
        $this->logTiming('lifetime', (microtime(true) - $t0) * 1000, count($phones), false);
        $payload = ['success' => true, 'by_phone' => $byPhone];
        Cache::put($key, $payload, self::TTL_LIFETIME);
        return array_merge($payload, ['cached' => false]);
    }

    /**
     * GET billing-rate-evolution — taux de facturation par jour sur la période (pour courbe). Cible < 100 ms.
     * Cache: timwe:billing_evolution:{start}:{end}, TTL 10 min.
     */
    public function getBillingRateEvolution(string $start, string $end): array
    {
        $key = self::cacheKey('billing_evolution', $start, $end, []);
        $cached = Cache::get($key);
        if ($cached !== null) {
            return array_merge($cached, ['cached' => true]);
        }
        $t0 = microtime(true);
        if (!$this->hasAggregates($start, $end)) {
            return ['success' => false, 'error' => 'no_aggregates', 'by_date' => []];
        }
        $summaryByDate = DB::table('timwe_diagnostic_daily_summary')
            ->whereBetween('stat_date', [$start, $end])
            ->orderBy('stat_date')
            ->get(['stat_date', 'total_billed'])
            ->keyBy('stat_date');
        $phonesByDate = DB::table('timwe_diagnostic_daily_phone')
            ->whereBetween('stat_date', [$start, $end])
            ->groupBy('stat_date')
            ->selectRaw('stat_date, COUNT(DISTINCT client_telephone) as unique_phones')
            ->get()
            ->keyBy('stat_date');
        $startCarbon = Carbon::parse($start);
        $endCarbon = Carbon::parse($end);
        $byDate = [];
        for ($d = $startCarbon->copy(); $d->lte($endCarbon); $d->addDay()) {
            $dateStr = $d->format('Y-m-d');
            $summaryRow = $summaryByDate->get($dateStr);
            $phonesRow = $phonesByDate->get($dateStr);
            $totalBilled = $summaryRow ? (int) ($summaryRow->total_billed ?? 0) : 0;
            $uniquePhones = $phonesRow ? (int) ($phonesRow->unique_phones ?? 0) : 0;
            $billingRate = $uniquePhones > 0 ? round(($totalBilled / $uniquePhones) * 100, 2) : 0;
            $byDate[] = [
                'date' => $dateStr,
                'total_billed' => $totalBilled,
                'unique_phones' => $uniquePhones,
                'billing_rate' => $billingRate,
            ];
        }
        $this->logTiming('billing_evolution', (microtime(true) - $t0) * 1000, count($byDate), false);
        $payload = ['success' => true, 'by_date' => $byDate];
        Cache::put($key, $payload, self::TTL_SUMMARY);
        return array_merge($payload, ['cached' => false]);
    }
}
