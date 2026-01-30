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
            case 'admin_sub_store':
            case 'collaborator':
            default:
                // Tous les autres : SEULEMENT les utilisateurs qu'ils ont créés + eux-mêmes
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
        
        // Les rôles disponibles selon le niveau de l'utilisateur connecté
        if ($user->isSuperAdmin()) {
            $roles = Role::active()->get();
            $operators = $this->getAllOperators();
        } else {
            // Un admin ne peut créer que des collaborateurs
            $roles = Role::where('name', 'collaborator')->active()->get();
            $operators = $user->operators->pluck('operator_name', 'operator_name');
        }
        
        // Déterminer le thème selon l'utilisateur connecté
        $theme = $user->isTimweOoredooUser() ? 'ooredoo' : 'club_privileges';
        $isOoredoo = $theme === 'ooredoo';
        
        return view('admin.users.create', compact('roles', 'operators', 'theme', 'isOoredoo'));
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
            'operators' => 'required|array|min:1',
            'operators.*' => 'required|string'
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
            'operators.required' => 'Au moins un opérateur doit être sélectionné.',
        ]);

        // Vérifier les permissions
        $role = Role::find($request->role_id);
        if (!$user->isSuperAdmin() && $role->name !== 'collaborator') {
            return back()->with('error', 'Vous ne pouvez créer que des collaborateurs.');
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

            // Assigner les opérateurs
            foreach ($request->operators as $index => $operatorName) {
                UserOperator::create([
                    'user_id' => $newUser->id,
                    'operator_name' => $operatorName,
                    'is_primary' => $index === 0, // Le premier est principal
                    'assigned_by' => $user->id
                ]);
            }

            DB::commit();
            return redirect()->route('admin.users.index')
                           ->with('success', 'Utilisateur créé avec succès.');

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
            $operators = $this->getAllOperators();
        } else {
            $roles = Role::where('name', 'collaborator')->active()->get();
            $operators = $currentUser->operators->pluck('operator_name', 'operator_name');
        }
        
        // Déterminer le thème selon l'utilisateur connecté
        $theme = $currentUser->isTimweOoredooUser() ? 'ooredoo' : 'club_privileges';
        $isOoredoo = $theme === 'ooredoo';
        
        return view('admin.users.edit', compact('user', 'roles', 'operators', 'theme', 'isOoredoo'));
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
}