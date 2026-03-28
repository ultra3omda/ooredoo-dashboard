/**
 * Dashboard - Module Timwe
 * Fonctions pour les stats, tableaux et KPIs Timwe
 */

let allTimweStatistics = [];
let currentTimweStatsSortColumn = 0;
let timweStatsSortDirection = 'asc';

function calculateTimweTotals(monthlyStats) {
  if (!monthlyStats || monthlyStats.length === 0) {
    return {
      newSubs: 0,
      unsubs: 0,
      simchurn: 0,
      simchurnRevenue: 0,
      activeSubsEndOfPeriod: 0,
      revenueTnd: 0,
      caBigdealHt: 0
    };
  }
  
  const totals = {
    newSubs: 0,
    unsubs: 0,
    simchurn: 0,
    simchurnRevenue: 0,
    activeSubsEndOfPeriod: 0,
    revenueTnd: 0,
    caBigdealHt: 0
  };
  
  // Sommer les totaux mensuels
  monthlyStats.forEach(month => {
    totals.newSubs += Number(month.total_new_sub) || 0;
    totals.unsubs += Number(month.total_unsub) || 0;
    totals.simchurn += Number(month.total_simchurn) || 0;
    totals.simchurnRevenue += Number(month.total_rev_simchurn) || 0;
    totals.revenueTnd += Number(month.total_revenu_ttc_tnd) || 0;
    totals.caBigdealHt += Number(month.ca_bigdeal_ht) || 0;
  });
  
  // Active Subs = valeur du DERNIER mois de la période
  const lastMonth = monthlyStats[0]; // Le premier dans l'ordre décroissant
  totals.activeSubsEndOfPeriod = lastMonth ? (Number(lastMonth.total_active_sub) || 0) : 0;
  
  return totals;
}

function calculateTimweComparisonTotals(monthlyStatsComparison) {
  // Utiliser directement les données mensuelles de comparaison du backend
  if (!monthlyStatsComparison || monthlyStatsComparison.length === 0) {
    console.log('🔍 [TIMWE COMPARISON] Pas de données de comparaison');
    return null;
  }
  
  return calculateTimweTotals(monthlyStatsComparison);
}

// Stockage des mois Timwe et leur état d'expansion
let allTimweMonthlyStats = [];
let expandedMonths = new Set();

function updateTimweStatisticsTable(monthlyStats) {
  const tbody = document.getElementById('timweStatsTableBody');
  if (!tbody) return;
  
  if (!monthlyStats || monthlyStats.length === 0) {
    tbody.innerHTML = '<tr><td colspan="10" class="no-data" style="text-align: center; padding: 40px; color: var(--muted);">Aucune donnée disponible</td></tr>';
    return;
  }
  
  allTimweMonthlyStats = monthlyStats;
  renderTimweStatisticsTable();
}

function renderTimweStatisticsTable() {
  const tbody = document.getElementById('timweStatsTableBody');
  if (!tbody) return;
  
  if (!allTimweMonthlyStats || allTimweMonthlyStats.length === 0) {
    tbody.innerHTML = '<tr><td colspan="10" class="no-data" style="text-align: center; padding: 40px; color: var(--muted);">Aucune donnée disponible</td></tr>';
    return;
  }
  
  let html = '';
  
  allTimweMonthlyStats.forEach((month, idx) => {
    const isExpanded = expandedMonths.has(month.month_key);
    const expandIcon = isExpanded ? '▼' : '▶';
    
    // Ligne du mois (cliquable)
    html += `
      <tr style="background: var(--card); border-bottom: 2px solid var(--border); cursor: pointer; font-weight: 600;" 
          onclick="toggleTimweMonth('${month.month_key}')">
        <td style="padding: 12px; text-align: center;">${expandIcon}</td>
        <td style="padding: 12px;">${month.display_label}</td>
        <td style="padding: 12px; text-align: center;">${formatNumber(month.total_new_sub, 0)}</td>
        <td style="padding: 12px; text-align: center;">${formatNumber(month.total_unsub, 0)}</td>
        <td style="padding: 12px; text-align: center;">${formatNumber(month.total_simchurn, 0)}</td>
        <td style="padding: 12px; text-align: center;">${formatNumber(month.total_active_sub, 0)}</td>
        <td style="padding: 12px; text-align: center;">${formatNumber(month.total_nb_facturation, 0)}</td>
        <td style="padding: 12px; text-align: center;">${formatPercentage(month.total_taux_facturation, 3)}</td>
        <td style="padding: 12px; text-align: center;">${formatNumber(month.total_revenu_ttc_tnd, 3)} TND</td>
        <td style="padding: 12px; text-align: center;">${formatNumber(month.ca_bigdeal_ht, 3)} TND</td>
      </tr>
    `;
    
    // Lignes des détails quotidiens (affichées seulement si le mois est expandé)
    if (isExpanded && month.daily_details && month.daily_details.length > 0) {
      month.daily_details.forEach(day => {
        html += `
          <tr style="background: rgba(0,0,0,0.02); border-bottom: 1px solid var(--border);">
            <td style="padding: 8px;"></td>
            <td style="padding: 8px; padding-left: 30px; font-size: 13px;">${day.dimension}</td>
            <td style="padding: 8px; text-align: center; font-size: 13px;">${formatNumber(day.new_sub || 0, 0)}</td>
            <td style="padding: 8px; text-align: center; font-size: 13px;">${formatNumber(day.unsub || 0, 0)}</td>
            <td style="padding: 8px; text-align: center; font-size: 13px;">${formatNumber(day.simchurn || 0, 0)}</td>
            <td style="padding: 8px; text-align: center; font-size: 13px;">${formatNumber(day.active_sub || 0, 0)}</td>
            <td style="padding: 8px; text-align: center; font-size: 13px;">${formatNumber(day.nb_facturation || 0, 0)}</td>
            <td style="padding: 8px; text-align: center; font-size: 13px;">${formatPercentage(day.taux_facturation || 0, 3)}</td>
            <td style="padding: 8px; text-align: center; font-size: 13px; color: var(--muted);">-</td>
            <td style="padding: 8px; text-align: center; font-size: 13px; color: var(--muted);">-</td>
          </tr>
        `;
      });
    }
  });
  
  tbody.innerHTML = html;
}

