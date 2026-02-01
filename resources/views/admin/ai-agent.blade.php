<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>🤖 Agent IA - Dashboard ML</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #6366f1;
            --secondary-color: #8b5cf6;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
        }

        body {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }

        .chat-container {
            height: 70vh;
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .chat-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 20px;
            text-align: center;
        }

        .chat-messages {
            height: calc(100% - 140px);
            overflow-y: auto;
            padding: 20px;
            scroll-behavior: smooth;
        }

        .message {
            margin-bottom: 16px;
            clear: both;
        }

        .message.user-message {
            text-align: right;
        }

        .message.assistant-message {
            text-align: left;
        }

        .message-bubble {
            display: inline-block;
            max-width: 80%;
            padding: 12px 16px;
            border-radius: 18px;
            word-wrap: break-word;
            animation: fadeIn 0.3s ease-in;
        }

        .user-message .message-bubble {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }

        .assistant-message .message-bubble {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            color: #1e293b;
        }

        .message-meta {
            font-size: 0.75rem;
            color: #64748b;
            margin-top: 4px;
        }

        .chat-input-container {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            padding: 20px;
            border-top: 1px solid #e2e8f0;
        }

        .suggestion-btn {
            margin: 4px;
            font-size: 0.85rem;
            border-radius: 20px;
            transition: all 0.2s;
        }

        .suggestion-btn:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-1px);
        }

        .typing-indicator {
            display: none;
        }

        .typing-indicator.show {
            display: block;
            animation: pulse 1.5s infinite;
        }

        .config-alert {
            margin-bottom: 20px;
        }

        .stats-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            padding: 16px;
            margin-bottom: 16px;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        .markdown-content table {
            width: 100%;
            margin: 12px 0;
            border-collapse: collapse;
        }

        .markdown-content th, .markdown-content td {
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            text-align: left;
        }

        .markdown-content th {
            background: #f8fafc;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <!-- En-tête -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <h2>🤖 Agent IA - Assistant ML Intelligent</h2>
                    <div class="d-flex gap-2">
                        <button id="testAgentBtn" class="btn btn-outline-primary btn-sm">🧪 Test Agent</button>
                        <button id="newConversationBtn" class="btn btn-primary btn-sm">➕ Nouvelle Conversation</button>
                        <a href="/admin/ml-dashboard" class="btn btn-outline-secondary btn-sm">⬅️ Retour ML Dashboard</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Configuration Alert -->
        @if($config['status'] !== 'ok')
            <div class="alert alert-{{ $config['status'] === 'error' ? 'danger' : 'warning' }} config-alert">
                <h6>⚠️ Configuration Agent IA</h6>
                <ul class="mb-0">
                    @foreach($config['issues'] as $issue)
                        <li>{{ $issue }}</li>
                    @endforeach
                </ul>
                @if($config['status'] === 'error')
                    <small class="mt-2 d-block"><strong>Action requise :</strong> Configurez OPENAI_API_KEY dans .env et relancez les migrations.</small>
                @endif
            </div>
        @endif

        <div class="row">
            <!-- Chat Principal -->
            <div class="col-lg-8 col-xl-9">
                <div class="chat-container position-relative">
                    <!-- En-tête Chat -->
                    <div class="chat-header">
                        <h5 class="mb-1">🤖 Assistant ML Intelligent</h5>
                        <small>Posez-moi des questions sur les données, modèles ML, et stratégies de pricing</small>
                    </div>
                    
                    <!-- Messages -->
                    <div id="chatMessages" class="chat-messages">
                        <!-- Message de bienvenue -->
                        <div class="message assistant-message">
                            <div class="message-bubble">
                                <strong>🤖 Agent IA</strong>
                                <div class="mt-2">
                                    Bonjour ! Je connais parfaitement votre système ML :
                                    <ul class="mb-2 mt-2">
                                        <li><strong>📊 {{ number_format($config['configuration']['total_clients'] ?? 85744) }} clients</strong> analysés avec 36 features ML</li>
                                        <li><strong>🎯 5 segments</strong> : premium (91.3%), regular (54.3%), struggling (24.6%), high_risk (0.2%), churn (0.5%)</li>
                                        <li><strong>🌐 3 opérateurs</strong> : Timwe (3.0 TND mensuel), Eklektik (0.3 TND quotidien), Ooredoo (3.0 TND mensuel)</li>
                                        <li><strong>💡 Recommandation principale</strong> : Migration quotidienne 0.3 TND (+643% ROI)</li>
                                    </ul>
                                    Posez-moi vos questions !
                                </div>
                            </div>
                        </div>
                        
                        <!-- Indicateur de frappe -->
                        <div id="typingIndicator" class="message assistant-message typing-indicator">
                            <div class="message-bubble">
                                <em>🤖 L'agent analyse vos données...</em>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Zone de saisie -->
                    <div class="chat-input-container">
                        <!-- Suggestions -->
                        <div id="suggestionsContainer" class="mb-3">
                            <small class="text-muted">💡 <strong>Suggestions :</strong></small>
                            <div class="d-flex flex-wrap">
                                <button class="btn btn-outline-secondary btn-sm suggestion-btn">Quel est le taux de succès actuel ?</button>
                                <button class="btn btn-outline-secondary btn-sm suggestion-btn">Compare quotidien 0.3 vs mensuel 3.0 TND</button>
                                <button class="btn btn-outline-secondary btn-sm suggestion-btn">Explique les top 5 features ML</button>
                                <button class="btn btn-outline-secondary btn-sm suggestion-btn">Recommandations pour High Risk (29k clients)</button>
                                <button class="btn btn-outline-secondary btn-sm suggestion-btn">Analyse client ID 12345</button>
                                <button class="btn btn-outline-secondary btn-sm suggestion-btn">ROI stratégie Eklektik quotidienne</button>
                            </div>
                        </div>
                        
                        <!-- Input -->
                        <div class="input-group">
                            <input type="text" id="questionInput" class="form-control" 
                                   placeholder="Ex: Compare les 3 stratégies de pricing..." 
                                   maxlength="2000" />
                            <button id="sendBtn" class="btn btn-primary" @if($config['status'] === 'error') disabled @endif>
                                📤 <span class="d-none d-sm-inline">Envoyer</span>
                            </button>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <small class="text-muted">💬 Session : <code id="currentSessionId">Nouvelle</code></small>
                            <small class="text-muted">🔥 Modèle : {{ $config['configuration']['model'] ?? 'gpt-4' }}</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Sidebar Stats -->
            <div class="col-lg-4 col-xl-3">
                <!-- Statistiques d'utilisation -->
                <div class="stats-card">
                    <h6 class="mb-3">📈 Utilisation Agent IA (7j)</h6>
                    <div class="row g-2">
                        <div class="col-6">
                            <div class="text-center">
                                <div class="h4 mb-0 text-primary">{{ $usageStats['total_questions'] }}</div>
                                <small class="text-muted">Questions</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center">
                                <div class="h4 mb-0 text-success">{{ $usageStats['unique_users'] }}</div>
                                <small class="text-muted">Utilisateurs</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center">
                                <div class="h4 mb-0 text-info">{{ $usageStats['avg_response_time_ms'] }}ms</div>
                                <small class="text-muted">Temps moy.</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center">
                                <div class="h4 mb-0 text-warning">{{ number_format($usageStats['total_tokens_consumed']) }}</div>
                                <small class="text-muted">Tokens</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sessions récentes -->
                @if(!empty($recentSessions))
                <div class="stats-card">
                    <h6 class="mb-3">💬 Sessions Récentes</h6>
                    @foreach(array_slice($recentSessions, 0, 5, true) as $sessionId => $createdAt)
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <small class="text-muted">{{ \Carbon\Carbon::parse($createdAt)->diffForHumans() }}</small>
                            <div>
                                <button class="btn btn-outline-primary btn-sm load-session" data-session="{{ $sessionId }}">📂</button>
                                <button class="btn btn-outline-danger btn-sm delete-session" data-session="{{ $sessionId }}">🗑️</button>
                            </div>
                        </div>
                    @endforeach
                </div>
                @endif

                <!-- Aide -->
                <div class="stats-card">
                    <h6 class="mb-3">💡 Aide</h6>
                    <div class="small">
                        <p><strong>L'agent peut :</strong></p>
                        <ul class="mb-2">
                            <li>Analyser les performances ML</li>
                            <li>Comparer les stratégies pricing</li>
                            <li>Expliquer les features importantes</li>
                            <li>Recommander des optimisations</li>
                            <li>Analyser des clients spécifiques</li>
                        </ul>
                        
                        <p><strong>Exemples de questions :</strong></p>
                        <ul class="mb-0">
                            <li>"Pourquoi 78% des clients ont 0% succès ?"</li>
                            <li>"ROI Eklektik vs Timwe pour 1000 clients"</li>
                            <li>"Features les plus importantes ?"</li>
                            <li>"Stratégie pour high_risk ?"</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        let currentSessionId = null;

        document.addEventListener('DOMContentLoaded', function() {
            // Initialiser une nouvelle session
            newConversation();
            
            // Event listeners
            document.getElementById('sendBtn').addEventListener('click', sendQuestion);
            document.getElementById('newConversationBtn').addEventListener('click', newConversation);
            document.getElementById('testAgentBtn').addEventListener('click', testAgent);
            document.getElementById('questionInput').addEventListener('keypress', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    sendQuestion();
                }
            });
            
            // Suggestions
            document.querySelectorAll('.suggestion-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    document.getElementById('questionInput').value = this.textContent;
                    sendQuestion();
                });
            });

            // Sessions récentes
            document.querySelectorAll('.load-session').forEach(btn => {
                btn.addEventListener('click', function() {
                    loadSession(this.dataset.session);
                });
            });

            document.querySelectorAll('.delete-session').forEach(btn => {
                btn.addEventListener('click', function() {
                    deleteSession(this.dataset.session);
                });
            });
        });

        function newConversation() {
            currentSessionId = generateUUID();
            document.getElementById('currentSessionId').textContent = currentSessionId.substr(0, 8);
            
            const messagesContainer = document.getElementById('chatMessages');
            
            // Garder seulement le message de bienvenue
            const welcomeMessage = messagesContainer.querySelector('.assistant-message');
            messagesContainer.innerHTML = '';
            if (welcomeMessage) {
                messagesContainer.appendChild(welcomeMessage.cloneNode(true));
            }
            
            console.log('Nouvelle conversation:', currentSessionId);
        }

        async function sendQuestion() {
            const input = document.getElementById('questionInput');
            const question = input.value.trim();
            const sendBtn = document.getElementById('sendBtn');
            
            if (!question || sendBtn.disabled) return;
            
            // Désactiver temporairement
            sendBtn.disabled = true;
            input.disabled = true;
            
            // Afficher le message utilisateur
            appendMessage('user', question);
            input.value = '';
            
            // Afficher l'indicateur de frappe
            showTypingIndicator();
            
            try {
                const response = await fetch('/admin/ai-agent/ask', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        question: question,
                        session_id: currentSessionId
                    })
                });
                
                const data = await response.json();
                
                hideTypingIndicator();
                
                if (data.success) {
                    appendMessage('assistant', data.message, {
                        tokens: data.tokens_used,
                        time: data.execution_time_ms,
                        context: data.context_used
                    });
                    
                    // Mettre à jour l'ID de session si nouveau
                    if (data.session_id && data.session_id !== currentSessionId) {
                        currentSessionId = data.session_id;
                        document.getElementById('currentSessionId').textContent = currentSessionId.substr(0, 8);
                    }
                } else {
                    appendMessage('assistant', '❌ ' + (data.error || 'Erreur lors du traitement de votre question'));
                }
            } catch (error) {
                hideTypingIndicator();
                appendMessage('assistant', '❌ Erreur réseau. Vérifiez votre connexion et réessayez.');
                console.error('Erreur réseau:', error);
            } finally {
                // Réactiver
                sendBtn.disabled = false;
                input.disabled = false;
                input.focus();
            }
        }

        function appendMessage(type, content, meta = null) {
            const container = document.getElementById('chatMessages');
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${type}-message`;
            
            let metaInfo = '';
            if (meta) {
                const contextTypes = meta.context && Array.isArray(meta.context) ? meta.context.join(', ') : '';
                metaInfo = `
                    <div class="message-meta">
                        ${meta.time ? `⏱️ ${meta.time}ms` : ''}
                        ${meta.tokens ? ` • 🔗 ${meta.tokens} tokens` : ''}
                        ${contextTypes ? ` • 📊 ${contextTypes}` : ''}
                    </div>
                `;
            }
            
            messageDiv.innerHTML = `
                <div class="message-bubble">
                    ${type === 'assistant' ? '<strong>🤖 Agent IA</strong><br>' : ''}
                    <div class="markdown-content">${formatMessage(content)}</div>
                    ${metaInfo}
                </div>
            `;
            
            container.appendChild(messageDiv);
            scrollToBottom();
        }

        function formatMessage(content) {
            // Convertir Markdown basique vers HTML
            let html = content
                .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                .replace(/\*(.*?)\*/g, '<em>$1</em>')
                .replace(/`([^`]+)`/g, '<code>$1</code>')
                .replace(/\n\n/g, '</p><p>')
                .replace(/\n/g, '<br>');
            
            // Tableaux Markdown simplifiés
            if (html.includes('|')) {
                html = html.replace(/\|(.+?)\|/g, function(match, content) {
                    const cells = content.split('|').map(cell => cell.trim());
                    if (cells.length > 1) {
                        return '<tr>' + cells.map(cell => `<td>${cell}</td>`).join('') + '</tr>';
                    }
                    return match;
                });
                
                if (html.includes('<tr>')) {
                    html = '<table class="table table-sm table-striped">' + html + '</table>';
                }
            }
            
            return '<p>' + html + '</p>';
        }

        function showTypingIndicator() {
            const indicator = document.getElementById('typingIndicator');
            indicator.classList.add('show');
            scrollToBottom();
        }

        function hideTypingIndicator() {
            const indicator = document.getElementById('typingIndicator');
            indicator.classList.remove('show');
        }

        function scrollToBottom() {
            const container = document.getElementById('chatMessages');
            container.scrollTop = container.scrollHeight;
        }

        function generateUUID() {
            return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
                const r = Math.random() * 16 | 0;
                const v = c === 'x' ? r : (r & 0x3 | 0x8);
                return v.toString(16);
            });
        }

        async function loadSession(sessionId) {
            try {
                const response = await fetch(`/admin/ai-agent/conversation/${sessionId}`);
                const data = await response.json();
                
                if (data.success) {
                    currentSessionId = sessionId;
                    document.getElementById('currentSessionId').textContent = sessionId.substr(0, 8);
                    
                    // Effacer les messages actuels
                    const container = document.getElementById('chatMessages');
                    container.innerHTML = '';
                    
                    // Charger l'historique
                    data.messages.forEach(msg => {
                        appendMessage(msg.type, msg.message, {
                            time: msg.execution_time_ms,
                            tokens: msg.tokens_used
                        });
                    });
                    
                    console.log(`Session ${sessionId} chargée (${data.total_messages} messages)`);
                }
            } catch (error) {
                alert('Erreur lors du chargement de la session');
                console.error(error);
            }
        }

        async function deleteSession(sessionId) {
            if (!confirm('Supprimer cette conversation ?')) return;
            
            try {
                const response = await fetch(`/admin/ai-agent/conversation/${sessionId}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });
                
                const data = await response.json();
                if (data.success) {
                    location.reload(); // Recharger pour mettre à jour la liste
                }
            } catch (error) {
                alert('Erreur lors de la suppression');
                console.error(error);
            }
        }

        async function testAgent() {
            const testBtn = document.getElementById('testAgentBtn');
            const originalText = testBtn.innerHTML;
            
            testBtn.disabled = true;
            testBtn.innerHTML = '⏳ Test...';
            
            try {
                const response = await fetch('/admin/ai-agent/test');
                const data = await response.json();
                
                if (data.success) {
                    const result = data.test_result;
                    alert(`✅ Test réussi!\n` +
                          `Status: ${result.test_status}\n` +
                          `Temps: ${result.execution_time_ms}ms\n` +
                          `Tokens: ${result.tokens_used}`);
                } else {
                    alert('❌ Test échoué: ' + (data.error || 'Erreur inconnue'));
                }
            } catch (error) {
                alert('❌ Erreur réseau lors du test');
                console.error(error);
            } finally {
                testBtn.disabled = false;
                testBtn.innerHTML = originalText;
            }
        }
    </script>
</body>
</html>