<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TransactionHistory;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

/**
 * API découpée pour le diagnostic Timwe — objectif < 200 ms par endpoint sur agrégats.
 * Chaque endpoint est indépendant, mis en cache, et ne charge pas de gros ensembles en mémoire.
 */
class TimweDiagnosticApiController extends Controller
{
    private const MAX_RECENT_TRANSACTIONS = 500;
    private const MAX_PHONES_PER_PAGE = 500;
    private const RECENT_DAYS_WHEN_LONG_PERIOD = 7;
    private const CACHE_TTL_SUMMARY = 600;      // 10 min
    private const CACHE_TTL_DELIVERY = 900;     // 15 min
    private const CACHE_TTL_PHONES = 300;       // 5 min
    private const CACHE_TTL_RECENT = 60;       // 1 min
    private const CACHE_TTL_LIFETIME = 120;    // 2 min
    private const CACHE_PREFIX = 'timwe_diag';

    private function normalizePeriod(Request $request): array
    {
        $start = $request->input('start', Carbon::now()->subDays(7)->format('Y-m-d'));
        $end = $request->input('end', Carbon::now()->format('Y-m-d'));
        $startCarbon = Carbon::parse($start)->startOfDay();
        $endCarbon = Carbon::parse($end)->endOfDay();
        return [$startCarbon->format('Y-m-d'), $endCarbon->format('Y-m-d'), $startCarbon, $endCarbon];
    }

    private function logTiming(string $block, float $ms, int $rows, ?float $memMb = null): void
    {
        $mem = $memMb !== null ? round($memMb, 2) . ' MB' : round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB';
        Log::info('TimweDiagnostic timing', [
            'block' => $block,
            'ms' => round($ms, 2),
            'rows' => $rows,
            'memory' => $mem,
        ]);
    }

    private function hasAggregates(string $startStr, string $endStr): bool
    {
        if (!Schema::hasTable('timwe_diagnostic_daily_summary')) {
            return false;
        }
        return DB::table('timwe_diagnostic_daily_summary')
            ->whereBetween('stat_date', [$startStr, $endStr])
            ->exists();
    }

    /**
     * GET /api/timwe-diagnostic/summary?start=&end=&delivery_code=
     * Retourne uniquement les totaux (1 objet). < 50 ms cible.
     */
    public function summary(Request $request): JsonResponse
    {
        $t0 = microtime(true);
        [$startStr, $endStr] = $this->normalizePeriod($request);
        $deliveryCode = $request->input('delivery_code');
        $cacheKey = self::CACHE_PREFIX . ':summary:' . md5($startStr . '|' . $endStr . '|' . ($deliveryCode ?? ''));
        $payload = Cache::get($cacheKey);
        if ($payload !== null) {
            $this->logTiming('summary_cache_hit', (microtime(true) - $t0) * 1000, 0);
            return response()->json(array_merge($payload, ['cached' => true]));
        }

        if (!$this->hasAggregates($startStr, $endStr)) {
            return response()->json(['success' => false, 'error' => 'Agrégats indisponibles pour cette période'], 404);
        }

        $row = DB::table('timwe_diagnostic_daily_summary')
            ->whereBetween('stat_date', [$startStr, $endStr])
            ->selectRaw('COALESCE(SUM(total_transactions),0) as total_transactions, COALESCE(SUM(total_billed),0) as total_billed, COALESCE(SUM(total_revenue_tnd),0) as total_revenue_tnd')
            ->first();

        $totalCount = (int) ($row->total_transactions ?? 0);
        $totalBilled = (int) ($row->total_billed ?? 0);
        $totalRevenueTnd = (float) ($row->total_revenue_tnd ?? 0);
        $uniquePhones = (int) DB::table('timwe_diagnostic_daily_phone')
            ->whereBetween('stat_date', [$startStr, $endStr])
            ->distinct('client_telephone')
            ->count('client_telephone');

        $payload = [
            'success' => true,
            'period' => ['start' => $startStr, 'end' => $endStr],
            'summary' => [
                'total_transactions' => $totalCount,
                'unique_phones' => $uniquePhones,
                'total_billed' => $totalBilled,
                'billing_rate' => $uniquePhones > 0 ? round(($totalBilled / $uniquePhones) * 100, 2) : 0,
                'total_revenue_tnd' => round($totalRevenueTnd, 3),
            ],
            'cached' => false,
        ];
        Cache::put($cacheKey, $payload, self::CACHE_TTL_SUMMARY);
        $this->logTiming('summary', (microtime(true) - $t0) * 1000, 1);
        return response()->json($payload);
    }

