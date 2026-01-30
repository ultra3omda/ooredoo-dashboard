<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SubStoreService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class OperatorsController extends Controller
{
    protected $subStoreService;

    public function __construct(SubStoreService $subStoreService)
    {
        $this->subStoreService = $subStoreService;
    }

    /**
     * Récupérer la liste des opérateurs disponibles
     */
    public function getOperators(Request $request)
    {
        try {
            $startTime = microtime(true);
            $user = auth()->user();
            
            if (!$user) {
                return response()->json([
                    'operators' => [],
                    'default_operator' => 'ALL',
                    'user_role' => 'guest',
                    'error' => 'Utilisateur non authentifié'
                ], 401);
            }
            
            // Utiliser le cache pour éviter les requêtes répétées
            // Cache plus long pour SuperAdmin (beaucoup d'opérateurs)
            $cacheTTL = $user->isSuperAdmin() ? 1800 : 600; // 30 min pour SuperAdmin, 10 min pour autres
            $cacheKey = 'operators:user:' . $user->id . ':v4';
            
            $result = Cache::remember($cacheKey, $cacheTTL, function () use ($user) {
                // Récupérer les opérateurs selon le rôle de l'utilisateur
                // Ces méthodes utilisent déjà leur propre cache interne
                $operatorsArray = $this->getAvailableOperatorsForUser($user);
                $defaultOperator = $this->getDefaultOperatorForUser($user);
                
                // Convertir le tableau associatif en format attendu par le frontend
                // Conversion optimisée - traitement direct
                $operators = [];
                foreach ($operatorsArray as $value => $label) {
                    if ($label !== '') { // Ignorer les labels vides
                        $operators[] = [
                            'value' => (string)$value,
                            'label' => $label
                        ];
                    }
                }
                
                return [
                    'operators' => $operators,
                    'default_operator' => (string)$defaultOperator,
                    'user_role' => $user->role->name ?? 'collaborator',
                    'hasAllOption' => isset($operatorsArray['ALL'])
                ];
            });
            
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);
            
            // Log uniquement si la requête prend plus de 1 seconde (pour debug)
            if ($executionTime > 1000) {
                Log::info("Operators API lente", [
                    'user_id' => $user->id,
                    'role' => $user->role->name ?? 'unknown',
                    'execution_time_ms' => $executionTime,
                    'operators_count' => count($result['operators']),
                    'cached' => Cache::has($cacheKey)
                ]);
            }
            
            return response()->json($result);
            
        } catch (\Exception $e) {
            Log::error("Erreur dans Operators getOperators: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());
            
            // Ne jamais retourner de fallback - retourner une erreur claire
            return response()->json([
                'success' => false,
                'operators' => [],
                'default_operator' => 'ALL',
                'user_role' => 'guest',
                'error' => 'Erreur lors du chargement des opérateurs',
                'message' => $e->getMessage(),
                'data_source' => 'error'
            ], 500);
        }
    }
    
    /**
     * Obtenir les opérateurs disponibles pour un utilisateur
     */
    private function getAvailableOperatorsForUser($user)
    {
        // Super Admin : tous les opérateurs
        if ($user->isSuperAdmin()) {
            return $this->getAllOperators();
        }
        
        // Admin : opérateurs assignés
        if ($user->isAdmin()) {
            return $this->getUserAssignedOperators($user);
        }
        
        // Collaborateur : opérateurs assignés
        if ($user->isCollaborator()) {
            return $this->getUserAssignedOperators($user);
        }
        
        // Fallback : opérateurs de base
        return $this->getBasicOperators();
    }
    
    /**
     * Récupérer tous les opérateurs (avec IDs) - avec cache optimisé
     */
    private function getAllOperators()
    {
        $cacheKey = 'operators:all:v4';
        $cacheTTL = 1800; // 30 minutes - cache très long pour SuperAdmin
        
        return Cache::remember($cacheKey, $cacheTTL, function () {
            $operators = ['ALL' => 'Tous les opérateurs'];
            
            // Récupérer tous les opérateurs depuis country_payments_methods avec leurs IDs
            // Optimisé : sélectionner seulement les colonnes nécessaires, pas de WHERE complexes
            // Limiter à 1000 pour éviter les problèmes de performance
            // Utiliser chunk si nécessaire pour de très grandes tables
            $allOperators = DB::table('country_payments_methods')
                ->whereNotNull('country_payments_methods_name')
                ->where('country_payments_methods_name', '!=', '')
                ->select('country_payments_methods_id', 'country_payments_methods_name')
                ->orderBy('country_payments_methods_name')
                ->limit(1000)
                ->get();
            
            // Conversion optimisée - traitement direct sans vérifications supplémentaires
            foreach ($allOperators as $operator) {
                $name = trim($operator->country_payments_methods_name);
                if ($name !== '') {
                    $operators[$operator->country_payments_methods_id] = $name;
                }
            }
            
            return $operators;
        });
    }
    
    /**
     * Récupérer les opérateurs assignés à un utilisateur (avec IDs) - optimisé avec cache
     * Pour les collaborateurs, ne pas inclure "Tous les opérateurs"
     */
    private function getUserAssignedOperators($user)
    {
        // Cache par utilisateur pour éviter les requêtes répétées
        $cacheKey = 'operators:user:' . $user->id . ':assigned:v2';
        $cacheTTL = 600; // 10 minutes
        
        return Cache::remember($cacheKey, $cacheTTL, function () use ($user) {
            $operators = [];
            
            // Pour les admins, ajouter "Tous les opérateurs", mais pas pour les collaborateurs
            if ($user->isAdmin()) {
                $operators = ['ALL' => 'Tous les opérateurs'];
            }
            
            // Récupérer les noms d'opérateurs assignés - optimisé avec une seule requête
            $assignedOperatorNames = DB::table('user_operators')
                ->where('user_id', $user->id)
                ->where('is_active', true)
                ->pluck('operator_name')
                ->map(function($name) { return trim($name); })
                ->filter()
                ->unique()
                ->values()
                ->toArray();
            
            if (empty($assignedOperatorNames)) {
                return $operators;
            }
            
            // Récupérer les opérateurs depuis country_payments_methods avec leurs IDs
            // Optimisé : utiliser whereIn avec TRIM dans une sous-requête ou utiliser une approche plus simple
            // Pour de meilleures performances, on utilise une requête avec plusieurs OR mais limitée
            $operatorsFromDB = DB::table('country_payments_methods')
                ->where(function($query) use ($assignedOperatorNames) {
                    foreach ($assignedOperatorNames as $name) {
                        $query->orWhereRaw("TRIM(country_payments_methods_name) = ?", [trim($name)]);
                    }
                })
                ->select('country_payments_methods_id', 'country_payments_methods_name')
                ->get();
            
            foreach ($operatorsFromDB as $operator) {
                $operators[$operator->country_payments_methods_id] = trim($operator->country_payments_methods_name);
            }
            
            return $operators;
        });
    }
    
    /**
     * Récupérer les opérateurs de base
     */
    private function getBasicOperators()
    {
        return $this->subStoreService->getClassicOperators();
    }
    
    /**
     * Obtenir l'opérateur par défaut pour un utilisateur (retourne l'ID) - optimisé avec une seule requête
     */
    private function getDefaultOperatorForUser($user)
    {
        // Super Admin : ALL par défaut
        if ($user->isSuperAdmin()) {
            return 'ALL';
        }
        
        // Admin/Collaborateur : récupérer l'ID de l'opérateur principal ou le premier assigné avec une seule requête JOIN
        $operatorResult = DB::table('user_operators as uo')
            ->join('country_payments_methods as cpm', function($join) {
                $join->on(DB::raw('TRIM(cpm.country_payments_methods_name)'), '=', DB::raw('TRIM(uo.operator_name)'));
            })
            ->where('uo.user_id', $user->id)
            ->where('uo.is_active', true)
            ->orderBy('uo.is_primary', 'desc')
            ->orderBy('uo.id', 'asc')
            ->select('cpm.country_payments_methods_id')
            ->first();
        
        if ($operatorResult && $operatorResult->country_payments_methods_id) {
            return (string)$operatorResult->country_payments_methods_id;
        }
        
        // Si aucun opérateur trouvé, récupérer les opérateurs disponibles et retourner le premier
        $availableOperators = $this->getAvailableOperatorsForUser($user);
        if (!empty($availableOperators)) {
            // Exclure 'ALL' pour les non-SuperAdmin
            $operatorsWithoutAll = array_filter($availableOperators, function($key) {
                return $key !== 'ALL';
            }, ARRAY_FILTER_USE_KEY);
            
            if (!empty($operatorsWithoutAll)) {
                return (string)array_key_first($operatorsWithoutAll);
            }
            
            // Si seulement 'ALL' est disponible (ne devrait pas arriver pour non-SuperAdmin)
            return 'ALL';
        }
        
        // Dernier fallback
        return 'ALL';
    }
    
}
