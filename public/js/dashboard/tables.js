/**
 * Dashboard - Module Tables
 * Tableaux de données : statistiques quotidiennes, marchands, abonnements
 */

let allDailyStatistics = [];
let currentDailyStatsSortColumn = -1;
let dailyStatsSortDirection = 'asc';

// Fonction pour mettre à jour le tableau des statistiques quotidiennes
function updateDailyStatisticsTable(subscriptions) {
  const tbody = document.getElementById('daily-statistics-body');
  if (!tbody) return;
  
  // Récupérer les statistiques quotidiennes
  let dailyStats = [];
  if (subscriptions && subscriptions.daily_statistics && Array.isArray(subscriptions.daily_statistics)) {
    dailyStats = subscriptions.daily_statistics;
  }
  
  allDailyStatistics = dailyStats;
  
  if (!dailyStats || dailyStats.length === 0) {
    tbody.innerHTML = '<tr><td colspan="12" class="no-data" style="text-align: center; padding: 40px; color: var(--muted);">Aucune donnée disponible</td></tr>';
    return;
  }
  
  renderDailyStatisticsTable();
}

// Fonction pour afficher le tableau des statistiques quotidiennes
function renderDailyStatisticsTable() {
  const tbody = document.getElementById('daily-statistics-body');
  if (!tbody) return;
  
  if (!allDailyStatistics || allDailyStatistics.length === 0) {
    tbody.innerHTML = '<tr><td colspan="12" class="no-data" style="text-align: center; padding: 40px; color: var(--muted);">Aucune donnée disponible</td></tr>';
    return;
  }
  
  tbody.innerHTML = allDailyStatistics.map(row => {
    const dimension = row.dimension || '-';
    const offre = row.offre || 'N/A';
    const newSub = row.new_sub || 0;
    const unsub = row.unsub || 0;
    const simchurn = row.simchurn || 0;
    const revSimchurn = row.rev_simchurn || 0;
    const activeSub = row.active_sub || 0;
    const nbFacturation = row.nb_facturation || 0;
    const tauxFacturation = row.taux_facturation || 0;
    const revenuTTC = row.revenu_ttc_tnd || row.revenu_ttc_local || 0;
    const revenuUSD = row.revenu_ttc_usd || 0;
    const revenuTND = row.revenu_ttc_tnd || row.revenu_ttc_local || 0;
    
    return `
      <tr>
        <td>${dimension}</td>
        <td>${offre}</td>
        <td>${newSub}</td>
        <td>${unsub}</td>
        <td>${simchurn}</td>
        <td>${revSimchurn}</td>
        <td>${activeSub.toLocaleString()}</td>
        <td>${nbFacturation.toLocaleString()}</td>
        <td>${tauxFacturation.toFixed(2)}%</td>
        <td>${revenuTTC.toFixed(2)}</td>
        <td>${revenuUSD.toFixed(2)}</td>
        <td>${revenuTND.toFixed(2)}</td>
      </tr>
    `;
  }).join('');
}

// Fonction pour trier le tableau des statistiques quotidiennes
function sortDailyStatistics(columnIndex) {
  if (currentDailyStatsSortColumn === columnIndex) {
    dailyStatsSortDirection = dailyStatsSortDirection === 'asc' ? 'desc' : 'asc';
  } else {
    currentDailyStatsSortColumn = columnIndex;
    dailyStatsSortDirection = 'asc';
  }
  
  allDailyStatistics.sort((a, b) => {
    let aVal, bVal;
    
    switch(columnIndex) {
      case 0: aVal = a.dimension; bVal = b.dimension; break;
      case 1: aVal = a.offre; bVal = b.offre; break;
      case 2: aVal = a.new_sub || 0; bVal = b.new_sub || 0; break;
      case 3: aVal = a.unsub || 0; bVal = b.unsub || 0; break;
      case 4: aVal = a.simchurn || 0; bVal = b.simchurn || 0; break;
      case 5: aVal = a.rev_simchurn || 0; bVal = b.rev_simchurn || 0; break;
      case 6: aVal = a.active_sub || 0; bVal = b.active_sub || 0; break;
      case 7: aVal = a.nb_facturation || 0; bVal = b.nb_facturation || 0; break;
      case 8: aVal = a.taux_facturation || 0; bVal = b.taux_facturation || 0; break;
      case 9: aVal = a.revenu_ttc_local || 0; bVal = b.revenu_ttc_local || 0; break;
      case 10: aVal = a.revenu_ttc_usd || 0; bVal = b.revenu_ttc_usd || 0; break;
      case 11: aVal = a.revenu_ttc_tnd || 0; bVal = b.revenu_ttc_tnd || 0; break;
      default: return 0;
    }
    
    if (typeof aVal === 'string') {
      return dailyStatsSortDirection === 'asc' ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
    } else {
      return dailyStatsSortDirection === 'asc' ? aVal - bVal : bVal - aVal;
    }
  });
  
  renderDailyStatisticsTable();
}

