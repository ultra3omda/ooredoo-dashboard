<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Invitation;
use App\Models\Role;
use App\Models\User;
use App\Models\UserOperator;
use App\Models\UserOtpCode;
use App\Models\PasswordResetRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Mail\InvitationMail;

class InvitationController extends Controller
{
    /**
     * Afficher la liste des invitations
     */
    public function index()
    {
        $user = auth()->user();
        
        // Logique selon les types d'utilisateurs
        if ($user->isSuperAdmin()) {
            // Super Admin voit TOUTES les invitations
            $invitations = Invitation::with(['invitedBy', 'role'])
                ->orderBy('created_at', 'desc')
                ->paginate(20);
        } else {
            // Tous les autres : SEULEMENT leurs propres invitations
            $invitations = Invitation::where('invited_by', $user->id)
                ->with(['invitedBy', 'role'])
                ->orderBy('created_at', 'desc')
                ->paginate(20);
        }
        
        // Déterminer le thème selon l'utilisateur connecté
        $theme = $user->isTimweOoredooUser() ? 'ooredoo' : 'club_privileges';
        $isOoredoo = $theme === 'ooredoo';
        
        return view('admin.invitations.index', compact('invitations', 'theme', 'isOoredoo'));
    }

    /**
     * Afficher le formulaire de création d'invitation
     */
    public function create()
    {
        $user = auth()->user();
        
        // Vérifier les permissions d'accès
        if (!$user->isSuperAdmin() && !$user->isAdminOperator() && !$user->isAdminSubStore()) {
            abort(403, 'Vous n\'avez pas les permissions pour inviter des utilisateurs.');
        }
        
        // Les rôles disponibles selon le niveau de l'utilisateur connecté
        if ($user->isSuperAdmin()) {
            // Super admin peut inviter admin ou collaborateur
            $roles = Role::whereIn('name', ['admin', 'collaborator'])->active()->get();
            $operators = $this->getOperators();
            $subStores = $this->getSubStores();
        } elseif ($user->isAdminOperator()) {
            // Admin opérateur ne peut inviter que des collaborateurs pour son opérateur
            $roles = Role::where('name', 'collaborator')->active()->get();
            $operators = $user->operators->pluck('operator_name', 'operator_name');
            $subStores = [];
        } elseif ($user->isAdminSubStore()) {
            // Admin sub-store ne peut inviter que des collaborateurs pour son sub-store
            $roles = Role::where('name', 'collaborator')->active()->get();
            $operators = [];
            $subStores = $user->operators->pluck('operator_name', 'operator_name');
        }
        
        // Déterminer le thème selon l'utilisateur connecté
        $theme = $user->isTimweOoredooUser() ? 'ooredoo' : 'club_privileges';
        $isOoredoo = $theme === 'ooredoo';
        
        return view('admin.invitations.create', compact('roles', 'operators', 'subStores', 'theme', 'isOoredoo'));
    }