    /**
     * GET /api/timwe-diagnostic/delivery?start=&end=
     * Stats par delivery_code. < 50 ms cible.
     */
    public function delivery(Request $request): JsonResponse
    {
        $t0 = microtime(true);
        [$startStr, $endStr] = $this->normalizePeriod($request);
        $cacheKey = self::CACHE_PREFIX . ':delivery:' . md5($startStr . '|' . $endStr);
        $payload = Cache::get($cacheKey);
        if ($payload !== null) {
            $this->logTiming('delivery_cache_hit', (microtime(true) - $t0) * 1000, 0);
            return response()->json(array_merge($payload, ['cached' => true]));
        }

        if (!$this->hasAggregates($startStr, $endStr)) {
            return response()->json(['success' => false, 'error' => 'Agrégats indisponibles'], 404);
        }

        $totalCount = (int) DB::table('timwe_diagnostic_daily_summary')
            ->whereBetween('stat_date', [$startStr, $endStr])
            ->sum('total_transactions');

        $rows = DB::table('timwe_diagnostic_daily_delivery')
            ->whereBetween('stat_date', [$startStr, $endStr])
            ->groupBy('delivery_code')
            ->selectRaw('delivery_code as code, SUM(count) as count, SUM(total_charged_tnd) as total_charged_tnd')
            ->orderByDesc(DB::raw('SUM(count)'))
            ->get();

        $list = [];
        foreach ($rows as $r) {
            $list[] = [
                'code' => $r->code,
                'count' => (int) $r->count,
                'total_charged_tnd' => round((float) $r->total_charged_tnd, 3),
                'percentage' => $totalCount > 0 ? round(($r->count / $totalCount) * 100, 2) : 0,
            ];
        }

        $payload = [
            'success' => true,
            'period' => ['start' => $startStr, 'end' => $endStr],
            'by_delivery_code' => $list,
            'cached' => false,
        ];
        Cache::put($cacheKey, $payload, self::CACHE_TTL_DELIVERY);
        $this->logTiming('delivery', (microtime(true) - $t0) * 1000, count($list));
        return response()->json($payload);
    }