// Fonction pour filtrer le tableau des statistiques quotidiennes
function filterDailyStatistics() {
  const searchInput = document.getElementById('daily-stats-search');
  if (!searchInput) return;
  
  const searchTerm = searchInput.value.toLowerCase();
  
  if (!searchTerm) {
    renderDailyStatisticsTable();
    return;
  }
  
  const filtered = allDailyStatistics.filter(row => {
    return (
      (row.dimension && row.dimension.toLowerCase().includes(searchTerm)) ||
      (row.offre && row.offre.toLowerCase().includes(searchTerm)) ||
      String(row.new_sub || '').includes(searchTerm) ||
      String(row.unsub || '').includes(searchTerm) ||
      String(row.active_sub || '').includes(searchTerm)
    );
  });
  
  const tbody = document.getElementById('daily-statistics-body');
  if (!tbody) return;
  
  if (filtered.length === 0) {
    tbody.innerHTML = '<tr><td colspan="12" class="no-data" style="text-align: center; padding: 40px; color: var(--muted);">Aucun résultat trouvé</td></tr>';
    return;
  }
  
  tbody.innerHTML = filtered.map(row => {
    const dimension = row.dimension || '-';
    const offre = row.offre || 'N/A';
    const newSub = row.new_sub || 0;
    const unsub = row.unsub || 0;
    const simchurn = row.simchurn || 0;
    const revSimchurn = row.rev_simchurn || 0;
    const activeSub = row.active_sub || 0;
    const nbFacturation = row.nb_facturation || 0;
    const tauxFacturation = row.taux_facturation || 0;
    const revenuTTC = row.revenu_ttc_tnd || row.revenu_ttc_local || 0;
    const revenuUSD = row.revenu_ttc_usd || 0;
    const revenuTND = row.revenu_ttc_tnd || row.revenu_ttc_local || 0;
    
    return `
      <tr>
        <td>${dimension}</td>
        <td>${offre}</td>
        <td>${newSub}</td>
        <td>${unsub}</td>
        <td>${simchurn}</td>
        <td>${revSimchurn}</td>
        <td>${activeSub.toLocaleString()}</td>
        <td>${nbFacturation.toLocaleString()}</td>
        <td>${tauxFacturation.toFixed(2)}%</td>
        <td>${revenuTTC.toFixed(2)}</td>
        <td>${revenuUSD.toFixed(2)}</td>
        <td>${revenuTND.toFixed(2)}</td>
      </tr>
    `;
  }).join('');
}

// Fonction pour exporter en Excel (simplifiée - copie dans le presse-papier)
function exportDailyStatistics() {
  if (!allDailyStatistics || allDailyStatistics.length === 0) {
    alert('Aucune donnée à exporter');
    return;
  }
  
  // Créer le CSV
  let csv = 'Dimension,Offre,New sub,Unsub,Simchurn,Rev Simchurn,Active Sub,NB facturation,Taux Facturation,Revenu TTC local,Revenu TTC USD,Revenu TTC TND\n';
  
  allDailyStatistics.forEach(row => {
    csv += `${row.dimension || ''},${row.offre || 'N/A'},${row.new_sub || 0},${row.unsub || 0},${row.simchurn || 0},${row.rev_simchurn || 0},${row.active_sub || 0},${row.nb_facturation || 0},${row.taux_facturation || 0},${row.revenu_ttc_local || 0},${row.revenu_ttc_usd || 0},${row.revenu_ttc_tnd || 0}\n`;
  });
  
  // Créer un blob et télécharger
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
  const link = document.createElement('a');
  const url = URL.createObjectURL(blob);
  link.setAttribute('href', url);
  link.setAttribute('download', `statistiques_quotidiennes_${new Date().toISOString().split('T')[0]}.csv`);
  link.style.visibility = 'hidden';
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
}

