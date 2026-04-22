<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Role;
use App\Models\UserOperator;
use App\Models\PasswordResetRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Admin\AuditLogController;
use App\Mail\PasswordResetMail;

class UserManagementController extends Controller
{
    /**
     * Afficher la liste des utilisateurs
     */
    public function index()
    {
        $user = auth()->user();
        
        // Logique selon les 5 types d'utilisateurs
        switch ($user->getUserType()) {
            case 'super_admin_club_privileges':
                // Super Admin voit TOUS les utilisateurs
                $users = User::with(['role', 'operators'])
                    ->paginate(20);
                break;
                
            case 'admin_club_privileges':
                // Admin CP voit tous les utilisateurs Club Privilèges (sauf Super Admins)
                $users = User::where('platform_type', 'club_privileges')
                    ->whereHas('role', function($query) {
                        $query->where('name', '!=', 'super_admin');
                    })
                    ->with(['role', 'operators'])
                    ->paginate(20);
                break;
                
            case 'admin_operator':
                // Admin opérateur : voit les utilisateurs de son opérateur
                $operatorName = $user->getPrimaryOperatorName();
                $users = User::whereHas('operators', function($query) use ($operatorName) {
                    $query->where('operator_name', $operatorName);
                })
                ->whereHas('role', function($query) {
                    $query->where('name', '!=', 'super_admin');
                })
                ->with(['role', 'operators'])
                ->paginate(20);
                break;

            case 'admin_sub_store':
                // Admin sub-store : voit TOUS les utilisateurs de son sub-store (campagnes et collaborateurs)
                $subStoreName = $user->getPrimaryOperatorName();
                $users = User::whereHas('operators', function($query) use ($subStoreName) {
                    $query->where('operator_name', $subStoreName);
                })
                ->whereHas('role', function($query) {
                    $query->where('name', '!=', 'super_admin');
                })
                ->with(['role', 'operators'])
                ->paginate(20);
                break;

            case 'collaborator':
            default:
                // Collaborateur : SEULEMENT les utilisateurs qu'ils ont créés + eux-mêmes
                $users = User::where(function($query) use ($user) {
                    $query->where('created_by', $user->id)
                          ->orWhere('id', $user->id);
                })
                ->whereHas('role', function($query) {
                    $query->where('name', '!=', 'super_admin');
                })
                ->with(['role', 'operators'])
                ->paginate(20);
                break;
        }
        
        // Déterminer le thème selon l'utilisateur connecté
        $theme = $user->isTimweOoredooUser() ? 'ooredoo' : 'club_privileges';
        $isOoredoo = $theme === 'ooredoo';
        
        return view('admin.users.index', compact('users', 'theme', 'isOoredoo'));
    }

    /**
     * Afficher le formulaire de création d'utilisateur
     */
    public function create()
    {
        $user = auth()->user();
        $subStoreService = app(\App\Services\SubStoreService::class);
        
        // Les rôles disponibles selon le niveau de l'utilisateur connecté
        if ($user->isSuperAdmin()) {
            $roles = Role::active()->get();
            $operators = $subStoreService->getClassicOperators();
            $subStores = $subStoreService->getSubStores();
        } elseif ($user->isAdminOperator()) {
            $roles = Role::where('name', 'collaborator')->active()->get();
            $operators = $user->operators->pluck('operator_name', 'operator_name')->toArray();
            $subStores = [];
        } elseif ($user->isAdminSubStore() || $user->canInviteCollaborators()) {
            $roles = Role::where('name', 'collaborator')->active()->get();
            $operators = [];
            $subStores = $user->operators->pluck('operator_name', 'operator_name')->toArray();
        } else {
            $roles = Role::where('name', 'collaborator')->active()->get();
            $operators = $user->operators->pluck('operator_name', 'operator_name')->toArray();
            $subStores = [];
        }
        
        // Déterminer le thème selon l'utilisateur connecté
        $theme = $user->isTimweOoredooUser() ? 'ooredoo' : 'club_privileges';
        $isOoredoo = $theme === 'ooredoo';
        
        return view('admin.users.create', compact('roles', 'operators', 'subStores', 'theme', 'isOoredoo'));
    }

