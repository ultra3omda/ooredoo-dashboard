@php
    $isOoredoo = isset($isOoredoo) ? $isOoredoo : false;
    $theme = isset($theme) ? $theme : 'club_privileges';
@endphp

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier l'Utilisateur - {{ $isOoredoo ? 'Ooredoo' : 'Club Privilèges' }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        :root {
            @if($isOoredoo)
                --brand-primary: #E30613;
                --brand-secondary: #B91C1C;
                --brand-accent: #FBBF24;
                --brand-dark: #1f2937;
                --bg: #f8fafc;
                --card: #ffffff;
                --muted: #64748b;
                --success: #10b981;
                --warning: #f59e0b;
                --danger: #ef4444;
                --accent: #3b82f6;
                --border: #e2e8f0;
                /* Backward compatibility */
                --brand-red: var(--brand-primary);
            @else
                --brand-primary: #6B46C1;
                --brand-secondary: #8B5CF6;
                --brand-accent: #F59E0B;
                --brand-dark: #1f2937;
                --bg: #f8fafc;
                --card: #ffffff;
                --muted: #64748b;
                --success: #10b981;
                --warning: #f59e0b;
                --danger: #ef4444;
                --accent: #3b82f6;
                --border: #e2e8f0;
                /* Backward compatibility */
                --brand-red: var(--brand-primary);
            @endif
        }
        
        * { box-sizing: border-box; }
        html, body { 
            margin: 0; 
            padding: 0; 
            background: var(--bg); 
            color: var(--brand-dark); 
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            line-height: 1.5;
        }
        
        .container { 
            max-width: 800px; 
            margin: 0 auto; 
            padding: 20px; 
        }
        
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: var(--card);
            padding: 24px;
            border-radius: 12px;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            color: var(--brand-red);
            font-size: 28px;
            font-weight: 700;
            margin: 0;
        }
        
        .header-actions {
            display: flex;
            gap: 12px;
        }
        
        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: var(--brand-red);
            color: white;
        }
        
        .btn-primary:hover {
            background: #c20510;
            text-decoration: none;
        }
        
        .btn-secondary {
            background: var(--bg);
            color: var(--brand-dark);
            border: 1px solid var(--border);
        }
        
        .btn-secondary:hover {
            background: var(--card);
            text-decoration: none;
        }
        
        .card {
            background: var(--card);
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            padding: 24px;
            margin-bottom: 24px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--brand-dark);
        }
        
        .form-input, .form-select {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.2s;
        }
        
        .form-input:focus, .form-select:focus {
            outline: none;
            border-color: var(--brand-red);
            box-shadow: 0 0 0 3px rgba(227, 6, 19, 0.1);
        }
        
        .form-input:read-only {
            background: var(--bg);
            color: var(--muted);
        }
        
        .form-text {
            font-size: 12px;
            color: var(--muted);
            margin-top: 4px;
        }
        
        .error-text {
            font-size: 12px;
            color: var(--danger);
            margin-top: 4px;
        }
        
        .alert {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            font-size: 14px;
        }
        
        .alert-success {
            background: #dcfce7;
            color: #16a34a;
            border: 1px solid #bbf7d0;
        }
        
        .alert-danger {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }
        
        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 24px;
            font-size: 14px;
            color: var(--muted);
        }
        
        .breadcrumb a {
            color: var(--brand-red);
            text-decoration: none;
        }
        
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        
        .info-card {
            background: #f8f9fa;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 16px;
            margin-top: 20px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
        }
        
        .info-item {
            background: white;
            padding: 12px;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
        }
        
        .info-label {
            font-size: 12px;
            font-weight: 600;
            color: var(--muted);
            margin-bottom: 4px;
        }
        
        .info-value {
            font-size: 14px;
            color: var(--brand-dark);
        }
    </style>
