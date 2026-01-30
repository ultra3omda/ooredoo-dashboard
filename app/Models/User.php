<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
        'first_name',
        'last_name',
        'phone',
        'status',
        'last_login_at',
        'last_login_ip',
        'preferences',
        'is_otp_enabled',
        'password_changed_at',
        'created_by',
        'platform_type'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'last_login_at' => 'datetime',
        'preferences' => 'array',
        'is_otp_enabled' => 'boolean',
        'password_changed_at' => 'datetime'
    ];

    // Relations
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function operators(): HasMany
    {
        return $this->hasMany(UserOperator::class);
    }

    public function activeOperators(): HasMany
    {
        return $this->operators()->active();
    }

    public function primaryOperator()
    {
        return $this->operators()->where('is_primary', true)->first();
    }

    public function sentInvitations(): HasMany
    {
        return $this->hasMany(Invitation::class, 'invited_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function createdUsers(): HasMany
    {
        return $this->hasMany(User::class, 'created_by');
    }

    // Méthodes utilitaires
    public function isSuperAdmin(): bool
    {
        return $this->role && $this->role->isSuperAdmin();
    }

    public function isAdmin(): bool
    {
        return $this->role && $this->role->isAdmin();
    }

    public function isCollaborator(): bool
    {
        return $this->role && $this->role->isCollaborator();
    }

    public function isClubPrivilegesUser(): bool
    {
        return $this->platform_type === 'club_privileges';
    }

    public function isTimweOoredooUser(): bool
    {
        return $this->platform_type === 'timwe_ooredoo';
    }

    public function hasPermission(string $permission): bool
    {
        return $this->role && $this->role->hasPermission($permission);
    }

    public function canAccessOperator(string $operatorName): bool
    {
        if ($this->isSuperAdmin()) {
            return true; // Super admin peut accéder à tous les opérateurs
        }

        return $this->activeOperators()
                   ->where('operator_name', $operatorName)
                   ->exists();
    }

    public function getOperatorsList(): array
    {
        if ($this->isSuperAdmin()) {
            // Retourner tous les opérateurs disponibles
            return $this->getAllOperators();
        }

        return $this->activeOperators()
                   ->pluck('operator_name')
                   ->toArray();
    }

    public function getPrimaryOperatorName(): ?string
    {
        $primary = $this->primaryOperator();
        return $primary ? $primary->operator_name : null;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function updateLastLogin(string $ipAddress): void
    {
        $this->update([
            'last_login_at' => now(),
            'last_login_ip' => $ipAddress
        ]);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeWithRole($query, string $roleName)
    {
        return $query->whereHas('role', function($q) use ($roleName) {
            $q->where('name', $roleName);
        });
    }

    public function scopeForOperator($query, string $operatorName)
    {
        return $query->whereHas('operators', function($q) use ($operatorName) {
            $q->where('operator_name', $operatorName)->active();
        });
    }

    /**
     * Vérifier si l'utilisateur peut accéder à un sub-store spécifique
     */
    public function canAccessSubStore(string $subStoreName): bool
    {
        if ($this->isSuperAdmin()) {
            return true; // Super Admin peut accéder à tous les sub-stores
        }

        // Pour les autres rôles, vérifier les permissions selon les opérateurs assignés
        if ($subStoreName === 'ALL') {
            return $this->isSuperAdmin(); // Seul Super Admin peut voir "ALL"
        }

        // Logique d'autorisation basée sur les opérateurs ou autres critères
        // À étendre selon les besoins spécifiques
        return true; // Pour le moment, accès autorisé pour tous les utilisateurs authentifiés
    }

    /**
     * Récupérer les sub-stores accessibles pour cet utilisateur
     */
    public function getAccessibleSubStores(): array
    {
        if ($this->isSuperAdmin()) {
            // Super Admin voit tous les sub-stores
            return ['ALL' => 'Tous les sub-stores'];
        }

        // Logique pour récupérer les sub-stores selon les permissions
        // À implémenter selon les besoins
        return ['ALL' => 'Sub-stores autorisés'];
    }

    /**
     * Déterminer le dashboard préféré selon le rôle et les permissions de l'utilisateur
     */
    public function getPreferredDashboard(): string
    {
        // Dispatching selon le type de plateforme
        if ($this->isTimweOoredooUser()) {
            // Utilisateurs Timwe/Ooredoo : Dashboard avec thème Ooredoo
            return url('/?theme=ooredoo');
        }

        // Utilisateurs Club Privilèges : Logique existante
        // Super Admin : Dashboard principal avec vue globale
        if ($this->isSuperAdmin()) {
            return url('/?theme=club_privileges');
        }

        // Admin : Vérifier si orienté sub-stores ou dashboard principal
        if ($this->isAdmin()) {
            $primaryOperator = $this->primaryOperator();
            
            // Si l'admin est orienté sub-stores, rediriger vers sub-stores dashboard
            if ($primaryOperator && in_array($primaryOperator->operator_name, ['Sub-Stores', 'Retail', 'Partnership', 'Sofrecom'])) {
                return url('/sub-stores/?theme=club_privileges');
            }
            
            // Sinon, dashboard principal avec vue filtrée par opérateur
            return url('/?theme=club_privileges');
        }

        // Collaborator : Selon les permissions et le contexte
        if ($this->isCollaborator()) {
            // Si l'utilisateur a accès aux sub-stores uniquement
            $primaryOperator = $this->primaryOperator();
            
            // Si l'opérateur principal est lié aux sub-stores, rediriger vers sub-stores dashboard
            if ($primaryOperator && in_array($primaryOperator->operator_name, ['Sub-Stores', 'Retail', 'Partnership', 'Sofrecom'])) {
                return url('/sub-stores/?theme=club_privileges');
            }
            
            // Sinon, dashboard principal
            return url('/?theme=club_privileges');
        }

        // Par défaut, dashboard principal Club Privilèges
        return url('/?theme=club_privileges');
    }

    /**
     * Vérifier si l'utilisateur est principalement orienté sub-stores
     */
    public function isPrimarySubStoreUser(): bool
    {
        if ($this->isSuperAdmin()) {
            return false; // Super Admin a accès à tout
        }

        $primaryOperator = $this->primaryOperator();
        
        if (!$primaryOperator) {
            return false;
        }

        // Utiliser le service centralisé pour vérifier si c'est un sub-store
        $subStoreService = app(\App\Services\SubStoreService::class);
        return $subStoreService->isSubStoreOperator($primaryOperator->operator_name);
    }

    /**
     * Définir les 5 types d'utilisateurs selon les spécifications :
     * 1. Super Admin Club Privilèges (all access)
     * 2. Admin Club Privilèges (peut voir tous opérateurs et sub-stores)
     * 3. Admin Opérateur (ne voit que son opérateur)
     * 4. Admin Sub Store (ne voit que son sub-store)
     * 5. Collaborateur (selon son contexte)
     */

    /**
     * Type 1: Super Admin Club Privilèges - Accès total
     */
    public function isSuperAdminClubPrivileges(): bool
    {
        return $this->isSuperAdmin() && $this->isClubPrivilegesUser();
    }

    /**
     * Type 2: Admin Club Privilèges - Voit tous opérateurs et sub-stores
     */
    public function isAdminClubPrivileges(): bool
    {
        return $this->isAdmin() && $this->isClubPrivilegesUser();
    }

    /**
     * Type 3: Admin Opérateur - Ne voit que son opérateur
     */
    public function isAdminOperator(): bool
    {
        if (!$this->isAdmin()) return false;
        
        // Admin avec un opérateur spécifique (pas sub-store)
        $primaryOperator = $this->primaryOperator();
        if (!$primaryOperator) return false;
        
        // Liste des opérateurs "classiques" (non sub-store)
        $operatorTypes = [
            'S\'abonner via Timwe', 'S\'abonner via Orange', 'S\'abonner via TT',
            'Timwe', 'Ooredoo', 'MTN', 'Orange', 'Moov', 'Wave', 'PayPal',
            'Visa', 'Mastercard', 'Mobile Money', 'Bank Transfer',
            'Paiement par carte bancaire', 'Carte cadeaux'
        ];
        
        return in_array($primaryOperator->operator_name, $operatorTypes);
    }

    /**
     * Type 4: Admin Sub Store - Ne voit que son sub-store
     */
    public function isAdminSubStore(): bool
    {
        if (!$this->isAdmin()) return false;
        
        // Admin avec un opérateur de type "sub-store"
        $primaryOperator = $this->primaryOperator();
        if (!$primaryOperator) return false;
        
        // Utiliser le service centralisé pour vérifier si c'est un sub-store
        $subStoreService = app(\App\Services\SubStoreService::class);
        return $subStoreService->isSubStoreOperator($primaryOperator->operator_name);
    }

    /**
     * Type 5: Collaborateur - Selon son contexte (opérateur ou sub-store)
     */
    public function isCollaboratorWithContext(): bool
    {
        return $this->isCollaborator();
    }

    /**
     * Vérifier si l'utilisateur est un utilisateur sub-stores
     * Inclut les admins sub-store ET les collaborateurs sub-store
     */
    public function isSubStoreUser(): bool
    {
        // Admin sub-store : toujours considéré comme utilisateur sub-store
        if ($this->isAdminSubStore()) {
            return true;
        }
        
        // Collaborateur sub-store : vérifier l'opérateur principal
        if ($this->isCollaborator()) {
            $primaryOperator = $this->primaryOperator();
            if (!$primaryOperator) return false;
            
            // Utiliser le service centralisé pour vérifier si c'est un sub-store
            $subStoreService = app(\App\Services\SubStoreService::class);
            return $subStoreService->isSubStoreOperator($primaryOperator->operator_name);
        }
        
        return false;
    }

    /**
     * Obtenir le type d'utilisateur (pour affichage et logique)
     * IMPORTANT: L'ordre des tests est crucial !
     */
    public function getUserType(): string
    {
        if ($this->isSuperAdminClubPrivileges()) {
            return 'super_admin_club_privileges';
        }
        
        // Tester les types spécifiques d'admin AVANT le type général
        if ($this->isAdminOperator()) {
            return 'admin_operator';
        }
        
        if ($this->isAdminSubStore()) {
            return 'admin_sub_store';
        }
        
        // Admin Club Privilèges générique (pour les admins sans opérateur spécifique)
        if ($this->isAdminClubPrivileges()) {
            return 'admin_club_privileges';
        }
        
        if ($this->isCollaboratorWithContext()) {
            return 'collaborator';
        }
        
        return 'unknown';
    }

    /**
     * Obtenir le nom d'affichage du type d'utilisateur
     */
    public function getUserTypeLabel(): string
    {
        switch ($this->getUserType()) {
            case 'super_admin_club_privileges':
                return 'Super Admin Club Privilèges';
            case 'admin_club_privileges':
                return 'Admin Club Privilèges';
            case 'admin_operator':
                $operator = $this->getPrimaryOperatorName();
                return "Admin {$operator}";
            case 'admin_sub_store':
                $subStore = $this->getPrimaryOperatorName();
                return "Admin {$subStore}";
            case 'collaborator':
                $context = $this->getPrimaryOperatorName();
                return "Collaborateur {$context}";
            default:
                return 'Utilisateur';
        }
    }

    /**
     * Vérifier si l'admin peut accéder aux Sub-Stores
     * Selon la nouvelle logique des 5 types
     */
    public function canAccessSubStoresDashboard(): bool
    {
        switch ($this->getUserType()) {
            case 'super_admin_club_privileges':
                return true; // Accès total
                
            case 'admin_club_privileges':
                return true; // Peut voir tous opérateurs et sub-stores
                
            case 'admin_sub_store':
                return true; // Spécifiquement pour sub-stores
                
            case 'collaborator':
                // Collaborateur sub-store uniquement
                return $this->isPrimarySubStoreUser();
                
            default:
                return false; // Admin opérateur et autres : pas d'accès sub-stores
        }
    }

    /**
     * Vérifier si l'utilisateur peut accéder au Dashboard Opérateurs
     * Admin sub-store ne devrait voir QUE les sub-stores
     */
    public function canAccessOperatorsDashboard(): bool
    {
        switch ($this->getUserType()) {
            case 'super_admin_club_privileges':
                return true; // Accès total
                
            case 'admin_operator':
                return true; // Admin opérateur peut voir son opérateur
                
            case 'collaborator':
                // Collaborateur opérateur uniquement (pas sub-store)
                return !$this->isPrimarySubStoreUser();
                
            case 'admin_sub_store':
                return false; // Admin sub-store ne voit QUE les sub-stores
                
            case 'admin_club_privileges':
                return false; // Admin Club Privilèges ne voit QUE les sub-stores
                
            default:
                return false;
        }
    }

    /**
     * Vérifier si l'utilisateur peut accéder à la Configuration Eklektik
     * Seuls les Super Admin peuvent accéder à la configuration Eklektik
     */
    public function canAccessEklektikConfig(): bool
    {
        return $this->isSuperAdmin();
    }

    /**
     * Vérifier si l'utilisateur peut voir la rubrique Eklektik dans le dashboard
     * Seuls les Super Admin peuvent voir la rubrique Eklektik
     */
    public function canViewEklektikSection(): bool
    {
        return $this->isSuperAdmin();
    }

    /**
     * Vérifier si l'utilisateur peut voir la rubrique Timwe dans le dashboard
     * Seuls les Super Admin peuvent voir la rubrique Timwe
     */
    public function canViewTimweSection(): bool
    {
        return $this->isSuperAdmin();
    }

    /**
     * Vérifier si l'utilisateur peut inviter des collaborateurs
     * Seuls les admins (tous types) peuvent inviter des collaborateurs
     */
    public function canInviteCollaborators(): bool
    {
        return $this->isAdmin() || $this->isSuperAdmin();
    }

    // Méthodes privées
    private function getAllOperators(): array
    {
        // Cette méthode devrait récupérer tous les opérateurs disponibles
        // depuis la base de données (table country_payments_methods par exemple)
        return \DB::table('country_payments_methods')
                  ->distinct()
                  ->pluck('country_payments_methods_name')
                  ->toArray();
    }

}