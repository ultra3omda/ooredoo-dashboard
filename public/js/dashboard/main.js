    // Définition immédiate des couleurs thème - CRITIQUE pour éviter les erreurs
    window.THEME_COLORS = {
      primary: '#6C4BA0',
      primaryRgba: 'rgba(108, 75, 160, 0.15)',
      secondary: '#D4A843',
      accent: '#D4A843',
      success: '#10b981',
      warning: '#f59e0b',
      muted: '#A1A1AA',
      mutedRgba: 'rgba(161, 161, 170, 0.2)',
      gridColor: 'rgba(255, 255, 255, 0.05)',
      textColor: '#A1A1AA',
      cardBg: '#161131'
    };
    
    // Alias global immédiat
    const THEME_COLORS = window.THEME_COLORS;
    
    // Global variables for charts and data
    let dashboardData = null;
    let charts = {};
    
    // Pagination variables
    let allMerchants = [];
    let currentMerchantsPage = 1;
    let merchantsPerPage = 25;

    // Eklektik charts variable
    window.eklektikCharts = {};

    // THEME_COLORS déjà défini au début du script

    // Fonction utilitaire pour accès sécurisé aux couleurs
    function getThemeColor(colorName) {
      try {
        if (window.THEME_COLORS && window.THEME_COLORS[colorName]) {
          return window.THEME_COLORS[colorName];
        }
        if (typeof THEME_COLORS !== 'undefined' && THEME_COLORS[colorName]) {
          return THEME_COLORS[colorName];
        }
      } catch (e) {
        console.warn('Erreur accès THEME_COLORS:', e);
      }
      
      // Fallback colors
      const fallbackColors = {
        primary: '#6C4BA0',
        primaryRgba: 'rgba(108, 75, 160, 0.1)',
        secondary: '#D4A843',
        accent: '#D4A843',
        success: '#10b981',
        warning: '#f59e0b',
        muted: '#64748b',
        mutedRgba: 'rgba(100, 116, 139, 0.2)'
      };
      
      return fallbackColors[colorName] || '#6C4BA0';
    }

    // Alias sécurisé pour THEME_COLORS
    const safeThemeColors = new Proxy({}, {
      get: function(target, prop) {
        return getThemeColor(prop);
      }
    });

    // Chart.js dark theme defaults
    Chart.defaults.color = '#A1A1AA';
    Chart.defaults.borderColor = 'rgba(255, 255, 255, 0.05)';
    Chart.defaults.plugins.legend.labels.color = '#A1A1AA';
    Chart.defaults.plugins.legend.labels.font = { family: 'Manrope', size: 11 };
    Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(22, 17, 49, 0.95)';
    Chart.defaults.plugins.tooltip.titleColor = '#FFFFFF';
    Chart.defaults.plugins.tooltip.bodyColor = '#A1A1AA';
    Chart.defaults.plugins.tooltip.borderColor = 'rgba(255,255,255,0.1)';
    Chart.defaults.plugins.tooltip.borderWidth = 1;
    Chart.defaults.plugins.tooltip.cornerRadius = 8;
    Chart.defaults.plugins.tooltip.padding = 10;

    // === Eklektik => public/js/dashboard/eklektik.js ===
    // === Utils => public/js/dashboard/utils.js ===
    // Load dashboard data with simple loading
    async function loadDashboardData() {
      try {
        // Show progressive loading
        showLoading();
        updateProgressiveStatus('Initialisation...', 0);
        
        // Get date values for both periods
        const startDate = document.getElementById('start-date').value;
        const endDate = document.getElementById('end-date').value;
        const comparisonStartDate = document.getElementById('comparison-start-date').value;
        const comparisonEndDate = document.getElementById('comparison-end-date').value;
        
        // Get selected operators
        const selectedOperator = selectedOperators.includes('ALL') || selectedOperators.length === 0 
          ? 'ALL' 
          : selectedOperators.length === 1 
            ? selectedOperators[0] 
            : selectedOperators.join(',');
        
        // Build params
        const params = new URLSearchParams();
        if (startDate && endDate) {
          params.append('start_date', startDate);
          params.append('end_date', endDate);
        }
        if (comparisonStartDate && comparisonEndDate) {
          params.append('comparison_start_date', comparisonStartDate);
          params.append('comparison_end_date', comparisonEndDate);
        }
        if (selectedOperator) {
          params.append('operator', selectedOperator);
        }
        const queryString = params.toString();
        
        const startTime = performance.now();
        
        // Essayer d'abord le chargement progressif (split endpoints)
        // Si un endpoint echoue, on fallback sur le monolithique
        const sections = [
          { name: 'kpis', url: `/api/dashboard/split/kpis?${queryString}`, label: 'KPIs', weight: 20 },
          { name: 'merchants', url: `/api/dashboard/split/merchants?${queryString}`, label: 'Marchands', weight: 20 },
          { name: 'transactions', url: `/api/dashboard/split/transactions?${queryString}`, label: 'Transactions', weight: 15 },
          { name: 'subscriptions', url: `/api/dashboard/split/subscriptions?${queryString}`, label: 'Abonnements', weight: 25 },
          { name: 'ooredoo_stats', url: `/api/dashboard/split/ooredoo?${queryString}`, label: 'Ooredoo', weight: 10 },
          { name: 'timwe_stats', url: `/api/dashboard/split/timwe?${queryString}`, label: 'Timwe', weight: 10 },
          { name: 'eklektik_stats', url: `/api/dashboard/split/eklektik?${queryString}`, label: 'Eklektik', weight: 5 }
        ];
        
        let completedWeight = 0;
        let sectionResults = {};
        let hasAnyData = false;
        
        // Lancer TOUTES les requetes en parallele
        const fetchPromises = sections.map(section => {
          const controller = new AbortController();
          const timeoutId = setTimeout(() => controller.abort(), 180000);
          
          return fetch(section.url, {
            signal: controller.signal,
            headers: { 'Accept': 'application/json' }
          })
          .then(async (response) => {
            clearTimeout(timeoutId);
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const json = await response.json();
            
            // Mise a jour progressive
            completedWeight += section.weight;
            updateProgressiveStatus(`${section.label} charge!`, completedWeight);
            
            sectionResults[section.name] = json;
            hasAnyData = true;
            
            // Mettre a jour la section correspondante immediatement
            updateDashboardSection(section.name, json);
            
            return { section: section.name, success: true, data: json };
          })
          .catch(err => {
            clearTimeout(timeoutId);
            console.warn(`Section ${section.name} echec:`, err.message);
            completedWeight += section.weight;
            updateProgressiveStatus(`${section.label} - fallback...`, completedWeight);
            return { section: section.name, success: false, error: err.message };
          });
        });
        
        // Attendre que TOUTES les sections soient chargees
        const results = await Promise.all(fetchPromises);
        
        const loadTime = performance.now() - startTime;
        console.log('Dashboard progressif:', {
          operator: selectedOperator,
          loadTime: `${loadTime.toFixed(0)}ms`,
          sections: results.map(r => `${r.section}: ${r.success ? 'OK' : 'FAIL'}`)
        });
        
        // Si le chargement progressif a echoue pour toutes les sections, fallback monolithique
        if (!hasAnyData) {
          console.warn('Fallback sur endpoint monolithique...');
          updateProgressiveStatus('Chargement complet...', 50);
          const fallbackController = new AbortController();
          const fallbackTimeout = setTimeout(() => fallbackController.abort(), 180000);
          
          const response = await fetch(`/api/dashboard/data?${queryString}`, {
            signal: fallbackController.signal,
            headers: { 'Accept': 'application/json' }
          });
          clearTimeout(fallbackTimeout);
          
          if (response.ok) {
            const data = await response.json();
            updateDashboard(data);
          } else {
            throw new Error(`Fallback echoue: HTTP ${response.status}`);
          }
        }
        
        // Masquer le chargement
        hideOptimizationMessage();
        updatePerformanceIndicator(loadTime);
        hideLoading();
        
        // Charger les ML insights en parallele (non bloquant)
        loadMLInsights();
        
        const operatorLabel = selectedOperator === 'ALL' ? 'globales' : selectedOperator;
        setTimeout(() => {
          showNotification(`Donnees ${operatorLabel} mises a jour! (${(loadTime/1000).toFixed(1)}s)`, 'success');
        }, 100);
        
        try {
          window.dispatchEvent(new CustomEvent('dashboard:refreshed'));
        } catch (e) {}
        
      } catch (error) {
        console.error('Error loading dashboard data:', error);
        hideLoading();
        
        if (error.name === 'AbortError') {
          showNotification('Delai d\'attente depasse - Chargement des donnees de demonstration', 'warning');
        } else {
          showNotification('Erreur de connexion: ' + error.message, 'error');
        }
        loadFallbackData();
        updateDashboard(dashboardData);
      }
    }
    
    // Mise a jour progressive du statut de chargement
    function updateProgressiveStatus(message, percent) {
      const overlay = document.getElementById('loading-overlay');
      if (overlay) {
        const statusEl = overlay.querySelector('.loading-status');
        const progressBar = overlay.querySelector('.progress-fill');
        if (statusEl) statusEl.textContent = message;
        if (progressBar) progressBar.style.width = percent + '%';
      }
    }
    
    // Mettre a jour UNE section du dashboard quand elle arrive
    function updateDashboardSection(sectionName, json) {
      if (!json || !json.success) return;
      try {
        // Initialiser le store global
        if (!window._dashboardData) {
          window._dashboardData = {
            periods: {
              primary: (document.getElementById('start-date')?.value || '') + ' - ' + (document.getElementById('end-date')?.value || ''),
              comparison: ''
            },
            kpis: {},
            merchants: [],
            categoryDistribution: [],
            transactions: {},
            subscriptions: {},
            ooredoo_stats: {},
            insights: []
          };
        }
        
        switch(sectionName) {
          case 'kpis':
            if (json.data) {
              window._dashboardData.kpis = json.data;
              // Mettre a jour les cartes KPI immediatement
              dashboardData = window._dashboardData;
              updateKPIs(json.data);
              // Mettre a jour la table de comparaison
              try { updateComparisonTable(json.data); } catch(e) {}
            }
            break;
          case 'merchants':
            if (json.data) {
              window._dashboardData.merchants = json.data;
              // Les catégories peuvent être dans json.data.categories ou json.categoryDistribution
              window._dashboardData.categoryDistribution = json.data.categories || json.categoryDistribution || [];
              dashboardData = window._dashboardData;
              updateMerchantKPIs(json.data, window._dashboardData.kpis);
              // Mettre a jour le tableau des marchands
              if (typeof updateMerchantsTable === 'function') {
                try { updateMerchantsTable(json.data); } catch(e) { console.warn('updateMerchantsTable error:', e); }
              }
              // Redessiner les graphiques marchands
              if (typeof updateCharts === 'function') {
                try { updateCharts(window._dashboardData); } catch(e) {}
              }
            }
            break;
          case 'transactions':
            if (json.data) {
              window._dashboardData.transactions = json.data;
              dashboardData = window._dashboardData;
              // Redessiner les graphiques avec les donnees de transactions
              if (typeof updateCharts === 'function') {
                try { updateCharts(window._dashboardData); } catch(e) {}
              }
            }
            break;
          case 'subscriptions':
            if (json.data) {
              window._dashboardData.subscriptions = json.data;
              dashboardData = window._dashboardData;
              // Mettre a jour les graphiques d'abonnements
              if (typeof updateCharts === 'function') {
                try { updateCharts(window._dashboardData); } catch(e) {}
              }
              // Mettre a jour les tableaux d'abonnements et statistiques
              if (typeof updateDailyStatisticsTable === 'function') {
                try { updateDailyStatisticsTable(json.data); } catch(e) { console.warn('updateDailyStatisticsTable error:', e); }
              }
              if (typeof updateSubscriptionsTable === 'function') {
                try { updateSubscriptionsTable(json.data); } catch(e) { console.warn('updateSubscriptionsTable error:', e); }
              }
            }
            break;
          case 'ooredoo_stats':
            if (json.data) {
              window._dashboardData.ooredoo_stats = json.data;
              // Injecter dans subscriptions pour compatibilité
              if (!window._dashboardData.subscriptions) window._dashboardData.subscriptions = {};
              window._dashboardData.subscriptions.ooredoo_monthly_stats = json.data.ooredoo_monthly_stats || [];
              window._dashboardData.subscriptions.ooredoo_monthly_stats_comparison = json.data.ooredoo_monthly_stats_comparison || [];
              dashboardData = window._dashboardData;
              // Mettre à jour les KPIs Ooredoo immédiatement
              if (typeof updateOoredooKPIs === 'function') {
                try { updateOoredooKPIs(dashboardData); } catch(e) { console.warn('updateOoredooKPIs error:', e); }
              }
            }
            break;
          case 'timwe_stats':
            if (json.data) {
              window._dashboardData.timwe_stats = json.data;
              // Injecter les monthly stats dans subscriptions pour que updateTimweKPIs fonctionne
              if (!window._dashboardData.subscriptions) window._dashboardData.subscriptions = {};
              window._dashboardData.subscriptions.timwe_monthly_stats = json.data.timwe_monthly_stats || [];
              window._dashboardData.subscriptions.timwe_monthly_stats_comparison = json.data.timwe_monthly_stats_comparison || [];
              window._dashboardData.subscriptions.daily_statistics = json.data.daily_statistics || [];
              window._dashboardData.subscriptions.daily_statistics_comparison = json.data.daily_statistics_comparison || [];
              dashboardData = window._dashboardData;
              // Mettre a jour les KPIs Timwe immediatement
              if (typeof updateTimweKPIs === 'function') {
                try { updateTimweKPIs(dashboardData); } catch(e) { console.warn('updateTimweKPIs error:', e); }
              }
              if (typeof updateCharts === 'function') {
                try { updateCharts(window._dashboardData); } catch(e) {}
              }
            }
            break;
          case 'eklektik_stats':
            if (json.data) {
              window._dashboardData.eklektik_stats = json.data;
              dashboardData = window._dashboardData;
              if (typeof allEklektikMonthlyStats !== 'undefined') {
                allEklektikMonthlyStats = json.data.eklektik_monthly_stats || [];
                if (typeof renderEklektikStatisticsTable === 'function') {
                  try { renderEklektikStatisticsTable(); } catch(e) { console.warn('renderEklektikStatisticsTable error:', e); }
                }
              }
            }
            break;
        }
      } catch(e) {
        console.warn(`Erreur mise a jour section ${sectionName}:`, e);
      }
    }
    
    // Simple loading management
    function showLoading() {
      // Update button state
      const refreshBtn = document.getElementById('refresh-btn');
      const refreshText = document.getElementById('refresh-text');
      const refreshLoading = document.getElementById('refresh-loading');
      
      if (refreshBtn) refreshBtn.disabled = true;
      if (refreshText) refreshText.style.display = 'none';
      if (refreshLoading) refreshLoading.style.display = 'inline';
      
      // Simple overlay
      showSimpleOverlay();
    }

    function showSimpleOverlay() {
      // Remove existing overlay
      const existingOverlay = document.getElementById('loading-overlay');
      if (existingOverlay) {
        existingOverlay.remove();
      }

      const overlay = document.createElement('div');
      overlay.id = 'loading-overlay';
      overlay.className = 'loading-overlay';
      overlay.innerHTML = `
        <div class="loading-spinner">
          <div class="spinner"></div>
          <div class="loading-status" style="margin-top: 15px; font-weight: 500;">Chargement des donnees...</div>
          <div style="margin-top: 10px; width: 200px; height: 4px; background: rgba(255,255,255,0.2); border-radius: 2px; overflow: hidden;">
            <div class="progress-fill" style="height: 100%; width: 0%; background: linear-gradient(90deg, #3b82f6, #10b981); border-radius: 2px; transition: width 0.5s ease;"></div>
          </div>
        </div>
      `;

      document.body.appendChild(overlay);
    }
    
    function hideLoading() {
      // Reset button state
      const refreshBtn = document.getElementById('refresh-btn');
      const refreshText = document.getElementById('refresh-text');
      const refreshLoading = document.getElementById('refresh-loading');
      
      if (refreshBtn) refreshBtn.disabled = false;
      if (refreshText) refreshText.style.display = 'inline';
      if (refreshLoading) refreshLoading.style.display = 'none';
      
      // Remove simple overlay
      const overlay = document.getElementById('loading-overlay');
      if (overlay) {
        overlay.remove();
      }
    }
    
    // Enhanced notification system with better UX
    function showNotification(message, type = 'info', duration = 4000) {
      // Remove existing notifications of same type
      const existing = document.querySelectorAll(`.notification.${type}`);
      existing.forEach(n => n.remove());
      
      // Create new notification with enhanced features
      const notification = document.createElement('div');
      notification.className = `notification ${type}`;
      notification.innerHTML = `
        <div style="display: flex; align-items: center; gap: 10px; position: relative;">
          <span style="font-size: 16px;">${type === 'success' ? '✅' : type === 'error' ? '❌' : type === 'warning' ? '⚠️' : 'ℹ️'}</span>
          <span style="flex: 1; font-weight: 500;">${message}</span>
          <button onclick="closeNotification(this)" style="background: none; border: none; font-size: 18px; cursor: pointer; color: inherit; opacity: 0.7;">×</button>
        </div>
        <div class="notification-progress" style="position: absolute; bottom: 0; left: 0; height: 3px; background: rgba(255,255,255,0.3); width: 100%; overflow: hidden;">
          <div class="notification-progress-bar" style="height: 100%; background: rgba(255,255,255,0.8); width: 100%; animation: progressShrink ${duration}ms linear;"></div>
        </div>
      `;
      
      // Improve positioning and stacking
      notification.style.position = 'fixed';
      notification.style.zIndex = '10000';
      notification.style.marginBottom = '10px';
      
      // Stack notifications
      const existingNotifications = document.querySelectorAll('.notification');
      const offset = existingNotifications.length * 80; // 80px per notification
      notification.style.top = (20 + offset) + 'px';
      
      document.body.appendChild(notification);
      
      // Add progress animation style if not exists
      if (!document.getElementById('progress-animation-style')) {
        const style = document.createElement('style');
        style.id = 'progress-animation-style';
        style.textContent = `
          @keyframes progressShrink {
            from { width: 100%; }
            to { width: 0%; }
          }
          
          .notification {
            position: relative;
            min-height: 60px;
            display: flex;
            align-items: center;
            transition: all 0.3s ease;
          }
          
          .notification:hover .notification-progress-bar {
            animation-play-state: paused;
          }
        `;
        document.head.appendChild(style);
      }
      
      // Auto-remove with smooth animation
      setTimeout(() => {
        if (document.body.contains(notification)) {
          notification.style.animation = 'slideIn 0.3s ease reverse';
          notification.style.transform = 'translateX(100%)';
          setTimeout(() => {
            if (document.body.contains(notification)) {
              document.body.removeChild(notification);
              // Reposition remaining notifications
              repositionNotifications();
            }
          }, 300);
        }
      }, duration);
    }
    
    function closeNotification(button) {
      const notification = button.closest('.notification');
      if (notification) {
        notification.style.animation = 'slideIn 0.3s ease reverse';
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => {
          if (document.body.contains(notification)) {
            document.body.removeChild(notification);
            repositionNotifications();
          }
        }, 300);
      }
    }
    
    function repositionNotifications() {
      const notifications = document.querySelectorAll('.notification');
      notifications.forEach((notification, index) => {
        notification.style.top = (20 + index * 80) + 'px';
      });
    }

    function updatePerformanceIndicator(loadTime) {
      const indicator = document.getElementById('performance-indicator');
      if (!indicator) return;
      
      if (loadTime < 3000) {
        // Fast load - likely from cache
        indicator.style.display = 'flex';
        indicator.querySelector('.performance-text').textContent = 'Rapide ⚡';
        indicator.style.background = 'rgba(16, 185, 129, 0.1)';
        indicator.style.borderColor = 'rgba(16, 185, 129, 0.3)';
        indicator.style.color = '#059669';
        
        // Hide after 3 seconds
        setTimeout(() => {
          indicator.style.display = 'none';
        }, 3000);
      } else if (loadTime < 8000) {
        // Medium load
        indicator.style.display = 'flex';
        indicator.querySelector('.performance-text').textContent = `${Math.round(loadTime)}ms`;
        indicator.style.background = 'rgba(245, 158, 11, 0.1)';
        indicator.style.borderColor = 'rgba(245, 158, 11, 0.3)';
        indicator.style.color = '#d97706';
        
        setTimeout(() => {
          indicator.style.display = 'none';
        }, 2000);
      } else {
        // Slow load
        indicator.style.display = 'flex';
        indicator.querySelector('.performance-text').textContent = 'Lent';
        indicator.style.background = 'rgba(239, 68, 68, 0.1)';
        indicator.style.borderColor = 'rgba(239, 68, 68, 0.3)';
        indicator.style.color = '#dc2626';
        
        setTimeout(() => {
          indicator.style.display = 'none';
        }, 4000);
      }
    }
    
    // Load available operators with improved error handling
    async function loadOperators() {
      let timeoutId = null;
      
      const controller = new AbortController();
      // Timeout augmenté à 60s pour SuperAdmin (beaucoup d'opérateurs)
      // Le timeout est silencieux si les opérateurs sont déjà chargés
      timeoutId = setTimeout(() => controller.abort(), 60000); // 60s timeout
      
      try {
        const response = await fetch('/api/operators', {
          signal: controller.signal,
          headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          },
          credentials: 'same-origin'
        });
        
        clearTimeout(timeoutId);
        
        if (!response.ok) {
          const errorData = await response.json().catch(() => ({}));
          throw new Error(`HTTP ${response.status}: ${errorData.error || response.statusText}`);
        }
        
        const data = await response.json();
        
        console.log('🔍 DEBUG API Response:', {
          operators: data.operators,
          default_operator: data.default_operator,
          user_role: data.user_role
        });
        
        if (data.operators && Array.isArray(data.operators) && data.operators.length > 0) {
          const operatorsList = document.getElementById('operators-list');
          const operatorInfo = document.getElementById('operator-info');
          
          // Store available operators
          availableOperators = data.operators;
          
          // Clear existing operators
          operatorsList.innerHTML = '';
          
          // Vérifier si "ALL" est disponible (seulement pour SuperAdmin et Admin)
          hasAllOption = data.operators.some(op => op.value === 'ALL');
          const selectAllCheckbox = document.getElementById('select-all-operators');
          const selectAllOption = selectAllCheckbox ? selectAllCheckbox.closest('.select-all-option') : null;
          
          // Masquer "Tous les opérateurs" pour les collaborateurs
          if (!hasAllOption) {
            if (selectAllOption) {
              selectAllOption.style.display = 'none';
            }
            if (selectAllCheckbox) {
              selectAllCheckbox.checked = false;
            }
          } else {
            if (selectAllOption) {
              selectAllOption.style.display = 'block';
            }
          }
          
          // Stocker les opérateurs disponibles globalement
          availableOperators = data.operators;
          
          // Add operators to multi-select
          data.operators.forEach(operator => {
            const operatorDiv = document.createElement('div');
            operatorDiv.className = 'operator-option';
            
            const label = document.createElement('label');
            label.className = 'checkbox-label';
            
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.value = operator.value;
            checkbox.onchange = function() {
              handleOperatorChange(operator.value, this);
            };
            
            const checkmark = document.createElement('span');
            checkmark.className = 'checkmark';
            
            const text = document.createElement('span');
            text.textContent = `📱 ${operator.label}`;
            
            label.appendChild(checkbox);
            label.appendChild(checkmark);
            label.appendChild(text);
            operatorDiv.appendChild(label);
            operatorsList.appendChild(operatorDiv);
            
            console.log(`🔍 Opérateur ajouté: ${operator.label} (${operator.value})`);
          });
          
          // Set default selection - s'assurer qu'un opérateur est toujours sélectionné
          let defaultOperatorSelected = false;
          
          if (data.default_operator && data.default_operator !== 'ALL') {
            // Vérifier que l'opérateur par défaut existe dans la liste
            const defaultOpExists = data.operators.some(op => op.value === data.default_operator);
            if (defaultOpExists) {
              selectedOperators = [data.default_operator];
              selectAllCheckbox.checked = false;
              
              // Check the default operator
              const defaultCheckbox = operatorsList.querySelector(`input[value="${data.default_operator}"]`);
              if (defaultCheckbox) {
                defaultCheckbox.checked = true;
                defaultOperatorSelected = true;
              }
            }
          } else if (data.default_operator === 'ALL' && hasAllOption) {
            // Si "ALL" est le défaut et disponible, le sélectionner
            selectedOperators = ['ALL'];
            selectAllCheckbox.checked = true;
            defaultOperatorSelected = true;
          }
          
          // Si aucun opérateur par défaut n'a été sélectionné, sélectionner le premier disponible
          if (!defaultOperatorSelected && data.operators.length > 0) {
            const firstOperator = hasAllOption && data.operators.some(op => op.value === 'ALL') 
              ? 'ALL' 
              : data.operators[0].value;
            
            selectedOperators = [firstOperator];
            
            if (firstOperator === 'ALL' && selectAllCheckbox) {
              selectAllCheckbox.checked = true;
            } else {
              const firstCheckbox = operatorsList.querySelector(`input[value="${firstOperator}"]`);
              if (firstCheckbox) {
                firstCheckbox.checked = true;
              }
            }
          }
          
          updateSelectedOperatorsDisplay();
          updateOperatorInfo();
          
          // Déclencher le chargement des données avec l'opérateur sélectionné
          if (selectedOperators.length > 0) {
            loadDashboardData();
          }
          
          // Update info text based on user role
          if (data.user_role === 'super_admin') {
            operatorInfo.textContent = `Vue globale disponible (${data.operators.length} opérateurs)`;
          } else if (data.user_role === 'collaborator') {
            operatorInfo.textContent = `${data.operators.length} opérateur(s) assigné(s)`;
          } else {
            operatorInfo.textContent = `${data.operators.length} opérateur(s) assigné(s)`;
          }
          
          console.log('✅ Opérateurs chargés:', data.operators.length);
          
        } else {
          throw new Error('No operators data');
        }
        
      } catch (error) {
        clearTimeout(timeoutId);
        
        // Ne pas afficher d'erreur si c'est juste une annulation (timeout)
        // Vérifier si les opérateurs ont déjà été chargés (cas où le timeout arrive après chargement)
        if (error.name === 'AbortError') {
          // Vérifier de manière robuste si les opérateurs sont déjà chargés
          const operatorsList = document.getElementById('operators-list');
          const hasOperatorsInList = operatorsList && operatorsList.children.length > 0;
          const operatorInfo = document.getElementById('operator-info');
          const hasOperatorInfo = operatorInfo && operatorInfo.textContent && (
            operatorInfo.textContent.includes('opérateur') || 
            operatorInfo.textContent.includes('Vue globale') ||
            operatorInfo.textContent.includes('assigné')
          );
          
          // Vérifier aussi si availableOperators est défini et non vide
          const hasAvailableOperators = availableOperators && Array.isArray(availableOperators) && availableOperators.length > 0;
          
          // Vérifier si les opérateurs sont réellement chargés
          // Si au moins un indicateur montre que les opérateurs sont chargés, ignorer le timeout complètement
          if (hasOperatorsInList || hasOperatorInfo || hasAvailableOperators) {
            // Les opérateurs sont déjà chargés - ignorer silencieusement le timeout
            // Ne rien afficher, ne rien logger
            return;
          }
          
          // Seulement afficher le warning si les opérateurs ne sont vraiment pas chargés
          console.warn('⚠️ Chargement des opérateurs annulé (timeout) - réessayez si les opérateurs ne sont pas visibles');
          if (operatorInfo) {
            operatorInfo.textContent = 'Erreur: Impossible de charger les opérateurs. Veuillez rafraîchir la page.';
            operatorInfo.style.color = '#ef4444';
          }
        } else {
          console.error('❌ Erreur lors du chargement des opérateurs:', error.message);
          const operatorInfo = document.getElementById('operator-info');
          if (operatorInfo) {
            operatorInfo.textContent = 'Erreur: Impossible de charger les opérateurs. Veuillez rafraîchir la page.';
            operatorInfo.style.color = '#ef4444';
          }
        }
      }
    }
    
    // Toggle operator dropdown
    function toggleOperatorDropdown() {
      const dropdown = document.getElementById('operators-dropdown');
      const header = document.querySelector('.multi-select-header');
      
      if (dropdown.style.display === 'none') {
        dropdown.style.display = 'block';
        header.classList.add('open');
      } else {
        dropdown.style.display = 'none';
        header.classList.remove('open');
      }
    }
    
    // Handle select all operators
    function handleSelectAllOperators() {
      const selectAllCheckbox = document.getElementById('select-all-operators');
      const operatorCheckboxes = document.querySelectorAll('.operators-list input[type="checkbox"]');
      
      if (selectAllCheckbox.checked) {
        selectedOperators = ['ALL'];
        operatorCheckboxes.forEach(checkbox => {
          checkbox.checked = false;
        });
      } else {
        selectedOperators = [];
        operatorCheckboxes.forEach(checkbox => {
          checkbox.checked = true;
          if (!selectedOperators.includes(checkbox.value)) {
            selectedOperators.push(checkbox.value);
          }
        });
      }
      
      updateSelectedOperatorsDisplay();
      updateOperatorInfo();
      loadDashboardData();
    }
    
    // Handle individual operator selection
    function handleOperatorChange(operatorValue, checkbox) {
      const selectAllCheckbox = document.getElementById('select-all-operators');
      
      if (checkbox.checked) {
        // Add operator
        if (selectedOperators.includes('ALL')) {
          selectedOperators = [operatorValue];
          selectAllCheckbox.checked = false;
        } else if (!selectedOperators.includes(operatorValue)) {
          selectedOperators.push(operatorValue);
        }
      } else {
        // Remove operator
        selectedOperators = selectedOperators.filter(op => op !== operatorValue);
        selectAllCheckbox.checked = false;
        
        // Si aucun opérateur sélectionné, revenir à "Tous" seulement si disponible
        if (selectedOperators.length === 0 && hasAllOption) {
          selectedOperators = ['ALL'];
          selectAllCheckbox.checked = true;
        } else if (selectedOperators.length === 0 && !hasAllOption && availableOperators.length > 0) {
          // Pour les collaborateurs, sélectionner le premier opérateur disponible
          selectedOperators = [availableOperators[0].value];
          const firstCheckbox = document.querySelector(`input[value="${availableOperators[0].value}"]`);
          if (firstCheckbox) {
            firstCheckbox.checked = true;
          }
        }
      }
      
      updateSelectedOperatorsDisplay();
      updateOperatorInfo();
      loadDashboardData();
    }
    
    // Update selected operators display
    function updateSelectedOperatorsDisplay() {
      const displayElement = document.getElementById('selected-operators-text');
      
      if (selectedOperators.includes('ALL') || selectedOperators.length === 0) {
        displayElement.textContent = '📱 Tous les opérateurs';
      } else if (selectedOperators.length === 1) {
        displayElement.textContent = `📱 ${selectedOperators[0]}`;
      } else {
        displayElement.textContent = `📱 ${selectedOperators.length} opérateurs sélectionnés`;
      }
    }
    
    // Update operator info
    function updateOperatorInfo() {
      const operatorInfo = document.getElementById('operator-info');
      
      if (selectedOperators.includes('ALL') || selectedOperators.length === 0) {
        operatorInfo.textContent = 'Vue globale - Tous les opérateurs';
      } else if (selectedOperators.length === 1) {
        operatorInfo.textContent = `Données limitées à l'opérateur ${selectedOperators[0]}`;
      } else {
        operatorInfo.textContent = `Données limitées à ${selectedOperators.length} opérateurs sélectionnés`;
      }
    }
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function(event) {
      const container = document.querySelector('.multi-select-container');
      const dropdown = document.getElementById('operators-dropdown');
      
      if (container && !container.contains(event.target)) {
        dropdown.style.display = 'none';
        document.querySelector('.multi-select-header').classList.remove('open');
      }
    });

    // Set default dates (last 14 days for primary, previous 14 for comparison)
    function setDefaultDates() {
      const endDate = new Date();
      const startDate = new Date();
      startDate.setDate(endDate.getDate() - 13);
      
      // Comparison period (14 days before the primary period)
      const comparisonEndDate = new Date(startDate);
      comparisonEndDate.setDate(comparisonEndDate.getDate() - 1);
      const comparisonStartDate = new Date(comparisonEndDate);
      comparisonStartDate.setDate(comparisonStartDate.getDate() - 13);

      document.getElementById('start-date').value = startDate.toISOString().split('T')[0];
      document.getElementById('end-date').value = endDate.toISOString().split('T')[0];
      document.getElementById('comparison-start-date').value = comparisonStartDate.toISOString().split('T')[0];
      document.getElementById('comparison-end-date').value = comparisonEndDate.toISOString().split('T')[0];
    }
    
    // Set smart comparison period (adapté selon la durée de la période)
    function setSmartComparison() {
      const startDate = new Date(document.getElementById('start-date').value);
      const endDate = new Date(document.getElementById('end-date').value);
      
      if (startDate && endDate) {
        const duration = Math.ceil((endDate - startDate) / (1000 * 60 * 60 * 24));
        
        let comparisonStartDate, comparisonEndDate;
        
        if (duration > 365) {
          // Pour les longues périodes: comparer l'année précédente (end-2ans à end-1an)
          comparisonEndDate = new Date(endDate);
          comparisonEndDate.setFullYear(comparisonEndDate.getFullYear() - 1);
          comparisonStartDate = new Date(endDate);
          comparisonStartDate.setFullYear(comparisonStartDate.getFullYear() - 2);
          const dataStart = new Date('2021-01-01');
          if (comparisonStartDate < dataStart) comparisonStartDate = dataStart;
        } else {
          // Pour les courtes/moyennes périodes: même durée juste avant
          comparisonEndDate = new Date(startDate);
          comparisonEndDate.setDate(comparisonEndDate.getDate() - 1);
          comparisonStartDate = new Date(comparisonEndDate);
          comparisonStartDate.setDate(comparisonStartDate.getDate() - duration);
        }
        
        document.getElementById('comparison-start-date').value = comparisonStartDate.toISOString().split('T')[0];
        document.getElementById('comparison-end-date').value = comparisonEndDate.toISOString().split('T')[0];
        
        updateDateRange();
        loadDashboardData();
      }
    }

    // Auto-calculer les dates de comparaison et charger les données
    function autoCompareAndLoad() {
      const startDate = new Date(document.getElementById('start-date').value);
      const endDate = new Date(document.getElementById('end-date').value);
      
      if (startDate && endDate) {
        const duration = Math.ceil((endDate - startDate) / (1000 * 60 * 60 * 24));
        
        let comparisonStartDate, comparisonEndDate;
        
        if (duration > 365) {
          // Longues périodes: comparer l'année précédente (end-2ans à end-1an)
          comparisonEndDate = new Date(endDate);
          comparisonEndDate.setFullYear(comparisonEndDate.getFullYear() - 1);
          comparisonStartDate = new Date(endDate);
          comparisonStartDate.setFullYear(comparisonStartDate.getFullYear() - 2);
          const dataStart = new Date('2021-01-01');
          if (comparisonStartDate < dataStart) comparisonStartDate = dataStart;
        } else {
          // Courtes/moyennes périodes: même durée juste avant
          comparisonEndDate = new Date(startDate);
          comparisonEndDate.setDate(comparisonEndDate.getDate() - 1);
          comparisonStartDate = new Date(comparisonEndDate);
          comparisonStartDate.setDate(comparisonStartDate.getDate() - duration);
        }
        
        document.getElementById('comparison-start-date').value = comparisonStartDate.toISOString().split('T')[0];
        document.getElementById('comparison-end-date').value = comparisonEndDate.toISOString().split('T')[0];
      }
      
      updateDateRange();
      loadDashboardData();
    }

    // Update date range display
    function updateDateRange() {
      const startDate = document.getElementById('start-date').value;
      const endDate = document.getElementById('end-date').value;
      
      if (startDate && endDate) {
        const start = new Date(startDate);
        const end = new Date(endDate);
        const primaryPeriod = `${start.toLocaleDateString('fr-FR')} - ${end.toLocaleDateString('fr-FR')}`;
        document.getElementById('primaryPeriod').textContent = primaryPeriod;
      }
    }

    // Show loading state
    function showLoading() {
      // Add loading indicators to KPI cards
      const kpiValues = document.querySelectorAll('.kpi-value');
      
      // Détecter les longues périodes
      const startDate = document.getElementById('start-date').value;
      const endDate = document.getElementById('end-date').value;
      let isLongPeriod = false;
      let diffDays = 0;
      
      if (startDate && endDate) {
        const start = new Date(startDate);
        const end = new Date(endDate);
        diffDays = Math.ceil((end - start) / (1000 * 60 * 60 * 24));
        isLongPeriod = diffDays > 90;
      }
      
      kpiValues.forEach(el => {
        if (isLongPeriod) {
          el.innerHTML = `<div class="spinner"></div> <small>Optimisation ${diffDays}j...</small>`;
        } else {
        el.innerHTML = '<div class="spinner"></div>';
        }
      });
      
      if (isLongPeriod) {
        showOptimizationMessage(diffDays);
      }
    }

    function showOptimizationMessage(days) {
      // Créer le message d'optimisation
      let optimMsg = document.getElementById('optimization-message');
      if (!optimMsg) {
        optimMsg = document.createElement('div');
        optimMsg.id = 'optimization-message';
        optimMsg.style.cssText = `
          position: fixed;
          top: 80px;
          right: 20px;
          background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
          color: white;
          padding: 12px 16px;
          border-radius: 8px;
          box-shadow: 0 4px 12px rgba(0,0,0,0.15);
          z-index: 1000;
          font-size: 14px;
          max-width: 300px;
          animation: slideIn 0.3s ease-out;
        `;
        document.body.appendChild(optimMsg);
      }
      
      optimMsg.innerHTML = `
        <div style="display: flex; align-items: center; gap: 8px;">
          <div class="spinner" style="width: 16px; height: 16px; border-width: 2px;"></div>
          <div>
            <strong>🚀 Mode optimisé</strong><br>
            <small>Période étendue: ${days} jours</small>
          </div>
        </div>
      `;
    }

    function hideOptimizationMessage() {
      const optimMsg = document.getElementById('optimization-message');
      if (optimMsg) {
        optimMsg.style.animation = 'slideOut 0.3s ease-in';
        setTimeout(() => optimMsg.remove(), 300);
      }
    }

    // Hide loading state
    function hideLoading() {
      // Loading will be hidden when data is updated
    }

    // Show error message
    function showError(message) {
      const kpiValues = document.querySelectorAll('.kpi-value');
      kpiValues.forEach(el => {
        el.textContent = 'Erreur';
      });
      
      // You could also show a toast notification here
      alert(message);
    }

    // Duplicate DOMContentLoaded removed - initialization handled by main DOMContentLoaded above

    // Load fallback data (static data for demo)
    function loadFallbackData() {
      dashboardData = {
        periods: {
          primary: "August 1-14, 2025",
          comparison: "July 18-31, 2025"
        },
        kpis: {
          activatedSubscriptions: { current: 12321, previous: 2129, change: 478.8 },
          activeSubscriptions: { current: 11586, previous: 1800, change: 543.7 },
          deactivatedSubscriptions: { current: 735, previous: 329, change: 123.4 },
          totalTransactions: { current: 32, previous: 33, change: -3.0 },
          transactingUsers: { current: 28, previous: 27, change: 3.7 },
          transactionsPerUser: { current: 1.1, previous: 1.2, change: -8.3 },
          activeMerchants: { current: 16, previous: 12, change: 33.3 },
          transactionsPerMerchant: { current: 2.0, previous: 3.0, change: -33.3 },
          conversionRate: { current: 0.24, previous: 0.18, change: 33.3 }
        },
        merchants: [
          { name: "MABROUK", current: 12, previous: 4, share: 37.5 },
          { name: "DR PARA", current: 3, previous: 4, share: 9.4 },
          { name: "PURE JUICE", current: 2, previous: 1, share: 6.3 },
          { name: "Others", current: 15, previous: 24, share: 46.8 }
        ],
        insights: {
          positive: [
            "Exceptional subscription growth of +478.8% demonstrates strong market demand",
            "High retention rate of 94.0% indicates customer satisfaction with the service",
            "Merchant network expansion with 33.3% more active partners",
            "Improved conversion rate compared to previous period (+33.3%)"
          ],
          challenges: [
            "Transaction conversion rate (0.24%) significantly below Club Privilèges benchmark (30%)",
            "Decline in transactions per user (-8.3%) suggests engagement challenges",
            "Lower transactions per merchant (-33.3%) indicates distribution inefficiency"
          ],
          recommendations: [
            "Implement targeted customer education campaigns about service benefits",
            "Develop merchant training programs to improve transaction facilitation",
            "Create incentive programs to encourage first-time transactions",
            "Analyze user journey to identify conversion barriers"
          ],
          nextSteps: [
            "Launch comprehensive user onboarding program within 2 weeks",
            "Establish merchant support team for transaction optimization",
            "Implement A/B testing for different engagement strategies",
            "Set up weekly monitoring of conversion metrics"
          ]
        }
      };
      
      updateDashboard(dashboardData);
    }

    // Update dashboard with data - optimized for performance
    function updateDashboard(data) {
      // Store globally FIRST so dependent functions can safely read it
      dashboardData = data;

      // Update periods immediately with safety check
      const primaryPeriodEl = document.getElementById('primaryPeriod');
      if (primaryPeriodEl && data.periods && data.periods.primary) {
        primaryPeriodEl.textContent = data.periods.primary;
      }
      
      const comparisonPeriodEl = document.getElementById('comparisonPeriod');
      if (comparisonPeriodEl && data.periods && data.periods.comparison) {
        comparisonPeriodEl.textContent = data.periods.comparison;
      }
      
      // Update KPIs first (most important)
      updateKPIs(data.kpis);
      
      // Update other components with small delays to avoid blocking
      requestAnimationFrame(() => {
        updateCharts(data);
      
        requestAnimationFrame(() => {
          updateTables(data);
          updateMerchantKPIs(data.merchants, data.kpis);
        });
      });
    }

    // Fonction orchestratrice pour mettre a jour TOUS les tableaux
    function updateTables(data) {
      // Tableau statistiques quotidiennes (Subscriptions tab)
      if (typeof updateDailyStatisticsTable === 'function' && data.subscriptions) {
        try { updateDailyStatisticsTable(data.subscriptions); } catch(e) { console.warn('updateDailyStatisticsTable error:', e); }
      }
      // Tableau marchands (Merchants tab)
      if (typeof updateMerchantsTable === 'function' && data.merchants) {
        try { updateMerchantsTable(data.merchants); } catch(e) { console.warn('updateMerchantsTable error:', e); }
      }
      // Tableau abonnements (Subscriptions tab)
      if (typeof updateSubscriptionsTable === 'function' && data.subscriptions) {
        try { updateSubscriptionsTable(data.subscriptions); } catch(e) { console.warn('updateSubscriptionsTable error:', e); }
      }
      // Tableau comparaison (Comparison tab)
      if (data.kpis) {
        try { updateComparisonTable(data.kpis); } catch(e) { console.warn('updateComparisonTable error:', e); }
      }
    }

    // Fonction dediee pour mettre a jour les KPIs de l'onglet Timwe
    function updateTimweKPIs(dashData) {
      if (!dashData || !dashData.subscriptions || !dashData.subscriptions.timwe_monthly_stats) return;
      
      updateTimweStatisticsTable(dashData.subscriptions.timwe_monthly_stats);
      
      const monthlyStats = dashData.subscriptions.timwe_monthly_stats || [];
      const monthlyStatsComparison = dashData.subscriptions.timwe_monthly_stats_comparison || [];
      const totals = calculateTimweTotals(monthlyStats);
      const comparisonTotals = monthlyStatsComparison.length > 0 ? calculateTimweComparisonTotals(monthlyStatsComparison) : null;
      
      const makeKPI = (current, previous) => {
        if (previous === null || previous === undefined || !comparisonTotals) return { current, previous: 0, change: 0 };
        return { current, previous, change: calculateChange(current, previous) };
      };
      
      // Logique originale : calcul depuis timwe_daily_stats (monthly aggregation)
      updateKPI('timwe-active-subs', makeKPI(totals.activeSubsEndOfPeriod, comparisonTotals?.activeSubsEndOfPeriod));
      updateKPI('timwe-new-subscriptions', makeKPI(totals.newSubs, comparisonTotals?.newSubs));
      updateKPI('timwe-unsubscriptions', makeKPI(totals.unsubs, comparisonTotals?.unsubs));
      updateKPI('timwe-simchurn', makeKPI(totals.simchurn, comparisonTotals?.simchurn));
      
      updateKPI('timwe-simchurn-revenue', {
        current: formatNumber(totals.simchurnRevenue, 3),
        previous: comparisonTotals ? formatNumber(comparisonTotals.simchurnRevenue, 3) : 0,
        change: comparisonTotals ? calculateChange(totals.simchurnRevenue, comparisonTotals.simchurnRevenue) : 0
      }, ' TND');
      
      updateKPI('timwe-revenue-tnd', {
        current: formatNumber(totals.revenueTnd, 3),
        previous: comparisonTotals ? formatNumber(comparisonTotals.revenueTnd, 3) : 0,
        change: comparisonTotals ? calculateChange(totals.revenueTnd, comparisonTotals.revenueTnd) : 0
      }, ' TND');
      
      updateKPI('timwe-revenue-usd', {
        current: formatNumber(totals.caBigdealHt, 3),
        previous: comparisonTotals ? formatNumber(comparisonTotals.caBigdealHt, 3) : 0,
        change: comparisonTotals ? calculateChange(totals.caBigdealHt, comparisonTotals.caBigdealHt) : 0
      }, ' TND');
      
      // Nombre de jours de la periode
      const startDate = document.getElementById('start-date')?.value;
      const endDate = document.getElementById('end-date')?.value;
      let periodDays = 30;
      if (startDate && endDate) {
        const s = new Date(startDate), e = new Date(endDate);
        periodDays = Math.ceil((e - s) / (1000 * 60 * 60 * 24)) || 30;
      }
      
      // Base d'actifs pour les ratios
      const activeBase = totals.activeSubsEndOfPeriod;
      const activeBaseComp = comparisonTotals?.activeSubsEndOfPeriod || 0;
      
      // Taux de Croissance Nette
      const netGrowth = totals.newSubs - totals.unsubs - totals.simchurn;
      const netGrowthRate = activeBase > 0 ? (netGrowth / activeBase) * 100 : 0;
      const netGrowthRateComp = comparisonTotals && activeBaseComp > 0
        ? ((comparisonTotals.newSubs - comparisonTotals.unsubs - comparisonTotals.simchurn) / activeBaseComp) * 100 : null;
      
      updateKPI('timwe-net-growth-rate', {
        current: formatNumber(netGrowthRate, 2),
        previous: netGrowthRateComp !== null ? formatNumber(netGrowthRateComp, 2) : 0,
        change: netGrowthRateComp !== null ? calculateChange(netGrowthRate, netGrowthRateComp) : 0
      }, '%');
      
      // ARPU mensuel normalise
      const arpuValue = activeBase > 0 ? (totals.revenueTnd / activeBase) * (30 / periodDays) : 0;
      updateKPI('timwe-arpu', { current: formatNumber(arpuValue, 3), previous: 0, change: 0 }, ' TND');
      
      // Facturation Timwe depuis timwe_daily_stats
      const dailyStats = dashData.subscriptions.daily_statistics || [];
      let totalBillings = 0;
      let totalBillingRate = 0;
      let daysWithRate = 0;
      dailyStats.forEach(d => {
        totalBillings += (parseInt(d.nb_facturation) || 0);
        const taux = parseFloat(d.taux_facturation) || 0;
        if (taux > 0) { totalBillingRate += taux; daysWithRate++; }
      });
      const avgBillingRate = daysWithRate > 0 ? totalBillingRate / daysWithRate : 0;
      
      updateKPI('timwe-total-billings', { current: totalBillings, previous: 0, change: 0 });
      updateKPI('timwe-billing-rate', { current: formatNumber(avgBillingRate, 2), previous: 0, change: 0 }, '%');
      
      // Revenu moyen par facturation
      const avgBillingValue = totalBillings > 0 ? totals.revenueTnd / totalBillings : 0;
      updateKPI('timwe-avg-billing-revenue', { current: formatNumber(avgBillingValue, 3), previous: 0, change: 0 }, ' TND');
    }

    // Update KPI values
    function updateKPIs(kpis) {
      const normalizeKPI = (obj) => (obj && typeof obj.current !== 'undefined') ? obj : { current: 0, previous: 0, change: 0 };
      
      // Overview KPIs
      updateKPI('activatedSubscriptions', normalizeKPI(kpis?.activatedSubscriptions));
      updateKPI('activeSubscriptions', normalizeKPI(kpis?.activeSubscriptions));
      updateKPI('totalTransactions', normalizeKPI(kpis?.totalTransactions));
      // Cohorte: toujours mettre à jour (0 si absent)
      updateKPI('cohortTransactions', normalizeKPI(kpis?.cohortTransactions));
      updateKPI('cohortTransactingUsers', normalizeKPI(kpis?.cohortTransactingUsers));
      // Total Transacting Users (période)
      updateKPI('totalTransactingUsers', normalizeKPI(kpis?.transactingUsers));
      updateKPI('conversionRate', normalizeKPI(kpis?.conversionRate), '%');
      // Overview retention rate
      updateKPI('overview-retentionRate', normalizeKPI(kpis?.retentionRate), '%');
      
      // Update Overview conversion progress bar
      updateOverviewConversionProgressBar(normalizeKPI(kpis?.conversionRate));
      
      // Subscription KPIs
      updateKPI('sub-activatedSubscriptions', normalizeKPI(kpis?.activatedSubscriptions));
      updateKPI('sub-activeSubscriptions', normalizeKPI(kpis?.activeSubscriptions));
      updateKPI('sub-deactivatedSubscriptions', normalizeKPI(kpis?.periodDeactivated));
      updateKPI('sub-retentionRate', normalizeKPI(kpis?.retentionRateTrue), '%');
      // Deactivated (Cohorte) doit utiliser la cohorte réelle
      updateKPI('sub-lostSubscriptions', normalizeKPI(kpis?.cohortDeactivated));
      // Taux de churn doit utiliser la valeur churnRate
      updateKPI('sub-retentionRateTrue', normalizeKPI(kpis?.churnRate), '%');
      
      // Timwe Tab KPIs - gérés par le endpoint split/timwe via updateTimweKPIs()
      // Les KPIs billingRateTimwe et totalTimweBillings sont calculés dans updateTimweKPIs
      if (dashboardData && dashboardData.subscriptions && dashboardData.subscriptions.timwe_monthly_stats) {
        updateTimweKPIs(dashboardData);
      }

      // Ooredoo/DGV KPIs
      if (dashboardData && dashboardData.ooredoo_stats) {
        updateOoredooKPIs(dashboardData);
      }
      
      // Nouveaux KPIs Avancés - Activations par Canal (avec comparaison)
      if (dashboardData && dashboardData.subscriptions && dashboardData.subscriptions.activations_by_channel) {
        const activations = dashboardData.subscriptions.activations_by_channel;
        updateKPI('sub-activationsCB', normalizeKPI(activations.cb));
        updateKPI('sub-activationsRecharge', normalizeKPI(activations.recharge));
        updateKPI('sub-activationsPhone', normalizeKPI(activations.phone_balance));
      }
      
      // Nouveaux KPIs Avancés - Plans (avec comparaison)
      if (dashboardData && dashboardData.subscriptions && dashboardData.subscriptions.plan_distribution) {
        const plans = dashboardData.subscriptions.plan_distribution;
        updateKPI('sub-plansDaily', normalizeKPI(plans.daily));
        updateKPI('sub-plansMonthly', normalizeKPI(plans.monthly));
        updateKPI('sub-plansAnnual', normalizeKPI(plans.annual));
      }
      
      // Nouveaux KPIs Avancés - Métriques (avec comparaison)
      if (dashboardData && dashboardData.subscriptions) {
        updateKPI('sub-renewalRate', normalizeKPI(dashboardData.subscriptions.renewal_rate), '%');
        updateKPI('sub-averageLifespan', normalizeKPI(dashboardData.subscriptions.average_lifespan), ' jours');
      }

      // Valeurs transactions & conversion affichées désormais en haut
      updateKPI('sub-totalTransactions', normalizeKPI(kpis?.totalTransactions));
      updateKPI('sub-conversionRate', normalizeKPI(kpis?.conversionRate), '%');

      // Transactions Tab KPIs
      updateKPI('trans-totalTransactions', normalizeKPI(kpis?.totalTransactions));
      updateKPI('trans-cohortTransactions', normalizeKPI(kpis?.cohortTransactions));
      updateKPI('trans-transactingUsers', normalizeKPI(kpis?.transactingUsers));
      updateKPI('trans-cohortTransactingUsers', normalizeKPI(kpis?.cohortTransactingUsers));
      updateKPI('trans-convCohort', normalizeKPI(kpis?.conversionRate), '%');
      updateKPI('trans-convPeriod', normalizeKPI(kpis?.conversionRatePeriod), '%');
      // transactions/user fallback
      const tpObj = (kpis?.transactionsPerUser)
        ? normalizeKPI(kpis.transactionsPerUser)
        : (kpis?.totalTransactions && kpis?.transactingUsers)
          ? { current: (normalizeKPI(kpis.totalTransactions).current && normalizeKPI(kpis.transactingUsers).current)
                ? +(normalizeKPI(kpis.totalTransactions).current / normalizeKPI(kpis.transactingUsers).current).toFixed(1)
                : 0,
              previous: 0, change: 0 }
          : { current: 0, previous: 0, change: 0 };
      updateKPI('trans-transactionsPerUser', tpObj);
      updateKPI('trans-avgInterTxDays', normalizeKPI(kpis?.avgInterTransactionDays), ' j');

      // Merchants Tab KPIs
      updateKPI('merch-totalPartners', normalizeKPI(kpis?.totalPartners));
      updateKPI('merch-activeMerchants', normalizeKPI(kpis?.activeMerchants));
      updateKPI('merch-totalTransactions', normalizeKPI(kpis?.totalTransactions));
      updateKPI('merch-transactionsPerMerchant', normalizeKPI(kpis?.transactionsPerMerchant));
      updateKPI('merch-totalLocationsActive', normalizeKPI(kpis?.totalLocationsActive));
      const activeNow = normalizeKPI(kpis?.activeMerchants).current;
      const totalNow = normalizeKPI(kpis?.totalPartners).current;
      const activePrev = normalizeKPI(kpis?.activeMerchants).previous;
      const totalPrev = normalizeKPI(kpis?.totalPartners).previous;
      const ratioNow = totalNow > 0 ? +(activeNow / totalNow * 100).toFixed(1) : 0;
      const ratioPrev = totalPrev > 0 ? +(activePrev / totalPrev * 100).toFixed(1) : 0;
      const ratioChange = ratioPrev !== 0 ? +(((ratioNow - ratioPrev) / Math.abs(ratioPrev)) * 100).toFixed(1) : 0;
      updateKPI('merch-activeMerchantRatio', { current: ratioNow, previous: ratioPrev, change: ratioChange }, '%');
    }

        function updateMerchantKPIs(merchants, kpis) {
      const normalizeKPI = (obj) => (obj && typeof obj.current !== 'undefined') ? obj : { current: 0, previous: 0, change: 0 };
        const topMerchantShareEl = document.getElementById('merch-topMerchantShare');
        const topMerchantNameEl = document.getElementById('merch-topMerchantName');
        const diversityEl = document.getElementById('merch-diversity');
        const diversityDetailEl = document.getElementById('merch-diversityDetail');
        
            // Extraire le tableau de marchands: peut être un tableau direct ou {data: [...], categories: [...]}
            const merchantsList = Array.isArray(merchants) ? merchants : (Array.isArray(merchants?.data) ? merchants.data : []);
            const enriched = merchantsList.slice();
            if (enriched.length > 0 && (typeof enriched[0].share === 'undefined' || enriched[0].share === null)) {
              const totalTx = enriched.reduce((s, m) => s + (m.current || 0), 0);
              enriched.forEach(m => { m.share = totalTx > 0 ? +(m.current * 100 / totalTx).toFixed(1) : 0; });
              enriched.sort((a, b) => (b.current || 0) - (a.current || 0));
            }
            
            if (enriched && enriched.length > 0) {
                const topMerchant = enriched[0];
        if (topMerchantShareEl) topMerchantShareEl.textContent = `${topMerchant.share}%`;
                if (topMerchantNameEl) {
          const merchantName = topMerchant.name.length > 20 ? topMerchant.name.substring(0, 20) + '...' : topMerchant.name;
                    topMerchantNameEl.textContent = merchantName;
          topMerchantNameEl.title = topMerchant.name;
                }
        // Diversité basée sur le nombre de marchands actifs
        const merchantCount = normalizeKPI(kpis?.activeMerchants).current;
                let diversityLevel = 'Faible';
                if (merchantCount >= 15) diversityLevel = 'Élevée';
                else if (merchantCount >= 8) diversityLevel = 'Moyenne';
                if (diversityEl) diversityEl.textContent = diversityLevel;
        if (diversityDetailEl) diversityDetailEl.textContent = `${merchantCount} marchands actifs`;
            } else {
                if (topMerchantShareEl) topMerchantShareEl.textContent = '0%';
                if (topMerchantNameEl) topMerchantNameEl.textContent = 'Aucun marchand';
                if (diversityEl) diversityEl.textContent = 'Aucune';
                if (diversityDetailEl) diversityDetailEl.textContent = 'Aucun marchand actif';
      }
    }

    // Update individual KPI
    function updateKPI(elementId, data, suffix = '') {
      const valueElement = document.getElementById(elementId);
      // Pour les KPIs Timwe et Ooredoo, utiliser '-delta' au lieu de 'Delta'
      const deltaId = (elementId.startsWith('timwe-') || elementId.startsWith('ooredoo-')) 
        ? elementId + '-delta' 
        : elementId + 'Delta';
      const deltaElement = document.getElementById(deltaId);
      
      // Normalisation: éviter les erreurs si data est undefined/null
      const safe = (data && typeof data.current !== 'undefined')
        ? data
        : { current: 0, previous: 0, change: 0 };

      // DEBUG: tracer tous les KPI subscription ET timwe ET ooredoo problématiques
      if (elementId.startsWith('sub-') || elementId.startsWith('timwe-') || elementId.startsWith('ooredoo-')) {
        console.log('[KPI DEBUG]', elementId, JSON.parse(JSON.stringify(safe)));
      }
      
      if (valueElement) {
        // Force la mise à jour complète même si c'était en mode "Optimisation"
        valueElement.innerHTML = ''; // Clear any existing content including loading states
        // Force un nouveau rendu pour éviter les résidus
        valueElement.className = valueElement.className; // Trigger reflow
        // Utiliser 1 décimale pour les KPI fractionnels, 0 pour les entiers
        const isDecimalKpi = elementId.includes('transactionsPerUser') || elementId.includes('avgInterTxDays') || elementId.includes('transactionsPerMerchant');
        const decimals = isDecimalKpi ? 1 : 0;
        const formattedValue = (typeof safe.current === 'string') ? safe.current : formatNumber(safe.current, decimals);
        valueElement.textContent = formattedValue + suffix;
      }
      
      if (deltaElement) {
        const change = Number.isFinite(safe.change) ? safe.change : 0;
        const isPositive = change > 0;
        const isNegative = change < 0;

        // DEBUG pour Timwe
        if (elementId.startsWith('timwe-')) {
          console.log(`🔍 [DELTA] ${elementId}:`, {
            exists: !!deltaElement,
            change,
            previous: safe.previous,
            willShow: !(change === 0 && safe.previous === 0)
          });
        }

        // Masquer le delta si pas de données de comparaison (change = 0 ET previous = 0)
        if (change === 0 && safe.previous === 0) {
          // Nettoyer complètement le contenu et masquer
          deltaElement.innerHTML = '';
          deltaElement.textContent = '';
          deltaElement.style.display = 'none';
          deltaElement.className = 'kpi-delta';
        } else {
          // Afficher le delta avec les bonnes classes
          deltaElement.style.display = '';
          deltaElement.innerHTML = ''; // Nettoyer d'abord
          
          // Inverser la couleur pour les KPI où une hausse est MAUVAISE (deactivations, churn, durée entre transactions)
          const inverse = elementId.includes('deactivated') || elementId.includes('Deactivated') || elementId.includes('churn') || elementId.includes('Churn') || elementId.includes('lostSubscriptions') || elementId.includes('avgInterTxDays') || elementId.includes('simchurn') || elementId.includes('unsubscriptions') || elementId.includes('Unsubscriptions');
          const positiveClass = inverse ? 'delta-negative' : 'delta-positive';
          const negativeClass = inverse ? 'delta-positive' : 'delta-negative';
          
          deltaElement.textContent = `${isPositive ? '↗' : isNegative ? '↘' : '→'} ${isPositive ? '+' : ''}${change.toFixed(1)}%`;
          deltaElement.className = `kpi-delta ${isPositive ? positiveClass : isNegative ? negativeClass : 'delta-neutral'}`;
          
          // DEBUG pour Timwe
          if (elementId.startsWith('timwe-')) {
            console.log(`✅ [DELTA SET] ${elementId}:`, deltaElement.textContent);
          }
        }
      } else if (elementId.startsWith('timwe-')) {
        console.log(`❌ [DELTA] ${elementId}: deltaElement NOT FOUND`);
      }
    }

    // Helper function to update KPI value only (for new KPIs without comparison)
    function updateKPIValue(id, value, suffix = '') {
      const element = document.getElementById(id);
      if (element && value !== undefined && value !== null) {
        // Utiliser formatNumber avec 0 décimales pour les entiers
        const formattedValue = (typeof value === 'string') ? value : formatNumber(value, 0);
        element.textContent = formattedValue + suffix;
      }
    }

    // SUPPRIMÉ - Utiliser la nouvelle fonction formatNumber() avec espaces et virgules définie plus haut
    // Ancienne fonction qui arrondissait en "K" - remplacée par formatNumber(value, decimals)

    // === Charts => public/js/dashboard/charts.js ===
    // === Daily Statistics Table => public/js/dashboard/tables.js ===
    // === Timwe Functions => public/js/dashboard/timwe.js ===
    // === Ooredoo/DGV Functions => public/js/dashboard/ooredoo.js ===
    // === Merchants & Subscriptions Tables => public/js/dashboard/tables.js ===
    // Add date picker shortcuts functionality
    function toggleDatePickerMode() {
      const shortcuts = [
        { label: '7 derniers jours', days: 7 },
        { label: '14 derniers jours', days: 14 },
        { label: '30 derniers jours', days: 30 },
        { label: '3 mois', type: 'nMonths', months: 3 },
        { label: '6 mois', type: 'nMonths', months: 6 },
        { label: '12 mois', type: 'nMonths', months: 12 }
      ];
      
      // Create modal for shortcuts
      const modal = document.createElement('div');
      modal.style.cssText = `
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center;
        z-index: 10000;
      `;
      
      const content = document.createElement('div');
      content.style.cssText = `
        background: white; padding: 24px; border-radius: 12px; min-width: 300px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.2);
      `;
      
      content.innerHTML = `
        <h3 style="margin: 0 0 16px 0; color: var(--brand-dark);">📆 Raccourcis de Date</h3>
        <div id="shortcut-buttons"></div>
        <button onclick="this.closest('.modal').remove()" style="
          width: 100%; margin-top: 16px; padding: 8px; border: 1px solid #ccc;
          border-radius: 6px; background: white; cursor: pointer;
        ">Annuler</button>
      `;
      
      const buttonsContainer = content.querySelector('#shortcut-buttons');
      shortcuts.forEach(shortcut => {
        const btn = document.createElement('button');
        btn.textContent = shortcut.label;
        btn.style.cssText = `
          width: 100%; margin-bottom: 8px; padding: 12px; border: none;
          border-radius: 6px; background: var(--brand-red); color: white;
          cursor: pointer; font-weight: 500;
        `;
        btn.onclick = () => {
          applyDateShortcut(shortcut);
          modal.remove();
        };
        buttonsContainer.appendChild(btn);
      });
      
      modal.className = 'modal';
      modal.appendChild(content);
      document.body.appendChild(modal);
    }

    function applyDateShortcut(shortcut) {
      const today = new Date();
      let startDate, endDate;
      
      if (shortcut.days) {
        endDate = new Date(today);
        startDate = new Date(today);
        startDate.setDate(startDate.getDate() - shortcut.days + 1);
      } else if (shortcut.type === 'nMonths') {
        endDate = new Date(today);
        startDate = new Date(today);
        startDate.setMonth(startDate.getMonth() - shortcut.months);
      } else if (shortcut.type === 'month') {
        startDate = new Date(today.getFullYear(), today.getMonth(), 1);
        endDate = new Date(today);
      } else if (shortcut.type === 'lastMonth') {
        startDate = new Date(today.getFullYear(), today.getMonth() - 1, 1);
        endDate = new Date(today.getFullYear(), today.getMonth(), 0);
      }
      
      document.getElementById('start-date').value = startDate.toISOString().split('T')[0];
      document.getElementById('end-date').value = endDate.toISOString().split('T')[0];
      
      // Auto-set comparison period
      setSmartComparison();
      updateDateRange();
      loadDashboardData();
      
      showNotification(`Période appliquée: ${shortcut.label}`, 'success');
    }

    // Update comparison table
    function updateComparisonTable(kpis) {
      const tbody = document.getElementById('comparisonTableBody');
      if (!tbody) return;
      
      const safe = (obj) => obj ?? { current: 0, previous: 0, change: 0 };
      
      const metrics = [
        { name: 'Activated Subscriptions', data: safe(kpis?.activatedSubscriptions) },
        { name: 'Active Subscriptions', data: safe(kpis?.activeSubscriptions) },
        { name: 'Total Transactions', data: safe(kpis?.totalTransactions) },
        { name: 'Transacting Users', data: safe(kpis?.transactingUsers) },
        { name: 'Active Merchants', data: safe(kpis?.activeMerchants) },
        { name: 'Conversion Rate (%)', data: safe(kpis?.conversionRate) }
      ];
      
      tbody.innerHTML = metrics.map(metric => {
        const data = metric.data || { current: 0, previous: 0, change: 0 };
        const current = Number(data.current) || 0;
        const previous = Number(data.previous) || 0;
        const change = Number.isFinite(data.change) ? Number(data.change) : 0;
        const isPositive = change > 0;
        const badgeClass = isPositive ? 'badge-success' : change < 0 ? 'badge-danger' : 'badge-info';
        const absoluteChange = current - previous;
        const isPercent = metric.name.includes('%');
        const dec = isPercent ? 1 : 0;
        
        return `
          <tr>
            <td><strong>${metric.name}</strong></td>
            <td>${formatNumber(current, dec)}</td>
            <td>${formatNumber(previous, dec)}</td>
            <td>${absoluteChange > 0 ? '+' : ''}${formatNumber(absoluteChange, dec)}</td>
            <td>${change > 0 ? '+' : ''}${change.toFixed(1)}%</td>
            <td><span class="badge ${badgeClass}">${isPositive ? 'Improved' : change < 0 ? 'Declined' : 'Stable'}</span></td>
          </tr>
        `;
      }).join('');
    }

    // Update insights (disabled)
    /*
    function updateInsights(insights) {
      updateInsightList('positiveInsights', insights.positive);
      updateInsightList('challenges', insights.challenges);
      updateInsightList('recommendations', insights.recommendations);
      updateInsightList('nextSteps', insights.nextSteps);
    }
    */

    // Update individual insight list
    function updateInsightList(elementId, items) {
      const list = document.getElementById(elementId);
      if (!list) return;
      
      list.innerHTML = items.map(item => `<li>${item}</li>`).join('');
    }


    // ML Insights Widget loader
    async function loadMLInsights() {
      try {
        const response = await fetch('/admin/ml-dashboard/insights', {
          headers: { 'Accept': 'application/json' },
          credentials: 'same-origin'
        });
        if (!response.ok) return;
        const data = await response.json();
        if (!data.success) return;
        
        const accEl = document.getElementById('ml-model-accuracy');
        const churnEl = document.getElementById('ml-churn-risk');
        const successEl = document.getElementById('ml-success-rate');
        const trainedEl = document.getElementById('ml-last-trained');
        
        if (accEl) accEl.textContent = data.accuracy ? data.accuracy.toFixed(1) + '%' : 'N/A';
        if (churnEl) churnEl.textContent = (data.churn_risk_count || 0).toLocaleString();
        if (successEl) successEl.textContent = data.avg_success_rate ? data.avg_success_rate + '%' : 'N/A';
        if (trainedEl && data.trained_at) {
          const d = new Date(data.trained_at);
          trainedEl.textContent = d.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
        }
      } catch (e) {
        console.warn('ML Insights load error:', e);
      }
    }
    // Auto-load on page init
    document.addEventListener('DOMContentLoaded', () => { setTimeout(loadMLInsights, 2000); });
    window.loadMLInsights = loadMLInsights;
