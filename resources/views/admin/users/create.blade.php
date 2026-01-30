@php
    $isOoredoo = isset($isOoredoo) ? $isOoredoo : false;
    $theme = isset($theme) ? $theme : 'club_privileges';
@endphp

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Créer un Utilisateur - {{ $isOoredoo ? 'Ooredoo' : 'Club Privilèges' }}</title>
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
        
        .form-input, .form-select {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--border);
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.2s ease;
            font-family: inherit;
        }
        
        .form-input:focus, .form-select:focus {
            outline: none;
            border-color: var(--brand-red);
            box-shadow: 0 0 0 3px rgba(227, 6, 19, 0.1);
        }
        
        .form-textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .checkbox-group {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
            margin-top: 8px;
        }
        
        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border: 1px solid var(--border);
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .checkbox-item:hover {
            background: var(--bg);
        }
        
        .checkbox-item input[type="checkbox"] {
            margin: 0;
        }
        
        .checkbox-item.checked {
            background: #fee2e2;
            border-color: var(--brand-red);
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
            <a href="{{ route('admin.users.index') }}">Utilisateurs</a>
            <span>→</span>
            <span>Créer</span>
        </div>
        
        <div class="header">
            <h1>Créer un Utilisateur</h1>
            <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">
                ← Retour à la liste
            </a>
        </div>
        
        @if(session('error'))
            <div class="alert alert-danger">
                {{ session('error') }}
            </div>
        @endif
        
        <div class="card">
            <form action="{{ route('admin.users.store') }}" method="POST">
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
                    
                    <div class="form-group">
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
                        <label for="phone" class="form-label">Téléphone</label>
                        <input 
                            type="tel" 
                            id="phone" 
                            name="phone" 
                            class="form-input" 
                            value="{{ old('phone') }}" 
                            placeholder="+216 20 000 000"
                        >
                        @error('phone')
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
                    
                    <div class="form-group">
                        <label for="password" class="form-label">Mot de passe *</label>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            class="form-input" 
                            required
                            placeholder="••••••••"
                            minlength="8"
                        >
                        @error('password')
                            <div class="form-error">{{ $message }}</div>
                        @enderror
                    </div>
                    
                    <div class="form-group">
                        <label for="password_confirmation" class="form-label">Confirmer le mot de passe *</label>
                        <input 
                            type="password" 
                            id="password_confirmation" 
                            name="password_confirmation" 
                            class="form-input" 
                            required
                            placeholder="••••••••"
                            minlength="8"
                        >
                    </div>
                    
                    <div class="form-group full-width">
                        <label class="form-label">Opérateurs assignés *</label>
                        <div class="checkbox-group">
                            @foreach($operators as $operatorKey => $operatorName)
                                <div class="checkbox-item">
                                    <input 
                                        type="checkbox" 
                                        id="operator_{{ $loop->index }}" 
                                        name="operators[]" 
                                        value="{{ $operatorName }}"
                                        {{ in_array($operatorName, old('operators', [])) ? 'checked' : '' }}
                                    >
                                    <label for="operator_{{ $loop->index }}">{{ $operatorName }}</label>
                                </div>
                            @endforeach
                        </div>
                        @error('operators')
                            <div class="form-error">{{ $message }}</div>
                        @enderror
                        <small style="color: var(--muted); margin-top: 8px; display: block;">
                            Le premier opérateur sélectionné sera défini comme principal.
                        </small>
                    </div>
                </div>
                
                <div class="form-actions">
                    <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">
                        Annuler
                    </a>
                    <button type="submit" class="btn btn-primary">
                        Créer l'utilisateur
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Gérer l'apparence des checkboxes
        document.querySelectorAll('.checkbox-item input[type="checkbox"]').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const item = this.closest('.checkbox-item');
                if (this.checked) {
                    item.classList.add('checked');
                } else {
                    item.classList.remove('checked');
                }
            });
            
            // État initial
            if (checkbox.checked) {
                checkbox.closest('.checkbox-item').classList.add('checked');
            }
        });
    </script>
</body>
</html>
