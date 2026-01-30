<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TransactionHistory;
use App\Models\Client;
use Illuminate\Http\Request;
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
        set_time_limit(120);
        ini_set('memory_limit', '512M');
        
        try {
            $startDate = $request->input('start_date', Carbon::now()->subDays(30)->format('Y-m-d'));
            $endDate = $request->input('end_date', Carbon::now()->format('Y-m-d'));
            $searchPhone = $request->input('search_phone');
            $deliveryCodeFilter = $request->input('delivery_code');
            
            Log::info("Diagnostic Timwe - Période: {$startDate} à {$endDate}");
            
            // Query de base pour les transactions Timwe
            $query = TransactionHistory::query()
                ->join('client as c', 'transactions_history.client_id', '=', 'c.client_id')
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
                    'c.client_prenom'
                );
            
            // Filtrer par numéro si recherche (historique complet sans contrainte de date)
            if ($searchPhone) {
                $query->where('c.client_telephone', 'LIKE', '%' . $searchPhone . '%');
                Log::info("Recherche par numéro: {$searchPhone} - Historique complet (sans contrainte de date)");
            } else {
                // Appliquer le filtre de date uniquement pour les recherches globales
                $query->whereBetween('transactions_history.created_at', [
                    $startDate . ' 00:00:00',
                    $endDate . ' 23:59:59'
                ]);
            }
            
            $transactions = $query->orderBy('transactions_history.created_at', 'DESC')
                ->limit(10000) // Limite de sécurité
                ->get();
            
            Log::info("Transactions trouvées: " . $transactions->count());
            
            // Analyser les transactions
            $diagnosticData = $this->analyzeTransactions($transactions, $deliveryCodeFilter);
            
            return response()->json([
                'success' => true,
                'period' => [
                    'start' => $searchPhone ? 'Historique complet' : $startDate,
                    'end' => $searchPhone ? '' : $endDate
                ],
                'summary' => $diagnosticData['summary'],
                'by_phone' => $diagnosticData['by_phone'],
                'by_delivery_code' => $diagnosticData['by_delivery_code'],
                'recent_transactions' => $diagnosticData['recent_transactions']
            ]);
            
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
            
            // Garder les 100 dernières transactions pour affichage détaillé
            if (count($recentTransactions) < 100) {
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
                'billing_rate' => $totalTransactions > 0 ? round(($totalBilled / $totalTransactions) * 100, 2) : 0,
                'total_revenue_tnd' => round($totalRevenue, 3),
                'delivery_codes_count' => count($deliveryCodeStats)
            ],
            'by_phone' => $phoneStatsArray,
            'by_delivery_code' => $deliveryCodeFormatted,
            'recent_transactions' => $recentTransactions
        ];
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
                    'Total Tentatives',
                    'Facturé (DELIVERED)',
                    'No Balance',
                    'Non Livré',
                    'Autres',
                    'Total Facturé (TND)',
                    'Dernière Tentative',
                    'Codes Delivery'
                ]);
                
                // Données par téléphone
                foreach ($data->by_phone as $phone) {
                    fputcsv($file, [
                        $phone->phone,
                        $phone->client_name,
                        $phone->total_attempts,
                        $phone->delivered,
                        $phone->no_balance,
                        $phone->not_delivered,
                        $phone->other,
                        number_format($phone->total_charged_tnd, 3, '.', ''),
                        $phone->last_attempt,
                        implode(', ', $phone->delivery_codes)
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
