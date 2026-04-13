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
                --text-primary: #1f2937;
                --text-secondary: #64748b;
                --input-bg: #ffffff;
                --input-border: #e2e8f0;
                --brand-red: var(--brand-primary);
            @else
                --brand-primary: #6C4BA0;
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
                --text-primary: #1f2937;
                --text-secondary: #64748b;
                --input-bg: #ffffff;
                --input-border: #e2e8f0;
                --brand-red: var(--brand-primary);
            @endif
        }
        
        * { box-sizing: border-box; }
        html, body { 
            margin: 0; 
            padding: 0; 
            background: var(--bg); 
            color: var(--text-primary); 
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            line-height: 1.5;
        }
        
        .container { 
            max-width: 800px; 
            margin: 0 auto; 
            padding: 20px; 
        }
        
        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
        }
        
        .page-header h1 {
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
            opacity: 0.9;
        }
        
        .btn-secondary {
            background: var(--bg);
            color: var(--text-primary);
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
            color: var(--text-primary);
        }
        
        .form-input, .form-select {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--input-border);
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.2s ease;
            font-family: inherit;
            background: var(--input-bg);
            color: var(--text-primary);
        }
        
        .form-input:focus, .form-select:focus {
            outline: none;
            border-color: var(--brand-red);
            box-shadow: 0 0 0 3px rgba(108, 75, 160, 0.1);
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
            
            .page-header {
                flex-direction: column;
                gap: 12px;
                align-items: flex-start;
            }
        }
        
        .dark-mode { 
            --brand-dark:#FFF; --bg:#0D0A1A; --card:#161131; --card-hover:#1E1745; 
            --muted:#A1A1AA; --border:#2A2350; --text-primary:#FFF; --text-secondary:#A1A1AA; 
            --input-bg:#1E1745; --input-border:#2A2350; --shadow-sm:0 1px 3px rgba(0,0,0,0.3); 
            --shadow-md:0 4px 12px rgba(0,0,0,0.4); --table-stripe:rgba(255,255,255,0.03); 
            --success:#10b981; --warning:#f59e0b; --danger:#ef4444; --accent:#D4A843; 
        }
    </style>
    <script>(function(){var s=localStorage.getItem("dashboard-theme");if(s==="dark")document.documentElement.classList.add("dark-mode");}());</script>
