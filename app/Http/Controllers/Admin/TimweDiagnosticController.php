<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TransactionHistory;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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

    /**
     * Récupérer les données de diagnostic pour une période
     */
    public function getDiagnosticData(Request $request)
    {
        set_time_limit(180);
        ini_set('memory_limit', '1024M');
        
        try {
            $startDate = $request->input('start_date', Carbon::now()->subDays(7)->format('Y-m-d'));
            $endDate = $request->input('end_date', Carbon::now()->format('Y-m-d'));
            $searchPhone = $request->input('search_phone');
            $deliveryCodeFilter = $request->input('delivery_code');
            $page = $request->input('page', 1);
            $perPage = $request->input('per_page', 50);

            $cacheKey = 'timwe_diagnostic:' . md5(implode('|', [
                $startDate,
                $endDate,
                $searchPhone ?? '',
                $deliveryCodeFilter ?? ''
            ]));
            $ttl = $searchPhone ? 600 : 300; // 10 min recherche, 5 min période

            $payload = Cache::get($cacheKey);
            if ($payload !== null) {
                Log::info("Diagnostic Timwe - Cache HIT: {$cacheKey}");
                $payload['cached'] = true;
                $payload['cached_at'] = $payload['_cached_at'] ?? null;
                unset($payload['_cached_at']);
                return response()->json($payload);
            }

            Log::info("Diagnostic Timwe - Cache MISS - Période: {$startDate} à {$endDate}, Page: {$page}");
            
            $query = TransactionHistory::query()
                ->join('client as c', 'transactions_history.client_id', '=', 'c.client_id')
                ->leftJoin('client_abonnement as ca', function($join) {
                    $join->on('c.client_id', '=', 'ca.client_id')
                         ->whereRaw('ca.client_abonnement_id = (SELECT MIN(client_abonnement_id) FROM client_abonnement WHERE client_id = c.client_id)');
                })
                ->where(function($q) {
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
            
            $transactions = $query->orderBy('transactions_history.created_at', 'DESC')->get();
            $totalCount = $transactions->count();
            Log::info("Transactions chargées: {$totalCount} pour la période");
            
            $diagnosticData = $this->analyzeTransactions($transactions, $deliveryCodeFilter);
            
            $phoneList = array_column($diagnosticData['by_phone'], 'phone');
            $lifetimeByPhone = $this->getLifetimeStatsByPhones($phoneList);
            foreach ($diagnosticData['by_phone'] as &$row) {
                $p = $row['phone'];
                $row['lifetime_attempts'] = $lifetimeByPhone[$p]['lifetime_attempts'] ?? 0;
                $row['lifetime_delivered'] = $lifetimeByPhone[$p]['lifetime_delivered'] ?? 0;
                $row['lifetime_no_balance'] = $lifetimeByPhone[$p]['lifetime_no_balance'] ?? 0;
                $row['lifetime_not_delivered'] = $lifetimeByPhone[$p]['lifetime_not_delivered'] ?? 0;
                $row['lifetime_other'] = $lifetimeByPhone[$p]['lifetime_other'] ?? 0;
                $row['lifetime_total_charged_tnd'] = $lifetimeByPhone[$p]['lifetime_total_charged_tnd'] ?? 0;
                $row['lifetime_last_attempt'] = $lifetimeByPhone[$p]['lifetime_last_attempt'] ?? null;
                $row['days_inscription_to_last'] = null;
                if (!empty($row['subscription_date']) && !empty($row['lifetime_last_attempt'])) {
                    $sub = Carbon::parse($row['subscription_date']);
                    $last = Carbon::parse($row['lifetime_last_attempt']);
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
                'summary' => $diagnosticData['summary'],
                'by_phone' => $diagnosticData['by_phone'],
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
     * Analyser les transactions et générer les statistiques
     */
    private function analyzeTransactions($transactions, $deliveryCodeFilter = null)
    {
        $phoneStats = [];
        $deliveryCodeStats = [];
        $totalTransactions = 0;
        $totalBilled = 0;
        $totalRevenue = 0;
        $recentTransactions = [];
        
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
            
            // Garder toutes les transactions pour affichage détaillé (pagination côté client)
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
                    $list[] = [
                        'transaction_id' => $transaction->transaction_history_id,
                        'date' => $transaction->created_at,
                        'phone' => $phone,
                        'delivery_code' => $mnoDeliveryCode,
                        'total_charged' => $totalCharged,
                        'total_charged_tnd' => round($totalCharged / 1000, 3),
                        'is_billed' => $isBilled,
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