// Fonction pour copier les données
function copyDailyStatistics() {
  if (!allDailyStatistics || allDailyStatistics.length === 0) {
    alert('Aucune donnée à copier');
    return;
  }
  
  // Créer le texte tabulé
  let text = 'Dimension\tOffre\tNew sub\tUnsub\tSimchurn\tRev Simchurn\tActive Sub\tNB facturation\tTaux Facturation\tRevenu TTC local\tRevenu TTC USD\tRevenu TTC TND\n';
  
  allDailyStatistics.forEach(row => {
    text += `${row.dimension || ''}\t${row.offre || 'N/A'}\t${row.new_sub || 0}\t${row.unsub || 0}\t${row.simchurn || 0}\t${row.rev_simchurn || 0}\t${row.active_sub || 0}\t${row.nb_facturation || 0}\t${row.taux_facturation || 0}\t${row.revenu_ttc_local || 0}\t${row.revenu_ttc_usd || 0}\t${row.revenu_ttc_tnd || 0}\n`;
  });
  
  navigator.clipboard.writeText(text).then(() => {
    alert('Données copiées dans le presse-papier !');
  }).catch(err => {
    console.error('Erreur lors de la copie:', err);
    alert('Erreur lors de la copie');
  });
}

// ===== FONCTIONS TIMWE =====

// ========================================
// Merchants & Subscriptions Tables
// ========================================

function updateMerchantsTable(merchants) {
  // merchants peut être un objet {data: [...], categories: [...]} ou un tableau
  const merchantsList = Array.isArray(merchants) ? merchants : (Array.isArray(merchants?.data) ? merchants.data : []);
  allMerchants = merchantsList;
  currentMerchantsPage = 1;
  
  if (!allMerchants || allMerchants.length === 0) {
    const tbody = document.getElementById('merchantsTableBody');
    if (tbody) {
      tbody.innerHTML = '<tr><td colspan="7" class="no-data" style="text-align: center; padding: 40px; color: var(--muted);">Aucun marchand disponible</td></tr>';
    }
    // Mettre à jour la pagination
    document.getElementById('merchantsPaginationInfo').textContent = 'Affichage de 0-0 sur 0 marchands';
    document.getElementById('merchantsPrevBtn').disabled = true;
    document.getElementById('merchantsNextBtn').disabled = true;
    return;
  }
  
  renderMerchantsPage();
}

// Variables globales pour la pagination des abonnements
let allSubscriptionDetails = [];
let currentSubscriptionPage = 1;
let subscriptionsPerPage = 25;

// Update subscriptions table with details
function updateSubscriptionsTable(subscriptions) {
  const tbody = document.getElementById('subs-details-body');
  if (!tbody) return;
  
  // Afficher indicateur de chargement spécifique
  tbody.innerHTML = '<tr><td colspan="6" class="loading">🔄 Chargement des détails...</td></tr>';
  
  // Gestion de la nouvelle structure avec meta
  let detailsData = [];
  let meta = null;
  
  if (subscriptions && subscriptions.details) {
    if (Array.isArray(subscriptions.details)) {
      // Ancienne structure (compatibilité)
      detailsData = subscriptions.details;
    } else if (subscriptions.details.data && Array.isArray(subscriptions.details.data)) {
      // Nouvelle structure avec meta
      detailsData = subscriptions.details.data;
      meta = subscriptions.details.meta;
    } else if (subscriptions.details.data === undefined && Object.keys(subscriptions.details).length > 0) {
      // Si c'est un objet avec des propriétés mais pas de .data, peut-être que c'est déjà un tableau d'objets
      const testItem = subscriptions.details[0] || subscriptions.details;
      if (testItem && (testItem.first_name !== undefined || testItem.client_prenom !== undefined)) {
        detailsData = Array.isArray(subscriptions.details) ? subscriptions.details : [subscriptions.details];
      }
    }
  }
  
  // Si pas de données, afficher le message
  if (!detailsData || detailsData.length === 0) {
    tbody.innerHTML = '<tr><td colspan="7" class="no-data" style="text-align: center; padding: 40px; color: var(--muted);">Aucune donnée disponible</td></tr>';
    return;
  }
  
  // Simule un petit délai pour montrer le chargement
  setTimeout(() => {
    allSubscriptionDetails = detailsData;
    currentSubscriptionPage = 1;
    renderSubscriptionsPage();
    
    // Afficher les informations de performance
    if (meta) {
      updateSubscriptionTableInfo(meta);
    }
  }, 100);
}

