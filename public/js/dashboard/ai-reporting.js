    // ===== AGENT IA STYLE CHATGPT =====
    let aiSessionDashboard = null;
    let aiConversationsFromApi = [];
    let aiDashboardInitialized = false;

    function initializeAIDashboard() {
      if (aiDashboardInitialized) { loadConversationsFromDatabase(); return; }
      aiDashboardInitialized = true;
      aiSessionDashboard = generateAIUUID();
      loadAIQuotaStats();
      const sessionEl = document.getElementById('aiCurrentSession');
      const sidebarSessionEl = document.getElementById('aiSessionSidebar');
      if (sessionEl) sessionEl.textContent = aiSessionDashboard.substr(0, 8);
      if (sidebarSessionEl) sidebarSessionEl.textContent = aiSessionDashboard.substr(0, 8);
      loadConversationsFromDatabase();
      updateConversationsSidebar();
      const aiInput = document.getElementById('aiQuestionInput');
      if (aiInput) {
        aiInput.addEventListener('input', function() { this.style.height = 'auto'; this.style.height = Math.min(this.scrollHeight, 120) + 'px'; });
      }
      window.addEventListener('beforeunload', function() { saveCurrentConversationAuto(); });
      const renameModal = document.getElementById('aiRenameModal');
      const renameInput = document.getElementById('aiRenameModalInput');
      const renameOk = document.getElementById('aiRenameModalOk');
      const renameCancel = document.getElementById('aiRenameModalCancel');
      if (renameOk) renameOk.addEventListener('click', submitRenameConversation);
      if (renameCancel) renameCancel.addEventListener('click', closeRenameModal);
      if (renameInput) renameInput.addEventListener('keydown', function(e) { if (e.key === 'Enter') { e.preventDefault(); submitRenameConversation(); } if (e.key === 'Escape') closeRenameModal(); });
      if (renameModal) renameModal.addEventListener('click', function(e) { if (e.target === renameModal) closeRenameModal(); });
    }

    function loadConversationsFromDatabase() {
      fetch('/admin/ai-agent/conversations', { headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content } })
      .then(r => r.json()).then(data => { if (data.success && data.conversations) { aiConversationsFromApi = data.conversations; updateConversationsSidebar(); } })
      .catch(() => { aiConversationsFromApi = []; });
    }

    function renameCurrentConversation() {
      if (!aiSessionDashboard) { showNotification('Aucune conversation active', 'error'); return; }
      const currentFromApi = aiConversationsFromApi.find(c => c.session_id === aiSessionDashboard);
      const modal = document.getElementById('aiRenameModal');
      const input = document.getElementById('aiRenameModalInput');
      if (!modal || !input) return;
      input.value = currentFromApi && currentFromApi.title ? currentFromApi.title : '';
      modal.style.display = 'flex';
      input.focus(); input.select();
    }

    function closeRenameModal() { const modal = document.getElementById('aiRenameModal'); if (modal) modal.style.display = 'none'; }

    function submitRenameConversation() {
      const input = document.getElementById('aiRenameModalInput');
      const title = input && input.value ? input.value.trim() : '';
      if (!title) { showNotification('Saisissez un nom', 'error'); return; }
      if (!aiSessionDashboard) return;
      closeRenameModal();
      fetch('/admin/ai-agent/conversation/' + aiSessionDashboard + '/title', { method: 'PATCH', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body: JSON.stringify({ title: title }) })
      .then(r => r.json()).then(data => { if (data.success) { loadConversationsFromDatabase(); updateConversationsSidebar(aiSessionDashboard); showNotification('Conversation nommee : ' + data.title, 'success'); } else { showNotification('Erreur lors de la mise a jour', 'error'); } })
      .catch(() => showNotification('Erreur reseau', 'error'));
    }

    function loadConversationFromApi(sessionId) {
      fetch('/admin/ai-agent/conversation/' + sessionId, { headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content } })
      .then(r => r.json()).then(data => {
        if (!data.success || !data.messages) { showNotification('Impossible de charger la conversation', 'error'); return; }
        document.getElementById('aiMessagesContainer').innerHTML = '';
        data.messages.forEach(m => appendAIMessageFromHistory(m.type, m.message));
        aiSessionDashboard = sessionId;
        document.getElementById('aiCurrentSession').textContent = sessionId.substr(0, 8);
        document.getElementById('aiSessionSidebar').textContent = sessionId.substr(0, 8);
        updateConversationsSidebar(sessionId);
        showNotification('Conversation chargee', 'success');
      }).catch(() => showNotification('Erreur chargement', 'error'));
    }

    function askAIQuestion(question) { document.getElementById('aiQuestionInput').value = question; sendAIQuestionNow(); }

    function newAIConversationNow() {
      saveCurrentConversationAuto();
      aiSessionDashboard = generateAIUUID();
      document.getElementById('aiCurrentSession').textContent = aiSessionDashboard.substr(0, 8);
      document.getElementById('aiSessionSidebar').textContent = aiSessionDashboard.substr(0, 8);
      document.getElementById('aiMessagesContainer').innerHTML = '';
      updateConversationsSidebar();
      showNotification('Nouvelle conversation', 'success');
    }

    function saveCurrentConversation() {
      const messages = document.getElementById('aiMessagesContainer').children;
      if (messages.length === 0) { showNotification('Aucune conversation a sauvegarder', 'error'); return; }
      const title = prompt('Nom de la conversation :', 'Conversation ML ' + new Date().toLocaleString('fr-FR', {month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit'}));
      if (!title) return;
      const conversation = { id: aiSessionDashboard, title: title, messages: Array.from(messages).map(msg => ({ type: msg.classList.contains('ai-message-user') ? 'user' : 'assistant', content: msg.querySelector('.ai-message-content').innerHTML })), created_at: new Date().toISOString(), session_id: aiSessionDashboard };
      let savedConversations = JSON.parse(localStorage.getItem('aiConversations') || '[]');
      savedConversations.unshift(conversation);
      if (savedConversations.length > 20) savedConversations = savedConversations.slice(0, 20);
      localStorage.setItem('aiConversations', JSON.stringify(savedConversations));
      updateConversationsSidebar();
      showNotification('Conversation "' + title + '" sauvegardee', 'success');
    }

    function saveCurrentConversationAuto() {
      const messages = document.getElementById('aiMessagesContainer');
      if (!messages || messages.children.length === 0) return;
      const autoTitle = 'Auto - ' + new Date().toLocaleString('fr-FR', {month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit'});
      const conversation = { id: aiSessionDashboard + '_auto', title: autoTitle, messages: Array.from(messages.children).map(msg => ({ type: msg.classList.contains('ai-message-user') ? 'user' : 'assistant', content: msg.querySelector('.ai-message-content') ? msg.querySelector('.ai-message-content').innerHTML : '' })), created_at: new Date().toISOString(), session_id: aiSessionDashboard, auto_saved: true };
      let savedConversations = JSON.parse(localStorage.getItem('aiConversations') || '[]');
      savedConversations.unshift(conversation);
      localStorage.setItem('aiConversations', JSON.stringify(savedConversations.slice(0, 20)));
    }

    function loadConversation(conversationId) {
      const conversations = JSON.parse(localStorage.getItem('aiConversations') || '[]');
      const conversation = conversations.find(c => c.id === conversationId);
      if (!conversation) { showNotification('Conversation non trouvee', 'error'); return; }
      document.getElementById('aiMessagesContainer').innerHTML = '';
      conversation.messages.forEach(msg => appendAIMessageFromHistory(msg.type, msg.content));
      aiSessionDashboard = conversation.session_id;
      document.getElementById('aiCurrentSession').textContent = aiSessionDashboard.substr(0, 8);
      document.getElementById('aiSessionSidebar').textContent = aiSessionDashboard.substr(0, 8);
      updateConversationsSidebar(conversationId);
      showNotification('Conversation "' + conversation.title + '" chargee', 'success');
    }

    function appendAIMessageFromHistory(type, content) {
      const container = document.getElementById('aiMessagesContainer');
      if (!container) return;
      const messageDiv = document.createElement('div');
      messageDiv.className = 'ai-message-' + type;
      messageDiv.innerHTML = content.includes('ai-message-content') ? content : '<div style="display: flex; gap: 12px; align-items: flex-start;"><div style="width: 30px; height: 30px; background: ' + (type === 'user' ? '#6366f1' : '#10b981') + '; border-radius: 4px; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 0.9rem; flex-shrink: 0;">' + (type === 'user' ? 'U' : 'IA') + '</div><div class="ai-message-content" style="flex: 1; padding-top: 4px;">' + content + '</div></div>';
      container.appendChild(messageDiv);
      scrollAIToBottom();
    }

    function updateConversationsSidebar(activeId) {
      const container = document.getElementById('aiConversationsList');
      if (!container) return;
      const isCurrentActive = !activeId || activeId === aiSessionDashboard;
      const currentFromApi = aiConversationsFromApi.find(c => c.session_id === aiSessionDashboard);
      const currentTitle = currentFromApi ? (currentFromApi.title || 'Conversation Actuelle') : 'Conversation Actuelle';
      container.innerHTML = '';
      const currentDiv = document.createElement('div');
      currentDiv.className = 'ai-conversation-item' + (isCurrentActive ? ' active' : '');
      currentDiv.style.cssText = 'padding: 12px; margin: 4px 0; background: ' + (isCurrentActive ? 'rgba(108,75,160,0.08)' : 'transparent') + '; border-radius: 8px; border-left: 3px solid ' + (isCurrentActive ? '#6C4BA0' : 'transparent') + '; cursor: pointer; color: #A1A1AA;';
      currentDiv.innerHTML = '<div style="display: flex; justify-content: space-between; align-items: center;"><div style="flex: 1; min-width: 0;" onclick="selectCurrentConversation()"><div style="font-size: 0.85rem; font-weight: 500; color: #374151;">' + currentTitle + '</div></div><button type="button" onclick="event.stopPropagation(); renameCurrentConversation();" style="background: #6366f1; border: none; color: white; padding: 4px 8px; border-radius: 4px; font-size: 0.7rem; cursor: pointer;">Nommer</button></div>';
      container.appendChild(currentDiv);
      aiConversationsFromApi.forEach(function(conv) {
        if (conv.session_id === aiSessionDashboard) return;
        const isActive = activeId === conv.session_id;
        const title = (conv.title || 'Sans titre');
        const item = document.createElement('div');
        item.className = 'ai-conversation-item' + (isActive ? ' active' : '');
        item.style.cssText = 'padding: 12px; margin: 4px 0; background: ' + (isActive ? 'rgba(108,75,160,0.08)' : 'transparent') + '; border-radius: 8px; border-left: 3px solid ' + (isActive ? '#6C4BA0' : 'transparent') + '; cursor: pointer; color: #A1A1AA;';
        item.innerHTML = '<div style="font-size: 0.8rem; font-weight: 500; color: #374151;">' + title + '</div>';
        item.onclick = function() { loadConversationFromApi(conv.session_id); };
        container.appendChild(item);
      });
    }

    function deleteConversationFromApi(sessionId) {
      if (!sessionId || !confirm('Supprimer cette conversation ?')) return;
      fetch('/admin/ai-agent/conversation/' + encodeURIComponent(sessionId), { method: 'DELETE', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content } })
      .then(r => r.json()).then(data => {
        if (data.success) { if (aiSessionDashboard === sessionId) { aiSessionDashboard = generateAIUUID(); document.getElementById('aiCurrentSession').textContent = aiSessionDashboard.substr(0, 8); document.getElementById('aiSessionSidebar').textContent = aiSessionDashboard.substr(0, 8); document.getElementById('aiMessagesContainer').innerHTML = ''; } loadConversationsFromDatabase(); updateConversationsSidebar(); showNotification('Conversation supprimee', 'success'); }
      }).catch(() => showNotification('Erreur reseau', 'error'));
    }

    function selectCurrentConversation() { updateConversationsSidebar(); }

    function loadConversationDialog() {
      const conversations = JSON.parse(localStorage.getItem('aiConversations') || '[]');
      if (conversations.length === 0) { showNotification('Aucune conversation sauvegardee', 'error'); return; }
      const options = conversations.map((c, i) => (i + 1) + '. ' + c.title).join('\n');
      const choice = prompt('Choisissez une conversation :\n\n' + options + '\n\nEntrez le numero :');
      if (choice && !isNaN(choice) && choice > 0 && choice <= conversations.length) loadConversation(conversations[choice - 1].id);
    }

    function deleteConversation(conversationId) {
      if (!confirm('Supprimer cette conversation ?')) return;
      let conversations = JSON.parse(localStorage.getItem('aiConversations') || '[]');
      conversations = conversations.filter(c => c.id !== conversationId);
      localStorage.setItem('aiConversations', JSON.stringify(conversations));
      updateConversationsSidebar();
    }

    function clearAllConversations() {
      if (!confirm('Supprimer toutes les conversations ?')) return;
      localStorage.removeItem('aiConversations');
      updateConversationsSidebar();
    }

    function sendAIQuestionNow() {
      const input = document.getElementById('aiQuestionInput');
      const question = input.value.trim();
      const sendBtn = document.getElementById('aiSendBtn');
      if (!question) { showNotification('Veuillez saisir une question', 'error'); return; }
      if (!aiSessionDashboard) { aiSessionDashboard = generateAIUUID(); document.getElementById('aiCurrentSession').textContent = aiSessionDashboard.substr(0, 8); }
      if (sendBtn) { sendBtn.disabled = true; sendBtn.style.background = '#d1d5db'; }
      if (input) input.disabled = true;
      appendAIMessage('user', question);
      input.value = '';
      document.getElementById('aiTypingIndicator').style.display = 'block';
      scrollAIToBottom();
      fetch('/admin/ai-agent/ask', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body: JSON.stringify({ question: question, session_id: aiSessionDashboard, provider: (document.getElementById('aiProviderSelectDashboard') && document.getElementById('aiProviderSelectDashboard').value) || 'openai' }) })
      .then(response => { if (!response.ok) throw new Error('HTTP ' + response.status); const ct = response.headers.get('content-type'); if (!ct || !ct.includes('application/json')) return response.text().then(html => { throw new Error('Serveur a renvoye du HTML au lieu de JSON'); }); return response.json(); })
      .then(data => {
        document.getElementById('aiTypingIndicator').style.display = 'none';
        if (data.success) { appendAIMessage('assistant', data.message); if (data.session_id) { aiSessionDashboard = data.session_id; document.getElementById('aiCurrentSession').textContent = data.session_id.substr(0, 8); document.getElementById('aiSessionSidebar').textContent = data.session_id.substr(0, 8); } if (data.quota) { updateQuotaWidget(data.quota.daily_used, data.quota.daily_limit, data.quota.remaining); } loadConversationsFromDatabase(); }
        else { appendAIMessage('assistant', 'Erreur: ' + (data.error || 'Verifiez la configuration API')); }
      })
      .catch(error => { document.getElementById('aiTypingIndicator').style.display = 'none'; appendAIMessage('assistant', 'Erreur reseau ou configuration. Verifiez les cles API dans .env'); })
      .finally(() => { if (sendBtn) { sendBtn.disabled = false; sendBtn.style.background = '#6366f1'; } if (input) { input.disabled = false; input.focus(); } });
    }

    function appendAIMessage(type, content) {
      const container = document.getElementById('aiMessagesContainer');
      if (!container) return;
      const messageDiv = document.createElement('div');
      messageDiv.className = 'ai-message-' + type;
      if (type === 'user') {
        messageDiv.innerHTML = '<div style="display: flex; gap: 12px; align-items: flex-start;"><div style="width: 30px; height: 30px; background: #6366f1; border-radius: 4px; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 0.9rem; flex-shrink: 0;">U</div><div class="ai-message-content" style="flex: 1; padding-top: 4px;">' + content + '</div></div>';
      } else {
        messageDiv.innerHTML = '<div style="display: flex; gap: 12px; align-items: flex-start;"><div style="width: 30px; height: 30px; background: #10b981; border-radius: 4px; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 0.9rem; flex-shrink: 0;">IA</div><div class="ai-message-content" style="flex: 1; padding-top: 4px;">' + formatAIMessage(content) + '</div></div>';
      }
      container.appendChild(messageDiv);
      scrollAIToBottom();
    }

    function formatAIMessage(content) {
      let formatted = content.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>').replace(/\*(.*?)\*/g, '<em>$1</em>').replace(/`([^`]+)`/g, '<code style="background:#f3f4f6;padding:2px 6px;border-radius:4px;font-family:monospace;color:#e11d48;">$1</code>').replace(/\n\n/g, '</p><p>').replace(/\n/g, '<br>');
      return '<div style="line-height: 1.6;">' + formatted + '</div>';
    }

    function scrollAIToBottom() {
      const container = document.getElementById('aiMessagesZone');
      if (container) container.scrollTop = container.scrollHeight;
    }

    function generateAIUUID() {
      return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) { const r = Math.random() * 16 | 0; return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16); });
    }

    function loadAIQuotaStats() {
      fetch('/admin/ai-agent/stats', { headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' } })
      .then(r => r.json())
      .then(data => {
        if (!data.success) return;
        const q = data.global_stats?.daily_quota || {};
        updateQuotaWidget(q.used || 0, q.limit || 250, q.remaining || 250);
        const stats = data.global_stats || {};
        const avgEl = document.getElementById('aiAvgTime');
        if (avgEl) avgEl.textContent = stats.avg_execution_time ? Math.round(stats.avg_execution_time) : '0';
        const questEl = document.getElementById('aiTotalQuestions');
        if (questEl) questEl.textContent = formatNumberShort(stats.total_questions || 0);
        const tokEl = document.getElementById('aiTotalTokens');
        if (tokEl) tokEl.textContent = formatNumberShort(stats.total_tokens_consumed || 0);
      }).catch(() => {});
    }

    function updateQuotaWidget(used, limit, remaining) {
      const usedEl = document.getElementById('aiQuotaUsed');
      const limitEl = document.getElementById('aiQuotaLimit');
      const barEl = document.getElementById('aiQuotaBar');
      if (usedEl) usedEl.textContent = used;
      if (limitEl) limitEl.textContent = limit;
      if (barEl) {
        const pct = limit > 0 ? Math.min(100, (used / limit) * 100) : 0;
        barEl.style.width = pct + '%';
        barEl.style.background = pct > 80 ? '#ef4444' : pct > 50 ? '#f59e0b' : '#6366f1';
      }
    }

    function formatNumberShort(n) {
      if (n >= 1000000) return (n/1000000).toFixed(1) + 'M';
      if (n >= 1000) return (n/1000).toFixed(1) + 'K';
      return n.toString();
    }

    // ========================================
    // THEME TOGGLE (Light/Dark Mode)
    // ========================================
    function initTheme() {
      const saved = localStorage.getItem('dashboard-theme');
      // Default is light mode (no class = light)
      if (saved === 'dark') {
        document.documentElement.classList.add('dark-mode');
      } else {
        document.documentElement.classList.remove('dark-mode');
      }
      updateThemeIcons();
    }

    function toggleTheme() {
      const isDark = document.documentElement.classList.toggle('dark-mode');
      localStorage.setItem('dashboard-theme', isDark ? 'dark' : 'light');
      updateThemeIcons();
      // Update Chart.js colors for current theme
      updateChartsTheme();
    }

    function updateThemeIcons() {
      const isDark = document.documentElement.classList.contains('dark-mode');
      const sunIcon = document.getElementById('sun-icon');
      const moonIcon = document.getElementById('moon-icon');
      if (sunIcon) sunIcon.style.display = isDark ? 'block' : 'none';
      if (moonIcon) moonIcon.style.display = isDark ? 'none' : 'block';
    }

    function updateChartsTheme() {
      const isDark = document.documentElement.classList.contains('dark-mode');
      const gridColor = isDark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.08)';
      const textColor = isDark ? '#A1A1AA' : '#52525b';
      
      // Update all Chart.js instances
      if (typeof Chart !== 'undefined') {
        Chart.defaults.color = textColor;
        Chart.defaults.borderColor = gridColor;
        
        Object.values(Chart.instances || {}).forEach(chart => {
          if (!chart || !chart.options) return;
          try {
            if (chart.options.scales) {
              Object.values(chart.options.scales).forEach(scale => {
                if (scale.grid) scale.grid.color = gridColor;
                if (scale.ticks) scale.ticks.color = textColor;
              });
            }
            if (chart.options.plugins?.legend?.labels) {
              chart.options.plugins.legend.labels.color = textColor;
            }
            chart.update('none');
          } catch(e) {}
        });
      }
    }

    // Initialize theme immediately
    initTheme();
