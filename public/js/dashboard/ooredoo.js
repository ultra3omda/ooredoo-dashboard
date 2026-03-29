/**
 * Dashboard - Module Ooredoo/DGV
 * Fonctions pour les stats, tableaux et KPIs Ooredoo
 */

let allOoredooMonthlyStats = [];
let expandedOoredooMonths = new Set();
let currentOoredooStatsSortColumn = 0;
let ooredooStatsSortDirection = 'asc';

function calculateOoredooTotals(monthlyStats) {
  if (!monthlyStats || monthlyStats.length === 0) {
    return {
      newSubs: 0,
      unsubs: 0,
      billings: 0,
      activeSubsEndOfPeriod: 0,
      revenueTnd: 0
    };
  }
  
  const totals = {
    newSubs: 0,
    unsubs: 0,
    billings: 0,
    activeSubsEndOfPeriod: 0,
    revenueTnd: 0
  };
  
  // Sommer les totaux mensuels
  monthlyStats.forEach(month => {
    totals.newSubs += Number(month.total_new_sub) || 0;
    totals.unsubs += Number(month.total_unsub) || 0;
    totals.billings += Number(month.total_nb_facturation) || 0;
    totals.revenueTnd += Number(month.total_revenu_tnd) || 0;
  });
  
  // Active Subs = valeur du DERNIER mois de la période
  const lastMonth = monthlyStats[0]; // Le premier dans l'ordre décroissant
  totals.activeSubsEndOfPeriod = lastMonth ? (Number(lastMonth.total_active_sub) || 0) : 0;
  
  return totals;
}

function updateOoredooKPIs(data) {
  console.log('🔍 [OOREDOO] Mise à jour des KPIs:', data);
  console.log('🔍 [OOREDOO] ooredoo_stats:', data?.ooredoo_stats);
  console.log('🔍 [OOREDOO] monthly_stats:', data?.ooredoo_stats?.ooredoo_monthly_stats);
  console.log('🔍 [OOREDOO] monthly_stats_comparison:', data?.ooredoo_stats?.ooredoo_monthly_stats_comparison);
  
  if (!data || !data.ooredoo_stats) {
    console.warn('⚠️ [OOREDOO] Données manquantes');
    return;
  }
  
  // Récupérer les statistiques mensuelles groupées Ooredoo
  if (data.ooredoo_stats.ooredoo_monthly_stats) {
    updateOoredooStatisticsTable(data.ooredoo_stats.ooredoo_monthly_stats);
    
    // Calculer les KPIs agrégés avec comparaison
    const monthlyStats = data.ooredoo_stats.ooredoo_monthly_stats || [];
    const monthlyStatsComparison = data.ooredoo_stats.ooredoo_monthly_stats_comparison || [];
    
    const totals = calculateOoredooTotals(monthlyStats);
    const comparisonTotals = monthlyStatsComparison.length > 0 
      ? calculateOoredooTotals(monthlyStatsComparison) 
      : null;
    
    console.log('🔍 [OOREDOO] Statistiques:', {
      current_months: monthlyStats.length,
      comparison_months: monthlyStatsComparison.length,
      totals: totals,
      comparisonTotals: comparisonTotals
    });
    
    // Helper pour créer un objet KPI avec ou sans comparaison
    const makeKPI = (current, previous, decimals = 0) => {
      const currentNum = Number(current) || 0;
      const previousNum = Number(previous) || 0;
      
      if (previous === null || previous === undefined || previousNum === 0) {
        return { 
          current: formatNumber(currentNum, decimals), 
          previous: 0, 
          change: 0 
        };
      }
      return {
        current: formatNumber(currentNum, decimals),
        previous: formatNumber(previousNum, decimals),
        change: calculateChange(currentNum, previousNum)
      };
    };
    
    // Taux de Facturation = moyenne des taux quotidiens (calculée côté backend dans total_taux_facturation)
    // On utilise la moyenne pondérée des mois
    let billingRateCurrent = 0;
    let totalDaysCurrent = 0;
    monthlyStats.forEach(month => {
      const monthRate = Number(month.total_taux_facturation) || 0;
      const monthDays = Number(month.days_count) || 0;
      billingRateCurrent += monthRate * monthDays;
      totalDaysCurrent += monthDays;
    });
    billingRateCurrent = totalDaysCurrent > 0 ? billingRateCurrent / totalDaysCurrent : 0;

    let billingRatePrevious = null;
    if (monthlyStatsComparison.length > 0) {
      let totalDaysComp = 0;
      let sumComp = 0;
      monthlyStatsComparison.forEach(month => {
        const monthRate = Number(month.total_taux_facturation) || 0;
        const monthDays = Number(month.days_count) || 0;
        sumComp += monthRate * monthDays;
        totalDaysComp += monthDays;
      });
      billingRatePrevious = totalDaysComp > 0 ? sumComp / totalDaysComp : 0;
    }
    updateKPI('ooredoo-billing-rate', makeKPI(billingRateCurrent, billingRatePrevious, 2), '%');
    
    // Total Facturations
    updateKPI('ooredoo-total-billings', makeKPI(totals.billings, comparisonTotals?.billings, 0));
    
    // Active Subs: pas de delta (valeur à la fin de période)
    updateKPI('ooredoo-active-subs', {
      current: formatNumber(totals.activeSubsEndOfPeriod, 0),
      previous: 0,
      change: 0
    });
    
    // Nouveaux Abonnements
    updateKPI('ooredoo-new-subscriptions', makeKPI(totals.newSubs, comparisonTotals?.newSubs, 0));
    
    // Désabonnements
    updateKPI('ooredoo-unsubscriptions', makeKPI(totals.unsubs, comparisonTotals?.unsubs, 0));
    
    // Revenu Total TND
    updateKPI('ooredoo-revenue-tnd', makeKPI(totals.revenueTnd, comparisonTotals?.revenueTnd, 3), ' TND');
    
    // ARPU: pas de delta (calcul global)
    // Calculer le nombre de jours de la période pour normaliser l'ARPU
    const startDate = document.getElementById('start-date')?.value;
    const endDate = document.getElementById('end-date')?.value;
    let periodDays = 30; // Défaut
    if (startDate && endDate) {
      const start = new Date(startDate);
      const end = new Date(endDate);
      periodDays = Math.ceil((end - start) / (1000 * 60 * 60 * 24)) || 30;
    }
    const arpuValue = totals.activeSubsEndOfPeriod > 0 
      ? (totals.revenueTnd / totals.activeSubsEndOfPeriod) * (30 / periodDays)
      : 0;
    updateKPI('ooredoo-arpu', {
      current: formatNumber(arpuValue, 3),
      previous: 0,
      change: 0
    }, ' TND');
    
    // Revenu Moyen/Facturation: pas de delta (calcul global)
    const avgBillingValue = totals.billings > 0 
      ? totals.revenueTnd / totals.billings
      : 0;
    updateKPI('ooredoo-avg-billing-revenue', {
      current: formatNumber(avgBillingValue, 3),
      previous: 0,
      change: 0
    }, ' TND');
  } else {
    console.warn('⚠️ [OOREDOO] Pas de ooredoo_monthly_stats dans les données');
  }
}