function updateSubscriptionTableInfo(meta) {
  const tableTitle = document.querySelector('#subscriptions .table-title');
  if (tableTitle && meta) {
    const infoSpan = tableTitle.querySelector('.table-info') || document.createElement('span');
    infoSpan.className = 'table-info';
    infoSpan.innerHTML = ` <small style="color: #666; font-weight: normal;">(${meta.total_count} clients - ${meta.execution_time_ms}ms)</small>`;
    
    if (!tableTitle.querySelector('.table-info')) {
      tableTitle.appendChild(infoSpan);
    }
  }
}

// Normalise une ligne d'abonnement (formats Laravel ou tableaux associatifs)
// Utilisée à la fois par le rendu du tableau et par l'export CSV.
function normalizeSubscriptionRow(row) {
  const firstName = row.first_name || row.client_prenom || '';
  const lastName = row.last_name || row.client_nom || '';
  const activationDate = row.activation_date || row.client_abonnement_creation || null;
  const endDate = row.end_date || row.client_abonnement_expiration || null;
  const formatDate = (value) =>
    value ? (typeof value === 'string' ? value.substring(0, 10) : value) : '-';

  return {
    fullName: `${firstName} ${lastName}`.trim() || '-',
    phone: row.phone || row.client_telephone || '-',
    operator: row.operator || row.country_payments_methods_name || '-',
    plan: row.plan || '-',
    clientId: row.client_id || null,
    activationDate: formatDate(activationDate),
    endDate: formatDate(endDate)
  };
}

function renderSubscriptionsPage() {
  const tbody = document.getElementById('subs-details-body');
  if (!tbody) return;

  const startIndex = (currentSubscriptionPage - 1) * subscriptionsPerPage;
  const endIndex = startIndex + subscriptionsPerPage;
  const pageData = allSubscriptionDetails.slice(startIndex, endIndex);

  if (!pageData || pageData.length === 0) {
    tbody.innerHTML = '<tr><td colspan="7" class="no-data" style="text-align: center; padding: 40px; color: var(--muted);">Aucune donnée disponible</td></tr>';
    return;
  }

  tbody.innerHTML = pageData.map(rawRow => {
    const {
      fullName,
      phone,
      operator,
      plan,
      clientId,
      activationDate: formattedActivation,
      endDate: formattedEnd
    } = normalizeSubscriptionRow(rawRow);

    const planBadgeClass =
      plan === 'Trial' ? 'badge-primary' :
      plan === 'Journalier' ? 'badge-warning' :
      plan === 'Mensuel' ? 'badge-info' :
      plan === 'Annuel' ? 'badge-success' : 'badge-secondary';

    // Bouton détails (seulement si client_id est disponible)
    // Échapper les apostrophes dans le nom pour éviter les erreurs JavaScript
    const escapedName = fullName.replace(/'/g, "\\'").replace(/"/g, '&quot;');
    const detailsButton = clientId ? 
      `<button onclick="showUserSubscriptionsDetails(${clientId}, '${escapedName}')" class="btn-details" style="padding: 6px 12px; background: var(--accent); color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 13px; transition: all 0.2s; font-weight: 500;" onmouseover="this.style.background='var(--brand-primary)'" onmouseout="this.style.background='var(--accent)'">Détails</button>` :
      `<span style="color: var(--muted); font-size: 12px;">-</span>`;
    
    return `
      <tr>
        <td>${fullName}</td>
        <td>${phone}</td>
        <td>${operator}</td>
        <td><span class="badge ${planBadgeClass}">${plan}</span></td>
        <td>${formattedActivation}</td>
        <td>${formattedEnd}</td>
        <td>${detailsButton}</td>
      </tr>
    `;
  }).join('');
  
  updateSubscriptionsPagination();
}

