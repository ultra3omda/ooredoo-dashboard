@php
    $isOoredoo = isset($isOoredoo) ? $isOoredoo : false;
    $theme = isset($theme) ? $theme : 'club_privileges';
@endphp

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Permissions Campagnes - {{ $isOoredoo ? 'Ooredoo' : 'Club Privileges' }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        :root {
            @if($isOoredoo)
                --brand-primary: #E30613;
                --brand-secondary: #DC2626;
            @else
                --brand-primary: #6C4BA0;
                --brand-secondary: #D4A843;
            @endif
            --brand-dark: #1a1a2e;
            --bg: #f4f4f8;
            --card: #ffffff;
            --card-hover: #f0edf5;
            --muted: #71717a;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --accent: #D4A843;
            --border: #e2e0ea;
            --text-primary: #1a1a2e;
            --text-secondary: #52525b;
            --input-bg: #ffffff;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
        }
        .dark-mode {
            --brand-dark: #FFFFFF;
            --bg: #0D0A1A;
            --card: #161131;
            --card-hover: #1E1745;
            --muted: #A1A1AA;
            --border: #2A2350;
            --text-primary: #FFFFFF;
            --text-secondary: #A1A1AA;
            --input-bg: #1E1745;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.3);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.4);
        }
        
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; background: var(--bg); color: var(--text-primary); font-family: 'Inter', system-ui, sans-serif; }
        
        .container { max-width: 1200px; margin: 0 auto; padding: 24px 16px; }
        
        .header { 
            background: linear-gradient(135deg, var(--brand-primary), var(--brand-secondary)); 
            border-radius: 14px; padding: 28px 24px; color: white; margin-bottom: 24px; 
            display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;
        }
        .header h1 { margin: 0; font-size: 22px; font-weight: 800; }
        .header p { margin: 4px 0 0; font-size: 13px; opacity: 0.8; }
        
        .btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; border: none; cursor: pointer; text-decoration: none; transition: all 0.2s; }
        .btn-outline { background: rgba(255,255,255,0.15); color: white; border: 1px solid rgba(255,255,255,0.3); }
        .btn-outline:hover { background: rgba(255,255,255,0.25); }
        .btn-primary { background: var(--brand-primary); color: white; }
        .btn-primary:hover { opacity: 0.9; }
        .btn-success { background: var(--success); color: white; }
        .btn-success:hover { opacity: 0.9; }
        .btn-sm { padding: 4px 10px; font-size: 12px; border-radius: 6px; }

        .card { background: var(--card); border-radius: 12px; border: 1px solid var(--border); box-shadow: var(--shadow-sm); margin-bottom: 20px; overflow: hidden; }
        .card-header { padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; }
        .card-header h2 { margin: 0; font-size: 15px; font-weight: 700; }
        
        .search-bar { display: flex; gap: 8px; margin-bottom: 20px; }
        .search-bar input { flex: 1; padding: 10px 14px; border: 1px solid var(--border); border-radius: 8px; font-size: 13px; background: var(--input-bg); color: var(--text-primary); }
        .search-bar select { padding: 10px 14px; border: 1px solid var(--border); border-radius: 8px; font-size: 13px; background: var(--input-bg); color: var(--text-primary); }

        table { width: 100%; border-collapse: collapse; }
        th { padding: 10px 14px; text-align: left; font-size: 11px; text-transform: uppercase; color: var(--muted); background: var(--bg); border-bottom: 1px solid var(--border); font-weight: 600; }
        td { padding: 12px 14px; border-bottom: 1px solid var(--border); font-size: 13px; vertical-align: middle; }
        tr:hover { background: var(--card-hover); }

        .badge { display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 5px; font-size: 11px; font-weight: 600; }
        .badge-admin { background: rgba(108,75,160,0.1); color: #6C4BA0; }
        .badge-collab { background: rgba(59,130,246,0.1); color: #2563eb; }
        .badge-super { background: rgba(16,185,129,0.1); color: #059669; }
        .badge-restricted { background: rgba(239,68,68,0.1); color: #ef4444; }
        .badge-full { background: rgba(16,185,129,0.1); color: #059669; }
        
        .campaign-tag { display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 500; background: rgba(59,130,246,0.1); color: #2563eb; margin: 2px; }
        .campaign-tag .remove-btn { cursor: pointer; opacity: 0.6; font-size: 13px; line-height: 1; }
        .campaign-tag .remove-btn:hover { opacity: 1; color: var(--danger); }

        .modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; display: flex; align-items: center; justify-content: center; opacity: 0; pointer-events: none; transition: opacity 0.2s; }
        .modal-overlay.active { opacity: 1; pointer-events: all; }
        .modal { background: var(--card); border-radius: 14px; max-width: 600px; width: 90%; max-height: 80vh; overflow-y: auto; padding: 24px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
        .modal h3 { margin: 0 0 16px; font-size: 17px; font-weight: 700; }
        
        .checkbox-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; max-height: 300px; overflow-y: auto; padding: 12px; border: 1px solid var(--border); border-radius: 8px; background: var(--bg); }
        .checkbox-grid label { display: flex; align-items: center; gap: 8px; padding: 8px 10px; border: 1px solid var(--border); border-radius: 6px; cursor: pointer; transition: all 0.15s; background: var(--card); font-size: 13px; }
        .checkbox-grid label:hover { border-color: var(--brand-primary); }
        .checkbox-grid label input { width: 16px; height: 16px; accent-color: var(--brand-primary); }

        .kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 20px; }
        .kpi { background: var(--card); border-radius: 10px; padding: 16px; text-align: center; border: 1px solid var(--border); }
        .kpi .val { font-size: 28px; font-weight: 800; }
        .kpi .lbl { font-size: 11px; color: var(--muted); margin-top: 2px; }

        .toast { position: fixed; bottom: 20px; right: 20px; background: var(--success); color: white; padding: 12px 20px; border-radius: 8px; font-size: 13px; font-weight: 600; z-index: 2000; opacity: 0; transform: translateY(10px); transition: all 0.3s; box-shadow: 0 4px 12px rgba(0,0,0,0.2); }
        .toast.show { opacity: 1; transform: translateY(0); }
        .toast.error { background: var(--danger); }

        @media (max-width: 768px) {
            .kpi-grid { grid-template-columns: repeat(2, 1fr); }
            .checkbox-grid { grid-template-columns: 1fr; }
            .search-bar { flex-direction: column; }
            .container { padding: 12px 8px; }
            .header h1 { font-size: 20px; }
            .header p { font-size: 12px; }
            .header { flex-direction: column; text-align: center; gap: 10px; }
            .user-card { padding: 14px; }
        }
        @media (max-width: 480px) {
            .kpi-grid { grid-template-columns: 1fr 1fr; gap: 8px; }
            .kpi-card { padding: 12px; }
            .kpi-card .value { font-size: 22px; }
            .header h1 { font-size: 18px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div>
                <h1><i class="fas fa-shield-alt"></i> Permissions Campagnes</h1>
                <p>Gerez les acces campagnes de chaque utilisateur en temps reel</p>
            </div>
            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <a href="{{ route('admin.users.index') }}" class="btn btn-outline"><i class="fas fa-users"></i> Utilisateurs</a>
                <a href="{{ route('admin.invitations.create') }}" class="btn btn-outline"><i class="fas fa-user-plus"></i> Inviter</a>
                <a href="{{ route('admin.audit-logs.index') }}" class="btn btn-outline"><i class="fas fa-history"></i> Journal d'Audit</a>
            </div>
        </div>

        <!-- KPIs -->
        @php
            $totalUsers = count($users);
            $restricted = $users->filter(fn($u) => $u['has_restriction'])->count();
            $fullAccess = $users->filter(fn($u) => !$u['has_restriction'])->count();
            $collaborators = $users->filter(fn($u) => $u['role'] === 'collaborator')->count();
        @endphp
        <div class="kpi-grid" data-testid="permissions-kpi-grid">
            <div class="kpi">
                <div class="val" style="color: var(--text-primary);">{{ $totalUsers }}</div>
                <div class="lbl">Total utilisateurs</div>
            </div>
            <div class="kpi">
                <div class="val" style="color: var(--brand-primary);">{{ $collaborators }}</div>
                <div class="lbl">Collaborateurs</div>
            </div>
            <div class="kpi">
                <div class="val" style="color: var(--success);">{{ $fullAccess }}</div>
                <div class="lbl">Acces complet</div>
            </div>
            <div class="kpi">
                <div class="val" style="color: var(--danger);">{{ $restricted }}</div>
                <div class="lbl">Acces restreint</div>
            </div>
        </div>

        <!-- Search -->
        <div class="search-bar" data-testid="permissions-search">
            <input type="text" id="searchInput" placeholder="Rechercher par nom ou email..." oninput="filterUsers()">
            <select id="filterRole" onchange="filterUsers()">
                <option value="">Tous les roles</option>
                <option value="super_admin">Super Admin</option>
                <option value="admin">Admin</option>
                <option value="collaborator">Collaborateur</option>
            </select>
            <select id="filterAccess" onchange="filterUsers()">
                <option value="">Tous les acces</option>
                <option value="restricted">Restreint</option>
                <option value="full">Acces complet</option>
            </select>
        </div>

        <!-- Users Table -->
        <div class="card" data-testid="permissions-table-card">
            <table>
                <thead>
                    <tr>
                        <th>Utilisateur</th>
                        <th>Role</th>
                        <th>Operateur / Sub-Store</th>
                        <th>Acces campagnes</th>
                        <th>Peut inviter</th>
                        <th style="text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody id="usersTableBody" data-testid="permissions-table-body">
                    @foreach($users as $u)
                    <tr data-user-id="{{ $u['id'] }}" 
                        data-name="{{ strtolower($u['name'] . ' ' . $u['email']) }}"
                        data-role="{{ $u['role'] }}"
                        data-access="{{ $u['has_restriction'] ? 'restricted' : 'full' }}">
                        <td>
                            <div style="font-weight: 600;">{{ $u['name'] }}</div>
                            <div style="font-size: 11px; color: var(--muted);">{{ $u['email'] }}</div>
                        </td>
                        <td>
                            @if($u['role'] === 'super_admin')
                                <span class="badge badge-super">Super Admin</span>
                            @elseif($u['role'] === 'admin')
                                <span class="badge badge-admin">Admin</span>
                            @else
                                <span class="badge badge-collab">Collaborateur</span>
                            @endif
                        </td>
                        <td style="font-size: 12px;">{{ $u['operator'] }}</td>
                        <td>
                            <div id="campaigns-{{ $u['id'] }}" data-testid="campaigns-user-{{ $u['id'] }}">
                                @if($u['role'] === 'super_admin')
                                    <span class="badge badge-full"><i class="fas fa-infinity"></i>&nbsp; Toutes</span>
                                @elseif(!$u['has_restriction'])
                                    <span class="badge badge-full"><i class="fas fa-check"></i>&nbsp; Toutes</span>
                                @else
                                    @foreach($u['campaigns'] as $camp)
                                        <span class="campaign-tag">{{ $camp }}</span>
                                    @endforeach
                                @endif
                            </div>
                        </td>
                        <td style="text-align: center;">
                            @if($u['can_invite'])
                                <span style="color: var(--success);"><i class="fas fa-check-circle"></i></span>
                            @else
                                <span style="color: var(--muted);"><i class="fas fa-minus-circle"></i></span>
                            @endif
                        </td>
                        <td style="text-align: center;">
                            @if($u['role'] !== 'super_admin')
                                <button class="btn btn-sm btn-primary" onclick="openEditModal({{ $u['id'] }}, '{{ addslashes($u['name']) }}', '{{ $u['operator'] }}', {{ json_encode($u['campaigns']) }})" data-testid="edit-btn-{{ $u['id'] }}">
                                    <i class="fas fa-edit"></i> Modifier
                                </button>
                            @else
                                <span style="font-size: 11px; color: var(--muted);">-</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal-overlay" id="editModal" data-testid="edit-modal">
        <div class="modal">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                <h3 id="modalTitle">Modifier les permissions</h3>
                <button onclick="closeEditModal()" style="background: none; border: none; font-size: 22px; cursor: pointer; color: var(--muted);">&times;</button>
            </div>
            
            <div style="background: var(--bg); border-radius: 8px; padding: 12px 16px; margin-bottom: 16px;">
                <div style="font-weight: 600; font-size: 14px;" id="modalUserName"></div>
                <div style="font-size: 12px; color: var(--muted);" id="modalUserOperator"></div>
            </div>

            <div style="margin-bottom: 12px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <label style="font-weight: 600; font-size: 13px;">Campagnes accessibles</label>
                    <div style="display: flex; gap: 6px;">
                        <button type="button" class="btn btn-sm" onclick="toggleAllCampaigns(true)" style="background: var(--bg); border: 1px solid var(--border);">Tout cocher</button>
                        <button type="button" class="btn btn-sm" onclick="toggleAllCampaigns(false)" style="background: var(--bg); border: 1px solid var(--border);">Tout decocher</button>
                    </div>
                </div>
                <div style="font-size: 12px; color: var(--muted); margin-bottom: 8px;">
                    <i class="fas fa-info-circle"></i> Si aucune campagne n'est cochee, l'utilisateur aura acces a <strong>toutes les campagnes</strong> et pourra inviter.
                </div>
                <div class="checkbox-grid" id="modalCampaigns" data-testid="modal-campaigns-grid">
                    <div style="text-align: center; padding: 16px; color: var(--muted); grid-column: span 2;">Chargement...</div>
                </div>
            </div>

            <div style="display: flex; gap: 8px; justify-content: flex-end; margin-top: 20px;">
                <button class="btn" onclick="closeEditModal()" style="background: var(--bg); border: 1px solid var(--border);">Annuler</button>
                <button class="btn btn-primary" onclick="saveCampaignAccess()" data-testid="save-permissions-btn" id="saveBtn">
                    <i class="fas fa-save"></i> Enregistrer
                </button>
                <button class="btn btn-success" onclick="saveCampaignAccess(true)" data-testid="save-full-access-btn">
                    <i class="fas fa-lock-open"></i> Acces complet
                </button>
            </div>
        </div>
    </div>

    <!-- Toast -->
    <div class="toast" id="toast" data-testid="toast-notification"></div>

    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        let currentUserId = null;
        let allCampaigns = [];

        // Load available campaigns once
        async function loadAllCampaigns() {
            try {
                const res = await fetch('{{ route("admin.users.available-campaigns") }}');
                const data = await res.json();
                allCampaigns = data.campaigns || [];
            } catch (e) {
                console.error('Error loading campaigns:', e);
            }
        }

        loadAllCampaigns();

        function filterUsers() {
            const search = document.getElementById('searchInput').value.toLowerCase();
            const roleFilter = document.getElementById('filterRole').value;
            const accessFilter = document.getElementById('filterAccess').value;

            document.querySelectorAll('#usersTableBody tr').forEach(row => {
                const name = row.dataset.name || '';
                const role = row.dataset.role || '';
                const access = row.dataset.access || '';

                const matchSearch = !search || name.includes(search);
                const matchRole = !roleFilter || role === roleFilter;
                const matchAccess = !accessFilter || access === accessFilter;

                row.style.display = (matchSearch && matchRole && matchAccess) ? '' : 'none';
            });
        }

        function openEditModal(userId, userName, userOperator, currentCampaigns) {
            currentUserId = userId;
            document.getElementById('modalTitle').textContent = 'Modifier les permissions';
            document.getElementById('modalUserName').textContent = userName;
            document.getElementById('modalUserOperator').textContent = 'Operateur: ' + userOperator;
            
            // Filter campaigns to only show those from the user's operator/sub-store
            let filteredCampaigns = allCampaigns;
            if (userOperator && userOperator !== '-') {
                filteredCampaigns = allCampaigns.filter(c => c.store_name === userOperator);
            }

            // Build campaign checkboxes
            const grid = document.getElementById('modalCampaigns');
            
            if (filteredCampaigns.length === 0) {
                grid.innerHTML = '<div style="text-align:center;padding:16px;color:var(--muted);grid-column:span 2;">Aucune campagne disponible pour ' + userOperator + '</div>';
            } else {
                // Group by store
                const byStore = {};
                filteredCampaigns.forEach(c => {
                    const store = c.store_name || 'Autre';
                    if (!byStore[store]) byStore[store] = [];
                    byStore[store].push(c);
                });

                let html = '';
                for (const [store, camps] of Object.entries(byStore)) {
                    html += `<div style="grid-column: span 2; font-size: 12px; font-weight: 700; color: var(--brand-primary); padding: 6px 0 2px; border-bottom: 1px solid var(--border); margin-top: 4px;">${store}</div>`;
                    camps.forEach(c => {
                        const checked = currentCampaigns.includes(c.campain_name) ? 'checked' : '';
                        const cards = c.total_cards ? ` (${parseInt(c.total_cards).toLocaleString()} cartes)` : '';
                        html += `<label>
                            <input type="checkbox" value="${c.campain_name}" ${checked} class="campaign-checkbox">
                            <div>
                                <div style="font-weight:600;">${c.campain_name}</div>
                                <div style="font-size:10px;color:var(--muted);">${c.total_batches} lots${cards}</div>
                            </div>
                        </label>`;
                    });
                }
                grid.innerHTML = html;
            }

            document.getElementById('editModal').classList.add('active');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
            currentUserId = null;
        }

        function toggleAllCampaigns(checked) {
            document.querySelectorAll('.campaign-checkbox').forEach(cb => cb.checked = checked);
        }

        async function saveCampaignAccess(fullAccess = false) {
            if (!currentUserId) return;

            const saveBtn = document.getElementById('saveBtn');
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enregistrement...';

            let campaigns = [];
            if (!fullAccess) {
                document.querySelectorAll('.campaign-checkbox:checked').forEach(cb => {
                    campaigns.push(cb.value);
                });
            }

            try {
                const res = await fetch(`/admin/users/${currentUserId}/campaign-access`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ campaigns })
                });

                const data = await res.json();

                if (data.success) {
                    // Update the table row
                    const cell = document.getElementById(`campaigns-${currentUserId}`);
                    const row = document.querySelector(`tr[data-user-id="${currentUserId}"]`);
                    
                    if (data.campaigns.length === 0) {
                        cell.innerHTML = '<span class="badge badge-full"><i class="fas fa-check"></i>&nbsp; Toutes</span>';
                        if (row) row.dataset.access = 'full';
                    } else {
                        cell.innerHTML = data.campaigns.map(c => `<span class="campaign-tag">${c}</span>`).join('');
                        if (row) row.dataset.access = 'restricted';
                    }

                    // Update can_invite icon
                    const inviteCell = row ? row.querySelectorAll('td')[4] : null;
                    if (inviteCell) {
                        inviteCell.innerHTML = data.can_invite 
                            ? '<span style="color: var(--success);"><i class="fas fa-check-circle"></i></span>'
                            : '<span style="color: var(--muted);"><i class="fas fa-minus-circle"></i></span>';
                    }

                    showToast(fullAccess ? 'Acces complet accorde' : `${data.campaigns.length} campagnes assignees`, false);
                    closeEditModal();

                    // Update KPIs
                    updateKpis();
                } else {
                    showToast('Erreur: ' + (data.error || 'Inconnue'), true);
                }
            } catch (e) {
                showToast('Erreur reseau: ' + e.message, true);
            }

            saveBtn.disabled = false;
            saveBtn.innerHTML = '<i class="fas fa-save"></i> Enregistrer';
        }

        function updateKpis() {
            let restricted = 0, full = 0;
            document.querySelectorAll('#usersTableBody tr').forEach(row => {
                if (row.dataset.access === 'restricted') restricted++;
                else full++;
            });
            const kpis = document.querySelectorAll('.kpi .val');
            if (kpis.length >= 4) {
                kpis[2].textContent = full;
                kpis[3].textContent = restricted;
            }
        }

        function showToast(message, isError = false) {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.className = 'toast show' + (isError ? ' error' : '');
            setTimeout(() => { toast.className = 'toast'; }, 3000);
        }

        // Close modal on overlay click
        document.getElementById('editModal').addEventListener('click', function(e) {
            if (e.target === this) closeEditModal();
        });
    </script>
</body>
</html>