function updateOoredooStatisticsTable(monthlyStats) {
  const tbody = document.getElementById('ooredooStatsTableBody');
  if (!tbody) return;
  
  if (!monthlyStats || monthlyStats.length === 0) {
    tbody.innerHTML = '<tr><td colspan="8" class="no-data" style="text-align: center; padding: 40px; color: var(--muted);">Aucune donnée disponible</td></tr>';
    return;
  }
  
  allOoredooMonthlyStats = monthlyStats;
  renderOoredooStatisticsTable();
}

function renderOoredooStatisticsTable() {
  const tbody = document.getElementById('ooredooStatsTableBody');
  if (!tbody) return;
  
  if (!allOoredooMonthlyStats || allOoredooMonthlyStats.length === 0) {
    tbody.innerHTML = '<tr><td colspan="8" class="no-data" style="text-align: center; padding: 40px; color: var(--muted);">Aucune donnée disponible</td></tr>';
    return;
  }
  
  let html = '';
  
  allOoredooMonthlyStats.forEach((month, idx) => {
    const isExpanded = expandedOoredooMonths.has(month.month_key);
    const expandIcon = isExpanded ? '▼' : '▶';
    
    // Ligne du mois (cliquable)
    html += `
      <tr style="background: var(--card); border-bottom: 2px solid var(--border); cursor: pointer; font-weight: 600;" 
          onclick="toggleOoredooMonth('${month.month_key}')">
        <td style="padding: 12px; text-align: center;">${expandIcon}</td>
        <td style="padding: 12px;">${month.display_label}</td>
        <td style="padding: 12px; text-align: center;">${formatNumber(month.total_new_sub, 0)}</td>
        <td style="padding: 12px; text-align: center;">${formatNumber(month.total_unsub, 0)}</td>
        <td style="padding: 12px; text-align: center;">${formatNumber(month.total_active_sub, 0)}</td>
        <td style="padding: 12px; text-align: center;">${formatNumber(month.total_nb_facturation, 0)}</td>
        <td style="padding: 12px; text-align: center;">${formatPercentage(month.total_taux_facturation, 3)}</td>
        <td style="padding: 12px; text-align: center;">${formatNumber(month.total_revenu_tnd, 3)} TND</td>
      </tr>
    `;
    
    // Lignes des détails quotidiens (affichées seulement si le mois est expandé)
    if (isExpanded && month.daily_details && month.daily_details.length > 0) {
      month.daily_details.forEach(day => {
        html += `
          <tr style="background: rgba(0,0,0,0.02); border-bottom: 1px solid var(--border);">
            <td style="padding: 8px;"></td>
            <td style="padding: 8px; padding-left: 30px; font-size: 13px;">${day.stat_date}</td>
            <td style="padding: 8px; text-align: center; font-size: 13px;">${formatNumber(day.new_subscriptions || 0, 0)}</td>
            <td style="padding: 8px; text-align: center; font-size: 13px;">${formatNumber(day.unsubscriptions || 0, 0)}</td>
            <td style="padding: 8px; text-align: center; font-size: 13px;">${formatNumber(day.active_subscriptions || 0, 0)}</td>
            <td style="padding: 8px; text-align: center; font-size: 13px;">${formatNumber(day.total_billings || 0, 0)}</td>
            <td style="padding: 8px; text-align: center; font-size: 13px;">${formatPercentage(day.billing_rate || 0, 3)}</td>
            <td style="padding: 8px; text-align: center; font-size: 13px;">${formatNumber(day.revenue_tnd || 0, 3)} TND</td>
          </tr>
        `;
      });
    }
  });
  
  tbody.innerHTML = html;
}