function updateSubscriptionsPagination() {
  const totalPages = Math.ceil(allSubscriptionDetails.length / subscriptionsPerPage);
  const pagination = document.querySelector('.subscriptions-pagination');
  
  if (pagination && totalPages > 1) {
    let paginationHTML = '<div class="pagination-controls">';
    
    // Previous button
    if (currentSubscriptionPage > 1) {
      paginationHTML += `<button onclick="changeSubscriptionPage(${currentSubscriptionPage - 1})" class="pagination-btn">‹ Précédent</button>`;
    }
    
    // Page numbers
    const startPage = Math.max(1, currentSubscriptionPage - 2);
    const endPage = Math.min(totalPages, currentSubscriptionPage + 2);
    
    for (let i = startPage; i <= endPage; i++) {
      const activeClass = i === currentSubscriptionPage ? 'active' : '';
      paginationHTML += `<button onclick="changeSubscriptionPage(${i})" class="pagination-btn ${activeClass}">${i}</button>`;
    }
    
    // Next button
    if (currentSubscriptionPage < totalPages) {
      paginationHTML += `<button onclick="changeSubscriptionPage(${currentSubscriptionPage + 1})" class="pagination-btn">Suivant ›</button>`;
    }
    
    paginationHTML += `</div><div class="pagination-info">Page ${currentSubscriptionPage} sur ${totalPages} (${allSubscriptionDetails.length} éléments)</div>`;
    pagination.innerHTML = paginationHTML;
  }
}

function changeSubscriptionPage(page) {
  currentSubscriptionPage = page;
  renderSubscriptionsPage();
}

function changeSubscriptionsPerPage(perPage) {
  subscriptionsPerPage = parseInt(perPage);
  currentSubscriptionPage = 1;
  renderSubscriptionsPage();
}

// Résout le paramètre `operator` à envoyer à l'API à partir de la sélection
// courante (variable globale partagée avec le reste du dashboard).
function resolveSelectedOperatorParam() {
  if (typeof selectedOperators === 'undefined' || !Array.isArray(selectedOperators) || selectedOperators.length === 0) {
    return 'ALL';
  }
  return selectedOperators.includes('ALL') ? 'ALL' : selectedOperators.join(',');
}

// Exporte TOUS les abonnements de la période sélectionnée.
//
// L'export est délégué au serveur : le tableau affiché est plafonné à 1000
// lignes, donc générer le CSV depuis les données déjà chargées tronquerait
// silencieusement le résultat. L'endpoint streame l'intégralité des lignes.
function exportSubscriptionsToCSV() {
  const startDate = document.getElementById('start-date')?.value;
  const endDate = document.getElementById('end-date')?.value;

  const params = new URLSearchParams();
  if (startDate && endDate) {
    params.append('start_date', startDate);
    params.append('end_date', endDate);
  }
  params.append('operator', resolveSelectedOperatorParam());

  // Ancre invisible plutôt qu'un fetch + Blob : le navigateur écrit le flux
  // directement sur le disque, sans charger tout le CSV en mémoire.
  // Le nom du fichier vient de l'en-tête Content-Disposition du serveur.
  const link = document.createElement('a');
  link.setAttribute('href', `/api/dashboard/export/subscriptions?${params.toString()}`);
  link.setAttribute('download', '');
  link.style.visibility = 'hidden';
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
}