</head>
<body>
    @include('partials._admin-header')
    <div class="container">
        <div class="header" style="margin-top: 16px;">
            <h1>Modifier l'Utilisateur</h1>
        </div>
        
        @if(session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif
        
        @if(session('error'))
            <div class="alert alert-danger">
                {{ session('error') }}
            </div>
        @endif

        <div class="card">
            <form action="{{ route('admin.users.update', $user) }}" method="POST">
                @csrf
                @method('PUT')

                <!-- Email (Read-only) -->
                <div class="form-group">
                    <label for="email" class="form-label">
                        📧 Adresse Email
                    </label>
                    <input id="email" name="email" type="email" readonly 
                           value="{{ $user->email }}"
                           class="form-input">
                    <div class="form-text">L'email ne peut pas être modifié</div>
                </div>

                <!-- Prénom -->
                <div class="form-group">
                    <label for="first_name" class="form-label">
                        👤 Prénom
                    </label>
                    <input id="first_name" name="first_name" type="text" required 
                           value="{{ old('first_name', $user->first_name) }}"
                           class="form-input @error('first_name') error @enderror"
                           placeholder="Prénom">
                    @error('first_name')
                        <div class="error-text">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Nom -->
                <div class="form-group">
                    <label for="last_name" class="form-label">
                        👤 Nom de Famille
                    </label>
                    <input id="last_name" name="last_name" type="text" required 
                           value="{{ old('last_name', $user->last_name) }}"
                           class="form-input @error('last_name') error @enderror"
                           placeholder="Nom de famille">
                    @error('last_name')
                        <div class="error-text">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Rôle -->
                <div class="form-group">
                    <label for="role_id" class="form-label">
                        🎭 Rôle
                    </label>
                    <select id="role_id" name="role_id" required class="form-select @error('role_id') error @enderror">
                        @foreach($roles as $role)
                            <option value="{{ $role->id }}" {{ old('role_id', $user->role_id) == $role->id ? 'selected' : '' }}>
                                @if($role->name === 'super_admin')
                                    👑 Super Administrateur
                                @elseif($role->name === 'admin')
                                    👨‍💼 Administrateur
                                @else
                                    👥 Collaborateur
                                @endif
                            </option>
                        @endforeach
                    </select>
                    @error('role_id')
                        <div class="error-text">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Opérateurs / Sub-Store (si Admin/Collaborator) -->
                @if(auth()->user()->isSuperAdmin() || $user->role_id !== 1)
                <div id="operators-section" class="form-group" style="{{ $user->role && $user->role->name === 'super_admin' ? 'display: none;' : '' }}">
                    <label for="operators" class="form-label">
                        @if(isset($isSubStoreUser) && $isSubStoreUser)
                            Dashboard Sub-Store
                        @else
                            Operateurs Assignes
                        @endif
                    </label>
                    <select id="operators" name="operators[]" multiple 
                            class="form-select @error('operators') error @enderror"
                            style="min-height: 120px;">
                        @php
                            $userOperators = $user->operators->pluck('operator_name')->toArray();
                        @endphp
                        @foreach($operators as $operator => $operatorLabel)
                            <option value="{{ $operator }}" 
                                    {{ in_array($operator, old('operators', $userOperators)) ? 'selected' : '' }}>
                                {{ $operatorLabel }}
                            </option>
                        @endforeach
                    </select>
                    <div class="form-text">
                        @if(isset($isSubStoreUser) && $isSubStoreUser)
                            Sub-store auquel cet utilisateur est rattache
                        @else
                            Maintenir Ctrl/Cmd pour selectionner plusieurs operateurs
                        @endif
                    </div>
                    @error('operators')
                        <div class="error-text">{{ $message }}</div>
                    @enderror
                </div>
                @endif

                <!-- Statut -->
                <div class="form-group">
                    <label for="status" class="form-label">
                        🚦 Statut du Compte
                    </label>
                    <select id="status" name="status" class="form-select">
                        <option value="active" {{ old('status', $user->status ?? 'active') == 'active' ? 'selected' : '' }}>
                            ✅ Actif
                        </option>
                        <option value="inactive" {{ old('status', $user->status ?? 'active') == 'inactive' ? 'selected' : '' }}>
                            ❌ Inactif
                        </option>
                        <option value="suspended" {{ old('status', $user->status ?? 'active') == 'suspended' ? 'selected' : '' }}>
                            ⏸️ Suspendu
                        </option>
                    </select>
                </div>

                <!-- Boutons d'action -->
                <div class="form-group" style="display: flex; gap: 12px; margin-top: 30px;">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">
                        💾 Enregistrer les Modifications
                    </button>
                    
                    <a href="{{ route('admin.users.index') }}" class="btn btn-secondary" style="flex: 1; text-align: center;">
                        ↩️ Retour
                    </a>
                </div>
            </form>
        </div>

        <!-- Section Réinitialisation Mot de Passe (Super Admin uniquement) -->
        @if(Auth::user()->isSuperAdmin())
        <div class="info-card" style="border-left: 4px solid var(--warning);">
            <h3 style="margin: 0 0 16px 0; font-size: 16px; font-weight: 600; color: var(--warning);">
                🔐 Gestion du Mot de Passe
            </h3>
            
            <div style="background: #fffbeb; padding: 16px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #fed7aa;">
                <p style="margin: 0; font-size: 14px; color: #92400e;">
                    <strong>⚠️ Attention :</strong> Cette action va générer un nouveau lien de réinitialisation pour cet utilisateur. 
                    Un email sera envoyé à <strong>{{ $user->email }}</strong> avec un lien valide 1 heure.
                </p>
            </div>
            
            <form action="{{ route('admin.users.reset-password', $user->id) }}" method="POST" 
                  onsubmit="return confirm('Êtes-vous sûr de vouloir envoyer un lien de réinitialisation à {{ $user->email }} ?')">
                @csrf
                <div style="display: flex; gap: 12px; align-items: center;">
                    <button type="submit" class="btn" 
                            style="background: linear-gradient(45deg, var(--warning), #fbbf24); 
                                   color: white; border: none; flex: 1;">
                        📧 Envoyer un Lien de Réinitialisation
                    </button>
                    
                    <div style="font-size: 12px; color: var(--muted); line-height: 1.4;">
                        L'utilisateur recevra un email<br>
                        pour configurer un nouveau<br>
                        mot de passe sécurisé.
                    </div>
                </div>
            </form>
        </div>
        @endif

        <!-- Informations supplémentaires -->
        <div class="info-card">
            <h3 style="margin: 0 0 16px 0; font-size: 16px; font-weight: 600;">ℹ️ Informations sur l'Utilisateur</h3>
            
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Créé le</div>
                    <div class="info-value">{{ $user->created_at->format('d/m/Y à H:i') }}</div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Dernière modification</div>
                    <div class="info-value">{{ $user->updated_at->format('d/m/Y à H:i') }}</div>
                </div>
                
                @if($user->created_by)
                <div class="info-item">
                    <div class="info-label">Créé par</div>
                    <div class="info-value">{{ $user->creator->first_name ?? 'Système' }} {{ $user->creator->last_name ?? '' }}</div>
                </div>
                @endif
                
                <div class="info-item">
                    <div class="info-label">Dernière connexion</div>
                    <div class="info-value">{{ $user->last_login_at ? $user->last_login_at->format('d/m/Y à H:i') : 'Jamais' }}</div>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Masquer/afficher la section opérateurs selon le rôle
    document.getElementById('role_id').addEventListener('change', function() {
        const operatorsSection = document.getElementById('operators-section');
        const selectedOption = this.options[this.selectedIndex];
        const roleText = selectedOption.textContent;
        
        if (roleText.includes('Super Administrateur')) {
            operatorsSection.style.display = 'none';
        } else {
            operatorsSection.style.display = 'block';
        }
    });
    </script>
</body>
</html>
