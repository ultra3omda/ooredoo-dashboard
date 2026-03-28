/**
 * Dashboard - Module Eklektik
 * Fonctions pour les graphiques et statistiques Eklektik
 */

function showEklektikStatsLoading() {
  const elements = [
    'kpi-revenue-ttc',
    'kpi-revenue-ht',
    'kpi-ca-bigdeal',
    'kpi-bigdeal-percentage'
  ];

  elements.forEach(id => {
    const element = document.getElementById(id);
    if (element) {
      element.innerHTML = '<div class="loading-spinner">🔄</div>';
    }
  });
}

// Fonction pour afficher les erreurs des KPIs
function showEklektikStatsError() {
  const elements = [
    'kpi-revenue-ttc',
    'kpi-revenue-ht',
    'kpi-ca-bigdeal',
    'kpi-bigdeal-percentage'
  ];

  elements.forEach(id => {
    const element = document.getElementById(id);
    if (element) {
      element.innerHTML = '<span class="error-text">❌ Erreur</span>';
    }
  });
}

// Charger les données Eklektik (sera définie plus tard)
async function loadEklektikData() {
  console.log('🔄 Chargement des données Eklektik...');

  // Afficher l'état de chargement
  showEklektikStatsLoading();

  try {
    // Charger les KPIs
    const kpisResponse = await fetch('/api/eklektik-dashboard/kpis');
    const kpisData = await kpisResponse.json();

    if (kpisData.success) {
      updateEklektikStatsDisplay(kpisData.data);
    } else {
      console.error('❌ Erreur KPIs Eklektik:', kpisData.message);
      showEklektikStatsError();
    }

    // Charger les statistiques par opérateur
    const operatorsResponse = await fetch('/api/eklektik-dashboard/revenue-distribution');
    const operatorsData = await operatorsResponse.json();

    if (operatorsData.success) {
      updateEklektikOperatorsStats(operatorsData.data.distribution);
    } else {
      console.error('❌ Erreur opérateurs Eklektik:', operatorsData.message);
    }

  } catch (error) {
    console.error('❌ Erreur lors du chargement des données Eklektik:', error);
    showEklektikStatsError();
  }
}

// Mettre à jour l'affichage des statistiques Eklektik
function updateEklektikStatsDisplay(data) {
  console.log('📊 Mise à jour des KPIs Eklektik:', data);

  // Mettre à jour les éléments KPI avec les données
  if (data && data.kpis) {
    // Revenue TTC
    const revenueTtcElement = document.getElementById('kpi-revenue-ttc');
    if (revenueTtcElement && data.kpis.total_revenue_ttc !== undefined) {
      revenueTtcElement.innerHTML = formatNumber(data.kpis.total_revenue_ttc) + ' €';
    }

    // Revenue HT
    const revenueHtElement = document.getElementById('kpi-revenue-ht');
    if (revenueHtElement && data.kpis.total_revenue_ht !== undefined) {
      revenueHtElement.innerHTML = formatNumber(data.kpis.total_revenue_ht) + ' €';
    }

    // CA BigDeal
    const caBigdealElement = document.getElementById('kpi-ca-bigdeal');
    if (caBigdealElement && data.kpis.total_facturation !== undefined) {
      caBigdealElement.innerHTML = formatNumber(data.kpis.total_facturation) + ' €';
    }

    // Pourcentage BigDeal
    const bigdealPercentageElement = document.getElementById('kpi-bigdeal-percentage');
    if (bigdealPercentageElement && data.kpis.bigdeal_percentage !== undefined) {
      bigdealPercentageElement.innerHTML = data.kpis.bigdeal_percentage.toFixed(1) + '%';
    }
  }
}

// Mobile-optimized chart options with enhanced 5-breakpoint system
function getMobileOptimizedChartOptions(customOptions = {}) {
  const screenWidth = window.innerWidth;
  const isLargeDesktop = screenWidth >= 1400;
  const isDesktop = screenWidth >= 1200 && screenWidth < 1400;
  const isTabletLarge = screenWidth >= 900 && screenWidth < 1200;
  const isTablet = screenWidth >= 768 && screenWidth < 900;
  const isMobileLarge = screenWidth >= 600 && screenWidth < 768;
  const isMobileSmall = screenWidth >= 480 && screenWidth < 600;
  const isMobileTiny = screenWidth < 480;
  
  // Determine font sizes based on breakpoint
  let legendFontSize, tooltipTitleSize, tooltipBodySize, tickFontSize, padding;
  
  if (isLargeDesktop) {
    legendFontSize = 13; tooltipTitleSize = 15; tooltipBodySize = 14; tickFontSize = 12; padding = 24;
  } else if (isDesktop) {
    legendFontSize = 12; tooltipTitleSize = 14; tooltipBodySize = 13; tickFontSize = 11; padding = 20;
  } else if (isTabletLarge) {
    legendFontSize = 11; tooltipTitleSize = 13; tooltipBodySize = 12; tickFontSize = 10; padding = 16;
  } else if (isTablet) {
    legendFontSize = 10; tooltipTitleSize = 12; tooltipBodySize = 11; tickFontSize = 9; padding = 12;
  } else if (isMobileLarge) {
    legendFontSize = 9; tooltipTitleSize = 11; tooltipBodySize = 10; tickFontSize = 8; padding = 10;
  } else if (isMobileSmall) {
    legendFontSize = 8; tooltipTitleSize = 10; tooltipBodySize = 9; tickFontSize = 7; padding = 8;
  } else { // isMobileTiny
    legendFontSize = 7; tooltipTitleSize = 9; tooltipBodySize = 8; tickFontSize = 6; padding = 6;
  }
  
  const isMobile = screenWidth < 768;
  const isSmallMobile = screenWidth < 480;
  
  const baseOptions = {
    responsive: true,
    maintainAspectRatio: false,
    layout: {
      padding: padding
    },
    plugins: {
      legend: {
        display: true,
        position: isMobile ? 'bottom' : 'top',
        labels: {
          boxWidth: isMobile ? (isSmallMobile ? 8 : 10) : 15,
          padding: isMobile ? (isSmallMobile ? 6 : 8) : 15,
          font: {
            size: legendFontSize
          },
          usePointStyle: isMobile // Utilise des points au lieu de carrés sur mobile
        }
      },
      tooltip: {
        enabled: true,
        mode: isMobile ? 'nearest' : 'index',
        intersect: false,
        titleFont: {
          size: tooltipTitleSize
        },
        bodyFont: {
          size: tooltipBodySize
        },
        padding: isMobile ? (isSmallMobile ? 6 : 8) : 12,
        caretSize: isMobile ? 4 : 6
      }
    },
    scales: {
      x: {
        ticks: {
          font: {
            size: tickFontSize
          },
          maxRotation: isMobile ? (isSmallMobile ? 60 : 45) : 0,
          minRotation: isMobile ? (isSmallMobile ? 60 : 45) : 0,
          maxTicksLimit: isMobile ? (isSmallMobile ? 5 : 8) : undefined
        },
        grid: {
          display: !isSmallMobile,
          lineWidth: isMobile ? 0.5 : 1
        }
      },
      y: {
        ticks: {
          font: {
            size: tickFontSize
          },
          maxTicksLimit: isMobile ? (isSmallMobile ? 4 : 6) : undefined
        },
        grid: {
          display: true,
          lineWidth: isMobile ? 0.5 : 1
        }
      }
    },
    interaction: {
      mode: 'nearest',
      axis: 'x',
      intersect: false
    },
    elements: {
      point: {
        radius: isMobile ? (isSmallMobile ? 2 : 3) : 4,
        hoverRadius: isMobile ? (isSmallMobile ? 4 : 5) : 6
      },
      line: {
        borderWidth: isMobile ? (isSmallMobile ? 1.5 : 2) : 3,
        tension: 0.1 // Lignes légèrement plus lisses sur mobile
      }
    }
  };
  
  // Simple merge avec priorité aux options personnalisées
  return Object.assign({}, baseOptions, customOptions);
}

// Window resize handler for mobile optimization
let resizeTimeout;
window.addEventListener('resize', function() {
  clearTimeout(resizeTimeout);
  resizeTimeout = setTimeout(function() {
    // Re-render charts with new mobile settings
    if (typeof charts !== 'undefined') {
      Object.keys(charts).forEach(key => {
        if (charts[key] && charts[key].resize) {
          charts[key].resize();
        }
      });
    }
    
    // Eklektik charts removed (they were buggy)
  }, 250);
});