// Fonction pour afficher les détails des abonnements d'un utilisateur
async function showUserSubscriptionsDetails(clientId, clientName) {
  // Valider le clientId
  if (!clientId || isNaN(clientId) || clientId <= 0) {
    console.warn('showUserSubscriptionsDetails: clientId invalide:', clientId);
    return;
  }
  
  // Supprimer la modale existante si elle existe
  const existing = document.getElementById('user-subscriptions-modal');
  if (existing) existing.remove();
  
  const displayName = (clientName && clientName !== '-' && clientName.trim() !== '') ? clientName : 'Client #' + clientId;
  
  // Créer la modale avec indicateur de chargement
  const modal = document.createElement('div');
  modal.id = 'user-subscriptions-modal';
  modal.innerHTML = `
    <div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 10001; display: flex; align-items: center; justify-content: center; padding: 16px;" onclick="if(event.target===this) document.getElementById('user-subscriptions-modal').remove()">
      <div style="background: white; border-radius: 12px; padding: 20px; max-width: 900px; max-height: 85vh; overflow-y: auto; width: 95%; box-sizing: border-box;" onclick="event.stopPropagation()">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
          <h3 style="margin: 0; color: #6C4BA0; font-size: 18px; word-break: break-word;">Abonnements de ${displayName}</h3>
          <button onclick="document.getElementById('user-subscriptions-modal').remove()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #999; padding: 0; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">×</button>
        </div>
        <div id="user-subscriptions-content" style="min-height: 200px;">
          <div style="text-align: center; padding: 40px; color: #999;">
            <div style="margin-bottom: 10px;">Chargement des abonnements...</div>
          </div>
        </div>
      </div>
    </div>
  `;
  document.body.appendChild(modal);
  
  try {
    // Appeler l'API
    const response = await fetch(`/api/dashboard/subscriptions/${parseInt(clientId)}`, {
      method: 'GET',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
      }
    });
    
    if (!response.ok) {
      throw new Error(`Erreur HTTP ${response.status}`);
    }
    
    const data = await response.json();
    const contentDiv = document.getElementById('user-subscriptions-content');
    
    if (!data.success || !data.subscriptions || data.subscriptions.length === 0) {
      contentDiv.innerHTML = `
        <div style="text-align: center; padding: 40px; color: #999;">
          <div style="font-size: 48px; margin-bottom: 10px;">&#128237;</div>
          <div>Aucun abonnement trouvé pour cet utilisateur</div>
        </div>
      `;
      return;
    }
    
    // Afficher les abonnements dans un tableau
    const subscriptions = data.subscriptions;
    const totalSubscriptions = data.total_subscriptions || subscriptions.length;
    
    let tableHTML = `
      <div style="margin-bottom: 15px; color: #666; font-size: 14px;">
        Total: <strong>${totalSubscriptions}</strong> abonnement(s)
      </div>
      <div style="overflow-x: auto; -webkit-overflow-scrolling: touch;">
      <table style="width: 100%; border-collapse: collapse; font-size: 13px; min-width: 600px;">
        <thead>
          <tr style="background: #f5f5f5; border-bottom: 2px solid #e0e0e0;">
            <th style="padding: 10px 8px; text-align: left; font-weight: 600; color: #333;">Opérateur</th>
            <th style="padding: 10px 8px; text-align: left; font-weight: 600; color: #333;">Plan</th>
            <th style="padding: 10px 8px; text-align: left; font-weight: 600; color: #333;">Type</th>
            <th style="padding: 10px 8px; text-align: left; font-weight: 600; color: #333;">Activation</th>
            <th style="padding: 10px 8px; text-align: left; font-weight: 600; color: #333;">Fin</th>
            <th style="padding: 10px 8px; text-align: left; font-weight: 600; color: #333;">Statut</th>
            <th style="padding: 10px 8px; text-align: left; font-weight: 600; color: #333;">Prix</th>
          </tr>
        </thead>
        <tbody>
    `;
    
    subscriptions.forEach(sub => {
      const operator = sub.operator || '-';
      const plan = sub.plan || '-';
      const subscriptionName = sub.subscription_name || '-';
      const activationDate = sub.activation_date ? (typeof sub.activation_date === 'string' ? sub.activation_date.substring(0, 10) : sub.activation_date) : '-';
      const endDate = sub.end_date ? (typeof sub.end_date === 'string' ? sub.end_date.substring(0, 10) : sub.end_date) : '-';
      const status = sub.status || 'Inconnu';
      const price = (plan === 'Trial' || parseFloat(sub.price) === 0) 
        ? '<span style="color: #10b981; font-weight: 600;">Gratuit</span>' 
        : (sub.price ? parseFloat(sub.price).toFixed(2) + ' TND' : '-');
      
      const statusBadge = status === 'Actif' ? 
        '<span style="background: #10b981; color: white; padding: 3px 8px; border-radius: 4px; font-size: 11px;">Actif</span>' :
        '<span style="background: #9ca3af; color: white; padding: 3px 8px; border-radius: 4px; font-size: 11px;">Expiré</span>';
      
      const planColors = {
        'Trial': '#6C4BA0', 'Journalier': '#f59e0b', 'Mensuel': '#D4A843', 'Annuel': '#10b981'
      };
      const planColor = planColors[plan] || '#9ca3af';
      
      tableHTML += `
        <tr style="border-bottom: 1px solid #eee;">
          <td style="padding: 10px 8px;">${operator}</td>
          <td style="padding: 10px 8px;"><span style="background: ${planColor}; color: white; padding: 3px 8px; border-radius: 4px; font-size: 11px;">${plan}</span></td>
          <td style="padding: 10px 8px; font-size: 12px;">${subscriptionName}</td>
          <td style="padding: 10px 8px;">${activationDate}</td>
          <td style="padding: 10px 8px;">${endDate}</td>
          <td style="padding: 10px 8px;">${statusBadge}</td>
          <td style="padding: 10px 8px;">${price}</td>
        </tr>
      `;
    });
    
    tableHTML += `
        </tbody>
      </table>
      </div>
    `;
    
    contentDiv.innerHTML = tableHTML;
    
  } catch (error) {
    console.error('Erreur lors de la récupération des abonnements:', error);
    const contentDiv = document.getElementById('user-subscriptions-content');
    if (contentDiv) {
      contentDiv.innerHTML = `
        <div style="text-align: center; padding: 40px; color: #ef4444;">
          <div style="font-size: 48px; margin-bottom: 10px;">&#9888;</div>
          <div style="font-weight: 600;">Erreur lors du chargement des abonnements</div>
          <div style="font-size: 12px; margin-top: 10px; color: #999;">${error.message || 'Erreur inconnue'}</div>
        </div>
      `;
    }
  }
}

