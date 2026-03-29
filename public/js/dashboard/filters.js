    // Tab switching functionality
    function showTab(tabName) {
      // Hide all tab contents
      document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.remove('active');
      });
      
      // Remove active class from all tabs
      document.querySelectorAll('.nav-tab').forEach(tab => {
        tab.classList.remove('active');
      });

      // Show selected tab content
      const selectedTab = document.getElementById(tabName);
      if (selectedTab) {
        selectedTab.classList.add('active');
      }

      // Add active class to clicked tab (use data-tab attribute)
      const clickedTab = document.querySelector(`.nav-tab[data-tab="${tabName}"]`);
      if (clickedTab) {
        clickedTab.classList.add('active');
      }
      
      // Auto-scroll to center active tab on mobile
      if (clickedTab && typeof centerActiveTab === 'function') {
        centerActiveTab(clickedTab);
      }
      
      if (tabName === 'eklektik') {
        console.log('Onglet Eklektik activé');
      }
      
      // Masquer la section dates sur l'onglet Agent IA
      var filtersBar = document.querySelector('.enhanced-filters-bar');
      if (filtersBar) {
        filtersBar.style.display = (tabName === 'ai-agent') ? 'none' : '';
      }
      
      // Charger l'historique des conversations Agent IA
      if (tabName === 'ai-agent' && typeof initializeAIDashboard === 'function') {
        initializeAIDashboard();
      }
      
      // Resize charts when tab becomes visible
      setTimeout(() => {
        Object.values(charts).forEach(chart => {
          if (chart && typeof chart.resize === 'function') {
            chart.resize();
          }
        });
        if (window._dashboardData && typeof updateCharts === 'function') {
          try { updateCharts(window._dashboardData); } catch(e) {}
        }
      }, 200);
    }

    // Agent IA Panel toggle
    function toggleAIPanel() {
      const panel = document.getElementById('aiPanel');
      const overlay = document.getElementById('aiPanelOverlay');
      if (!panel) return;
      
      const isOpen = panel.classList.contains('open');
      if (isOpen) {
        panel.classList.remove('open');
        if (overlay) overlay.classList.remove('open');
      } else {
        panel.classList.add('open');
        if (overlay) overlay.classList.add('open');
        // Déplacer le contenu AI dans le panel si pas encore fait
        const aiContent = document.getElementById('ai-agent');
        const panelBody = document.getElementById('aiPanelBody');
        if (aiContent && panelBody && !panelBody.hasChildNodes()) {
          panelBody.appendChild(aiContent);
          aiContent.style.display = 'block';
          aiContent.classList.add('active');
        }
        if (typeof initializeAIDashboard === 'function') {
          initializeAIDashboard();
        }
      }
    }
