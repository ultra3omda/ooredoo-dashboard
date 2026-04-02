{{-- Vue d'ensemble (aligné style Timwe/Ooredoo) --}}
<div class="grid">
    <div class="card chart-card full-width">
        <div class="chart-title">
            📊 Vue d'ensemble Multi-Axes
            <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Revenus, abonnés actifs et CA BigDeal sur la période">ⓘ</span>
            <div style="float: right;">
                <select id="eklektik-operator-select" class="enhanced-select" style="font-size: 14px;">
                    <option value="ALL">Tous les opérateurs</option>
                    <option value="Orange">Orange</option>
                    <option value="TT">TT</option>
                    <option value="Ooredoo">Ooredoo</option>
                    <option value="Taraji">Taraji</option>
                </select>
            </div>
        </div>
        <div class="chart-container">
            <canvas id="eklektik-overview-chart"></canvas>
        </div>
    </div>
</div>

<div class="grid">
    <div class="card chart-card" style="grid-column: span 6;">
        <div class="chart-title">💰 Revenus par Opérateur + CA BigDeal <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Évolution des revenus par opérateur">ⓘ</span></div>
        <div class="chart-container">
            <canvas id="eklektik-revenue-evolution-chart"></canvas>
        </div>
    </div>
    <div class="card chart-card" style="grid-column: span 6;">
        <div class="chart-title">📱 Répartition par Opérateur <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Part des revenus par opérateur">ⓘ</span></div>
        <div class="chart-container">
            <canvas id="eklektik-operators-distribution-chart"></canvas>
        </div>
    </div>
</div>

<div class="grid">
    <div class="card chart-card full-width">
        <div class="chart-title">📈 Évolution Active Subs et Abonnements Facturés <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Abonnés actifs et facturés dans le temps">ⓘ</span></div>
        <div class="chart-container">
            <canvas id="eklektik-ca-partners-chart"></canvas>
        </div>
    </div>
</div>

 


<script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3.0.0/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
<script>
// Configuration Chart.js optimisée pour éliminer le sautillement
(function() {
    'use strict';
    
    // Configuration spécifique pour les graphiques Eklektik (pas de modification globale)
    console.log('🎨 Configuration des graphiques Eklektik...');
    
    // Palette de couleurs cohérente avec le thème Club Privilèges (violet/or)
    const eklektikColors = {
        primary: '#6C4BA0',      // Violet principal
        secondary: '#D4A843',    // Or/Gold
        success: '#8B6FC0',      // Violet clair
        warning: '#B8860B',      // Or sombre
        purple: '#9B7EC8',       // Lavande
        cyan: '#A0522D',         // Brun doré
        orange: '#7B68A0',       // Violet gris
        gray: '#C4A265',         // Or pâle
        // Versions avec transparence
        primaryAlpha: 'rgba(108, 75, 160, 0.8)',
        secondaryAlpha: 'rgba(212, 168, 67, 0.8)',
        successAlpha: 'rgba(139, 111, 192, 0.8)',
        warningAlpha: 'rgba(184, 134, 11, 0.8)',
        purpleAlpha: 'rgba(155, 126, 200, 0.8)',
        cyanAlpha: 'rgba(160, 82, 45, 0.8)',
        orangeAlpha: 'rgba(123, 104, 160, 0.8)',
        grayAlpha: 'rgba(196, 162, 101, 0.8)'
    };
    
    // Palette de couleurs pour les graphiques multi-opérateurs
    const operatorColors = [
        eklektikColors.primary,      // Rouge - Orange
        eklektikColors.secondary,    // Bleu - TT  
        eklektikColors.success,      // Vert - Ooredoo
        eklektikColors.warning,      // Orange - Taraji
        eklektikColors.purple,       // Violet - autres
        eklektikColors.cyan,         // Cyan
        eklektikColors.orange,       // Orange vif
        eklektikColors.gray          // Gris
    ];
    
    // Fonction pour mettre à jour les KPIs Eklektik
    function updateEklektikKPIs(data) {
        console.log('📊 Mise à jour des KPIs Eklektik:', data);
        
        // Revenus TTC
        if (data.total_revenue_ttc !== undefined) {
            const element = document.getElementById('eklektik-revenue-ttc');
            const deltaElement = document.getElementById('eklektik-revenue-ttc-delta');
            if (element) {
                element.textContent = new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'TND', maximumFractionDigits: 0 }).format(data.total_revenue_ttc);
            }
            if (deltaElement) {
                deltaElement.textContent = 'Revenus TTC';
            }
        }
        
        // Revenus HT
        if (data.total_revenue_ht !== undefined) {
            const element = document.getElementById('eklektik-revenue-ht');
            const deltaElement = document.getElementById('eklektik-revenue-ht-delta');
            if (element) {
                element.textContent = new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'TND', maximumFractionDigits: 0 }).format(data.total_revenue_ht);
            }
            if (deltaElement) {
                deltaElement.textContent = 'Revenus HT';
            }
        }
        
        // CA BigDeal
        if (data.total_ca_bigdeal !== undefined) {
            const element = document.getElementById('eklektik-ca-bigdeal');
            const deltaElement = document.getElementById('eklektik-ca-bigdeal-delta');
            if (element) {
                element.textContent = new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'TND', maximumFractionDigits: 0 }).format(data.total_ca_bigdeal);
            }
            if (deltaElement) {
                deltaElement.textContent = 'CA BigDeal';
            }
        }
        
        // Active Subs
        if (data.total_active_subscribers !== undefined) {
            const element = document.getElementById('eklektik-active-subs');
            const deltaElement = document.getElementById('eklektik-active-subs-delta');
            if (element) {
                element.textContent = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(data.total_active_subscribers);
            }
            if (deltaElement) {
                // Afficher le détail par opérateur si disponible
                const byOp = data.active_subscribers_by_operator || {};
                const parts = Object.entries(byOp).map(([op, val]) => `${op}: ${new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(val)}`);
                deltaElement.textContent = parts.length ? parts.join('  |  ') : 'Abonnés Actifs';
            }
        }
        
        // Nouveaux abonnements
        if (data.total_new_subscriptions !== undefined) {
            const element = document.getElementById('eklektik-new-subscriptions');
            const deltaElement = document.getElementById('eklektik-new-subscriptions-delta');
            if (element) {
                element.textContent = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(data.total_new_subscriptions);
            }
            if (deltaElement) {
                deltaElement.textContent = 'Nouveaux abonnements';
            }
        }
        
        // Désabonnements
        if (data.total_unsubscriptions !== undefined) {
            const element = document.getElementById('eklektik-unsubscriptions');
            const deltaElement = document.getElementById('eklektik-unsubscriptions-delta');
            if (element) {
                element.textContent = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(data.total_unsubscriptions);
            }
            if (deltaElement) {
                deltaElement.textContent = 'Désabonnements';
            }
        }
        
        // Simchurn
        if (data.total_simchurn !== undefined) {
            const element = document.getElementById('eklektik-simchurn');
            const deltaElement = document.getElementById('eklektik-simchurn-delta');
            if (element) {
                element.textContent = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(data.total_simchurn);
            }
            if (deltaElement) {
                deltaElement.textContent = 'Simchurn';
            }
        }
        
        // Abonnements Facturés (total au lieu de moyenne)
        if (data.total_facturation !== undefined) {
            const element = document.getElementById('eklektik-facturation');
            const deltaElement = document.getElementById('eklektik-facturation-delta');
            if (element) {
                element.textContent = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(data.total_facturation);
            }
            if (deltaElement) {
                deltaElement.textContent = 'Abonnements Facturés';
            }
        }
        
        console.log('✅ KPIs Eklektik mis à jour');
    }
    
    // Variables globales pour les graphiques
    window.eklektikCharts = {};
    window.isCreatingEklektikCharts = false;
    
    // Fonction générique pour créer un graphique de manière sécurisée
    function createChartSafely(canvasId, chartKey, chartData, chartType, options, delay = 0) {
        console.log(`🎨 Tentative de création du graphique ${chartKey} sur canvas ${canvasId}`);

        const ctx = document.getElementById(canvasId);
        if (!ctx) {
            console.error(`❌ Canvas ${canvasId} introuvable dans le DOM`);
            return;
        }

        if (!chartData) {
            console.error(`❌ Pas de données de graphique pour ${chartKey}`);
            console.error('Données reçues:', chartData);
            return;
        }

        console.log(`✅ Canvas ${canvasId} trouvé, données présentes pour ${chartKey}`);

        // Vérifier la visibilité et les dimensions du canvas
        const canvas = document.getElementById(canvasId);
        console.log(`📏 Dimensions du canvas ${canvasId}:`, {
            width: canvas.width,
            height: canvas.height,
            offsetWidth: canvas.offsetWidth,
            offsetHeight: canvas.offsetHeight,
            display: window.getComputedStyle(canvas).display,
            visibility: window.getComputedStyle(canvas).visibility
        });
        
        const createChart = () => {
            try {
                // Destruction complète de toute instance existante
                if (window.eklektikCharts[chartKey]) {
                    console.log(`🗑️ Destruction du graphique existant ${chartKey}`);
                    window.eklektikCharts[chartKey].destroy();
                    window.eklektikCharts[chartKey] = null;
                }
                
                // Nettoyer le canvas complètement
                if (ctx.chart) {
                    console.log(`🗑️ Nettoyage du canvas ${chartKey}`);
                    ctx.chart.destroy();
                    ctx.chart = null;
                }
                
                // Récupérer toutes les instances Chart.js sur ce canvas
                const instances = Chart.getChart(ctx);
                if (instances) {
                    console.log(`🗑️ Destruction de l'instance Chart.js existante pour ${chartKey}`);
                    instances.destroy();
                }
                
                // Attendre un peu pour s'assurer que le canvas est libre
                setTimeout(() => {
                    window.eklektikCharts[chartKey] = new Chart(ctx, {
                        type: chartType,
                        data: chartData,
                        options: options
                    });
                    console.log(`✅ Graphique ${chartKey} créé avec succès`);
                    console.log(`📊 Vérification du graphique ${chartKey}:`, {
                        data_labels: chartData.labels?.length || 'N/A',
                        data_datasets: chartData.datasets?.length || 'N/A',
                        chart_type: chartType,
                        canvas_dimensions: (function(){
                            const el = (ctx && ctx.canvas) ? ctx.canvas : ctx; // ctx est un canvas élément
                            return {
                                width: el?.width,
                                height: el?.height
                            };
                        })()
                    });

                    // Vérifier si le graphique est rendu
                    const canvas = (ctx && ctx.canvas) ? ctx.canvas : ctx;
                    setTimeout(() => {
                        console.log(`🎨 État du graphique ${chartKey} après rendu:`, {
                            isDrawn: canvas.toDataURL().length > 100, // Vérifier si le canvas contient quelque chose
                            chart_config: window.eklektikCharts[chartKey]?.config?.type,
                            canvas_style: {
                                width: canvas.style.width,
                                height: canvas.style.height,
                                display: canvas.style.display,
                                visibility: canvas.style.visibility
                            }
                        });

                        // Forcer le redimensionnement du graphique
                        if (window.eklektikCharts[chartKey]) {
                            window.eklektikCharts[chartKey].resize();
                            console.log(`🔄 Graphique ${chartKey} redimensionné`);
                        }
                    }, 100);
                }, 10);
                
            } catch (error) {
                console.error(`❌ Erreur lors de la création du graphique ${chartKey}:`, error);
                // Tentative de récupération
                setTimeout(() => {
                    try {
                        const retryInstances = Chart.getChart(ctx);
                        if (retryInstances) {
                            retryInstances.destroy();
                        }
                        window.eklektikCharts[chartKey] = new Chart(ctx, {
                            type: chartType,
                            data: chartData,
                            options: options
                        });
                        console.log(`✅ Graphique ${chartKey} recréé avec succès après erreur`);
                    } catch (retryError) {
                        console.error(`❌ Impossible de recréer le graphique ${chartKey}:`, retryError);
                    }
                }, 100);
            }
        };
        
        if (delay > 0) {
            setTimeout(createChart, delay);
        } else {
            createChart();
        }
    }
    
    // Fonction pour créer le graphique multi-axes
    function createEklektikOverviewChart(chartData) {
        const ctx = document.getElementById('eklektik-overview-chart');
        if (!ctx || !chartData) {
            console.log('❌ Canvas ou données manquantes pour overview chart');
            return;
        }
        
        // Vérifier si le graphique existe déjà
        if (window.eklektikCharts.overview) {
            console.log('⚠️ Graphique overview existe déjà, destruction...');
            try {
                window.eklektikCharts.overview.destroy();
            } catch (e) {
                console.log('Graphique overview déjà détruit');
            }
            window.eklektikCharts.overview = null;
        }
        
        // Vérifier que le canvas est libre
        if (ctx.chart) {
            console.log('⚠️ Canvas overview déjà utilisé, nettoyage...');
            try {
                ctx.chart.destroy();
            } catch (e) {
                console.log('Canvas chart déjà détruit');
            }
            ctx.chart = null;
        }
        
        // Attendre un peu pour s'assurer que le canvas est libre
        setTimeout(() => {
            createOverviewChartInternal(ctx, chartData);
        }, 50);
    }
    
    function createOverviewChartInternal(ctx, chartData) {
        
        const options = {
            responsive: true,
            maintainAspectRatio: false,
            animation: false,
            animations: { duration: 0 },
            elements: {
                point: { hoverRadius: 0 },
                line: { tension: 0 }
            },
            plugins: {
                legend: { animation: false },
                tooltip: { animation: false }
            },
            scales: {
                x: {
                    display: true,
                    title: { display: true, text: 'Date' }
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
                    grid: { drawOnChartArea: false }
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
                    grid: { drawOnChartArea: false }
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
                    grid: { drawOnChartArea: false }
                }
            }
        };
        
        try {
            // Vérifier une dernière fois que le canvas est libre
            if (ctx.chart) {
                console.log('⚠️ Canvas encore utilisé, destruction forcée...');
                ctx.chart.destroy();
                ctx.chart = null;
            }
            
            window.eklektikCharts.overview = new Chart(ctx, {
                type: 'bar',
                data: chartData,
                options: options
            });
            console.log('✅ Graphique overview créé avec succès');
        } catch (error) {
            console.error('❌ Erreur lors de la création du graphique overview:', error);
            // Essayer de nettoyer et recréer
            if (ctx.chart) {
                try {
                    ctx.chart.destroy();
                    ctx.chart = null;
                } catch (e) {
                    console.log('Erreur lors du nettoyage forcé:', e);
                }
            }
        }
    }
    
    // Fonction pour créer le graphique d'évolution des revenus
    function createEklektikRevenueEvolutionChart(chartData) {
        const ctx = document.getElementById('eklektik-revenue-evolution-chart');
        if (!ctx || !chartData) return;
        
        if (window.eklektikCharts.revenueEvolution) {
            try {
                window.eklektikCharts.revenueEvolution.destroy();
            } catch (e) {
                console.log('Graphique revenueEvolution déjà détruit');
            }
            window.eklektikCharts.revenueEvolution = null;
        }
        
        // Vérifier que le canvas est libre
        if (ctx.chart) {
            try {
                ctx.chart.destroy();
            } catch (e) {
                console.log('Canvas chart déjà détruit');
            }
            ctx.chart = null;
        }
        
        try {
            window.eklektikCharts.revenueEvolution = new Chart(ctx, {
                type: 'line',
                data: chartData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: false,
                    animations: { duration: 0 },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'TND' }).format(value);
                                }
                            }
                        }
                    }
                }
            });
            console.log('✅ Graphique revenueEvolution créé avec succès');
        } catch (error) {
            console.error('❌ Erreur lors de la création du graphique revenueEvolution:', error);
        }
    }
    
    // Fonction pour créer le graphique de répartition par opérateur
    function createEklektikOperatorsDistributionChart(chartData) {
        const ctx = document.getElementById('eklektik-operators-distribution-chart');
        if (!ctx || !chartData) return;
        
        if (window.eklektikCharts.operatorsDistribution) {
            try {
                window.eklektikCharts.operatorsDistribution.destroy();
            } catch (e) {
                console.log('Graphique operatorsDistribution déjà détruit');
            }
            window.eklektikCharts.operatorsDistribution = null;
        }
        
        // Vérifier que le canvas est libre
        if (ctx.chart) {
            try {
                ctx.chart.destroy();
            } catch (e) {
                console.log('Canvas chart déjà détruit');
            }
            ctx.chart = null;
        }
        
        try {
            window.eklektikCharts.operatorsDistribution = new Chart(ctx, {
                type: 'doughnut',
                data: chartData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: false,
                    animations: { duration: 0 }
                }
            });
            console.log('✅ Graphique operatorsDistribution créé avec succès');
        } catch (error) {
            console.error('❌ Erreur lors de la création du graphique operatorsDistribution:', error);
        }
    }
    
    // Fonction pour créer le graphique CA par partenaire
    function createEklektikCAPartnersChart(chartData) {
        const ctx = document.getElementById('eklektik-ca-partners-chart');
        if (!ctx || !chartData) return;
        
        if (window.eklektikCharts.caPartners) {
            try {
                window.eklektikCharts.caPartners.destroy();
            } catch (e) {
                console.log('Graphique caPartners déjà détruit');
            }
            window.eklektikCharts.caPartners = null;
        }
        
        // Vérifier que le canvas est libre
        if (ctx.chart) {
            try {
                ctx.chart.destroy();
            } catch (e) {
                console.log('Canvas chart déjà détruit');
            }
            ctx.chart = null;
        }
        
        try {
            window.eklektikCharts.caPartners = new Chart(ctx, {
                type: 'bar',
                data: chartData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: false,
                    animations: { duration: 0 },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'TND' }).format(value);
                                }
                            }
                        }
                    }
                }
            });
            console.log('✅ Graphique caPartners créé avec succès');
        } catch (error) {
            console.error('❌ Erreur lors de la création du graphique caPartners:', error);
        }
    }
    
    // Fonction pour charger les données (réutilisable à l'actualisation globale)
    async function loadEklektikCharts() {
        // Éviter les créations multiples
        if (window.isCreatingEklektikCharts) {
            console.log('⚠️ Création de graphiques déjà en cours, ignoré');
            return;
        }

        window.isCreatingEklektikCharts = true;
        
        try {
            const operator = document.getElementById('eklektik-operator-select')?.value || 'ALL';
            const operatorForKPIs = 'ALL'; // Active Subs et KPIs globaux: somme tous opérateurs
            
            // Utiliser les dates de la section "Sélection des Périodes"
            const startDateElement = document.getElementById('start-date');
            const endDateElement = document.getElementById('end-date');
            
            let startDateStr, endDateStr;
            if (startDateElement && endDateElement && startDateElement.value && endDateElement.value) {
                startDateStr = startDateElement.value;
                endDateStr = endDateElement.value;
                console.log('📅 Utilisation des dates de la Sélection des Périodes');
            } else {
                // Fallback: utiliser les 30 derniers jours par défaut
                const endDate = new Date();
                const startDate = new Date();
                startDate.setDate(endDate.getDate() - 30);
                startDateStr = startDate.toISOString().split('T')[0];
                endDateStr = endDate.toISOString().split('T')[0];
                console.log('📅 Utilisation des dates par défaut (30 derniers jours)');
            }
            
            console.log(`🔄 Chargement des vraies données Eklektik pour ${startDateStr} - ${endDateStr}...`);

            // Nettoyer d'abord tous les graphiques existants
            console.log('🗑️ Nettoyage des graphiques existants...');
            window.clearEklektikCharts();
            
            // Charger les vraies données de l'API Eklektik
            const [kpis, overviewChart, revenueEvolution, revenueDistribution, subsEvolution] = await Promise.all([
                fetchData('/api/eklektik-dashboard/kpis', { start_date: startDateStr, end_date: endDateStr, operator: operatorForKPIs }),
                fetchData('/api/eklektik-dashboard/overview-chart', { start_date: startDateStr, end_date: endDateStr, operator }),
                fetchData('/api/eklektik-dashboard/revenue-evolution', { start_date: startDateStr, end_date: endDateStr, operator }),
                fetchData('/api/eklektik-dashboard/revenue-distribution', { start_date: startDateStr, end_date: endDateStr }),
                fetchData('/api/eklektik-dashboard/subs-evolution', { start_date: startDateStr, end_date: endDateStr, operator })
            ]);
            
            console.log('📊 Données Eklektik chargées:', {
                kpis: kpis?.success ? '✅' : '❌',
                overviewChart: overviewChart?.success ? '✅' : '❌',
                revenueEvolution: revenueEvolution?.success ? '✅' : '❌',
                revenueDistribution: revenueDistribution?.success ? '✅' : '❌',
                subsEvolution: subsEvolution?.success ? '✅' : '❌'
            });

            console.log('📋 Détails des données:', {
                kpis_data: kpis?.data,
                overview_data: overviewChart?.data,
                revenue_data: revenueEvolution?.data,
                distribution_data: revenueDistribution?.data,
                subs_data: subsEvolution?.data
            });

            // Continuer avec les données disponibles (ne pas tout abandonner si une API échoue)
            if (!kpis?.success) console.warn('⚠️ API KPIs échouée:', kpis?.error || kpis?.message);
            if (!overviewChart?.success) console.warn('⚠️ API overview-chart échouée:', overviewChart?.error || overviewChart?.message);
            if (!revenueEvolution?.success) console.warn('⚠️ API revenue-evolution échouée:', revenueEvolution?.error || revenueEvolution?.message);
            if (!revenueDistribution?.success) console.warn('⚠️ API revenue-distribution échouée:', revenueDistribution?.error || revenueDistribution?.message);
            if (!subsEvolution?.success) console.warn('⚠️ API subs-evolution échouée:', subsEvolution?.error || subsEvolution?.message);

            // Mettre à jour les KPIs si disponibles
            if (kpis?.success && kpis.data) {
                updateEklektikKPIs(kpis.data);
            }
            
            // Modifier les couleurs du graphique Overview avec la palette cohérente
            if (overviewChart.data?.chart?.datasets) {
                overviewChart.data.chart.datasets.forEach((dataset, index) => {
                    // Harmoniser avec couleurs Overview: barres violettes/grises
                    const violet = '#8b5cf6';
                    const violetAlpha = 'rgba(139, 92, 246, 0.6)';
                    const gray = '#9ca3af';
                    const grayAlpha = 'rgba(156, 163, 175, 0.6)';
                    dataset.backgroundColor = index % 2 === 0 ? violetAlpha : grayAlpha;
                    dataset.borderColor = index % 2 === 0 ? violet : gray;
                });
            }
            
            // Préparer les conteneurs (au cas où un fallback d'erreur a remplacé le canvas)
            function prepareChartContainer(canvasId, minHeight = 300) {
                let container = null;
                const existingCanvas = document.getElementById(canvasId);
                if (existingCanvas && existingCanvas.parentElement) {
                    container = existingCanvas.parentElement;
                } else {
                    // Chrome supporte :has, sinon fallback manuel
                    container = document.querySelector(`.chart-container:has(#${canvasId})`);
                    if (!container) {
                        // Fallback: parcourir tous les conteneurs et chercher ceux sans canvas
                        document.querySelectorAll('.chart-container').forEach(c => {
                            if (!container && !c.querySelector('canvas')) container = c;
                        });
                    }
                }
                if (container) {
                    // Si le canvas n'existe pas, ou si le conteneur contient un ancien message d'erreur, recréer le canvas
                    if (!existingCanvas || container.querySelector('.eklektik-error')) {
                        container.innerHTML = `<canvas id="${canvasId}"></canvas>`;
                    }
                    container.style.minHeight = minHeight + 'px';
                    container.style.display = 'block';
                    container.style.width = '100%';
                }
            }

            prepareChartContainer('eklektik-overview-chart', 300);
            prepareChartContainer('eklektik-revenue-evolution-chart', 300);
            prepareChartContainer('eklektik-operators-distribution-chart', 300);
            prepareChartContainer('eklektik-ca-partners-chart', 300);

            // Créer les graphiques avec les vraies données avec un délai pour éviter les conflits
            console.log('📊 Données du graphique overview:', overviewChart.data?.chart);
            console.log('📊 Structure des données overview:', {
                labels: overviewChart.data?.chart?.labels,
                datasets: overviewChart.data?.chart?.datasets?.length,
                dataset0_data: overviewChart.data?.chart?.datasets?.[0]?.data?.length,
                dataset0_sample: overviewChart.data?.chart?.datasets?.[0]?.data?.slice(0, 3),
                dataset0_sum: overviewChart.data?.chart?.datasets?.[0]?.data?.reduce((a, b) => a + b, 0),
                dataset1_sample: overviewChart.data?.chart?.datasets?.[1]?.data?.slice(0, 3),
                dataset1_sum: overviewChart.data?.chart?.datasets?.[1]?.data?.reduce((a, b) => a + b, 0)
            });
            console.log('📊 Données du graphique revenue evolution:', revenueEvolution.data?.chart);
            console.log('📊 Structure des données revenue:', {
                labels: revenueEvolution.data?.chart?.labels,
                datasets: revenueEvolution.data?.chart?.datasets?.length,
                dataset0_data: revenueEvolution.data?.chart?.datasets?.[0]?.data?.length
            });
            console.log('📊 Données du graphique distribution:', revenueDistribution.data?.pie_chart);
            console.log('📊 Données du graphique subs evolution:', subsEvolution.data?.chart);

            // Créer uniquement les graphiques pour lesquels on a des données (ne pas tout abandonner)
            if (!overviewChart.data?.chart) console.warn('⚠️ Pas de données overview, graphique ignoré');
            if (!revenueEvolution.data?.chart) console.warn('⚠️ Pas de données revenue evolution, graphique ignoré');
            if (!revenueDistribution.data?.pie_chart) console.warn('⚠️ Pas de données distribution, graphique ignoré');
            if (!subsEvolution.data?.chart) console.warn('⚠️ Pas de données subs evolution, graphique ignoré');

            // Vérifier si les données sont toutes à 0 (seulement si overview disponible)
            if (overviewChart.data?.chart) {
            const overviewData = overviewChart.data.chart;
            const hasData = overviewData.datasets && overviewData.datasets.some(dataset =>
                dataset.data && dataset.data.some(value => value > 0)
            );
            console.log('📊 Le graphique overview a-t-il des données > 0 ?', hasData);
            }

            // Vérifier la visibilité du conteneur avant de créer le graphique
            const container = document.querySelector('.chart-container');
            console.log('📦 Conteneur du graphique overview:', {
                display: container ? window.getComputedStyle(container).display : 'N/A',
                visibility: container ? window.getComputedStyle(container).visibility : 'N/A',
                width: container ? container.offsetWidth : 'N/A',
                height: container ? container.offsetHeight : 'N/A'
            });

            if (overviewChart.data?.chart) {
            createChartSafely('eklektik-overview-chart', 'overview', overviewChart.data.chart, 'bar', {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 0 },
                animations: {
                    duration: 0,
                    hover: { duration: 0 },
                    active: { duration: 0 }
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    },
                    tooltip: {
                        enabled: true,
                        mode: 'index',
                        intersect: false
                    }
                },
                elements: {
                    point: { hoverRadius: 4 },
                    line: { tension: 0 }
                },
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                scales: {
                    x: {
                        type: 'time',
                        time: {
                            parser: 'yyyy-MM-dd',
                            unit: 'day',
                            displayFormats: {
                                day: 'dd/MM'
                            }
                        },
                        display: true,
                        title: { display: true, text: 'Date' }
                    },
                    'y-active': {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Active Subscribers',
                            color: eklektikColors.primary
                        },
                        ticks: {
                            color: eklektikColors.primary,
                            callback: function(value) {
                                return new Intl.NumberFormat('fr-FR').format(value);
                            }
                        }
                    },
                    'y-bigdeal': {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'CA BigDeal (TND)',
                            color: eklektikColors.warning
                        },
                        ticks: {
                            color: eklektikColors.warning,
                            callback: function(value) {
                                return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'TND' }).format(value);
                            }
                        },
                        grid: { drawOnChartArea: false }
                    }
                }
            }, 100);
            }

            // Modifier les couleurs du graphique Revenue Evolution
            if (revenueEvolution.data?.chart?.datasets) {
                revenueEvolution.data.chart.datasets.forEach((dataset, index) => {
                    dataset.borderColor = operatorColors[index % operatorColors.length];
                    dataset.backgroundColor = operatorColors[index % operatorColors.length] + '20';
                    dataset.pointBackgroundColor = operatorColors[index % operatorColors.length];
                    dataset.pointBorderColor = operatorColors[index % operatorColors.length];
                });
            }
            
            if (revenueEvolution.data?.chart) {
            createChartSafely('eklektik-revenue-evolution-chart', 'revenueEvolution', revenueEvolution.data.chart, 'line', {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 0 },
                animations: { 
                    duration: 0,
                    hover: { duration: 0 },
                    active: { duration: 0 }
                },
                elements: {
                    point: { hoverRadius: 4 },
                    line: { tension: 0.4 }
                },
                plugins: {
                    legend: {
                        position: 'top'
                    },
                    tooltip: { 
                        animation: false,
                        enabled: true,
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            title: function(tooltipItems) {
                                return 'Date: ' + tooltipItems[0].label;
                            },
                            label: function(context) {
                                const label = context.dataset.label || '';
                                const value = new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'TND' }).format(context.parsed.y);
                                return label + ': ' + value;
                            }
                        }
                    }
                },
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'CA BigDeal (TND)'
                        },
                        ticks: {
                            callback: function(value) {
                                return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'TND' }).format(value);
                            }
                        }
                    },
                    x: {
                        type: 'time',
                        time: {
                            parser: 'yyyy-MM-dd',
                            unit: 'day',
                            displayFormats: {
                                day: 'dd/MM'
                            }
                        },
                        title: {
                            display: true,
                            text: 'Période'
                        }
                    }
                }
            }, 200);
            }
            
            // Modifier les couleurs du graphique Operators Distribution pour thème Club Privilèges
            if (revenueDistribution.data?.pie_chart) {
            if (revenueDistribution.data.pie_chart?.datasets?.[0]) {
                const colors = [
                    '#6C4BA0',  // Violet principal
                    '#D4A843',  // Or
                    '#8B6FC0'   // Violet clair
                ];
                revenueDistribution.data.pie_chart.datasets[0].backgroundColor = colors;
                revenueDistribution.data.pie_chart.datasets[0].borderColor = '#1a1a2e';
                revenueDistribution.data.pie_chart.datasets[0].borderWidth = 2;
            }
            createChartSafely('eklektik-operators-distribution-chart', 'operatorsDistribution', revenueDistribution.data.pie_chart, 'doughnut', {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 0 },
                animations: { 
                    duration: 0,
                    hover: { duration: 0 },
                    active: { duration: 0 }
                },
                plugins: {
                    tooltip: { 
                        animation: false,
                        enabled: true
                    }
                },
                interaction: {
                    mode: 'index',
                    intersect: false
                }
            }, 300);
            }

            // Mettre à jour la carte "Statistiques par Opérateur" (si la fonction globale existe)
            try {
        if (typeof window.displayEklektikOperatorsStats === 'function') {
                    const dist = revenueDistribution?.data?.distribution || revenueDistribution?.distribution || null;
                    if (dist) {
                        window.displayEklektikOperatorsStats(dist);
                    }
                }
            } catch (e) {
                console.warn('⚠️ Impossible de mettre à jour Statistiques par Opérateur:', e);
            }
            
            if (subsEvolution.data?.chart) {
            // Modifier les couleurs du graphique Subs Evolution
            if (subsEvolution.data.chart?.datasets) {
                subsEvolution.data.chart.datasets.forEach((dataset, index) => {
                    switch(dataset.label) {
                        case 'Active Subs':
                            dataset.backgroundColor = eklektikColors.primaryAlpha;
                            dataset.borderColor = eklektikColors.primary;
                            break;
                        case 'Abonnements Facturés':
                            dataset.backgroundColor = eklektikColors.secondaryAlpha;
                            dataset.borderColor = eklektikColors.secondary;
                            break;
                        default:
                            dataset.backgroundColor = operatorColors[index % operatorColors.length] + '80';
                            dataset.borderColor = operatorColors[index % operatorColors.length];
                    }
                    dataset.borderWidth = 1;
                });
            }
            createChartSafely('eklektik-ca-partners-chart', 'subsEvolution', subsEvolution.data.chart, 'line', {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 0 },
                animations: { 
                    duration: 0,
                    hover: { duration: 0 },
                    active: { duration: 0 }
                },
                elements: {
                    point: { hoverRadius: 4 },
                    line: { tension: 0.4 }
                },
                plugins: {
                    legend: {
                        position: 'top'
                    },
                    tooltip: { 
                        animation: false,
                        enabled: true,
                        mode: 'index',
                        intersect: false
                    }
                },
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Nombre'
                        },
                        ticks: {
                            callback: function(value) {
                                return new Intl.NumberFormat('fr-FR').format(value);
                            }
                        }
                    },
                    x: {
                        type: 'time',
                        time: {
                            parser: 'yyyy-MM-dd',
                            unit: 'day',
                            displayFormats: {
                                day: 'dd/MM'
                            }
                        },
                        title: {
                            display: true,
                            text: 'Période'
                        }
                    }
                }
            }, 400);
            }

            // (Supprimé) Graphique Active Subs cumulés
            
            
        } catch (error) {
            console.error('❌ Erreur lors du chargement des graphiques Eklektik:', error);
            console.log('🚫 Pas de fallback - affichage d\'un message d\'erreur');
            
            // Afficher un message d'erreur non destructif (sans remplacer le canvas)
            const containers = ['eklektik-overview-chart', 'eklektik-revenue-evolution-chart', 'eklektik-operators-distribution-chart', 'eklektik-ca-partners-chart'];
            containers.forEach(containerId => {
                const canvas = document.getElementById(containerId);
                if (canvas && canvas.parentElement) {
                    const p = document.createElement('div');
                    p.className = 'eklektik-error';
                    p.style.cssText = 'display:flex;align-items:center;justify-content:center;height:200px;color:#dc3545;text-align:center;';
                    p.innerHTML = '<div><div style="font-size:14px;">Erreur lors du chargement des données Eklektik</div><div style="font-size:12px;margin-top:5px;">Vérifiez la synchronisation des données</div></div>';
                    // Effacer les anciens messages d'erreur
                    canvas.parentElement.querySelectorAll('.eklektik-error').forEach(el => el.remove());
                    canvas.parentElement.appendChild(p);
                }
            });
        } finally {
            window.isCreatingEklektikCharts = false;
        }
    }
    
    // Fonction de test supprimée - utilisation exclusive des vraies APIs
    
    // Fonction pour récupérer les données
    async function fetchData(endpoint, params) {
        const url = new URL(endpoint, window.location.origin);
        Object.keys(params).forEach(key => url.searchParams.append(key, params[key]));

        console.log(`🔗 Appel API: ${url.toString()}`);

        const response = await fetch(url, {
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            }
        });

        if (!response.ok) {
            console.error(`❌ Erreur HTTP ${response.status} pour ${endpoint}`);
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();
        console.log(`✅ Réponse API ${endpoint}:`, data);

        return data;
    }
    
    
    // Fonction pour vider les graphiques
    window.clearEklektikCharts = function() {
        Object.entries(window.eklektikCharts).forEach(([key, chart]) => {
            if (chart) {
                try {
                    chart.destroy();
                    console.log(`✅ Graphique ${key} détruit`);
                } catch (e) {
                    console.log(`⚠️ Erreur lors de la destruction du graphique ${key}:`, e);
                }
            }
        });
        window.eklektikCharts = {};
        
        // Nettoyer aussi les canvas
        const canvasIds = [
            'eklektik-overview-chart',
            'eklektik-revenue-evolution-chart',
            'eklektik-operators-distribution-chart',
            'eklektik-ca-partners-chart'
        ];
        
        canvasIds.forEach(id => {
            const canvas = document.getElementById(id);
            if (canvas && canvas.chart) {
                try {
                    canvas.chart.destroy();
                    canvas.chart = null;
                } catch (e) {
                    console.log(`⚠️ Erreur lors du nettoyage du canvas ${id}:`, e);
                }
            }
        });
    }
    
    // Exposer les fonctions globalement
    window.loadEklektikCharts = loadEklektikCharts;
    window.clearEklektikCharts = clearEklektikCharts;

    // Brancher sur l'actualisation globale du dashboard si disponible
    if (typeof window.addEventListener === 'function') {
        window.addEventListener('dashboard:refreshed', function() {
            // Recharger les KPIs et les charts Eklektik après l'actualisation globale
            try {
                if (typeof window.loadEklektikData === 'function') {
                    window.loadEklektikData().then(() => setTimeout(loadEklektikCharts, 100));
                } else {
                    setTimeout(loadEklektikCharts, 100);
                }
            } catch (e) {
                console.warn('Eklektik refresh hook error:', e);
                setTimeout(loadEklektikCharts, 200);
            }
        });
    }
    
    // Les graphiques sont chargés quand l'onglet Eklektik est activé
    
    // Écouter les changements d'opérateur
    document.addEventListener('change', function(e) {
        if (e.target.id === 'eklektik-operator-select') {
            // Éviter les rechargements multiples
            if (!window.isCreatingEklektikCharts) {
                console.log('🔄 Changement d\'opérateur détecté, rechargement des graphiques...');
                loadEklektikCharts();
            }
        }

        // Écouter les changements de dates de la section principale
        if (e.target.id === 'start-date' || e.target.id === 'end-date') {
            if (!window.isCreatingEklektikCharts) {
                console.log('🔄 Changement de dates détecté, rechargement des graphiques Eklektik...');
                setTimeout(() => {
                    loadEklektikCharts();
                }, 500); // Petit délai pour s'assurer que les deux dates sont mises à jour
            }
        }
    });
})();
</script>