function toggleOoredooMonth(monthKey) {
  if (expandedOoredooMonths.has(monthKey)) {
    expandedOoredooMonths.delete(monthKey);
  } else {
    expandedOoredooMonths.add(monthKey);
  }
  renderOoredooStatisticsTable();
}

function sortOoredooStatistics(columnIndex) {
  // TODO: Implement sorting for monthly stats if needed
  console.log('Sort Ooredoo column:', columnIndex);
}

function filterOoredooStats() {
  // TODO: Implement filtering for monthly stats if needed
  console.log('Filter Ooredoo stats');
}

function exportOoredooStatsToExcel() {
  if (!allOoredooMonthlyStats || allOoredooMonthlyStats.length === 0) {
    alert('Aucune donnée à exporter');
    return;
  }
  
  let csv = 'Période,New Sub,Unsub,Active Sub,NB Facturation,Taux Facturation %,Revenu TND\n';
  
  allOoredooMonthlyStats.forEach(month => {
    csv += `${month.display_label},${month.total_new_sub || 0},${month.total_unsub || 0},${month.total_active_sub || 0},${month.total_nb_facturation || 0},${month.total_taux_facturation || 0},${month.total_revenu_tnd || 0}\n`;
  });
  
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
  const link = document.createElement('a');
  const url = URL.createObjectURL(blob);
  link.setAttribute('href', url);
  link.setAttribute('download', `ooredoo_statistiques_${new Date().toISOString().split('T')[0]}.csv`);
  link.style.visibility = 'hidden';
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
}

function copyOoredooStatsToClipboard() {
  if (!allOoredooMonthlyStats || allOoredooMonthlyStats.length === 0) {
    alert('Aucune donnée à copier');
    return;
  }
  
  let text = 'Période\tNew Sub\tUnsub\tActive Sub\tNB Facturation\tTaux Facturation %\tRevenu TND\n';
  
  allOoredooMonthlyStats.forEach(month => {
    text += `${month.display_label}\t${month.total_new_sub || 0}\t${month.total_unsub || 0}\t${month.total_active_sub || 0}\t${month.total_nb_facturation || 0}\t${month.total_taux_facturation || 0}\t${month.total_revenu_tnd || 0}\n`;
  });
  
  navigator.clipboard.writeText(text).then(() => {
    alert('Données copiées dans le presse-papier !');
  }).catch(err => {
    console.error('Erreur lors de la copie:', err);
    alert('Erreur lors de la copie');
  });
}

// Update merchants table with enhanced data and pagination
