<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Display the main dashboard view
     *
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        
        // Redirection spéciale pour les utilisateurs sub-stores
        if ($user->isSubStoreUser()) {
            return redirect()->route('sub-stores.dashboard');
        }
        
        // Déterminer le thème selon le type d'utilisateur
        $theme = $request->get('theme', $user->isTimweOoredooUser() ? 'ooredoo' : 'club_privileges');
        
        // Déterminer l'opérateur par défaut selon le rôle
        $defaultOperator = $this->getDefaultOperatorForUser($user);
        $availableOperators = $this->getAvailableOperatorsForUser($user);
        
        return view('dashboard', compact('defaultOperator', 'availableOperators', 'theme'));
    }

    /**
     * Display the dashboard with calendar view
     *
     * @return \Illuminate\View\View
     */
    public function dashboard()
    {
        return view('dashboard');
    }

    /**
     * Get dashboard configuration data
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getConfig()
    {
        $config = [
            'app_name' => config('app.name'),
            'app_version' => '1.0.0',
            'refresh_interval' => 300000, // 5 minutes in milliseconds
            'api_endpoints' => [
                'dashboard_data' => route('api.dashboard.data'),
                'kpis' => route('api.dashboard.kpis'),
                'merchants' => route('api.dashboard.merchants'),
                'transactions' => route('api.dashboard.transactions'),
                'subscriptions' => route('api.dashboard.subscriptions'),
            ],
            'periods' => [
                'primary' => [
                    'label' => 'Primary Period',
                    'start_date' => '2025-08-01',
                    'end_date' => '2025-08-14',
                    'display' => 'August 1-14, 2025'
                ],
                'comparison' => [
                    'label' => 'Comparison Period', 
                    'start_date' => '2025-07-18',
                    'end_date' => '2025-07-31',
                    'display' => 'July 18-31, 2025'
                ]
            ],
            'filters' => [
                'payment_method' => 'Subscribe via Timwe',
                'service_status' => 'Launch Phase'
            ]
        ];

        return response()->json($config);
    }

    /**
     * Déterminer l'opérateur par défaut pour un utilisateur
     */
    private function getDefaultOperatorForUser($user): string
    {
        if ($user->isSuperAdmin()) {
            // Super Admin voit TOUJOURS la vue globale (pas d'opérateur spécifique)
            return 'ALL';
        } else {
            // Admin/Collaborateur DOIT avoir un opérateur assigné
            $primaryOperator = $user->primaryOperator();
            if (!$primaryOperator) {
                // Si aucun opérateur assigné, utiliser le premier disponible ou Timwe par défaut
                $firstOperator = $user->operators()->first();
                return $firstOperator ? $firstOperator->operator_name : 'S\'abonner via Timwe';
            }
            return $primaryOperator->operator_name;
        }
    }

    /**
     * Récupérer la liste des opérateurs disponibles pour un utilisateur
     */
    private function getAvailableOperatorsForUser($user): array
    {
        if ($user->isSuperAdmin()) {
            // Super Admin peut voir tous les opérateurs + vue globale
            $allOperators = \DB::table('country_payments_methods')
                             ->distinct()
                             ->pluck('country_payments_methods_name')
                             ->toArray();
            
            // Ajouter l'option "Tous les opérateurs" en premier
            return array_merge(['ALL' => 'Tous les opérateurs'], array_combine($allOperators, $allOperators));
        } else {
            // Admin/Collaborateur ne voit que ses opérateurs assignés
            return $user->operators->pluck('operator_name', 'operator_name')->toArray();
        }
    }
}