function toggleTimweMonth(monthKey) {
  if (expandedMonths.has(monthKey)) {
    expandedMonths.delete(monthKey);
  } else {
    expandedMonths.add(monthKey);
  }
  renderTimweStatisticsTable();
}

function sortTimweStatistics(columnIndex) {
  if (currentTimweStatsSortColumn === columnIndex) {
    timweStatsSortDirection = timweStatsSortDirection === 'asc' ? 'desc' : 'asc';
  } else {
    currentTimweStatsSortColumn = columnIndex;
    timweStatsSortDirection = 'asc';
  }
  
  allTimweStatistics.sort((a, b) => {
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
      case 9: aVal = a.revenu_ttc_tnd || a.revenu_ttc_local || 0; bVal = b.revenu_ttc_tnd || b.revenu_ttc_local || 0; break;
      case 10: aVal = a.revenu_ttc_usd || 0; bVal = b.revenu_ttc_usd || 0; break;
      default: return 0;
    }
    
    if (typeof aVal === 'string') {
      return timweStatsSortDirection === 'asc' ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
    } else {
      return timweStatsSortDirection === 'asc' ? aVal - bVal : bVal - aVal;
    }
  });
  
  renderTimweStatisticsTable();
}

function filterTimweStats() {
  // Fonction simplifiée : on filtre simplement par le nom du mois
  renderTimweStatisticsTable();
}

function exportTimweStatsToExcel() {
  if (!allTimweMonthlyStats || allTimweMonthlyStats.length === 0) {
    alert('Aucune donnée à exporter');
    return;
  }
  
  let csv = 'Période,New Sub,Unsub,Simchurn,Active Sub,NB Facturation,Taux Facturation %,Revenu TTC (TND),CA BigDeal HT (TND)\n';
  
  allTimweMonthlyStats.forEach(month => {
    // Ligne du mois (avec formatage français)
    csv += `${month.display_label},${formatNumber(month.total_new_sub, 0)},${formatNumber(month.total_unsub, 0)},${formatNumber(month.total_simchurn, 0)},${formatNumber(month.total_active_sub, 0)},${formatNumber(month.total_nb_facturation, 0)},${formatPercentage(month.total_taux_facturation, 3)},${formatNumber(month.total_revenu_ttc_tnd, 3)},${formatNumber(month.ca_bigdeal_ht, 3)}\n`;
    
    // Lignes des détails quotidiens
    if (month.daily_details && month.daily_details.length > 0) {
      month.daily_details.forEach(day => {
        csv += `  ${day.dimension},${formatNumber(day.new_sub || 0, 0)},${formatNumber(day.unsub || 0, 0)},${formatNumber(day.simchurn || 0, 0)},${formatNumber(day.active_sub || 0, 0)},${formatNumber(day.nb_facturation || 0, 0)},${formatPercentage(day.taux_facturation || 0, 3)},-,-\n`;
      });
    }
  });
  
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
  const link = document.createElement('a');
  const url = URL.createObjectURL(blob);
  link.setAttribute('href', url);
  link.setAttribute('download', `timwe_statistiques_mensuelles_${new Date().toISOString().split('T')[0]}.csv`);
  link.style.visibility = 'hidden';
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
}

