<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Services\EklektikRevenueSharingService;

class EklektikStatsService
{
    private $baseUrl = 'https://stats.eklectic.tn/getelements.php';
    private $username;
    private $password;
    private $operators;

    private $revenueSharingService;

    public function __construct()
    {
        $this->baseUrl = config('eklektik.stats.base_url', 'https://stats.eklectic.tn/getelements.php');
        $this->operators = config('eklektik.stats.operators', []);
        $this->revenueSharingService = new EklektikRevenueSharingService();
    }

    /**
     * Synchroniser les statistiques Eklektik pour une période donnée
     */
    public function syncStatsForPeriod($startDate, $endDate)
    {
        Log::info('🔄 [EKLEKTIK STATS] Début de la synchronisation', [
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);

        $results = [
            'total_synced' => 0,
            'operators' => [],
            'errors' => []
        ];

        $credentials = config('eklektik.stats.credentials', []);

        foreach ($credentials as $operatorName => $config) {
            try {
                Log::info("📊 [EKLEKTIK STATS] Synchronisation $operatorName");
                
                $operatorResults = [
                    'synced' => 0,
                    'records' => 0,
                    'offers' => []
                ];

                foreach ($config['offers'] as $offreId) {
                    Log::info("  📦 [EKLEKTIK STATS] Offre $offreId pour $operatorName");
                    
                    $data = $this->fetchStatsFromAPI($offreId, $operatorName, $startDate, $endDate, $config['username'], $config['password']);
                    
                    if (!empty($data)) {
                        $synced = $this->storeStats($data, $operatorName, $offreId);
                        $operatorResults['offers'][$offreId] = [
                            'synced' => $synced,
                            'records' => count($data)
                        ];
                        $operatorResults['synced'] += $synced;
                        $operatorResults['records'] += count($data);
                    } else {
                        Log::info("    ⚠️ [EKLEKTIK STATS] Aucune donnée pour l'offre $offreId");
                        $operatorResults['offers'][$offreId] = [
                            'synced' => 0,
                            'records' => 0
                        ];
                    }
                }

                $results['operators'][$operatorName] = $operatorResults;
                $results['total_synced'] += $operatorResults['synced'];
                
            } catch (\Exception $e) {
                $error = "Erreur pour $operatorName: " . $e->getMessage();
                Log::error("❌ [EKLEKTIK STATS] $error");
                $results['errors'][] = $error;
            }
        }

        Log::info('✅ [EKLEKTIK STATS] Synchronisation terminée', $results);
        return $results;
    }

    /**
     * Récupérer les statistiques depuis l'API Eklektik
     */
    private function fetchStatsFromAPI($offreId, $operatorName, $startDate, $endDate, $username, $password)
    {
        $params = [
            'dim' => 'daily',
            'dim2' => 'offre',
            'offreid' => $offreId,
            'datedeb' => $startDate,
            'datefin' => $endDate,
            '_' => time() * 1000
        ];

        $url = $this->baseUrl . '?' . http_build_query($params);
        
        Log::info("🌐 [EKLEKTIK STATS] Appel API pour $operatorName (offre $offreId)", ['url' => $url]);

        $verifySsl = config('eklektik.stats.verify_ssl', true);
        $response = Http::timeout(30)
            ->withOptions(['verify' => $verifySsl])
            ->withBasicAuth($username, $password)
            ->withHeaders([
                'Accept' => 'application/json',
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ])
            ->get($url);

        if (!$response->successful()) {
            throw new \Exception("Erreur HTTP {$response->status()}: {$response->body()}");
        }

        $data = $response->json();
        
        if (empty($data['data'])) {
            Log::info("📭 [EKLEKTIK STATS] Aucune donnée pour $operatorName (offre $offreId)");
            return [];
        }

        Log::info("📊 [EKLEKTIK STATS] Données reçues pour $operatorName (offre $offreId)", [
            'count' => count($data['data'])
        ]);

        return $data['data'];
    }

    /**
     * Stocker les statistiques en base de données
     */
    private function storeStats($data, $operatorName, $offreId)
    {
        $synced = 0;
        
        foreach ($data as $row) {
            try {
                // Parser les données selon la structure Eklektik
                $parsedData = $this->parseEklektikRow($row, $operatorName, $offreId);
                
                // Insérer ou mettre à jour
                DB::table('eklektik_stats_daily')->updateOrInsert(
                    [
                        'date' => $parsedData['date'],
                        'operator' => $parsedData['operator'],
                        'offre_id' => $parsedData['offre_id']
                    ],
                    array_merge($parsedData, [
                        'synced_at' => now(),
                        'updated_at' => now()
                    ])
                );
                
                $synced++;
                
            } catch (\Exception $e) {
                Log::error("❌ [EKLEKTIK STATS] Erreur parsing ligne", [
                    'row' => $row,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $synced;
    }

    /**
     * Parser une ligne de données Eklektik
     */
    private function parseEklektikRow($row, $operatorName, $offreId)
    {
        // Structure basée sur l'interface Eklektik:
        // [date, offer, new_sub, unsub, simchurn, rev_simchurn, active_sub, nb_facturation, taux_facturation, revenu_ttc_local, revenu_ttc_usd, revenu_ttc_tnd]
        
        $offerName = $row[1] ?? 'Offre Inconnue';
        $offerType = $this->extractOfferType($offerName);
        $revenuTtcTnd = (float) str_replace([' ', 'TND'], '', $row[11]);
        
        // Calculer le partage des revenus
        $revenueSharing = $this->revenueSharingService->calculateRevenueSharing($operatorName, $revenuTtcTnd);
        
        return array_merge([
            'date' => Carbon::parse($row[0])->format('Y-m-d'),
            'operator' => $operatorName,
            'offre_id' => $offreId,
            'service_name' => $offerName,
            'offer_name' => $offerName,
            'offer_type' => $offerType,
            'new_subscriptions' => (int) $row[2],
            'unsubscriptions' => (int) $row[3],
            'simchurn' => (int) $row[4],
            'rev_simchurn_cents' => (int) str_replace(' ', '', $row[5]),
            'rev_simchurn_tnd' => (float) str_replace([' ', 'TND'], '', $row[5]),
            'active_subscribers' => (int) str_replace(' ', '', $row[6]),
            'nb_facturation' => (int) str_replace(' ', '', $row[7]),
            'billing_rate' => (float) str_replace('%', '', $row[8]),
            'revenu_ttc_local' => (float) str_replace([' ', 'TND'], '', $row[9]),
            'revenu_ttc_usd' => (float) $row[10],
            'revenu_ttc_tnd' => $revenuTtcTnd,
            // Colonnes de compatibilité
            'renewals' => 0, // Pas disponible dans la nouvelle structure
            'charges' => (int) str_replace(' ', '', $row[7]), // Utilise nb_facturation
            'revenue_cents' => (int) str_replace(' ', '', $row[5]), // Utilise rev_simchurn
            'total_revenue' => $revenuTtcTnd, // Utilise revenu_ttc_tnd
            'average_price' => 0, // Pas disponible dans la nouvelle structure
            'total_amount' => $revenuTtcTnd, // Utilise revenu_ttc_tnd
            'source' => 'eklektik_api'
        ], $revenueSharing);
    }

    /**
     * Extraire le type d'offre depuis le nom
     */
    private function extractOfferType($offerName)
    {
        if (str_contains($offerName, 'DAILY_WEB')) {
            return 'DAILY_WEB';
        } elseif (str_contains($offerName, 'DAILY_SMS')) {
            return 'DAILY_SMS';
        } elseif (str_contains($offerName, 'WEEKLY')) {
            return 'WEEKLY';
        } elseif (str_contains($offerName, 'MONTHLY')) {
            return 'MONTHLY';
        }
        
        return 'UNKNOWN';
    }

    /**
     * Récupérer les statistiques locales pour une période
     */
    public function getLocalStats($startDate, $endDate, $operator = null)
    {
        $query = DB::table('eklektik_stats_daily')
            ->whereBetween('date', [$startDate, $endDate]);

        if ($operator && $operator !== 'ALL') {
            $query->where('operator', $operator);
        }

        return $query->orderBy('date', 'desc')->get();
    }

    /**
     * Calculer les KPIs agrégés
     */
    public function calculateKPIs($stats)
    {
        if ($stats->isEmpty()) {
            return [
                'total_new_subscriptions' => 0,
                'total_renewals' => 0,
                'total_charges' => 0,
                'total_unsubscriptions' => 0,
                'total_revenue' => 0,
                'average_billing_rate' => 0,
                'total_active_subscribers' => 0,
                'operators_distribution' => []
            ];
        }

        $kpis = [
            'total_new_subscriptions' => $stats->sum('new_subscriptions'),
            'total_renewals' => $stats->sum('renewals'),
            'total_charges' => $stats->sum('charges'),
            'total_unsubscriptions' => $stats->sum('unsubscriptions'),
            'total_revenue' => $stats->sum('total_revenue'),
            'average_billing_rate' => $stats->avg('billing_rate'),
            'total_active_subscribers' => $stats->max('active_subscribers'), // Dernière valeur
            'operators_distribution' => []
        ];

        // Calculer la répartition par opérateur
        $operators = $stats->groupBy('operator');
        foreach ($operators as $operator => $operatorStats) {
            $kpis['operators_distribution'][$operator] = [
                'total' => $operatorStats->count(),
                'new_subscriptions' => $operatorStats->sum('new_subscriptions'),
                'renewals' => $operatorStats->sum('renewals'),
                'charges' => $operatorStats->sum('charges'),
                'unsubscriptions' => $operatorStats->sum('unsubscriptions'),
                'revenue' => $operatorStats->sum('total_revenue')
            ];
        }

        return $kpis;
    }

    /**
     * Synchroniser les données d'hier (pour le cron quotidien)
     */
    public function syncYesterdayStats()
    {
        $yesterday = Carbon::yesterday()->format('Y-m-d');
        return $this->syncStatsForPeriod($yesterday, $yesterday);
    }

    /**
     * Synchroniser les données des 30 derniers jours
     */
    public function syncLast30Days()
    {
        $endDate = Carbon::yesterday()->format('Y-m-d');
        $startDate = Carbon::yesterday()->subDays(30)->format('Y-m-d');
        return $this->syncStatsForPeriod($startDate, $endDate);
    }
}