    /**
     * GET /api/timwe-diagnostic/phones?start=&end=&page=&per_page=&search_phone=&delivery_code=
     * Une page de numéros uniquement, sans delivery_codes ni lifetime. < 200 ms cible.
     */
    public function phones(Request $request): JsonResponse
    {
        $t0 = microtime(true);
        [$startStr, $endStr, $startCarbon, $endCarbon] = $this->normalizePeriod($request);
        $page = max(1, (int) $request->input('page', 1));
        $perPage = min(self::MAX_PHONES_PER_PAGE, max(1, (int) $request->input('per_page', 200)));
        $searchPhone = $request->input('search_phone');
        $deliveryCode = $request->input('delivery_code');

        $cacheKey = self::CACHE_PREFIX . ':phones:' . md5($startStr . '|' . $endStr . '|' . $page . '|' . $perPage . '|' . ($searchPhone ?? '') . '|' . ($deliveryCode ?? ''));
        $payload = Cache::get($cacheKey);
        if ($payload !== null) {
            $this->logTiming('phones_cache_hit', (microtime(true) - $t0) * 1000, 0);
            return response()->json(array_merge($payload, ['cached' => true]));
        }

        if (!$this->hasAggregates($startStr, $endStr)) {
            return response()->json(['success' => false, 'error' => 'Agrégats indisponibles'], 404);
        }

        $base = DB::table('timwe_diagnostic_daily_phone')
            ->whereBetween('stat_date', [$startStr, $endStr])
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

        if ($searchPhone) {
            $base->where('client_telephone', 'LIKE', '%' . $searchPhone . '%');
        }
        if ($deliveryCode) {
            $base->whereRaw('JSON_CONTAINS(COALESCE(delivery_codes, ?), ?)', ['[]', json_encode($deliveryCode)]);
        }

        $countQuery = DB::table('timwe_diagnostic_daily_phone')
            ->whereBetween('stat_date', [$startStr, $endStr])
            ->when($searchPhone, fn ($q) => $q->where('client_telephone', 'LIKE', '%' . $searchPhone . '%'))
            ->when($deliveryCode, fn ($q) => $q->whereRaw('JSON_CONTAINS(COALESCE(delivery_codes, ?), ?)', ['[]', json_encode($deliveryCode)]));
        $totalPhones = (int) $countQuery->selectRaw('COUNT(DISTINCT client_telephone) as c')->value('c');

        $rows = (clone $base)
            ->orderByDesc('total_attempts')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        $by_phone = [];
        foreach ($rows as $r) {
            $by_phone[] = [
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

        $payload = [
            'success' => true,
            'period' => ['start' => $startStr, 'end' => $endStr],
            'by_phone' => $by_phone,
            'total_phones' => $totalPhones,
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => $totalPhones > 0 ? (int) ceil($totalPhones / $perPage) : 0,
            ],
            'cached' => false,
        ];
        Cache::put($cacheKey, $payload, self::CACHE_TTL_PHONES);
        $this->logTiming('phones', (microtime(true) - $t0) * 1000, count($by_phone));
        return response()->json($payload);
    }

    /**
     * GET /api/timwe-diagnostic/phones/{phone}/delivery-codes?start=&end=
     * Lazy load: delivery_codes pour un seul numéro.
     */
    public function phoneDeliveryCodes(Request $request, string $phone): JsonResponse
    {
        $t0 = microtime(true);
        $phone = trim($phone);
        [$startStr, $endStr] = $this->normalizePeriod($request);
        $cacheKey = self::CACHE_PREFIX . ':dc:' . md5($phone . '|' . $startStr . '|' . $endStr);
        $payload = Cache::get($cacheKey);
        if ($payload !== null) {
            $this->logTiming('delivery_codes_cache_hit', (microtime(true) - $t0) * 1000, 0);
            return response()->json(array_merge($payload, ['cached' => true]));
        }

        $rows = DB::table('timwe_diagnostic_daily_phone')
            ->whereBetween('stat_date', [$startStr, $endStr])
            ->where('client_telephone', $phone)
            ->select('delivery_codes')
            ->get();

        $codes = [];
        foreach ($rows as $r) {
            $decoded = json_decode($r->delivery_codes ?? '[]', true);
            if (is_array($decoded)) {
                $codes = array_values(array_unique(array_merge($codes, $decoded)));
            }
        }

        $payload = [
            'success' => true,
            'phone' => $phone,
            'period' => ['start' => $startStr, 'end' => $endStr],
            'delivery_codes' => $codes,
            'cached' => false,
        ];
        Cache::put($cacheKey, $payload, self::CACHE_TTL_PHONES);
        $this->logTiming('delivery_codes', (microtime(true) - $t0) * 1000, count($codes));
        return response()->json($payload);
    }

    /**
     * GET /api/timwe-diagnostic/recent?start=&end=&limit=
     * Sur longues périodes, ne scanne que les 7 derniers jours.
     */
    public function recent(Request $request): JsonResponse
    {
        $t0 = microtime(true);
        [$startStr, $endStr, $startCarbon, $endCarbon] = $this->normalizePeriod($request);
        $limit = min(self::MAX_RECENT_TRANSACTIONS, max(1, (int) $request->input('limit', 100)));
        $daysInPeriod = $startCarbon->diffInDays($endCarbon) + 1;
        $recentStart = $daysInPeriod > 14
            ? $endCarbon->copy()->subDays(self::RECENT_DAYS_WHEN_LONG_PERIOD)->format('Y-m-d') . ' 00:00:00'
            : $startStr . ' 00:00:00';
        $recentEnd = $endStr . ' 23:59:59';

        $cacheKey = self::CACHE_PREFIX . ':recent:' . md5($recentStart . '|' . $recentEnd . '|' . $limit);
        $payload = Cache::get($cacheKey);
        if ($payload !== null) {
            $this->logTiming('recent_cache_hit', (microtime(true) - $t0) * 1000, 0);
            return response()->json(array_merge($payload, ['cached' => true]));
        }

        $rows = DB::table('transactions_history as th')
            ->join('client as c', 'th.client_id', '=', 'c.client_id')
            ->where(function ($q) {
                $q->where('th.status', 'LIKE', '%TIMWE_RENEWED_NOTIF%')->orWhere('th.status', 'LIKE', '%TIMWE_CHARGE_DELIVERED%');
            })
            ->whereNotNull('th.result')
            ->whereBetween('th.created_at', [$recentStart, $recentEnd])
            ->orderByDesc('th.created_at')
            ->limit($limit)
            ->get(['th.transaction_history_id', 'th.client_id', 'th.status', 'th.result', 'th.created_at', 'c.client_telephone', 'c.client_nom', 'c.client_prenom']);

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

        $payload = [
            'success' => true,
            'period' => ['start' => $recentStart, 'end' => $recentEnd],
            'recent_transactions' => $list,
            'cached' => false,
        ];
        Cache::put($cacheKey, $payload, self::CACHE_TTL_RECENT);
        $this->logTiming('recent', (microtime(true) - $t0) * 1000, count($list));
        return response()->json($payload);
    }

    /**
     * GET /api/timwe-diagnostic/lifetime?phones[]=... (batch, page courante uniquement)
     */
    public function lifetime(Request $request): JsonResponse
    {
        $t0 = microtime(true);
        $phones = $request->input('phones', []);
        if (!is_array($phones)) {
            $phones = $phones ? [$phones] : [];
        }
        $phones = array_slice(array_unique(array_filter(array_map('trim', $phones))), 0, 500);
        if (empty($phones)) {
            $this->logTiming('lifetime', (microtime(true) - $t0) * 1000, 0);
            return response()->json(['success' => true, 'by_phone' => [], 'cached' => false]);
        }

        $sorted = $phones;
        sort($sorted);
        $cacheKey = self::CACHE_PREFIX . ':lifetime:' . md5(implode(',', $sorted));
        $payload = Cache::get($cacheKey);
        if ($payload !== null) {
            $this->logTiming('lifetime_cache_hit', (microtime(true) - $t0) * 1000, 0);
            return response()->json(array_merge($payload, ['cached' => true]));
        }

        $rows = TransactionHistory::query()
            ->join('client as c', 'transactions_history.client_id', '=', 'c.client_id')
            ->whereIn('c.client_telephone', $phones)
            ->where(function ($q) {
                $q->where('transactions_history.status', 'LIKE', '%TIMWE_RENEWED_NOTIF%')
                    ->orWhere('transactions_history.status', 'LIKE', '%TIMWE_CHARGE_DELIVERED%');
            })
            ->whereNotNull('transactions_history.result')
            ->select('transactions_history.result', 'transactions_history.created_at', 'c.client_telephone')
            ->get();

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
        foreach ($rows as $t) {
            $result = is_array($t->result) ? $t->result : json_decode($t->result, true);
            if (!$result || !is_array($result)) {
                continue;
            }
            $phone = $t->client_telephone ?? 'N/A';
            if (!isset($byPhone[$phone])) {
                continue;
            }
            $mno = $result['mnoDeliveryCode'] ?? 'UNKNOWN';
            $charged = isset($result['totalCharged']) ? (int) $result['totalCharged'] : 0;
            $byPhone[$phone]['lifetime_attempts']++;
            $byPhone[$phone]['lifetime_total_charged_tnd'] += $charged / 1000;
            switch ($mno) {
                case 'DELIVERED': $byPhone[$phone]['lifetime_delivered']++; break;
                case 'NO_BALANCE': $byPhone[$phone]['lifetime_no_balance']++; break;
                case 'NOT_DELIVERED': $byPhone[$phone]['lifetime_not_delivered']++; break;
                default: $byPhone[$phone]['lifetime_other']++;
            }
            if (!$byPhone[$phone]['lifetime_last_attempt'] || (strtotime($t->created_at) > strtotime($byPhone[$phone]['lifetime_last_attempt']))) {
                $byPhone[$phone]['lifetime_last_attempt'] = $t->created_at;
            }
        }
        foreach ($byPhone as $p => &$s) {
            $s['lifetime_total_charged_tnd'] = round($s['lifetime_total_charged_tnd'], 3);
        }
        unset($s);

        $payload = [
            'success' => true,
            'by_phone' => $byPhone,
            'cached' => false,
        ];
        Cache::put($cacheKey, $payload, self::CACHE_TTL_LIFETIME);
        $this->logTiming('lifetime', (microtime(true) - $t0) * 1000, count($rows));
        return response()->json($payload);
    }

    /**
     * Invalider les caches dont la plage inclut une date (appelé par backfill/observer).
     */
    public static function invalidateCachesForDate(string $date): void
    {
        $prefix = self::CACHE_PREFIX;
        $keys = Cache::get('timwe_diag_cache_keys', []);
        foreach ($keys as $key) {
            if (str_contains($key, $date)) {
                Cache::forget($key);
            }
        }
    }
}
