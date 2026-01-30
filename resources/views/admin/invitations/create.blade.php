@php
    $isOoredoo = isset($isOoredoo) ? $isOoredoo : false;
    $theme = isset($theme) ? $theme : 'club_privileges';
@endphp

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inviter un Utilisateur - {{ $isOoredoo ? 'Ooredoo' : 'Club Privilèges' }}</title>
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
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .form-label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--brand-dark);
        }
        
        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--border);
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.2s ease;
            font-family: inherit;
        }
        
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: var(--brand-red);
            box-shadow: 0 0 0 3px rgba(227, 6, 19, 0.1);
        }
        
        .form-textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .alert {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            font-size: 14px;
        }
        
        .alert-danger {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }
        
        .alert-info {
            background: #dbeafe;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
        }
        
        .form-error {
            color: var(--danger);
            font-size: 14px;
            margin-top: 4px;
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
        
        .form-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid var(--border);
        }
        
        .info-box {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 24px;
        }
        
        .info-box h3 {
            color: var(--accent);
            margin: 0 0 8px 0;
            font-size: 16px;
        }
        
        .info-box p {
            margin: 0;
            color: #1e40af;
            font-size: 14px;
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="breadcrumb">
            @if(Auth::user()->canAccessOperatorsDashboard())
                <a href="{{ route('dashboard') }}">Dashboard</a>
                <span>→</span>
            @else
                <a href="{{ route('sub-stores.dashboard') }}">Sub-Stores Dashboard</a>
                <span>→</span>
            @endif
            <a href="{{ route('admin.invitations.index') }}">Invitations</a>
            <span>→</span>
            <span>Inviter</span>
        </div>
        
        <div class="header">
            <h1>Inviter un Utilisateur</h1>
            <a href="{{ route('admin.invitations.index') }}" class="btn btn-secondary">
                ← Retour aux invitations
            </a>
        </div>
        
        <div class="info-box">
            <h3>🔗 Fonctionnement des invitations</h3>
            <p>
                L'utilisateur recevra un lien d'invitation par email. En cliquant sur ce lien, il recevra un code OTP 
                à 6 chiffres pour confirmer son identité et créer automatiquement son compte.
                <br><br>
                <strong>Mode test :</strong> Sans serveur SMTP configuré, le lien d'invitation sera affiché dans les logs 
                et sur la page de confirmation pour que vous puissiez le copier et le tester.
            </p>
        </div>
        
        @if(session('error'))
            <div class="alert alert-danger">
                {{ session('error') }}
            </div>
        @endif
        
        <div class="card">
            <form action="{{ route('admin.invitations.store') }}" method="POST">
                @csrf
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="first_name" class="form-label">Prénom *</label>
                        <input 
                            type="text" 
                            id="first_name" 
                            name="first_name" 
                            class="form-input" 
                            value="{{ old('first_name') }}" 
                            required
                            placeholder="John"
                        >
                        @error('first_name')
                            <div class="form-error">{{ $message }}</div>
                        @enderror
                    </div>
                    
                    <div class="form-group">
                        <label for="last_name" class="form-label">Nom *</label>
                        <input 
                            type="text" 
                            id="last_name" 
                            name="last_name" 
                            class="form-input" 
                            value="{{ old('last_name') }}" 
                            required
                            placeholder="Doe"
                        >
                        @error('last_name')
                            <div class="form-error">{{ $message }}</div>
                        @enderror
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="email" class="form-label">Adresse e-mail *</label>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            class="form-input" 
                            value="{{ old('email') }}" 
                            required
                            placeholder="john.doe@exemple.com"
                        >
                        @error('email')
                            <div class="form-error">{{ $message }}</div>
                        @enderror
                    </div>
                    
                    <div class="form-group">
                        <label for="role_id" class="form-label">Rôle *</label>
                        <select id="role_id" name="role_id" class="form-select" required>
                            <option value="">Sélectionner un rôle</option>
                            @foreach($roles as $role)
                                <option value="{{ $role->id }}" {{ old('role_id') == $role->id ? 'selected' : '' }}>
                                    {{ $role->display_name }}
                                </option>
                            @endforeach
                        </select>
                        @error('role_id')
                            <div class="form-error">{{ $message }}</div>
                        @enderror
                    </div>
                    
                    @if(Auth::user()->isSuperAdmin())
                        <div class="form-group">
                            <label for="type_selection" class="form-label">Type *</label>
                            <select id="type_selection" name="type_selection" class="form-select" required onchange="toggleOperatorLists()">
                                <option value="">Sélectionner un type</option>
                                <option value="operator" {{ old('type_selection') == 'operator' ? 'selected' : '' }}>Opérateur</option>
                                <option value="substore" {{ old('type_selection') == 'substore' ? 'selected' : '' }}>Sub-Store</option>
                            </select>
                            @error('type_selection')
                                <div class="form-error">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <div class="form-group" id="operator_selection" style="display: none;">
                            <label for="operator_name" class="form-label">Opérateur *</label>
                            <select id="operator_name" name="operator_name" class="form-select">
                                <option value="">Sélectionner un opérateur</option>
                                @foreach($operators as $operatorKey => $operatorName)
                                    <option value="{{ $operatorName }}" {{ old('operator_name') == $operatorName ? 'selected' : '' }}>
                                        {{ $operatorName }}
                                    </option>
                                @endforeach
                            </select>
                            @error('operator_name')
                                <div class="form-error">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <div class="form-group" id="substore_selection" style="display: none;">
                            <label for="substore_name" class="form-label">Sub-Store *</label>
                            <select id="substore_name" name="substore_name" class="form-select">
                                <option value="">Sélectionner un sub-store</option>
                                @foreach($subStores as $subStoreKey => $subStoreName)
                                    <option value="{{ $subStoreName }}" {{ old('substore_name') == $subStoreName ? 'selected' : '' }}>
                                        {{ $subStoreName }}
                                    </option>
                                @endforeach
                            </select>
                            @error('substore_name')
                                <div class="form-error">{{ $message }}</div>
                            @enderror
                        </div>
                    @elseif(Auth::user()->isAdminOperator())
                        <div class="form-group">
                            <label for="operator_name" class="form-label">Opérateur *</label>
                            <select id="operator_name" name="operator_name" class="form-select" required>
                                <option value="">Sélectionner un opérateur</option>
                                @foreach($operators as $operatorKey => $operatorName)
                                    <option value="{{ $operatorName }}" {{ old('operator_name') == $operatorName ? 'selected' : '' }}>
                                        {{ $operatorName }}
                                    </option>
                                @endforeach
                            </select>
                            @error('operator_name')
                                <div class="form-error">{{ $message }}</div>
                            @enderror
                        </div>
                        <input type="hidden" name="type_selection" value="operator">
                    @elseif(Auth::user()->isAdminSubStore())
                        <div class="form-group">
                            <label for="substore_name" class="form-label">Sub-Store *</label>
                            <select id="substore_name" name="substore_name" class="form-select" required>
                                <option value="">Sélectionner un sub-store</option>
                                @foreach($subStores as $subStoreKey => $subStoreName)
                                    <option value="{{ $subStoreName }}" {{ old('substore_name') == $subStoreName ? 'selected' : '' }}>
                                        {{ $subStoreName }}
                                    </option>
                                @endforeach
                            </select>
                            @error('substore_name')
                                <div class="form-error">{{ $message }}</div>
                            @enderror
                        </div>
                        <input type="hidden" name="type_selection" value="substore">
                    @endif
                    
                    <div class="form-group full-width">
                        <label for="message" class="form-label">Message personnalisé (optionnel)</label>
                        <textarea 
                            id="message" 
                            name="message" 
                            class="form-textarea" 
                            placeholder="Un message de bienvenue personnalisé pour l'invité..."
                        >{{ old('message') }}</textarea>
                        @error('message')
                            <div class="form-error">{{ $message }}</div>
                        @enderror
                        <small style="color: var(--muted); margin-top: 8px; display: block;">
                            Ce message sera inclus dans l'email d'invitation.
                        </small>
                    </div>
                </div>
                
                <div class="form-actions">
                    <a href="{{ route('admin.invitations.index') }}" class="btn btn-secondary">
                        Annuler
                    </a>
                    <button type="submit" class="btn btn-primary">
                        📧 Envoyer l'invitation
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function toggleOperatorLists() {
            const typeSelection = document.getElementById('type_selection');
            const operatorSelection = document.getElementById('operator_selection');
            const substoreSelection = document.getElementById('substore_selection');
            const operatorName = document.getElementById('operator_name');
            const substoreName = document.getElementById('substore_name');
            
            // Vérifier si les éléments existent (seulement pour super admin)
            if (!typeSelection || !operatorSelection || !substoreSelection) {
                return;
            }
            
            // Masquer les deux listes par défaut
            operatorSelection.style.display = 'none';
            substoreSelection.style.display = 'none';
            
            // Désactiver les champs
            if (operatorName) {
                operatorName.required = false;
                operatorName.value = '';
            }
            if (substoreName) {
                substoreName.required = false;
                substoreName.value = '';
            }
            
            // Afficher la liste appropriée selon le type sélectionné
            if (typeSelection.value === 'operator') {
                operatorSelection.style.display = 'block';
                if (operatorName) operatorName.required = true;
            } else if (typeSelection.value === 'substore') {
                substoreSelection.style.display = 'block';
                if (substoreName) substoreName.required = true;
            }
        }
        
        // Initialiser l'affichage au chargement de la page
        document.addEventListener('DOMContentLoaded', function() {
            toggleOperatorLists();
        });
    </script>
</body>
</html>