// Initialize dashboard (charge tout en une seule fois)
document.addEventListener('DOMContentLoaded', async function() {
  // Dropdown Profil
  const toggle = document.getElementById('profileMenuToggle');
  const dropdown = document.getElementById('profileDropdown');
  if (toggle && dropdown) {
    toggle.addEventListener('click', function(e) {
      e.stopPropagation();
      dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
    });
    document.addEventListener('click', function() {
      dropdown.style.display = 'none';
    });
  }
  // Configuration globale Chart.js pour désactiver les animations
  if (typeof Chart !== 'undefined') {
    Chart.defaults.animation = false;
    Chart.defaults.animations = {
      duration: 0
    };
    Chart.defaults.transitions = {
      active: {
        animation: {
          duration: 0
        }
      },
      resize: {
        animation: {
          duration: 0
        }
      }
    };
    
    // Désactiver complètement toutes les animations
    Chart.defaults.plugins = Chart.defaults.plugins || {};
    Chart.defaults.plugins.legend = Chart.defaults.plugins.legend || {};
    Chart.defaults.plugins.legend.animation = false;
    
    // Désactiver les animations de survol
    Chart.defaults.elements = Chart.defaults.elements || {};
    Chart.defaults.elements.point = Chart.defaults.elements.point || {};
    Chart.defaults.elements.point.hoverRadius = 0;
    Chart.defaults.elements.line = Chart.defaults.elements.line || {};
    Chart.defaults.elements.line.tension = 0;
    Chart.defaults.responsive = true;
    Chart.defaults.maintainAspectRatio = false;
    Chart.defaults.interaction = {
      intersect: false,
      mode: 'index'
    };
    
    // Configuration Chart.js pour un comportement normal (pas d'interception globale)
    if (typeof Chart !== 'undefined') {
      // Configuration légère pour améliorer les performances
      Chart.defaults.maintainAspectRatio = false;
      Chart.defaults.responsive = true;
    }
    
    
    
    
    
    console.log('✅ Chart.js configuré avec succès');
  } else {
    console.error('❌ Chart.js non chargé');
  }

  // Charger les données Eklektik une seule fois au démarrage
  try {
    if (typeof loadEklektikData === 'function') {
      await loadEklektikData();
    }
    if (typeof loadEklektikCharts === 'function') {
      setTimeout(() => loadEklektikCharts(), 150);
    }
  } catch (e) {
    console.warn('Eklektik initial load skipped:', e);
  }
  
  setDefaultDates();
  updateDateRange();
  initializeDashboard();
  
  // Initialize mobile navigation
  initializeMobileNavigation();
  
  // Auto-refresh every 5 minutes
  setInterval(loadDashboardData, 5 * 60 * 1000);
  
  // Initialize keyboard shortcuts
  initializeKeyboardShortcuts();
});

// Initialize mobile-specific navigation features
function initializeMobileNavigation() {
  // Center active tab on page load (mobile)
  const activeTab = document.querySelector('.nav-tab.active');
  if (activeTab && window.innerWidth <= 768) {
    setTimeout(() => centerActiveTab(activeTab), 200);
  }
  
  // Add touch/swipe support for tab navigation (optional)
  if (window.innerWidth <= 768) {
    addMobileSwipeSupport();
  }
}

// Add swipe support for mobile tab navigation
function addMobileSwipeSupport() {
  const tabsContainer = document.querySelector('.nav-tabs');
  let startX = 0;
  let scrollLeft = 0;
  
  tabsContainer.addEventListener('touchstart', (e) => {
    startX = e.touches[0].pageX - tabsContainer.offsetLeft;
    scrollLeft = tabsContainer.scrollLeft;
  }, { passive: true });
  
  tabsContainer.addEventListener('touchmove', (e) => {
    const x = e.touches[0].pageX - tabsContainer.offsetLeft;
    const walk = (x - startX) * 2; // Adjust scroll speed
    tabsContainer.scrollLeft = scrollLeft - walk;
  }, { passive: true });
}

// Advanced keyboard shortcuts for power users
function initializeKeyboardShortcuts() {
  document.addEventListener('keydown', function(e) {
    // Only trigger shortcuts when no input is focused
    if (document.activeElement.tagName === 'INPUT' || 
        document.activeElement.tagName === 'SELECT' || 
        document.activeElement.tagName === 'TEXTAREA') {
      return;
    }
    
    // Ctrl/Cmd + R - Refresh dashboard
    if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
      e.preventDefault();
      loadDashboardData();
      showNotification('🔄 Dashboard actualisé via raccourci clavier', 'info', 2000);
    }
    
    // Tab navigation: 1-5 for tabs
    if (['1', '2', '3', '4', '5'].includes(e.key)) {
      e.preventDefault();
      const tabs = ['overview', 'subscriptions', 'transactions', 'merchants', 'eklektik'];
      const tabName = tabs[parseInt(e.key) - 1];
      if (tabName) {
        showTab(tabName);
        // Update visual feedback
        document.querySelectorAll('.nav-tab').forEach(tab => tab.classList.remove('active'));
        document.querySelector(`.nav-tab[data-tab="${tabName}"]`)?.classList.add('active');
        showNotification(`📊 Onglet ${tabName} activé`, 'info', 1500);
      }
    }
    
    // E for Export (if on merchants tab)
    if (e.key === 'e' || e.key === 'E') {
      const activeTab = document.querySelector('.tab-content.active');
      if (activeTab && activeTab.id === 'merchants') {
        e.preventDefault();
        exportMerchantsData();
        showNotification('📥 Export des données marchands lancé', 'success', 2000);
      }
    }
    
    // D for Date shortcuts modal
    if (e.key === 'd' || e.key === 'D') {
      e.preventDefault();
      toggleDatePickerMode();
      showNotification('📅 Raccourcis de dates', 'info', 1500);
    }
    
    // H for Help (show shortcuts)
    if (e.key === 'h' || e.key === 'H' || e.key === '?') {
      e.preventDefault();
      showKeyboardShortcutsHelp();
    }
    
    // Escape to close modals/notifications
    if (e.key === 'Escape') {
      // Close date shortcuts modal if open
      const modal = document.getElementById('date-shortcuts-modal');
      if (modal && modal.style.display !== 'none') {
        modal.style.display = 'none';
      }
      
      // Close help modal if open
      const helpModal = document.getElementById('shortcuts-help-modal');
      if (helpModal && helpModal.style.display !== 'none') {
        helpModal.style.display = 'none';
      }
      
      // Close all notifications
      document.querySelectorAll('.notification').forEach(n => n.remove());
    }
  });
}

function showKeyboardShortcutsHelp() {
  // Remove existing help modal
  const existing = document.getElementById('shortcuts-help-modal');
  if (existing) existing.remove();
  
  const modal = document.createElement('div');
  modal.id = 'shortcuts-help-modal';
  modal.innerHTML = `
    <div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 10001; display: flex; align-items: center; justify-content: center;">
      <div style="background: white; border-radius: 12px; padding: 30px; max-width: 500px; max-height: 80vh; overflow-y: auto;">
        <div style="display: flex; justify-content: between; align-items: center; margin-bottom: 20px;">
          <h3 style="margin: 0; color: var(--brand-red); font-size: 20px;">⌨️ Raccourcis Clavier</h3>
          <button onclick="document.getElementById('shortcuts-help-modal').remove()" style="background: none; border: none; font-size: 20px; cursor: pointer; margin-left: auto;">×</button>
        </div>
        
        <div style="space-y: 12px;">
          <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f0f0f0;">
            <span><kbd style="background: #f3f4f6; padding: 4px 8px; border-radius: 4px; font-family: monospace;">Ctrl+R</kbd></span>
            <span>Actualiser le dashboard</span>
          </div>
          
          <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f0f0f0;">
            <span><kbd style="background: #f3f4f6; padding: 4px 8px; border-radius: 4px; font-family: monospace;">1-4</kbd></span>
            <span>Naviguer entre les onglets</span>
          </div>
          
          <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f0f0f0;">
            <span><kbd style="background: #f3f4f6; padding: 4px 8px; border-radius: 4px; font-family: monospace;">E</kbd></span>
            <span>Exporter (onglet Marchands)</span>
          </div>
          
          <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f0f0f0;">
            <span><kbd style="background: #f3f4f6; padding: 4px 8px; border-radius: 4px; font-family: monospace;">D</kbd></span>
            <span>Raccourcis de dates</span>
          </div>
          
          <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f0f0f0;">
            <span><kbd style="background: #f3f4f6; padding: 4px 8px; border-radius: 4px; font-family: monospace;">H / ?</kbd></span>
            <span>Afficher cette aide</span>
          </div>
          
          <div style="display: flex; justify-content: space-between; padding: 8px 0;">
            <span><kbd style="background: #f3f4f6; padding: 4px 8px; border-radius: 4px; font-family: monospace;">Esc</kbd></span>
            <span>Fermer modales/notifications</span>
          </div>
        </div>
        
        <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px; font-size: 14px; color: #6c757d;">
          💡 <strong>Astuce :</strong> Ces raccourcis fonctionnent uniquement quand aucun champ de saisie n'est actif.
        </div>
      </div>
    </div>
  `;
  
  document.body.appendChild(modal);
  
  // Close on background click
  modal.addEventListener('click', function(e) {
    if (e.target === modal) {
      modal.remove();
    }
  });
}

