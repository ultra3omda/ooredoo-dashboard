<script>
const diagnosticApp = {
    data: null,
    billingRateChart: null,
    funnelVolumeChart: null,
    funnelRatesChart: null,
    currentPages: {
        byPhone: 1,
        byDeliveryCode: 1,
        recentTransactions: 1
    },
    perPage: 50,
    apiPerPage: 500,
    sortColumn: 'lifetime_attempts',
    sortDirection: 'desc',
    useNewApi: true,

    /** Appel API lifetime en POST (évite 414 Request-URI Too Large avec beaucoup de numéros). */
    fetchLifetime(phoneList) {
        const csrf = document.querySelector('meta[name="csrf-token"]');
        return fetch('/admin/timwe-diagnostic/api/lifetime', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf ? csrf.getAttribute('content') : ''
            },
            body: JSON.stringify({ phones: phoneList })
        }).then(r => r.json());
    },
    
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
        
        // Limiter la date max à aujourd'hui (au cas où la page reste ouverte après minuit)
        this.initDateLimits();
        // Auto-search au chargement (7 derniers jours)
        this.search();
    },
    
    initDateLimits() {
        const today = new Date().toISOString().slice(0, 10);
        const endInput = document.getElementById('end_date');
        const startInput = document.getElementById('start_date');
        if (endInput) endInput.setAttribute('max', today);
        if (startInput) startInput.setAttribute('max', today);
    },
    
    showError(type, message, details) {
        const zone = document.getElementById('timweErrorZone');
        if (!zone) return;
        zone.style.display = 'block';
        zone.className = 'timwe-error-zone ' + (type === 'error' ? 'error' : 'warning');
        const icon = type === 'error' ? '⚠️' : 'ℹ️';
        zone.innerHTML = '<span class="timwe-error-icon">' + icon + '</span><div class="timwe-error-body">' + message + (details ? '<div class="timwe-error-details">' + details + '</div>' : '') + '</div>';
    },
    
    clearError() {
        const zone = document.getElementById('timweErrorZone');
        if (zone) { zone.style.display = 'none'; zone.innerHTML = ''; }
        if (document.getElementById('timweNoAggregatesAlert')) document.getElementById('timweNoAggregatesAlert').remove();
    },
    
    validateDates() {
        const startDate = document.getElementById('start_date').value;
        const endDate = document.getElementById('end_date').value;
        if (!startDate || !endDate) {
            this.showError('error', 'Veuillez sélectionner une date de début et une date de fin.');
            return false;
        }
        const today = new Date().toISOString().slice(0, 10);
        if (endDate > today) {
            this.showError('error', 'La date de fin ne peut pas être dans le futur.', 'Choisissez une date égale ou antérieure à aujourd\'hui.');
            return false;
        }
        if (startDate > today) {
            this.showError('error', 'La date de début ne peut pas être dans le futur.');
            return false;
        }
        if (startDate > endDate) {
            this.showError('error', 'La date de début doit être antérieure ou égale à la date de fin.');
            return false;
        }
        this.clearError();
        return true;
    },
    
    initSort() {
        const table = document.getElementById('phoneTable');
        if (!table) return;
        table.addEventListener('click', (e) => {
            const th = e.target.closest('th.sortable');
            if (!th || !th.dataset.sort) return;
            const column = th.dataset.sort;
            const type = th.dataset.type || 'string';
            this.sortTable(column, type);
        });
    },
    
    getApiSortParams() {
        if (this.sortColumn === 'lifetime_total_charged_tnd') {
            return { sort_by: 'total_charged_tnd', sort_dir: this.sortDirection };
        }
        return { sort_by: 'total_attempts', sort_dir: 'desc' };
    },
    
    sortTable(column, type) {
        if (this.sortColumn === column) {
            this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            this.sortColumn = column;
            this.sortDirection = (type === 'string') ? 'asc' : 'desc';
        }
        
        if (column === 'lifetime_total_charged_tnd') {
            this.currentPages.byPhone = 1;
            this.phonePageRequested = 1;
            this.search();
            this.updateSortIcons(column);
            return;
        }
        
        if (!this.data || !this.data.by_phone || this.data.by_phone.length === 0) return;
        
        const dir = this.sortDirection === 'asc' ? 1 : -1;
        const getSortValue = (row, col) => {
            if (col === 'lifetime_total_charged_tnd') {
                const lifetime = Number(row.lifetime_total_charged_tnd) || 0;
                const period = Number(row.total_charged_tnd) || 0;
                return Math.max(lifetime, period);
            }
            let v = row[col];
            if (v === null || v === undefined) return (type === 'number' ? 0 : '');
            return v;
        };
        this.data.by_phone.sort((a, b) => {
            let valA = getSortValue(a, column);
            let valB = getSortValue(b, column);
            if (type === 'number') {
                valA = Number(valA) || 0;
                valB = Number(valB) || 0;
            } else if (type === 'date') {
                valA = (valA && !isNaN(new Date(valA).getTime())) ? new Date(valA).getTime() : 0;
                valB = (valB && !isNaN(new Date(valB).getTime())) ? new Date(valB).getTime() : 0;
            } else {
                valA = String(valA || '').toLowerCase();
                valB = String(valB || '').toLowerCase();
            }
            let result = 0;
            if (valA < valB) result = -1;
            if (valA > valB) result = 1;
            if (result !== 0) return result * dir;
            const phoneA = String(a.phone || '').toLowerCase();
            const phoneB = String(b.phone || '').toLowerCase();
            return phoneA.localeCompare(phoneB) * dir;
        });
        
        this.currentPages.byPhone = 1;
        this.renderPhoneTable(this.data.by_phone);
        this.updateSortIcons(column);
    },
    
    updateSortIcons(column) {
        document.querySelectorAll('#phoneTable .sortable').forEach(th => {
            th.classList.remove('sorted-asc', 'sorted-desc');
        });
        const sortedTh = document.querySelector(`#phoneTable .sortable[data-sort="${column}"]`);
        if (sortedTh) sortedTh.classList.add(`sorted-${this.sortDirection}`);
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
    
    getBaseParams() {
        const startDate = document.getElementById('start_date').value;
        const endDate = document.getElementById('end_date').value;
        const searchPhone = document.getElementById('search_phone').value;
        const deliveryCode = document.getElementById('delivery_code').value;
        return { startDate, endDate, searchPhone, deliveryCode };
    },
    
    async search() {
        this.currentPages = { byPhone: 1, byDeliveryCode: 1, recentTransactions: 1 };
        if (!this.validateDates()) return;
        const { startDate, endDate, searchPhone, deliveryCode } = this.getBaseParams();
        this.clearError();
        this.showLoading(true);
        document.getElementById('btnExport').disabled = true;
        this.setProgress(0, '0%');
        this.showProgressBar(true);
        try {
            if (this.useNewApi) {
                const page = this.phonePageRequested || 1;
                const { sort_by, sort_dir } = this.getApiSortParams();
                const base = `start=${encodeURIComponent(startDate)}&end=${encodeURIComponent(endDate)}`;
                const summaryPromise = fetch(`/admin/timwe-diagnostic/api/summary?${base}&delivery_code=${encodeURIComponent(deliveryCode || '')}`).then(r => { this.setProgress(20, '20%'); return r; });
                const funnelPromise = fetch(`/admin/timwe-diagnostic/api/funnel-kpis?${base}`).then(r => { this.setProgress(35, '35%'); return r; });
                const deliveryPromise = fetch(`/admin/timwe-diagnostic/api/delivery?${base}`).then(r => { this.setProgress(50, '50%'); return r; });
                const phonesPromise = fetch(`/admin/timwe-diagnostic/api/phones?${base}&page=${page}&per_page=${this.apiPerPage}&search_phone=${encodeURIComponent(searchPhone || '')}&delivery_code=${encodeURIComponent(deliveryCode || '')}&sort_by=${encodeURIComponent(sort_by)}&sort_dir=${encodeURIComponent(sort_dir)}`).then(r => { this.setProgress(75, '75%'); return r; });
                const recentPromise = fetch(`/admin/timwe-diagnostic/api/recent?${base}&limit=100`);
                const [summaryRes, funnelRes, deliveryRes, phonesRes, recentRes] = await Promise.all([summaryPromise, funnelPromise, deliveryPromise, phonesPromise, recentPromise]);
                const summary = await summaryRes.json();
                const funnel = await funnelRes.json();
                const delivery = await deliveryRes.json();
                const phones = await phonesRes.json();
                const recent = await recentRes.json();
                if (!summary.success || !delivery.success || !phones.success || !recent.success) {
                    const err = summary.error || delivery.error || phones.error || recent.error || 'Données indisponibles';
                    if (summary.error === 'no_aggregates' || phones.error === 'no_aggregates' || delivery.error === 'no_aggregates') {
                        this.data = {
                            success: true,
                            period: { start: startDate, end: endDate },
                            total_count: 0,
                            total_phones: 0,
                            phones_page: 1,
                            phones_per_page: this.apiPerPage,
                            summary: { total_transactions: 0, unique_phones: 0, total_billed: 0, billing_rate: 0, total_revenue_tnd: 0, delivery_codes_count: 0 },
                            by_phone: [],
                            by_delivery_code: [],
                            recent_transactions: [],
                            no_aggregates_message: true
                        };
                        this.phonePageRequested = null;
                        this.renderData(this.data);
                        this.showLoading(false);
                        this.setProgress(100, '100%');
                        setTimeout(() => this.showProgressBar(false), 500);
                        document.getElementById('btnExport').disabled = false;
                        this.showError('warning',
                            'Aucune donnée agrégée disponible pour cette période.',
                            'Les statistiques pour les longues périodes doivent être calculées côté serveur. Contactez l\'administrateur pour lancer le calcul des agrégats (backfill).'
                        );
                        return;
                    }
                    this.showError('error', 'Erreur lors du chargement des données.', err || 'Données indisponibles');
                    this.showLoading(false);
                    this.setProgress(100, '100%');
                    setTimeout(() => this.showProgressBar(false), 500);
                    document.getElementById('btnExport').disabled = false;
                    return;
                }
                this.setProgress(75, '75%');
                const phoneList = (phones.by_phone || []).map(p => p.phone);
                const by_phone = (phones.by_phone || []).map(row => {
                    const sub = row.subscription_date;
                    const lastAttempt = row.last_attempt;
                    let days_inscription_to_last = null;
                    if (sub && lastAttempt) {
                        const d = Math.floor((new Date(lastAttempt) - new Date(sub)) / (1000 * 60 * 60 * 24));
                        if (d >= 0) days_inscription_to_last = d;
                    }
                    return {
                        ...row,
                        delivery_codes: row.delivery_codes || [],
                        lifetime_attempts: 0,
                        lifetime_delivered: 0,
                        lifetime_no_balance: 0,
                        lifetime_not_delivered: 0,
                        lifetime_other: 0,
                        lifetime_total_charged_tnd: 0,
                        lifetime_last_attempt: null,
                        lifetime_loaded: false,
                        days_inscription_to_last
                    };
                });
                this.data = {
                    success: true,
                    period: { start: startDate, end: endDate },
                    total_count: summary.summary?.total_transactions ?? 0,
                    total_phones: phones.total_phones ?? 0,
                    phones_page: phones.meta?.current_page ?? 1,
                    phones_per_page: phones.meta?.per_page ?? this.apiPerPage,
                    summary: summary.summary,
                    kpis: funnel.success && funnel.kpis ? funnel.kpis : null,
                    by_phone,
                    by_delivery_code: delivery.by_delivery_code || [],
                    recent_transactions: recent.recent_transactions || []
                };
                this.phonePageRequested = null;
                this.renderData(this.data);
                this.showLoading(false);
                if (phoneList.length > 0) {
                    this.fetchLifetime(phoneList)
                        .then(life => {
                            if (!life.success || !life.by_phone) return;
                            const lifetimeByPhone = life.by_phone;
                            if (!this.data || !this.data.by_phone) return;
                            this.data.by_phone.forEach(row => {
                                const l = lifetimeByPhone[row.phone] || {};
                                const lastAttempt = row.last_attempt || l.lifetime_last_attempt;
                                const sub = row.subscription_date;
                                let days_inscription_to_last = null;
                                if (sub && lastAttempt) {
                                    const d = Math.floor((new Date(lastAttempt) - new Date(sub)) / (1000 * 60 * 60 * 24));
                                    if (d >= 0) days_inscription_to_last = d;
                                }
                                row.lifetime_attempts = l.lifetime_attempts ?? 0;
                                row.lifetime_delivered = l.lifetime_delivered ?? 0;
                                row.lifetime_no_balance = l.lifetime_no_balance ?? 0;
                                row.lifetime_not_delivered = l.lifetime_not_delivered ?? 0;
                                row.lifetime_other = l.lifetime_other ?? 0;
                                row.lifetime_total_charged_tnd = l.lifetime_total_charged_tnd ?? 0;
                                row.lifetime_last_attempt = l.lifetime_last_attempt ?? null;
                                row.lifetime_loaded = true;
                                row.days_inscription_to_last = days_inscription_to_last;
                            });
                            if (this.sortColumn === 'lifetime_total_charged_tnd') {
                                const dir = this.sortDirection === 'asc' ? 1 : -1;
                                const getCharged = (row) => Math.max(Number(row.lifetime_total_charged_tnd) || 0, Number(row.total_charged_tnd) || 0);
                                this.data.by_phone.sort((a, b) => {
                                    const diff = getCharged(a) - getCharged(b);
                                    if (diff !== 0) return diff * dir;
                                    return String(a.phone || '').localeCompare(String(b.phone || '')) * dir;
                                });
                            }
                            this.setProgress(100, '100%');
                            this.renderPhoneTable(this.data.by_phone);
                            this.updateSortIcons(this.sortColumn);
                            setTimeout(() => this.showProgressBar(false), 1500);
                        })
                        .catch(() => {
                            this.setProgress(100, '100%');
                            setTimeout(() => this.showProgressBar(false), 500);
                        });
                } else {
                    this.setProgress(100, '100%');
                    setTimeout(() => this.showProgressBar(false), 500);
                }
                document.getElementById('btnExport').disabled = false;
                return;
            } else {
                const params = new URLSearchParams({
                    start_date: startDate,
                    end_date: endDate,
                    search_phone: searchPhone,
                    delivery_code: deliveryCode,
                    page: String(this.phonePageRequested || 1),
                    per_page: String(this.apiPerPage)
                });
                const response = await fetch(`/admin/timwe-diagnostic/data?${params}`);
                const data = await response.json();
                if (!data.success) {
                    alert('Erreur: ' + (data.message || 'Impossible de charger les données'));
                    return;
                }
                this.data = data;
                this.phonePageRequested = null;
                this.data.by_phone = (this.data.by_phone || []).map(row => ({ ...row, lifetime_loaded: true }));
                this.setProgress(100, '100%');
            }
            this.renderData(this.data);
            document.getElementById('btnExport').disabled = false;
        } catch (error) {
            console.error('Erreur:', error);
            this.showError('error', 'Erreur lors du chargement des données.', error && error.message ? error.message : 'Veuillez réessayer.');
            document.getElementById('btnExport').disabled = false;
        } finally {
            this.showLoading(false);
            this.showProgressBar(false);
        }
    },
    
    async changePage(tabName, page) {
        if (tabName === 'byPhone' && this.useNewApi && this.data && this.data.total_phones != null) {
            const phonesPerPageApi = this.data.phones_per_page || this.apiPerPage;
            const totalPagesApi = Math.ceil(this.data.total_phones / phonesPerPageApi) || 1;
            const startItemIndex = (page - 1) * this.perPage;
            const requestedApiPage = Math.min(Math.floor(startItemIndex / phonesPerPageApi) + 1, totalPagesApi);
            const currentApiPage = this.data.phones_page || 1;
            if (requestedApiPage !== currentApiPage) {
                this.currentPages.byPhone = page;
                this.phonePageRequested = requestedApiPage;
                this.showLoading(true);
                try {
                    const { startDate, endDate, searchPhone, deliveryCode } = this.getBaseParams();
                    const { sort_by, sort_dir } = this.getApiSortParams();
                    const base = `start=${encodeURIComponent(startDate)}&end=${encodeURIComponent(endDate)}`;
                    const phonesRes = await fetch(`/admin/timwe-diagnostic/api/phones?${base}&page=${requestedApiPage}&per_page=${this.apiPerPage}&search_phone=${encodeURIComponent(searchPhone || '')}&delivery_code=${encodeURIComponent(deliveryCode || '')}&sort_by=${encodeURIComponent(sort_by)}&sort_dir=${encodeURIComponent(sort_dir)}`);
                    const phones = await phonesRes.json();
                    if (!phones.success) return;
                    const phoneList = (phones.by_phone || []).map(p => p.phone);
                    const by_phone = (phones.by_phone || []).map(row => {
                        const sub = row.subscription_date;
                        const lastAttempt = row.last_attempt;
                        let days_inscription_to_last = null;
                        if (sub && lastAttempt) {
                            const d = Math.floor((new Date(lastAttempt) - new Date(sub)) / (1000 * 60 * 60 * 24));
                            if (d >= 0) days_inscription_to_last = d;
                        }
                        return {
                            ...row,
                            delivery_codes: row.delivery_codes || [],
                            lifetime_attempts: 0,
                            lifetime_delivered: 0,
                            lifetime_no_balance: 0,
                            lifetime_not_delivered: 0,
                            lifetime_other: 0,
                            lifetime_total_charged_tnd: 0,
                            lifetime_last_attempt: null,
                            lifetime_loaded: false,
                            days_inscription_to_last
                        };
                    });
                    this.data.by_phone = by_phone;
                    this.data.phones_page = phones.meta?.current_page ?? requestedApiPage;
                    this.renderPhoneTable(this.data.by_phone);
                    if (phoneList.length > 0) {
                        this.fetchLifetime(phoneList)
                            .then(life => {
                                if (!life.success || !life.by_phone || !this.data || !this.data.by_phone) return;
                                const lifetimeByPhone = life.by_phone;
                                this.data.by_phone.forEach(row => {
                                    const l = lifetimeByPhone[row.phone] || {};
                                    const lastAttempt = row.last_attempt || l.lifetime_last_attempt;
                                    const sub = row.subscription_date;
                                    let days_inscription_to_last = null;
                                    if (sub && lastAttempt) {
                                        const d = Math.floor((new Date(lastAttempt) - new Date(sub)) / (1000 * 60 * 60 * 24));
                                        if (d >= 0) days_inscription_to_last = d;
                                    }
                                    row.lifetime_attempts = l.lifetime_attempts ?? 0;
                                    row.lifetime_delivered = l.lifetime_delivered ?? 0;
                                    row.lifetime_no_balance = l.lifetime_no_balance ?? 0;
                                    row.lifetime_not_delivered = l.lifetime_not_delivered ?? 0;
                                    row.lifetime_other = l.lifetime_other ?? 0;
                                    row.lifetime_total_charged_tnd = l.lifetime_total_charged_tnd ?? 0;
                                    row.lifetime_last_attempt = l.lifetime_last_attempt ?? null;
                                    row.lifetime_loaded = true;
                                    row.days_inscription_to_last = days_inscription_to_last;
                                });
                                if (this.sortColumn === 'lifetime_total_charged_tnd') {
                                    const dir = this.sortDirection === 'asc' ? 1 : -1;
                                    const getCharged = (row) => Math.max(Number(row.lifetime_total_charged_tnd) || 0, Number(row.total_charged_tnd) || 0);
                                    this.data.by_phone.sort((a, b) => {
                                        const diff = getCharged(a) - getCharged(b);
                                        if (diff !== 0) return diff * dir;
                                        return String(a.phone || '').localeCompare(String(b.phone || '')) * dir;
                                    });
                                }
                                this.renderPhoneTable(this.data.by_phone);
                                this.updateSortIcons(this.sortColumn);
                            });
                    }
                } finally {
                    this.showLoading(false);
                }
                return;
            }
        }
        if (tabName === 'byPhone' && this.data && this.data.total_phones != null) {
            const perPage = this.perPage;
            const totalPhones = this.data.total_phones;
            const phonesPerPageApi = this.data.phones_per_page || 1000;
            const batchStart = ((this.data.phones_page || 1) - 1) * phonesPerPageApi;
            const startIndex = (page - 1) * perPage;
            const endIndex = page * perPage;
            if (!this.useNewApi && (endIndex > batchStart + (this.data.by_phone || []).length || startIndex < batchStart)) {
                const requiredApiPage = Math.ceil(endIndex / phonesPerPageApi);
                if (requiredApiPage !== (this.data.phones_page || 1)) {
                    this.phonePageRequested = requiredApiPage;
                    this.currentPages.byPhone = page;
                    return this.search();
                }
            }
        }
        this.currentPages[tabName] = page;
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
    
    setProgress(percent, label) {
        const container = document.getElementById('progressBarContainer');
        const fill = document.getElementById('progressBarFill');
        const lbl = document.getElementById('progressBarLabel');
        if (!container || !fill || !lbl) return;
        const p = Math.min(100, Math.max(0, percent));
        fill.style.width = p + '%';
        lbl.textContent = label != null ? label : (p + '%');
        if (p > 0) container.style.display = 'block';
    },
    showProgressBar(show) {
        const container = document.getElementById('progressBarContainer');
        if (container) container.style.display = show ? 'block' : 'none';
    },
    showLoading(show) {
        const indicator = document.getElementById('loadingIndicator');
        const btnSearch = document.getElementById('btnSearch');
        const summarySection = document.getElementById('summarySection');
        const diagnosticTabs = document.getElementById('diagnosticTabs');
        
        if (show) {
            indicator.classList.add('active');
            btnSearch.disabled = true;
            summarySection.classList.remove('hidden-until-data');
            summarySection.classList.add('visible', 'skeleton-mode');
            diagnosticTabs.classList.remove('hidden-until-data');
            diagnosticTabs.classList.add('visible');
            this.renderSkeletonTables();
        } else {
            indicator.classList.remove('active');
            btnSearch.disabled = false;
        }
    },
    
    renderSkeletonTables() {
        const skeletonRow = (cols, id) => {
            const cells = Array(cols).fill('<td><div class="skeleton-cell w-60"></div></td>').join('');
            return `<tr class="skeleton-row">${cells}</tr>`;
        };
        const phoneBody = document.getElementById('phoneTableBody');
        const deliveryBody = document.getElementById('deliveryCodeTableBody');
        const transactionsBody = document.getElementById('transactionsTableBody');
        if (phoneBody) {
            const rows = Array(8).fill(0).map(() => skeletonRow(13, 'phone'));
            phoneBody.innerHTML = rows.join('');
        }
        if (deliveryBody) {
            const rows = Array(4).fill(0).map(() => skeletonRow(5, 'delivery'));
            deliveryBody.innerHTML = rows.join('');
        }
        if (transactionsBody) {
            const rows = Array(5).fill(0).map(() => skeletonRow(6, 'recent'));
            transactionsBody.innerHTML = rows.join('');
        }
        document.getElementById('paginationByPhone').style.display = 'none';
        document.getElementById('paginationByDeliveryCode').style.display = 'none';
        document.getElementById('paginationRecentTransactions').style.display = 'none';
    },
    
    renderData(data) {
        const summarySection = document.getElementById('summarySection');
        summarySection.classList.remove('skeleton-mode');
        summarySection.classList.add('visible');
        document.getElementById('diagnosticTabs').classList.add('visible');
        
        // Indicateur cache (comme Timwe/Ooredoo)
        const cacheBadge = document.getElementById('cacheBadge');
        if (cacheBadge) {
            if (data.cached && data.cached_at) {
                cacheBadge.style.display = 'inline-flex';
                const d = new Date(data.cached_at);
                cacheBadge.textContent = '📦 Données en cache (actualisé à ' + d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }) + ')';
                cacheBadge.title = 'Données servies depuis le cache Redis - ' + d.toLocaleString('fr-FR');
            } else {
                cacheBadge.style.display = 'none';
            }
        }
        
        // KPI Cards (funnel-kpis) + graphiques funnel
        if (data.kpis) {
            const k = data.kpis;
            const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
            set('kpiTotalAttempts', (k.total_attempts ?? 0).toLocaleString());
            set('kpiUniquePhones', (k.unique_phones ?? 0).toLocaleString());
            set('kpiTotalRevenue', (k.total_revenue_tnd ?? 0).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
            set('kpiBigDealRevenue', (k.bigdeal_revenue_tnd ?? 0).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
            set('kpiBillingRateGlobal', (k.billing_rate_global ?? 0).toFixed(2) + ' %');
            set('kpiTotalDelivered', (k.total_delivered ?? 0).toLocaleString());
            set('kpiDeliveryRate', (k.delivery_rate ?? 0).toFixed(2) + ' %');
            set('kpiTotalNotDelivered', (k.total_not_delivered ?? 0).toLocaleString());
            set('kpiTechnicalLossRate', (k.technical_loss_rate ?? 0).toFixed(2) + ' %');
            set('kpiDeliveredBilled', (k.total_success ?? 0).toLocaleString());
            set('kpiDeliveredNonBilled', (k.delivered_non_billed ?? 0).toLocaleString());
            set('kpiBillingRateOnDelivered', (k.billing_rate_on_delivered ?? 0).toFixed(2) + ' %');
            set('kpiTotalNoBalance', (k.total_no_balance ?? 0).toLocaleString());
            set('kpiNoBalanceRatio', (k.no_balance_ratio ?? 0).toFixed(2) + ' %');
            this.renderFunnelCharts(k);
        } else {
            const ids = ['kpiTotalAttempts','kpiUniquePhones','kpiTotalRevenue','kpiBigDealRevenue','kpiBillingRateGlobal','kpiTotalDelivered','kpiDeliveryRate','kpiTotalNotDelivered','kpiTechnicalLossRate','kpiDeliveredBilled','kpiDeliveredNonBilled','kpiBillingRateOnDelivered','kpiTotalNoBalance','kpiNoBalanceRatio'];
            ids.forEach(id => { const el = document.getElementById(id); if (el) el.textContent = '-'; });
            this.destroyFunnelCharts();
        }
        const funnelSection = document.getElementById('funnelChartsSection');
        if (funnelSection) {
            if (data.kpis && !data.no_aggregates_message) { funnelSection.classList.add('visible'); funnelSection.style.display = 'grid'; }
            else { funnelSection.classList.remove('visible'); funnelSection.style.display = 'none'; }
        }
        
        // Tables
        this.renderPhoneTable(data.by_phone || []);
        this.renderDeliveryCodeTable(data.by_delivery_code || []);
        this.renderTransactionsTable(data.recent_transactions || []);
        this.updateSortIcons(this.sortColumn);
        // Graphique taux de facturation (période courante)
        const chartSection = document.getElementById('billingRateChartSection');
        if (chartSection) {
            if (data.period && data.period.start && data.period.end && !data.no_aggregates_message) {
                chartSection.classList.add('visible');
                this.loadBillingRateChart(data.period.start, data.period.end);
            } else {
                chartSection.classList.remove('visible');
                this.destroyBillingRateChart();
            }
        }
    },
    
    loadBillingRateChart(start, end) {
        const base = `start=${encodeURIComponent(start)}&end=${encodeURIComponent(end)}`;
        fetch(`/admin/timwe-diagnostic/api/billing-rate-evolution?${base}`)
            .then(r => r.json())
            .then(res => {
                if (res.success && res.by_date && res.by_date.length > 0) {
                    this.renderBillingRateChart(res.by_date);
                } else {
                    this.destroyBillingRateChart();
                }
            })
            .catch(() => this.destroyBillingRateChart());
    },
    
    renderBillingRateChart(byDate) {
        const canvas = document.getElementById('billingRateChartCanvas');
        if (!canvas || typeof Chart === 'undefined') return;
        this.destroyBillingRateChart();
        const labels = byDate.map(d => {
            const dt = new Date(d.date + 'T12:00:00');
            return dt.toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: '2-digit' });
        });
        const successData = byDate.map(d => (typeof d.total_billed === 'number' ? d.total_billed : 0));
        const attemptsData = byDate.map(d => (typeof d.total_attempts === 'number' ? d.total_attempts : 0));
        const sortedSuccess = [...successData].filter(v => typeof v === 'number' && !isNaN(v)).sort((a, b) => a - b);
        const medianSuccess = sortedSuccess.length === 0 ? 0 : sortedSuccess.length % 2 === 1
            ? sortedSuccess[(sortedSuccess.length - 1) / 2]
            : (sortedSuccess[sortedSuccess.length / 2 - 1] + sortedSuccess[sortedSuccess.length / 2]) / 2;
        const medianSuccessLineData = labels.map(() => Math.round(medianSuccess));
        const maxCount = Math.max(...successData, ...attemptsData, 1);
        const ctx = canvas.getContext('2d');
        this.billingRateChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels,
                datasets: [
                    {
                        label: 'Nombre de facturations success',
                        data: successData,
                        borderColor: '#8B5CF6',
                        backgroundColor: 'rgba(139, 92, 246, 0.1)',
                        fill: true,
                        tension: 0.2,
                        pointRadius: successData.length <= 31 ? 4 : 0,
                        pointHoverRadius: 6,
                        yAxisID: 'yCounts'
                    },
                    {
                        label: 'Médiane facturations (' + Math.round(medianSuccess).toLocaleString('fr-FR') + ')',
                        data: medianSuccessLineData,
                        borderColor: '#f59e0b',
                        borderDash: [6, 4],
                        borderWidth: 2,
                        fill: false,
                        pointRadius: 0,
                        pointHoverRadius: 0,
                        yAxisID: 'yCounts'
                    },
                    {
                        label: 'Nombre de tentatives',
                        data: attemptsData,
                        borderColor: '#0d9488',
                        backgroundColor: 'rgba(13, 148, 136, 0.08)',
                        fill: true,
                        tension: 0.2,
                        pointRadius: attemptsData.length <= 31 ? 4 : 0,
                        pointHoverRadius: 6,
                        yAxisID: 'yCounts'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { maxRotation: 45, maxTicksLimit: 15 }
                    },
                    yCounts: {
                        type: 'linear',
                        position: 'right',
                        min: 0,
                        max: Math.ceil(maxCount * 1.1) || 1,
                        grid: { color: 'rgba(0,0,0,0.06)' },
                        ticks: { callback: v => Number(v).toLocaleString('fr-FR') }
                    }
                }
            }
        });
    },
    
    destroyBillingRateChart() {
        if (this.billingRateChart) {
            this.billingRateChart.destroy();
            this.billingRateChart = null;
        }
    },
    
    renderFunnelCharts(kpis) {
        if (typeof Chart === 'undefined') return;
        this.destroyFunnelCharts();
        const volCanvas = document.getElementById('funnelVolumeChartCanvas');
        const ratesCanvas = document.getElementById('funnelRatesChartCanvas');
        if (!volCanvas || !ratesCanvas) return;
        const s = kpis.total_success ?? 0;
        const notSuccess = kpis.delivered_non_billed ?? 0;
        const nb = kpis.total_no_balance ?? 0;
        const nd = kpis.total_not_delivered ?? 0;
        this.funnelVolumeChart = new Chart(volCanvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels: ['Success', 'Not success', 'No Balance', 'Not Delivered'],
                datasets: [{
                    label: 'Volume',
                    data: [s, notSuccess, nb, nd],
                    backgroundColor: ['#10b981', '#f59e0b', '#eab308', '#ef4444'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false }, ticks: { maxRotation: 25 } },
                    y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.06)' } }
                }
            }
        });
        const dr = (kpis.delivery_rate ?? 0).toFixed(2);
        const bd = (kpis.billing_rate_on_delivered ?? 0).toFixed(2);
        const bg = (kpis.billing_rate_global ?? 0).toFixed(2);
        this.funnelRatesChart = new Chart(ratesCanvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels: ['Delivery Rate', 'Billing sur Delivered', 'Billing Global'],
                datasets: [{
                    label: '%',
                    data: [parseFloat(dr), parseFloat(bd), parseFloat(bg)],
                    backgroundColor: ['#3b82f6', '#10b981', '#6B46C1'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false }, ticks: { maxRotation: 25 } },
                    y: { beginAtZero: true, max: 100, grid: { color: 'rgba(0,0,0,0.06)' }, ticks: { callback: v => v + ' %' } }
                }
            }
        });
    },
    
    destroyFunnelCharts() {
        if (this.funnelVolumeChart) { this.funnelVolumeChart.destroy(); this.funnelVolumeChart = null; }
        if (this.funnelRatesChart) { this.funnelRatesChart.destroy(); this.funnelRatesChart = null; }
    },
    
    renderPhoneTable(phones) {
        const tbody = document.getElementById('phoneTableBody');
        const totalPhones = (this.data && this.data.total_phones != null) ? this.data.total_phones : (phones ? phones.length : 0);
        
        if (!phones || phones.length === 0) {
            tbody.innerHTML = '<tr><td colspan="13" style="text-align: center; color: var(--muted); padding: 40px;">Aucune donnée trouvée</td></tr>';
            this.renderPagination('byPhone', totalPhones, 'paginationByPhone');
            return;
        }
        
        const currentPage = this.currentPages.byPhone;
        const startIndex = (currentPage - 1) * this.perPage;
        const endIndex = currentPage * this.perPage;
        const batchStart = (this.data && this.data.phones_per_page) ? ((this.data.phones_page || 1) - 1) * this.data.phones_per_page : 0;
        let paginatedPhones;
        if (this.data && this.data.total_phones != null && this.data.phones_per_page && (startIndex >= batchStart && endIndex <= batchStart + phones.length)) {
            paginatedPhones = phones.slice(startIndex - batchStart, endIndex - batchStart);
        } else {
            paginatedPhones = phones.slice(startIndex, endIndex);
        }
        
        tbody.innerHTML = paginatedPhones.map(phone => {
            const lifetimeLoaded = phone.lifetime_loaded === true;
            const lifetime = phone.lifetime_attempts ?? 0;
            const periodAttempts = phone.total_attempts ?? 0;
            const lifetimeDelivered = phone.lifetime_delivered ?? 0;
            const periodDelivered = phone.delivered ?? 0;
            const lifetimeNoBalance = phone.lifetime_no_balance ?? 0;
            const periodNoBalance = phone.no_balance ?? 0;
            const lifetimeNotDelivered = phone.lifetime_not_delivered ?? 0;
            const periodNotDelivered = phone.not_delivered ?? 0;
            const lifetimeOther = phone.lifetime_other ?? 0;
            const periodOther = phone.other ?? 0;
            const lifetimeCharged = (phone.lifetime_total_charged_tnd ?? 0).toFixed(3);
            const periodCharged = (phone.total_charged_tnd ?? 0).toFixed(3);
            const deliveredVal = lifetimeLoaded ? (lifetimeDelivered || periodDelivered) : '—';
            const noBalanceVal = lifetimeLoaded ? (lifetimeNoBalance || periodNoBalance) : '—';
            const notDeliveredVal = lifetimeLoaded ? (lifetimeNotDelivered || periodNotDelivered) : '—';
            const otherVal = lifetimeLoaded ? (lifetimeOther || periodOther) : '—';
            const chargedVal = lifetimeLoaded ? (parseFloat(lifetimeCharged) > 0 ? lifetimeCharged : periodCharged) : '—';
            const lifetimeCell = lifetimeLoaded ? `<span class="badge badge-info" title="Tentatives toutes périodes">${lifetime || periodAttempts}</span>` : '<span class="lifetime-loading">Chargement…</span>';
            const daysLabel = (phone.days_inscription_to_last !== undefined && phone.days_inscription_to_last !== null)
                ? String(phone.days_inscription_to_last) : '—';
            return `
            <tr>
                <td><strong>${phone.phone}</strong></td>
                <td>${phone.client_name || 'N/A'}</td>
                <td><span class="badge badge-primary" title="Tentatives sur la période">${periodAttempts}</span></td>
                <td>${lifetimeCell}</td>
                <td><strong>${daysLabel}</strong></td>
                <td><span class="badge badge-success" title="DELIVERED (période / lifetime)">${deliveredVal}</span></td>
                <td><span class="badge badge-warning" title="NO_BALANCE">${noBalanceVal}</span></td>
                <td><span class="badge badge-danger" title="NOT_DELIVERED">${notDeliveredVal}</span></td>
                <td><span class="badge badge-secondary" title="Autres">${otherVal}</span></td>
                <td><strong>${chargedVal} TND</strong></td>
                <td><small>${phone.subscription_date ? new Date(phone.subscription_date).toLocaleString('fr-FR') : '<span style="color: var(--muted);">N/A</span>'}</small></td>
                <td><small>${phone.last_attempt ? new Date(phone.last_attempt).toLocaleString('fr-FR') : '—'}</small></td>
                <td>
                    <button class="btn-details" onclick="diagnosticApp.showPhoneDetails('${phone.phone.replace(/'/g, "\\'")}')">
                        👁 Détails
                    </button>
                </td>
            </tr>
        `;
        }).join('');
        
        this.renderPagination('byPhone', totalPhones, 'paginationByPhone');
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
                    <td>${(code.unique_phones ?? 0).toLocaleString()}</td>
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
        if (!this.data || !this.data.by_phone) return;
        
        const phoneEntry = this.data.by_phone.find(p => p.phone === phone);
        if (!phoneEntry) {
            alert('Numéro non trouvé');
            return;
        }
        
        const l = phoneEntry;
        const lifetimeAttempts = l.lifetime_attempts ?? 0;
        const lifetimeDelivered = l.lifetime_delivered ?? 0;
        const lifetimeNoBalance = l.lifetime_no_balance ?? 0;
        const lifetimeNotDelivered = l.lifetime_not_delivered ?? 0;
        const lifetimeOther = l.lifetime_other ?? 0;
        const lifetimeCharged = (l.lifetime_total_charged_tnd ?? 0).toFixed(3);
        const daysInscriptionToLast = (l.days_inscription_to_last !== undefined && l.days_inscription_to_last !== null)
            ? l.days_inscription_to_last : (() => {
                const sub = l.subscription_date ? new Date(l.subscription_date) : null;
                const last = (l.lifetime_last_attempt || l.last_attempt) ? new Date(l.lifetime_last_attempt || l.last_attempt) : null;
                if (sub && last && !isNaN(sub.getTime()) && !isNaN(last.getTime())) {
                    const d = Math.floor((last - sub) / (1000 * 60 * 60 * 24));
                    return d >= 0 ? d : null;
                }
                return null;
            })();
        const daysLabel = daysInscriptionToLast !== null ? daysInscriptionToLast + ' jour(s)' : '—';
        
        let html = `
            <div style="margin-bottom: 20px;">
                <h4 style="color: var(--brand-primary); margin-bottom: 12px;">📱 ${phone}</h4>
                ${phoneEntry.client_name ? `<p style="color: var(--muted); margin-bottom: 12px;">${phoneEntry.client_name}</p>` : ''}
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;">
                    <div id="phoneDetailsLifetimeCard" class="card" style="padding: 12px; border: 1px solid var(--border); border-radius: 8px;">
                        <strong style="color: var(--muted); font-size: 12px;">Tentatives lifetime</strong>
                        <p style="font-size: 20px; font-weight: bold; margin: 4px 0;">${lifetimeAttempts}</p>
                        <div style="font-size: 12px; color: var(--muted);">
                            DELIVERED: <strong>${lifetimeDelivered}</strong> · NO_BALANCE: <strong>${lifetimeNoBalance}</strong> · NOT_DELIVERED: <strong>${lifetimeNotDelivered}</strong> · Autres: <strong>${lifetimeOther}</strong>
                        </div>
                        <div style="font-size: 12px; margin-top: 4px;">Facturé lifetime: <strong>${lifetimeCharged} TND</strong></div>
                        <div style="font-size: 12px; margin-top: 4px;">Nb jours (inscription → dernière tentative): <strong>${daysLabel}</strong></div>
                    </div>
                    <div class="card" style="padding: 12px; border: 1px solid var(--border); border-radius: 8px;">
                        <strong style="color: var(--muted); font-size: 12px;">Tentatives (période)</strong>
                        <p style="font-size: 20px; font-weight: bold; margin: 4px 0;">${l.total_attempts}</p>
                        <div style="font-size: 12px; color: var(--muted);">
                            DELIVERED: <strong>${l.delivered}</strong> · NO_BALANCE: <strong>${l.no_balance}</strong> · NOT_DELIVERED: <strong>${l.not_delivered}</strong> · Autres: <strong>${l.other}</strong>
                        </div>
                        <div style="font-size: 12px; margin-top: 4px;">Facturé (période): <strong>${(l.total_charged_tnd || 0).toFixed(3)} TND</strong></div>
                    </div>
                </div>
                
                <p style="color: var(--muted); margin-bottom: 8px;">Détail des transactions <strong>lifetime</strong> :</p>
            </div>
            
            <div class="table-container" style="max-height: 50vh; overflow-y: auto;">
                <table style="font-size: 13px;">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Delivery Code</th>
                            <th>Montant (TND)</th>
                            <th>Offre (Pricepoint)</th>
                            <th>Statut</th>
                            <th>Transaction ID</th>
                        </tr>
                    </thead>
                    <tbody id="phoneDetailsLifetimeBody">
                        <tr><td colspan="6" style="text-align: center; color: var(--muted); padding: 24px;">Chargement des transactions lifetime...</td></tr>
                    </tbody>
                </table>
            </div>
        `;
        
        document.getElementById('phoneDetailsContent').innerHTML = html;
        document.getElementById('phoneDetailsModal').classList.add('active');
        
        const encodedPhone = encodeURIComponent(phone);
        fetch(`/admin/timwe-diagnostic/phone/${encodedPhone}/transactions`, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(res => res.json())
            .then(data => {
                const tbody = document.getElementById('phoneDetailsLifetimeBody');
                const lifetimeCard = document.getElementById('phoneDetailsLifetimeCard');
                if (!tbody) return;
                if (!data.success || !data.transactions) {
                    tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; color: var(--danger); padding: 24px;">Erreur lors du chargement</td></tr>';
                    return;
                }
                const transactions = data.transactions;
                if (transactions.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; color: var(--muted); padding: 24px;">Aucune transaction lifetime</td></tr>';
                    if (lifetimeCard) {
                        lifetimeCard.innerHTML = `
                            <strong style="color: var(--muted); font-size: 12px;">Tentatives lifetime</strong>
                            <p style="font-size: 20px; font-weight: bold; margin: 4px 0;">0</p>
                            <div style="font-size: 12px; color: var(--muted);">DELIVERED: <strong>0</strong> · NO_BALANCE: <strong>0</strong> · NOT_DELIVERED: <strong>0</strong> · Autres: <strong>0</strong></div>
                            <div style="font-size: 12px; margin-top: 4px;">Facturé lifetime: <strong>0.000 TND</strong></div>
                            <div style="font-size: 12px; margin-top: 4px;">Nb jours: <strong>—</strong></div>
                        `;
                    }
                    return;
                }
                const ltAttempts = transactions.length;
                const ltDelivered = transactions.filter(t => t.delivery_code === 'DELIVERED').length;
                const ltNoBalance = transactions.filter(t => t.delivery_code === 'NO_BALANCE').length;
                const ltNotDelivered = transactions.filter(t => t.delivery_code === 'NOT_DELIVERED').length;
                const ltOther = ltAttempts - ltDelivered - ltNoBalance - ltNotDelivered;
                const ltCharged = transactions.reduce((s, t) => s + (parseFloat(t.total_charged_tnd) || 0), 0);
                const lastTxDate = transactions.length ? transactions.reduce((max, t) => t.date > max ? t.date : max, transactions[0].date) : null;
                let daysLabel = '—';
                if (l.subscription_date && lastTxDate) {
                    const d = Math.floor((new Date(lastTxDate) - new Date(l.subscription_date)) / (1000 * 60 * 60 * 24));
                    if (d >= 0) daysLabel = d + ' jour(s)';
                }
                if (lifetimeCard) {
                    lifetimeCard.innerHTML = `
                        <strong style="color: var(--muted); font-size: 12px;">Tentatives lifetime</strong>
                        <p style="font-size: 20px; font-weight: bold; margin: 4px 0;">${ltAttempts}</p>
                        <div style="font-size: 12px; color: var(--muted);">
                            DELIVERED: <strong>${ltDelivered}</strong> · NO_BALANCE: <strong>${ltNoBalance}</strong> · NOT_DELIVERED: <strong>${ltNotDelivered}</strong> · Autres: <strong>${ltOther}</strong>
                        </div>
                        <div style="font-size: 12px; margin-top: 4px;">Facturé lifetime: <strong>${ltCharged.toFixed(3)} TND</strong></div>
                        <div style="font-size: 12px; margin-top: 4px;">Nb jours (inscription → dernière tentative): <strong>${daysLabel}</strong></div>
                    `;
                }
                tbody.innerHTML = transactions.map(tx => {
                    const badgeClass = tx.delivery_code === 'DELIVERED' ? 'badge-success' : 
                                      tx.delivery_code === 'NO_BALANCE' ? 'badge-warning' : 
                                      tx.delivery_code === 'NOT_DELIVERED' ? 'badge-danger' : 'badge-secondary';
                    const offerLabel = (tx.pricepoint_id != null && tx.pricepoint_id !== '') ? String(tx.pricepoint_id) : (tx.product_id != null && tx.product_id !== '') ? 'prod.' + tx.product_id : '–';
                    return `
                    <tr>
                        <td><small>${new Date(tx.date).toLocaleString('fr-FR')}</small></td>
                        <td><span class="badge ${badgeClass}">${tx.delivery_code}</span></td>
                        <td><strong>${(tx.total_charged_tnd || 0).toFixed(3)} TND</strong></td>
                        <td><code style="font-size: 11px;" title="ID offre (différencie 0.3 TND vs 3.0 TND)">${offerLabel}</code></td>
                        <td>
                            <span class="badge ${tx.is_billed ? 'badge-success' : 'badge-secondary'}">
                                ${tx.is_billed ? '✓ Facturé' : '✗ Non facturé'}
                            </span>
                        </td>
                        <td><code style="font-size: 11px;">#${tx.transaction_id}</code></td>
                    </tr>
                `;
                }).join('');
                const periodLabel = document.querySelector('#phoneDetailsContent p[style*="Détail des transactions"]');
                if (periodLabel) periodLabel.innerHTML = 'Détail des transactions <strong>lifetime</strong> : <strong>' + transactions.length + '</strong> transaction(s)';
            })
            .catch(err => {
                const tbody = document.getElementById('phoneDetailsLifetimeBody');
                if (tbody) tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; color: var(--danger); padding: 24px;">Erreur réseau</td></tr>';
            });
    },
    
    closePhoneDetails() {
        document.getElementById('phoneDetailsModal').classList.remove('active');
    }
};

// Initialiser au chargement
document.addEventListener('DOMContentLoaded', () => diagnosticApp.init());
</script>