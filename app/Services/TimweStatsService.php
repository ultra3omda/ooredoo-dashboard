<?php

namespace App\Services;

use App\Models\TimweDailyStat;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TimweStatsService
{
    /**
     * Calculer et stocker les stats Timwe pour une date donnée
     */
    public function calculateAndStoreStatsForDate(Carbon $date): bool
    {
        try {
            Log::info("TimweStatsService - Calcul stats pour date: {$date->format('Y-m-d')}");

            // Récupérer les IDs des opérateurs Timwe
            $timweOperatorIds = DB::table('country_payments_methods')
                ->where('country_payments_methods_name', 'LIKE', '%timwe%')
                ->pluck('country_payments_methods_id')
                ->toArray();

            if (empty($timweOperatorIds)) {
                Log::warning("TimweStatsService - Aucun opérateur Timwe trouvé");
                return false;
            }

            $startOfDay = $date->copy()->startOfDay();
            $endOfDay = $date->copy()->endOfDay();

            // 1. Nouveaux abonnements
            $newSubs = DB::table('client_abonnement as ca')
                ->whereIn('ca.country_payments_methods_id', $timweOperatorIds)
                ->whereBetween('ca.client_abonnement_creation', [$startOfDay, $endOfDay])
                ->count();

            // 2. Désabonnements
            $unsubs = DB::table('client_abonnement as ca')
                ->whereIn('ca.country_payments_methods_id', $timweOperatorIds)
                ->whereBetween('ca.client_abonnement_expiration', [$startOfDay, $endOfDay])
                ->whereNotNull('ca.client_abonnement_expiration')
                ->count();

            // 3. Simchurn (abonnements créés ET expirés le même jour)
            $simchurn = DB::table('client_abonnement as ca')
                ->whereIn('ca.country_payments_methods_id', $timweOperatorIds)
                ->whereBetween('ca.client_abonnement_creation', [$startOfDay, $endOfDay])
                ->whereNotNull('ca.client_abonnement_expiration')
                ->whereColumn(DB::raw('DATE(ca.client_abonnement_creation)'), DB::raw('DATE(ca.client_abonnement_expiration)'))
                ->count();

            // 4. Revenu Simchurn (calculé depuis transactions_history avec pricepointId = 63980)
            $billingPpid = env('TIMWE_BILLING_PPID', '63980');
            
            // Récupérer les IDs des abonnements simchurn
            $simchurnIds = DB::table('client_abonnement as ca')
                ->whereIn('ca.country_payments_methods_id', $timweOperatorIds)
                ->whereBetween('ca.client_abonnement_creation', [$startOfDay, $endOfDay])
                ->whereNotNull('ca.client_abonnement_expiration')
                ->whereColumn(DB::raw('DATE(ca.client_abonnement_creation)'), DB::raw('DATE(ca.client_abonnement_expiration)'))
                ->pluck('ca.client_id')
                ->toArray();
            
            $simchurnRevenue = 0;
            if (!empty($simchurnIds)) {
                // Calculer le revenu depuis transactions_history
                $transactions = DB::table('transactions_history as th')
                    ->whereIn('th.client_id', $simchurnIds)
                    ->where(function($q) {
                        $q->where('th.status', 'LIKE', '%TIMWE_RENEWED_NOTIF%')
                          ->orWhere('th.status', 'LIKE', '%TIMWE_CHARGE_DELIVERED%');
                    })
                    ->whereBetween('th.created_at', [$startOfDay, $endOfDay])
                    ->get();
                
                foreach ($transactions as $transaction) {
                    if ($transaction->result) {
                        $result = json_decode($transaction->result, true);
                        if (isset($result['pricepointId']) && $result['pricepointId'] == $billingPpid) {
                            if (isset($result['mnoDeliveryCode']) && $result['mnoDeliveryCode'] === 'DELIVERED') {
                                if (isset($result['totalCharged'])) {
                                    $simchurnRevenue += floatval($result['totalCharged']);
                                }
                            }
                        }
                    }
                }
            }

            // 5. Abonnements actifs à la fin de la journée
            $activeSubs = DB::table('client_abonnement as ca')
                ->whereIn('ca.country_payments_methods_id', $timweOperatorIds)
                ->where('ca.client_abonnement_creation', '<=', $endOfDay)
                ->where(function($q) use ($endOfDay) {
                    $q->whereNull('ca.client_abonnement_expiration')
                      ->orWhere('ca.client_abonnement_expiration', '>', $endOfDay);
                })
                ->count();

            // 6. Total clients (actifs à cette date)
            $totalClients = DB::table('client_abonnement as ca')
                ->whereIn('ca.country_payments_methods_id', $timweOperatorIds)
                ->where('ca.client_abonnement_creation', '<=', $endOfDay)
                ->where(function($q) use ($endOfDay) {
                    $q->whereNull('ca.client_abonnement_expiration')
                      ->orWhere('ca.client_abonnement_expiration', '>', $endOfDay);
                })
                ->distinct('ca.client_id')
                ->count('ca.client_id');

            // 7. Facturations (transactions avec pricepointId = 63980, mnoDeliveryCode = DELIVERED ET totalCharged > 0)
            // CORRECTION: Utiliser la même logique que generate_timwe_daily_unique_numbers.php
            // Joindre directement avec 'client' (pas 'client_abonnement') et compter les numéros uniques par téléphone
            $transactions = DB::table('transactions_history as th')
                ->join('client as c', 'th.client_id', '=', 'c.client_id')
                ->whereBetween('th.created_at', [$startOfDay, $endOfDay])
                ->where(function($q) {
                    $q->where('th.status', 'LIKE', '%TIMWE_RENEWED_NOTIF%')
                      ->orWhere('th.status', 'LIKE', '%TIMWE_CHARGE_DELIVERED%');
                })
                ->select('th.client_id', 'th.result', 'c.client_telephone')
                ->get();
            
            // Compter les numéros uniques facturés (comme dans les CSV)
            // Utiliser le téléphone comme clé unique, ou client_id si téléphone vide
            $billedPhones = [];
            $billings = 0;
            foreach ($transactions as $transaction) {
                if ($transaction->result && $transaction->client_id) {
                    $result = json_decode($transaction->result, true);
                    if (!is_array($result)) {
                        continue;
                    }
                    
                    $ppid = $result['pricepointId'] ?? null;
                    $delivery = $result['mnoDeliveryCode'] ?? null;
                    $totalCharged = isset($result['totalCharged']) ? (int)$result['totalCharged'] : 0;
                    
                    // Même logique que le script : pricepointId = billingPpid, mnoDeliveryCode = DELIVERED, totalCharged > 0
                    if ((string)$ppid !== (string)$billingPpid || $delivery !== 'DELIVERED' || $totalCharged <= 0) {
                        continue;
                    }
                    
                    // Utiliser le téléphone comme clé unique (comme dans le script)
                    $phone = trim((string)($transaction->client_telephone ?? ''));
                    if ($phone === '') {
                        $phone = 'client_id:' . $transaction->client_id;
                    }
                    
                    // Compter uniquement les numéros uniques (comme dans les CSV)
                    if (!isset($billedPhones[$phone])) {
                        $billedPhones[$phone] = true;
                        $billings++;
                    }
                }
            }

            // 8. Taux de facturation
            $billingRate = $totalClients > 0 ? round(($billings / $totalClients) * 100, 2) : 0;

            // 9. Revenus (calculés depuis transactions_history avec pricepointId = 63980, mnoDeliveryCode = DELIVERED et totalCharged > 0)
            // totalCharged est en millimes, donc on divise par 1000 pour obtenir des TND
            // CORRECTION: Utiliser la même logique que pour les facturations (même filtres)
            // Pour les revenus, on somme TOUS les totalCharged même si c'est le même client plusieurs fois
            $revenueTnd = 0;
            foreach ($transactions as $transaction) {
                if ($transaction->result) {
                    $result = json_decode($transaction->result, true);
                    if (!is_array($result)) {
                        continue;
                    }
                    
                    $ppid = $result['pricepointId'] ?? null;
                    $delivery = $result['mnoDeliveryCode'] ?? null;
                    $totalCharged = isset($result['totalCharged']) ? (int)$result['totalCharged'] : 0;
                    
                    // Même logique que le script : pricepointId = billingPpid, mnoDeliveryCode = DELIVERED, totalCharged > 0
                    if ((string)$ppid !== (string)$billingPpid || $delivery !== 'DELIVERED' || $totalCharged <= 0) {
                        continue;
                    }
                    
                    // Convertir millimes en TND (diviser par 1000)
                    $revenueTnd += floatval($totalCharged) / 1000;
                }
            }
            
            $revenueUsd = $revenueTnd * 0.343; // Conversion approximative: 1 TND = 0.343 USD

            // 10. Détail par offre (désactivé pour l'instant - table offre n'existe pas)
            $offersBreakdown = [];

            // Supprimer les anciennes stats si elles existent
            TimweDailyStat::deleteStatsForDate($date);

            // Créer ou mettre à jour les stats
            $stat = TimweDailyStat::create([
                'stat_date' => $date->format('Y-m-d'),
                'new_subscriptions' => $newSubs,
                'unsubscriptions' => $unsubs,
                'simchurn' => $simchurn,
                'simchurn_revenue' => round($simchurnRevenue, 3),
                'active_subscriptions' => $activeSubs,
                'total_billings' => $billings,
                'billing_rate' => $billingRate,
                'revenue_tnd' => round($revenueTnd, 3),
                'revenue_usd' => round($revenueUsd, 3),
                'total_clients' => $totalClients,
                'offers_breakdown' => $offersBreakdown,
                'calculated_at' => now(),
            ]);

            Log::info("TimweStatsService - Stats créées pour {$date->format('Y-m-d')}", [
                'new_subs' => $newSubs,
                'active_subs' => $activeSubs,
                'billings' => $billings,
                'billing_rate' => $billingRate
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error("TimweStatsService - Erreur calcul stats pour {$date->format('Y-m-d')}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Calculer les stats pour une période
     */
    public function calculateStatsForPeriod(Carbon $startDate, Carbon $endDate): int
    {
        $calculated = 0;
        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            if ($this->calculateAndStoreStatsForDate($currentDate)) {
                $calculated++;
            }
            $currentDate->addDay();
        }

        Log::info("TimweStatsService - Période calculée: {$calculated} jours sur " . $startDate->diffInDays($endDate) + 1);

        return $calculated;
    }

    /**
     * Récupérer les stats agrégées pour une période
     */
    public function getAggregatedStats(Carbon $startDate, Carbon $endDate): array
    {
        $stats = TimweDailyStat::getStatsForPeriod($startDate, $endDate);

        if ($stats->isEmpty()) {
            return [
                'new_subscriptions' => 0,
                'unsubscriptions' => 0,
                'simchurn' => 0,
                'simchurn_revenue' => 0,
                'active_subscriptions' => 0,
                'total_billings' => 0,
                'billing_rate' => 0,
                'revenue_tnd' => 0,
                'revenue_usd' => 0,
                'total_clients' => 0,
                'daily_stats' => []
            ];
        }

        // Les valeurs actives et taux de facturation viennent du dernier jour
        $lastDayStat = $stats->last();

        return [
            'new_subscriptions' => $stats->sum('new_subscriptions'),
            'unsubscriptions' => $stats->sum('unsubscriptions'),
            'simchurn' => $stats->sum('simchurn'),
            'simchurn_revenue' => round($stats->sum('simchurn_revenue'), 3),
            'active_subscriptions' => $lastDayStat->active_subscriptions,
            'total_billings' => $stats->sum('total_billings'),
            'billing_rate' => $lastDayStat->billing_rate,
            'revenue_tnd' => round($stats->sum('revenue_tnd'), 3),
            'revenue_usd' => round($stats->sum('revenue_usd'), 3),
            'total_clients' => $lastDayStat->total_clients,
            'daily_stats' => $stats->toArray()
        ];
    }
}

