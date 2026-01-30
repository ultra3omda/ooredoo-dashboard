<script>
const diagnosticApp = {
    data: null,
    
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
        
        // Auto-search au chargement
        this.search();
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
            return;
        }
        
        tbody.innerHTML = phones.map(phone => `
            <tr>
                <td><strong>${phone.phone}</strong></td>
                <td>${phone.client_name || 'N/A'}</td>
                <td><span class="badge badge-primary">${phone.total_attempts}</span></td>
                <td><span class="badge badge-success">${phone.delivered}</span></td>
                <td><span class="badge badge-warning">${phone.no_balance}</span></td>
                <td><span class="badge badge-danger">${phone.not_delivered}</span></td>
                <td><span class="badge badge-secondary">${phone.other}</span></td>
                <td><strong>${phone.total_charged_tnd.toFixed(3)} TND</strong></td>
                <td><small>${new Date(phone.last_attempt).toLocaleString('fr-FR')}</small></td>
            </tr>
        `).join('');
    },
    
    renderDeliveryCodeTable(codes) {
        const tbody = document.getElementById('deliveryCodeTableBody');
        
        if (codes.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; color: var(--muted); padding: 40px;">Aucune donnée trouvée</td></tr>';
            return;
        }
        
        tbody.innerHTML = codes.map(code => {
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
    },
    
    renderTransactionsTable(transactions) {
        const tbody = document.getElementById('transactionsTableBody');
        
        if (transactions.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; color: var(--muted); padding: 40px;">Aucune transaction trouvée</td></tr>';
            return;
        }
        
        tbody.innerHTML = transactions.map(tx => {
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
    }
};

// Initialiser au chargement
document.addEventListener('DOMContentLoaded', () => diagnosticApp.init());
</script>