function renderMerchantsPage() {
  const tbody = document.getElementById('merchantsTableBody');
  if (!tbody) return;
  
  const startIndex = (currentMerchantsPage - 1) * merchantsPerPage;
  const endIndex = startIndex + merchantsPerPage;
  const pageData = allMerchants.slice(startIndex, endIndex);
  
  tbody.innerHTML = pageData.map((merchant, index) => {
    const globalIndex = startIndex + index;
    // Calcul du changement plus robuste
    let change = 0;
    let badgeClass = 'badge-info';
    let changeText = 'Nouveau';
    let statusClass = 'badge-success';
    let statusText = 'Actif';
    
    if (merchant.previous > 0) {
      change = ((merchant.current - merchant.previous) / merchant.previous * 100);
      const isPositive = change > 0;
      badgeClass = isPositive ? 'badge-success' : 'badge-danger';
      changeText = `${isPositive ? '+' : ''}${change.toFixed(1)}%`;
    } else if (merchant.current > 0) {
      badgeClass = 'badge-success';
      changeText = 'Nouveau';
    }
    
    // Déterminer le statut basé sur la performance
    if (merchant.current === 0) {
      statusClass = 'badge-danger';
      statusText = 'Inactif';
    } else if (change < -20) {
      statusClass = 'badge-warning';
      statusText = 'En baisse';
    } else if (change > 20) {
      statusClass = 'badge-success';
      statusText = 'En croissance';
    }
    
    const shareVal = (typeof merchant.share === 'number') ? merchant.share : 0;
    
    return `
      <tr>
        <td>
          <div style="display: flex; align-items: center; gap: 8px;">
            <span style="font-size: 16px;">${globalIndex < 3 ? '🏆' : globalIndex < 10 ? '⭐' : '📊'}</span>
            <div>
              <strong>${merchant.name}</strong>
              <div style="font-size: 12px; color: #666; margin-top: 2px;">
                Position: #${globalIndex + 1}
              </div>
            </div>
          </div>
        </td>
        <td>
          <span class="badge badge-info" style="background: #e0f2fe; color: #0277bd;">
            ${merchant.category}
          </span>
        </td>
        <td>
          <strong style="color: var(--brand-red);">${merchant.current.toLocaleString()}</strong>
        </td>
        <td>
          <span style="color: #666;">${merchant.previous.toLocaleString()}</span>
        </td>
        <td>
          <span class="badge ${badgeClass}">${changeText}</span>
        </td>
        <td>
          <div style="display: flex; align-items: center; gap: 8px;">
            <strong>${shareVal}%</strong>
            <div style="width: 60px; height: 4px; background: #e2e8f0; border-radius: 2px; overflow: hidden;">
              <div style="width: ${Math.min(shareVal * 2, 100)}%; height: 100%; background: var(--brand-red);"></div>
            </div>
          </div>
        </td>
        <td>
          <span class="badge ${statusClass}">${statusText}</span>
        </td>
      </tr>
    `;
  }).join('');
  
  updateMerchantsPagination();
}