</head>
<body>
    @include('partials._admin-header')
    <div class="container">
        <div class="page-header" style="margin-top: 16px;">
            <h1 data-testid="create-user-title">Créer un Utilisateur</h1>
            <a href="{{ route('admin.users.index') }}" class="btn btn-secondary" data-testid="back-to-list-btn">
                Retour a la liste
            </a>
        </div>
        
        <div class="info-box">
            <p>
                <strong>Creation directe :</strong> L'utilisateur sera cree immediatement avec le mot de passe defini. 
                Selectionnez le type (Operateur ou Sub-Store) puis assignez les permissions appropriees.
            </p>
        </div>
        
        @if(session('error'))
            <div class="alert alert-danger" data-testid="error-alert">
                {{ session('error') }}
            </div>
        @endif
        
        <div class="card">
            <form action="{{ route('admin.users.store') }}" method="POST" data-testid="create-user-form">
                @csrf
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="first_name" class="form-label">Prenom *</label>
                        <input 
                            type="text" 
                            id="first_name" 
                            name="first_name" 
                            class="form-input" 
                            value="{{ old('first_name') }}" 
                            required
                            placeholder="John"
                            data-testid="first-name-input"
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
                            data-testid="last-name-input"
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
                            data-testid="email-input"
                        >
                        @error('email')
                            <div class="form-error">{{ $message }}</div>
                        @enderror
                    </div>
                    
                    <div class="form-group">
                        <label for="phone" class="form-label">Telephone</label>
                        <input 
                            type="tel" 
                            id="phone" 
                            name="phone" 
                            class="form-input" 
                            value="{{ old('phone') }}" 
                            placeholder="+216 20 000 000"
                            data-testid="phone-input"
                        >
                        @error('phone')
                            <div class="form-error">{{ $message }}</div>
                        @enderror
                    </div>
                    
                    <div class="form-group">
                        <label for="role_id" class="form-label">Role *</label>
                        <select id="role_id" name="role_id" class="form-select" required onchange="checkCampaignVisibility()" data-testid="role-select">
                            <option value="">Selectionner un role</option>
                            @foreach($roles as $role)
                                <option value="{{ $role->id }}" data-role-name="{{ $role->name }}" {{ old('role_id') == $role->id ? 'selected' : '' }}>
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
                            <select id="type_selection" name="type_selection" class="form-select" required onchange="toggleOperatorLists()" data-testid="type-select">
                                <option value="">Selectionner un type</option>
                                <option value="operator" {{ old('type_selection') == 'operator' ? 'selected' : '' }}>Operateur</option>
                                <option value="substore" {{ old('type_selection') == 'substore' ? 'selected' : '' }}>Sub-Store</option>
                            </select>
                            @error('type_selection')
                                <div class="form-error">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <div class="form-group full-width" id="operator_selection" style="display: none;">
                            <label class="form-label">Operateur(s) *</label>
                            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
                                <span id="operator_counter" style="font-size:12px;color:var(--muted);">0 operateur(s) selectionne(s)</span>
                                <div style="display:flex;gap:6px;">
                                    <button type="button" onclick="selectAllOperators()" class="btn btn-secondary" style="font-size:11px;padding:3px 10px;" data-testid="select-all-operators">Tout selectionner</button>
                                    <button type="button" onclick="deselectAllOperators()" class="btn btn-secondary" style="font-size:11px;padding:3px 10px;" data-testid="deselect-all-operators">Tout deselectionner</button>
                                </div>
                            </div>
                            <div id="operator_checkboxes" data-testid="operator-checkboxes"
                                style="max-height:280px;overflow-y:auto;border:1px solid var(--input-border);border-radius:8px;background:var(--input-bg);padding:4px;">
                                @foreach($operators as $operatorKey => $operatorName)
                                    <label style="display:flex;align-items:center;gap:10px;padding:10px 12px;border-bottom:1px solid var(--input-border);cursor:pointer;transition:background 0.15s;"
                                           onmouseover="this.style.background='rgba(107,70,193,0.06)'" onmouseout="this.style.background='transparent'">
                                        <input type="checkbox" name="operator_names[]" value="{{ $operatorName }}" 
                                               {{ is_array(old('operator_names')) && in_array($operatorName, old('operator_names')) ? 'checked' : '' }}
                                               onchange="updateOperatorCounter()"
                                               data-testid="operator-checkbox-{{ Str::slug($operatorName) }}"
                                               style="width:18px;height:18px;accent-color:var(--brand-primary);flex-shrink:0;cursor:pointer;">
                                        <span style="font-size:14px;font-weight:500;">{{ $operatorName }}</span>
                                    </label>
                                @endforeach
                            </div>
                            @error('operator_names')
                                <div class="form-error">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <div class="form-group full-width" id="substore_selection" style="display: none;">
                            <label class="form-label">Sub-Store(s) *</label>
                            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
                                <span id="substore_counter" style="font-size:12px;color:var(--muted);">0 sub-store(s) selectionne(s)</span>
                                <div style="display:flex;gap:6px;">
                                    <button type="button" onclick="selectAllSubstores()" class="btn btn-secondary" style="font-size:11px;padding:3px 10px;" data-testid="select-all-substores">Tout selectionner</button>
                                    <button type="button" onclick="deselectAllSubstores()" class="btn btn-secondary" style="font-size:11px;padding:3px 10px;" data-testid="deselect-all-substores">Tout deselectionner</button>
                                </div>
                            </div>
                            <div id="substore_checkboxes" data-testid="substore-checkboxes"
                                style="max-height:280px;overflow-y:auto;border:1px solid var(--input-border);border-radius:8px;background:var(--input-bg);padding:4px;">
                                @foreach($subStores as $subStoreKey => $subStoreName)
                                    <label style="display:flex;align-items:center;gap:10px;padding:10px 12px;border-bottom:1px solid var(--input-border);cursor:pointer;transition:background 0.15s;"
                                           onmouseover="this.style.background='rgba(107,70,193,0.06)'" onmouseout="this.style.background='transparent'">
                                        <input type="checkbox" name="substore_names[]" value="{{ $subStoreName }}"
                                               {{ is_array(old('substore_names')) && in_array($subStoreName, old('substore_names')) ? 'checked' : '' }}
                                               onchange="updateSubstoreCounter(); loadCampaignsForSelectedSubstores()"
                                               data-testid="substore-checkbox-{{ Str::slug($subStoreName) }}"
                                               style="width:18px;height:18px;accent-color:var(--brand-primary);flex-shrink:0;cursor:pointer;">
                                        <span style="font-size:14px;font-weight:500;">{{ $subStoreName }}</span>
                                    </label>
                                @endforeach
                            </div>
                            @error('substore_names')
                                <div class="form-error">{{ $message }}</div>
                            @enderror
                        </div>
                    @elseif(Auth::user()->isAdminOperator())
                        <div class="form-group">
                            <label for="operator_name" class="form-label">Operateur *</label>
                            <select id="operator_name" name="operator_name" class="form-select" required data-testid="operator-select">
                                <option value="">Selectionner un operateur</option>
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
                    @elseif(Auth::user()->isAdminSubStore() || Auth::user()->canInviteCollaborators())
                        <div class="form-group">
                            <label for="substore_name" class="form-label">Sub-Store *</label>
                            <select id="substore_name" name="substore_name" class="form-select" required onchange="loadCampaigns()" data-testid="substore-select">
                                <option value="">Selectionner un sub-store</option>
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
                    
                    <div class="form-group">
                        <label for="password" class="form-label">Mot de passe *</label>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            class="form-input" 
                            required
                            placeholder="--------"
                            minlength="8"
                            data-testid="password-input"
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
                            placeholder="--------"
                            minlength="8"
                            data-testid="password-confirm-input"
                        >
                    </div>
                    
                    <!-- Campaign Selection Section (dynamic) -->
                    <div class="form-group full-width" id="campaign_section" style="display: none;" data-testid="campaign-section">
                        <label class="form-label">Campagnes accessibles</label>
                        <div style="font-size: 13px; color: var(--muted); margin-bottom: 8px;">
                            Si aucune campagne n'est selectionnee, l'utilisateur aura acces a <strong>toutes les campagnes</strong>.
                        </div>
                        <!-- Searchable Multi-Select -->
                        <div id="campaign_multiselect" style="position:relative;" data-testid="campaign-multiselect">
                            <!-- Selected tags display -->
                            <div id="campaign_selected_tags" style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:8px;min-height:0;"></div>
                            <!-- Search input -->
                            <div style="position:relative;">
                                <input type="text" id="campaign_search" placeholder="Rechercher une campagne..." autocomplete="off" data-testid="campaign-search-input"
                                    style="width:100%;padding:10px 14px 10px 36px;border:1px solid var(--input-border);border-radius:8px;font-size:14px;background:var(--input-bg);color:var(--text-primary);outline:none;transition:border-color 0.2s;"
                                    onfocus="this.style.borderColor='var(--accent)'" onblur="this.style.borderColor='var(--input-border)'" oninput="filterCampaigns(this.value)">
                                <svg style="position:absolute;left:10px;top:50%;transform:translateY(-50%);width:18px;height:18px;color:var(--muted);" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35" stroke-linecap="round"/></svg>
                            </div>
                            <!-- Counter + actions bar -->
                            <div style="display:flex;align-items:center;justify-content:space-between;margin-top:6px;margin-bottom:4px;">
                                <span id="campaign_counter" style="font-size:12px;color:var(--muted);" data-testid="campaign-counter">0 campagne(s) selectionnee(s)</span>
                                <div style="display:flex;gap:6px;">
                                    <button type="button" onclick="selectAllCampaigns()" class="btn btn-secondary" style="font-size:11px;padding:3px 10px;" data-testid="select-all-campaigns">Tout selectionner</button>
                                    <button type="button" onclick="deselectAllCampaigns()" class="btn btn-secondary" style="font-size:11px;padding:3px 10px;" data-testid="deselect-all-campaigns">Tout deselectionner</button>
                                </div>
                            </div>
                            <!-- Scrollable checkbox list -->
                            <div id="campaign_checkboxes" data-testid="campaign-checkboxes"
                                style="max-height:280px;overflow-y:auto;border:1px solid var(--input-border);border-radius:8px;background:var(--input-bg);padding:4px;">
                                <div style="text-align:center;padding:20px;color:var(--muted);">
                                    Selectionnez d'abord un sub-store pour charger les campagnes...
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <a href="{{ route('admin.users.index') }}" class="btn btn-secondary" data-testid="cancel-btn">
                        Annuler
                    </a>
                    <button type="submit" class="btn btn-primary" data-testid="submit-create-user">
                        Creer l'utilisateur
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function getSelectedRoleName() {
            const roleSelect = document.getElementById('role_id');
            if (!roleSelect || !roleSelect.value) return '';
            const selected = roleSelect.options[roleSelect.selectedIndex];
            return (selected.getAttribute('data-role-name') || '').toLowerCase();
        }

        function isCampaignEligibleRole() {
            const roleName = getSelectedRoleName();
            return roleName === 'admin' || roleName === 'collaborator';
        }

        function checkCampaignVisibility() {
            const campaignSection = document.getElementById('campaign_section');
            if (!campaignSection) return;

            const eligible = isCampaignEligibleRole();
            const typeSelection = document.getElementById('type_selection');
            const currentType = typeSelection ? typeSelection.value : '';

            // For sub-store type: show campaigns if at least one sub-store is selected and role is eligible
            if (currentType === 'substore' || !typeSelection) {
                const checkedSubstores = document.querySelectorAll('#substore_checkboxes input[type="checkbox"]:checked');
                const substoreName = document.getElementById('substore_name');
                const hasSubstore = (checkedSubstores && checkedSubstores.length > 0) || (substoreName && substoreName.value);
                
                if (hasSubstore && eligible) {
                    campaignSection.style.display = 'block';
                    return;
                }
            }

            campaignSection.style.display = 'none';
        }

        function toggleOperatorLists() {
            const typeSelection = document.getElementById('type_selection');
            const operatorSelection = document.getElementById('operator_selection');
            const substoreSelection = document.getElementById('substore_selection');
            const campaignSection = document.getElementById('campaign_section');
            
            if (!typeSelection || !operatorSelection || !substoreSelection) {
                return;
            }
            
            operatorSelection.style.display = 'none';
            substoreSelection.style.display = 'none';
            if (campaignSection) campaignSection.style.display = 'none';
            
            if (typeSelection.value === 'operator') {
                operatorSelection.style.display = 'block';
            } else if (typeSelection.value === 'substore') {
                substoreSelection.style.display = 'block';
            }
        }

        function updateOperatorCounter() {
            const counter = document.getElementById('operator_counter');
            if (!counter) return;
            const selected = document.querySelectorAll('#operator_checkboxes input[type="checkbox"]:checked').length;
            counter.textContent = selected === 0 ? 'Aucun operateur selectionne' : `${selected} operateur(s) selectionne(s)`;
        }

        function selectAllOperators() {
            document.querySelectorAll('#operator_checkboxes input[type="checkbox"]').forEach(cb => cb.checked = true);
            updateOperatorCounter();
        }

        function deselectAllOperators() {
            document.querySelectorAll('#operator_checkboxes input[type="checkbox"]').forEach(cb => cb.checked = false);
            updateOperatorCounter();
        }

        function updateSubstoreCounter() {
            const counter = document.getElementById('substore_counter');
            if (!counter) return;
            const selected = document.querySelectorAll('#substore_checkboxes input[type="checkbox"]:checked').length;
            counter.textContent = selected === 0 ? 'Aucun sub-store selectionne' : `${selected} sub-store(s) selectionne(s)`;
        }

        function selectAllSubstores() {
            document.querySelectorAll('#substore_checkboxes input[type="checkbox"]').forEach(cb => cb.checked = true);
            updateSubstoreCounter();
            loadCampaignsForSelectedSubstores();
        }

        function deselectAllSubstores() {
            document.querySelectorAll('#substore_checkboxes input[type="checkbox"]').forEach(cb => cb.checked = false);
            updateSubstoreCounter();
            loadCampaignsForSelectedSubstores();
        }

        async function loadCampaignsForSelectedSubstores() {
            const campaignSection = document.getElementById('campaign_section');
            const campaignCheckboxes = document.getElementById('campaign_checkboxes');
            if (!campaignSection || !campaignCheckboxes) return;

            const checkedSubstores = Array.from(document.querySelectorAll('#substore_checkboxes input[type="checkbox"]:checked')).map(cb => cb.value);
            
            if (checkedSubstores.length === 0 || !isCampaignEligibleRole()) {
                campaignSection.style.display = 'none';
                return;
            }

            campaignSection.style.display = 'block';
            campaignCheckboxes.innerHTML = '<div style="text-align:center;padding:20px;color:var(--muted);">Chargement des campagnes...</div>';
            document.getElementById('campaign_search').value = '';
            document.getElementById('campaign_selected_tags').innerHTML = '';

            try {
                let allCampaignsResult = [];
                for (const storeName of checkedSubstores) {
                    const res = await fetch(`{{ route('admin.invitations.campaigns') }}?store_name=${encodeURIComponent(storeName)}`);
                    const data = await res.json();
                    if (data.campaigns) {
                        allCampaignsResult = allCampaignsResult.concat(data.campaigns);
                    }
                }
                allCampaigns = allCampaignsResult;
                if (allCampaigns.length === 0) {
                    campaignCheckboxes.innerHTML = '<div style="text-align:center;padding:20px;color:var(--muted);">Aucune campagne trouvee.</div>';
                } else {
                    renderCampaignList(allCampaigns);
                }
                updateCampaignCounter();
            } catch (e) {
                campaignCheckboxes.innerHTML = `<div style="text-align:center;padding:20px;color:var(--danger);">Erreur: ${e.message}</div>`;
            }
        }

        // Store all loaded campaigns for filtering
        let allCampaigns = [];

        async function loadCampaigns() {
            const substoreName = document.getElementById('substore_name');
            const campaignSection = document.getElementById('campaign_section');
            const campaignCheckboxes = document.getElementById('campaign_checkboxes');
            
            if (!substoreName || !campaignSection || !campaignCheckboxes) return;
            
            const storeName = substoreName.value;
            if (!storeName) {
                campaignSection.style.display = 'none';
                return;
            }

            if (!isCampaignEligibleRole()) {
                campaignSection.style.display = 'none';
                return;
            }
            
            campaignSection.style.display = 'block';
            campaignCheckboxes.innerHTML = '<div style="text-align:center;padding:20px;color:var(--muted);">Chargement des campagnes...</div>';
            document.getElementById('campaign_search').value = '';
            document.getElementById('campaign_selected_tags').innerHTML = '';
            
            try {
                const res = await fetch(`{{ route('admin.invitations.campaigns') }}?store_name=${encodeURIComponent(storeName)}`);
                const data = await res.json();
                
                if (!data.campaigns || data.campaigns.length === 0) {
                    allCampaigns = [];
                    campaignCheckboxes.innerHTML = '<div style="text-align:center;padding:20px;color:var(--muted);">Aucune campagne trouvee pour ce sub-store.</div>';
                    updateCampaignCounter();
                    return;
                }
                
                allCampaigns = data.campaigns;
                renderCampaignList(allCampaigns);
                updateCampaignCounter();
            } catch (e) {
                campaignCheckboxes.innerHTML = `<div style="text-align:center;padding:20px;color:var(--danger);">Erreur: ${e.message}</div>`;
            }
        }

        function renderCampaignList(campaigns) {
            const container = document.getElementById('campaign_checkboxes');
            if (!campaigns.length) {
                container.innerHTML = '<div style="text-align:center;padding:20px;color:var(--muted);">Aucun resultat.</div>';
                return;
            }
            let html = '';
            campaigns.forEach(c => {
                const checked = isCampaignSelected(c.name) ? 'checked' : '';
                html += `<label class="campaign-item" data-name="${c.name.toLowerCase()}" style="display:flex;align-items:center;gap:10px;padding:10px 12px;border-bottom:1px solid var(--input-border);cursor:pointer;transition:background 0.15s;"
                              onmouseover="this.style.background='rgba(107,70,193,0.06)'" onmouseout="this.style.background='transparent'">
                    <input type="checkbox" name="campaign_access[]" value="${c.name}" ${checked} onchange="onCampaignToggle(this)" data-testid="campaign-checkbox-${c.name.replace(/\s+/g,'-').toLowerCase()}"
                        style="width:18px;height:18px;accent-color:var(--brand-primary);flex-shrink:0;cursor:pointer;">
                    <div style="flex:1;min-width:0;">
                        <div style="font-size:13px;font-weight:600;color:var(--text-primary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="${c.name}">${c.name}</div>
                        <div style="font-size:11px;color:var(--muted);">${c.cards} cartes / ${c.batches} lots</div>
                    </div>
                </label>`;
            });
            container.innerHTML = html;
        }

        function filterCampaigns(query) {
            const q = query.toLowerCase().trim();
            if (!q) {
                renderCampaignList(allCampaigns);
                return;
            }
            const filtered = allCampaigns.filter(c => c.name.toLowerCase().includes(q));
            renderCampaignList(filtered);
        }

        function isCampaignSelected(name) {
            const tags = document.getElementById('campaign_selected_tags');
            return tags.querySelector(`[data-campaign="${CSS.escape(name)}"]`) !== null;
        }

        function onCampaignToggle(checkbox) {
            if (checkbox.checked) {
                addCampaignTag(checkbox.value);
            } else {
                removeCampaignTag(checkbox.value);
            }
            updateCampaignCounter();
        }

        function addCampaignTag(name) {
            const tags = document.getElementById('campaign_selected_tags');
            if (tags.querySelector(`[data-campaign="${CSS.escape(name)}"]`)) return;
            const tag = document.createElement('span');
            tag.setAttribute('data-campaign', name);
            tag.style.cssText = 'display:inline-flex;align-items:center;gap:4px;padding:4px 10px;background:var(--brand-primary);color:#fff;border-radius:20px;font-size:12px;font-weight:500;max-width:220px;';
            tag.innerHTML = `<span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="${name}">${name}</span>
                <button type="button" onclick="removeCampaignTagAndUncheck('${name.replace(/'/g, "\\'")}')" style="background:none;border:none;color:#fff;cursor:pointer;font-size:14px;padding:0 2px;line-height:1;opacity:0.8;">&times;</button>`;
            tags.appendChild(tag);
        }

        function removeCampaignTag(name) {
            const tags = document.getElementById('campaign_selected_tags');
            const tag = tags.querySelector(`[data-campaign="${CSS.escape(name)}"]`);
            if (tag) tag.remove();
        }

        function removeCampaignTagAndUncheck(name) {
            removeCampaignTag(name);
            const checkboxes = document.querySelectorAll('#campaign_checkboxes input[type="checkbox"]');
            checkboxes.forEach(cb => {
                if (cb.value === name) cb.checked = false;
            });
            updateCampaignCounter();
        }

        function updateCampaignCounter() {
            const counter = document.getElementById('campaign_counter');
            const selected = document.querySelectorAll('#campaign_selected_tags [data-campaign]').length;
            counter.textContent = selected === 0 ? 'Aucune campagne selectionnee (acces complet)' : `${selected} campagne(s) selectionnee(s)`;
        }

        function selectAllCampaigns() {
            document.querySelectorAll('#campaign_checkboxes input[type="checkbox"]').forEach(cb => {
                cb.checked = true;
                addCampaignTag(cb.value);
            });
            updateCampaignCounter();
        }

        function deselectAllCampaigns() {
            document.querySelectorAll('#campaign_checkboxes input[type="checkbox"]').forEach(cb => cb.checked = false);
            document.getElementById('campaign_selected_tags').innerHTML = '';
            updateCampaignCounter();
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize type toggle for super admin
            const typeSelect = document.getElementById('type_selection');
            if (typeSelect) {
                toggleOperatorLists();
            }

            // Auto-load campaigns if sub-store is pre-selected (old value)
            const substoreName = document.getElementById('substore_name');
            if (substoreName && substoreName.value) {
                loadCampaigns();
            }
        });
    </script>
</body>
</html>
