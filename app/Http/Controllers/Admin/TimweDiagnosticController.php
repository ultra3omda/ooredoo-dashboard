<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TransactionHistory;
use App\Models\Client;
use App\Services\TimweDiagnosticAggregateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class TimweDiagnosticController extends Controller
{
    /**
     * Afficher la page de diagnostic Timwe
     */
    public function index()
    {
        return view('admin.timwe-diagnostic');
    }

    /** Période max traitée en une seule requête (évite timeout/mémoire sur 365 jours) */
    private const CHUNK_DAYS = 31;

    /** Nombre max de transactions récentes renvoyées dans le JSON */
    private const MAX_RECENT_TRANSACTIONS = 1000;

    /** Nombre max de numéros renvoyés par page (évite payload énorme + OOM sur lifetime) */
    private const MAX_PHONES_PER_PAGE = 1000;

    /** Période max en jours pour une requête (évite OOM sur 192j+) */
    private const MAX_PERIOD_DAYS = 93;

    /**
     * Récupérer les données de diagnostic pour une période
     * Pour les périodes > 31 jours, traitement par chunks puis agrégation (support 365 jours).
     */
    public function getDiagnosticData(Request $request)
    {
        set_time_limit(300);
        ini_set('memory_limit', '256M');
        
        try {
            $startDate = $request->input('start_date', Carbon::now()->subDays(7)->format('Y-m-d'));
            $endDate = $request->input('end_date', Carbon::now()->format('Y-m-d'));
            $searchPhone = $request->input('search_phone');
            $deliveryCodeFilter = $request->input('delivery_code');
            $page = (int) $request->input('page', 1);
            $perPage = min((int) $request->input('per_page', 50), self::MAX_PHONES_PER_PAGE);

            $startCarbon = Carbon::parse($startDate);
            $endCarbon = Carbon::parse($endDate);
            $totalDays = $startCarbon->diffInDays($endCarbon) + 1;
            if ($totalDays > self::MAX_PERIOD_DAYS && !$searchPhone) {
                $endCarbon = $startCarbon->copy()->addDays(self::MAX_PERIOD_DAYS - 1);
                $endDate = $endCarbon->format('Y-m-d');
                $totalDays = self::MAX_PERIOD_DAYS;
            }

            $cacheKey = 'timwe_diagnostic:' . md5(implode('|', [
                $startDate,
                $endDate,
                $searchPhone ?? '',
                $deliveryCodeFilter ?? '',
                (string) $page,
                (string) $perPage,
            ]));
            $ttl = $searchPhone ? 600 : 300;

            $payload = Cache::get($cacheKey);
            if ($payload !== null) {
                Log::info("Diagnostic Timwe - Cache HIT", ['key' => substr($cacheKey, 0, 40)]);
                $payload['cached'] = true;
                $payload['cached_at'] = $payload['_cached_at'] ?? null;
                unset($payload['_cached_at']);
                return response()->json($payload);
            }

            Log::info("Diagnostic Timwe - Cache MISS", ['period' => "{$startDate} à {$endDate}", 'days' => $totalDays]);
            
            $hasAggregateTable = Schema::hasTable('timwe_diagnostic_daily_summary');
            $aggregateDaysInPeriod = $hasAggregateTable
                ? DB::table('timwe_diagnostic_daily_summary')
                    ->whereBetween('stat_date', [$startCarbon->format('Y-m-d'), $endCarbon->format('Y-m-d')])
                    ->count()
                : 0;
            $useAggregates = !$searchPhone && $hasAggregateTable && $aggregateDaysInPeriod > 0;

            // Longues périodes sans agrégats : ne pas lancer le scan lourd (timeout). Retourner vide.
            if (!$useAggregates && $totalDays > self::CHUNK_DAYS && !$searchPhone) {
                Log::info('Diagnostic Timwe - Période longue sans agrégats, retour vide (évite timeout)', ['period' => "{$startDate} à {$endDate}", 'days' => $totalDays]);
                $diagnosticData = [
                    'total_count' => 0,
                    'total_phones' => 0,
                    'summary' => [
                        'total_transactions' => 0,
                        'unique_phones' => 0,
                        'total_billed' => 0,
                        'billing_rate' => 0,
                        'total_revenue_tnd' => 0,
                        'delivery_codes_count' => 0,
                    ],
                    'by_phone' => [],
                    'by_delivery_code' => [],
                    'recent_transactions' => [],
                ];
            } elseif ($useAggregates) {
                $diagnosticData = $this->getDiagnosticDataFromAggregates($startCarbon, $endCarbon, $deliveryCodeFilter, $page, $perPage);
            } else {
                if ($totalDays > self::CHUNK_DAYS) {
                    $diagnosticData = $this->getDiagnosticDataChunked($startCarbon, $endCarbon, $searchPhone, $deliveryCodeFilter, $page, $perPage);
                } else {
                    $diagnosticData = $this->getDiagnosticDataSingle($startDate, $endDate, $searchPhone, $deliveryCodeFilter, $page, $perPage);
                }
            }
            
            $totalCount = $diagnosticData['total_count'];
            $byPhone = $diagnosticData['by_phone'];
            $totalPhones = $diagnosticData['total_phones'] ?? count($byPhone);
            // Lifetime retiré du flux principal (évite timeout 30s). Chargé côté frontend via GET /admin/timwe-diagnostic/api/lifetime?phones[]=...
            foreach ($byPhone as &$row) {
                $row['lifetime_attempts'] = 0;
                $row['lifetime_delivered'] = 0;
                $row['lifetime_no_balance'] = 0;
                $row['lifetime_not_delivered'] = 0;
                $row['lifetime_other'] = 0;
                $row['lifetime_total_charged_tnd'] = 0;
                $row['lifetime_last_attempt'] = null;
                $row['days_inscription_to_last'] = null;
                $lastAttemptForDays = $row['last_attempt'] ?? null;
                if (!empty($row['subscription_date']) && !empty($lastAttemptForDays)) {
                    $sub = Carbon::parse($row['subscription_date']);
                    $last = Carbon::parse($lastAttemptForDays);
                    $days = $sub->diffInDays($last);
                    $row['days_inscription_to_last'] = $days >= 0 ? (int) $days : null;
                }
            }
            unset($row);

            $payload = [
                'success' => true,
                'period' => [
                    'start' => $searchPhone ? 'Historique complet' : $startDate,
                    'end' => $searchPhone ? '' : $endDate
                ],
                'total_count' => $totalCount,
                'total_phones' => $totalPhones,
                'phones_page' => $page,
                'phones_per_page' => $perPage,
                'summary' => $diagnosticData['summary'],
                'by_phone' => $byPhone,
                'by_delivery_code' => $diagnosticData['by_delivery_code'],
                'recent_transactions' => $diagnosticData['recent_transactions'],
                '_cached_at' => now()->toISOString(),
            ];
            Cache::put($cacheKey, $payload, $ttl);
            $payload['cached'] = false;
            $payload['cached_at'] = $payload['_cached_at'];
            unset($payload['_cached_at']);
            return response()->json($payload);
            
        } catch (\Exception $e) {
            Log::error("Erreur diagnostic Timwe: " . $e->getMessage());
            Log::error($e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'error' => 'Erreur lors de la récupération des données',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Log timing simple (bloc -> ms -> rows -> mémoire) pour analyse performance.
     */
    private function logDiagnosticTiming(string $block, float $ms, int $rows): void
    {
        $memMb = round(memory_get_usage(true) / 1024 / 1024, 2);
        Log::info('TimweDiagnostic timing (legacy)', [
            'block' => $block,
            'ms' => round($ms, 2),
            'rows' => $rows,
            'memory_mb' => $memMb,
        ]);
    }

    /**
     * Lecture depuis les tables agrégées — agrégation en SQL (GROUP BY), pas de chargement massif en mémoire.
     */
    private function getDiagnosticDataFromAggregates(Carbon $startCarbon, Carbon $endCarbon, ?string $deliveryCodeFilter, int $page = 1, int $perPage = 1000): array
    {
        $startStr = $startCarbon->format('Y-m-d');
        $endStr = $endCarbon->format('Y-m-d');

        $t0 = microtime(true);
        $summaryRows = DB::table('timwe_diagnostic_daily_summary')
            ->whereBetween('stat_date', [$startStr, $endStr])
            ->selectRaw('COALESCE(SUM(total_transactions),0) as total_transactions, COALESCE(SUM(total_billed),0) as total_billed, COALESCE(SUM(total_revenue_tnd),0) as total_revenue_tnd')
            ->first();
        $totalCount = (int) ($summaryRows->total_transactions ?? 0);
        $totalBilled = (int) ($summaryRows->total_billed ?? 0);
        $totalRevenueTnd = (float) ($summaryRows->total_revenue_tnd ?? 0);
        $this->logDiagnosticTiming('summary', (microtime(true) - $t0) * 1000, 1);

        $t1 = microtime(true);
        $codesByPhone = [];
        $deliveryCodesChunkSize = 5000;
        $chunkRows = 0;
        DB::table('timwe_diagnostic_daily_phone')
            ->whereBetween('stat_date', [$startStr, $endStr])
            ->select('client_telephone', 'delivery_codes')
            ->orderBy('stat_date')
            ->orderBy('client_telephone')
            ->chunk($deliveryCodesChunkSize, function ($rows) use (&$codesByPhone, &$chunkRows, $deliveryCodeFilter) {
                $chunkRows += $rows->count();
                foreach ($rows as $row) {
                    $codes = json_decode($row->delivery_codes ?? '[]', true);
                    if (!is_array($codes)) {
                        continue;
                    }
                    if ($deliveryCodeFilter && !in_array($deliveryCodeFilter, $codes)) {
                        continue;
                    }
                    $p = $row->client_telephone;
                    if (!isset($codesByPhone[$p])) {
                        $codesByPhone[$p] = [];
                    }
                    $codesByPhone[$p] = array_values(array_unique(array_merge($codesByPhone[$p], $codes)));
                }
            });
        $this->logDiagnosticTiming('delivery_codes_chunk', (microtime(true) - $t1) * 1000, $chunkRows);

        if ($deliveryCodeFilter) {
            $phonesWithCode = array_keys(array_filter($codesByPhone, fn($codes) => in_array($deliveryCodeFilter, $codes)));
            $totalPhones = count($phonesWithCode);
        } else {
            $totalPhones = (int) DB::table('timwe_diagnostic_daily_phone')
                ->whereBetween('stat_date', [$startStr, $endStr])
                ->selectRaw('COUNT(DISTINCT client_telephone) as c')
                ->value('c');
        }

        $basePhoneQuery = DB::table('timwe_diagnostic_daily_phone')
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
        if ($deliveryCodeFilter && isset($phonesWithCode) && count($phonesWithCode) > 0) {
            $basePhoneQuery->whereIn('client_telephone', $phonesWithCode);
        } elseif ($deliveryCodeFilter && isset($phonesWithCode) && count($phonesWithCode) === 0) {
            $basePhoneQuery->whereRaw('1 = 0');
        }
        $t2 = microtime(true);
        $phoneRows = (clone $basePhoneQuery)->orderByDesc('total_attempts')
            ->offset(max(0, ($page - 1) * $perPage))
            ->limit($perPage)
            ->get();
        $this->logDiagnosticTiming('phones', (microtime(true) - $t2) * 1000, $phoneRows->count());
        $byPhoneMerged = [];
        foreach ($phoneRows as $row) {
            $p = $row->phone;
            $byPhoneMerged[] = [
                'phone' => $p,
                'client_id' => (int) $row->client_id,
                'client_name' => (string) ($row->client_name ?? ''),
                'subscription_date' => $row->subscription_date,
                'total_attempts' => (int) $row->total_attempts,
                'delivered' => (int) $row->delivered,
                'no_balance' => (int) $row->no_balance,
                'not_delivered' => (int) $row->not_delivered,
                'other' => (int) $row->other,
                'total_charged_tnd' => (float) $row->total_charged_tnd,
                'last_attempt' => $row->last_attempt,
                'delivery_codes' => $codesByPhone[$p] ?? [],
            ];
        }
        $uniquePhones = $totalPhones > 0 ? $totalPhones : count($codesByPhone);
        unset($codesByPhone);
        if (isset($phonesWithCode)) {
            unset($phonesWithCode);
        }

        $t3 = microtime(true);
        $deliveryRows = DB::table('timwe_diagnostic_daily_delivery')
            ->whereBetween('stat_date', [$startStr, $endStr])
            ->groupBy('delivery_code')
            ->selectRaw('delivery_code as code, SUM(count) as count, SUM(total_charged_tnd) as total_charged_tnd')
            ->get();
        $this->logDiagnosticTiming('delivery_query', (microtime(true) - $t3) * 1000, $deliveryRows->count());
        if ($deliveryCodeFilter) {
            $deliveryRows = $deliveryRows->where('code', $deliveryCodeFilter);
        }
        $byDeliveryArray = [];
        foreach ($deliveryRows as $row) {
            $byDeliveryArray[] = [
                'code' => $row->code,
                'count' => (int) $row->count,
                'unique_phones' => 0,
                'total_charged_tnd' => round((float) $row->total_charged_tnd, 3),
                'percentage' => $totalCount > 0 ? round(($row->count / $totalCount) * 100, 2) : 0,
            ];
        }
        usort($byDeliveryArray, fn($a, $b) => $b['count'] - $a['count']);
        foreach ($byDeliveryArray as &$dr) {
            foreach ($byPhoneMerged as $ph) {
                if (in_array($dr['code'], $ph['delivery_codes'] ?? [])) {
                    $dr['unique_phones']++;
                }
            }
        }
        unset($dr);

        $summary = [
            'total_transactions' => $totalCount,
            'unique_phones' => $uniquePhones,
            'total_billed' => $totalBilled,
            'billing_rate' => $uniquePhones > 0 ? round(($totalBilled / $uniquePhones) * 100, 2) : 0,
            'total_revenue_tnd' => round($totalRevenueTnd, 3),
            'delivery_codes_count' => count($byDeliveryArray),
        ];

        $daysInPeriod = $startCarbon->diffInDays($endCarbon) + 1;
        $recentStart = $daysInPeriod > 14
            ? $endCarbon->copy()->subDays(7)->format('Y-m-d') . ' 00:00:00'
            : $startStr . ' 00:00:00';
        $t4 = microtime(true);
        $recentTransactions = $this->getRecentTransactionsLight($recentStart, $endStr . ' 23:59:59', self::MAX_RECENT_TRANSACTIONS);
        $this->logDiagnosticTiming('recent_tx', (microtime(true) - $t4) * 1000, count($recentTransactions));

        return [
            'total_count' => $totalCount,
            'total_phones' => $uniquePhones,
            'summary' => $summary,
            'by_phone' => $byPhoneMerged,
            'by_delivery_code' => $byDeliveryArray,
            'recent_transactions' => $recentTransactions,
        ];
    }

    /**
     * Dernières N transactions (requête légère, pour affichage)
     */
    private function getRecentTransactionsLight(string $startDatetime, string $endDatetime, int $limit): array
    {
        $rows = DB::table('transactions_history as th')
            ->join('client as c', 'th.client_id', '=', 'c.client_id')
            ->where(function ($q) {
                $q->where('th.status', 'LIKE', '%TIMWE_RENEWED%')->orWhere('th.status', 'LIKE', '%TIMWE_CHARGE%');
            })
            ->whereNotNull('th.result')
            ->whereBetween('th.created_at', [$startDatetime, $endDatetime])
            ->orderBy('th.created_at', 'DESC')
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
                'pricepoint_id' => $result['pricepointId'] ?? 'N/A',
                'subscription_id' => $result['subscriptionId'] ?? $result['msisdn'] ?? 'N/A',
                'is_billed' => ($mno === 'DELIVERED' && $charged > 0),
            ];
        }
        return $list;
    }

    /**
     * Une seule requête pour la période (≤ CHUNK_DAYS jours)
     */
    private function getDiagnosticDataSingle(string $startDate, string $endDate, ?string $searchPhone, ?string $deliveryCodeFilter): array
    {
        $query = $this->buildDiagnosticQuery($startDate, $endDate, $searchPhone);
        $transactions = $query->orderBy('transactions_history.created_at', 'DESC')->get();
        $analyzed = $this->analyzeTransactions($transactions, $deliveryCodeFilter, self::MAX_RECENT_TRANSACTIONS);
        $analyzed['total_count'] = $transactions->count();
        return $analyzed;
    }

    /**
     * Période longue : découpage en chunks de CHUNK_DAYS jours (plafonnée à MAX_PERIOD_DAYS pour éviter OOM).
     */
    private function getDiagnosticDataChunked(Carbon $startCarbon, Carbon $endCarbon, ?string $searchPhone, ?string $deliveryCodeFilter, int $page = 1, int $perPage = 1000): array
    {
        $mergedSummary = [
            'total_transactions' => 0,
            'unique_phones' => 0,
            'total_billed' => 0,
            'billing_rate' => 0,
            'total_revenue_tnd' => 0,
            'delivery_codes_count' => 0,
        ];
        $mergedByPhone = [];
        $mergedByDeliveryCode = [];
        $recentTransactions = [];
        $totalCount = 0;

        $chunkStart = $startCarbon->copy();
        while ($chunkStart->lte($endCarbon)) {
            $chunkEnd = $chunkStart->copy()->addDays(self::CHUNK_DAYS - 1);
            if ($chunkEnd->gt($endCarbon)) {
                $chunkEnd = $endCarbon->copy();
            }
            $startStr = $chunkStart->format('Y-m-d');
            $endStr = $chunkEnd->format('Y-m-d');
            Log::info("Diagnostic Timwe - Chunk: {$startStr} → {$endStr}");
            
            $query = $this->buildDiagnosticQuery($startStr, $endStr, $searchPhone);
            $transactions = $query->orderBy('transactions_history.created_at', 'DESC')->get();
            $count = $transactions->count();
            $totalCount += $count;
            
            $limitRecent = ($chunkEnd->format('Y-m-d') === $endCarbon->format('Y-m-d'))
                ? self::MAX_RECENT_TRANSACTIONS
                : 0;
            $analyzed = $this->analyzeTransactions($transactions, $deliveryCodeFilter, $limitRecent);
            
            $mergedSummary['total_transactions'] += $analyzed['summary']['total_transactions'];
            $mergedSummary['total_billed'] += $analyzed['summary']['total_billed'];
            $mergedSummary['total_revenue_tnd'] += $analyzed['summary']['total_revenue_tnd'];
            
            foreach ($analyzed['by_phone'] as $row) {
                $p = $row['phone'];
                if (!isset($mergedByPhone[$p])) {
                    $mergedByPhone[$p] = $row;
                } else {
                    $mergedByPhone[$p]['total_attempts'] += $row['total_attempts'];
                    $mergedByPhone[$p]['delivered'] += $row['delivered'];
                    $mergedByPhone[$p]['no_balance'] += $row['no_balance'];
                    $mergedByPhone[$p]['not_delivered'] += $row['not_delivered'];
                    $mergedByPhone[$p]['other'] += $row['other'];
                    $mergedByPhone[$p]['total_charged_tnd'] += $row['total_charged_tnd'];
                    if (empty($mergedByPhone[$p]['last_attempt']) ||
                        (!empty($row['last_attempt']) && Carbon::parse($row['last_attempt'])->gt(Carbon::parse($mergedByPhone[$p]['last_attempt'] ?? '1970-01-01')))) {
                        $mergedByPhone[$p]['last_attempt'] = $row['last_attempt'];
                    }
                    $mergedByPhone[$p]['delivery_codes'] = array_values(array_unique(array_merge(
                        $mergedByPhone[$p]['delivery_codes'] ?? [],
                        $row['delivery_codes'] ?? []
                    )));
                }
            }
            
            foreach ($analyzed['by_delivery_code'] as $row) {
                $code = $row['code'];
                if (!isset($mergedByDeliveryCode[$code])) {
                    $mergedByDeliveryCode[$code] = [
                        'code' => $code,
                        'count' => 0,
                        'unique_phones_set' => [],
                        'total_charged_tnd' => 0,
                        'percentage' => 0,
                    ];
                }
                $mergedByDeliveryCode[$code]['count'] += $row['count'];
                $mergedByDeliveryCode[$code]['total_charged_tnd'] += $row['total_charged_tnd'];
            }
            
            foreach ($analyzed['by_phone'] as $row) {
                foreach ($row['delivery_codes'] ?? [] as $dc) {
                    if (!isset($mergedByDeliveryCode[$dc])) {
                        $mergedByDeliveryCode[$dc] = ['code' => $dc, 'count' => 0, 'unique_phones_set' => [], 'total_charged_tnd' => 0, 'percentage' => 0];
                    }
                    $mergedByDeliveryCode[$dc]['unique_phones_set'][$row['phone']] = true;
                }
            }
            
            if ($limitRecent > 0 && !empty($analyzed['recent_transactions'])) {
                $recentTransactions = $analyzed['recent_transactions'];
            }
            
            $chunkStart = $chunkEnd->copy()->addDay();
        }

        $mergedSummary['unique_phones'] = count($mergedByPhone);
        $mergedSummary['billing_rate'] = $mergedSummary['unique_phones'] > 0
            ? round(($mergedSummary['total_billed'] / $mergedSummary['unique_phones']) * 100, 2)
            : 0;
        $mergedSummary['total_revenue_tnd'] = round($mergedSummary['total_revenue_tnd'], 3);
        $mergedSummary['delivery_codes_count'] = count($mergedByDeliveryCode);
        
        $byPhoneArray = array_values($mergedByPhone);
        usort($byPhoneArray, function ($a, $b) {
            return $b['total_attempts'] - $a['total_attempts'];
        });
        
        $byDeliveryArray = [];
        $totalTx = $mergedSummary['total_transactions'];
        foreach ($mergedByDeliveryCode as $code => $data) {
            $byDeliveryArray[] = [
                'code' => $code,
                'count' => $data['count'],
                'unique_phones' => isset($data['unique_phones_set']) ? count($data['unique_phones_set']) : 0,
                'total_charged_tnd' => round($data['total_charged_tnd'], 3),
                'percentage' => $totalTx > 0 ? round(($data['count'] / $totalTx) * 100, 2) : 0,
            ];
        }
        usort($byDeliveryArray, function ($a, $b) {
            return $b['count'] - $a['count'];
        });

        $totalPhones = count($byPhoneArray);
        $byPhoneArray = array_slice($byPhoneArray, ($page - 1) * $perPage, $perPage);

        return [
            'total_count' => $totalCount,
            'total_phones' => $totalPhones,
            'summary' => $mergedSummary,
            'by_phone' => $byPhoneArray,
            'by_delivery_code' => $byDeliveryArray,
            'recent_transactions' => $recentTransactions,
        ];
    }

    /**
     * Construit la requête de base (sans get) pour le diagnostic
     */
    private function buildDiagnosticQuery(string $startDate, string $endDate, ?string $searchPhone)
    {
        $query = TransactionHistory::query()
            ->join('client as c', 'transactions_history.client_id', '=', 'c.client_id')
            ->leftJoin('client_abonnement as ca', function ($join) {
                $join->on('c.client_id', '=', 'ca.client_id')
                    ->whereRaw('ca.client_abonnement_id = (SELECT MIN(client_abonnement_id) FROM client_abonnement WHERE client_id = c.client_id)');
            })
            ->where(function ($q) {
                $q->where('transactions_history.status', 'LIKE', '%TIMWE_RENEWED%')
                    ->orWhere('transactions_history.status', 'LIKE', '%TIMWE_CHARGE%');
            })
            ->whereNotNull('transactions_history.result')
            ->select(
                'transactions_history.transaction_history_id',
                'transactions_history.client_id',
                'transactions_history.status',
                'transactions_history.result',
                'transactions_history.created_at',
                'c.client_telephone',
                'c.client_nom',
                'c.client_prenom',
                'ca.client_abonnement_creation as subscription_date'
            );
        if ($searchPhone) {
            $query->where('c.client_telephone', 'LIKE', '%' . $searchPhone . '%');
        } else {
            $query->whereBetween('transactions_history.created_at', [
                $startDate . ' 00:00:00',
                $endDate . ' 23:59:59'
            ]);
        }
        return $query;
    }

    /**
     * Lifetime stats par lots de numéros (évite une requête géante sur 10k+ phones)
     */
    private function getLifetimeStatsByPhonesBatched(array $phoneList): array
    {
        $batchSize = 1000;
        $result = [];
        foreach (array_chunk($phoneList, $batchSize) as $batch) {
            $batchResult = $this->getLifetimeStatsByPhones($batch);
            $result = array_merge($result, $batchResult);
        }
        return $result;
    }
    
    /**
     * Analyser les transactions et générer les statistiques
     * @param int $limitRecent 0 = toutes, sinon max nombre de transactions récentes à garder (évite payload énorme sur 365j)
     */
    private function analyzeTransactions($transactions, $deliveryCodeFilter = null, int $limitRecent = 0)
    {
        $phoneStats = [];
        $deliveryCodeStats = [];
        $totalTransactions = 0;
        $totalBilled = 0;
        $totalRevenue = 0;
        $recentTransactions = [];
        $recentCount = 0;
        
        foreach ($transactions as $transaction) {
            $result = is_array($transaction->result) 
                ? $transaction->result 
                : json_decode($transaction->result, true);
            
            if (!$result || !is_array($result)) {
                continue;
            }
            
            // Extraire les informations importantes
            $phone = $transaction->client_telephone ?: 'N/A';
            $mnoDeliveryCode = $result['mnoDeliveryCode'] ?? 'UNKNOWN';
            $totalCharged = isset($result['totalCharged']) ? (int)$result['totalCharged'] : 0;
            $pricepointId = $result['pricepointId'] ?? 'N/A';
            $subscriptionId = $result['subscriptionId'] ?? $result['msisdn'] ?? 'N/A';
            
            // Filtrer par delivery code si demandé
            if ($deliveryCodeFilter && $mnoDeliveryCode !== $deliveryCodeFilter) {
                continue;
            }
            
            $totalTransactions++;
            
            // Déterminer si c'est facturé
            $isBilled = ($mnoDeliveryCode === 'DELIVERED' && $totalCharged > 0);
            if ($isBilled) {
                $totalBilled++;
                $totalRevenue += $totalCharged / 1000; // Convertir millimes en TND
            }
            
            // Stats par téléphone
            if (!isset($phoneStats[$phone])) {
                $phoneStats[$phone] = [
                    'phone' => $phone,
                    'client_id' => $transaction->client_id,
                    'client_name' => trim(($transaction->client_nom ?? '') . ' ' . ($transaction->client_prenom ?? '')),
                    'subscription_date' => $transaction->subscription_date ?? null,
                    'total_attempts' => 0,
                    'delivered' => 0,
                    'no_balance' => 0,
                    'not_delivered' => 0,
                    'other' => 0,
                    'total_charged_tnd' => 0,
                    'last_attempt' => null,
                    'delivery_codes' => []
                ];
            }
            
            $phoneStats[$phone]['total_attempts']++;
            $phoneStats[$phone]['total_charged_tnd'] += $totalCharged / 1000;
            
            // Compter par type de delivery code
            switch ($mnoDeliveryCode) {
                case 'DELIVERED':
                    $phoneStats[$phone]['delivered']++;
                    break;
                case 'NO_BALANCE':
                    $phoneStats[$phone]['no_balance']++;
                    break;
                case 'NOT_DELIVERED':
                    $phoneStats[$phone]['not_delivered']++;
                    break;
                default:
                    $phoneStats[$phone]['other']++;
            }
            
            // Suivre tous les delivery codes uniques
            if (!in_array($mnoDeliveryCode, $phoneStats[$phone]['delivery_codes'])) {
                $phoneStats[$phone]['delivery_codes'][] = $mnoDeliveryCode;
            }
            
            // Date de dernière tentative
            if (!$phoneStats[$phone]['last_attempt'] || 
                Carbon::parse($transaction->created_at)->gt(Carbon::parse($phoneStats[$phone]['last_attempt']))) {
                $phoneStats[$phone]['last_attempt'] = $transaction->created_at;
            }
            
            // Stats par delivery code
            if (!isset($deliveryCodeStats[$mnoDeliveryCode])) {
                $deliveryCodeStats[$mnoDeliveryCode] = [
                    'code' => $mnoDeliveryCode,
                    'count' => 0,
                    'unique_phones' => [],
                    'total_charged_tnd' => 0
                ];
            }
            
            $deliveryCodeStats[$mnoDeliveryCode]['count']++;
            $deliveryCodeStats[$mnoDeliveryCode]['total_charged_tnd'] += $totalCharged / 1000;
            
            if (!in_array($phone, $deliveryCodeStats[$mnoDeliveryCode]['unique_phones'])) {
                $deliveryCodeStats[$mnoDeliveryCode]['unique_phones'][] = $phone;
            }
            
            // Garder les N plus récentes pour affichage (limite payload sur longues périodes)
            if ($limitRecent === 0 || $recentCount < $limitRecent) {
                $recentTransactions[] = [
                    'transaction_id' => $transaction->transaction_history_id,
                    'date' => $transaction->created_at,
                    'phone' => $phone,
                    'client_name' => trim(($transaction->client_nom ?? '') . ' ' . ($transaction->client_prenom ?? '')),
                    'status' => $transaction->status,
                    'delivery_code' => $mnoDeliveryCode,
                    'total_charged' => $totalCharged,
                    'total_charged_tnd' => round($totalCharged / 1000, 3),
                    'pricepoint_id' => $pricepointId,
                    'subscription_id' => $subscriptionId,
                    'is_billed' => $isBilled
                ];
                $recentCount++;
            }
        }
        
        // Formatter les stats par delivery code
        $deliveryCodeFormatted = [];
        foreach ($deliveryCodeStats as $code => $stats) {
            $deliveryCodeFormatted[] = [
                'code' => $code,
                'count' => $stats['count'],
                'unique_phones' => count($stats['unique_phones']),
                'total_charged_tnd' => round($stats['total_charged_tnd'], 3),
                'percentage' => $totalTransactions > 0 ? round(($stats['count'] / $totalTransactions) * 100, 2) : 0
            ];
        }
        
        // Trier par nombre de tentatives décroissant
        usort($deliveryCodeFormatted, function($a, $b) {
            return $b['count'] - $a['count'];
        });
        
        // Convertir phoneStats en array et trier
        $phoneStatsArray = array_values($phoneStats);
        usort($phoneStatsArray, function($a, $b) {
            return $b['total_attempts'] - $a['total_attempts'];
        });
        
        return [
            'summary' => [
                'total_transactions' => $totalTransactions,
                'unique_phones' => count($phoneStats),
                'total_billed' => $totalBilled,
                'billing_rate' => count($phoneStats) > 0 ? round(($totalBilled / count($phoneStats)) * 100, 2) : 0,
                'total_revenue_tnd' => round($totalRevenue, 3),
                'delivery_codes_count' => count($deliveryCodeStats)
            ],
            'by_phone' => $phoneStatsArray,
            'by_delivery_code' => $deliveryCodeFormatted,
            'recent_transactions' => $recentTransactions
        ];
    }
    
    /**
     * Statistiques lifetime (toutes périodes) par numéro pour les numéros donnés
     */
    private function getLifetimeStatsByPhones(array $phoneList): array
    {
        if (empty($phoneList)) {
            return [];
        }
        
        $query = TransactionHistory::query()
            ->join('client as c', 'transactions_history.client_id', '=', 'c.client_id')
            ->whereIn('c.client_telephone', $phoneList)
            ->where(function ($q) {
                $q->where('transactions_history.status', 'LIKE', '%TIMWE_RENEWED%')
                  ->orWhere('transactions_history.status', 'LIKE', '%TIMWE_CHARGE%');
            })
            ->whereNotNull('transactions_history.result')
            ->select('transactions_history.result', 'transactions_history.created_at', 'c.client_telephone');
        
        $rows = $query->get();
        
        $byPhone = [];
        foreach ($phoneList as $p) {
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
        
        foreach ($rows as $transaction) {
            $result = is_array($transaction->result)
                ? $transaction->result
                : json_decode($transaction->result, true);
            if (!$result || !is_array($result)) {
                continue;
            }
            $phone = $transaction->client_telephone ?: 'N/A';
            if (!isset($byPhone[$phone])) {
                continue;
            }
            $mnoDeliveryCode = $result['mnoDeliveryCode'] ?? 'UNKNOWN';
            $totalCharged = isset($result['totalCharged']) ? (int) $result['totalCharged'] : 0;
            
            $byPhone[$phone]['lifetime_attempts']++;
            $byPhone[$phone]['lifetime_total_charged_tnd'] += $totalCharged / 1000;
            
            switch ($mnoDeliveryCode) {
                case 'DELIVERED':
                    $byPhone[$phone]['lifetime_delivered']++;
                    break;
                case 'NO_BALANCE':
                    $byPhone[$phone]['lifetime_no_balance']++;
                    break;
                case 'NOT_DELIVERED':
                    $byPhone[$phone]['lifetime_not_delivered']++;
                    break;
                default:
                    $byPhone[$phone]['lifetime_other']++;
            }
            
            if (!$byPhone[$phone]['lifetime_last_attempt'] ||
                Carbon::parse($transaction->created_at)->gt(Carbon::parse($byPhone[$phone]['lifetime_last_attempt']))) {
                $byPhone[$phone]['lifetime_last_attempt'] = $transaction->created_at;
            }
        }
        
        foreach ($byPhone as $p => &$stats) {
            $stats['lifetime_total_charged_tnd'] = round($stats['lifetime_total_charged_tnd'], 3);
        }
        unset($stats);
        
        return $byPhone;
    }
    
    /**
     * Récupérer toutes les transactions lifetime d'un numéro (pour le modal Détails)
     */
    public function getPhoneTransactions(Request $request, string $phone)
    {
        try {
            $phone = trim($phone);
            if ($phone === '') {
                return response()->json(['success' => false, 'message' => 'Numéro invalide'], 400);
            }

            $cacheKey = 'timwe_diagnostic:phone:' . md5($phone) . ':transactions';
            $ttl = 600; // 10 min

            $payload = Cache::remember($cacheKey, $ttl, function () use ($phone) {
                $query = TransactionHistory::query()
                    ->join('client as c', 'transactions_history.client_id', '=', 'c.client_id')
                    ->where('c.client_telephone', $phone)
                    ->where(function ($q) {
                        $q->where('transactions_history.status', 'LIKE', '%TIMWE_RENEWED%')
                          ->orWhere('transactions_history.status', 'LIKE', '%TIMWE_CHARGE%');
                    })
                    ->whereNotNull('transactions_history.result')
                    ->select(
                        'transactions_history.transaction_history_id',
                        'transactions_history.status',
                        'transactions_history.result',
                        'transactions_history.created_at'
                    )
                    ->orderBy('transactions_history.created_at', 'DESC');

                $transactions = $query->get();
                $list = [];
                foreach ($transactions as $transaction) {
                    $result = is_array($transaction->result)
                        ? $transaction->result
                        : json_decode($transaction->result, true);
                    if (!$result || !is_array($result)) {
                        continue;
                    }
                    $mnoDeliveryCode = $result['mnoDeliveryCode'] ?? 'UNKNOWN';
                    $totalCharged = isset($result['totalCharged']) ? (int) $result['totalCharged'] : 0;
                    $isBilled = ($mnoDeliveryCode === 'DELIVERED' && $totalCharged > 0);
                    // pricepointId = identifiant de l'offre (différencie ex. 0.3 TND vs 3.0 TND)
                    $pricepointId = $result['pricepointId'] ?? $result['pricePointId'] ?? $result['pricepoint_id'] ?? null;
                    if ($pricepointId === null && isset($result['response']['pricepointId'])) {
                        $pricepointId = $result['response']['pricepointId'];
                    }
                    if ($pricepointId === null && isset($result['user']['pricepointId'])) {
                        $pricepointId = $result['user']['pricepointId'];
                    }
                    if ($pricepointId === null && isset($result['data']['pricepointId'])) {
                        $pricepointId = $result['data']['pricepointId'];
                    }
                    $productId = $result['productId'] ?? $result['product_id'] ?? null;
                    if ($productId === null && isset($result['response']['productId'])) {
                        $productId = $result['response']['productId'];
                    }
                    $list[] = [
                        'transaction_id' => $transaction->transaction_history_id,
                        'date' => $transaction->created_at,
                        'phone' => $phone,
                        'delivery_code' => $mnoDeliveryCode,
                        'total_charged' => $totalCharged,
                        'total_charged_tnd' => round($totalCharged / 1000, 3),
                        'is_billed' => $isBilled,
                        'pricepoint_id' => $pricepointId,
                        'product_id' => $productId,
                    ];
                }
                return [
                    'success' => true,
                    'phone' => $phone,
                    'transactions' => $list,
                    'total' => count($list),
                ];
            });

            return response()->json($payload);
        } catch (\Exception $e) {
            Log::error('Erreur getPhoneTransactions: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Exporter les données en CSV
     */
    public function exportCsv(Request $request)
    {
        try {
            $data = $this->getDiagnosticData($request)->getData();
            
            if (!$data->success) {
                return response()->json(['error' => 'Impossible d\'exporter les données'], 500);
            }
            
            $filename = 'timwe_diagnostic_' . $data->period->start . '_' . $data->period->end . '.csv';
            
            $headers = [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => "attachment; filename={$filename}",
            ];
            
            $callback = function() use ($data) {
                $file = fopen('php://output', 'w');
                
                // BOM UTF-8 pour Excel
                fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
                
                // En-têtes
                fputcsv($file, [
                    'Téléphone',
                    'Nom Client',
                    'Tentatives (période)',
                    'Tentatives lifetime',
                    'Nb jours (inscription → dernière tentative)',
                    'Facturé (DELIVERED)',
                    'No Balance',
                    'Non Livré',
                    'Autres',
                    'Total Facturé période (TND)',
                    'Total Facturé lifetime (TND)',
                    'Dernière Tentative',
                    'Codes Delivery'
                ]);
                
                // Données par téléphone
                foreach ($data->by_phone as $phone) {
                    $phoneArr = (array) $phone;
                    $deliveryCodes = $phoneArr['delivery_codes'] ?? [];
                    fputcsv($file, [
                        $phone->phone ?? '',
                        $phone->client_name ?? '',
                        $phone->total_attempts ?? 0,
                        $phone->lifetime_attempts ?? 0,
                        $phone->days_inscription_to_last ?? '',
                        $phone->delivered ?? 0,
                        $phone->no_balance ?? 0,
                        $phone->not_delivered ?? 0,
                        $phone->other ?? 0,
                        number_format($phone->total_charged_tnd ?? 0, 3, '.', ''),
                        number_format($phone->lifetime_total_charged_tnd ?? 0, 3, '.', ''),
                        $phone->last_attempt ?? '',
                        is_array($deliveryCodes) ? implode(', ', $deliveryCodes) : (string) $deliveryCodes
                    ]);
                }
                
                fclose($file);
            };
            
            return response()->stream($callback, 200, $headers);
            
        } catch (\Exception $e) {
            Log::error("Erreur export CSV diagnostic: " . $e->getMessage());
            return response()->json(['error' => 'Erreur lors de l\'export'], 500);
        }
    }
}
