<?php

namespace App\Services;

use App\Models\OoredooDailyStat;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OoredooStatsService
{
    /**
     * Calculer et stocker les statistiques pour une date donnée
     */
    public function calculateAndStoreStatsForDate(Carbon $date): void
    {
        try {
            Log::info("OoredooStatsService - Calcul pour {$date->format('Y-m-d')}");

            // Vérifier si cette date a déjà des données officielles DGV
            $existing = OoredooDailyStat::where('stat_date', $date->format('Y-m-d'))->first();
            if ($existing && $existing->data_source === 'officiel_dgv') {
                Log::info("OoredooStatsService - Date {$date->format('Y-m-d')} utilise déjà les données officielles DGV, skip");
                return;
            }

            $startOfDay = $date->copy()->startOfDay();
            $endOfDay = $date->copy()->endOfDay();

            // 1. Nouveaux abonnements (OOREDOO_PAYMENT_SUCCESS)
            $newSubs = $this->getNewSubscriptions($startOfDay, $endOfDay);

            // 2. Désabonnements
            $unsubs = $this->getUnsubscriptions($startOfDay, $endOfDay);

            // 3. Abonnements actifs à la fin de la journée
            $activeSubs = $this->getActiveSubscriptions($endOfDay);

            // 4. Total clients actifs
            $totalClients = $this->getTotalActiveClients($endOfDay);

            // 5. Facturations (INVOICE)
            $billings = $this->getBillings($startOfDay, $endOfDay);

            // 6. Revenus
            $revenue = $this->getRevenue($startOfDay, $endOfDay);

            // 7. Taux de facturation
            $billingRate = $totalClients > 0 ? ($billings / $totalClients) * 100 : 0;

            // 8. Répartition par offre
            $offersBreakdown = $this->getOffersBreakdown($startOfDay, $endOfDay);

            // Stocker les statistiques
            OoredooDailyStat::updateOrCreate(
                ['stat_date' => $date->format('Y-m-d')],
                [
                    'new_subscriptions' => $newSubs,
                    'unsubscriptions' => $unsubs,
                    'active_subscriptions' => $activeSubs,
                    'total_billings' => $billings,
                    'billing_rate' => round($billingRate, 2),
                    'revenue_tnd' => $revenue,
                    'total_clients' => $totalClients,
                    'offers_breakdown' => $offersBreakdown,
                    'data_source' => 'calculé',
                ]
            );

            Log::info("OoredooStatsService - Statistiques stockées avec succès", [
                'date' => $date->format('Y-m-d'),
                'new_subs' => $newSubs,
                'billings' => $billings,
                'revenue' => $revenue
            ]);

        } catch (\Exception $e) {
            Log::error("OoredooStatsService - Erreur: " . $e->getMessage(), [
                'date' => $date->format('Y-m-d'),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Nouveaux abonnements (OOREDOO_PAYMENT_SUCCESS)
     * Format ancien : {"event": "Subscription", ...}
     * Format nouveau : {"type": "SUBSCRIPTION", "status": "SUCCESS", ...}
     */
    private function getNewSubscriptions(Carbon $start, Carbon $end): int
    {
        // Compter tous les OOREDOO_PAYMENT_SUCCESS (ancien et nouveau format)
        return DB::table('transactions_history')
            ->where('status', 'OOREDOO_PAYMENT_SUCCESS')
            ->whereBetween('created_at', [$start, $end])
            ->count();
    }

    /**
     * Désabonnements
     */
    private function getUnsubscriptions(Carbon $start, Carbon $end): int
    {
        return DB::table('transactions_history')
            ->whereIn('status', [
                'OOREDOO_PAYMENT_UNSUBSCRIBE',
                'OOREDOO_UNSUBSCRIBE',
                'DELAYED_OOREDOO_PAYMENT_UNSUBSCRIBE'
            ])
            ->whereBetween('created_at', [$start, $end])
            ->count();
    }

    /**
     * Abonnements actifs (reconstruits depuis les transactions)
     * Approche simplifiée : Abonnements SUCCESS - Désabonnements jusqu'à cette date
     */
    private function getActiveSubscriptions(Carbon $endOfDay): int
    {
        // Total abonnements créés jusqu'à cette date
        $totalSuccess = DB::table('transactions_history')
            ->where('status', 'OOREDOO_PAYMENT_SUCCESS')
            ->where('created_at', '<=', $endOfDay)
            ->count();
        
        // Total désabonnements jusqu'à cette date
        $totalUnsub = DB::table('transactions_history')
            ->whereIn('status', ['OOREDOO_PAYMENT_UNSUBSCRIBE', 'OOREDOO_UNSUBSCRIBE', 'DELAYED_OOREDOO_PAYMENT_UNSUBSCRIBE'])
            ->where('created_at', '<=', $endOfDay)
            ->count();
        
        return max(0, $totalSuccess - $totalUnsub);
    }

    /**
     * Total clients actifs uniques (reconstruit depuis les transactions)
     * Approche SQL pure pour éviter les dépassements de mémoire
     * Format 2021-2024 : $.msisdn
     * Format Sept 2025+ : $.data.user.msisdn
     */
    private function getTotalActiveClients(Carbon $endOfDay): int
    {
        // Compter les msisdns uniques dans les PAYMENT_SUCCESS
        $successCount = DB::select("
            SELECT COUNT(DISTINCT COALESCE(
                JSON_EXTRACT(result, '$.msisdn'),
                JSON_EXTRACT(result, '$.data.user.msisdn'),
                JSON_EXTRACT(result, '$.user.msisdn')
            )) as count
            FROM transactions_history
            WHERE status = 'OOREDOO_PAYMENT_SUCCESS'
            AND created_at <= ?
            AND JSON_VALID(result) = 1
        ", [$endOfDay])[0]->count ?? 0;
        
        // Compter les msisdns uniques dans les désabonnements
        $unsubCount = DB::select("
            SELECT COUNT(DISTINCT COALESCE(
                JSON_EXTRACT(result, '$.msisdn'),
                JSON_EXTRACT(result, '$.data.user.msisdn'),
                JSON_EXTRACT(result, '$.user.msisdn')
            )) as count
            FROM transactions_history
            WHERE status IN ('OOREDOO_PAYMENT_UNSUBSCRIBE', 'OOREDOO_UNSUBSCRIBE', 'DELAYED_OOREDOO_PAYMENT_UNSUBSCRIBE')
            AND created_at <= ?
            AND JSON_VALID(result) = 1
        ", [$endOfDay])[0]->count ?? 0;
        
        // Estimation : clients actifs ≈ clients avec succès - clients désabonnés
        // (ce n'est pas parfait car un client peut s'abonner plusieurs fois, mais c'est une approximation acceptable)
        return max(0, $successCount - $unsubCount);
    }

    /**
     * Facturations
     * Avant Mai 2022 : Pas de facturations (PAYMENT_OFFLINE n'existait pas encore)
     * Mai 2022 - Août 2025 : OOREDOO_PAYMENT_OFFLINE (result=null, compter par statut)
     * Après 01/09/2025 : OOREDOO_PAYMENT_OFFLINE_INIT avec type="INVOICE"
     */
    private function getBillings(Carbon $start, Carbon $end): int
    {
        $cutoffDate = Carbon::parse('2025-09-01');
        $offlineStartDate = Carbon::parse('2022-05-19'); // Date de début de PAYMENT_OFFLINE
        
        // Avant Mai 2022 : Pas de facturations
        if ($end < $offlineStartDate) {
            return 0;
        }
        
        // Ajuster le début si la période commence avant Mai 2022
        $adjustedStart = $start < $offlineStartDate ? $offlineStartDate : $start;
        
        if ($end < $cutoffDate) {
            // Période Mai 2022 - Août 2025 : utiliser OOREDOO_PAYMENT_OFFLINE uniquement
            return DB::table('transactions_history')
                ->where('status', 'OOREDOO_PAYMENT_OFFLINE')
                ->whereBetween('created_at', [$adjustedStart, $end])
                ->count();
        } elseif ($adjustedStart >= $cutoffDate) {
            // Période après 01/09/2025 : utiliser OOREDOO_PAYMENT_OFFLINE_INIT avec type=INVOICE
            return DB::table('transactions_history')
                ->where('status', 'OOREDOO_PAYMENT_OFFLINE_INIT')
                ->whereBetween('created_at', [$adjustedStart, $end])
                ->whereRaw("JSON_EXTRACT(result, '$.type') = 'INVOICE'")
                ->whereRaw("JSON_EXTRACT(result, '$.status') = 'SUCCESS'")
                ->count();
        } else {
            // Période à cheval sur la date de coupure : compter les deux
            $before = DB::table('transactions_history')
                ->where('status', 'OOREDOO_PAYMENT_OFFLINE')
                ->whereBetween('created_at', [$adjustedStart, $cutoffDate->copy()->subSecond()])
                ->count();
                
            $after = DB::table('transactions_history')
                ->where('status', 'OOREDOO_PAYMENT_OFFLINE_INIT')
                ->whereBetween('created_at', [$cutoffDate, $end])
                ->whereRaw("JSON_EXTRACT(result, '$.type') = 'INVOICE'")
                ->whereRaw("JSON_EXTRACT(result, '$.status') = 'SUCCESS'")
                ->count();
                
            return $before + $after;
        }
    }

    /**
     * Revenus totaux
     * Avant Mai 2022 : Pas de revenus (PAYMENT_OFFLINE n'existait pas encore)
     * Mai 2022 - Août 2025 : Calculer depuis PAYMENT_OFFLINE + client_abonnement + prix des offres
     * Après 01/09/2025 : Extraire invoice.price depuis OOREDOO_PAYMENT_OFFLINE_INIT
     */
    private function getRevenue(Carbon $start, Carbon $end): float
    {
        $cutoffDate = Carbon::parse('2025-09-01');
        $offlineStartDate = Carbon::parse('2022-05-19'); // Date de début de PAYMENT_OFFLINE
        
        // Après 01/09/2025 : utiliser invoice.price depuis result
        if ($start >= $cutoffDate) {
            return $this->getRevenueFromInvoices($start, $end);
        }
        // Avant Mai 2022 : Pas de revenus (PAYMENT_OFFLINE n'existait pas)
        elseif ($end < $offlineStartDate) {
            Log::info("OoredooStatsService - Période avant PAYMENT_OFFLINE, revenu = 0", [
                'start' => $start->format('Y-m-d'),
                'end' => $end->format('Y-m-d')
            ]);
            return 0;
        }
        // Mai 2022 - Août 2025 : calculer depuis les abonnements et leurs offres
        elseif ($end < $cutoffDate) {
            // Si la période commence avant Mai 2022, ajuster le début
            $adjustedStart = $start < $offlineStartDate ? $offlineStartDate : $start;
            return $this->getRevenueFromSubscriptions($adjustedStart, $end);
        }
        // Période à cheval sur 01/09/2025 : combiner les deux méthodes
        else {
            // Si la période commence avant Mai 2022, ajuster le début
            $adjustedStart = $start < $offlineStartDate ? $offlineStartDate : $start;
            $revenueBefore = $this->getRevenueFromSubscriptions($adjustedStart, $cutoffDate->copy()->subSecond());
            $revenueAfter = $this->getRevenueFromInvoices($cutoffDate, $end);
            return $revenueBefore + $revenueAfter;
        }
    }

    /**
     * Revenus depuis les invoices (après 01/09/2025)
     */
    private function getRevenueFromInvoices(Carbon $start, Carbon $end): float
    {
        $transactions = DB::table('transactions_history')
            ->where('status', 'OOREDOO_PAYMENT_OFFLINE_INIT')
            ->whereBetween('created_at', [$start, $end])
            ->whereRaw("JSON_EXTRACT(result, '$.type') = 'INVOICE'")
            ->whereRaw("JSON_EXTRACT(result, '$.status') = 'SUCCESS'")
            ->pluck('result');

        $totalRevenue = 0;
        foreach ($transactions as $resultJson) {
            $result = json_decode($resultJson, true);
            if (isset($result['invoice']['price'])) {
                $totalRevenue += (float) $result['invoice']['price'];
            }
        }

        return $totalRevenue;
    }

    /**
     * Revenus depuis les abonnements + prix des offres (avant 01/09/2025)
     * Utilise le tarif_id de transactions_history pour récupérer le prix réel
     */
    private function getRevenueFromSubscriptions(Carbon $start, Carbon $end): float
    {
        // Récupérer les paiements OFFLINE avec leur tarif_id
        $payments = DB::table('transactions_history as th')
            ->leftJoin('abonnement_tarifs as at', 'th.tarif_id', '=', 'at.abonnement_tarifs_id')
            ->where('th.status', 'OOREDOO_PAYMENT_OFFLINE')
            ->whereBetween('th.created_at', [$start, $end])
            ->select(
                'th.tarif_id',
                'at.abonnement_tarifs_prix',
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('th.tarif_id', 'at.abonnement_tarifs_prix')
            ->get();
        
        $totalRevenue = 0;
        $defaultPrice = 0.3; // Prix par défaut si tarif_id est null ou prix non trouvé
        
        foreach ($payments as $payment) {
            // Utiliser le prix du tarif, ou le prix par défaut si null
            $price = $payment->abonnement_tarifs_prix ?? $defaultPrice;
            $totalRevenue += $price * $payment->count;
        }
        
        Log::info("OoredooStatsService - Revenus calculés depuis tarifs", [
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d'),
            'payments_groups' => $payments->count(),
            'total_payments' => $payments->sum('count'),
            'total_revenue' => $totalRevenue
        ]);
        
        return $totalRevenue;
    }


    /**
     * Répartition par offre
     * Format ancien : {"event": "Subscription", "service_id": "25985", ...}
     * Format nouveau : {"type": "SUBSCRIPTION", "offer": {"commercialName": "...", "id": 4066}, ...}
     */
    private function getOffersBreakdown(Carbon $start, Carbon $end): array
    {
        $transactions = DB::table('transactions_history')
            ->where('status', 'OOREDOO_PAYMENT_SUCCESS')
            ->whereBetween('created_at', [$start, $end])
            ->pluck('result');

        $offers = [];
        foreach ($transactions as $resultJson) {
            $result = json_decode($resultJson, true);
            
            // Nouveau format (>= 2025)
            if (isset($result['offer']['commercialName'])) {
                $offerName = $result['offer']['commercialName'];
                $offerId = $result['offer']['id'] ?? 0;
            }
            // Ancien format (<2025) - utiliser un nom par défaut
            elseif (isset($result['event']) && $result['event'] === 'Subscription') {
                $offerName = 'Club Privilèges';
                $offerId = 4066; // ID par défaut
            }
            else {
                continue;
            }
            
            if (!isset($offers[$offerName])) {
                $offers[$offerName] = [
                    'offre_name' => $offerName,
                    'offre_id' => $offerId,
                    'count' => 0
                ];
            }
            $offers[$offerName]['count']++;
        }

        return array_values($offers);
    }
}

