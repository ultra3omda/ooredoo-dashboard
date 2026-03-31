@extends('layouts.app')

@section('title', 'Gestion des utilisateurs Pluxee')

@section('styles')
<style>
.pluxee-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
.pluxee-header h1 { font-size: 22px; font-weight: 700; color: var(--text-primary); display: flex; align-items: center; gap: 10px; }
.pluxee-header .badge { background: #E30045; color: white; font-size: 11px; padding: 3px 10px; border-radius: 12px; font-weight: 600; }
.pluxee-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 20px; margin-bottom: 30px; }
.campaign-card { background: var(--card); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; box-shadow: var(--shadow-sm); }
.campaign-card-header { padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
.campaign-card-header h3 { font-size: 14px; font-weight: 700; color: var(--text-primary); margin: 0; }
.campaign-stats { display: flex; gap: 16px; padding: 12px 20px; background: var(--surface-secondary, #f8f9fa); }
.campaign-stat { text-align: center; flex: 1; }
.campaign-stat-value { font-size: 20px; font-weight: 700; color: var(--brand-primary); font-family: 'Outfit', sans-serif; }
.campaign-stat-label { font-size: 10px; text-transform: uppercase; color: var(--muted); letter-spacing: 0.5px; }
.campaign-users { padding: 12px 20px; }
.campaign-users-title { font-size: 12px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; }
.user-row { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid var(--border); }
.user-row:last-child { border-bottom: none; }
.user-info { display: flex; flex-direction: column; }
.user-name { font-size: 13px; font-weight: 600; color: var(--text-primary); }
.user-email { font-size: 11px; color: var(--muted); }
.user-status { padding: 2px 8px; border-radius: 10px; font-size: 10px; font-weight: 600; text-transform: uppercase; }
.user-status.active { background: #dcfce7; color: #16a34a; }
.user-status.suspended { background: #fee2e2; color: #dc2626; }
.user-actions { display: flex; gap: 6px; align-items: center; }
.btn-sm-action { padding: 4px 10px; border-radius: 6px; border: 1px solid var(--border); cursor: pointer; font-size: 11px; background: var(--card); color: var(--text-primary); transition: all 0.15s; }
.btn-sm-action:hover { background: var(--brand-primary); color: white; border-color: var(--brand-primary); }
.btn-sm-action.danger:hover { background: #dc2626; border-color: #dc2626; }
.no-users { padding: 12px 0; color: var(--muted); font-size: 12px; font-style: italic; }
.btn-add-user { display: flex; align-items: center; gap: 6px; padding: 8px 16px; background: var(--brand-primary); color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 12px; font-weight: 600; transition: opacity 0.15s; }
.btn-add-user:hover { opacity: 0.9; }
.modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
.modal-overlay.active { display: flex; }
.modal-box { background: var(--card); border-radius: 12px; padding: 24px; width: 420px; max-width: 95%; box-shadow: var(--shadow-lg, 0 20px 60px rgba(0,0,0,0.3)); }
.modal-box h3 { margin: 0 0 16px 0; font-size: 16px; font-weight: 700; color: var(--text-primary); }
.form-group { margin-bottom: 14px; }
.form-group label { display: block; font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.3px; }
.form-group input, .form-group select { width: 100%; padding: 8px 12px; border: 1px solid var(--border); border-radius: 8px; font-size: 13px; background: var(--card); color: var(--text-primary); }
.form-group input:focus, .form-group select:focus { outline: none; border-color: var(--brand-primary); box-shadow: 0 0 0 2px rgba(231,0,69,0.15); }
.modal-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; }
.modal-actions button { padding: 8px 20px; border-radius: 8px; border: none; cursor: pointer; font-size: 13px; font-weight: 600; }
.btn-cancel { background: var(--surface-secondary, #f1f1f1); color: var(--text-primary); }
.btn-submit { background: var(--brand-primary); color: white; }
.btn-submit:hover { opacity: 0.9; }
.toast { position: fixed; top: 20px; right: 20px; padding: 12px 20px; border-radius: 8px; font-size: 13px; font-weight: 600; z-index: 2000; display: none; box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
.toast.success { background: #16a34a; color: white; }
.toast.error { background: #dc2626; color: white; }
</style>
@endsection

@section('content')
<div style="padding: 24px;">
    <div class="pluxee-header">
        <h1>
            <i class="fas fa-building" style="color: #E30045;"></i>
            Gestion des utilisateurs Pluxee
            <span class="badge" id="total-users-badge">0</span>
        </h1>
        <button class="btn-add-user" onclick="openCreateModal()">
            <i class="fas fa-plus"></i> Nouvel utilisateur
        </button>
    </div>

    <div class="pluxee-grid" id="campaigns-grid">
        <div style="grid-column: 1/-1; text-align: center; padding: 40px; color: var(--muted);">
            <i class="fas fa-spinner fa-spin" style="font-size: 24px;"></i>
            <p>Chargement des campagnes...</p>
        </div>
    </div>
</div>

<!-- Modal: Create User -->
<div class="modal-overlay" id="create-modal">
    <div class="modal-box">
        <h3><i class="fas fa-user-plus" style="color: #E30045;"></i> Creer un acces campagne</h3>
        <form id="create-form" onsubmit="return createUser(event)">
            <div class="form-group">
                <label>Campagne</label>
                <select name="campaign_name" id="campaign-select" required>
                    <option value="">Selectionner une campagne...</option>
                </select>
            </div>
            <div class="form-group">
                <label>Nom complet</label>
                <input type="text" name="user_name" required placeholder="Ex: Responsable Marketing">
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="user_email" required placeholder="Ex: user@pluxee.tn">
            </div>
            <div class="form-group">
                <label>Mot de passe</label>
                <input type="password" name="user_password" required minlength="8" placeholder="Min. 8 caracteres">
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeCreateModal()">Annuler</button>
                <button type="submit" class="btn-submit">Creer</button>
            </div>
        </form>
    </div>
</div>

<!-- Toast -->
<div class="toast" id="toast"></div>
@endsection

@section('scripts')
<script>
const CSRF_TOKEN = '{{ csrf_token() }}';
let campaignsData = [];
let usersData = [];

document.addEventListener('DOMContentLoaded', loadData);

async function loadData() {
    try {
        const [campaignsRes, usersRes] = await Promise.all([
            fetch('{{ route("admin.pluxee.campaigns") }}'),
            fetch('{{ route("admin.pluxee.users.list") }}')
        ]);
        const campaignsJson = await campaignsRes.json();
        const usersJson = await usersRes.json();
        campaignsData = campaignsJson.campaigns || [];
        usersData = usersJson.users || [];
        renderCampaigns();
        populateCampaignSelect();
    } catch (e) {
        showToast('Erreur de chargement: ' + e.message, 'error');
    }
}

function renderCampaigns() {
    const grid = document.getElementById('campaigns-grid');
    document.getElementById('total-users-badge').textContent = usersData.length;

    if (campaignsData.length === 0) {
        grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:40px;color:var(--muted);">Aucune campagne Pluxee trouvee.</div>';
        return;
    }

    grid.innerHTML = campaignsData.map(campaign => {
        const campaignUsers = usersData.filter(u => u.pluxee_campaign_access === campaign.store_name);
        return `
        <div class="campaign-card">
            <div class="campaign-card-header">
                <h3>${campaign.store_name}</h3>
                <span style="font-size:11px;color:var(--muted);">ID: ${campaign.store_id}</span>
            </div>
            <div class="campaign-stats">
                <div class="campaign-stat">
                    <div class="campaign-stat-value">${campaign.client_count}</div>
                    <div class="campaign-stat-label">Clients</div>
                </div>
                <div class="campaign-stat">
                    <div class="campaign-stat-value">${campaign.active_subscriptions}</div>
                    <div class="campaign-stat-label">Abos actifs</div>
                </div>
                <div class="campaign-stat">
                    <div class="campaign-stat-value">${campaignUsers.length}</div>
                    <div class="campaign-stat-label">Utilisateurs</div>
                </div>
            </div>
            <div class="campaign-users">
                <div class="campaign-users-title">Utilisateurs dashboard</div>
                ${campaignUsers.length === 0 ? '<div class="no-users">Aucun utilisateur assigne</div>' :
                    campaignUsers.map(u => `
                    <div class="user-row">
                        <div class="user-info">
                            <span class="user-name">${u.name}</span>
                            <span class="user-email">${u.email}</span>
                        </div>
                        <div class="user-actions">
                            <span class="user-status ${u.status}">${u.status}</span>
                            ${u.status === 'active'
                                ? `<button class="btn-sm-action danger" onclick="toggleUser(${u.id},'deactivate')">Desactiver</button>`
                                : `<button class="btn-sm-action" onclick="toggleUser(${u.id},'activate')">Reactiver</button>`
                            }
                        </div>
                    </div>`).join('')
                }
            </div>
        </div>`;
    }).join('');
}

function populateCampaignSelect() {
    const sel = document.getElementById('campaign-select');
    sel.innerHTML = '<option value="">Selectionner une campagne...</option>' +
        campaignsData.map(c => `<option value="${c.store_name}">${c.store_name}</option>`).join('');
}

function openCreateModal() { document.getElementById('create-modal').classList.add('active'); }
function closeCreateModal() { document.getElementById('create-modal').classList.remove('active'); document.getElementById('create-form').reset(); }

async function createUser(e) {
    e.preventDefault();
    const form = document.getElementById('create-form');
    const data = Object.fromEntries(new FormData(form));

    try {
        const res = await fetch('{{ route("admin.pluxee.users.create") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
            body: JSON.stringify(data)
        });
        const json = await res.json();
        if (json.success) {
            showToast('Utilisateur cree: ' + json.email, 'success');
            closeCreateModal();
            loadData();
        } else {
            const errors = json.errors ? Object.values(json.errors).flat().join(', ') : json.error;
            showToast('Erreur: ' + errors, 'error');
        }
    } catch (e) {
        showToast('Erreur: ' + e.message, 'error');
    }
}

async function toggleUser(userId, action) {
    try {
        const res = await fetch(`/admin/pluxee/users/${userId}/${action}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN }
        });
        const json = await res.json();
        if (json.success) {
            showToast(json.message, 'success');
            loadData();
        } else {
            showToast(json.error, 'error');
        }
    } catch (e) {
        showToast('Erreur: ' + e.message, 'error');
    }
}

function showToast(msg, type) {
    const t = document.getElementById('toast');
    t.className = 'toast ' + type;
    t.textContent = msg;
    t.style.display = 'block';
    setTimeout(() => t.style.display = 'none', 3500);
}
</script>
@endsection