    /**
     * Créer un nouvel utilisateur
     */
    public function store(Request $request)
    {
        $user = auth()->user();
        
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'role_id' => 'required|exists:roles,id',
            'phone' => 'nullable|string|max:20',
            'type_selection' => 'required|in:operator,substore',
            'operator_names' => 'nullable|array',
            'operator_names.*' => 'string|max:255',
            'operator_name' => 'nullable|string|max:255',
            'substore_names' => 'nullable|array',
            'substore_names.*' => 'string|max:255',
            'substore_name' => 'nullable|string|max:255',
            'campaign_access' => 'nullable|array',
            'campaign_access.*' => 'string|max:255',
        ], [
            'first_name.required' => 'Le prénom est obligatoire.',
            'last_name.required' => 'Le nom est obligatoire.',
            'email.required' => 'L\'adresse e-mail est obligatoire.',
            'email.unique' => 'Cette adresse e-mail est déjà utilisée.',
            'password.required' => 'Le mot de passe est obligatoire.',
            'password.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
            'password.confirmed' => 'La confirmation du mot de passe ne correspond pas.',
            'role_id.required' => 'Le rôle est obligatoire.',
            'role_id.exists' => 'Le rôle sélectionné n\'existe pas.',
            'type_selection.required' => 'Le type est obligatoire.',
            'type_selection.in' => 'Le type doit être opérateur ou sub-store.',
        ]);

        // Vérifier les permissions
        $role = Role::find($request->role_id);
        if (!$user->isSuperAdmin() && $role->name !== 'collaborator') {
            return back()->with('error', 'Vous ne pouvez créer que des collaborateurs.');
        }

        // Déterminer les noms des opérateurs/sub-stores (multi-select pour SuperAdmin, single pour les autres)
        $operatorNamesList = [];
        if ($request->type_selection === 'operator') {
            $operatorNamesList = $request->input('operator_names', []);
            if (empty($operatorNamesList) && $request->operator_name) {
                $operatorNamesList = [$request->operator_name];
            }
        } else {
            $operatorNamesList = $request->input('substore_names', []);
            if (empty($operatorNamesList) && $request->substore_name) {
                $operatorNamesList = [$request->substore_name];
            }
        }

        if (empty($operatorNamesList)) {
            return back()->with('error', 'Veuillez sélectionner au moins un opérateur ou sub-store.');
        }

        DB::beginTransaction();
        try {
            // Créer l'utilisateur
            $newUser = User::create([
                'name' => $request->first_name . ' ' . $request->last_name,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role_id' => $request->role_id,
                'phone' => $request->phone,
                'status' => 'active',
                'created_by' => $user->id
            ]);

            // Assigner les opérateurs/sub-stores (multi-select)
            foreach ($operatorNamesList as $index => $opName) {
                UserOperator::create([
                    'user_id' => $newUser->id,
                    'operator_name' => $opName,
                    'is_primary' => $index === 0,
                    'assigned_by' => $user->id
                ]);
            }

            // Appliquer les restrictions de campagne si spécifiées
            $campaignAccess = $request->input('campaign_access', []);
            if (!empty($campaignAccess)) {
                $newUser->update([
                    'pluxee_campaign_access' => json_encode(array_values($campaignAccess))
                ]);
            }

            DB::commit();
            
            $campaignInfo = !empty($campaignAccess) ? ' (Campagnes: ' . implode(', ', $campaignAccess) . ')' : '';
            return redirect()->route('admin.users.index')
                           ->with('success', "Utilisateur créé avec succès.{$campaignInfo}");

        } catch (\Exception $e) {
            DB::rollback();
            return back()->with('error', 'Erreur lors de la création de l\'utilisateur: ' . $e->getMessage());
        }
    }

    /**
     * Afficher le formulaire d'édition
     */
    public function edit(User $user)
    {
        $currentUser = auth()->user();
        
        // Vérifier les permissions
        if (!$currentUser->isSuperAdmin() && !$this->canManageUser($currentUser, $user)) {
            abort(403, 'Vous n\'avez pas le droit de modifier cet utilisateur.');
        }
        
        if ($currentUser->isSuperAdmin()) {
            $roles = Role::active()->get();
        } else {
            $roles = Role::where('name', 'collaborator')->active()->get();
        }
        
        // Determine if this user belongs to a sub-store
        $subStoreService = app(\App\Services\SubStoreService::class);
        $primaryOp = $user->primaryOperator();
        $isSubStoreUser = $primaryOp ? $subStoreService->isSubStoreOperator($primaryOp->operator_name) : false;
        
        // Show sub-stores list for sub-store users, payment operators for operator users
        if ($currentUser->isSuperAdmin()) {
            if ($isSubStoreUser) {
                $operators = DB::table('stores')
                    ->where('store_active', 1)
                    ->where(function($q) {
                        $q->where('is_sub_store', 1)->orWhere('store_id', 54);
                    })
                    ->orderBy('store_name')
                    ->pluck('store_name', 'store_name')
                    ->toArray();
            } else {
                $operators = $this->getAllOperators();
            }
        } else {
            $operators = $currentUser->operators->pluck('operator_name', 'operator_name');
        }
        
        // Déterminer le thème selon l'utilisateur connecté
        $theme = $currentUser->isTimweOoredooUser() ? 'ooredoo' : 'club_privileges';
        $isOoredoo = $theme === 'ooredoo';
        
        return view('admin.users.edit', compact('user', 'roles', 'operators', 'theme', 'isOoredoo', 'isSubStoreUser'));
    }

    /**
     * Mettre à jour un utilisateur
     */
    public function update(Request $request, User $user)
    {
        $currentUser = auth()->user();
        
        // Vérifier les permissions
        if (!$currentUser->isSuperAdmin() && !$this->canManageUser($currentUser, $user)) {
            abort(403, 'Vous n\'avez pas le droit de modifier cet utilisateur.');
        }

        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:8|confirmed',
            'role_id' => 'required|exists:roles,id',
            'phone' => 'nullable|string|max:20',
            'status' => 'required|in:active,inactive,pending,suspended',
            'operators' => 'required|array|min:1',
            'operators.*' => 'required|string'
        ]);

        DB::beginTransaction();
        try {
            // Mettre à jour les informations de base
            $userData = [
                'name' => $request->first_name . ' ' . $request->last_name,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'role_id' => $request->role_id,
                'phone' => $request->phone,
                'status' => $request->status
            ];

            if ($request->filled('password')) {
                $userData['password'] = Hash::make($request->password);
                $userData['password_changed_at'] = now();
            }

            $user->update($userData);

            // Mettre à jour les opérateurs
            $user->operators()->delete();
            foreach ($request->operators as $index => $operatorName) {
                UserOperator::create([
                    'user_id' => $user->id,
                    'operator_name' => $operatorName,
                    'is_primary' => $index === 0,
                    'assigned_by' => $currentUser->id
                ]);
            }

            DB::commit();
            return redirect()->route('admin.users.index')
                           ->with('success', 'Utilisateur mis à jour avec succès.');

        } catch (\Exception $e) {
            DB::rollback();
            return back()->with('error', 'Erreur lors de la mise à jour: ' . $e->getMessage());
        }
    }

    /**
     * Supprimer un utilisateur
     */
    public function destroy(User $user)
    {
        $currentUser = auth()->user();
        
        // Vérifier les permissions
        if (!$currentUser->isSuperAdmin()) {
            abort(403, 'Seuls les super administrateurs peuvent supprimer des utilisateurs.');
        }

        if ($user->id === $currentUser->id) {
            return back()->with('error', 'Vous ne pouvez pas supprimer votre propre compte.');
        }

        if ($user->isSuperAdmin()) {
            return back()->with('error', 'Vous ne pouvez pas supprimer un super administrateur.');
        }

        try {
            $user->delete();
            return redirect()->route('admin.users.index')
                           ->with('success', 'Utilisateur supprimé avec succès.');
        } catch (\Exception $e) {
            return back()->with('error', 'Erreur lors de la suppression: ' . $e->getMessage());
        }
    }

    /**
     * Vérifier si l'utilisateur peut gérer un autre utilisateur
     */
    private function canManageUser(User $manager, User $target): bool
    {
        if ($manager->isSuperAdmin()) {
            return true;
        }

        if ($manager->isAdmin()) {
            // Un admin peut gérer les utilisateurs des mêmes opérateurs
            $managerOperators = $manager->operators->pluck('operator_name');
            $targetOperators = $target->operators->pluck('operator_name');
            
            return $managerOperators->intersect($targetOperators)->isNotEmpty();
        }

        return false;
    }

    /**
     * Suspendre un utilisateur
     */
    public function suspend(User $user)
    {
        if (!auth()->user()->isSuperAdmin()) {
            abort(403, 'Seuls les super administrateurs peuvent suspendre des comptes.');
        }

        $user->update(['status' => 'suspended']);

        // Invalider toutes les sessions de l'utilisateur
        DB::table('sessions')->where('user_id', $user->id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Utilisateur suspendu avec succès'
        ]);
    }

    /**
     * Réactiver un utilisateur suspendu
     */
    public function unsuspend(User $user)
    {
        if (!auth()->user()->isSuperAdmin()) {
            abort(403, 'Seuls les super administrateurs peuvent réactiver des comptes.');
        }

        $user->update(['status' => 'active']);

        return response()->json([
            'success' => true,
            'message' => 'Utilisateur réactivé avec succès'
        ]);
    }

    /**
     * Envoyer un lien de réinitialisation de mot de passe pour un utilisateur (Super Admin uniquement)
     */
    public function resetPassword(User $user)
    {
        // Vérifier que l'utilisateur connecté est Super Admin
        if (!auth()->user()->isSuperAdmin()) {
            return back()->with('error', 'Accès refusé. Cette action est réservée aux Super Administrateurs.');
        }

        // Vérifier que l'utilisateur cible existe et est actif
        if ($user->status !== 'active') {
            return back()->with('error', 'Impossible d\'envoyer un lien de réinitialisation à un compte inactif.');
        }

        try {
            // Créer la demande de réinitialisation
            $resetRequest = PasswordResetRequest::createForPasswordReset($user->email);
            
            // Envoyer l'email
            $resetUrl = route('password.reset.form', $resetRequest->token);
            Mail::to($user->email)->send(new PasswordResetMail($user, $resetUrl));
            
            Log::info("=== RÉINITIALISATION ADMIN ===");
            Log::info("Super Admin: " . auth()->user()->email);
            Log::info("Cible: " . $user->email);
            Log::info("Lien envoyé: " . $resetUrl);
            
            return back()->with('success', "✅ Lien de réinitialisation envoyé avec succès à {$user->email}. L'utilisateur recevra un email avec les instructions pour créer un nouveau mot de passe.");
            
        } catch (\Exception $e) {
            Log::error("Erreur lors de l'envoi de la réinitialisation admin: " . $e->getMessage());
            Log::error("Super Admin: " . auth()->user()->email);
            Log::error("Cible: " . $user->email);
            
            return back()->with('error', 'Erreur lors de l\'envoi de l\'email de réinitialisation. Veuillez réessayer ou contacter le support technique.');
        }
    }

    /**
     * Récupérer tous les opérateurs disponibles
     */
    private function getAllOperators(): array
    {
        return DB::table('country_payments_methods')
                 ->distinct()
                 ->pluck('country_payments_methods_name', 'country_payments_methods_name')
                 ->toArray();
    }

    /**
     * Page de gestion des permissions campagnes
     */
    public function permissions()
    {
        $currentUser = auth()->user();
        
        if (!$currentUser->isSuperAdmin() && !$currentUser->canInviteCollaborators()) {
            abort(403, 'Acces refuse.');
        }

        // Get all users with their operators and campaign access
        $query = User::with(['role', 'operators']);
        
        if (!$currentUser->isSuperAdmin()) {
            // Non-super admins see only users from their operators
            $myOperators = $currentUser->operators->pluck('operator_name');
            $query->whereHas('operators', function($q) use ($myOperators) {
                $q->whereIn('operator_name', $myOperators);
            });
        }
        
        $users = $query->orderBy('name')->get()->map(function($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role ? $user->role->name : 'unknown',
                'role_display' => $user->role ? $user->role->display_name : 'Inconnu',
                'operator' => $user->primaryOperator() ? $user->primaryOperator()->operator_name : '-',
                'campaigns' => $user->getAllowedCampaigns(),
                'has_restriction' => $user->hasCampaignRestriction(),
                'can_invite' => $user->canInviteCollaborators(),
                'status' => $user->status ?? 'active',
            ];
        });

        $theme = $currentUser->isTimweOoredooUser() ? 'ooredoo' : 'club_privileges';
        $isOoredoo = $theme === 'ooredoo';
        
        return view('admin.users.permissions', compact('users', 'theme', 'isOoredoo'));
    }

    /**
     * API: Update campaign access for a user
     */
    public function updateCampaignAccess(Request $request, User $user)
    {
        $currentUser = auth()->user();
        
        if (!$currentUser->isSuperAdmin() && !$currentUser->canInviteCollaborators()) {
            return response()->json(['success' => false, 'error' => 'Acces refuse'], 403);
        }

        // Cannot modify super admins
        if ($user->isSuperAdmin()) {
            return response()->json(['success' => false, 'error' => 'Impossible de modifier un super administrateur'], 403);
        }

        $campaigns = $request->input('campaigns', []);
        $oldValue = $user->pluxee_campaign_access;
        
        if (empty($campaigns)) {
            $user->update(['pluxee_campaign_access' => null]);
            $action = 'grant_full_access';
            $details = "Acces complet accorde a {$user->name} ({$user->email})";
        } else {
            $user->update(['pluxee_campaign_access' => json_encode(array_values($campaigns))]);
            $action = 'restrict_campaigns';
            $details = "Campagnes restreintes pour {$user->name} ({$user->email}): " . implode(', ', $campaigns);
        }

        // Log the permission change
        AuditLogController::logPermissionChange(
            $user->id,
            $user->name,
            $user->email,
            $currentUser->id,
            $currentUser->name,
            $currentUser->email,
            $action,
            $oldValue,
            $user->pluxee_campaign_access,
            $details,
            $request->ip()
        );

        Log::info("Campaign access updated for user {$user->id} ({$user->name}) by {$currentUser->name}: " . json_encode($campaigns));

        return response()->json([
            'success' => true,
            'user_id' => $user->id,
            'campaigns' => $user->getAllowedCampaigns(),
            'has_restriction' => $user->hasCampaignRestriction(),
            'can_invite' => $user->canInviteCollaborators(),
        ]);
    }

    /**
     * API: Get all available campaigns for a store
     */
    public function getAvailableCampaigns(Request $request)
    {
        $storeName = $request->input('store_name', '');
        
        if (empty($storeName)) {
            // Get all campaigns from all sub-stores
            $campaigns = DB::table('carte_recharge')
                ->join('stores', 'stores.store_id', '=', 'carte_recharge.stores')
                ->where('stores.is_sub_store', 1)
                ->select('carte_recharge.campain_name', 'stores.store_name', 
                         DB::raw('COUNT(*) as total_batches'), 
                         DB::raw('SUM(carte_recharge.card_generated_number) as total_cards'))
                ->groupBy('carte_recharge.campain_name', 'stores.store_name')
                ->orderBy('stores.store_name')
                ->orderBy('carte_recharge.campain_name')
                ->get();
        } else {
            $campaigns = DB::table('carte_recharge')
                ->join('stores', 'stores.store_id', '=', 'carte_recharge.stores')
                ->where('stores.store_name', $storeName)
                ->select('carte_recharge.campain_name', 'stores.store_name',
                         DB::raw('COUNT(*) as total_batches'), 
                         DB::raw('SUM(carte_recharge.card_generated_number) as total_cards'))
                ->groupBy('carte_recharge.campain_name', 'stores.store_name')
                ->orderBy('carte_recharge.campain_name')
                ->get();
        }

        return response()->json(['campaigns' => $campaigns]);
    }
}