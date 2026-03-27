<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class MLMultiOperatorFeatureService
{
    /** Batch size for insert/upsert (MySQL limit ~65535 placeholders: cols × rows). */
    private const INSERT_BATCH_SIZE = 1000;

    /** Process clients in chunks to avoid memory exhaustion (80k+ clients). Plus gros chunks = moins d’allers-retours DB. */
    private const CLIENT_CHUNK_SIZE = 3000;

    /** Chunk size when streaming transactions (memory). */
    private const TX_STREAM_CHUNK = 10000;

    /** tarif_id → opérateur (CPM 9 Solde téléphonique) : 10,16=Orange ; 15=TT ; 39=Ooredoo ; 43=Taraji */
    private const TARIF_ID_TO_OPERATOR = [
        10 => 'orange',
        16 => 'orange',
        15 => 'tt',
        39 => 'ooredoo',
        43 => 'taraji',
    ];

    /**
     * Extrait les features pour un seul client (usage diagnostic / API).
     * Conserve le comportement N+1 pour ce cas.
     */
    public function extractClientFeatures(int $clientId, Carbon $calculationDate): array
    {
        Log::info("MLMultiOperatorFeatureService - Extraction multi-opérateur pour client $clientId");

        $features = [
            'client_id' => $clientId,
            'calculation_date' => $calculationDate->toDateString(),
        ];

        $startDate = $calculationDate->copy()->subMonths(6);

        try {
            $timweFeatures = $this->extractTimweFeatures($clientId, $startDate, $calculationDate);
            $eklektikFeatures = $this->extractEklektikFeatures($clientId, $startDate, $calculationDate);
            $ooredooFeatures = $this->extractOoredooFeatures($clientId, $startDate, $calculationDate);
            $crossFeatures = $this->extractCrossOperatorFeatures($clientId, $startDate, $calculationDate);
            $offerTypeFeatures = $this->extractOfferTypeFeatures($clientId, $startDate, $calculationDate);
            $preferenceFeatures = $this->extractClientPreferences($clientId, $startDate, $calculationDate);

            $features = array_merge(
                $features,
                $timweFeatures,
                $eklektikFeatures,
                $ooredooFeatures,
                $crossFeatures,
                $offerTypeFeatures,
                $preferenceFeatures
            );
        } catch (\Exception $e) {
            Log::error("MLMultiOperatorFeatureService - Erreur extraction client $clientId", [
                'error' => $e->getMessage()
            ]);
            $features = $this->getDefaultMultiOperatorFeatures($clientId, $calculationDate);
        }

        return $features;
    }

    /**
     * Extrait et enregistre les features pour une date donnée (batch).
     * Pas de requêtes par client : préchargement agrégé + merge en mémoire + insert par lots.
     */
    public function extractAndStoreFeaturesForDate(Carbon $calculationDate): int
    {
        $dateStr = $calculationDate->toDateString();
        Log::info("MLMultiOperatorFeatureService - Début extraction multi-opérateur pour {$dateStr}");

        $t0 = microtime(true);
        $startDate = $calculationDate->copy()->subDays(180);

        // --- 1) Clients actifs (2 requêtes, pas de boucle)
        $allOperatorIds = $this->getMultiOperatorCpmIds();
        if (empty($allOperatorIds)) {
            Log::warning("MLMultiOperatorFeatureService - Aucun opérateur trouvé");
            return 0;
        }

        $activeClients = $this->getActiveClientIdsForDate($calculationDate, $startDate, $allOperatorIds);
        $nClients = count($activeClients);
        Log::info("MLMultiOperatorFeatureService - Clients actifs multi-opérateur: {$nClients}");

        if ($nClients === 0) {
            Log::info("MLMultiOperatorFeatureService - Extraction terminée", ['clients_processed' => 0, 'date' => $dateStr]);
            return 0;
        }

        $allowedColumns = array_flip(Schema::getColumnListing('ml_client_features'));
        $defaults = $this->getDefaultMultiOperatorFeatures(0, $calculationDate);
        unset($defaults['client_id'], $defaults['calculation_date']);

        $totalInserted = 0;
        $totalTxTime = 0;
        $totalSubTime = 0;
        $totalMergeTime = 0;
        $totalInsertTime = 0;
        $chunkIndex = 0;
        $chunks = array_chunk($activeClients, self::CLIENT_CHUNK_SIZE);

        foreach ($chunks as $clientChunk) {
            $chunkIndex++;

            // --- Préchargement transactions pour ce chunk (index: client_id, created_at)
            $t1 = microtime(true);
            $transactionsByClient = $this->loadTransactionsInWindowForClients($startDate, $calculationDate, $clientChunk);
            $totalTxTime += microtime(true) - $t1;

            // --- Préchargement abonnements pour ce chunk (index: client_id, client_abonnement_creation)
            $t2 = microtime(true);
            $subscriptionsByClient = $this->loadSubscriptionsInWindowForClients($startDate, $calculationDate, $allOperatorIds, $clientChunk);
            $totalSubTime += microtime(true) - $t2;

            // --- Calcul des features en mémoire
            $t3 = microtime(true);
            $featuresRows = [];
            foreach ($clientChunk as $clientId) {
                $clientId = (int) $clientId;
                if ($clientId <= 0) {
                    continue;
                }
                $txList = $transactionsByClient[$clientId] ?? [];
                $subList = $subscriptionsByClient[$clientId] ?? [];
                try {
                    $row = $this->computeFeaturesForClient($clientId, $calculationDate, $txList, $subList);
                    $row = array_intersect_key($row, $allowedColumns);
                    if (!empty($row)) {
                        $featuresRows[] = $row;
                    }
                } catch (\Exception $e) {
                    Log::error("MLMultiOperatorFeatureService - Erreur client $clientId: " . $e->getMessage());
                    $row = array_merge(
                        ['client_id' => $clientId, 'calculation_date' => $dateStr],
                        $defaults
                    );
                    $featuresRows[] = array_intersect_key($row, $allowedColumns);
                }
            }
            $totalMergeTime += microtime(true) - $t3;

            // --- Insert par lots de 1000 (évite trop de placeholders MySQL)
            $t4 = microtime(true);
            $inserted = $this->bulkUpsertFeatures($featuresRows, $allowedColumns);
            $totalInsertTime += microtime(true) - $t4;
            $totalInserted += $inserted;

            Log::info("MLMultiOperatorFeatureService - Chunk {$chunkIndex}/" . count($chunks), [
                'clients' => count($clientChunk),
                'rows_inserted' => $inserted,
            ]);

            unset($transactionsByClient, $subscriptionsByClient, $featuresRows);
            gc_collect_cycles();
        }

        $totalTime = round(microtime(true) - $t0, 2);
        Log::info("MLMultiOperatorFeatureService - Extraction terminée", [
            'clients_processed' => $totalInserted,
            'date' => $dateStr,
            'total_seconds' => $totalTime,
            'tx_seconds' => round($totalTxTime, 2),
            'sub_seconds' => round($totalSubTime, 2),
            'merge_seconds' => round($totalMergeTime, 2),
            'insert_seconds' => round($totalInsertTime, 2),
        ]);

        return $totalInserted;
    }

    /**
     * Retourne les country_payments_methods_id pour Timwe, Eklektik, Ooredoo, DGV.
     */
    private function getMultiOperatorCpmIds(): array
    {
        return DB::table('country_payments_methods')
            ->whereRaw("TRIM(LOWER(country_payments_methods_name)) LIKE '%timwe%'")
            ->orWhereRaw("TRIM(LOWER(country_payments_methods_name)) LIKE '%eklektik%'")
            ->orWhereRaw("TRIM(LOWER(country_payments_methods_name)) LIKE '%ooredoo%'")
            ->orWhereRaw("TRIM(LOWER(country_payments_methods_name)) LIKE '%dgv%'")
            ->pluck('country_payments_methods_id')
            ->toArray();
    }

    /**
     * Clients actifs à la date de calcul : abonnements non expirés + clients avec tx sur la fenêtre.
     * Utilise index client_abonnement(client_id, client_abonnement_creation) et transactions_history(created_at, client_id).
     */
    private function getActiveClientIdsForDate(Carbon $calculationDate, Carbon $periodStart, array $cpmIds): array
    {
        $fromSubscriptions = DB::table('client_abonnement')
            ->whereIn('country_payments_methods_id', $cpmIds)
            ->whereNotNull('client_id')
            ->where('client_abonnement_creation', '<=', $calculationDate)
            ->where(function ($q) use ($calculationDate) {
                $q->whereNull('client_abonnement_expiration')
                  ->orWhere('client_abonnement_expiration', '>=', $calculationDate);
            })
            ->distinct()
            ->pluck('client_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->toArray();

        $fromTransactions = DB::table('transactions_history')
            ->whereBetween('created_at', [$periodStart, $calculationDate])
            ->where(function ($q) {
                $q->where('status', 'LIKE', 'TIMWE_%')
                  ->orWhere('status', 'LIKE', 'ORANGE_%')
                  ->orWhere('status', 'LIKE', 'TARAJI_%')
                  ->orWhere('status', 'LIKE', 'TT_%')
                  ->orWhere('status', 'LIKE', '%OOREDOO%')
                  ->orWhere('status', 'LIKE', '%DGV%')
                  ->orWhere('status', 'LIKE', '%EKLEKTIK%')
                  ->orWhere('status', 'LIKE', 'EKLECTIC_%')
                  ->orWhere('status', 'LIKE', '%CLUB_PRIVILEGE%');
            })
            ->distinct()
            ->pluck('client_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->toArray();

        return array_values(array_unique(array_merge($fromSubscriptions, $fromTransactions)));
    }

    /**
     * Charge les transactions de la fenêtre pour une liste de client_id (index: client_id, created_at).
     * Utilise chunkById pour une pagination par clé (évite la dégradation offset sur grosses tables).
     * Retourne [ client_id => [ ['created_at'=>..., 'status'=>..., 'result'=>...], ... ] ]
     */
    private function loadTransactionsInWindowForClients(Carbon $startDate, Carbon $endDate, array $clientIds): array
    {
        $clientIds = array_values(array_filter(array_map('intval', $clientIds), fn ($id) => $id > 0));
        if (empty($clientIds)) {
            return [];
        }

        $byClient = [];
        DB::table('transactions_history')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereIn('client_id', $clientIds)
            ->where(function ($q) {
                $q->where('status', 'LIKE', 'TIMWE_%')
                  ->orWhere('status', 'LIKE', 'ORANGE_%')
                  ->orWhere('status', 'LIKE', 'TARAJI_%')
                  ->orWhere('status', 'LIKE', 'TT_%')
                  ->orWhere('status', 'LIKE', '%OOREDOO%')
                  ->orWhere('status', 'LIKE', '%DGV%')
                  ->orWhere('status', 'LIKE', '%EKLEKTIK%')
                  ->orWhere('status', 'LIKE', 'EKLECTIC_%')
                  ->orWhere('status', 'LIKE', '%CLUB_PRIVILEGE%');
            })
            ->select('transaction_history_id', 'client_id', 'created_at', 'status', 'result')
            ->orderBy('transaction_history_id')
            ->chunkById(self::TX_STREAM_CHUNK, function ($rows) use (&$byClient) {
                foreach ($rows as $r) {
                    $cid = (int) $r->client_id;
                    if ($cid <= 0) {
                        continue;
                    }
                    if (!isset($byClient[$cid])) {
                        $byClient[$cid] = [];
                    }
                    $byClient[$cid][] = [
                        'created_at' => $r->created_at,
                        'status' => $r->status,
                        'result' => $r->result,
                    ];
                }
            }, 'transaction_history_id');

        return $byClient;
    }

    /**
     * Charge tous les abonnements pertinents (client_id, client_abonnement_creation, tarif_id indexés).
     * Retourne [ client_id => [ ['cpm_name'=>..., 'prix'=>..., 'duration'=>..., 'frequence'=>..., 'creation'=>...], ... ] ]
     */
    /**
     * Charge les abonnements créés dans la fenêtre pour une liste de client_id.
     * Jointure minimale : client_abonnement + country_payments_methods + abonnement_tarifs (sans abonnement pour perf).
     * Enrichit chaque abo avec : expiration, tarif_id, operator (orange/tt/ooredoo/taraji), état (facturé/actif/expiré).
     * $endDate = date de calcul (pour dériver actif/expiré à cette date).
     */
    private function loadSubscriptionsInWindowForClients(Carbon $startDate, Carbon $endDate, array $cpmIds, array $clientIds): array
    {
        $clientIds = array_values(array_filter(array_map('intval', $clientIds), fn ($id) => $id > 0));
        if (empty($clientIds)) {
            return [];
        }

        $asOfDate = $endDate->toDateString();
        $byClient = [];
        $rows = DB::table('client_abonnement as ca')
            ->join('country_payments_methods as cpm', 'ca.country_payments_methods_id', '=', 'cpm.country_payments_methods_id')
            ->leftJoin('abonnement_tarifs as at', 'ca.tarif_id', '=', 'at.abonnement_tarifs_id')
            ->whereIn('ca.country_payments_methods_id', $cpmIds)
            ->whereIn('ca.client_id', $clientIds)
            ->whereBetween('ca.client_abonnement_creation', [$startDate, $endDate])
            ->select(
                'ca.client_id',
                'ca.client_abonnement_creation as creation',
                'ca.client_abonnement_expiration as expiration',
                'ca.tarif_id',
                'cpm.country_payments_methods_name as cpm_name',
                'at.abonnement_tarifs_prix as prix',
                'at.abonnement_tarifs_duration as duration',
                'at.abonnement_tarifs_frequence as frequence'
            )
            ->get();

        foreach ($rows as $r) {
            $cid = (int) $r->client_id;
            if ($cid <= 0) {
                continue;
            }
            $tarifId = isset($r->tarif_id) ? (int) $r->tarif_id : null;
            $operator = $tarifId !== null && isset(self::TARIF_ID_TO_OPERATOR[$tarifId])
                ? self::TARIF_ID_TO_OPERATOR[$tarifId]
                : null;
            $expiration = isset($r->expiration) && $r->expiration !== null ? $r->expiration : null;
            if ($expiration === null) {
                $etat = 'facture';
            } else {
                $expStr = $expiration instanceof \DateTimeInterface ? $expiration->format('Y-m-d') : (string) $expiration;
                $etat = $expStr < $asOfDate ? 'expire' : 'actif';
            }
            if (!isset($byClient[$cid])) {
                $byClient[$cid] = [];
            }
            $byClient[$cid][] = [
                'cpm_name' => $r->cpm_name ?? '',
                'prix' => $r->prix ?? null,
                'duration' => $r->duration ?? null,
                'frequence' => $r->frequence ?? null,
                'creation' => $r->creation ?? null,
                'expiration' => $expiration,
                'tarif_id' => $tarifId,
                'operator' => $operator,
                'etat' => $etat,
            ];
        }

        return $byClient;
    }

    /**
     * Calcule toutes les features pour un client à partir des listes préchargées (aucune requête).
     */
    private function computeFeaturesForClient(int $clientId, Carbon $calculationDate, array $txList, array $subList): array
    {
        $dateStr = $calculationDate->toDateString();

        $timwe = $this->computeTimweFeaturesFromList($txList);
        $eklektik = $this->computeEklektikFeaturesFromList($txList, $subList);
        $ooredoo = $this->computeOoredooFeaturesFromList($txList, $subList);
        $cross = $this->computeCrossOperatorFeaturesFromList($subList);
        $offerType = $this->computeOfferTypeFeaturesFromList($subList);
        $prefs = $this->computeClientPreferencesFromList($txList);
        $subState = $this->computeSubscriptionStateFeaturesFromList($subList);

        return array_merge(
            ['client_id' => $clientId, 'calculation_date' => $dateStr],
            $timwe,
            $eklektik,
            $ooredoo,
            $cross,
            $offerType,
            $prefs,
            $subState
        );
    }

    /**
     * À partir des abonnements enrichis (expiration, operator, etat) : compte facturé/expiré/actif et par opérateur.
     */
    private function computeSubscriptionStateFeaturesFromList(array $subList): array
    {
        $factureCount = 0;
        $expireCount = 0;
        $actifCount = 0;
        $orangeCount = 0;
        $ttCount = 0;
        $ooredooCount = 0;

        foreach ($subList as $s) {
            $etat = $s['etat'] ?? null;
            if ($etat === 'facture') {
                $factureCount++;
            } elseif ($etat === 'expire') {
                $expireCount++;
            } elseif ($etat === 'actif') {
                $actifCount++;
            }
            $op = $s['operator'] ?? null;
            if ($op === 'orange') {
                $orangeCount++;
            } elseif ($op === 'tt') {
                $ttCount++;
            } elseif ($op === 'ooredoo') {
                $ooredooCount++;
            }
        }

        return [
            'subs_facture_count' => $factureCount,
            'subs_expire_count' => $expireCount,
            'subs_actif_count' => $actifCount,
            'has_facture_subscription' => $factureCount > 0 ? 1 : 0,
            'orange_subs_count' => $orangeCount,
            'tt_subs_count' => $ttCount,
            'ooredoo_subs_count' => $ooredooCount,
        ];
    }

    private function computeTimweFeaturesFromList(array $txList): array
    {
        $billingPpid = env('TIMWE_BILLING_PPID', '63980');
        $timweTx = array_filter($txList, fn ($t) => str_contains($t['status'] ?? '', 'TIMWE_RENEWED_NOTIF') || str_contains($t['status'] ?? '', 'TIMWE_CHARGE_DELIVERED'));
        $timweTx = array_values(array_filter($timweTx, fn ($t) => !empty($t['result'])));

        $successes = 0;
        $totalRevenue = 0;
        $noBalanceCount = 0;
        $notDeliveredCount = 0;

        foreach ($timweTx as $t) {
            $result = is_string($t['result'] ?? null) ? json_decode($t['result'], true) : ($t['result'] ?? []);
            if (!is_array($result)) {
                continue;
            }
            $ppid = $this->getResultPricepointId($result);
            $delivery = $this->getResultMnoDeliveryCode($result);
            $charged = $this->getResultTotalCharged($result);

            if ((string)$ppid === (string)$billingPpid && $delivery === 'DELIVERED' && $charged > 0) {
                $successes++;
                $totalRevenue += $charged / 1000;
            }
            if ($delivery === 'NO_BALANCE') {
                $noBalanceCount++;
            }
            if ($delivery === 'NOT_DELIVERED') {
                $notDeliveredCount++;
            }
        }

        $attempts = count($timweTx);
        $successRate = $attempts > 0 ? $successes / $attempts : 0;
        $avgRevenue = $successes > 0 ? $totalRevenue / $successes : 0;
        $noBalanceRate = $attempts > 0 ? $noBalanceCount / $attempts : 0;
        $notDeliveredRate = $attempts > 0 ? $notDeliveredCount / $attempts : 0;

        return [
            'timwe_success_rate' => round($successRate, 4),
            'timwe_total_attempts' => $attempts,
            'timwe_total_successes' => $successes,
            'timwe_avg_revenue_per_success' => round($avgRevenue, 3),
            'timwe_no_balance_rate' => round($noBalanceRate, 4),
            'timwe_not_delivered_rate' => round($notDeliveredRate, 4),
            'timwe_has_activity' => $attempts > 0 ? 1 : 0,
        ];
    }

    private function computeEklektikFeaturesFromList(array $txList, array $subList): array
    {
        $eklektikTx = array_filter($txList, function ($t) {
            $s = $t['status'] ?? '';
            return str_starts_with($s, 'ORANGE_') || str_starts_with($s, 'TARAJI_') || str_starts_with($s, 'TT_')
                || str_contains($s, 'EKLEKTIK') || str_starts_with($s, 'EKLECTIC_') || str_contains($s, 'CLUB_PRIVILEGE');
        });

        $eklektikSubs = 0;
        foreach ($subList as $s) {
            if (stripos($s['cpm_name'] ?? '', 'eklektik') !== false) {
                $eklektikSubs++;
            }
        }

        $attempts = count($eklektikTx);
        $successes = 0;
        $dailySuccesses = [];

        foreach ($eklektikTx as $t) {
            $isSuccess = $this->isEklektikSuccess($t);
            if ($isSuccess) {
                $successes++;
                $date = Carbon::parse($t['created_at'])->toDateString();
                $dailySuccesses[$date] = ($dailySuccesses[$date] ?? 0) + 1;
            }
        }

        $successRate = $attempts > 0 ? $successes / $attempts : 0;
        $avgDailySuccesses = count($dailySuccesses) > 0 ? array_sum($dailySuccesses) / count($dailySuccesses) : 0;
        $consistencyScore = count($dailySuccesses) > 7 ? min(count($dailySuccesses) / 30, 1) : 0;

        return [
            'eklektik_success_rate' => round($successRate, 4),
            'eklektik_total_attempts' => $attempts,
            'eklektik_total_subscriptions' => $eklektikSubs,
            'eklektik_avg_daily_successes' => round($avgDailySuccesses, 2),
            'eklektik_daily_consistency' => round($consistencyScore, 4),
            'eklektik_has_activity' => ($attempts > 0 || $eklektikSubs > 0) ? 1 : 0,
        ];
    }

    private function computeOoredooFeaturesFromList(array $txList, array $subList): array
    {
        $ooredooTx = array_filter($txList, function ($t) {
            $s = $t['status'] ?? '';
            return str_contains($s, 'OOREDOO') || str_contains($s, 'DGV');
        });

        $ooredooSubs = 0;
        foreach ($subList as $s) {
            $n = $s['cpm_name'] ?? '';
            if (stripos($n, 'ooredoo') !== false || stripos($n, 'dgv') !== false) {
                $ooredooSubs++;
            }
        }

        $attempts = count($ooredooTx);
        $successes = 0;
        $monthlyPattern = [];

        foreach ($ooredooTx as $t) {
            $isSuccess = $this->isOoredooSuccess($t);
            if ($isSuccess) {
                $successes++;
                $month = Carbon::parse($t['created_at'])->format('Y-m');
                $monthlyPattern[$month] = ($monthlyPattern[$month] ?? 0) + 1;
            }
        }

        $successRate = $attempts > 0 ? $successes / $attempts : 0;
        $monthlyConsistency = count($monthlyPattern) > 0 ? min(count($monthlyPattern) / 6, 1) : 0;
        $avgMonthlySuccesses = count($monthlyPattern) > 0 ? array_sum($monthlyPattern) / count($monthlyPattern) : 0;

        return [
            'ooredoo_success_rate' => round($successRate, 4),
            'ooredoo_total_attempts' => $attempts,
            'ooredoo_total_subscriptions' => $ooredooSubs,
            'ooredoo_avg_monthly_successes' => round($avgMonthlySuccesses, 2),
            'ooredoo_monthly_consistency' => round($monthlyConsistency, 4),
            'ooredoo_has_activity' => ($attempts > 0 || $ooredooSubs > 0) ? 1 : 0,
        ];
    }

    private function computeCrossOperatorFeaturesFromList(array $subList): array
    {
        $operatorNames = [];
        $prices = [];
        $lowPriceCount = 0;
        $highPriceCount = 0;

        foreach ($subList as $s) {
            $name = $s['cpm_name'] ?? '';
            $operatorNames[$name] = true;
            $prix = $s['prix'] ?? null;
            if ($prix !== null && $prix !== '') {
                $prices[(string)$prix] = true;
                if ((float)$prix <= 1.0) {
                    $lowPriceCount++;
                } else {
                    $highPriceCount++;
                }
            } else {
                if (stripos($name, 'eklektik') !== false) {
                    $lowPriceCount++;
                } elseif (preg_match('/timwe|ooredoo|dgv/i', $name)) {
                    $highPriceCount++;
                }
            }
        }

        $operatorCount = count($operatorNames);
        $uniquePricePoints = count($prices);
        $pricePreference = $lowPriceCount > $highPriceCount ? 'low' : ($highPriceCount > $lowPriceCount ? 'high' : 'mixed');
        $operatorDiversity = min($operatorCount / 3, 1);

        return [
            'total_operators_used' => $operatorCount,
            'operator_diversity_score' => round($operatorDiversity, 4),
            'price_preference' => $pricePreference,
            'unique_price_points' => $uniquePricePoints,
            'prefers_low_price' => $pricePreference === 'low' ? 1 : 0,
            'prefers_high_price' => $pricePreference === 'high' ? 1 : 0,
            'is_multi_operator_user' => $operatorCount > 1 ? 1 : 0,
        ];
    }

    private function computeOfferTypeFeaturesFromList(array $subList): array
    {
        $dailyOffers = 0;
        $monthlyOffers = 0;

        foreach ($subList as $r) {
            $name = strtolower(trim($r['cpm_name'] ?? ''));
            $duration = (int)($r['duration'] ?? 0);
            $frequence = (int)($r['frequence'] ?? 0);
            $isTimwe = str_contains($name, 'timwe');
            $isEklektik = str_contains($name, 'eklektik') || str_contains($name, 'orange') || str_contains($name, 'taraji') || str_contains($name, 'tt') || str_contains($name, 'izi');
            $isMonthlyByTarif = ($duration >= 28 && $duration <= 31) || ($duration === 0 && $frequence >= 28 && $frequence <= 31);
            $isDailyByTarif = ($duration === 1) || ($duration === 0 && $frequence === 1);

            if ($isTimwe || $isMonthlyByTarif) {
                $monthlyOffers++;
            } elseif ($isDailyByTarif || $isEklektik) {
                $dailyOffers++;
            } else {
                $dailyOffers++;
            }
        }

        $totalOffers = $dailyOffers + $monthlyOffers;
        $dailyEngagementRate = $totalOffers > 0 ? $dailyOffers / $totalOffers : 0;
        $monthlyEngagementRate = $totalOffers > 0 ? $monthlyOffers / $totalOffers : 0;

        $preferredFrequency = 'unknown';
        if ($dailyOffers > $monthlyOffers * 2) {
            $preferredFrequency = 'daily';
        } elseif ($monthlyOffers > $dailyOffers * 2) {
            $preferredFrequency = 'monthly';
        } elseif ($totalOffers > 0) {
            $preferredFrequency = 'mixed';
        }

        return [
            'daily_offers_count' => $dailyOffers,
            'monthly_offers_count' => $monthlyOffers,
            'total_offers_count' => $totalOffers,
            'daily_engagement_rate' => round($dailyEngagementRate, 4),
            'monthly_engagement_rate' => round($monthlyEngagementRate, 4),
            'preferred_frequency' => $preferredFrequency,
            'prefers_daily_offers' => $preferredFrequency === 'daily' ? 1 : 0,
            'prefers_monthly_offers' => $preferredFrequency === 'monthly' ? 1 : 0,
            'is_frequency_flexible' => $preferredFrequency === 'mixed' ? 1 : 0,
        ];
    }

    private function computeClientPreferencesFromList(array $txList): array
    {
        $operators = [
            'timwe' => fn ($s) => str_starts_with($s, 'TIMWE_'),
            'eklektik' => fn ($s) => str_starts_with($s, 'ORANGE_') || str_starts_with($s, 'TARAJI_') || str_starts_with($s, 'TT_') || str_contains($s, 'EKLEKTIK') || str_starts_with($s, 'EKLECTIC_'),
            'ooredoo' => fn ($s) => str_contains($s, 'OOREDOO') || str_contains($s, 'DGV'),
        ];

        $rates = [];
        foreach ($operators as $opName => $match) {
            $subset = array_filter($txList, fn ($t) => $match($t['status'] ?? ''));
            $attempts = count($subset);
            $successes = 0;
            foreach ($subset as $t) {
                if ($opName === 'eklektik' && $this->isEklektikSuccess($t)) {
                    $successes++;
                } elseif ($opName === 'ooredoo' && $this->isOoredooSuccess($t)) {
                    $successes++;
                } else {
                    $result = is_string($t['result'] ?? null) ? json_decode($t['result'], true) : ($t['result'] ?? []);
                    if (is_array($result) && (! empty($result['success']) || $this->getResultMnoDeliveryCode($result) === 'DELIVERED')) {
                        $successes++;
                    }
                }
            }
            $rates[$opName] = $attempts > 0 ? $successes / $attempts : 0;
        }

        $bestOperator = 'none';
        $maxRate = 0;
        foreach ($rates as $op => $rate) {
            if ($rate > $maxRate) {
                $maxRate = $rate;
                $bestOperator = $op;
            }
        }

        return ['best_performing_operator' => $bestOperator];
    }

    /** @param array<string, int>|null $allowedColumns colonnes autorisées (array_flip), null = recalcul depuis la table */
    private function bulkUpsertFeatures(array $featuresRows, ?array $allowedColumns = null): int
    {
        if (empty($featuresRows)) {
            return 0;
        }

        if ($allowedColumns === null) {
            $allowedColumns = array_flip(Schema::getColumnListing('ml_client_features'));
        }
        $now = now();
        $total = 0;

        foreach (array_chunk($featuresRows, self::INSERT_BATCH_SIZE) as $batch) {
            $filtered = array_map(function (array $row) use ($allowedColumns, $now) {
                $row = array_intersect_key($row, $allowedColumns);
                if (isset($allowedColumns['updated_at'])) {
                    $row['updated_at'] = $now;
                }
                if (isset($allowedColumns['created_at'])) {
                    $row['created_at'] = $now;
                }
                return $row;
            }, $batch);
            $cols = array_keys($filtered[0]);
            if (empty($cols)) {
                continue;
            }
            $updateColumns = array_values(array_diff($cols, ['client_id', 'calculation_date', 'created_at']));
            $this->upsertFeaturesWithDeadlockRetry($filtered, ['client_id', 'calculation_date'], $updateColumns);
            $total += count($filtered);
        }

        return $total;
    }

    /**
     * Upsert avec retry en cas de deadlock MySQL (1213).
     */
    private function upsertFeaturesWithDeadlockRetry(array $rows, array $uniqueBy, array $updateColumns, int $maxAttempts = 3): void
    {
        $attempt = 0;
        while (true) {
            try {
                DB::table('ml_client_features')->upsert($rows, $uniqueBy, $updateColumns);
                return;
            } catch (\Throwable $e) {
                $isDeadlock = $e->getCode() === '40001' || $e->getCode() === 1213
                    || str_contains($e->getMessage(), '1213')
                    || str_contains($e->getMessage(), 'Deadlock');
                $attempt++;
                if (!$isDeadlock || $attempt >= $maxAttempts) {
                    throw $e;
                }
                Log::warning('MLMultiOperatorFeatureService - Deadlock, retry ' . $attempt . '/' . $maxAttempts, [
                    'message' => $e->getMessage(),
                ]);
                usleep(100000 * $attempt);
            }
        }
    }

    // --------------- Helpers JSON (inchangés) ---------------

    private function getResultPricepointId(array $result): ?string
    {
        $ppid = $result['pricepointId'] ?? $result['pricePointId'] ?? $result['pricepoint_id'] ?? null;
        if ($ppid !== null) return (string) $ppid;
        if (isset($result['response']['pricepointId'])) return (string) $result['response']['pricepointId'];
        if (isset($result['user']['pricepointId'])) return (string) $result['user']['pricepointId'];
        if (isset($result['data']['pricepointId'])) return (string) $result['data']['pricepointId'];
        return null;
    }

    private function getResultMnoDeliveryCode(array $result): ?string
    {
        $code = $result['mnoDeliveryCode'] ?? $result['mno_delivery_code'] ?? null;
        if ($code !== null) return (string) $code;
        if (isset($result['response']['mnoDeliveryCode'])) return (string) $result['response']['mnoDeliveryCode'];
        if (isset($result['data']['mnoDeliveryCode'])) return (string) $result['data']['mnoDeliveryCode'];
        return null;
    }

    private function getResultTotalCharged(array $result): int
    {
        if (isset($result['totalCharged'])) return (int) $result['totalCharged'];
        if (isset($result['response']['totalCharged'])) return (int) $result['response']['totalCharged'];
        if (isset($result['data']['totalCharged'])) return (int) $result['data']['totalCharged'];
        return 0;
    }

    /**
     * Eklektik : considère succès si le statut indique CHARGE/RENEW ou si result contient un indicateur de succès.
     * Formats réels (doc/TRANSACTIONS_HISTORY_STATUS_ANALYSIS) : result['message']==='OK', result['status']===0,
     * ou statuts ORANGE_CHARGE_DELIVERED, TT_RENEWED, etc.
     */
    private function isEklektikSuccess(array $t): bool
    {
        $status = $t['status'] ?? '';
        if (str_contains($status, 'CHARGE_DELIVERED') || str_contains($status, 'RENEWED')) {
            return true;
        }
        $result = is_string($t['result'] ?? null) ? json_decode($t['result'], true) : ($t['result'] ?? []);
        if (! is_array($result)) {
            return false;
        }
        if (! empty($result['success']) || $this->getResultMnoDeliveryCode($result) === 'DELIVERED') {
            return true;
        }
        if (isset($result['message']) && (string) $result['message'] === 'OK') {
            return true;
        }
        if (array_key_exists('status', $result) && (int) $result['status'] === 0) {
            return true;
        }
        if (isset($result['confirm']) && (string) $result['confirm'] === 'ok') {
            return true;
        }
        return false;
    }

    /**
     * Ooredoo/DGV : facturation réussie alignée sur OoredooStatsService et tables officielles (ooredoo_daily_stats).
     * - Avant 01/09/2025 : OOREDOO_PAYMENT_OFFLINE = facturation (result souvent null).
     * - Après 01/09/2025 : OOREDOO_PAYMENT_OFFLINE_INIT + result.type=INVOICE + result.status=SUCCESS.
     * - OOREDOO_PAYMENT_SUCCESS = nouvel abonnement réussi.
     */
    private function isOoredooSuccess(array $t): bool
    {
        $status = $t['status'] ?? '';
        if ($status === 'OOREDOO_PAYMENT_OFFLINE') {
            return true;
        }
        if ($status === 'OOREDOO_PAYMENT_SUCCESS' || $status === 'OOREDOO_CHARGE_DELIVERED' || $status === 'OOREDOO_RENEWED') {
            return true;
        }
        if ($status === 'OOREDOO_PAYMENT_OFFLINE_INIT') {
            $result = is_string($t['result'] ?? null) ? json_decode($t['result'], true) : ($t['result'] ?? []);
            if (is_array($result) && isset($result['type']) && (string) $result['type'] === 'INVOICE' && isset($result['status']) && (string) $result['status'] === 'SUCCESS') {
                return true;
            }
            return false;
        }
        $result = is_string($t['result'] ?? null) ? json_decode($t['result'], true) : ($t['result'] ?? []);
        if (! is_array($result)) {
            return false;
        }
        if (! empty($result['success']) || $this->getResultMnoDeliveryCode($result) === 'DELIVERED') {
            return true;
        }
        if (isset($result['status']) && (string) $result['status'] === 'SUCCESS') {
            return true;
        }
        return false;
    }

    // --------------- Anciennes méthodes (single-client, gardées pour diagnostic) ---------------

    private function extractTimweFeatures(int $clientId, Carbon $startDate, Carbon $endDate): array
    {
        $transactions = DB::table('transactions_history as th')
            ->where('th.client_id', $clientId)
            ->where(function ($q) {
                $q->where('th.status', 'LIKE', '%TIMWE_RENEWED_NOTIF%')
                  ->orWhere('th.status', 'LIKE', '%TIMWE_CHARGE_DELIVERED%');
            })
            ->whereBetween('th.created_at', [$startDate, $endDate])
            ->whereNotNull('th.result')
            ->get();

        $txList = $transactions->map(fn ($t) => [
            'created_at' => $t->created_at,
            'status' => $t->status,
            'result' => $t->result,
        ])->all();

        return $this->computeTimweFeaturesFromList($txList);
    }

    private function extractEklektikFeatures(int $clientId, Carbon $startDate, Carbon $endDate): array
    {
        $eklektikTransactions = DB::table('transactions_history as th')
            ->where('th.client_id', $clientId)
            ->where(function ($q) {
                $q->where('th.status', 'LIKE', 'ORANGE_%')
                  ->orWhere('th.status', 'LIKE', 'TARAJI_%')
                  ->orWhere('th.status', 'LIKE', 'TT_%')
                  ->orWhere('th.status', 'LIKE', '%EKLEKTIK%')
                  ->orWhere('th.status', 'LIKE', 'EKLECTIC_%')
                  ->orWhere('th.status', 'LIKE', '%CLUB_PRIVILEGE%');
            })
            ->whereBetween('th.created_at', [$startDate, $endDate])
            ->get();

        $eklektikSubscriptions = DB::table('client_abonnement as ca')
            ->join('country_payments_methods as cpm', 'ca.country_payments_methods_id', '=', 'cpm.country_payments_methods_id')
            ->where('ca.client_id', $clientId)
            ->whereRaw("LOWER(cpm.country_payments_methods_name) LIKE '%eklektik%'")
            ->whereBetween('ca.client_abonnement_creation', [$startDate, $endDate])
            ->count();

        $txList = $eklektikTransactions->map(fn ($t) => ['created_at' => $t->created_at, 'status' => $t->status, 'result' => $t->result])->all();
        $subList = array_fill(0, $eklektikSubscriptions, ['cpm_name' => 'eklektik', 'prix' => null, 'duration' => null, 'frequence' => null, 'creation' => null]);

        return $this->computeEklektikFeaturesFromList($txList, $subList);
    }

    private function extractOoredooFeatures(int $clientId, Carbon $startDate, Carbon $endDate): array
    {
        $ooredooTransactions = DB::table('transactions_history as th')
            ->where('th.client_id', $clientId)
            ->where(function ($q) {
                $q->where('th.status', 'LIKE', '%OOREDOO%')->orWhere('th.status', 'LIKE', '%DGV%');
            })
            ->whereBetween('th.created_at', [$startDate, $endDate])
            ->get();

        $ooredooSubscriptions = DB::table('client_abonnement as ca')
            ->join('country_payments_methods as cpm', 'ca.country_payments_methods_id', '=', 'cpm.country_payments_methods_id')
            ->where('ca.client_id', $clientId)
            ->where(function ($q) {
                $q->whereRaw("LOWER(cpm.country_payments_methods_name) LIKE '%ooredoo%'")
                  ->orWhereRaw("LOWER(cpm.country_payments_methods_name) LIKE '%dgv%'");
            })
            ->whereBetween('ca.client_abonnement_creation', [$startDate, $endDate])
            ->count();

        $txList = $ooredooTransactions->map(fn ($t) => ['created_at' => $t->created_at, 'status' => $t->status, 'result' => $t->result])->all();
        $subList = array_fill(0, $ooredooSubscriptions, ['cpm_name' => 'ooredoo', 'prix' => null, 'duration' => null, 'frequence' => null, 'creation' => null]);

        return $this->computeOoredooFeaturesFromList($txList, $subList);
    }

    private function extractCrossOperatorFeatures(int $clientId, Carbon $startDate, Carbon $endDate): array
    {
        $allOperators = DB::table('client_abonnement as ca')
            ->join('country_payments_methods as cpm', 'ca.country_payments_methods_id', '=', 'cpm.country_payments_methods_id')
            ->leftJoin('abonnement_tarifs as at', 'ca.tarif_id', '=', 'at.abonnement_tarifs_id')
            ->where('ca.client_id', $clientId)
            ->whereBetween('ca.client_abonnement_creation', [$startDate, $endDate])
            ->select('cpm.country_payments_methods_name', 'at.abonnement_tarifs_prix as prix')
            ->get();

        $subList = $allOperators->map(fn ($o) => [
            'cpm_name' => $o->country_payments_methods_name ?? '',
            'prix' => $o->prix ?? null,
            'duration' => null,
            'frequence' => null,
            'creation' => null,
        ])->all();

        return $this->computeCrossOperatorFeaturesFromList($subList);
    }

    private function extractOfferTypeFeatures(int $clientId, Carbon $startDate, Carbon $endDate): array
    {
        $rows = DB::table('client_abonnement as ca')
            ->join('country_payments_methods as cpm', 'ca.country_payments_methods_id', '=', 'cpm.country_payments_methods_id')
            ->leftJoin('abonnement_tarifs as at', 'ca.tarif_id', '=', 'at.abonnement_tarifs_id')
            ->where('ca.client_id', $clientId)
            ->whereBetween('ca.client_abonnement_creation', [$startDate, $endDate])
            ->select('cpm.country_payments_methods_name', 'at.abonnement_tarifs_duration as duration', 'at.abonnement_tarifs_frequence as frequence')
            ->get();

        $subList = $rows->map(fn ($r) => [
            'cpm_name' => $r->country_payments_methods_name ?? '',
            'prix' => null,
            'duration' => $r->duration ?? null,
            'frequence' => $r->frequence ?? null,
            'creation' => null,
        ])->all();

        return $this->computeOfferTypeFeaturesFromList($subList);
    }

    private function extractClientPreferences(int $clientId, Carbon $startDate, Carbon $endDate): array
    {
        $transactions = DB::table('transactions_history')
            ->where('client_id', $clientId)
            ->where(function ($q) {
                $q->where('status', 'LIKE', 'TIMWE_%')
                  ->orWhere('status', 'LIKE', 'ORANGE_%')
                  ->orWhere('status', 'LIKE', 'TARAJI_%')
                  ->orWhere('status', 'LIKE', 'TT_%')
                  ->orWhere('status', 'LIKE', '%EKLEKTIK%')
                  ->orWhere('status', 'LIKE', 'EKLECTIC_%')
                  ->orWhere('status', 'LIKE', '%OOREDOO%')
                  ->orWhere('status', 'LIKE', '%DGV%');
            })
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        $txList = $transactions->map(fn ($t) => ['created_at' => $t->created_at, 'status' => $t->status, 'result' => $t->result])->all();

        return $this->computeClientPreferencesFromList($txList);
    }

    private function getDefaultMultiOperatorFeatures(int $clientId, Carbon $calculationDate): array
    {
        return [
            'client_id' => $clientId,
            'calculation_date' => $calculationDate->toDateString(),
            'timwe_success_rate' => 0,
            'timwe_total_attempts' => 0,
            'timwe_total_successes' => 0,
            'timwe_avg_revenue_per_success' => 0,
            'timwe_no_balance_rate' => 0,
            'timwe_not_delivered_rate' => 0,
            'timwe_has_activity' => 0,
            'eklektik_success_rate' => 0,
            'eklektik_total_attempts' => 0,
            'eklektik_total_subscriptions' => 0,
            'eklektik_avg_daily_successes' => 0,
            'eklektik_daily_consistency' => 0,
            'eklektik_has_activity' => 0,
            'ooredoo_success_rate' => 0,
            'ooredoo_total_attempts' => 0,
            'ooredoo_total_subscriptions' => 0,
            'ooredoo_avg_monthly_successes' => 0,
            'ooredoo_monthly_consistency' => 0,
            'ooredoo_has_activity' => 0,
            'total_operators_used' => 0,
            'operator_diversity_score' => 0,
            'price_preference' => 'unknown',
            'unique_price_points' => 0,
            'prefers_low_price' => 0,
            'prefers_high_price' => 0,
            'is_multi_operator_user' => 0,
            'daily_offers_count' => 0,
            'monthly_offers_count' => 0,
            'total_offers_count' => 0,
            'daily_engagement_rate' => 0,
            'monthly_engagement_rate' => 0,
            'preferred_frequency' => 'unknown',
            'prefers_daily_offers' => 0,
            'prefers_monthly_offers' => 0,
            'is_frequency_flexible' => 0,
            'best_performing_operator' => 'none',
            'subs_facture_count' => 0,
            'subs_expire_count' => 0,
            'subs_actif_count' => 0,
            'has_facture_subscription' => 0,
            'orange_subs_count' => 0,
            'tt_subs_count' => 0,
            'ooredoo_subs_count' => 0,
        ];
    }
}
