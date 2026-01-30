<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SubStoreService
{
    /**
     * Cache key pour les sub-stores
     */
    const CACHE_KEY = 'sub_store_operators';
    
    /**
     * Durée du cache en secondes (5 minutes)
     */
    const CACHE_TTL = 300;

    /**
     * Récupérer dynamiquement la liste des opérateurs sub-stores
     * Utilise le cache pour optimiser les performances
     */
    public function getSubStoreOperators(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            try {
                // Récupérer les sub-stores depuis la table stores
                // NOTE: "IZI Privilèges" est un OPÉRATEUR (country_payments_methods), pas un sub-store
                $subStores = DB::table('stores')
                    ->where(function($query) {
                        $query->where('is_sub_store', 1)
                              // Ajouter le store ID 54 manuellement
                              ->orWhere('store_id', 54);
                    })
                    ->where('store_active', 1)
                    ->pluck('store_name')
                    ->toArray();
                
                // Récupérer les opérateurs sub-stores depuis country_payments_methods
                $subStorePaymentMethods = DB::table('country_payments_methods')
                    ->whereIn('country_payments_methods_name', [
                        'Sub-Stores', 'Retail', 'Partnership', 'White Mark', 
                        'Magasins', 'Boutiques', 'Points de Vente', 'Sofrecom',
                        'Université centrale'
                    ])
                    ->pluck('country_payments_methods_name')
                    ->toArray();
                
                // Combiner les deux listes
                return array_merge($subStores, $subStorePaymentMethods);
                
            } catch (\Exception $e) {
                // Fallback : liste statique en cas d'erreur
                Log::warning('Erreur récupération sub-stores dynamiques, utilisation du fallback', [
                    'error' => $e->getMessage()
                ]);
                
                return [
                    'Sub-Stores', 'Retail', 'Partnership', 'White Mark', 
                    'Magasins', 'Boutiques', 'Points de Vente', 'Sofrecom',
                    'Université centrale'
                ];
            }
        });
    }

    /**
     * Récupérer les sub-stores avec leurs IDs
     */
    public function getSubStoresWithIds(): array
    {
        return Cache::remember(self::CACHE_KEY . '_with_ids', self::CACHE_TTL, function () {
            try {
                return DB::table('stores')
                    ->where(function($query) {
                        $query->where('is_sub_store', 1)
                              // Ajouter le store ID 54 manuellement
                              ->orWhere('store_id', 54);
                    })
                    ->where('store_active', 1)
                    ->select('store_name as name', 'store_id')
                    ->orderBy('store_name')
                    ->get()
                    ->toArray();
                
            } catch (\Exception $e) {
                Log::warning('Erreur récupération sub-stores avec IDs, utilisation du fallback', [
                    'error' => $e->getMessage()
                ]);
                
                return [];
            }
        });
    }

    /**
     * Vérifier si un opérateur est un sub-store
     */
    public function isSubStoreOperator(string $operatorName): bool
    {
        $subStoreOperators = $this->getSubStoreOperators();
        return in_array($operatorName, $subStoreOperators);
    }

    /**
     * Récupérer tous les opérateurs (classiques + sub-stores)
     */
    public function getAllOperators(): array
    {
        return Cache::remember('all_operators', self::CACHE_TTL, function () {
            try {
                // Récupérer les opérateurs classiques
                $operators = DB::table('country_payments_methods')
                    ->where(function($query) {
                        $query->whereIn('country_payments_methods_name', [
                        "S'abonner via TT",
                        "S'abonner via Orange", 
                        "S'abonner via Taraji",
                        "S'abonner via Timwe",
                            "S'abonner via IZI",
                        "Solde téléphonique",
                        "Solde Taraji mobile"
                    ])
                        ->orWhere('country_payments_methods_name', 'LIKE', '%IZI%');
                    })
                    ->distinct()
                    ->pluck('country_payments_methods_name', 'country_payments_methods_name')
                    ->toArray();
                
                // Récupérer les sub-stores
                $subStores = DB::table('stores')
                    ->where('is_sub_store', 1)
                    ->where('store_active', 1)
                    ->pluck('store_name', 'store_name')
                    ->toArray();
                
                // Combiner les deux listes
                return array_merge($operators, $subStores);
                
            } catch (\Exception $e) {
                Log::warning('Erreur récupération tous les opérateurs, utilisation du fallback', [
                    'error' => $e->getMessage()
                ]);
                
                return [
                    "S'abonner via TT" => "S'abonner via TT",
                    "S'abonner via Orange" => "S'abonner via Orange",
                    "S'abonner via Taraji" => "S'abonner via Taraji",
                    "S'abonner via Timwe" => "S'abonner via Timwe"
                ];
            }
        });
    }

    /**
     * Récupérer seulement les opérateurs classiques
     */
    public function getClassicOperators(): array
    {
        return Cache::remember('classic_operators', self::CACHE_TTL, function () {
            try {
                return DB::table('country_payments_methods')
                    ->where(function($query) {
                        $query->whereIn('country_payments_methods_name', [
                        "S'abonner via TT",
                        "S'abonner via Orange", 
                        "S'abonner via Taraji",
                        "S'abonner via Timwe",
                            "S'abonner via IZI",
                        "Solde téléphonique",
                        "Solde Taraji mobile"
                    ])
                        ->orWhere('country_payments_methods_name', 'LIKE', '%IZI%');
                    })
                    ->distinct()
                    ->pluck('country_payments_methods_name', 'country_payments_methods_name')
                    ->toArray();
                
            } catch (\Exception $e) {
                Log::warning('Erreur récupération opérateurs classiques, utilisation du fallback', [
                    'error' => $e->getMessage()
                ]);
                
                return [
                    "S'abonner via TT" => "S'abonner via TT",
                    "S'abonner via Orange" => "S'abonner via Orange",
                    "S'abonner via Taraji" => "S'abonner via Taraji",
                    "S'abonner via Timwe" => "S'abonner via Timwe"
                ];
            }
        });
    }

    /**
     * Récupérer seulement les sub-stores
     */
    public function getSubStores(): array
    {
        return Cache::remember('sub_stores_only', self::CACHE_TTL, function () {
            try {
                return DB::table('stores')
                    ->where(function($query) {
                        $query->where('is_sub_store', 1)
                              // Ajouter le store ID 54 manuellement
                              ->orWhere('store_id', 54);
                    })
                    ->where('store_active', 1)
                    ->pluck('store_name', 'store_name')
                    ->toArray();
                
            } catch (\Exception $e) {
                Log::warning('Erreur récupération sub-stores uniquement, utilisation du fallback', [
                    'error' => $e->getMessage()
                ]);
                
                return [];
            }
        });
    }

    /**
     * Vider le cache des sub-stores
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::CACHE_KEY . '_with_ids');
        Cache::forget('all_operators');
        Cache::forget('classic_operators');
        Cache::forget('sub_stores_only');
    }

    /**
     * Récupérer les sub-stores disponibles pour un utilisateur
     */
    public function getAvailableSubStoresForUser($user): array
    {
        // Super Admin : tous les sub-stores
        if ($user->isSuperAdmin()) {
            return $this->getSubStoresWithIds();
        }
        
        // Admin Sub-Store ou Collaborateur : seulement leur sub-store assigné
        $primaryOperator = $user->primaryOperator();
        if ($primaryOperator && $this->isSubStoreOperator($primaryOperator->operator_name)) {
            return [
                ['name' => $primaryOperator->operator_name, 'store_id' => null]
            ];
        }
        
        // Autres cas : chercher par opérateur assigné
        if ($primaryOperator) {
            $subStores = DB::table('stores')
                ->where('is_sub_store', 1)
                ->where('store_active', 1)
                ->where('store_name', 'LIKE', '%' . $primaryOperator->operator_name . '%')
                ->select('store_name as name', 'store_id')
                ->get()
                ->toArray();
                
            if (!empty($subStores)) {
                return $subStores;
            }
        }
        
        // Fallback : retourner une liste vide
        return [];
    }

    /**
     * Obtenir le sub-store par défaut pour un utilisateur
     */
    public function getDefaultSubStoreForUser($user): ?string
    {
        // Super Admin : ALL par défaut
        if ($user->isSuperAdmin()) {
            return 'ALL';
        }
        
        // Admin Sub-Store : leur opérateur assigné
        $primaryOperator = $user->primaryOperator();
        if ($primaryOperator && $this->isSubStoreOperator($primaryOperator->operator_name)) {
            return $primaryOperator->operator_name;
        }
        
        return null;
    }
}