function copyTimweStatsToClipboard() {
  if (!allTimweMonthlyStats || allTimweMonthlyStats.length === 0) {
    alert('Aucune donnée à copier');
    return;
  }
  
  let text = 'Période\tNew Sub\tUnsub\tSimchurn\tActive Sub\tNB Facturation\tTaux Facturation %\tRevenu TTC (TND)\tCA BigDeal HT (TND)\n';
  
  allTimweMonthlyStats.forEach(month => {
    // Ligne du mois (avec formatage français)
    text += `${month.display_label}\t${formatNumber(month.total_new_sub, 0)}\t${formatNumber(month.total_unsub, 0)}\t${formatNumber(month.total_simchurn, 0)}\t${formatNumber(month.total_active_sub, 0)}\t${formatNumber(month.total_nb_facturation, 0)}\t${formatPercentage(month.total_taux_facturation, 3)}\t${formatNumber(month.total_revenu_ttc_tnd, 3)}\t${formatNumber(month.ca_bigdeal_ht, 3)}\n`;
    
    // Lignes des détails quotidiens
    if (month.daily_details && month.daily_details.length > 0) {
      month.daily_details.forEach(day => {
        text += `  ${day.dimension}\t${formatNumber(day.new_sub || 0, 0)}\t${formatNumber(day.unsub || 0, 0)}\t${formatNumber(day.simchurn || 0, 0)}\t${formatNumber(day.active_sub || 0, 0)}\t${formatNumber(day.nb_facturation || 0, 0)}\t${formatPercentage(day.taux_facturation || 0, 3)}\t-\t-\n`;
      });
    }
  });
  
  navigator.clipboard.writeText(text).then(() => {
    alert('Données copiées dans le presse-papier !');
  }).catch(err => {
    console.error('Erreur lors de la copie:', err);
    alert('Erreur lors de la copie');
  });
}