// Initialize dashboard in correct order - optimized for speed
async function initializeDashboard() {
  try {
    // Show immediate loading state for KPIs (skeleton)
    showKPISkeleton();
    
    // Start loading dashboard data immediately (most important)
    loadDashboardData();
    
    // Load operators in parallel (non-blocking)
    loadOperators().catch(error => {
      console.warn('Operators loading failed:', error);
      // Ne pas utiliser setupFallbackOperators - laisser loadOperators gérer les retries
    });
    
  } catch (error) {
    console.error('Erreur lors de l\'initialisation:', error);
    hideKPISkeleton();
    showNotification('Erreur lors de l\'initialisation du dashboard', 'error');
  }
}

// Cette fonction n'est plus utilisée - les opérateurs doivent toujours venir de l'API
// Conservée uniquement pour référence mais ne devrait jamais être appelée
function setupFallbackOperators() {
  console.warn('⚠️ setupFallbackOperators appelée - cela ne devrait pas arriver');
  const operatorInfo = document.getElementById('operator-info');
  if (operatorInfo) {
    operatorInfo.textContent = 'Erreur: Impossible de charger les opérateurs depuis l\'API. Veuillez rafraîchir la page.';
    operatorInfo.style.color = '#ef4444';
  }
}

// Show skeleton loading for KPIs immediately
function showKPISkeleton() {
  const kpiValues = document.querySelectorAll('.kpi-value');
  kpiValues.forEach(el => {
    el.innerHTML = '<div class="skeleton-text"></div>';
  });
  
  const kpiDeltas = document.querySelectorAll('.kpi-delta');
  kpiDeltas.forEach(el => {
    // Ne pas ajouter de skeleton pour les KPIs Timwe (qui seront masqués par updateKPI)
    const isTimweKPI = el.id && el.id.startsWith('timwe-');
    if (!isTimweKPI) {
      el.innerHTML = '<div class="skeleton-text-small"></div>';
    }
  });
  
  // Reset progress bars to 0
  const progressBars = document.querySelectorAll('.progress-fill');
  progressBars.forEach(bar => {
    bar.style.width = '0%';
  });
}

// Hide skeleton loading
function hideKPISkeleton() {
  // This will be replaced by real values when updateKPIs is called
}

// Progress bar issue resolved: height was 0px

// Update Overview conversion progress bar safely
function updateOverviewConversionProgressBar(conversionRateData) {
  const conversionProgress = document.getElementById('overview-conversionProgress');
  
  if (conversionProgress && conversionRateData && typeof conversionRateData.current !== 'undefined') {
    const percentage = Math.min(100, Math.max(0, (conversionRateData.current / 30) * 100));
    
    conversionProgress.style.width = `${percentage}%`;
    conversionProgress.style.transition = 'width 0.5s ease-in-out';
    conversionProgress.style.backgroundColor = getThemeColor('primary');
    conversionProgress.style.height = '8px'; // Fixed: same as transactions
    conversionProgress.style.display = 'block';
    
  } else if (conversionProgress) {
    // Fallback: set to 0% if no data
    conversionProgress.style.width = '0%';
    conversionProgress.style.height = '8px';
  }
}

// Update conversion progress bar safely
function updateConversionProgressBar(conversionRateData) {
  const conversionProgress = document.getElementById('trans-conversionProgress');
  
  if (conversionProgress && conversionRateData && typeof conversionRateData.current !== 'undefined') {
    const percentage = Math.min(100, Math.max(0, (conversionRateData.current / 30) * 100));
    
    conversionProgress.style.width = `${percentage}%`;
    conversionProgress.style.transition = 'width 0.5s ease-in-out';
    conversionProgress.style.backgroundColor = getThemeColor('primary');
    conversionProgress.style.height = '8px'; // Fixed: was 0px height
    conversionProgress.style.display = 'block';
    
  } else if (conversionProgress) {
    // Fallback: set to 0% if no data
    conversionProgress.style.width = '0%';
    conversionProgress.style.height = '8px';
  }
}

// Tab switching functionality - Supprimé (défini plus haut)





