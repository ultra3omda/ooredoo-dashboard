<?php

namespace App\Http\Controllers;

use App\Services\SubStoreService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SubStoreController extends Controller
{
    private SubStoreService $subStoreService;
    private ?string $currentCampaign = null;

    public function __construct(SubStoreService $subStoreService)
    {
        $this->subStoreService = $subStoreService;
    }

    // =========================================================================
    // UTILITIES
    // =========================================================================

    private function applySubStoreFilter($query, $tableAlias = 'stores')
    {
        return $query->where(function ($q) use ($tableAlias) {
            $q->where("$tableAlias.is_sub_store", 1)
              ->orWhere("$tableAlias.store_id", 54);
        });
    }

    private function isPluxeeCampaign(string $selectedSubStore): bool
    {
        if ($selectedSubStore === 'ALL') return false;
        return DB::table('stores')
            ->where('store_name', 'LIKE', "%$selectedSubStore%")
            ->where(function ($q) {
                $q->where('store_name', 'LIKE', '%Pluxee%')
                  ->orWhereIn('store_id', [57, 61]);
            })
            ->exists();
    }

    /**
     * KPIs / listes style Pluxee (carte_recharge + filtre campagne) : sous-store nommé OU vue globale ALL.
     */
    private function shouldUsePluxeeKpiBatch(string $ss): bool
    {
        return ($this->currentCampaign || $this->isPluxeeAllCampaigns)
            && ($ss === 'ALL' || $this->isPluxeeCampaign($ss));
    }

    // =========================================================================
    // CAMPAIGN CLIENT IDS — Pre-resolved & Cached
    // =========================================================================

    private ?array $resolvedCampaignClientIds = null;
    private bool $isPluxeeAllCampaigns = false;
    private array $allowedCampaigns = [];

    /**
     * Clients distincts rattachés à une campagne (nom exact dans carte_recharge.campain_name).
     * Inclut le titulaire sur la ligne `carte_recharge` ET les bénéficiaires liés par `carte_recharge_client`
     * (carte réutilisable : plusieurs clients pour le même lot / même carte_recharge_id).
     */
    private function distinctClientIdsForCampaignName(string $campaignName): array
    {
        $fromCr = DB::table('carte_recharge')
            ->where('campain_name', $campaignName)
            ->whereNotNull('client_id')
            ->where('client_id', '!=', '')
            ->distinct()
            ->pluck('client_id');

        $fromCrc = DB::table('carte_recharge_client as crc')
            ->join('carte_recharge as cr', 'crc.carte_recharge_id', '=', 'cr.carte_recharge_id')
            ->where('cr.campain_name', $campaignName)
            ->whereNotNull('crc.client_id')
            ->distinct()
            ->pluck('crc.client_id');

        return $this->uniqueNormalizedClientIds($fromCr, $fromCrc);
    }

    /**
     * @param  array<int|string>  $allowed  Campagnes autorisées (vide = toutes)
     * @return list<string>
     */
    private function distinctClientIdsForAllCampaigns(array $allowed): array
    {
        // Sans liste de campagnes, ne jamais agréger « tous les clients du monde » (tables énormes + WHERE IN).
        if (empty($allowed)) {
            return [];
        }

        $q1 = DB::table('carte_recharge')
            ->whereNotNull('client_id')
            ->where('client_id', '!=', '');
        if (!empty($allowed)) {
            $q1->whereIn('campain_name', $allowed);
        }
        $fromCr = $q1->distinct()->pluck('client_id');

        $q2 = DB::table('carte_recharge_client as crc')
            ->join('carte_recharge as cr', 'crc.carte_recharge_id', '=', 'cr.carte_recharge_id')
            ->whereNotNull('crc.client_id');
        if (!empty($allowed)) {
            $q2->whereIn('cr.campain_name', $allowed);
        }
        $fromCrc = $q2->distinct()->pluck('crc.client_id');

        return $this->uniqueNormalizedClientIds($fromCr, $fromCrc);
    }

    /**
     * @param  \Illuminate\Support\Collection|iterable  $a
     * @param  \Illuminate\Support\Collection|iterable  $b
     * @return list<string>
     */
    private function uniqueNormalizedClientIds($a, $b): array
    {
        $seen = [];
        foreach ([$a, $b] as $col) {
            foreach ($col as $id) {
                if ($id === null || $id === '') {
                    continue;
                }
                $seen[(string) $id] = true;
            }
        }

        return array_keys($seen);
    }

    /**
     * Resolve campaign client IDs ONCE and cache them for 30 minutes.
     * When no specific campaign is selected (all campaigns), resolve ALL
     * clients from campaigns linked to the current user's access.
     */
    private function getCampaignClientIds(): array
    {
        if ($this->resolvedCampaignClientIds !== null) {
            return $this->resolvedCampaignClientIds;
        }

        if ($this->currentCampaign) {
            // v4 : inclut carte_recharge_client (cartes réutilisables / multi-clients).
            $cacheKey = 'campaign_cids:v4:' . md5($this->currentCampaign);
            $this->resolvedCampaignClientIds = Cache::remember($cacheKey, 1800, function () {
                return $this->distinctClientIdsForCampaignName($this->currentCampaign);
            });
        } elseif ($this->isPluxeeAllCampaigns) {
            $allowed = $this->allowedCampaigns;
            $cacheKey = 'campaign_cids_all:v4:' . md5(json_encode($allowed));
            $this->resolvedCampaignClientIds = Cache::remember($cacheKey, 1800, function () use ($allowed) {
                return $this->distinctClientIdsForAllCampaigns($allowed);
            });
        } else {
            $this->resolvedCampaignClientIds = [];
        }
        return $this->resolvedCampaignClientIds;
    }

    private function normalizeSubStoreParams(Request $request): array
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $comparisonStartDate = $request->input('comparison_start_date');
        $comparisonEndDate = $request->input('comparison_end_date');
        $subStore = $request->input('sub_store', 'ALL');
        $campaign = $request->input('campaign');

        if (strtolower($subStore) === 'all') $subStore = 'ALL';

        if (!$startDate || !$endDate) {
            $endDate = Carbon::now()->toDateString();
            $startDate = Carbon::now()->subDays(364)->toDateString();
        }
        if (!$comparisonStartDate || !$comparisonEndDate) {
            $comparisonEndDate = Carbon::parse($startDate)->subDay()->toDateString();
            $comparisonStartDate = Carbon::parse($comparisonEndDate)->subDays(
                Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate))
            )->toDateString();
        }

        $periodDays = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate)) + 1;
        if ($periodDays > 400) {
            throw new \InvalidArgumentException("Periode trop longue ({$periodDays} jours). Maximum: 400 jours.");
        }

        $user = auth()->user();
        $subStore = $this->validateSubStoreAccess($user, $subStore);

        // Apply campaign restriction for users with limited access
        $allowedCampaigns = $user->getAllowedCampaigns();
        if (!empty($allowedCampaigns)) {
            if (!empty($campaign) && !in_array($campaign, $allowedCampaigns)) {
                // Selected campaign not in allowed list: force first allowed
                $campaign = $allowedCampaigns[0];
            }
            // If no campaign selected: leave empty → "all campaigns" mode
            // but getCampaignClientIds will filter by allowedCampaigns
        }
        // If no restrictions but campaign selected via dropdown, apply it too
        // This allows SuperAdmin/Admin to filter by campaign when they choose one

        // Store campaign for use by Pluxee methods
        $this->currentCampaign = $campaign ?: null;
        $this->allowedCampaigns = $allowedCampaigns;

        // When Pluxee sub-store but no specific campaign: flag for "all campaigns" mode
        $isPluxee = ($subStore !== 'ALL') && $this->isPluxeeCampaign($subStore);
        if ($isPluxee && !$this->currentCampaign) {
            $this->isPluxeeAllCampaigns = true;
            
            // SuperAdmin has no restrictions: auto-resolve campaigns from this sub-store
            if (empty($this->allowedCampaigns)) {
                $this->allowedCampaigns = DB::table('carte_recharge')
                    ->join('stores', function ($j) {
                        $j->whereRaw("FIND_IN_SET(stores.store_id, carte_recharge.stores)");
                    })
                    ->where('stores.store_name', 'LIKE', "%{$subStore}%")
                    ->distinct()
                    ->pluck('carte_recharge.campain_name')
                    ->toArray();
            }
        }

        // « Tous les sub-stores » sans campagne : n'activer le mode Pluxee « toutes campagnes » QUE si l'utilisateur
        // est restreint à une liste de campagnes. Sinon (ex. SuperAdmin, allowed vide) on chargeait TOUS les
        // client_id de carte_recharge (+ carte_recharge_client) en mémoire puis des WHERE IN géants → timeouts.
        if ($subStore === 'ALL' && !$this->currentCampaign && !empty($allowedCampaigns)) {
            $this->isPluxeeAllCampaigns = true;
        }

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'comparison_start_date' => $comparisonStartDate,
            'comparison_end_date' => $comparisonEndDate,
            'sub_store' => $subStore,
            'campaign' => $this->currentCampaign,
            'period_days' => $periodDays,
            'allowed_campaigns' => $allowedCampaigns,
            'use_pluxee_batch' => $this->shouldUsePluxeeKpiBatch($subStore),
            '_split_cache_version' => 7,
        ];
    }

    private function fastCacheResponse(Request $request, string $section)
    {
        try {
            $fastStart = microtime(true);
            $params = $this->normalizeSubStoreParams($request);
            $rawKey = 'ss_raw:' . $section . ':' . md5(json_encode([
                'start_date' => $params['start_date'],
                'end_date' => $params['end_date'],
                'sub_store' => $params['sub_store'],
                'campaign' => $params['campaign'],
                'split_cache_v' => $params['_split_cache_version'] ?? 7,
            ]));
            $cached = Cache::get($rawKey);
            if ($cached) {
                // Replace original execution_time_ms with actual cache-hit time
                $decoded = json_decode($cached, true);
                if ($decoded) {
                    $decoded['execution_time_ms'] = round((microtime(true) - $fastStart) * 1000);
                    $decoded['cache_hit'] = true;
                    return response()->json($decoded);
                }
                return response($cached, 200)->header('Content-Type', 'application/json');
            }
        } catch (\Exception $e) {}
        return null;
    }

    private function validateSubStoreAccess($user, string $requestedSubStore): string
    {
        // Users with campaign restrictions: force their assigned sub-store
        if ($user->hasCampaignRestriction()) {
            $primaryOperator = $user->primaryOperator();
            if ($primaryOperator) {
                return $primaryOperator->operator_name;
            }
        }
        if ($user->isSuperAdmin()) return $requestedSubStore;
        if ($user->isAdmin() && $user->isPrimarySubStoreUser()) return $requestedSubStore;
        return $requestedSubStore;
    }

    private function calculatePercentageChange($current, $previous): float
    {
        if ($previous == 0) return $current > 0 ? 100.0 : 0.0;
        return round((($current - $previous) / $previous) * 100, 1);
    }

    private function calculateUserChange($current, $previous)
    {
        if ($current == 0 && $previous == 0) return 0;
        if ($current == 0) return $previous > 0 ? -100 : 0;
        if ($previous == 0) return $current > 0 ? 100 : 0;
        return round((($current - $previous) / $previous) * 100, 1);
    }

    // =========================================================================
    // PUBLIC ENDPOINTS — View + Data
    // =========================================================================

    public function index()
    {
        $user = auth()->user();
        $availableSubStores = $this->subStoreService->getAvailableSubStoresForUser($user);
        $defaultSubStore = $this->subStoreService->getDefaultSubStoreForUser($user);
        return view('sub-stores.dashboard', compact('availableSubStores', 'defaultSubStore', 'user'));
    }

    public function triggerWarmup(Request $request)
    {
        $user = auth()->user();
        if (!$user->isSuperAdmin()) {
            return response()->json(['error' => 'SuperAdmin uniquement'], 403);
        }

        $subStore = $request->input('sub_store');
        $cmd = 'substores:warmup --force';
        if ($subStore) {
            $cmd .= " --sub-store=\"{$subStore}\"";
        }

        // Run in background
        \Illuminate\Support\Facades\Artisan::queue($cmd);

        return response()->json([
            'success' => true,
            'message' => 'Warmup lancé en arrière-plan',
            'sub_store' => $subStore ?: 'ALL',
        ]);
    }

    public function warmupStatus()
    {
        $status = Cache::get('monitoring:substores_last_warmup');
        return response()->json([
            'success' => true,
            'last_warmup' => $status,
        ]);
    }


    public function getSubStores()
    {
        $user = auth()->user();
        $availableSubStores = $this->subStoreService->getAvailableSubStoresForUser($user);
        $defaultSubStore = $this->subStoreService->getDefaultSubStoreForUser($user);

        // Fetch campaigns for Pluxee sub-stores
        $campaigns = [];
        $allowedCampaigns = $user->getAllowedCampaigns();
        
        foreach ($availableSubStores as $store) {
            $storeName = is_array($store) ? ($store['name'] ?? '') : ($store->name ?? '');
            if (stripos($storeName, 'pluxee') !== false || stripos($storeName, 'Pluxee') !== false) {
                $storeId = is_array($store) ? ($store['store_id'] ?? null) : ($store->store_id ?? null);
                if ($storeId) {
                    $query = DB::table('carte_recharge')
                        ->where('stores', $storeId)
                        ->select('campain_name', DB::raw('COUNT(*) as total_batches'), DB::raw('SUM(card_generated_number) as total_cards'))
                        ->groupBy('campain_name')
                        ->orderBy('campain_name');
                    
                    // Filter by allowed campaigns if user has restrictions
                    if (!empty($allowedCampaigns)) {
                        $query->whereIn('campain_name', $allowedCampaigns);
                    }
                    
                    $storeCampaigns = $query->get()
                        ->map(fn($c) => [
                            'name' => $c->campain_name,
                            'batches' => (int) $c->total_batches,
                            'cards' => (int) $c->total_cards,
                        ])->toArray();
                    $campaigns[$storeName] = $storeCampaigns;
                }
            }
        }

        return response()->json([
            'sub_stores' => $availableSubStores,
            'default_sub_store' => $defaultSubStore,
            'user_role' => $user->role ? $user->role->name : 'unknown',
            'campaigns' => $campaigns,
            'has_campaign_restriction' => $user->hasCampaignRestriction(),
            'allowed_campaigns' => $allowedCampaigns,
            'can_invite' => $user->canInviteCollaborators(),
        ]);
    }

    public function getExpirationsAsync(Request $request)
    {
        try {
            $p = $this->normalizeSubStoreParams($request);
            $subStore = $p['sub_store'];
            $campaign = $this->currentCampaign;
            $cacheKey = "expirations_{$subStore}_" . ($campaign ?: 'ALL');
            $data = Cache::remember($cacheKey, 600, function () use ($subStore, $campaign) {
                return $this->getExpirationsByMonth($subStore, 12, $campaign);
            });
            return response()->json(['expirationsByMonth' => $data]);
        } catch (\Exception $e) {
            return response()->json(['expirationsByMonth' => [], 'error' => $e->getMessage()], 500);
        }
    }

    public function getCampaignsSplit(Request $request)
    {
        try {
            $user = auth()->user();
            $subStore = $request->input('sub_store', '');
            $allowedCampaigns = $user->getAllowedCampaigns();

            // Find the store_id for this sub-store
            $store = DB::table('stores')
                ->where('store_name', $subStore)
                ->where('store_active', 1)
                ->first();

            if (!$store) {
                return response()->json(['success' => true, 'data' => []]);
            }

            $query = DB::table('carte_recharge')
                ->where('stores', $store->store_id)
                ->select('campain_name', DB::raw('COUNT(*) as total_batches'), DB::raw('SUM(card_generated_number) as total_cards'))
                ->groupBy('campain_name')
                ->orderBy('campain_name');

            if (!empty($allowedCampaigns)) {
                $query->whereIn('campain_name', $allowedCampaigns);
            }

            $campaigns = $query->get()->map(fn($c) => [
                'name' => $c->campain_name,
                'batches' => (int) $c->total_batches,
                'cards' => (int) $c->total_cards,
            ])->toArray();

            return response()->json(['success' => true, 'data' => $campaigns]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'data' => [], 'error' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // SPLIT ENDPOINTS — Parallel loading (pattern DataControllerOptimized)
    // =========================================================================

    public function getKpisSplit(Request $request)
    {
        $fast = $this->fastCacheResponse($request, 'kpis');
        if ($fast) return $fast;

        // Aligné sur le timeout fetch front (180s) + marge ; les logs montraient des fatal 120s sur requêtes lourdes
        set_time_limit(300);
        $start = microtime(true);
        try {
            $p = $this->normalizeSubStoreParams($request);
            $cacheKey = 'ss_split:kpis:' . md5(json_encode($p));
            $data = Cache::remember($cacheKey, 14400, function () use ($p) {
                return $this->computeKpis($p);
            });
            return response()->json(['success' => true, 'section' => 'kpis', 'data' => $data, 'execution_time_ms' => round((microtime(true) - $start) * 1000)]);
        } catch (\Exception $e) {
            Log::error("Split kpis error: " . $e->getMessage());
            return response()->json(['success' => false, 'section' => 'kpis', 'error' => $e->getMessage()], 500);
        }
    }

    public function getStoresSplit(Request $request)
    {
        $fast = $this->fastCacheResponse($request, 'stores');
        if ($fast) return $fast;

        set_time_limit(300);
        $start = microtime(true);
        try {
            $p = $this->normalizeSubStoreParams($request);
            $cacheKey = 'ss_split:stores:' . md5(json_encode($p));
            $data = Cache::remember($cacheKey, 14400, function () use ($p) {
                return $this->computeTopSubStores($p['sub_store'], $p['start_date'], $p['end_date']);
            });
            $campaignFilter = $this->currentCampaign;
            if (!$campaignFilter && $this->isPluxeeAllCampaigns) {
                $campaignFilter = 'all';
            }
            return response()->json([
                'success' => true, 'section' => 'stores', 'data' => $data,
                'campaign_filter' => $campaignFilter,
                'execution_time_ms' => round((microtime(true) - $start) * 1000)
            ]);
        } catch (\Exception $e) {
            Log::error("Split stores error: " . $e->getMessage());
            return response()->json(['success' => false, 'section' => 'stores', 'error' => $e->getMessage()], 500);
        }
    }

    public function getChartsSplit(Request $request)
    {
        $fast = $this->fastCacheResponse($request, 'charts');
        if ($fast) return $fast;

        set_time_limit(300);
        $start = microtime(true);
        try {
            $p = $this->normalizeSubStoreParams($request);
            $cacheKey = 'ss_split:charts:' . md5(json_encode($p));
            $data = Cache::remember($cacheKey, 14400, function () use ($p) {
                $currentCategories = $this->getCategoryDistribution($p['start_date'], $p['end_date'], $p['sub_store']);
                $compCategories = $this->getCategoryDistribution($p['comparison_start_date'], $p['comparison_end_date'], $p['sub_store']);
                
                // Build comparison lookup
                $compMap = [];
                foreach ($compCategories as $cc) {
                    $compMap[$cc['category']] = $cc['transactions'];
                }
                
                // Add evolution to current categories
                foreach ($currentCategories as &$cat) {
                    $prev = $compMap[$cat['category']] ?? 0;
                    $cur = $cat['transactions'];
                    if ($prev > 0) {
                        $cat['evolution'] = round((($cur - $prev) / $prev) * 100, 1);
                    } else {
                        $cat['evolution'] = $cur > 0 ? 100.0 : 0.0;
                    }
                }
                unset($cat);
                
                return [
                    'categoryDistribution' => $currentCategories,
                    'inscriptionsTrend' => $this->getInscriptionsTrend($p['start_date'], $p['end_date'], $p['sub_store']),
                ];
            });
            return response()->json(['success' => true, 'section' => 'charts', 'data' => $data, 'execution_time_ms' => round((microtime(true) - $start) * 1000)]);
        } catch (\Exception $e) {
            Log::error("Split charts error: " . $e->getMessage());
            return response()->json(['success' => false, 'section' => 'charts', 'error' => $e->getMessage()], 500);
        }
    }

    public function getMerchantsSplit(Request $request)
    {
        $fast = $this->fastCacheResponse($request, 'merchants');
        if ($fast) return $fast;

        set_time_limit(300);
        $start = microtime(true);
        try {
            $p = $this->normalizeSubStoreParams($request);
            $cacheKey = 'ss_split:merchants:' . md5(json_encode($p));
            $data = Cache::remember($cacheKey, 14400, function () use ($p) {
                return $this->getMerchantData($p['sub_store'], $p['start_date'], $p['end_date'], $p['comparison_start_date'], $p['comparison_end_date']);
            });
            return response()->json(['success' => true, 'section' => 'merchants', 'data' => $data, 'execution_time_ms' => round((microtime(true) - $start) * 1000)]);
        } catch (\Exception $e) {
            Log::error("Split merchants error: " . $e->getMessage());
            return response()->json(['success' => false, 'section' => 'merchants', 'error' => $e->getMessage()], 500);
        }
    }

    public function getUsersSplit(Request $request)
    {
        $fast = $this->fastCacheResponse($request, 'users');
        if ($fast) return $fast;

        set_time_limit(300);
        $start = microtime(true);
        try {
            $p = $this->normalizeSubStoreParams($request);
            // Cap users period to 30 days max
            $sd = Carbon::parse($p['start_date']);
            $ed = Carbon::parse($p['end_date']);
            if ($sd->diffInDays($ed) > 30) $sd = $ed->copy()->subDays(29);

            $cacheKey = 'ss_split:users:' . md5(json_encode($p));
            $data = Cache::remember($cacheKey, 14400, function () use ($p, $sd, $ed) {
                return [
                    'users_kpis' => $this->getUsersKPIs($sd, $ed, Carbon::parse($p['comparison_start_date']), Carbon::parse($p['comparison_end_date']), $p['sub_store']),
                    'users' => $this->getUsersList($sd, $ed->endOfDay(), $p['sub_store'], 150),
                ];
            });
            return response()->json(['success' => true, 'section' => 'users', 'data' => $data, 'execution_time_ms' => round((microtime(true) - $start) * 1000)]);
        } catch (\Exception $e) {
            Log::error("Split users error: " . $e->getMessage());
            return response()->json(['success' => false, 'section' => 'users', 'error' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // COMPUTATION — KPIs
    // =========================================================================

    private function computeKpis(array $p): array
    {
        $ss = $p['sub_store'];

        // ── PLUXEE BATCH: 3 queries instead of 15+ (y compris sub_store = ALL) ──
        if (!empty($p['use_pluxee_batch'])) {
            return $this->computeKpisPluxeeBatch($p);
        }

        // ── STANDARD PATH (unchanged, individual cached methods) ──
        $sd = $p['start_date'];
        $ed = $p['end_date'];
        $csd = $p['comparison_start_date'];
        $ced = $p['comparison_end_date'];

        $distributed       = $this->getDistributedCards($ss);
        $inscriptions      = $this->getInscriptionsWithCards($ss);
        $activeUsers       = $this->getActiveUsersWithCards($ss);
        $transactions      = $this->getTransactionsWithCards($ss);
        $totalSubscriptions = $this->getTotalSubscriptions($ss);
        $activeUsersCohorte = $this->getActiveUsersWithCardsCohorte($ss, $sd, $ed);
        $clientsWithTransactions = $this->getUsersWithCardsCount($ss);
        $inscriptionsCohorte = $this->getInscriptionsWithCardsCohorte($ss, $sd, $ed);
        $cardsActivated     = $this->getCardsActivated($ss, $sd, $ed);
        $conversionRate     = $distributed > 0 ? round(($inscriptions / $distributed) * 100, 1) : 0;

        $activeUsersCohorteComp = $this->getUsersWithCardsCohorteCount($ss, $csd, $ced);
        $clientsWithTransactionsComp = $this->getUsersWithCardsCount($ss);
        $inscriptionsCohorteComp = $this->getInscriptionsWithCardsCohorte($ss, $csd, $ced);
        $cardsActivatedComp     = $this->getCardsActivated($ss, $csd, $ced);

        $kpiPair = function ($cur, $prev) {
            return ['current' => $cur, 'previous' => $prev, 'change' => $this->calculatePercentageChange($cur, $prev)];
        };

        return [
            'distributed'        => $kpiPair($distributed, $distributed),
            'inscriptions'       => $kpiPair($inscriptions, $inscriptions),
            'activeUsers'        => $kpiPair($activeUsers, $clientsWithTransactions),
            'activeUsersCohorte' => $kpiPair($activeUsersCohorte, $activeUsersCohorteComp),
            'transactions'       => $kpiPair($transactions, $transactions),
            'totalSubscriptions' => $kpiPair($totalSubscriptions, $totalSubscriptions),
            'renewalRate'        => $kpiPair($cardsActivated, $cardsActivatedComp),
            'clientsWithTransactions' => $kpiPair($clientsWithTransactions, $clientsWithTransactionsComp),
            'inscriptionsCohorte' => $kpiPair($inscriptionsCohorte, $inscriptionsCohorteComp),
            'conversionRate'     => $kpiPair($conversionRate, $conversionRate),
        ];
    }

    /**
     * OPTIMISED BATCH: Compute ALL Pluxee KPIs in 3 SQL queries instead of 15+.
     * 
     * Query 1: Distributed cards (carte_recharge → SUM)
     * Query 2: Subscription-based KPIs (client + client_abonnement → CASE WHEN)
     * Query 3: Transaction-based KPIs (history → CASE WHEN)
     */
    private function computeKpisPluxeeBatch(array $p): array
    {
        $ss  = $p['sub_store'];
        $sd  = Carbon::parse($p['start_date'])->startOfDay()->toDateTimeString();
        $ed  = Carbon::parse($p['end_date'])->endOfDay()->toDateTimeString();
        $csd = Carbon::parse($p['comparison_start_date'])->startOfDay()->toDateTimeString();
        $ced = Carbon::parse($p['comparison_end_date'])->endOfDay()->toDateTimeString();
        $now = Carbon::now()->toDateTimeString();

        $kpiPair = function ($cur, $prev) {
            return ['current' => (int) $cur, 'previous' => (int) $prev, 'change' => $this->calculatePercentageChange($cur, $prev)];
        };

        $clientIds = $this->getCampaignClientIds();

        // ── Query 1: Distributed cards ──
        $distributed = $this->getPluxeeDistributed($ss);

        if (empty($clientIds)) {
            $z = $kpiPair(0, 0);
            return [
                'distributed' => $kpiPair($distributed, $distributed),
                'inscriptions' => $z, 'activeUsers' => $z, 'activeUsersCohorte' => $z,
                'transactions' => $z, 'totalSubscriptions' => $z, 'renewalRate' => $z,
                'clientsWithTransactions' => $z, 'inscriptionsCohorte' => $z,
                'conversionRate' => $kpiPair(0, 0),
            ];
        }

        // ── Query 2: Subscription-based KPIs (single query) ──
        // Note: $clientIds already filtered by campaign, no need to re-filter by sub_store
        // (some clients have sub_store=NULL but still belong to the campaign)
        $sub = DB::table('client as c')
            ->leftJoin('client_abonnement as ca', 'c.client_id', '=', 'ca.client_id')
            ->whereIn('c.client_id', $clientIds)
            ->selectRaw("
                COUNT(DISTINCT CASE WHEN ca.client_abonnement_id IS NOT NULL THEN c.client_id END) as inscriptions,
                COUNT(DISTINCT CASE WHEN ca.client_abonnement_expiration > ? THEN c.client_id END) as active_users,
                COUNT(ca.client_abonnement_id) as total_subscriptions,
                COUNT(DISTINCT CASE WHEN ca.client_abonnement_expiration > ?
                    AND ca.client_abonnement_creation BETWEEN ? AND ? THEN c.client_id END) as active_users_cohorte,
                COUNT(DISTINCT CASE WHEN ca.client_abonnement_expiration > ?
                    AND ca.client_abonnement_creation BETWEEN ? AND ? THEN c.client_id END) as active_users_cohorte_comp,
                COUNT(DISTINCT CASE WHEN c.created_at BETWEEN ? AND ? THEN c.client_id END) as inscriptions_cohorte,
                COUNT(DISTINCT CASE WHEN c.created_at BETWEEN ? AND ? THEN c.client_id END) as inscriptions_cohorte_comp,
                COUNT(DISTINCT CASE WHEN ca.client_abonnement_creation BETWEEN ? AND ? THEN c.client_id END) as cards_activated,
                COUNT(DISTINCT CASE WHEN ca.client_abonnement_creation BETWEEN ? AND ? THEN c.client_id END) as cards_activated_comp
            ", [$now, $now, $sd, $ed, $now, $csd, $ced, $sd, $ed, $csd, $ced, $sd, $ed, $csd, $ced])
            ->first();

        // ── Query 3: Transaction-based KPIs (single query) ──
        $tx = DB::table('history as h')
            ->join('client as c', 'h.client_id', '=', 'c.client_id')
            ->whereIn('c.client_id', $clientIds)
            ->selectRaw("
                COUNT(h.history_id) as total_transactions,
                COUNT(DISTINCT c.client_id) as clients_with_transactions,
                COUNT(DISTINCT CASE WHEN h.time BETWEEN ? AND ? THEN c.client_id END) as clients_with_tx_cohorte
            ", [$csd, $ced])
            ->first();

        $inscriptions = (int) $sub->inscriptions;
        $cwt = (int) $tx->clients_with_transactions;
        $conversionRate = $distributed > 0 ? round(($inscriptions / $distributed) * 100, 1) : 0;

        return [
            'distributed'           => $kpiPair($distributed, $distributed),
            'inscriptions'          => $kpiPair($inscriptions, $inscriptions),
            'activeUsers'           => $kpiPair((int) $sub->active_users, $cwt),
            'activeUsersCohorte'    => $kpiPair((int) $sub->active_users_cohorte, (int) $tx->clients_with_tx_cohorte),
            'transactions'          => $kpiPair((int) $tx->total_transactions, (int) $tx->total_transactions),
            'totalSubscriptions'    => $kpiPair(count($clientIds), count($clientIds)),
            'renewalRate'           => $kpiPair((int) $sub->cards_activated, (int) $sub->cards_activated_comp),
            'clientsWithTransactions' => $kpiPair($cwt, $cwt),
            'inscriptionsCohorte'   => $kpiPair((int) $sub->inscriptions_cohorte, (int) $sub->inscriptions_cohorte_comp),
            'conversionRate'        => $kpiPair($conversionRate, $conversionRate),
        ];
    }

    // =========================================================================
    // COMPUTATION — Top Sub-Stores
    // =========================================================================

    private function computeTopSubStores(string $ss, string $sd, string $ed): array
    {
        $pluxeeRanking = $this->shouldUsePluxeeKpiBatch($ss);

        if ($pluxeeRanking && $this->currentCampaign) {
            return $this->computeCampaignRanking($ss);
        }

        if ($pluxeeRanking && $this->isPluxeeAllCampaigns) {
            return $this->computeAllCampaignsRanking($ss);
        }

        $isPluxee = $this->isPluxeeCampaign($ss);

        $query = DB::table('stores')
            ->leftJoin('client', 'client.sub_store', '=', 'stores.store_id')
            ->leftJoin('client_abonnement', 'client_abonnement.client_id', '=', 'client.client_id')
            ->leftJoin('history', 'history.client_abonnement_id', '=', 'client_abonnement.client_abonnement_id');

        if (!$isPluxee) {
            $query->leftJoin('carte_recharge_client', 'carte_recharge_client.client_id', '=', 'client.client_id');
        }

        $query->select(
            'stores.store_id', 'stores.store_name', 'stores.store_type', 'stores.store_manager_name',
            $isPluxee
                ? DB::raw('COUNT(DISTINCT client.client_id) as customers')
                : DB::raw('COUNT(DISTINCT CASE WHEN carte_recharge_client.client_id IS NOT NULL THEN client.client_id END) as customers'),
            DB::raw('COUNT(DISTINCT history.history_id) as transactions')
        )
        ->groupBy('stores.store_id', 'stores.store_name', 'stores.store_type', 'stores.store_manager_name')
        ->orderByDesc('customers')
        ->limit(15);

        $this->applySubStoreFilter($query);
        if ($ss !== 'ALL') {
            $query->where('stores.store_name', 'LIKE', '%' . $ss . '%');
        }

        $results = $query->get();
        return $results->map(function ($item, $index) {
            return [
                'rank' => $index + 1,
                'name' => $item->store_name,
                'type' => $item->store_type ?? 'partnership',
                'customers' => (int) $item->customers,
                'transactions' => (int) $item->transactions,
                'manager' => $item->store_manager_name ?? 'N/A',
            ];
        })->toArray();
    }

    /**
     * Campaign-level ranking: show the campaign's stats within the sub-store.
     */
    private function computeCampaignRanking(string $ss): array
    {
        $campaign = $this->currentCampaign;
        $clientIds = $this->getCampaignClientIds();

        // Get campaign distributed cards
        $dq = DB::table('carte_recharge')
            ->join('stores', function ($j) { $j->whereRaw("FIND_IN_SET(stores.store_id, carte_recharge.stores)"); });
        if ($ss !== 'ALL' && $ss !== '') {
            $dq->where('stores.store_name', 'LIKE', '%' . $ss . '%');
        }
        $distributed = (int) $dq->where('carte_recharge.campain_name', $campaign)
            ->sum('carte_recharge.card_generated_number');

        // Get activated clients count (from pre-resolved IDs)
        $activatedClients = count($clientIds);

        // Get transactions from activated clients
        $transactions = 0;
        if ($activatedClients > 0) {
            $transactions = (int) DB::table('history')
                ->join('client_abonnement', 'history.client_abonnement_id', '=', 'client_abonnement.client_abonnement_id')
                ->whereIn('client_abonnement.client_id', $clientIds)
                ->count();
        }

        return [
            [
                'rank' => 1,
                'name' => $campaign,
                'type' => 'campagne',
                'customers' => $distributed,
                'transactions' => $activatedClients,
                'manager' => $ss,
            ]
        ];
    }

    /**
     * All-campaigns ranking: show each campaign's stats within the Pluxee sub-store.
     */
    private function computeAllCampaignsRanking(string $ss): array
    {
        // Get campaigns for this sub-store (filtered by allowed if restricted)
        $q = DB::table('carte_recharge')
            ->join('stores', function ($j) { $j->whereRaw("FIND_IN_SET(stores.store_id, carte_recharge.stores)"); });
        if ($ss !== 'ALL' && $ss !== '') {
            $q->where('stores.store_name', 'LIKE', '%' . $ss . '%');
        }
        
        if (!empty($this->allowedCampaigns)) {
            $q->whereIn('carte_recharge.campain_name', $this->allowedCampaigns);
        }
        
        $campaigns = $q->select('carte_recharge.campain_name', DB::raw('SUM(carte_recharge.card_generated_number) as distributed'))
            ->groupBy('carte_recharge.campain_name')
            ->orderByDesc('distributed')
            ->get();

        $results = [];
        $rank = 1;
        foreach ($campaigns as $c) {
            // Clients distincts : lot carte_recharge + liaisons carte_recharge_client (réutilisation)
            $activatedClients = count($this->distinctClientIdsForCampaignName((string) $c->campain_name));

            $results[] = [
                'rank' => $rank++,
                'name' => $c->campain_name,
                'type' => 'campagne',
                'customers' => (int) $c->distributed,
                'transactions' => $activatedClients,
                'manager' => $ss,
            ];
        }

        return $results;
    }


    // =========================================================================
    // PRIVATE KPI METHODS — Standard (carte_recharge_client based)
    // =========================================================================

    private function getDistributedCards(string $ss): int
    {
        try {
            if ($this->shouldUsePluxeeKpiBatch($ss)) return $this->getPluxeeDistributed($ss);
            $cacheKey = "distributed_cards_{$ss}";
            return (int) Cache::remember($cacheKey, 300, function () use ($ss) {
                $q = DB::table('carte_recharge')
                    ->join('stores', function ($join) { $join->whereRaw("FIND_IN_SET(stores.store_id, carte_recharge.stores)"); });
                $this->applySubStoreFilter($q);
                if ($ss !== 'ALL') $q->where('stores.store_name', 'LIKE', "%$ss%");
                return $q->sum('carte_recharge.card_generated_number');
            });
        } catch (\Exception $e) { Log::warning('getDistributedCards: '.$e->getMessage()); return 0; }
    }

    private function getInscriptionsWithCards(string $ss): int
    {
        try {
            if ($this->shouldUsePluxeeKpiBatch($ss)) return $this->getPluxeeInscriptions($ss);
            $cacheKey = "inscriptions_cards_{$ss}";
            return (int) Cache::remember($cacheKey, 600, function () use ($ss) {
                $q = DB::table('carte_recharge_client')
                    ->join('client', 'carte_recharge_client.client_id', '=', 'client.client_id')
                    ->join('stores', 'client.sub_store', '=', 'stores.store_id');
                $this->applySubStoreFilter($q);
                if ($ss !== 'ALL') $q->where('stores.store_name', 'LIKE', "%$ss%");
                return $q->distinct()->count('client.client_id');
            });
        } catch (\Exception $e) { Log::warning('getInscriptionsWithCards: '.$e->getMessage()); return 0; }
    }

    private function getActiveUsersWithCards(string $ss): int
    {
        try {
            if ($this->shouldUsePluxeeKpiBatch($ss)) return $this->getPluxeeActiveUsers($ss);
            $cacheKey = "active_users_cards_{$ss}";
            return (int) Cache::remember($cacheKey, 600, function () use ($ss) {
                $q = DB::table('carte_recharge_client')
                    ->join('client', 'carte_recharge_client.client_id', '=', 'client.client_id')
                    ->join('stores', 'client.sub_store', '=', 'stores.store_id')
                    ->join('client_abonnement', 'client.client_id', '=', 'client_abonnement.client_id')
                    ->where('client_abonnement.client_abonnement_expiration', '>', Carbon::now());
                $this->applySubStoreFilter($q);
                if ($ss !== 'ALL') $q->where('stores.store_name', 'LIKE', "%$ss%");
                return $q->distinct()->count('client.client_id');
            });
        } catch (\Exception $e) { Log::warning('getActiveUsersWithCards: '.$e->getMessage()); return 0; }
    }

    private function getActiveUsersWithCardsCohorte(string $ss, string $sd, string $ed): int
    {
        try {
            if ($this->shouldUsePluxeeKpiBatch($ss)) return $this->getPluxeeActiveUsersCohorte($ss, $sd, $ed);
            $q = DB::table('carte_recharge_client')
                ->join('client', 'carte_recharge_client.client_id', '=', 'client.client_id')
                ->join('stores', 'client.sub_store', '=', 'stores.store_id')
                ->join('client_abonnement', 'client.client_id', '=', 'client_abonnement.client_id')
                ->where('client_abonnement.client_abonnement_expiration', '>', Carbon::now())
                ->whereBetween('client_abonnement.client_abonnement_creation', [Carbon::parse($sd)->startOfDay(), Carbon::parse($ed)->endOfDay()]);
            $this->applySubStoreFilter($q);
            if ($ss !== 'ALL') $q->where('stores.store_name', 'LIKE', "%$ss%");
            return (int) $q->distinct()->count('client.client_id');
        } catch (\Exception $e) { return 0; }
    }

    private function getTransactionsWithCards(string $ss): int
    {
        try {
            if ($this->shouldUsePluxeeKpiBatch($ss)) return $this->getPluxeeTransactions($ss);
            $cacheKey = "transactions_cards_{$ss}";
            return (int) Cache::remember($cacheKey, 600, function () use ($ss) {
                $q = DB::table('history')
                    ->join('client_abonnement', 'history.client_abonnement_id', '=', 'client_abonnement.client_abonnement_id')
                    ->join('client', 'client_abonnement.client_id', '=', 'client.client_id')
                    ->join('stores', 'client.sub_store', '=', 'stores.store_id')
                    ->whereExists(function ($sub) { $sub->select(DB::raw(1))->from('carte_recharge_client')->whereColumn('carte_recharge_client.client_id', 'client.client_id'); });
                $this->applySubStoreFilter($q);
                if ($ss !== 'ALL') $q->where('stores.store_name', 'LIKE', "%$ss%");
                return $q->count('history.history_id');
            });
        } catch (\Exception $e) { Log::warning('getTransactionsWithCards: '.$e->getMessage()); return 0; }
    }

    private function getTransactionsWithCardsCohorte(string $ss, string $sd, string $ed): int
    {
        try {
            if ($this->shouldUsePluxeeKpiBatch($ss)) return $this->getPluxeeTransactionsCohorte($ss, $sd, $ed);
            $q = DB::table('history')
                ->join('client', 'history.client_id', '=', 'client.client_id')
                ->join('stores', 'client.sub_store', '=', 'stores.store_id')
                ->whereBetween('history.time', [Carbon::parse($sd)->startOfDay(), Carbon::parse($ed)->endOfDay()]);
            $this->applySubStoreFilter($q);
            if ($ss !== 'ALL') $q->where('stores.store_name', 'LIKE', "%$ss%");
            return (int) $q->count('history.history_id');
        } catch (\Exception $e) { return 0; }
    }

    private function getInscriptionsWithCardsCohorte(string $ss, string $sd, string $ed): int
    {
        try {
            if ($this->shouldUsePluxeeKpiBatch($ss)) return $this->getPluxeeInscriptionsCohorte($ss, $sd, $ed);
            $q = DB::table('carte_recharge_client')
                ->join('client', 'carte_recharge_client.client_id', '=', 'client.client_id')
                ->join('stores', 'client.sub_store', '=', 'stores.store_id')
                ->whereBetween('client.created_at', [Carbon::parse($sd)->startOfDay(), Carbon::parse($ed)->endOfDay()]);
            $this->applySubStoreFilter($q);
            if ($ss !== 'ALL') $q->where('stores.store_name', 'LIKE', "%$ss%");
            return (int) $q->distinct()->count('client.client_id');
        } catch (\Exception $e) { return 0; }
    }

    private function getTotalSubscriptions(string $ss): int
    {
        try {
            if ($this->shouldUsePluxeeKpiBatch($ss)) return $this->getPluxeeTotalSubscriptions($ss);
            $q = DB::table('carte_recharge_client')
                ->join('client', 'carte_recharge_client.client_id', '=', 'client.client_id')
                ->join('stores', 'client.sub_store', '=', 'stores.store_id');
            $this->applySubStoreFilter($q);
            if ($ss !== 'ALL') $q->where('stores.store_name', 'LIKE', "%$ss%");
            return (int) $q->count();
        } catch (\Exception $e) { return 0; }
    }

    private function getCardsActivated(string $ss, string $sd, string $ed): int
    {
        try {
            if ($this->shouldUsePluxeeKpiBatch($ss)) return $this->getPluxeeCardsActivated($ss, $sd, $ed);
            $q = DB::table('carte_recharge_client')
                ->join('client', 'carte_recharge_client.client_id', '=', 'client.client_id')
                ->join('stores', 'client.sub_store', '=', 'stores.store_id')
                ->join('client_abonnement', 'client.client_id', '=', 'client_abonnement.client_id')
                ->whereBetween('client_abonnement.client_abonnement_creation', [Carbon::parse($sd)->startOfDay(), Carbon::parse($ed)->endOfDay()]);
            $this->applySubStoreFilter($q);
            if ($ss !== 'ALL') $q->where('stores.store_name', 'LIKE', "%$ss%");
            return (int) $q->count();
        } catch (\Exception $e) { return 0; }
    }

    private function getUsersWithCardsCount(string $ss): int
    {
        try {
            if ($this->shouldUsePluxeeKpiBatch($ss)) return $this->getPluxeeUsersWithCardsCount($ss);
            $q = DB::table('carte_recharge_client')
                ->join('client', 'carte_recharge_client.client_id', '=', 'client.client_id')
                ->join('stores', 'client.sub_store', '=', 'stores.store_id')
                ->join('history', 'client.client_id', '=', 'history.client_id');
            $this->applySubStoreFilter($q);
            if ($ss !== 'ALL') $q->where('stores.store_name', 'LIKE', "%$ss%");
            return (int) $q->distinct()->count('client.client_id');
        } catch (\Exception $e) { return 0; }
    }

    private function getUsersWithCardsCohorteCount(string $ss, $sd, $ed): int
    {
        try {
            if ($this->shouldUsePluxeeKpiBatch($ss)) return $this->getPluxeeUsersWithCardsCohorteCount($ss, $sd, $ed);
            $q = DB::table('carte_recharge_client')
                ->join('client', 'carte_recharge_client.client_id', '=', 'client.client_id')
                ->join('stores', 'client.sub_store', '=', 'stores.store_id')
                ->join('history', 'client.client_id', '=', 'history.client_id')
                ->whereBetween('history.time', [$sd, $ed]);
            $this->applySubStoreFilter($q);
            if ($ss !== 'ALL') $q->where('stores.store_name', 'LIKE', "%$ss%");
            return (int) $q->distinct()->count('client.client_id');
        } catch (\Exception $e) { return 0; }
    }

    // =========================================================================
    // PLUXEE KPI METHODS — Without carte_recharge_client
    // =========================================================================

    /**
     * Filter Pluxee clients using PRE-RESOLVED client IDs.
     * Works for both specific campaign AND "all campaigns" mode.
     */
    private function applyPluxeeCampaignFilter($query, string $clientAlias = 'client')
    {
        if ($this->currentCampaign || $this->isPluxeeAllCampaigns) {
            $clientIds = $this->getCampaignClientIds();
            if (!empty($clientIds)) {
                $query->whereIn("$clientAlias.client_id", $clientIds);
            } else {
                $query->whereRaw('1 = 0');
            }
        }
        return $query;
    }

    private function getPluxeeDistributed(string $ss): int
    {
        $q = DB::table('carte_recharge')
            ->join('stores', function ($j) { $j->whereRaw("FIND_IN_SET(stores.store_id, carte_recharge.stores)"); });
        if ($ss !== 'ALL' && $ss !== '') {
            $q->where('stores.store_name', 'LIKE', '%' . $ss . '%');
        }

        if ($this->currentCampaign) {
            $q->where('carte_recharge.campain_name', $this->currentCampaign);
        } elseif (!empty($this->allowedCampaigns)) {
            // Admin with restrictions: only their campaigns
            $q->whereIn('carte_recharge.campain_name', $this->allowedCampaigns);
        }
        // Both cases (specific campaign or all): SUM the distributed cards
        return (int) $q->sum('carte_recharge.card_generated_number');
    }

    private function getPluxeeInscriptions(string $ss): int
    {
        $q = DB::table('client')->join('stores', 'client.sub_store', '=', 'stores.store_id')
            ->join('client_abonnement', 'client.client_id', '=', 'client_abonnement.client_id');
        if ($ss !== 'ALL' && $ss !== '') {
            $q->where('stores.store_name', 'LIKE', '%' . $ss . '%');
        }
        $this->applyPluxeeCampaignFilter($q);
        return (int) $q->distinct('client.client_id')->count('client.client_id');
    }

    private function getPluxeeActiveUsers(string $ss): int
    {
        $q = DB::table('client')->join('stores', 'client.sub_store', '=', 'stores.store_id')
            ->join('client_abonnement', 'client.client_id', '=', 'client_abonnement.client_id')
            ->where('client_abonnement.client_abonnement_expiration', '>', Carbon::now());
        if ($ss !== 'ALL' && $ss !== '') {
            $q->where('stores.store_name', 'LIKE', '%' . $ss . '%');
        }
        $this->applyPluxeeCampaignFilter($q);
        return (int) $q->distinct('client.client_id')->count('client.client_id');
    }

    private function getPluxeeActiveUsersCohorte(string $ss, string $sd, string $ed): int
    {
        $q = DB::table('client')->join('stores', 'client.sub_store', '=', 'stores.store_id')
            ->join('client_abonnement', 'client.client_id', '=', 'client_abonnement.client_id')
            ->where('client_abonnement.client_abonnement_expiration', '>', Carbon::now())
            ->whereBetween('client_abonnement.client_abonnement_creation', [Carbon::parse($sd)->startOfDay(), Carbon::parse($ed)->endOfDay()]);
        if ($ss !== 'ALL' && $ss !== '') {
            $q->where('stores.store_name', 'LIKE', '%' . $ss . '%');
        }
        $this->applyPluxeeCampaignFilter($q);
        return (int) $q->distinct('client.client_id')->count('client.client_id');
    }

    private function getPluxeeTransactions(string $ss): int
    {
        $q = DB::table('history')->join('client', 'history.client_id', '=', 'client.client_id')
            ->join('stores', 'client.sub_store', '=', 'stores.store_id');
        if ($ss !== 'ALL' && $ss !== '') {
            $q->where('stores.store_name', 'LIKE', '%' . $ss . '%');
        }
        $this->applyPluxeeCampaignFilter($q);
        return (int) $q->count('history.history_id');
    }

    private function getPluxeeTransactionsCohorte(string $ss, string $sd, string $ed): int
    {
        $q = DB::table('history')->join('client', 'history.client_id', '=', 'client.client_id')
            ->join('stores', 'client.sub_store', '=', 'stores.store_id')
            ->whereBetween('history.time', [Carbon::parse($sd)->startOfDay(), Carbon::parse($ed)->endOfDay()]);
        if ($ss !== 'ALL' && $ss !== '') {
            $q->where('stores.store_name', 'LIKE', '%' . $ss . '%');
        }
        $this->applyPluxeeCampaignFilter($q);
        return (int) $q->count('history.history_id');
    }

    private function getPluxeeInscriptionsCohorte(string $ss, string $sd, string $ed): int
    {
        $q = DB::table('client')->join('stores', 'client.sub_store', '=', 'stores.store_id')
            ->whereBetween('client.created_at', [Carbon::parse($sd)->startOfDay(), Carbon::parse($ed)->endOfDay()]);
        if ($ss !== 'ALL' && $ss !== '') {
            $q->where('stores.store_name', 'LIKE', '%' . $ss . '%');
        }
        $this->applyPluxeeCampaignFilter($q);
        return (int) $q->count('client.client_id');
    }

    private function getPluxeeCardsActivated(string $ss, string $sd, string $ed): int
    {
        $q = DB::table('client_abonnement')->join('client', 'client_abonnement.client_id', '=', 'client.client_id')
            ->join('stores', 'client.sub_store', '=', 'stores.store_id')
            ->whereBetween('client_abonnement.client_abonnement_creation', [Carbon::parse($sd)->startOfDay(), Carbon::parse($ed)->endOfDay()]);
        if ($ss !== 'ALL' && $ss !== '') {
            $q->where('stores.store_name', 'LIKE', '%' . $ss . '%');
        }
        $this->applyPluxeeCampaignFilter($q);
        return (int) $q->count();
    }

    private function getPluxeeTotalSubscriptions(string $ss): int
    {
        $q = DB::table('client_abonnement')->join('client', 'client_abonnement.client_id', '=', 'client.client_id')
            ->join('stores', 'client.sub_store', '=', 'stores.store_id');
        if ($ss !== 'ALL' && $ss !== '') {
            $q->where('stores.store_name', 'LIKE', '%' . $ss . '%');
        }
        $this->applyPluxeeCampaignFilter($q);
        return (int) $q->count();
    }

    private function getPluxeeUsersWithCardsCount(string $ss): int
    {
        $q = DB::table('client')->join('stores', 'client.sub_store', '=', 'stores.store_id')
            ->join('history', 'client.client_id', '=', 'history.client_id');
        if ($ss !== 'ALL' && $ss !== '') {
            $q->where('stores.store_name', 'LIKE', '%' . $ss . '%');
        }
        $this->applyPluxeeCampaignFilter($q);
        return (int) $q->distinct('client.client_id')->count('client.client_id');
    }

    private function getPluxeeUsersWithCardsCohorteCount(string $ss, $sd, $ed): int
    {
        $q = DB::table('client')->join('stores', 'client.sub_store', '=', 'stores.store_id')
            ->join('history', 'client.client_id', '=', 'history.client_id')
            ->whereBetween('history.time', [$sd, $ed]);
        if ($ss !== 'ALL' && $ss !== '') {
            $q->where('stores.store_name', 'LIKE', '%' . $ss . '%');
        }
        $this->applyPluxeeCampaignFilter($q);
        return (int) $q->distinct('client.client_id')->count('client.client_id');
    }

    // =========================================================================
    // DATA METHODS — Charts, Merchants, Users, Expirations
    // =========================================================================

    private function getCategoryDistribution(string $sd, string $ed, string $ss): array
    {
        try {
            $isPluxee = $this->isPluxeeCampaign($ss) || $this->shouldUsePluxeeKpiBatch($ss);
            $q = DB::table('history')
                ->join('client', 'history.client_id', '=', 'client.client_id')
                ->join('stores', 'client.sub_store', '=', 'stores.store_id')
                ->join('promotion', 'history.promotion_id', '=', 'promotion.promotion_id')
                ->join('partner', 'promotion.partner_id', '=', 'partner.partner_id')
                ->join('partner_category', 'partner.partner_category_id', '=', 'partner_category.partner_category_id')
                ->select('partner_category.partner_category_name', DB::raw('COUNT(DISTINCT history.history_id) as utilizations'));
            if (!$isPluxee) {
                $q->join('client_abonnement', 'history.client_abonnement_id', '=', 'client_abonnement.client_abonnement_id');
                $this->applySubStoreFilter($q);
            } else {
                $this->applyPluxeeCampaignFilter($q);
            }
            $q->where('stores.store_active', 1)
              ->whereBetween('history.time', [$sd, Carbon::parse($ed)->endOfDay()])
              ->when($ss !== 'ALL', fn($q2) => $q2->where('stores.store_name', 'LIKE', "%$ss%"))
              ->groupBy('partner_category.partner_category_name')
              ->orderBy('utilizations', 'desc');

            $categories = $q->get();
            $total = $categories->sum('utilizations');
            return $categories->map(fn($c) => [
                'category' => $c->partner_category_name,
                'transactions' => (int) $c->utilizations,
                'percentage' => $total > 0 ? round(($c->utilizations / $total) * 100, 1) : 0
            ])->toArray();
        } catch (\Exception $e) { return []; }
    }

    private function getInscriptionsTrend(string $sd, string $ed, string $ss): array
    {
        try {
            $extStart = Carbon::parse($sd)->subMonths(11)->startOfMonth()->format('Y-m-d');
            $extEnd = Carbon::parse($ed)->endOfMonth()->format('Y-m-d');
            $isPluxee = $this->isPluxeeCampaign($ss) || $this->shouldUsePluxeeKpiBatch($ss);

            if ($isPluxee) {
                $q = DB::table('client')->join('stores', 'client.sub_store', '=', 'stores.store_id')
                    ->select(DB::raw("DATE_FORMAT(client.created_at, '%Y-%m') as month"), DB::raw('COUNT(DISTINCT client.client_id) as value'))
                    ->whereBetween('client.created_at', [$extStart, Carbon::parse($extEnd)->endOfDay()]);
                if ($ss !== 'ALL' && $ss !== '') {
                    $q->where('stores.store_name', 'LIKE', '%' . $ss . '%');
                }
                $this->applyPluxeeCampaignFilter($q);
                $trend = $q->groupBy(DB::raw("DATE_FORMAT(client.created_at, '%Y-%m')"))
                    ->orderBy('month')->get();
            } else {
                $q = DB::table('carte_recharge_client')
                    ->join('client', 'carte_recharge_client.client_id', '=', 'client.client_id')
                    ->join('stores', 'client.sub_store', '=', 'stores.store_id')
                    ->select(DB::raw("DATE_FORMAT(client.created_at, '%Y-%m') as month"), DB::raw('COUNT(DISTINCT client.client_id) as value'));
                $this->applySubStoreFilter($q)
                    ->whereBetween('client.created_at', [$extStart, Carbon::parse($extEnd)->endOfDay()])
                    ->when($ss !== 'ALL', fn($q2) => $q2->where('stores.store_name', 'LIKE', "%$ss%"))
                    ->groupBy(DB::raw("DATE_FORMAT(client.created_at, '%Y-%m')"))
                    ->orderBy('month');
                $trend = $q->get();
            }
            return $trend->map(fn($t) => [
                'date' => Carbon::createFromFormat('Y-m', $t->month)->format('M Y'),
                'value' => (int) $t->value
            ])->toArray();
        } catch (\Exception $e) { return []; }
    }

    private function getMerchantData(string $ss, string $sd, string $ed, string $csd, string $ced): array
    {
        try {
            set_time_limit(300);
            $isPluxee = $this->isPluxeeCampaign($ss) || $this->shouldUsePluxeeKpiBatch($ss);

            $totalPartners = DB::table('partner')->where('partener_active', 1)->count();

            // Active merchants - current
            $amq = DB::table('history')
                ->join('client', 'history.client_id', '=', 'client.client_id')
                ->join('stores', 'client.sub_store', '=', 'stores.store_id')
                ->join('promotion', 'history.promotion_id', '=', 'promotion.promotion_id')
                ->join('partner', 'promotion.partner_id', '=', 'partner.partner_id');
            if (!$isPluxee) {
                $amq->join('client_abonnement', 'history.client_abonnement_id', '=', 'client_abonnement.client_abonnement_id');
                $this->applySubStoreFilter($amq);
            } else {
                $this->applyPluxeeCampaignFilter($amq);
            }
            $amq->when($ss !== 'ALL', fn($q) => $q->where('stores.store_name', 'LIKE', "%$ss%"))
                ->whereBetween('history.time', [$sd, Carbon::parse($ed)->endOfDay()])->distinct();
            $activeMerchants = $amq->count('partner.partner_id');

            // Active merchants - comparison
            $amcq = DB::table('history')
                ->join('client', 'history.client_id', '=', 'client.client_id')
                ->join('stores', 'client.sub_store', '=', 'stores.store_id')
                ->join('promotion', 'history.promotion_id', '=', 'promotion.promotion_id')
                ->join('partner', 'promotion.partner_id', '=', 'partner.partner_id');
            if (!$isPluxee) {
                $amcq->join('client_abonnement', 'history.client_abonnement_id', '=', 'client_abonnement.client_abonnement_id');
                $this->applySubStoreFilter($amcq);
            } else {
                $this->applyPluxeeCampaignFilter($amcq);
            }
            $amcq->when($ss !== 'ALL', fn($q) => $q->where('stores.store_name', 'LIKE', "%$ss%"))
                 ->whereBetween('history.time', [$csd, Carbon::parse($ced)->endOfDay()])->distinct();
            $activeMerchantsComp = $amcq->count('partner.partner_id');

            $diversity = $this->calculateDiversityLevel($activeMerchants);

            // All merchants with transaction counts
            $allMq = DB::table('history')
                ->join('client', 'history.client_id', '=', 'client.client_id')
                ->join('stores', 'client.sub_store', '=', 'stores.store_id')
                ->join('promotion', 'history.promotion_id', '=', 'promotion.promotion_id')
                ->join('partner', 'promotion.partner_id', '=', 'partner.partner_id')
                ->leftJoin('partner_category', 'partner.partner_category_id', '=', 'partner_category.partner_category_id')
                ->select('partner.partner_id', 'partner.partner_name', 'partner_category.partner_category_name', DB::raw('COUNT(history.history_id) as transactions_count'));
            if (!$isPluxee) {
                $allMq->join('client_abonnement', 'history.client_abonnement_id', '=', 'client_abonnement.client_abonnement_id');
                $this->applySubStoreFilter($allMq);
            } else {
                $this->applyPluxeeCampaignFilter($allMq);
            }
            $allMq->when($ss !== 'ALL', fn($q) => $q->where('stores.store_name', 'LIKE', "%$ss%"))
                  ->whereBetween('history.time', [$sd, Carbon::parse($ed)->endOfDay()])
                  ->groupBy('partner.partner_id', 'partner.partner_name', 'partner_category.partner_category_name')
                  ->orderBy('transactions_count', 'desc');
            $allMerchants = $allMq->get();

            // Comparison merchants
            $compMq = DB::table('history')
                ->join('client', 'history.client_id', '=', 'client.client_id')
                ->join('stores', 'client.sub_store', '=', 'stores.store_id')
                ->join('promotion', 'history.promotion_id', '=', 'promotion.promotion_id')
                ->join('partner', 'promotion.partner_id', '=', 'partner.partner_id')
                ->select('partner.partner_id', DB::raw('COUNT(history.history_id) as transactions_count'));
            if (!$isPluxee) {
                $compMq->join('client_abonnement', 'history.client_abonnement_id', '=', 'client_abonnement.client_abonnement_id');
                $this->applySubStoreFilter($compMq);
            } else {
                $this->applyPluxeeCampaignFilter($compMq);
            }
            $compMq->when($ss !== 'ALL', fn($q) => $q->where('stores.store_name', 'LIKE', "%$ss%"))
                   ->whereBetween('history.time', [$csd, Carbon::parse($ced)->endOfDay()])
                   ->groupBy('partner.partner_id');
            $compMap = $compMq->get()->keyBy('partner_id');

            // ===== COMPUTE ENRICHED KPIs =====
            $totalTransactions = $allMerchants->sum('transactions_count');
            $totalTransactionsComp = $compMap->sum('transactions_count');
            $transactionsPerMerchant = $activeMerchants > 0 ? round($totalTransactions / $activeMerchants, 1) : 0;
            $transactionsPerMerchantComp = $activeMerchantsComp > 0 ? round($totalTransactionsComp / $activeMerchantsComp, 1) : 0;
            $activeMerchantRatio = $totalPartners > 0 ? round(($activeMerchants / $totalPartners) * 100, 1) : 0;
            $activeMerchantRatioComp = $totalPartners > 0 ? round(($activeMerchantsComp / $totalPartners) * 100, 1) : 0;

            // Top merchant share
            $topMerchant = $allMerchants->first();
            $topMerchantShare = ($topMerchant && $totalTransactions > 0) ? round(($topMerchant->transactions_count / $totalTransactions) * 100, 1) : 0;
            $topMerchantName = $topMerchant ? $topMerchant->partner_name : 'N/A';

            // Active locations count
            $activePartnerIds = $allMerchants->pluck('partner_id')->toArray();
            $totalLocationsActive = 0;
            if (!empty($activePartnerIds)) {
                $totalLocationsActive = DB::table('partner_location')
                    ->whereIn('partner_id', $activePartnerIds)
                    ->count();
            }

            $merchants = $allMerchants->map(function ($m, $idx) use ($compMap, $totalTransactions) {
                $prev = $compMap->get($m->partner_id);
                $prevTx = $prev ? $prev->transactions_count : 0;
                $change = $prevTx > 0 ? round((($m->transactions_count - $prevTx) / $prevTx) * 100, 1) : ($m->transactions_count > 0 ? 100 : 0);
                $share = $totalTransactions > 0 ? round(($m->transactions_count / $totalTransactions) * 100, 1) : 0;
                return [
                    'rank' => $idx + 1,
                    'name' => $m->partner_name ?? 'N/A',
                    'category' => $m->partner_category_name ?? 'Autres',
                    'current' => (int) $m->transactions_count,
                    'transactions' => (int) $m->transactions_count,
                    'share' => $share,
                    'delta' => $change,
                    'change' => $change,
                ];
            })->toArray();

            return [
                'kpis' => [
                    'totalPartners' => ['current' => $totalPartners, 'previous' => $totalPartners, 'change' => 0],
                    'activeMerchants' => ['current' => $activeMerchants, 'previous' => $activeMerchantsComp, 'change' => $this->calculatePercentageChange($activeMerchants, $activeMerchantsComp)],
                    'totalLocationsActive' => ['current' => $totalLocationsActive, 'previous' => $totalLocationsActive, 'change' => 0],
                    'activeMerchantRatio' => ['current' => $activeMerchantRatio, 'previous' => $activeMerchantRatioComp, 'change' => $this->calculatePercentageChange($activeMerchantRatio, $activeMerchantRatioComp)],
                    'totalTransactions' => ['current' => $totalTransactions, 'previous' => $totalTransactionsComp, 'change' => $this->calculatePercentageChange($totalTransactions, $totalTransactionsComp)],
                    'transactionsPerMerchant' => ['current' => $transactionsPerMerchant, 'previous' => $transactionsPerMerchantComp, 'change' => $this->calculatePercentageChange($transactionsPerMerchant, $transactionsPerMerchantComp)],
                    'topMerchantShare' => ['current' => $topMerchantShare, 'previous' => 0, 'change' => 0, 'merchant_name' => $topMerchantName],
                    'diversity' => $diversity,
                ],
                'merchants' => $merchants,
            ];
        } catch (\Exception $e) {
            Log::error("getMerchantData error: " . $e->getMessage());
            return ['kpis' => [
                'totalPartners' => ['current' => 0, 'previous' => 0, 'change' => 0],
                'activeMerchants' => ['current' => 0, 'previous' => 0, 'change' => 0],
                'totalLocationsActive' => ['current' => 0, 'previous' => 0, 'change' => 0],
                'activeMerchantRatio' => ['current' => 0, 'previous' => 0, 'change' => 0],
                'totalTransactions' => ['current' => 0, 'previous' => 0, 'change' => 0],
                'transactionsPerMerchant' => ['current' => 0, 'previous' => 0, 'change' => 0],
                'topMerchantShare' => ['current' => 0, 'previous' => 0, 'change' => 0, 'merchant_name' => 'N/A'],
                'diversity' => ['level' => 'N/A', 'score' => 0],
            ], 'merchants' => []];
        }
    }

    private function calculateDiversityLevel(int $active): array
    {
        if ($active >= 50) return ['level' => 'Excellent', 'score' => min(100, $active)];
        if ($active >= 25) return ['level' => 'Bon', 'score' => $active * 2];
        if ($active >= 10) return ['level' => 'Moyen', 'score' => $active * 3];
        return ['level' => 'Faible', 'score' => max(5, $active * 5)];
    }

    private function getExpirationsByMonth(string $ss, int $months, ?string $campaign = null): array
    {
        try {
            $start = Carbon::now()->subMonths($months)->startOfMonth();
            $end = Carbon::now()->addMonths(12)->endOfMonth();
            $q = DB::table('client_abonnement')
                ->join('client', 'client_abonnement.client_id', '=', 'client.client_id')
                ->join('stores', 'client.sub_store', '=', 'stores.store_id')
                ->select(DB::raw("DATE_FORMAT(client_abonnement.client_abonnement_expiration, '%Y-%m') as ym"), DB::raw('COUNT(*) as total'))
                ->where('client_abonnement.status', '!=', 'removed');

            // Apply campaign filter: titulaire carte_recharge OU client lié via carte_recharge_client
            if ($campaign) {
                $q->where(function ($w) use ($campaign) {
                    $w->whereExists(function ($sub) use ($campaign) {
                        $sub->select(DB::raw(1))
                            ->from('carte_recharge')
                            ->whereColumn('carte_recharge.client_id', 'client.client_id')
                            ->where('carte_recharge.campain_name', $campaign)
                            ->whereNotNull('carte_recharge.client_id')
                            ->where('carte_recharge.client_id', '!=', '');
                    })->orWhereExists(function ($sub) use ($campaign) {
                        $sub->select(DB::raw(1))
                            ->from('carte_recharge_client as crc')
                            ->join('carte_recharge as cr', 'crc.carte_recharge_id', '=', 'cr.carte_recharge_id')
                            ->whereColumn('crc.client_id', 'client.client_id')
                            ->where('cr.campain_name', $campaign);
                    });
                });
            } elseif ($this->isPluxeeAllCampaigns && !empty($this->allowedCampaigns)) {
                // "All campaigns" mode: use pre-resolved client IDs
                $clientIds = $this->getCampaignClientIds();
                if (!empty($clientIds)) {
                    $q->whereIn('client.client_id', $clientIds);
                } else {
                    return [];
                }
            }

            $this->applySubStoreFilter($q)
                ->when($ss !== 'ALL', fn($q2) => $q2->where('stores.store_name', 'LIKE', "%$ss%"))
                ->whereBetween('client_abonnement.client_abonnement_expiration', [$start, $end])
                ->groupBy(DB::raw("DATE_FORMAT(client_abonnement.client_abonnement_expiration, '%Y-%m')"))
                ->orderBy('ym');
            return $q->get()->map(fn($r) => ['date' => Carbon::createFromFormat('Y-m', $r->ym)->format('M Y'), 'value' => (int) $r->total])->toArray();
        } catch (\Exception $e) { return []; }
    }

    private function getUsersKPIs($sd, $ed, $csd, $ced, $ss)
    {
        // ── PLUXEE BATCH: 2 queries instead of 14 (y compris sub_store = ALL) ──
        if ($this->shouldUsePluxeeKpiBatch($ss)) {
            return $this->getUsersKPIsPluxeeBatch($sd, $ed, $csd, $ced, $ss);
        }

        // ── STANDARD PATH ──
        $totalUsers = $this->getInscriptionsWithCards($ss);
        $activeUsers = $this->getActiveUsersWithCards($ss);
        $activeUsersCohorte = $this->getActiveUsersWithCardsCohorte($ss, $sd, $ed);
        $totalTransactions = $this->getTransactionsWithCards($ss);
        $totalTransactionsCohorte = $this->getTransactionsWithCardsCohorte($ss, $sd, $ed);
        $totalSubscriptions = $this->getTotalSubscriptions($ss);
        $newUsers = $this->getCardsActivated($ss, $sd, $ed);
        $avgTxPerUser = $activeUsers > 0 ? round($totalTransactions / $activeUsers, 2) : 0;
        $retention = $totalUsers > 0 ? round(($activeUsers / $totalUsers) * 100, 1) : 0;

        $comp = [];
        if ($csd && $ced) {
            $compActiveUsers = $this->getActiveUsersWithCards($ss);
            $comp = [
                'totalUsers' => $totalUsers,
                'activeUsers' => $compActiveUsers,
                'activeUsersCohorte' => $this->getActiveUsersWithCardsCohorte($ss, $csd, $ced),
                'totalTransactions' => $this->getTransactionsWithCards($ss),
                'totalTransactionsCohorte' => $this->getTransactionsWithCardsCohorte($ss, $csd, $ced),
                'totalSubscriptions' => $this->getTotalSubscriptions($ss),
                'newUsers' => $this->getCardsActivated($ss, $csd, $ced),
                'retentionRate' => $totalUsers > 0 ? round($compActiveUsers / $totalUsers * 100, 1) : 0,
            ];
        }

        $kp = function ($key, $cur) use ($comp) {
            $prev = $comp[$key] ?? $cur;
            return ['current' => $cur, 'previous' => $prev, 'change' => $this->calculateUserChange($cur, $prev)];
        };

        return [
            'totalUsers' => $kp('totalUsers', $totalUsers),
            'activeUsers' => $kp('activeUsers', $activeUsers),
            'totalTransactions' => $kp('totalTransactions', $totalTransactions),
            'avgTransactionsPerUser' => ['current' => $avgTxPerUser, 'previous' => 0, 'change' => 0],
            'totalSubscriptions' => $kp('totalSubscriptions', $totalSubscriptions),
            'newUsers' => $kp('newUsers', $newUsers),
            'transactionsCohorte' => $kp('totalTransactionsCohorte', $totalTransactionsCohorte),
            'retentionRate' => $kp('retentionRate', $retention),
        ];
    }

    /**
     * OPTIMISED BATCH: Compute ALL Pluxee Users KPIs in 2 SQL queries.
     */
    private function getUsersKPIsPluxeeBatch($sd, $ed, $csd, $ced, $ss)
    {
        $sdStr  = Carbon::parse($sd)->startOfDay()->toDateTimeString();
        $edStr  = Carbon::parse($ed)->endOfDay()->toDateTimeString();
        $csdStr = $csd ? Carbon::parse($csd)->startOfDay()->toDateTimeString() : $sdStr;
        $cedStr = $ced ? Carbon::parse($ced)->endOfDay()->toDateTimeString() : $edStr;
        $now    = Carbon::now()->toDateTimeString();
        $clientIds = $this->getCampaignClientIds();

        $kp = function ($cur, $prev) {
            return ['current' => (int) $cur, 'previous' => (int) $prev, 'change' => $this->calculateUserChange($cur, $prev)];
        };

        if (empty($clientIds)) {
            $z = $kp(0, 0);
            return [
                'totalUsers' => $z,
                'activeUsers' => $z,
                'totalTransactions' => $z,
                'avgTransactionsPerUser' => ['current' => 0, 'previous' => 0, 'change' => 0],
                'totalSubscriptions' => $z,
                'newUsers' => $z,
                'transactionsCohorte' => $z,
                'retentionRate' => $z,
            ];
        }

        // Query 1: subscription-based
        // $clientIds already filtered by campaign - no sub_store filter needed
        $sub = DB::table('client as c')
            ->leftJoin('client_abonnement as ca', 'c.client_id', '=', 'ca.client_id')
            ->whereIn('c.client_id', $clientIds)
            ->selectRaw("
                COUNT(DISTINCT CASE WHEN ca.client_abonnement_id IS NOT NULL THEN c.client_id END) as total_users,
                COUNT(DISTINCT CASE WHEN ca.client_abonnement_expiration > ? THEN c.client_id END) as active_users,
                COUNT(ca.client_abonnement_id) as total_subscriptions,
                COUNT(DISTINCT CASE WHEN ca.client_abonnement_expiration > ?
                    AND ca.client_abonnement_creation BETWEEN ? AND ? THEN c.client_id END) as active_users_cohorte,
                COUNT(DISTINCT CASE WHEN ca.client_abonnement_expiration > ?
                    AND ca.client_abonnement_creation BETWEEN ? AND ? THEN c.client_id END) as active_users_cohorte_comp,
                COUNT(DISTINCT CASE WHEN ca.client_abonnement_creation BETWEEN ? AND ? THEN c.client_id END) as new_users,
                COUNT(DISTINCT CASE WHEN ca.client_abonnement_creation BETWEEN ? AND ? THEN c.client_id END) as new_users_comp
            ", [$now, $now, $sdStr, $edStr, $now, $csdStr, $cedStr, $sdStr, $edStr, $csdStr, $cedStr])
            ->first();

        // Query 2: transaction-based
        $tx = DB::table('history as h')
            ->join('client as c', 'h.client_id', '=', 'c.client_id')
            ->whereIn('c.client_id', $clientIds)
            ->selectRaw("
                COUNT(h.history_id) as total_transactions,
                COUNT(CASE WHEN h.time BETWEEN ? AND ? THEN 1 END) as tx_cohorte,
                COUNT(CASE WHEN h.time BETWEEN ? AND ? THEN 1 END) as tx_cohorte_comp
            ", [$sdStr, $edStr, $csdStr, $cedStr])
            ->first();

        $totalUsers = (int) $sub->total_users;
        $activeUsers = (int) $sub->active_users;
        $totalTx = (int) $tx->total_transactions;
        $avgTxPerUser = $activeUsers > 0 ? round($totalTx / $activeUsers, 2) : 0;
        $retention = $totalUsers > 0 ? round(($activeUsers / $totalUsers) * 100, 1) : 0;
        $retentionComp = $totalUsers > 0 ? round($activeUsers / $totalUsers * 100, 1) : 0;

        return [
            'totalUsers'            => $kp($totalUsers, $totalUsers),
            'activeUsers'           => $kp($activeUsers, $activeUsers),
            'totalTransactions'     => $kp($totalTx, $totalTx),
            'avgTransactionsPerUser' => ['current' => $avgTxPerUser, 'previous' => 0, 'change' => 0],
            'totalSubscriptions'    => $kp(count($clientIds), count($clientIds)),
            'newUsers'              => $kp((int) $sub->new_users, (int) $sub->new_users_comp),
            'transactionsCohorte'   => $kp((int) $tx->tx_cohorte, (int) $tx->tx_cohorte_comp),
            'retentionRate'         => $kp($retention, $retentionComp),
        ];
    }

    private function getUsersList($sd, $ed, $ss, $limit = null)
    {
        if ($this->shouldUsePluxeeKpiBatch($ss)) return $this->getPluxeeUsersList($sd, $ed, $ss, $limit);

        $q = DB::table('carte_recharge_client')
            ->join('client', 'carte_recharge_client.client_id', '=', 'client.client_id')
            ->join('stores', 'client.sub_store', '=', 'stores.store_id')
            ->leftJoin('history', function ($join) use ($sd, $ed) {
                $join->on('client.client_id', '=', 'history.client_id')->whereBetween('history.time', [$sd, $ed]);
            })
            ->leftJoin('client_abonnement', 'client_abonnement.client_id', '=', 'client.client_id')
            ->select(
                'client.client_id as id',
                DB::raw('CONCAT(COALESCE(client.client_prenom,"")," ",COALESCE(client.client_nom,"")) as name'),
                'stores.store_name as sub_store_name',
                'client.created_at as registration_date',
                DB::raw('COUNT(DISTINCT history.history_id) as total_transactions'),
                DB::raw('COUNT(DISTINCT client_abonnement.client_abonnement_id) as total_subscriptions'),
                DB::raw('MAX(history.time) as last_activity'),
                DB::raw('CASE WHEN COUNT(DISTINCT history.history_id) > 0 THEN "active" ELSE "inactive" END as status')
            );
        $this->applySubStoreFilter($q)
            ->when($ss !== 'ALL', fn($q2) => $q2->where('stores.store_name', 'LIKE', "%$ss%"))
            ->groupBy('client.client_id', 'client.client_prenom', 'client.client_nom', 'client.created_at', 'stores.store_name')
            ->orderBy('total_transactions', 'desc');
        if ($limit) $q->limit((int) $limit);

        return $q->get()->map(fn($u) => [
            'id' => $u->id,
            'name' => trim($u->name ?? ''),
            'sub_store_name' => $u->sub_store_name,
            'registration_date' => $u->registration_date ? Carbon::parse($u->registration_date)->format('Y-m-d') : 'N/A',
            'total_transactions' => (int) $u->total_transactions,
            'total_subscriptions' => (int) $u->total_subscriptions,
            'recharge_cards' => [],
            'last_activity' => $u->last_activity ? Carbon::parse($u->last_activity)->format('Y-m-d H:i') : 'N/A',
            'status' => $u->status
        ]);
    }

    private function getPluxeeUsersList($sd, $ed, $ss, $limit = null)
    {
        $q = DB::table('client')
            ->join('stores', 'client.sub_store', '=', 'stores.store_id')
            ->leftJoin('history', function ($join) use ($sd, $ed) {
                $join->on('client.client_id', '=', 'history.client_id')->whereBetween('history.time', [$sd, $ed]);
            })
            ->leftJoin('client_abonnement', 'client.client_id', '=', 'client_abonnement.client_id');
        if ($ss !== 'ALL' && $ss !== '') {
            $q->where('stores.store_name', 'LIKE', '%' . $ss . '%');
        }

        // Apply campaign filter for restricted users
        $this->applyPluxeeCampaignFilter($q);

        $q->select(
                'client.client_id as id',
                DB::raw('CONCAT(COALESCE(client.client_prenom,"")," ",COALESCE(client.client_nom,"")) as name'),
                'stores.store_name as sub_store_name',
                'client.created_at as registration_date',
                DB::raw('COUNT(DISTINCT history.history_id) as total_transactions'),
                DB::raw('COUNT(DISTINCT client_abonnement.client_abonnement_id) as total_subscriptions'),
                DB::raw('MAX(history.time) as last_activity'),
                DB::raw('CASE WHEN COUNT(DISTINCT history.history_id) > 0 THEN "active" ELSE "inactive" END as status')
            )
            ->groupBy('client.client_id', 'client.client_prenom', 'client.client_nom', 'client.created_at', 'stores.store_name')
            ->orderBy('total_transactions', 'desc');
        if ($limit) $q->limit((int) $limit);

        return $q->get()->map(fn($u) => [
            'id' => $u->id, 'name' => trim($u->name ?? ''), 'sub_store_name' => $u->sub_store_name,
            'registration_date' => $u->registration_date ? Carbon::parse($u->registration_date)->format('Y-m-d') : 'N/A',
            'total_transactions' => (int) $u->total_transactions, 'total_subscriptions' => (int) $u->total_subscriptions,
            'recharge_cards' => [], 'last_activity' => $u->last_activity ? Carbon::parse($u->last_activity)->format('Y-m-d H:i') : 'N/A',
            'status' => $u->status
        ]);
    }
}