// ========== TIMWE TRANSACTIONS BY USER FUNCTIONS ==========
// DÉSACTIVÉ POUR OPTIMISATION - TOUTES LES FONCTIONS CI-DESSOUS SONT COMMENTÉES
/*
let allTimweTransactions = [];
let currentTimweTransactionsPage = 1;
let timweTransactionsPerPage = 25;
let currentTimweTransactionsSortColumn = 1; // Default: sort by nb_transactions
let timweTransactionsSortDirection = 'desc';
let filteredTimweTransactions = [];

function updateTimweTransactionsTable(transactions) {
  const tbody = document.getElementById('timweTransactionsTableBody');
  if (!tbody) return;
  
  if (!transactions || transactions.length === 0) {
    // Vérifier si c'est une longue période
    const startDate = document.getElementById('start-date')?.value;
    const endDate = document.getElementById('end-date')?.value;
    let message = 'Aucune transaction disponible';
    
    if (startDate && endDate) {
      const start = new Date(startDate);
      const end = new Date(endDate);
      const diffDays = Math.ceil((end - start) / (1000 * 60 * 60 * 24));
      
      if (diffDays > 90) {
        message = '⚠️ Tableau désactivé pour les périodes > 90 jours (optimisation des performances). Veuillez sélectionner une période plus courte.';
      }
    }
    
    tbody.innerHTML = `<tr><td colspan="6" class="no-data" style="text-align: center; padding: 40px; color: var(--muted);">${message}</td></tr>`;
    return;
  }
  
  allTimweTransactions = transactions;
  filteredTimweTransactions = transactions;
  renderTimweTransactionsTable();
}

function renderTimweTransactionsTable() {
  const tbody = document.getElementById('timweTransactionsTableBody');
  if (!tbody) return;
  
  if (!filteredTimweTransactions || filteredTimweTransactions.length === 0) {
    tbody.innerHTML = '<tr><td colspan="6" class="no-data" style="text-align: center; padding: 40px; color: var(--muted);">Aucune transaction trouvée</td></tr>';
    return;
  }
  
  // Pagination
  const start = (currentTimweTransactionsPage - 1) * timweTransactionsPerPage;
  const end = start + timweTransactionsPerPage;
  const pageData = filteredTimweTransactions.slice(start, end);
  
  tbody.innerHTML = pageData.map(row => {
    const clientId = row.client_id || '-';
    const nbTransactions = row.nb_transactions || 0;
    const derniereTransactionId = row.derniere_transaction_id || '-';
    const derniereDate = row.derniere_date ? new Date(row.derniere_date).toLocaleString('fr-FR') : '-';
    const lastStatus = row.last_status || '-';
    
    // Badge de statut basé sur la facturation
    let statusBadge = '';
    if (lastStatus === 'RENOUVELÉ') {
      statusBadge = '<span style="padding: 4px 12px; background: #d1fae5; color: #065f46; border-radius: 12px; font-size: 11px; font-weight: 600;">✅ RENOUVELÉ</span>';
    } else if (lastStatus === 'NON RENOUVELÉ') {
      statusBadge = '<span style="padding: 4px 12px; background: #fee2e2; color: #991b1b; border-radius: 12px; font-size: 11px; font-weight: 600;">❌ NON RENOUVELÉ</span>';
    } else {
      statusBadge = '<span style="padding: 4px 12px; background: #f3f4f6; color: #374151; border-radius: 12px; font-size: 11px; font-weight: 600;">' + lastStatus + '</span>';
    }
    
    return `
      <tr>
        <td><strong>${clientId}</strong></td>
        <td><span style="font-weight: 600; color: var(--primary);">${nbTransactions}</span></td>
        <td>${derniereTransactionId}</td>
        <td>${derniereDate}</td>
        <td>${statusBadge}</td>
        <td>
          <button onclick="viewClientTimweTransactions(${clientId})" 
                  style="padding: 4px 8px; background: var(--primary); color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 11px;">
            📊 Détails
          </button>
        </td>
      </tr>
    `;
  }).join('');
  
  updateTimweTransactionsPagination();
}

function updateTimweTransactionsPagination() {
  const paginationDiv = document.getElementById('timweTransactionsPagination');
  if (!paginationDiv) return;
  
  const totalPages = Math.ceil(filteredTimweTransactions.length / timweTransactionsPerPage);
  
  if (totalPages <= 1) {
    paginationDiv.innerHTML = '';
    return;
  }
  
  let html = '';
  
  // Previous button
  if (currentTimweTransactionsPage > 1) {
    html += `<button onclick="changeTimweTransactionsPage(${currentTimweTransactionsPage - 1})" style="padding: 8px 12px; border: 1px solid var(--border); background: white; border-radius: 4px; cursor: pointer;">‹ Précédent</button>`;
  }
  
  // Page numbers
  for (let i = 1; i <= Math.min(totalPages, 5); i++) {
    const isActive = i === currentTimweTransactionsPage;
    html += `<button onclick="changeTimweTransactionsPage(${i})" style="padding: 8px 12px; border: 1px solid var(--border); background: ${isActive ? 'var(--primary)' : 'white'}; color: ${isActive ? 'white' : 'black'}; border-radius: 4px; cursor: pointer;">${i}</button>`;
  }
  
  if (totalPages > 5) {
    html += '<span style="padding: 8px;">...</span>';
    html += `<button onclick="changeTimweTransactionsPage(${totalPages})" style="padding: 8px 12px; border: 1px solid var(--border); background: white; border-radius: 4px; cursor: pointer;">${totalPages}</button>`;
  }
  
  // Next button
  if (currentTimweTransactionsPage < totalPages) {
    html += `<button onclick="changeTimweTransactionsPage(${currentTimweTransactionsPage + 1})" style="padding: 8px 12px; border: 1px solid var(--border); background: white; border-radius: 4px; cursor: pointer;">Suivant ›</button>`;
  }
  
  paginationDiv.innerHTML = html;
}

function changeTimweTransactionsPage(page) {
  currentTimweTransactionsPage = page;
  renderTimweTransactionsTable();
}

function changeTimweTransactionsPerPage(perPage) {
  timweTransactionsPerPage = parseInt(perPage);
  currentTimweTransactionsPage = 1;
  renderTimweTransactionsTable();
}

function sortTimweTransactions(columnIndex) {
  if (currentTimweTransactionsSortColumn === columnIndex) {
    timweTransactionsSortDirection = timweTransactionsSortDirection === 'asc' ? 'desc' : 'asc';
  } else {
    currentTimweTransactionsSortColumn = columnIndex;
    timweTransactionsSortDirection = 'desc'; // Default to desc for numbers
  }
  
  filteredTimweTransactions.sort((a, b) => {
    let aVal, bVal;
    
    switch(columnIndex) {
      case 0: aVal = a.client_id || 0; bVal = b.client_id || 0; break;
      case 1: aVal = a.nb_transactions || 0; bVal = b.nb_transactions || 0; break;
      case 2: aVal = a.derniere_transaction_id || 0; bVal = b.derniere_transaction_id || 0; break;
      case 3: aVal = a.derniere_date || ''; bVal = b.derniere_date || ''; break;
      case 4: aVal = a.last_status || ''; bVal = b.last_status || ''; break;
      default: return 0;
    }
    
    if (typeof aVal === 'string') {
      return timweTransactionsSortDirection === 'asc' ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
    } else {
      return timweTransactionsSortDirection === 'asc' ? aVal - bVal : bVal - aVal;
    }
  });
  
  currentTimweTransactionsPage = 1;
  renderTimweTransactionsTable();
}

function filterTimweTransactions() {
  const searchInput = document.getElementById('timweTransactionsSearch');
  if (!searchInput) return;
  
  const searchTerm = searchInput.value.toLowerCase();
  
  if (!searchTerm) {
    filteredTimweTransactions = allTimweTransactions;
  } else {
    filteredTimweTransactions = allTimweTransactions.filter(row => {
      return (
        String(row.client_id || '').toLowerCase().includes(searchTerm) ||
        String(row.derniere_transaction_id || '').toLowerCase().includes(searchTerm) ||
        String(row.last_status || '').toLowerCase().includes(searchTerm) ||
        String(row.derniere_date || '').toLowerCase().includes(searchTerm)
      );
    });
  }
  
  currentTimweTransactionsPage = 1;
  renderTimweTransactionsTable();
}

function exportTimweTransactionsToExcel() {
  if (!allTimweTransactions || allTimweTransactions.length === 0) {
    alert('Aucune donnée à exporter');
    return;
  }
  
  let csv = 'Client ID,Nb Transactions,Dernière Transaction,Dernière Date,Statut\n';
  
  allTimweTransactions.forEach(row => {
    csv += `${row.client_id || ''},${row.nb_transactions || 0},${row.derniere_transaction_id || ''},${row.derniere_date || ''},${row.last_status || ''}\n`;
  });
  
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
  const link = document.createElement('a');
  const url = URL.createObjectURL(blob);
  link.setAttribute('href', url);
  link.setAttribute('download', `timwe_transactions_par_utilisateur_${new Date().toISOString().split('T')[0]}.csv`);
  link.style.visibility = 'hidden';
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
}

function copyTimweTransactionsToClipboard() {
  if (!allTimweTransactions || allTimweTransactions.length === 0) {
    alert('Aucune donnée à copier');
    return;
  }
  
  let text = 'Client ID\tNb Transactions\tDernière Transaction\tDernière Date\tStatut\n';
  
  allTimweTransactions.forEach(row => {
    text += `${row.client_id || ''}\t${row.nb_transactions || 0}\t${row.derniere_transaction_id || ''}\t${row.derniere_date || ''}\t${row.last_status || ''}\n`;
  });
  
  navigator.clipboard.writeText(text).then(() => {
    alert('Données copiées dans le presse-papier !');
  }).catch(err => {
    console.error('Erreur lors de la copie:', err);
    alert('Erreur lors de la copie');
  });
}

// Variables globales pour le modal
let currentClientTransactions = [];
let currentModalClientId = null;
let filteredModalTransactions = [];
let modalSortColumn = 3; // Default: sort by date
let modalSortDirection = 'desc';

async function viewClientTimweTransactions(clientId) {
  currentModalClientId = clientId;
  
  // Afficher le modal
  const modal = document.getElementById('clientTransactionsModal');
  modal.style.display = 'block';
  
  // Mettre à jour le client ID
  document.getElementById('modalClientId').textContent = clientId;
  
  // Réinitialiser la table
  document.getElementById('modalTransactionsTableBody').innerHTML = `
    <tr>
      <td colspan="5" style="text-align: center; padding: 40px;">
        <i class="fas fa-spinner fa-spin"></i> Chargement des transactions...
      </td>
    </tr>
  `;
  
  try {
    // Récupérer les dates de la période sélectionnée
    const startDate = document.getElementById('start-date')?.value || '';
    const endDate = document.getElementById('end-date')?.value || '';
    
    // Appeler l'API pour récupérer les transactions du client
    const response = await fetch(`/api/timwe-client-transactions/${clientId}?start_date=${startDate}&end_date=${endDate}`, {
      method: 'GET',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      credentials: 'include'
    });
    
    if (!response.ok) {
      const errorText = await response.text();
      console.error('Erreur API:', errorText);
      throw new Error(`Erreur ${response.status}: ${response.statusText}`);
    }
    
    const data = await response.json();
    
    if (data.success) {
      currentClientTransactions = data.transactions || [];
      filteredModalTransactions = currentClientTransactions;
      
      // Mettre à jour les stats
      updateModalClientStats(data.stats);
      
      // Afficher les transactions
      renderModalTransactions();
    } else {
      throw new Error(data.message || 'Erreur inconnue');
    }
    
  } catch (error) {
    console.error('Erreur:', error);
    document.getElementById('modalTransactionsTableBody').innerHTML = `
      <tr>
        <td colspan="5" style="text-align: center; padding: 40px; color: #ef4444;">
          <i class="fas fa-exclamation-triangle"></i> Erreur lors du chargement des transactions: ${error.message}
        </td>
      </tr>
    `;
  }
}

function updateModalClientStats(stats) {
  const statsDiv = document.getElementById('modalClientStats');
  
  const totalTransactions = stats.total_transactions || 0;
  const renewals = stats.renewals || 0;
  const unsubscriptions = stats.unsubscriptions || 0;
  const facture = stats.facture || 0;
  const tentativeNB = stats.tentative_nb || 0;
  const tentative = stats.tentative || 0;
  const firstTransaction = stats.first_transaction_date ? new Date(stats.first_transaction_date).toLocaleDateString('fr-FR') : '-';
  const lastTransaction = stats.last_transaction_date ? new Date(stats.last_transaction_date).toLocaleDateString('fr-FR') : '-';
  
  statsDiv.innerHTML = `
    <div style="background: white; padding: 15px; border-radius: 6px; border-left: 4px solid #667eea;">
      <div style="color: #6b7280; font-size: 12px; margin-bottom: 5px;">Total Transactions</div>
      <div style="font-size: 24px; font-weight: 600; color: #111827;">${totalTransactions}</div>
    </div>
    <div style="background: white; padding: 15px; border-radius: 6px; border-left: 4px solid #10b981;">
      <div style="color: #6b7280; font-size: 12px; margin-bottom: 5px;">✅ Facturé</div>
      <div style="font-size: 24px; font-weight: 600; color: #059669;">${facture}</div>
    </div>
    <div style="background: white; padding: 15px; border-radius: 6px; border-left: 4px solid #f59e0b;">
      <div style="color: #6b7280; font-size: 12px; margin-bottom: 5px;">⚠️ Tentative NB</div>
      <div style="font-size: 24px; font-weight: 600; color: #d97706;">${tentativeNB}</div>
    </div>
    <div style="background: white; padding: 15px; border-radius: 6px; border-left: 4px solid #6b7280;">
      <div style="color: #6b7280; font-size: 12px; margin-bottom: 5px;">🔄 Tentative</div>
      <div style="font-size: 24px; font-weight: 600; color: #4b5563;">${tentative}</div>
    </div>
    <div style="background: white; padding: 15px; border-radius: 6px; border-left: 4px solid #3b82f6;">
      <div style="color: #6b7280; font-size: 12px; margin-bottom: 5px;">🔄 Renouvellements</div>
      <div style="font-size: 18px; font-weight: 600; color: #2563eb;">${renewals}</div>
    </div>
    <div style="background: white; padding: 15px; border-radius: 6px; border-left: 4px solid #ef4444;">
      <div style="color: #6b7280; font-size: 12px; margin-bottom: 5px;">❌ Désabonnements</div>
      <div style="font-size: 18px; font-weight: 600; color: #dc2626;">${unsubscriptions}</div>
    </div>
    <div style="background: white; padding: 15px; border-radius: 6px; border-left: 4px solid #8b5cf6;">
      <div style="color: #6b7280; font-size: 12px; margin-bottom: 5px;">📅 Première</div>
      <div style="font-size: 13px; font-weight: 600; color: #7c3aed;">${firstTransaction}</div>
    </div>
    <div style="background: white; padding: 15px; border-radius: 6px; border-left: 4px solid #ec4899;">
      <div style="color: #6b7280; font-size: 12px; margin-bottom: 5px;">📅 Dernière</div>
      <div style="font-size: 13px; font-weight: 600; color: #db2777;">${lastTransaction}</div>
    </div>
  `;
}

function renderModalTransactions() {
  const tbody = document.getElementById('modalTransactionsTableBody');
  
  if (!filteredModalTransactions || filteredModalTransactions.length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="8" style="text-align: center; padding: 40px; color: #6b7280;">
          <i class="fas fa-inbox"></i> Aucune transaction trouvée
        </td>
      </tr>
    `;
    return;
  }
  
  tbody.innerHTML = filteredModalTransactions.map(tx => {
    const transactionId = tx.transaction_history_id || '-';
    const reference = tx.reference || '-';
    const status = tx.status || '-';
    const date = tx.created_at ? new Date(tx.created_at).toLocaleString('fr-FR') : '-';
    
    // Badge de statut original
    let statusBadge = '';
    if (status.includes('RENEWED')) {
      statusBadge = '<span style="padding: 4px 12px; background: #dbeafe; color: #1e40af; border-radius: 12px; font-size: 11px; font-weight: 600;">🔄 RENOUVELÉ</span>';
    } else if (status.includes('UNSUBSCRIPTION')) {
      statusBadge = '<span style="padding: 4px 12px; background: #fee2e2; color: #991b1b; border-radius: 12px; font-size: 11px; font-weight: 600;">❌ DÉSABONNÉ</span>';
    } else {
      statusBadge = '<span style="padding: 4px 12px; background: #f3f4f6; color: #374151; border-radius: 12px; font-size: 11px; font-weight: 600;">' + status + '</span>';
    }
    
    // Badge de statut de facturation
    let billingStatusBadge = '';
    const billingStatus = tx.billing_status || 'tentative';
    const billingLabel = tx.billing_status_label || 'Tentative';
    
    if (billingStatus === 'facture') {
      billingStatusBadge = '<span style="padding: 4px 12px; background: #d1fae5; color: #065f46; border-radius: 12px; font-size: 11px; font-weight: 600;">✅ FACTURÉ</span>';
    } else if (billingStatus === 'tentative_nb') {
      billingStatusBadge = '<span style="padding: 4px 12px; background: #fef3c7; color: #92400e; border-radius: 12px; font-size: 11px; font-weight: 600;">⚠️ TENTATIVE NB</span>';
    } else {
      billingStatusBadge = '<span style="padding: 4px 12px; background: #e5e7eb; color: #374151; border-radius: 12px; font-size: 11px; font-weight: 600;">🔄 TENTATIVE</span>';
    }
    
    // Delivery Code
    const deliveryCode = tx.mno_delivery_code || '-';
    let deliveryCodeBadge = '';
    if (deliveryCode === 'DELIVERED') {
      deliveryCodeBadge = '<span style="padding: 4px 8px; background: #d1fae5; color: #065f46; border-radius: 8px; font-size: 10px; font-weight: 600;">DELIVERED</span>';
    } else if (deliveryCode === 'NO_BALANCE') {
      deliveryCodeBadge = '<span style="padding: 4px 8px; background: #fef3c7; color: #92400e; border-radius: 8px; font-size: 10px; font-weight: 600;">NO_BALANCE</span>';
    } else if (deliveryCode === '-') {
      deliveryCodeBadge = '<span style="padding: 4px 8px; background: #f3f4f6; color: #6b7280; border-radius: 8px; font-size: 10px;">-</span>';
    } else {
      deliveryCodeBadge = '<span style="padding: 4px 8px; background: #e0e7ff; color: #3730a3; border-radius: 8px; font-size: 10px; font-weight: 600;">' + deliveryCode + '</span>';
    }
    
    // Montant
    const amount = tx.total_charged || 0;
    const amountDisplay = amount > 0 
      ? '<span style="color: #059669; font-weight: 600;">' + amount.toFixed(3) + ' TND</span>'
      : '<span style="color: #6b7280;">0.000 TND</span>';
    
    return `
      <tr>
        <td style="padding: 12px; border-bottom: 1px solid #e5e7eb;">
          <strong style="color: #111827;">#${transactionId}</strong>
        </td>
        <td style="padding: 12px; border-bottom: 1px solid #e5e7eb;">
          <code style="background: #f3f4f6; padding: 4px 8px; border-radius: 4px; font-size: 12px;">${reference}</code>
        </td>
        <td style="padding: 12px; border-bottom: 1px solid #e5e7eb;">
          ${statusBadge}
        </td>
        <td style="padding: 12px; border-bottom: 1px solid #e5e7eb;">
          ${billingStatusBadge}
        </td>
        <td style="padding: 12px; border-bottom: 1px solid #e5e7eb;">
          ${deliveryCodeBadge}
        </td>
        <td style="padding: 12px; border-bottom: 1px solid #e5e7eb; text-align: right;">
          ${amountDisplay}
        </td>
        <td style="padding: 12px; border-bottom: 1px solid #e5e7eb; color: #6b7280;">
          <i class="fas fa-clock" style="margin-right: 5px;"></i>${date}
        </td>
        <td style="padding: 12px; border-bottom: 1px solid #e5e7eb; text-align: center;">
          <button onclick="viewTransactionDetails(${transactionId}, '${encodeURIComponent(JSON.stringify(tx.result_details || {}))}' )" style="padding: 6px 12px; background: #667eea; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px;">
            <i class="fas fa-eye"></i> Voir
          </button>
        </td>
      </tr>
    `;
  }).join('');
}

function filterModalTransactions() {
  const searchTerm = document.getElementById('modalTransactionsSearch')?.value.toLowerCase() || '';
  const statusFilter = document.getElementById('modalStatusFilter')?.value || '';
  const billingFilter = document.getElementById('modalBillingFilter')?.value || '';
  
  filteredModalTransactions = currentClientTransactions.filter(tx => {
    const matchesSearch = !searchTerm || 
      (tx.reference && tx.reference.toLowerCase().includes(searchTerm)) ||
      (tx.status && tx.status.toLowerCase().includes(searchTerm)) ||
      (tx.transaction_history_id && String(tx.transaction_history_id).includes(searchTerm)) ||
      (tx.mno_delivery_code && tx.mno_delivery_code.toLowerCase().includes(searchTerm));
    
    const matchesStatus = !statusFilter || 
      (statusFilter === 'RENEWED' && tx.status.includes('RENEWED')) ||
      (statusFilter === 'UNSUBSCRIPTION' && tx.status.includes('UNSUBSCRIPTION'));
    
    const matchesBilling = !billingFilter || tx.billing_status === billingFilter;
    
    return matchesSearch && matchesStatus && matchesBilling;
  });
  
  renderModalTransactions();
}

function sortModalTransactions(columnIndex) {
  if (modalSortColumn === columnIndex) {
    modalSortDirection = modalSortDirection === 'asc' ? 'desc' : 'asc';
  } else {
    modalSortColumn = columnIndex;
    modalSortDirection = 'asc';
  }
  
  filteredModalTransactions.sort((a, b) => {
    let aVal, bVal;
    
    switch(columnIndex) {
      case 0: aVal = a.transaction_history_id || 0; bVal = b.transaction_history_id || 0; break;
      case 1: aVal = a.reference || ''; bVal = b.reference || ''; break;
      case 2: aVal = a.status || ''; bVal = b.status || ''; break;
      case 3: aVal = a.billing_status || ''; bVal = b.billing_status || ''; break;
      case 4: aVal = a.mno_delivery_code || ''; bVal = b.mno_delivery_code || ''; break;
      case 5: aVal = a.total_charged || 0; bVal = b.total_charged || 0; break;
      case 6: aVal = a.created_at || ''; bVal = b.created_at || ''; break;
      default: return 0;
    }
    
    if (typeof aVal === 'string') {
      return modalSortDirection === 'asc' ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
    } else {
      return modalSortDirection === 'asc' ? aVal - bVal : bVal - aVal;
    }
  });
  
  renderModalTransactions();
}

function exportClientTransactions() {
  if (!currentClientTransactions || currentClientTransactions.length === 0) {
    alert('Aucune transaction à exporter');
    return;
  }
  
  let csv = 'Transaction ID,Référence,Statut Original,Statut Facturation,Delivery Code,Montant (TND),Date\n';
  
  currentClientTransactions.forEach(tx => {
    const transactionId = tx.transaction_history_id || '';
    const reference = tx.reference || '';
    const status = tx.status || '';
    const billingStatus = tx.billing_status_label || '';
    const deliveryCode = tx.mno_delivery_code || '';
    const amount = tx.total_charged || 0;
    const date = tx.created_at || '';
    
    csv += `${transactionId},"${reference}","${status}","${billingStatus}","${deliveryCode}",${amount},"${date}"\n`;
  });
  
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
  const link = document.createElement('a');
  const url = URL.createObjectURL(blob);
  link.setAttribute('href', url);
  link.setAttribute('download', `timwe_client_${currentModalClientId}_transactions_${new Date().toISOString().split('T')[0]}.csv`);
  link.style.visibility = 'hidden';
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
}

function closeClientTransactionsModal() {
  document.getElementById('clientTransactionsModal').style.display = 'none';
  currentClientTransactions = [];
  currentModalClientId = null;
  filteredModalTransactions = [];
}

function viewTransactionDetails(transactionId, resultDetailsEncoded) {
  try {
    const resultDetails = JSON.parse(decodeURIComponent(resultDetailsEncoded));
    
    // Formater le JSON pour affichage
    const jsonFormatted = JSON.stringify(resultDetails, null, 2);
    
    // Créer le contenu HTML
    let htmlContent = '<div style="padding: 20px;">';
    htmlContent += '<h3 style="margin-top: 0; color: #111827;">Détails de la Transaction #' + transactionId + '</h3>';
    
    if (resultDetails && Object.keys(resultDetails).length > 0) {
      htmlContent += '<div style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 15px; margin-top: 15px;">';
      htmlContent += '<h4 style="margin: 0 0 10px 0; color: #374151;">Détails Result (JSON)</h4>';
      htmlContent += '<pre style="background: #1f2937; color: #f9fafb; padding: 15px; border-radius: 6px; overflow-x: auto; font-size: 13px; line-height: 1.6;">' + jsonFormatted + '</pre>';
      htmlContent += '</div>';
      
      // Afficher les champs importants
      if (resultDetails.mnoDeliveryCode || resultDetails.totalCharged !== undefined) {
        htmlContent += '<div style="margin-top: 20px; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">';
        
        if (resultDetails.mnoDeliveryCode) {
          htmlContent += '<div style="background: white; padding: 15px; border: 1px solid #e5e7eb; border-radius: 8px;">';
          htmlContent += '<div style="font-size: 12px; color: #6b7280; margin-bottom: 5px;">Delivery Code</div>';
          htmlContent += '<div style="font-size: 18px; font-weight: 600; color: #111827;">' + resultDetails.mnoDeliveryCode + '</div>';
          htmlContent += '</div>';
        }
        
        if (resultDetails.totalCharged !== undefined) {
          htmlContent += '<div style="background: white; padding: 15px; border: 1px solid #e5e7eb; border-radius: 8px;">';
          htmlContent += '<div style="font-size: 12px; color: #6b7280; margin-bottom: 5px;">Montant Chargé</div>';
          htmlContent += '<div style="font-size: 18px; font-weight: 600; color: ' + (resultDetails.totalCharged > 0 ? '#059669' : '#6b7280') + ';">' + resultDetails.totalCharged + ' TND</div>';
          htmlContent += '</div>';
        }
        
        htmlContent += '</div>';
      }
    } else {
      htmlContent += '<div style="padding: 40px; text-align: center; color: #6b7280; background: #f9fafb; border-radius: 8px; margin-top: 15px;">';
      htmlContent += '<i class="fas fa-info-circle" style="font-size: 48px; margin-bottom: 15px; opacity: 0.5;"></i><br>';
      htmlContent += 'Aucun détail result disponible pour cette transaction';
      htmlContent += '</div>';
    }
    
    htmlContent += '</div>';
    
    // Utiliser SweetAlert si disponible, sinon alert simple
    if (typeof Swal !== 'undefined') {
      Swal.fire({
        html: htmlContent,
        width: '800px',
        showCloseButton: true,
        showConfirmButton: true,
        confirmButtonText: 'Fermer',
        confirmButtonColor: '#667eea'
      });
    } else {
      // Fallback: créer un modal simple
      const modalHtml = `
        <div id="detailsModal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10001; display: flex; align-items: center; justify-content: center;">
          <div style="background: white; max-width: 800px; max-height: 80vh; overflow-y: auto; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
            ${htmlContent}
            <div style="padding: 0 20px 20px;">
              <button onclick="document.getElementById('detailsModal').remove()" style="width: 100%; padding: 12px; background: #667eea; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 600;">
                Fermer
              </button>
            </div>
          </div>
        </div>
      `;
      document.body.insertAdjacentHTML('beforeend', modalHtml);
    }
    
  } catch (error) {
    console.error('Erreur lors du parsing des détails:', error);
    alert('Erreur lors de l\'affichage des détails de la transaction');
  }
}

// Fermer le modal en cliquant en dehors
document.addEventListener('click', function(event) {
  const modal = document.getElementById('clientTransactionsModal');
  if (event.target === modal) {
    closeClientTransactionsModal();
  }
});
*/
// FIN DÉSACTIVATION - Toutes les fonctions Timwe Transactions sont commentées ci-dessus

// ========== OOREDOO/DGV FUNCTIONS ==========