    /**
     * Créer une nouvelle invitation
     */
    public function store(Request $request)
    {
        $user = auth()->user();
        
        // Vérifier les permissions d'accès
        if (!$user->isSuperAdmin() && !$user->isAdminOperator() && !$user->isAdminSubStore()) {
            abort(403, 'Vous n\'avez pas les permissions pour inviter des utilisateurs.');
        }
        
        $request->validate([
            'email' => 'required|email|unique:users,email|unique:invitations,email',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'role_id' => 'required|exists:roles,id',
            'type_selection' => 'required|in:operator,substore',
            'operator_name' => 'required_if:type_selection,operator|string|nullable',
            'substore_name' => 'required_if:type_selection,substore|string|nullable',
            'message' => 'nullable|string|max:500'
        ], [
            'email.required' => 'L\'adresse e-mail est obligatoire.',
            'email.email' => 'L\'adresse e-mail doit être valide.',
            'email.unique' => 'Cette adresse e-mail est déjà utilisée ou a déjà été invitée.',
            'first_name.required' => 'Le prénom est obligatoire.',
            'last_name.required' => 'Le nom est obligatoire.',
            'role_id.required' => 'Le rôle est obligatoire.',
            'type_selection.required' => 'Le type est obligatoire.',
            'type_selection.in' => 'Le type doit être opérateur ou sub-store.',
            'operator_name.required_if' => 'L\'opérateur est obligatoire quand le type est opérateur.',
            'substore_name.required_if' => 'Le sub-store est obligatoire quand le type est sub-store.'
        ]);

        // Vérifier les permissions selon le type d'utilisateur
        $role = Role::find($request->role_id);
        
        // Déterminer le nom de l'opérateur/sub-store selon le type sélectionné
        $operatorName = $request->type_selection === 'operator' ? $request->operator_name : $request->substore_name;
        
        if ($user->isSuperAdmin()) {
            // Super admin peut inviter admin ou collaborateur
            if (!in_array($role->name, ['admin', 'collaborator'])) {
                return back()->with('error', 'Vous ne pouvez inviter que des administrateurs ou collaborateurs.');
            }
        } elseif ($user->isAdminOperator() || $user->isAdminSubStore()) {
            // Admin opérateur/sub-store ne peut inviter que des collaborateurs
            if ($role->name !== 'collaborator') {
                return back()->with('error', 'Vous ne pouvez inviter que des collaborateurs.');
            }
            
            // Vérifier que l'opérateur/sub-store est dans la liste autorisée
            $userOperators = $user->operators->pluck('operator_name');
            if (!$userOperators->contains($operatorName)) {
                return back()->with('error', 'Vous ne pouvez pas inviter pour cet opérateur/sub-store.');
            }
        }

        try {
            // Créer l'invitation (SANS créer l'utilisateur immédiatement)
            $invitation = Invitation::create([
                'email' => $request->email,
                'token' => Str::random(64),
                'invited_by' => $user->id,
                'role_id' => $request->role_id,
                'operator_name' => $operatorName,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'status' => 'pending', // Invitation en attente
                'expires_at' => now()->addDays(7), // Expiration dans 7 jours
                'additional_data' => [
                    'message' => $request->message,
                    'invited_by_name' => $user->name,
                    'type_selection' => $request->type_selection
                ]
            ]);

            // Générer l'URL d'invitation
            $invitationUrl = route('auth.invitation', $invitation->token);
            
            try {
                // Envoyer l'email avec le lien d'invitation
                Mail::to($invitation->email)->send(new InvitationMail($invitation, $invitationUrl));
                
                Log::info("=== INVITATION ENVOYÉE ===");
                Log::info("Email: {$invitation->email}");
                Log::info("Invité par: {$user->name}");
                Log::info("Lien d'invitation: {$invitationUrl}");
                
                return redirect()->route('admin.invitations.index')
                               ->with('success', "Invitation envoyée avec succès à {$invitation->email}.");
                               
            } catch (\Exception $e) {
                Log::error("Erreur envoi email invitation: " . $e->getMessage());
                
                return redirect()->route('admin.invitations.index')
                               ->with('success', "Invitation créée. Erreur d'envoi email - Lien pour test: {$invitationUrl}");
            }

        } catch (\Exception $e) {
            return back()->with('error', 'Erreur lors de l\'envoi de l\'invitation: ' . $e->getMessage());
        }
    }

    /**
     * Supprimer une invitation
     */
    public function destroy(Invitation $invitation)
    {
        $user = auth()->user();
        
        // Vérifier les permissions
        if (!$user->isSuperAdmin() && $invitation->invited_by !== $user->id) {
            abort(403, 'Vous ne pouvez supprimer que vos propres invitations.');
        }

        if ($invitation->status === 'accepted') {
            return back()->with('error', 'Vous ne pouvez pas supprimer une invitation déjà acceptée.');
        }

        try {
            $invitation->delete();
            return redirect()->route('admin.invitations.index')
                           ->with('success', 'Invitation supprimée avec succès.');
        } catch (\Exception $e) {
            return back()->with('error', 'Erreur lors de la suppression: ' . $e->getMessage());
        }
    }