function updateMerchantsPagination() {
  const totalMerchants = allMerchants.length;
  const totalPages = Math.ceil(totalMerchants / merchantsPerPage);
  const startIndex = (currentMerchantsPage - 1) * merchantsPerPage + 1;
  const endIndex = Math.min(currentMerchantsPage * merchantsPerPage, totalMerchants);
  
  // Update pagination info
  const infoEl = document.getElementById('merchantsPaginationInfo');
  if (infoEl) {
    infoEl.textContent = `Affichage de ${startIndex}-${endIndex} sur ${totalMerchants} marchands`;
  }
  
  // Update page numbers
  const pageNumbersEl = document.getElementById('merchantsPageNumbers');
  if (pageNumbersEl) {
    pageNumbersEl.textContent = `Page ${currentMerchantsPage} sur ${totalPages}`;
  }
  
  // Update button states
  const prevBtn = document.getElementById('merchantsPrevBtn');
  const nextBtn = document.getElementById('merchantsNextBtn');
  
  if (prevBtn) {
    prevBtn.disabled = currentMerchantsPage <= 1;
    prevBtn.style.opacity = currentMerchantsPage <= 1 ? '0.5' : '1';
    prevBtn.style.cursor = currentMerchantsPage <= 1 ? 'not-allowed' : 'pointer';
  }
  
  if (nextBtn) {
    nextBtn.disabled = currentMerchantsPage >= totalPages;
    nextBtn.style.opacity = currentMerchantsPage >= totalPages ? '0.5' : '1';
    nextBtn.style.cursor = currentMerchantsPage >= totalPages ? 'not-allowed' : 'pointer';
  }
}

function changeMerchantsPerPage() {
  const select = document.getElementById('merchantsPerPage');
  merchantsPerPage = parseInt(select.value);
  currentMerchantsPage = 1;
  renderMerchantsPage();
}

function previousMerchantsPage() {
  if (currentMerchantsPage > 1) {
    currentMerchantsPage--;
    renderMerchantsPage();
  }
}

function nextMerchantsPage() {
  const totalPages = Math.ceil(allMerchants.length / merchantsPerPage);
  if (currentMerchantsPage < totalPages) {
    currentMerchantsPage++;
    renderMerchantsPage();
  }
}

// Add export function for merchants data
function exportMerchantsData() {
  if (!dashboardData || !dashboardData.merchants) {
    showNotification('Aucune donnée à exporter', 'warning');
    return;
  }
  
  const csvContent = "data:text/csv;charset=utf-8," + 
    "Merchant,Category,Current,Previous,Change,Market Share,Status\n" +
    dashboardData.merchants.map(merchant => {
      const change = merchant.previous > 0 ? 
        ((merchant.current - merchant.previous) / merchant.previous * 100).toFixed(1) + '%' : 
        'Nouveau';
      return `"${merchant.name}","${merchant.category}",${merchant.current},${merchant.previous},"${change}",${merchant.share}%,"Active"`;
    }).join("\n");
  
  const encodedUri = encodeURI(csvContent);
  const link = document.createElement("a");
  link.setAttribute("href", encodedUri);
  link.setAttribute("download", "merchants_export.csv");
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
}