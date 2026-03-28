/**
 * Reporting Module - Club Privileges Dashboard
 * Manages recipients CRUD, report sending, and logs display
 */

(function() {
  'use strict';

  const API_BASE = '/api/reports';
  let allRecipients = [];

  // Auto-load when Reporting tab becomes visible
  const originalShowTab = window.showTab;
  window.showTab = function(tab) {
    originalShowTab(tab);
    if (tab === 'reporting') {
      loadRecipients();
      loadReportLogs();
      loadScheduleInfo();
    }
  };

  // ── Recipients CRUD ──

  window.loadRecipients = async function() {
    try {
      const resp = await fetch(API_BASE + '/recipients');
      const data = await resp.json();
      allRecipients = data.recipients || [];
      renderRecipients();
    } catch (e) {
      console.error('Failed to load recipients:', e);
    }
  };

  function renderRecipients() {
    const tbody = document.getElementById('recipientsTableBody');
    if (!tbody) return;
    const filter = document.getElementById('recipientTypeFilter')?.value || '';
    const filtered = filter ? allRecipients.filter(r => r.type === filter) : allRecipients;

    if (filtered.length === 0) {
      tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; color: var(--muted); padding: 24px;">Aucun destinataire configure. Cliquez sur "Ajouter un destinataire" pour commencer.</td></tr>';
      return;
    }

    const typeBadge = {
      ceo: '<span class="badge badge-primary" style="font-size:0.7rem;">CEO</span>',
      marketing: '<span class="badge badge-info" style="font-size:0.7rem;">Marketing</span>',
      partner: '<span class="badge badge-success" style="font-size:0.7rem;">Partenaire</span>'
    };

    tbody.innerHTML = filtered.map(r => `
      <tr data-testid="recipient-row-${r.id}">
        <td style="font-weight:600;">${escHtml(r.name)}</td>
        <td>${escHtml(r.email)}</td>
        <td>${typeBadge[r.type] || r.type}</td>
        <td>${r.partner_name ? escHtml(r.partner_name) : '<span style="color:var(--muted)">—</span>'}</td>
        <td>
          <button onclick="toggleRecipientStatus(${r.id})" style="background:none; border:none; cursor:pointer;" data-testid="toggle-recipient-${r.id}">
            <span style="display:inline-block; width:10px; height:10px; border-radius:50%; background:${r.is_active ? '#10b981' : '#71717a'}; margin-right:4px;"></span>
            <span style="color:${r.is_active ? '#10b981' : '#71717a'}; font-size:0.8rem;">${r.is_active ? 'Actif' : 'Inactif'}</span>
          </button>
        </td>
        <td style="font-size:0.8rem; color:var(--muted);">${r.last_sent || '—'} ${r.last_status === 'failed' ? '<span style="color:#ef4444;">&#9888;</span>' : ''}</td>
        <td>
          <div style="display:flex; gap:4px;">
            <button onclick="sendReportTo(${r.id}, '${escHtml(r.email)}')" title="Envoyer maintenant" style="background:none; border:1px solid var(--border); border-radius:6px; padding:4px 8px; cursor:pointer; color:var(--brand-primary);">
              <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/></svg>
            </button>
            <button onclick="editRecipient(${r.id})" title="Modifier" style="background:none; border:1px solid var(--border); border-radius:6px; padding:4px 8px; cursor:pointer; color:var(--accent);">
              <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </button>
            <button onclick="deleteRecipient(${r.id}, '${escHtml(r.name)}')" title="Supprimer" style="background:none; border:1px solid var(--border); border-radius:6px; padding:4px 8px; cursor:pointer; color:#ef4444;">
              <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
            </button>
          </div>
        </td>
      </tr>
    `).join('');
  }

  // ── Modal management ──

  window.openAddRecipientModal = function() {
    document.getElementById('recipientModalTitle').textContent = 'Ajouter un destinataire';
    document.getElementById('recipientId').value = '';
    document.getElementById('recipientName').value = '';
    document.getElementById('recipientEmail').value = '';
    document.getElementById('recipientType').value = '';
    document.getElementById('recipientPartnerId').value = '';
    document.getElementById('partnerSearch').value = '';
    document.getElementById('recipientDay').value = 'monday';
    document.getElementById('recipientTime').value = '08:00';
    togglePartnerField();
    showModal();
  };

  window.editRecipient = function(id) {
    const r = allRecipients.find(rec => rec.id === id);
    if (!r) return;
    document.getElementById('recipientModalTitle').textContent = 'Modifier le destinataire';
    document.getElementById('recipientId').value = r.id;
    document.getElementById('recipientName').value = r.name;
    document.getElementById('recipientEmail').value = r.email;
    document.getElementById('recipientType').value = r.type;
    document.getElementById('recipientPartnerId').value = r.partner_id || '';
    document.getElementById('partnerSearch').value = r.partner_name || '';
    document.getElementById('recipientDay').value = r.schedule_day || 'monday';
    document.getElementById('recipientTime').value = r.schedule_time || '08:00';
    togglePartnerField();
    showModal();
  };

  window.closeRecipientModal = function() {
    document.getElementById('recipientModal').style.display = 'none';
  };

  function showModal() {
    document.getElementById('recipientModal').style.display = 'flex';
  }

  window.togglePartnerField = function() {
    const type = document.getElementById('recipientType').value;
    document.getElementById('partnerFieldGroup').style.display = type === 'partner' ? 'block' : 'none';
  };

  // ── Partner search ──

  let partnerSearchTimeout;
  window.searchPartners = function() {
    clearTimeout(partnerSearchTimeout);
    const query = document.getElementById('partnerSearch').value;
    if (query.length < 2) {
      document.getElementById('partnerSearchResults').innerHTML = '';
      return;
    }
    partnerSearchTimeout = setTimeout(async () => {
      try {
        const resp = await fetch(API_BASE + '/partners?q=' + encodeURIComponent(query));
        const data = await resp.json();
        const results = data.partners || [];
        const container = document.getElementById('partnerSearchResults');
        if (results.length === 0) {
          container.innerHTML = '<div style="padding:8px; color:var(--muted); font-size:0.8rem;">Aucun partenaire trouve</div>';
          return;
        }
        container.innerHTML = results.map(p => `
          <div onclick="selectPartner(${p.partner_id}, '${escHtml(p.partner_name)}', '${escHtml(p.partner_mail)}')" 
               style="padding:8px 12px; cursor:pointer; border-bottom:1px solid var(--border); color:var(--text-primary); font-size:0.85rem; transition:background 0.15s;"
               onmouseover="this.style.background='rgba(108,75,160,0.08)'" onmouseout="this.style.background='transparent'">
            <strong>${escHtml(p.partner_name)}</strong> <span style="color:var(--muted); font-size:0.75rem;">${escHtml(p.partner_mail)}</span>
          </div>
        `).join('');
      } catch (e) {
        console.error('Partner search error:', e);
      }
    }, 300);
  };

  window.selectPartner = function(id, name, email) {
    document.getElementById('recipientPartnerId').value = id;
    document.getElementById('partnerSearch').value = name;
    if (!document.getElementById('recipientEmail').value) {
      document.getElementById('recipientEmail').value = email;
    }
    if (!document.getElementById('recipientName').value) {
      document.getElementById('recipientName').value = name;
    }
    document.getElementById('partnerSearchResults').innerHTML = '';
  };

  // ── Save recipient ──

  window.saveRecipient = async function(event) {
    event.preventDefault();
    const id = document.getElementById('recipientId').value;
    const payload = {
      name: document.getElementById('recipientName').value,
      email: document.getElementById('recipientEmail').value,
      type: document.getElementById('recipientType').value,
      partner_id: document.getElementById('recipientPartnerId').value || null,
      schedule_day: document.getElementById('recipientDay').value,
      schedule_time: document.getElementById('recipientTime').value,
      is_active: true
    };

    if (payload.type === 'partner' && !payload.partner_id) {
      showNotification('Veuillez selectionner un partenaire.', 'error');
      return;
    }

    try {
      const url = id ? `${API_BASE}/recipients/${id}` : `${API_BASE}/recipients`;
      const method = id ? 'PUT' : 'POST';
      const resp = await fetch(url, {
        method,
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
        body: JSON.stringify(payload)
      });
      const data = await resp.json();
      if (!resp.ok) {
        showNotification(data.error || 'Erreur lors de la sauvegarde.', 'error');
        return;
      }
      showNotification(data.message || 'Sauvegarde reussie.', 'success');
      closeRecipientModal();
      loadRecipients();
    } catch (e) {
      showNotification('Erreur reseau.', 'error');
    }
  };

  // ── Toggle / Delete ──

  window.toggleRecipientStatus = async function(id) {
    try {
      const resp = await fetch(`${API_BASE}/recipients/${id}/toggle`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' }
      });
      if (resp.ok) loadRecipients();
    } catch (e) {
      showNotification('Erreur.', 'error');
    }
  };

  window.deleteRecipient = async function(id, name) {
    if (!confirm(`Supprimer le destinataire "${name}" ?`)) return;
    try {
      const resp = await fetch(`${API_BASE}/recipients/${id}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' }
      });
      if (resp.ok) {
        showNotification('Destinataire supprime.', 'success');
        loadRecipients();
      }
    } catch (e) {
      showNotification('Erreur.', 'error');
    }
  };

  // ── Send reports ──

  window.sendReportTo = async function(id, email) {
    if (!confirm(`Envoyer le rapport a ${email} maintenant ?`)) return;
    showNotification('Envoi en cours...', 'info');
    try {
      const resp = await fetch(`${API_BASE}/send`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
        body: JSON.stringify({ recipient_id: id })
      });
      const data = await resp.json();
      if (resp.ok) {
        showNotification(data.message || 'Rapport envoye!', 'success');
        loadReportLogs();
        loadRecipients();
      } else {
        showNotification(data.error || 'Echec de l\'envoi.', 'error');
      }
    } catch (e) {
      showNotification('Erreur reseau.', 'error');
    }
  };

  window.sendAllReportsNow = async function() {
    if (!confirm('Envoyer les rapports a TOUS les destinataires actifs maintenant ?')) return;
    showNotification('Envoi de tous les rapports en cours...', 'info');
    try {
      const resp = await fetch(`${API_BASE}/send`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
        body: JSON.stringify({})
      });
      const data = await resp.json();
      if (resp.ok) {
        showNotification(data.message || 'Envoi termine!', 'success');
        loadReportLogs();
        loadRecipients();
      } else {
        showNotification(data.error || 'Echec.', 'error');
      }
    } catch (e) {
      showNotification('Erreur reseau.', 'error');
    }
  };

  // ── Logs ──

  async function loadReportLogs() {
    try {
      const resp = await fetch(API_BASE + '/logs');
      const data = await resp.json();
      renderLogs(data.logs || []);
    } catch (e) {
      console.error('Failed to load logs:', e);
    }
  }
  window.loadReportLogs = loadReportLogs;

  function renderLogs(logs) {
    const tbody = document.getElementById('reportLogsTableBody');
    if (!tbody) return;
    if (logs.length === 0) {
      tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; color:var(--muted); padding:24px;">Aucun envoi enregistre.</td></tr>';
      return;
    }
    const statusColor = { sent: '#10b981', failed: '#ef4444', pending: '#f59e0b' };
    const statusLabel = { sent: 'Envoye', failed: 'Echoue', pending: 'En cours' };
    tbody.innerHTML = logs.map(l => `
      <tr>
        <td style="font-size:0.8rem;">${l.created_at}</td>
        <td>${escHtml(l.recipient_name || '?')}<br><span style="color:var(--muted);font-size:0.75rem;">${escHtml(l.recipient_email || '')}</span></td>
        <td style="font-size:0.8rem;">${l.report_type}</td>
        <td style="font-size:0.8rem;">${l.period}</td>
        <td><span style="color:${statusColor[l.status] || '#a1a1aa'}; font-weight:600; font-size:0.8rem;">${statusLabel[l.status] || l.status}</span></td>
        <td style="text-align:center;">${l.has_ai ? '<span style="color:#D4A843;" title="Suggestions IA incluses">&#9733;</span>' : '—'}</td>
        <td style="font-size:0.75rem; color:#ef4444; max-width:200px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="${escHtml(l.error || '')}">${l.error ? escHtml(l.error) : '—'}</td>
      </tr>
    `).join('');
  }

  // ── Schedule info ──

  async function loadScheduleInfo() {
    try {
      const resp = await fetch(API_BASE + '/schedule');
      const data = await resp.json();
      const el1 = document.getElementById('reportingActiveCount');
      const el2 = document.getElementById('reportingLastRun');
      if (el1) el1.textContent = data.recipients_count || 0;
      if (el2) el2.textContent = data.last_run || 'Jamais';
    } catch (e) {
      console.error('Failed to load schedule info:', e);
    }
  }

  // ── Helpers ──

  function escHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }
})();