function updateEklektikTable(numbers) {
  const tbody = document.getElementById('eklektik-numbers-tbody');
  
  if (!numbers || numbers.length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="9" style="text-align: center; padding: 40px; color: var(--muted);">
          📱 Aucun numéro Eklektik trouvé
        </td>
      </tr>
    `;
    return;
  }
  
  tbody.innerHTML = numbers.map(number => `
    <tr>
      <td><strong>${number.phone_number}</strong></td>
      <td>
        <span class="service-badge service-${(number.service_type || 'unknown').toLowerCase()}">
          ${getServiceIcon(number.service_type)} ${number.service_type || 'Unknown'}
        </span>
      </td>
      <td>
        <span class="status-badge status-${(number.status || 'unknown').toLowerCase()}">
          ${getStatusIcon(number.status)} ${number.status || 'Unknown'}
        </span>
      </td>
      <td>
        <span class="operator-badge operator-${(number.operator || 'unknown').toLowerCase()}">
          ${number.operator === 'TT' ? '🔵' : number.operator === 'Orange' ? '🟠' : '❓'} ${number.operator || 'Unknown'}
        </span>
      </td>
      <td style="font-size: 11px; max-width: 120px; overflow: hidden; text-overflow: ellipsis;">
        ${number.payment_method || 'Unknown'}
      </td>
      <td>
        ${number.eklektik_summary ? `
          <div style="font-size: 10px;">
            <div>✅ ${number.eklektik_summary.active_offers ? number.eklektik_summary.active_offers.length : 0} actifs</div>
            <div>📋 ${number.eklektik_summary.available_offers_count || 0} disponibles</div>
            <div>❌ ${number.eklektik_summary.error_offers_count || 0} erreurs</div>
          </div>
        ` : '<span style="color: var(--muted); font-size: 11px;">Non testé</span>'}
      </td>
      <td>
        <strong style="color: var(--primary);">${number.price || 0} TND</strong>
        ${number.duration ? `<br><small>${number.duration} jours</small>` : ''}
      </td>
      <td>
        <span class="source-badge source-${(number.source || 'unknown').toLowerCase()}" style="font-size: 10px;">
          ${number.source === 'EKLEKTIK_API_TESTED' ? '🟢 API Testé' : 
            number.source === 'LOCAL_DATABASE_EKLEKTIK_ONLY' ? '🔵 Local' : 
            number.source === 'LOCAL_DATABASE_READY_FOR_API_TEST' ? '🟡 Prêt pour Test' :
            number.source === 'FALLBACK_LOCAL_DATA' ? '🟡 Fallback' : '❓ Unknown'}
        </span>
      </td>
      <td>
        <div class="action-buttons">
          <button class="btn-sm btn-primary" onclick="viewEklektikDetails('${number.phone_number}')" title="Voir détails">
            👁️
          </button>
          <button class="btn-sm btn-secondary" onclick="testEklektikNumber('${number.phone_number}')" title="Tester">
            🧪
          </button>
        </div>
      </td>
    </tr>
  `).join('');
}

function updateEklektikApiStatus(apiStatus) {
  // Connection status
  const connectionEl = document.getElementById('eklektik-api-status');
  const isConnected = apiStatus.connected !== false;
  connectionEl.innerHTML = `
    <span class="status-indicator ${isConnected ? 'success' : 'danger'}">
      ${isConnected ? '✅' : '❌'}
    </span>
    ${isConnected ? 'Connecté' : 'Déconnecté'}
  `;
  
  // Response time
  const responseTimeEl = document.getElementById('eklektik-response-time');
  const responseTime = apiStatus.responseTime || 0;
  const timeStatus = responseTime < 1000 ? 'success' : responseTime < 3000 ? 'warning' : 'danger';
  responseTimeEl.innerHTML = `
    <span class="status-indicator ${timeStatus}">⚡</span>
    ${responseTime}ms
  `;
  
  // Last sync
  const lastSyncEl = document.getElementById('eklektik-last-sync');
  lastSyncEl.innerHTML = `
    <span class="status-indicator">📊</span>
    ${formatDate(apiStatus.lastSync) || 'Jamais'}
  `;
  
  // Sync status
  const syncStatusEl = document.getElementById('eklektik-sync-status');
  const syncStatus = apiStatus.syncStatus || 'unknown';
  const syncIcon = syncStatus === 'success' ? '✅' : syncStatus === 'error' ? '❌' : '⏳';
  syncStatusEl.innerHTML = `
    <span class="status-indicator">${syncIcon}</span>
    ${syncStatus === 'success' ? 'OK' : syncStatus === 'error' ? 'Erreur' : 'En cours'}
  `;
}

function createEklektikCharts(chartsData) {
  console.log('🔍 [EKLEKTIK DEBUG] Création des graphiques avec données:', chartsData);
  
  // Destroy existing charts to prevent conflicts
  if (window.eklektikCharts) {
    Object.values(window.eklektikCharts).forEach(chart => {
      if (chart && typeof chart.destroy === 'function') {
        chart.destroy();
      }
    });
  }
  window.eklektikCharts = {};
  
  // Usage by service chart
  const usageCtx = document.getElementById('eklektik-usage-chart')?.getContext('2d');
  if (usageCtx && chartsData.serviceUsage) {
    console.log('📊 [EKLEKTIK] Création graphique usage service:', chartsData.serviceUsage);
    window.eklektikCharts.usage = new Chart(usageCtx, {
      type: 'doughnut',
      data: {
        labels: chartsData.serviceUsage.labels || [],
        datasets: [{
          data: chartsData.serviceUsage.data || [],
          backgroundColor: ['#ef4444', '#f97316', '#eab308', '#22c55e', '#3b82f6'],
          borderWidth: 2,
          borderColor: '#ffffff'
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        animation: {
          duration: 1000
        },
        plugins: {
          legend: {
            position: 'bottom'
          }
        }
      }
    });
  }
  
  // Timeline chart
  const timelineCtx = document.getElementById('eklektik-timeline-chart')?.getContext('2d');
  if (timelineCtx && chartsData.timeline) {
    console.log('📈 [EKLEKTIK] Création graphique timeline:', chartsData.timeline);
    window.eklektikCharts.timeline = new Chart(timelineCtx, {
      type: 'line',
      data: {
        labels: chartsData.timeline.labels || [],
        datasets: [{
          label: 'Appels API',
          data: chartsData.timeline.data || [],
          borderColor: '#3b82f6',
          backgroundColor: 'rgba(59, 130, 246, 0.1)',
          tension: 0.4,
          fill: true
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        animation: {
          duration: 1000
        },
        interaction: {
          intersect: false,
          mode: 'index'
        },
        scales: {
          y: {
            beginAtZero: true,
            grid: {
              color: 'rgba(0,0,0,0.1)'
            }
          },
          x: {
            grid: {
              color: 'rgba(0,0,0,0.1)'
            }
          }
        }
      }
    });
  }
}

// Variables globales pour la pagination
let currentPage = 1;
let pageSize = 20;
let totalNumbers = 0;
let allEklektikNumbers = [];
let autoRefreshInterval = null;

function setupPagination(numbers) {
  allEklektikNumbers = numbers || [];
  totalNumbers = allEklektikNumbers.length;
  currentPage = 1;
  
  updatePaginationDisplay();
  updateTableWithPagination();
}

function updatePaginationDisplay() {
  const start = ((currentPage - 1) * pageSize) + 1;
  const end = Math.min(currentPage * pageSize, totalNumbers);
  
  document.getElementById('eklektik-pagination-info').textContent = 
    `Affichage des numéros ${start}-${end} sur ${totalNumbers}`;
  
  // Update button states
  document.getElementById('prev-page-btn').disabled = currentPage <= 1;
  document.getElementById('next-page-btn').disabled = currentPage >= Math.ceil(totalNumbers / pageSize);
}

function changePage(direction) {
  const maxPages = Math.ceil(totalNumbers / pageSize);
  
  if (direction === 1 && currentPage < maxPages) {
    currentPage++;
  } else if (direction === -1 && currentPage > 1) {
    currentPage--;
  }
  
  updatePaginationDisplay();
  updateTableWithPagination();
}

function changePageSize() {
  pageSize = parseInt(document.getElementById('page-size-select').value);
  currentPage = 1; // Reset to first page
  updatePaginationDisplay();
  updateTableWithPagination();
}

function updateTableWithPagination() {
  const start = (currentPage - 1) * pageSize;
  const end = start + pageSize;
  const pageNumbers = allEklektikNumbers.slice(start, end);
  
  updateEklektikTable(pageNumbers);
}

function toggleAutoRefresh() {
  // Auto-refresh désactivé pour stabilité (demande utilisateur)
  if (autoRefreshInterval) {
    clearInterval(autoRefreshInterval);
    autoRefreshInterval = null;
  }
  const checkbox = document.getElementById('auto-refresh-checkbox');
  if (checkbox) checkbox.checked = false;
  console.log('❌ Auto-actualisation désactivée');
}


// Bulk test functionality
async function startBulkTest() {
  try {
    const btn = document.getElementById('bulk-test-btn');
    const progressDiv = document.getElementById('bulk-test-progress');
    const progressText = document.getElementById('test-progress-text');
    const progressFill = document.getElementById('test-progress-fill');
    const summaryDiv = document.getElementById('test-results-summary');
    
    // Disable button and show progress
    btn.disabled = true;
    btn.textContent = '🧪 Test en cours...';
    progressDiv.style.display = 'block';
    summaryDiv.style.display = 'none';
    
    progressText.textContent = 'Authentification...';
    progressFill.style.width = '10%';
    
    console.log('🧪 [EKLEKTIK] Démarrage du test en masse...');
    
    // Start the bulk test
    const response = await fetch('/api/eklektik/test-all', {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        limit: 50, // Limit for demo
        operator: 'ALL'
      })
    });
    
    progressText.textContent = 'Test des numéros en cours...';
    progressFill.style.width = '50%';
    
    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }
    
    const result = await response.json();
    
    progressText.textContent = 'Traitement des résultats...';
    progressFill.style.width = '90%';
    
    console.log('✅ [EKLEKTIK] Test en masse terminé:', result);
    
    // Update results
    updateBulkTestResults(result);
    
    progressText.textContent = 'Terminé !';
    progressFill.style.width = '100%';
    
    // Hide progress after a moment
    setTimeout(() => {
      progressDiv.style.display = 'none';
      btn.disabled = false;
      btn.textContent = '🧪 Tester Tous les Numéros';
    }, 2000);
    
    showNotification('✅ Test en masse terminé avec succès !', 'success', 3000);
    
  } catch (error) {
    console.error('❌ [EKLEKTIK] Erreur lors du test en masse:', error);
    
    // Reset UI on error
    const btn = document.getElementById('bulk-test-btn');
    const progressDiv = document.getElementById('bulk-test-progress');
    
    progressDiv.style.display = 'none';
    btn.disabled = false;
    btn.textContent = '🧪 Tester Tous les Numéros';
    
    showNotification('❌ Erreur lors du test en masse', 'error', 3000);
  }
}

function updateBulkTestResults(result) {
  const summaryDiv = document.getElementById('test-results-summary');
  const tableBody = document.getElementById('eklektik-numbers-tbody');
  
  if (!result.success || !result.statistics) {
    showNotification('❌ Erreur dans les résultats du test', 'error', 3000);
    return;
  }
  
  const stats = result.statistics;
  
  // Update statistics
  document.getElementById('test-stat-total').textContent = stats.total || 0;
  document.getElementById('test-stat-active').textContent = stats.active || 0;
  document.getElementById('test-stat-inactive').textContent = (stats.available || 0) + (stats.timeout || 0);
  document.getElementById('test-stat-errors').textContent = stats.errors || 0;
  document.getElementById('test-stat-success-rate').textContent = `${stats.success_rate || 0}%`;
  document.getElementById('test-stat-avg-time').textContent = `${stats.avg_response_time || 0}ms`;
  
  // Afficher les timeouts séparément si présents
  if (stats.timeout > 0) {
    console.log(`⏱️ [EKLEKTIK] ${stats.timeout} timeout(s) détecté(s) - API Eklektik lente`);
  }
  
  // Show summary
  summaryDiv.style.display = 'block';
  
  // Update table with test results
  if (result.results && result.results.length > 0) {
    tableBody.innerHTML = result.results.map(testResult => `
      <tr>
        <td><strong>${testResult.msisdn}</strong></td>
        <td>
          <span class="service-badge service-subscription">
            📱 SUBSCRIPTION
          </span>
        </td>
        <td>
          <span class="status-badge status-${(testResult.final_status || 'unknown').toLowerCase()}">
            ${getStatusIcon(testResult.final_status)} ${testResult.final_status || 'Unknown'}
          </span>
        </td>
        <td>
          <span class="operator-badge operator-${(testResult.operator || 'unknown').toLowerCase()}">
            ${testResult.operator === 'TT' ? '🔵' : testResult.operator === 'Orange' ? '🟠' : '❓'} ${testResult.operator || 'Unknown'}
          </span>
        </td>
        <td style="font-size: 11px; max-width: 120px; overflow: hidden; text-overflow: ellipsis;">
          ${testResult.payment_method || 'Unknown'}
        </td>
        <td>
          <div style="font-size: 10px;">
            <div>🧪 ${testResult.tests ? testResult.tests.length : 0} tests</div>
            <div>✅ ${testResult.summary && testResult.summary.active_offers ? testResult.summary.active_offers.length : 0} actifs</div>
            <div>📋 ${testResult.summary && testResult.summary.available_offers_count ? testResult.summary.available_offers_count : 0} disponibles</div>
          </div>
        </td>
        <td>
          <strong style="color: var(--primary);">${testResult.subscription_name || 'N/A'}</strong>
          <br><small>${testResult.response_time_ms || 0}ms</small>
        </td>
        <td>
          <span class="source-badge" style="font-size: 10px;">
            🟢 API Réel Testé
          </span>
        </td>
        <td>
          <div class="action-buttons">
            <button class="btn-sm btn-primary" onclick="viewTestDetails('${testResult.msisdn}')" title="Voir détails">
              👁️
            </button>
            <button class="btn-sm btn-secondary" onclick="testEklektikNumber('${testResult.msisdn}')" title="Tester">
              🧪
            </button>
          </div>
        </td>
      </tr>
    `).join('');
  } else {
    tableBody.innerHTML = `
      <tr>
        <td colspan="9" style="text-align: center; padding: 40px; color: var(--muted);">
          📱 Aucun résultat de test disponible
        </td>
      </tr>
    `;
  }
}

function viewTestDetails(msisdn) {
  showNotification(`👁️ Détails pour le test du numéro ${msisdn}`, 'info', 2000);
  console.log(`[EKLEKTIK] Demande de détails pour ${msisdn}`);
  // TODO: Implement detailed view
}

function viewEklektikDetails(phoneNumber) {
  showNotification(`👁️ Détails pour ${phoneNumber}`, 'info', 2000);
  // TODO: Implement detail view
}

function testEklektikNumber(phoneNumber) {
  showNotification(`🧪 Test du numéro ${phoneNumber}...`, 'info', 2000);
  // TODO: Implement number testing
}

function showEklektikError(message) {
  const tbody = document.getElementById('eklektik-numbers-tbody');
  tbody.innerHTML = `
    <tr>
      <td colspan="7" style="text-align: center; padding: 40px; color: var(--danger);">
        ❌ ${message}
      </td>
    </tr>
  `;
}

// ========================================
// NOUVELLES FONCTIONS POUR STATISTIQUES EKLEKTIK
// ========================================

// Variables globales pour les graphiques Eklektik
let eklektikCharts = {};

// Variables globales pour les opérateurs
let availableOperators = [];
let selectedOperators = []; // Sera initialisé selon le rôle utilisateur
let hasAllOption = false; // Indique si "Tous les opérateurs" est disponible

// Center active tab on mobile
function centerActiveTab(activeTab) {
  const navTabs = document.querySelector('.nav-tabs');
  const tabRect = activeTab.getBoundingClientRect();
  const navRect = navTabs.getBoundingClientRect();
  
  // Only auto-scroll on mobile/tablet
  if (window.innerWidth <= 768) {
    const scrollLeft = activeTab.offsetLeft - (navRect.width / 2) + (tabRect.width / 2);
    navTabs.scrollTo({
      left: Math.max(0, scrollLeft),
      behavior: 'smooth'
    });
  }
}


// Fonction obsolète supprimée - utilisez le composant eklektik-charts

// Fonction utilitaire pour récupérer les statistiques
async function fetchEklektikStats(endpoint, params) {
  const url = new URL(endpoint, window.location.origin);
  Object.keys(params).forEach(key => url.searchParams.append(key, params[key]));
  
  const response = await fetch(url, {
    headers: {
      'Accept': 'application/json',
      'Content-Type': 'application/json'
    }
  });
  
  if (!response.ok) {
    throw new Error(`HTTP error! status: ${response.status}`);
  }
  
  return await response.json();
}

// Charger les données Eklektik
// loadEklektikData déjà définie plus haut

// showEklektikStatsLoading, showEklektikStatsError et updateEklektikStatsDisplay déjà définies plus haut

// Mettre à jour les statistiques par opérateur
function updateEklektikOperatorsStats(distribution) {
  const container = document.getElementById('eklektik-operators-stats');
  if (!container) return;

  let html = '';
  for (const [operator, data] of Object.entries(distribution)) {
    html += `
      <div class="card mb-2">
        <div class="card-body">
          <h6 class="card-title">${operator}</h6>
          <p class="card-text">
            <strong>Revenus TTC:</strong> ${new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'TND' }).format(data.revenue_ttc || 0)}<br>
            <strong>Revenus HT:</strong> ${new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'TND' }).format(data.revenue_ht || 0)}<br>
            <strong>CA BigDeal:</strong> ${new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'TND' }).format(data.ca_bigdeal || 0)}
          </p>
        </div>
      </div>
    `;
  }

  container.innerHTML = html || '<div class="text-center text-muted">Aucune donnée disponible</div>';
}

// Créer les graphiques des statistiques Eklektik
async function createEklektikStatsCharts(data) {
  const { overviewChart, revenueEvolution, revenueDistribution } = data;
  
  console.log('🎨 [CHARTS] Création des graphiques avec données:', data);
  
  // Détruire les graphiques existants
  console.log('🗑️ [CHARTS] Destruction des graphiques existants:', Object.keys(eklektikCharts));
  Object.values(eklektikCharts).forEach(chart => {
    if (chart) {
      console.log('🗑️ [CHARTS] Destruction d\'un graphique');
      chart.destroy();
    }
  });
  eklektikCharts = {};
  
  console.log('📊 [CHARTS] Création des nouveaux graphiques...');
  
  // Attendre un peu avant de créer les graphiques pour éviter les conflits
  setTimeout(() => {
    // Graphique multi-axes principal (Vue d'ensemble)
    createEklektikOverviewChart(overviewChart?.chart);
    
    // Graphique d'évolution des revenus
    createEklektikRevenueEvolutionChart(revenueEvolution?.chart);
    
    // Graphique de répartition par opérateur
    createEklektikOperatorsDistributionChart(revenueDistribution?.pie_chart);
    
    // Graphique CA par partenaire
    createEklektikCAPartnersChart(revenueDistribution?.bar_chart);
    
    // Afficher les statistiques par opérateur
    if (revenueDistribution?.data?.distribution) {
      displayEklektikOperatorsStats(revenueDistribution.data.distribution);
    } else {
      console.warn('❌ [OPERATORS STATS] Données de distribution manquantes:', revenueDistribution);
    }
  }, 50); // Délai de 50ms pour éviter les conflits de rendu
}

// Graphique multi-axes principal (Vue d'ensemble)
function createEklektikOverviewChart(chartData) {
  const ctx = document.getElementById('eklektik-overview-chart');
  if (!ctx || !chartData) {
    console.log('❌ [OVERVIEW CHART] Pas de données ou contexte manquant');
    return;
  }
  
  // Vérifier si le graphique existe déjà et a les mêmes données
  if (eklektikCharts.overview && eklektikCharts.overview.data) {
    const currentData = JSON.stringify(eklektikCharts.overview.data);
    const newData = JSON.stringify(chartData);
    if (currentData === newData) {
      console.log('🔄 [OVERVIEW CHART] Données identiques, pas de recréation');
      return;
    }
  }
  
  // Détruire le graphique existant s'il existe
  if (eklektikCharts.overview) {
    console.log('🗑️ [OVERVIEW CHART] Destruction du graphique existant');
    eklektikCharts.overview.destroy();
    eklektikCharts.overview = null;
  }
  
  // Attendre un peu avant de créer le nouveau graphique
  setTimeout(() => {
  console.log('🎨 [DEBUG] Création du graphique multi-axes avec données:', chartData);
  
  // Créer le graphique avec des options ultra-strictes
  const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    animation: false,
    animations: {
      duration: 0
    },
    // Désactiver complètement toutes les animations
    transitions: {
      active: {
        animation: {
          duration: 0
        }
      },
      resize: {
        animation: {
          duration: 0
        }
      }
    },
    elements: {
      point: {
        hoverRadius: 0
      },
      line: {
        tension: 0
      }
    },
    plugins: {
      legend: {
        animation: false
      },
      tooltip: {
        animation: false
      }
    },
    interaction: {
      mode: 'index',
      intersect: false,
    },
    scales: {
      x: {
        display: true,
        title: {
          display: true,
          text: 'Date'
        }
      },
      'y-revenue': {
        type: 'linear',
        display: true,
        position: 'left',
        title: {
          display: true,
          text: 'Revenue TTC (K TND)',
          color: 'rgb(54, 162, 235)'
        },
        ticks: {
          color: 'rgb(54, 162, 235)',
          callback: function(value) {
            return value + 'K';
          }
        },
        grid: {
          drawOnChartArea: false,
        }
      },
      'y-active': {
        type: 'linear',
        display: true,
        position: 'right',
        title: {
          display: true,
          text: 'Active Sub',
          color: 'rgb(255, 99, 132)'
        },
        ticks: {
          color: 'rgb(255, 99, 132)',
          callback: function(value) {
            return new Intl.NumberFormat('fr-FR').format(value);
          }
        },
        grid: {
          drawOnChartArea: false,
        }
      },
      'y-rate': {
        type: 'linear',
        display: true,
        position: 'right',
        title: {
          display: true,
          text: 'Taux Facturation / Part BigDeal (%)',
          color: 'rgb(75, 192, 192)'
        },
        ticks: {
          color: 'rgb(75, 192, 192)',
          callback: function(value) {
            return value.toFixed(1) + '%';
          }
        },
        grid: {
          drawOnChartArea: false,
        }
      }
    },
    plugins: {
      legend: {
        display: true,
        position: 'bottom',
        labels: {
          usePointStyle: true,
          padding: 20
        }
      },
      tooltip: {
        mode: 'index',
        intersect: false,
        callbacks: {
          label: function(context) {
            let label = context.dataset.label || '';
            if (label) {
              label += ': ';
            }
            
            if (context.dataset.yAxisID === 'y-revenue') {
              label += new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'TND' }).format(context.parsed.y * 1000);
            } else if (context.dataset.yAxisID === 'y-active') {
              label += new Intl.NumberFormat('fr-FR').format(context.parsed.y);
            } else if (context.dataset.yAxisID === 'y-rate') {
              label += context.parsed.y.toFixed(2) + '%';
            }
            
            return label;
          }
        }
      }
    }
  };
  
  console.log('🔧 [DEBUG] Options du graphique:', chartOptions);
  
  // Créer le graphique avec interception de requestAnimationFrame
  // Pas d'interception globale - laissons les autres graphiques fonctionner normalement
  
  try {
    eklektikCharts.overview = new Chart(ctx, {
      type: 'bar',
      data: chartData,
      options: chartOptions
    });
  } catch (error) {
    console.error('❌ [OVERVIEW CHART] Erreur lors de la création:', error);
  } finally {
    // Restaurer requestAnimationFrame
    window.requestAnimationFrame = originalRAF;
    window.cancelAnimationFrame = originalCAF;
  }
  
  console.log('✅ [OVERVIEW CHART] Graphique multi-axes créé avec succès');
  console.log('🔍 [DEBUG] Graphique overview:', eklektikCharts.overview);
  }, 10); // Délai de 10ms pour éviter les conflits de rendu
}

// Graphique d'évolution des revenus
function createEklektikRevenueEvolutionChart(chartData) {
  const ctx = document.getElementById('eklektik-revenue-evolution-chart');
  if (!ctx || !chartData) {
    console.log('❌ [REVENUE EVOLUTION CHART] Pas de données ou contexte manquant');
    return;
  }
  
  // Vérifier si le graphique existe déjà et a les mêmes données
  if (eklektikCharts.revenueEvolution && eklektikCharts.revenueEvolution.data) {
    const currentData = JSON.stringify(eklektikCharts.revenueEvolution.data);
    const newData = JSON.stringify(chartData);
    if (currentData === newData) {
      console.log('🔄 [REVENUE EVOLUTION CHART] Données identiques, pas de recréation');
      return;
    }
  }
  
  // Détruire le graphique existant s'il existe
  if (eklektikCharts.revenueEvolution) {
    console.log('🗑️ [REVENUE EVOLUTION CHART] Destruction du graphique existant');
    eklektikCharts.revenueEvolution.destroy();
    eklektikCharts.revenueEvolution = null;
  }
  
  eklektikCharts.revenueEvolution = new Chart(ctx, {
    type: 'line',
    data: chartData,
    options: {
      responsive: true,
      maintainAspectRatio: false,
      animation: false,
      animations: {
        duration: 0
      },
      scales: {
        y: {
          beginAtZero: true,
          ticks: {
            callback: function(value) {
              return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'TND' }).format(value);
            }
          }
        }
      },
      plugins: {
        tooltip: {
          callbacks: {
            label: function(context) {
              return context.dataset.label + ': ' + 
                new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'TND' }).format(context.parsed.y);
            }
          }
        }
      }
    }
  });
}

// Graphique de répartition par opérateur
function createEklektikOperatorsDistributionChart(chartData) {
  const ctx = document.getElementById('eklektik-operators-distribution-chart');
  if (!ctx || !chartData) {
    console.log('❌ [OPERATORS DISTRIBUTION CHART] Pas de données ou contexte manquant');
    return;
  }
  
  // Vérifier si le graphique existe déjà et a les mêmes données
  if (eklektikCharts.operatorsDistribution && eklektikCharts.operatorsDistribution.data) {
    const currentData = JSON.stringify(eklektikCharts.operatorsDistribution.data);
    const newData = JSON.stringify(chartData);
    if (currentData === newData) {
      console.log('🔄 [OPERATORS DISTRIBUTION CHART] Données identiques, pas de recréation');
      return;
    }
  }
  
  // Détruire le graphique existant s'il existe
  if (eklektikCharts.operatorsDistribution) {
    console.log('🗑️ [OPERATORS DISTRIBUTION CHART] Destruction du graphique existant');
    eklektikCharts.operatorsDistribution.destroy();
    eklektikCharts.operatorsDistribution = null;
  }
  
  eklektikCharts.operatorsDistribution = new Chart(ctx, {
    type: 'doughnut',
    data: chartData,
    options: {
      responsive: true,
      maintainAspectRatio: false,
      animation: false,
      animations: {
        duration: 0
      },
      plugins: {
        tooltip: {
          callbacks: {
            label: function(context) {
              return context.label + ': ' + 
                new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'TND' }).format(context.parsed);
            }
          }
        }
      }
    }
  });
}

// Graphique CA par partenaire
function createEklektikCAPartnersChart(chartData) {
  const ctx = document.getElementById('eklektik-ca-partners-chart');
  if (!ctx || !chartData) {
    console.log('❌ [CA PARTNERS CHART] Pas de données ou contexte manquant');
    return;
  }
  
  // Vérifier si le graphique existe déjà et a les mêmes données
  if (eklektikCharts.caPartners && eklektikCharts.caPartners.data) {
    const currentData = JSON.stringify(eklektikCharts.caPartners.data);
    const newData = JSON.stringify(chartData);
    if (currentData === newData) {
      console.log('🔄 [CA PARTNERS CHART] Données identiques, pas de recréation');
      return;
    }
  }
  
  // Détruire le graphique existant s'il existe
  if (eklektikCharts.caPartners) {
    console.log('🗑️ [CA PARTNERS CHART] Destruction du graphique existant');
    eklektikCharts.caPartners.destroy();
    eklektikCharts.caPartners = null;
  }
  
  eklektikCharts.caPartners = new Chart(ctx, {
    type: 'bar',
    data: chartData,
    options: {
      responsive: true,
      maintainAspectRatio: false,
      animation: false,
      animations: {
        duration: 0
      },
      scales: {
        y: {
          beginAtZero: true,
          ticks: {
            callback: function(value) {
              return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'TND' }).format(value);
            }
          }
        }
      },
      plugins: {
        tooltip: {
          callbacks: {
            label: function(context) {
              return context.dataset.label + ': ' + 
                new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'TND' }).format(context.parsed.y);
            }
          }
        }
      }
    }
  });
}

// Afficher les statistiques par opérateur
function displayEklektikOperatorsStats(distribution) {
  const container = document.getElementById('eklektik-operators-stats');
  if (!container || !distribution) {
    console.log('❌ [OPERATORS STATS] Pas de données ou conteneur manquant');
    return;
  }
  
  let html = '';
  
  for (const [operator, data] of Object.entries(distribution)) {
    const newSubs = (data.new_subscriptions ?? data.new_subs ?? data.subscriptions ?? data.activated ?? 0);
    const active = (data.active_subscribers ?? data.active ?? 0);
    const fact = (data.facturation ?? 0);
    const rev = (data.revenue_ttc ?? data.ca_bigdeal ?? 0);
    const formattedNewSubs = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(newSubs);
    const formattedActive = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(active);
    const formattedFact = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(fact);
    const formattedRev = new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'TND', maximumFractionDigits: 0 }).format(rev);
    html += `
      <div class="card mb-2" style="border: 1px solid var(--border); border-radius: 8px; padding: 12px;">
        <div class="card-body" style="padding: 0;">
          <h6 class="card-title" style="margin: 0 0 8px 0; font-weight: 600; color: var(--brand-dark);">${operator}</h6>
          <div style="font-size: 12px; line-height: 1.4;">
            <div><strong>Active subs:</strong> ${formattedActive}</div>
            <div><strong>Nouveaux abonnements:</strong> ${formattedNewSubs}</div>
            <div><strong>Facturations:</strong> ${formattedFact}</div>
            <div><strong>Revenus TTC:</strong> ${formattedRev}</div>
          </div>
        </div>
      </div>
    `;
  }
  
  container.innerHTML = html;
}

// Fonctions pour les boutons de configuration
async function checkEklektikSyncStatus() {
  try {
    const response = await fetch('/api/eklektik-dashboard/sync-status');
    const data = await response.json();
    
    if (data.success && data.data) {
      const status = data.data;
      const statusValue = status.status || 'unknown';
      const statusColor = statusValue === 'healthy' ? 'success' : 
                         statusValue === 'warning' ? 'warning' : 'danger';
      
      const lastSync = status.last_sync ? 
        new Date(status.last_sync).toLocaleString('fr-FR') : 'Jamais';
      
      const totalRecords = status.total_records || 0;
      
      alert(`Statut Eklektik: ${statusValue.toUpperCase()}\nDernière sync: ${lastSync}\nEnregistrements: ${totalRecords}`);
    } else {
      alert('Erreur: Impossible de récupérer le statut de synchronisation');
    }
  } catch (error) {
    console.error('❌ [EKLEKTIK SYNC] Erreur lors de la vérification du statut:', error);
    alert('Erreur lors de la vérification du statut de synchronisation');
  }
}

async function clearEklektikCache() {
  try {
    const response = await fetch('/api/eklektik-dashboard/clear-cache', { method: 'POST' });
    const data = await response.json();
    
    if (data.success) {
      alert('Cache vidé avec succès!');
      console.log('Cache vidé - les graphiques vont se recharger automatiquement');
    } else {
      alert('Erreur lors du vidage du cache: ' + data.message);
    }
  } catch (error) {
    console.error('❌ [EKLEKTIK CACHE] Erreur lors du vidage du cache:', error);
    alert('Erreur lors du vidage du cache');
  }
}

// Graphique d'évolution des abonnements
function createEklektikSubscriptionsChart(data) {
  const ctx = document.getElementById('eklektik-subscriptions-chart');
  if (!ctx || !data) {
    console.log('❌ [SUBSCRIPTIONS CHART] Pas de données ou contexte manquant', { ctx: !!ctx, data: data });
    return;
  }
  
  console.log('📊 [SUBSCRIPTIONS CHART] Données reçues:', data);
  console.log('📊 [SUBSCRIPTIONS CHART] Contexte canvas:', { 
    width: ctx.width, 
    height: ctx.height, 
    offsetWidth: ctx.offsetWidth, 
    offsetHeight: ctx.offsetHeight 
  });
  
  // Détruire le graphique existant s'il existe
  if (eklektikCharts.subscriptions) {
    console.log('🗑️ [SUBSCRIPTIONS CHART] Destruction du graphique existant');
    eklektikCharts.subscriptions.destroy();
    eklektikCharts.subscriptions = null;
  }
  
  eklektikCharts.subscriptions = new Chart(ctx, {
    type: 'line',
    data: {
      labels: ['Nouveaux', 'Actifs', 'Désabonnements'],
      datasets: [{
        label: 'Abonnements',
        data: [
          data.kpis?.sub_count || 0,
          data.kpis?.active_subscriptions || 0,
          data.kpis?.unsub_count || 0
        ],
        borderColor: '#3b82f6',
        backgroundColor: 'rgba(59, 130, 246, 0.1)',
        tension: 0.4
      }]
    },
    options: {
      responsive: true,
      plugins: {
        legend: {
          display: false
        }
      },
      scales: {
        y: {
          beginAtZero: true
        }
      }
    }
  });
}

// Graphique d'évolution des revenus
function createEklektikRevenueChart(data) {
  const ctx = document.getElementById('eklektik-revenue-chart');
  if (!ctx || !data) {
    console.log('❌ [REVENUE CHART] Pas de données ou contexte manquant', { ctx: !!ctx, data: data });
    return;
  }
  
  console.log('📊 [REVENUE CHART] Données reçues:', data);
  console.log('📊 [REVENUE CHART] Contexte canvas:', { 
    width: ctx.width, 
    height: ctx.height, 
    offsetWidth: ctx.offsetWidth, 
    offsetHeight: ctx.offsetHeight 
  });
  
  // Détruire le graphique existant s'il existe
  if (eklektikCharts.revenue) {
    console.log('🗑️ [REVENUE CHART] Destruction du graphique existant');
    eklektikCharts.revenue.destroy();
    eklektikCharts.revenue = null;
  }
  
  eklektikCharts.revenue = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: ['RENEW', 'CHARGE'],
      datasets: [{
        label: 'Revenus (TND)',
        data: [
          data.revenue_by_action?.RENEW || 0,
          data.revenue_by_action?.CHARGE || 0
        ],
        backgroundColor: ['#10b981', '#f59e0b']
      }]
    },
    options: {
      responsive: true,
      plugins: {
        legend: {
          display: false
        }
      },
      scales: {
        y: {
          beginAtZero: true
        }
      }
    }
  });
}

// Graphique de répartition par action
function createEklektikActionsPieChart(data) {
  const ctx = document.getElementById('eklektik-actions-pie-chart');
  if (!ctx || !data?.kpis) {
    console.log('❌ [ACTIONS CHART] Pas de données ou contexte manquant', { ctx: !!ctx, data: data });
    return;
  }
  
  if (typeof Chart === 'undefined') {
    console.error('❌ [ACTIONS CHART] Chart.js non chargé');
    return;
  }
  
  // Détruire le graphique existant s'il existe
  if (eklektikCharts.actions) {
    eklektikCharts.actions.destroy();
    eklektikCharts.actions = null;
  }
  
  const kpis = data.kpis;
  console.log('📊 [ACTIONS CHART] Données KPIs:', kpis);
  console.log('📊 [ACTIONS CHART] Contexte canvas:', { 
    width: ctx.width, 
    height: ctx.height, 
    offsetWidth: ctx.offsetWidth, 
    offsetHeight: ctx.offsetHeight 
  });
  
  const actions = [
    { label: 'SUB', value: kpis.new_subscriptions || 0, color: '#3b82f6' },
    { label: 'RENEW', value: kpis.renewals || 0, color: '#10b981' },
    { label: 'CHARGE', value: kpis.charges || 0, color: '#f59e0b' },
    { label: 'UNSUB', value: kpis.unsubscriptions || 0, color: '#ef4444' }
  ];
  
  console.log('📊 [ACTIONS CHART] Actions calculées:', actions);
  
  // Filtrer les actions avec des valeurs > 0
  const filteredActions = actions.filter(action => action.value > 0);
  
  console.log('📊 [ACTIONS CHART] Actions filtrées:', filteredActions);
  
  if (filteredActions.length === 0) {
    console.log('⚠️ [ACTIONS CHART] Aucune action avec valeur > 0');
    return;
  }
  
  console.log('📊 [ACTIONS CHART] Création du graphique avec données:', {
    labels: filteredActions.map(action => action.label),
    data: filteredActions.map(action => action.value)
  });
  
  eklektikCharts.actions = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: filteredActions.map(action => action.label),
      datasets: [{
        label: 'Nombre d\'actions',
        data: filteredActions.map(action => action.value),
        backgroundColor: filteredActions.map(action => action.color),
        borderColor: filteredActions.map(action => action.color),
        borderWidth: 1
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: true,
      aspectRatio: 2,
      interaction: {
        intersect: false,
        mode: 'index'
      },
      plugins: {
        legend: {
          display: false
        },
        tooltip: {
          callbacks: {
            label: function(context) {
              const label = context.label || '';
              const value = context.parsed.y;
              const total = context.dataset.data.reduce((a, b) => a + b, 0);
              const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
              return `${label}: ${value} (${percentage}%)`;
            }
          }
        }
      },
      scales: {
        y: {
          beginAtZero: true,
          ticks: {
            stepSize: 1
          }
        }
      },
      animation: false,
      transitions: {
        active: {
          animation: {
            duration: 0
          }
        },
        resize: {
          animation: {
            duration: 0
          }
        }
      },
      layout: {
        padding: {
          top: 10,
          bottom: 10,
          left: 10,
          right: 10
        }
      }
    }
  });
  
  console.log('✅ [ACTIONS CHART] Graphique créé avec succès');
}

// Graphique de répartition par opérateur
async function createEklektikOperatorsChart(data) {
  const ctx = document.getElementById('eklektik-operators-chart');
  if (!ctx || !data?.operators_distribution) {
    console.log('❌ [OPERATORS CHART] Pas de données ou contexte manquant', { ctx: !!ctx, data: data });
    return;
  }
  
  if (typeof Chart === 'undefined') {
    console.error('❌ [OPERATORS CHART] Chart.js non chargé');
    return;
  }
  
  console.log('📊 [OPERATORS CHART] Données opérateurs:', data.operators_distribution);
  console.log('📊 [OPERATORS CHART] Contexte canvas:', { 
    width: ctx.width, 
    height: ctx.height, 
    offsetWidth: ctx.offsetWidth, 
    offsetHeight: ctx.offsetHeight 
  });
  
  // Détruire le graphique existant s'il existe
  if (eklektikCharts.operators) {
    console.log('🗑️ [OPERATORS CHART] Destruction du graphique existant');
    try {
      eklektikCharts.operators.destroy();
    } catch (e) {
      console.warn('Erreur lors de la destruction du graphique opérateurs:', e);
    }
    eklektikCharts.operators = null;
  }
  
  // Attendre un tick pour éviter les conflits de rendu
  await new Promise(resolve => setTimeout(resolve, 10));
  
  // Extraire les données des opérateurs
  const operatorsData = data.operators_distribution;
  const operators = Object.keys(operatorsData);
  const values = operators.map(op => operatorsData[op].total);
  
  console.log('📊 [OPERATORS CHART] Opérateurs extraits:', operators);
  console.log('📊 [OPERATORS CHART] Valeurs extraites:', values);
  
  // Couleurs pour chaque opérateur
  const colors = {
    'Orange': '#FF9500',
    'TT': '#FF6384',
    'Taraji': '#4BC0C0',
    'Timwe': '#36A2EB',
    'Ooredoo': '#FFCE56',
    'Unknown': '#9E9E9E'
  };
  
  eklektikCharts.operators = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: operators,
      datasets: [{
        label: 'Transactions par Opérateur',
        data: values,
        backgroundColor: operators.map(op => colors[op] || '#9E9E9E'),
        borderColor: operators.map(op => colors[op] || '#9E9E9E'),
        borderWidth: 1
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: true,
      aspectRatio: 2,
      interaction: {
        intersect: false,
        mode: 'index'
      },
      plugins: {
        legend: {
          display: false
        },
        tooltip: {
          callbacks: {
            label: function(context) {
              const label = context.label || '';
              const value = context.parsed.y;
              const total = context.dataset.data.reduce((a, b) => a + b, 0);
              const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
              const operatorData = operatorsData[label];
              return `${label}: ${value} transactions (${percentage}%)\n` +
                     `- Abonnements: ${operatorData.sub}\n` +
                     `- Renouvellements: ${operatorData.renew}\n` +
                     `- Facturations: ${operatorData.charge}\n` +
                     `- Revenus: ${operatorData.revenue} TND`;
            }
          }
        }
      },
      scales: {
        y: {
          beginAtZero: true,
          ticks: {
            stepSize: 1
          }
        }
      },
      layout: {
        padding: {
          top: 10,
          bottom: 10,
          left: 10,
          right: 10
        }
      },
      animation: false,
      transitions: {
        active: {
          animation: {
            duration: 0
          }
        },
        resize: {
          animation: {
            duration: 0
          }
        }
      }
    }
  });
  
  console.log('✅ [OPERATORS CHART] Graphique créé avec succès');
}

// Graphique du taux de facturation
function createEklektikBillingRateChart(data) {
  const ctx = document.getElementById('eklektik-billing-rate-chart');
  if (!ctx || !data) {
    console.log('❌ [BILLING RATE CHART] Pas de données ou contexte manquant', { ctx: !!ctx, data: data });
    return;
  }
  
  console.log('📊 [BILLING RATE CHART] Données reçues:', data);
  console.log('📊 [BILLING RATE CHART] Contexte canvas:', { 
    width: ctx.width, 
    height: ctx.height, 
    offsetWidth: ctx.offsetWidth, 
    offsetHeight: ctx.offsetHeight 
  });
  
  // Détruire le graphique existant s'il existe
  if (eklektikCharts.billingRate) {
    console.log('🗑️ [BILLING RATE CHART] Destruction du graphique existant');
    eklektikCharts.billingRate.destroy();
    eklektikCharts.billingRate = null;
  }
  
  eklektikCharts.billingRate = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: ['Taux de Facturation'],
      datasets: [{
        label: 'Taux (%)',
        data: [data.billing_rate || 0],
        backgroundColor: '#10b981'
      }]
    },
    options: {
      responsive: true,
      plugins: {
        legend: {
          display: false
        }
      },
      scales: {
        y: {
          beginAtZero: true,
          max: 100
        }
      }
    }
  });
}

// Afficher l'erreur des statistiques
function showEklektikStatsError(message) {
  const elements = [
    'eklektik-billing-rate', 'eklektik-revenue', 'eklektik-active-subscriptions',
    'eklektik-new-subscriptions', 'eklektik-unsubscriptions', 'eklektik-renewals', 'eklektik-charges', 'eklektik-billed-clients'
  ];
  
  elements.forEach(id => {
    const element = document.getElementById(id);
    if (element) {
      element.textContent = 'Erreur';
    }
  });
  
  console.error('❌ [EKLEKTIK STATS]', message);
}

// Exporter les statistiques Eklektik
function exportEklektikStats() {
  showNotification('📥 Export des statistiques Eklektik en cours...', 'info', 2000);
  // TODO: Implémenter l'export des statistiques
}

// Debug pour les événements de redimensionnement (désactivé pour éviter les boucles)
// window.addEventListener('resize', function() {
//   console.log('📏 [RESIZE] Redimensionnement détecté');
//   clearTimeout(resizeTimeout);
//   resizeTimeout = setTimeout(() => {
//     console.log('📏 [RESIZE] Redimensionnement terminé, recréation des graphiques');
//     if (Object.keys(eklektikCharts).length > 0) {
//       // Les graphiques se rechargent automatiquement
//     }
//   }, 300);
// });