    /**
     * Traiter l'acceptation d'une invitation via OTP
     */
    public function acceptInvitation(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required|digits:6',
            'invitation_token' => 'required|string'
        ]);

        // Vérifier le code OTP
        $otpCode = UserOtpCode::where('email', $request->email)
                             ->where('code', $request->code)
                             ->where('type', 'invitation')
                             ->where('invitation_token', $request->invitation_token)
                             ->valid()
                             ->first();

        if (!$otpCode) {
            return back()->with('error', 'Code de vérification invalide ou expiré.');
        }

        // Vérifier l'invitation
        $invitation = Invitation::where('token', $request->invitation_token)
                                ->where('email', $request->email)
                                ->pending()
                                ->first();

        if (!$invitation) {
            return back()->with('error', 'Invitation invalide ou expirée.');
        }

        DB::beginTransaction();
        try {
            // Vérifier si l'utilisateur existe déjà
            $existingUser = User::where('email', $invitation->email)->first();
            
            if ($existingUser) {
                // L'utilisateur existe déjà, utiliser cet utilisateur
                $user = $existingUser;
                
                // Mettre à jour les informations si nécessaire
                $user->update([
                    'role_id' => $invitation->role_id,
                    'status' => 'active',
                    'is_otp_enabled' => true,
                    'created_by' => $invitation->invited_by,
                    'platform_type' => 'club_privileges'
                ]);
            } else {
                // Créer l'utilisateur
                $user = User::create([
                    'name' => $invitation->first_name . ' ' . $invitation->last_name,
                    'first_name' => $invitation->first_name,
                    'last_name' => $invitation->last_name,
                    'email' => $invitation->email,
                    'password' => Hash::make(Str::random(16)), // Mot de passe temporaire
                    'role_id' => $invitation->role_id,
                    'status' => 'active',
                    'is_otp_enabled' => true, // L'utilisateur invité utilise OTP par défaut
                    'created_by' => $invitation->invited_by,
                    'platform_type' => 'club_privileges'
                ]);
            }

            // Vérifier si l'opérateur est déjà assigné
            $existingOperator = UserOperator::where('user_id', $user->id)
                                          ->where('operator_name', $invitation->operator_name)
                                          ->first();
            
            if (!$existingOperator) {
                // Assigner l'opérateur
                UserOperator::create([
                    'user_id' => $user->id,
                    'operator_name' => $invitation->operator_name,
                    'is_primary' => true,
                    'assigned_by' => $invitation->invited_by
                ]);
            }

            // Marquer l'invitation comme acceptée
            $invitation->accept();

            // Marquer le code OTP comme utilisé
            $otpCode->markAsUsed();

            // Vérifier si l'utilisateur doit changer son mot de passe
            $needsPasswordChange = $user->password_changed_at === null;

            if ($needsPasswordChange) {
                // Créer un token de première connexion pour changer le mot de passe
                $firstLoginRequest = PasswordResetRequest::createForFirstLogin($user->email);
                $passwordChangeUrl = route('password.first-login', $firstLoginRequest->token);
                
                // Stocker l'utilisateur en session pour la connexion après changement de mot de passe
                $request->session()->put('pending_user_id', $user->id);
                
                DB::commit();
                
                Log::info("Invitation acceptée, redirection vers changement de mot de passe pour: " . $user->email);
                
                return redirect($passwordChangeUrl)
                               ->with('success', 'Bienvenue ! Veuillez créer votre mot de passe pour continuer.');
            } else {
                // L'utilisateur a déjà un mot de passe, le connecter directement
                auth()->login($user);
                $request->session()->regenerate();

                // Mettre à jour les informations de dernière connexion
                $user->updateLastLogin($request->ip());

                DB::commit();

                Log::info("Invitation acceptée avec succès pour: " . $user->email);

                // Redirection intelligente selon le rôle et les permissions
                $preferredDashboard = $user->getPreferredDashboard();

                return redirect($preferredDashboard)
                               ->with('success', 'Bienvenue ! Votre compte a été créé avec succès.');
            }

        } catch (\Exception $e) {
            DB::rollback();
            Log::error("Erreur lors de l'acceptation de l'invitation: " . $e->getMessage());
            return back()->with('error', 'Erreur lors de la création du compte: ' . $e->getMessage());
        }
    }

    /**
     * Annuler une invitation (marquer comme cancelled)
     */
    public function cancel(Invitation $invitation)
    {
        $user = auth()->user();
        
        if (!$user->isSuperAdmin() && $invitation->invited_by !== $user->id) {
            abort(403, 'Vous ne pouvez annuler que vos propres invitations.');
        }

        if ($invitation->status !== 'pending') {
            return back()->with('error', 'Cette invitation ne peut plus être annulée.');
        }

        $invitation->cancel();
        return back()->with('success', 'Invitation annulée avec succès.');
    }

    /**
     * Renvoyer une invitation (créer un nouveau token)
     */
    public function resend(Invitation $invitation)
    {
        $user = auth()->user();
        
        if (!$user->isSuperAdmin() && $invitation->invited_by !== $user->id) {
            abort(403, 'Vous ne pouvez renvoyer que vos propres invitations.');
        }

        if ($invitation->status !== 'pending') {
            return back()->with('error', 'Cette invitation ne peut pas être renvoyée.');
        }

        // Générer un nouveau token et prolonger l'expiration
        $invitation->update([
            'token' => Str::random(64),
            'expires_at' => now()->addDays(7)
        ]);

        $invitationUrl = route('auth.invitation', $invitation->token);
        
        try {
            Mail::to($invitation->email)->send(new InvitationMail($invitation, $invitationUrl));
            
            Log::info("=== INVITATION RENVOYÉE ===");
            Log::info("Email: {$invitation->email}");
            Log::info("Nouveau lien: {$invitationUrl}");
            
            return back()->with('success', "Invitation renvoyée avec succès à {$invitation->email}.");
            
        } catch (\Exception $e) {
            Log::error("Erreur renvoi email invitation: " . $e->getMessage());
            
            return back()->with('success', "Invitation mise à jour. Erreur d'envoi email - Nouveau lien: {$invitationUrl}");
        }
    }

    /**
     * Récupérer tous les opérateurs disponibles (opérateurs + sub-stores)
     */
    private function getAllOperators(): array
    {
        $subStoreService = app(\App\Services\SubStoreService::class);
        return $subStoreService->getAllOperators();
    }
    
    /**
     * Récupérer seulement les opérateurs classiques
     */
    private function getOperators(): array
    {
        $subStoreService = app(\App\Services\SubStoreService::class);
        return $subStoreService->getClassicOperators();
    }
    
    /**
     * Récupérer seulement les sub-stores
     */
    private function getSubStores(): array
    {
        $subStoreService = app(\App\Services\SubStoreService::class);
        return $subStoreService->getSubStores();
    }
}