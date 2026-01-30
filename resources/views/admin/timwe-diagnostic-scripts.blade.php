<script>
const diagnosticApp = {
    data: null,
    currentPages: {
        byPhone: 1,
        byDeliveryCode: 1,
        recentTransactions: 1
    },
    perPage: 50,
    sortColumn: 'total_attempts',
    sortDirection: 'desc',
    
    init() {
        document.getElementById('btnSearch').addEventListener('click', () => this.search());
        document.getElementById('btnExport').addEventListener('click', () => this.exportCsv());
        
        // Gestion des tabs
        document.querySelectorAll('.tab-button').forEach(button => {
            button.addEventListener('click', (e) => {
                const tabName = e.target.dataset.tab;
                this.switchTab(tabName);
            });
        });
        
        // Gestion du tri sur les colonnes
        this.initSort();
        
        // Fermer la modal en cliquant à l'extérieur
        document.getElementById('phoneDetailsModal').addEventListener('click', (e) => {
            if (e.target.id === 'phoneDetailsModal') {
                this.closePhoneDetails();
            }
        });
        
        // Auto-search au chargement (7 derniers jours)
        this.search();
    },
    
    initSort() {
        document.querySelectorAll('#phoneTable .sortable').forEach(th => {
            th.addEventListener('click', () => {
                const column = th.dataset.sort;
                const type = th.dataset.type;
                this.sortTable(column, type);
            });
        });
    },
    
    sortTable(column, type) {
        if (!this.data || !this.data.by_phone) return;
        
        // Toggle direction si même colonne
        if (this.sortColumn === column) {
            this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            this.sortColumn = column;
            this.sortDirection = 'desc'; // Par défaut desc pour les nombres
        }
        
        // Trier les données
        this.data.by_phone.sort((a, b) => {
            let valA = a[column];
            let valB = b[column];
            
            // Gérer les valeurs nulles
            if (valA === null || valA === undefined) valA = '';
            if (valB === null || valB === undefined) valB = '';
            
            // Conversion selon le type
            if (type === 'number') {
                valA = parseFloat(valA) || 0;
                valB = parseFloat(valB) || 0;
            } else if (type === 'date') {
                valA = new Date(valA).getTime();
                valB = new Date(valB).getTime();
            } else {
                valA = String(valA).toLowerCase();
                valB = String(valB).toLowerCase();
            }
            
            // Comparaison
            let result = 0;
            if (valA < valB) result = -1;
            if (valA > valB) result = 1;
            
            return this.sortDirection === 'asc' ? result : -result;
        });
        
        // Réinitialiser la page à 1
        this.currentPages.byPhone = 1;
        
        // Re-render le tableau
        this.renderPhoneTable(this.data.by_phone);
        
        // Mettre à jour les indicateurs visuels
        document.querySelectorAll('#phoneTable .sortable').forEach(th => {
            th.classList.remove('sorted-asc', 'sorted-desc');
        });
        
        const sortedTh = document.querySelector(`#phoneTable .sortable[data-sort="${column}"]`);
        if (sortedTh) {
            sortedTh.classList.add(`sorted-${this.sortDirection}`);
        }
    },
    
    switchTab(tabName) {
        // Désactiver tous les boutons
        document.querySelectorAll('.tab-button').forEach(btn => {
            btn.classList.remove('active');
        });
        
        // Cacher tous les contenus
        document.querySelectorAll('.tab-content').forEach(content => {
            content.classList.remove('active');
        });
        
        // Activer le bouton cliqué
        document.querySelector(`[data-tab="${tabName}"]`).classList.add('active');
        
        // Afficher le contenu correspondant
        document.getElementById(tabName).classList.add('active');
    },
    
    async search() {
        // Réinitialiser les pages
        this.currentPages = {
            byPhone: 1,
            byDeliveryCode: 1,
            recentTransactions: 1
        };
        
        const startDate = document.getElementById('start_date').value;
        const endDate = document.getElementById('end_date').value;
        const searchPhone = document.getElementById('search_phone').value;
        const deliveryCode = document.getElementById('delivery_code').value;
        
        if (!startDate || !endDate) {
            alert('Veuillez sélectionner une période');
            return;
        }
        
        this.showLoading(true);
        document.getElementById('btnExport').disabled = true;
        
        try {
            const params = new URLSearchParams({
                start_date: startDate,
                end_date: endDate,
                search_phone: searchPhone,
                delivery_code: deliveryCode
            });
            
            const response = await fetch(`/admin/timwe-diagnostic/data?${params}`);
            const data = await response.json();
            
            if (!data.success) {
                alert('Erreur: ' + (data.message || 'Impossible de charger les données'));
                return;
            }
            
            this.data = data;
            this.renderData(data);
            document.getElementById('btnExport').disabled = false;
            
        } catch (error) {
            console.error('Erreur:', error);
            alert('Erreur lors du chargement des données');
        } finally {
            this.showLoading(false);
        }
    },
    
    changePage(tabName, page) {
        this.currentPages[tabName] = page;
        
        // Re-render le tableau concerné
        if (tabName === 'byPhone') {
            this.renderPhoneTable(this.data.by_phone);
        } else if (tabName === 'byDeliveryCode') {
            this.renderDeliveryCodeTable(this.data.by_delivery_code);
        } else if (tabName === 'recentTransactions') {
            this.renderTransactionsTable(this.data.recent_transactions);
        }
        
        // Scroll vers le haut
        window.scrollTo({ top: 400, behavior: 'smooth' });
    },
    
    renderPagination(tabName, total, containerId) {
        const container = document.getElementById(containerId);
        if (!container) return;
        
        const totalPages = Math.ceil(total / this.perPage);
        
        if (totalPages <= 1) {
            container.style.display = 'none';
            return;
        }
        
        container.style.display = 'flex';
        
        const currentPage = this.currentPages[tabName];
        const startItem = ((currentPage - 1) * this.perPage) + 1;
        const endItem = Math.min(currentPage * this.perPage, total);
        
        let html = '<div class="pagination-info">';
        html += `Affichage de <strong>${startItem}</strong> à <strong>${endItem}</strong> sur <strong>${total.toLocaleString()}</strong> résultats`;
        html += '</div>';
        html += '<div class="pagination-buttons">';
        
        // Bouton Première page
        html += `<button class="pagination-btn" ${currentPage === 1 ? 'disabled' : ''} onclick="diagnosticApp.changePage('${tabName}', 1)">⏮ Première</button>`;
        
        // Bouton Précédent
        html += `<button class="pagination-btn" ${currentPage === 1 ? 'disabled' : ''} onclick="diagnosticApp.changePage('${tabName}', ${currentPage - 1})">◀ Précédent</button>`;
        
        // Pages
        const maxButtons = 5;
        let startPage = Math.max(1, currentPage - Math.floor(maxButtons / 2));
        let endPage = Math.min(totalPages, startPage + maxButtons - 1);
        
        if (endPage - startPage < maxButtons - 1) {
            startPage = Math.max(1, endPage - maxButtons + 1);
        }
        
        if (startPage > 1) {
            html += `<button class="pagination-btn" onclick="diagnosticApp.changePage('${tabName}', 1)">1</button>`;
            if (startPage > 2) html += '<span class="pagination-ellipsis">...</span>';
        }
        
        for (let i = startPage; i <= endPage; i++) {
            html += `<button class="pagination-btn ${i === currentPage ? 'active' : ''}" onclick="diagnosticApp.changePage('${tabName}', ${i})">${i}</button>`;
        }
        
        if (endPage < totalPages) {
            if (endPage < totalPages - 1) html += '<span class="pagination-ellipsis">...</span>';
            html += `<button class="pagination-btn" onclick="diagnosticApp.changePage('${tabName}', ${totalPages})">${totalPages}</button>`;
        }
        
        // Bouton Suivant
        html += `<button class="pagination-btn" ${currentPage === totalPages ? 'disabled' : ''} onclick="diagnosticApp.changePage('${tabName}', ${currentPage + 1})">Suivant ▶</button>`;
        
        // Bouton Dernière page
        html += `<button class="pagination-btn" ${currentPage === totalPages ? 'disabled' : ''} onclick="diagnosticApp.changePage('${tabName}', ${totalPages})">Dernière ⏭</button>`;
        
        html += '</div>';
        container.innerHTML = html;
    },
    
    showLoading(show) {
        const indicator = document.getElementById('loadingIndicator');
        const btnSearch = document.getElementById('btnSearch');
        
        if (show) {
            indicator.classList.add('active');
            btnSearch.disabled = true;
        } else {
            indicator.classList.remove('active');
            btnSearch.disabled = false;
        }
    },
    
    renderData(data) {
        // Afficher les sections
        document.getElementById('summarySection').classList.add('active');
        document.getElementById('diagnosticTabs').classList.add('active');
        
        // Résumé
        document.getElementById('totalTransactions').textContent = data.summary.total_transactions.toLocaleString();
        document.getElementById('uniquePhones').textContent = data.summary.unique_phones.toLocaleString();
        document.getElementById('totalBilled').textContent = data.summary.total_billed.toLocaleString();
        document.getElementById('billingRate').textContent = data.summary.billing_rate + '%';
        document.getElementById('totalRevenue').textContent = data.summary.total_revenue_tnd.toLocaleString('fr-FR', {minimumFractionDigits: 2});
        document.getElementById('deliveryCodesCount').textContent = data.summary.delivery_codes_count;
        
        // Tables
        this.renderPhoneTable(data.by_phone);
        this.renderDeliveryCodeTable(data.by_delivery_code);
        this.renderTransactionsTable(data.recent_transactions);
    },
    
    renderPhoneTable(phones) {
        const tbody = document.getElementById('phoneTableBody');
        
        if (phones.length === 0) {
            tbody.innerHTML = '<tr><td colspan="9" style="text-align: center; color: var(--muted); padding: 40px;">Aucune donnée trouvée</td></tr>';
            this.renderPagination('byPhone', 0, 'paginationByPhone');
            return;
        }
        
        // Pagination côté client
        const currentPage = this.currentPages.byPhone;
        const startIndex = (currentPage - 1) * this.perPage;
        const endIndex = startIndex + this.perPage;
        const paginatedPhones = phones.slice(startIndex, endIndex);
        
        tbody.innerHTML = paginatedPhones.map(phone => `
            <tr>
                <td><strong>${phone.phone}</strong></td>
                <td>${phone.client_name || 'N/A'}</td>
                <td><span class="badge badge-primary">${phone.total_attempts}</span></td>
                <td><span class="badge badge-success">${phone.delivered}</span></td>
                <td><span class="badge badge-warning">${phone.no_balance}</span></td>
                <td><span class="badge badge-danger">${phone.not_delivered}</span></td>
                <td><span class="badge badge-secondary">${phone.other}</span></td>
                <td><strong>${phone.total_charged_tnd.toFixed(3)} TND</strong></td>
                <td><small>${phone.subscription_date ? new Date(phone.subscription_date).toLocaleString('fr-FR') : '<span style="color: var(--muted);">N/A</span>'}</small></td>
                <td><small>${new Date(phone.last_attempt).toLocaleString('fr-FR')}</small></td>
                <td>
                    <button class="btn-details" onclick="diagnosticApp.showPhoneDetails('${phone.phone}')">
                        👁 Détails
                    </button>
                </td>
            </tr>
        `).join('');
        
        // Render pagination
        this.renderPagination('byPhone', phones.length, 'paginationByPhone');
    },
    
    renderDeliveryCodeTable(codes) {
        const tbody = document.getElementById('deliveryCodeTableBody');
        
        if (codes.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; color: var(--muted); padding: 40px;">Aucune donnée trouvée</td></tr>';
            this.renderPagination('byDeliveryCode', 0, 'paginationByDeliveryCode');
            return;
        }
        
        // Pas besoin de pagination pour delivery codes (généralement 3-5 codes max)
        // Mais on garde la logique pour la cohérence
        const currentPage = this.currentPages.byDeliveryCode;
        const startIndex = (currentPage - 1) * this.perPage;
        const endIndex = startIndex + this.perPage;
        const paginatedCodes = codes.slice(startIndex, endIndex);
        
        tbody.innerHTML = paginatedCodes.map(code => {
            const badgeClass = code.code === 'DELIVERED' ? 'badge-success' : 
                              code.code === 'NO_BALANCE' ? 'badge-warning' : 
                              code.code === 'NOT_DELIVERED' ? 'badge-danger' : 'badge-secondary';
            
            const progressClass = code.code === 'DELIVERED' ? 'success' : 
                                 code.code === 'NO_BALANCE' ? 'warning' : 
                                 code.code === 'NOT_DELIVERED' ? 'danger' : 'secondary';
            
            return `
                <tr>
                    <td><span class="badge ${badgeClass}">${code.code}</span></td>
                    <td><strong>${code.count.toLocaleString()}</strong></td>
                    <td>${code.unique_phones.toLocaleString()}</td>
                    <td><strong>${code.total_charged_tnd.toFixed(3)} TND</strong></td>
                    <td>
                        <div class="progress">
                            <div class="progress-bar badge-${progressClass}" style="width: ${code.percentage}%; background: var(--${progressClass})">
                                ${code.percentage}%
                            </div>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
        
        // Render pagination (sera cachée si codes.length <= perPage)
        this.renderPagination('byDeliveryCode', codes.length, 'paginationByDeliveryCode');
    },
    
    renderTransactionsTable(transactions) {
        const tbody = document.getElementById('transactionsTableBody');
        
        if (transactions.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; color: var(--muted); padding: 40px;">Aucune transaction trouvée</td></tr>';
            this.renderPagination('recentTransactions', 0, 'paginationRecentTransactions');
            return;
        }
        
        // Pagination côté client
        const currentPage = this.currentPages.recentTransactions;
        const startIndex = (currentPage - 1) * this.perPage;
        const endIndex = startIndex + this.perPage;
        const paginatedTransactions = transactions.slice(startIndex, endIndex);
        
        tbody.innerHTML = paginatedTransactions.map(tx => {
            const badgeClass = tx.delivery_code === 'DELIVERED' ? 'badge-success' : 
                              tx.delivery_code === 'NO_BALANCE' ? 'badge-warning' : 
                              tx.delivery_code === 'NOT_DELIVERED' ? 'badge-danger' : 'badge-secondary';
            
            return `
                <tr>
                    <td><small>${new Date(tx.date).toLocaleString('fr-FR')}</small></td>
                    <td>${tx.phone}</td>
                    <td><small>${tx.client_name || 'N/A'}</small></td>
                    <td><span class="badge ${badgeClass}">${tx.delivery_code}</span></td>
                    <td><strong>${tx.total_charged_tnd.toFixed(3)} TND</strong></td>
                    <td><span class="badge ${tx.is_billed ? 'badge-success' : 'badge-secondary'}">
                        ${tx.is_billed ? 'Facturé' : 'Non facturé'}
                    </span></td>
                </tr>
            `;
        }).join('');
        
        // Render pagination
        this.renderPagination('recentTransactions', transactions.length, 'paginationRecentTransactions');
    },
    
    exportCsv() {
        if (!this.data) return;
        
        const startDate = document.getElementById('start_date').value;
        const endDate = document.getElementById('end_date').value;
        const searchPhone = document.getElementById('search_phone').value;
        const deliveryCode = document.getElementById('delivery_code').value;
        
        const params = new URLSearchParams({
            start_date: startDate,
            end_date: endDate,
            search_phone: searchPhone,
            delivery_code: deliveryCode
        });
        
        window.location.href = `/admin/timwe-diagnostic/export?${params}`;
    },
    
    showPhoneDetails(phone) {
        if (!this.data || !this.data.recent_transactions) return;
        
        // Filtrer les transactions pour ce numéro
        const phoneTransactions = this.data.recent_transactions.filter(tx => tx.phone === phone);
        
        if (phoneTransactions.length === 0) {
            alert('Aucune transaction trouvée pour ce numéro');
            return;
        }
        
        // Construire le contenu HTML
        let html = `
            <div style="margin-bottom: 20px;">
                <h4 style="color: var(--brand-primary); margin-bottom: 8px;">📱 ${phone}</h4>
                <p style="color: var(--muted);">Total : <strong>${phoneTransactions.length}</strong> transaction(s)</p>
            </div>
            
            <div class="table-container" style="max-height: 60vh; overflow-y: auto;">
                <table style="font-size: 13px;">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Delivery Code</th>
                            <th>Montant (TND)</th>
                            <th>Statut</th>
                            <th>Transaction ID</th>
                        </tr>
                    </thead>
                    <tbody>
        `;
        
        phoneTransactions.forEach(tx => {
            const badgeClass = tx.delivery_code === 'DELIVERED' ? 'badge-success' : 
                              tx.delivery_code === 'NO_BALANCE' ? 'badge-warning' : 
                              tx.delivery_code === 'NOT_DELIVERED' ? 'badge-danger' : 'badge-secondary';
            
            html += `
                <tr>
                    <td><small>${new Date(tx.date).toLocaleString('fr-FR')}</small></td>
                    <td><span class="badge ${badgeClass}">${tx.delivery_code}</span></td>
                    <td><strong>${tx.total_charged_tnd.toFixed(3)} TND</strong></td>
                    <td>
                        <span class="badge ${tx.is_billed ? 'badge-success' : 'badge-secondary'}">
                            ${tx.is_billed ? '✓ Facturé' : '✗ Non facturé'}
                        </span>
                    </td>
                    <td><code style="font-size: 11px;">#${tx.transaction_id}</code></td>
                </tr>
            `;
        });
        
        html += `
                    </tbody>
                </table>
            </div>
        `;
        
        document.getElementById('phoneDetailsContent').innerHTML = html;
        document.getElementById('phoneDetailsModal').classList.add('active');
    },
    
    closePhoneDetails() {
        document.getElementById('phoneDetailsModal').classList.remove('active');
    }
};

// Initialiser au chargement
document.addEventListener('DOMContentLoaded', () => diagnosticApp.init());
</script>