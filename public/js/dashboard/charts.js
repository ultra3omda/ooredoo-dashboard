/**
 * Dashboard - Module Charts
 * Fonctions de création des graphiques Chart.js
 */

function updateCharts(data) {
  // Overview Chart
  createOverviewChart(data);
  
  // Subscription Charts
  createSubscriptionTrendChart(data);
  createRetentionChart(data);
  
  // Nouveaux graphiques de subscription
  createActivationsByChannelChart(data);
  createPlanDistributionChart(data);
  createCohortsAnalysisChart(data);
  
  // Transaction Charts
  createTransactionVolumeChart(data);
  createTransactingUsersChart(data);
  
  // Nouveaux graphiques d'analyse des transactions
  createTransactionsByOperatorChart(data);
  createTransactionsByPlanChart(data);

  // Merchants Charts (réactivés)
  createTopMerchantsChart(data);
  createCategoryChart(data);
  createActiveLocationsTrend(data);
  
  // Comparison Chart (nouveau)
  createComparisonChart(data);
}
  // Create active locations trend chart
  function createActiveLocationsTrend(data) {
const ctx = document.getElementById('activeLocationsTrend');
if (!ctx) return;

if (charts.activeLocationsTrend) {
  charts.activeLocationsTrend.destroy();
}

const points = (data.subscriptions && data.subscriptions.quarterly_active_locations) ? data.subscriptions.quarterly_active_locations : [];
const labels = points.map(p => p.quarter);
const values = points.map(p => p.locations);

charts.activeLocationsTrend = new Chart(ctx, {
  type: 'line',
  data: {
    labels,
    datasets: [{
      label: 'Points de vente actifs',
      data: values,
      borderColor: getThemeColor('primary'),
      backgroundColor: getThemeColor('primaryRgba'),
      tension: 0.25,
      fill: true
    }]
  },
  options: getMobileOptimizedChartOptions({
    plugins: {
      legend: { display: false }
    }
  })
});
}

// Create transactions by operator chart
function createTransactionsByOperatorChart(data) {
  const ctx = document.getElementById('transactionsByOperatorChart');
  if (!ctx) return;

  if (charts.transactionsByOperator) {
    charts.transactionsByOperator.destroy();
  }

  const operatorData = (data.transactions && data.transactions.analytics && data.transactions.analytics.byOperator) ? data.transactions.analytics.byOperator : [];
  const labels = operatorData.map(item => item.operator);
  const values = operatorData.map(item => item.count);

  charts.transactionsByOperator = new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels,
      datasets: [{
        data: values,
        backgroundColor: [
          getThemeColor('primary'),
          getThemeColor('accent'),
          getThemeColor('success'),
          getThemeColor('warning'),
          '#6366f1',
          '#8b5cf6',
          '#ec4899'
        ]
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: 'bottom'
        }
      }
    }
  });
}

// Create transactions by plan chart
function createTransactionsByPlanChart(data) {
  const ctx = document.getElementById('transactionsByPlanChart');
  if (!ctx) return;
  
  // Ne pas créer le graphique si l'élément n'existe pas (masqué pour collaborateur)
  if (!ctx.parentElement || ctx.parentElement.style.display === 'none') return;

  if (charts.transactionsByPlan) {
    charts.transactionsByPlan.destroy();
  }

  const planData = (data.transactions && data.transactions.analytics && data.transactions.analytics.byPlan) ? data.transactions.analytics.byPlan : [];
  const labels = planData.map(item => item.plan);
  const values = planData.map(item => item.count);

  const planColors = {
    'Journalier': getThemeColor('warning'),
    'Mensuel': getThemeColor('accent'),
    'Annuel': getThemeColor('success'),
    'Autre': '#6b7280'
  };

  const backgroundColors = labels.map(label => planColors[label] || '#6b7280');

  charts.transactionsByPlan = new Chart(ctx, {
    type: 'bar',
    data: {
      labels,
      datasets: [{
        label: 'Nombre de transactions',
        data: values,
        backgroundColor: backgroundColors
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
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

// Create overview chart
function createOverviewChart(data) {
  const ctx = document.getElementById('overviewChart');
  if (!ctx) return;
  
  if (charts.overview) {
    charts.overview.destroy();
  }
  
  charts.overview = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: ['Activated Subscriptions', 'Active Subscriptions', 'Total Transactions', 'Active Merchants'],
      datasets: [
        {
          label: 'Current Period',
          data: [
            (data.kpis?.activatedSubscriptions?.current ?? 0),
            (data.kpis?.activeSubscriptions?.current ?? 0),
            (data.kpis?.totalTransactions?.current ?? 0),
            (data.kpis?.activeMerchants?.current ?? 0)
          ],
          backgroundColor: getThemeColor('primary'),
          borderRadius: 4
        },
        {
          label: 'Previous Period',
          data: [
            (data.kpis?.activatedSubscriptions?.previous ?? 0),
            (data.kpis?.activeSubscriptions?.previous ?? 0),
            (data.kpis?.totalTransactions?.previous ?? 0),
            (data.kpis?.activeMerchants?.previous ?? 0)
          ],
          backgroundColor: '#64748b',
          borderRadius: 4
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: 'top'
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

// Create subscription trend chart
function createSubscriptionTrendChart(data) {
  const ctx = document.getElementById('subscriptionTrendChart');
  if (!ctx) return;
  
  if (charts.subscriptionTrend) {
    charts.subscriptionTrend.destroy();
  }
  
  // Use real daily activations data from backend
  const dailyActivations = data.subscriptions?.daily_activations || [];
  // Build a continuous date range (align X axis with other charts)
  const dateToValue = new Map();
  const parseISO = (s) => new Date(s + 'T00:00:00');
  dailyActivations.forEach(it => {
    if (it && it.date) {
      dateToValue.set(it.date, Number(it.activations || 0));
    }
  });

  const sortedDates = Array.from(dateToValue.keys()).sort();
  if (sortedDates.length === 0) return;
  const start = parseISO(sortedDates[0]);
  const end = parseISO(sortedDates[sortedDates.length - 1]);
  const days = [];
  const dailyData = [];
  for (let d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
    const iso = d.toISOString().slice(0, 10);
    days.push(iso);
    dailyData.push(dateToValue.has(iso) ? dateToValue.get(iso) : 0);
  }
  
  charts.subscriptionTrend = new Chart(ctx, {
    type: 'line',
    data: {
      labels: days,
      datasets: [{
        label: 'Daily Activated Subscriptions',
        data: dailyData,
        borderColor: getThemeColor('primary'),
        backgroundColor: getThemeColor('primaryRgba'),
        fill: true,
        tension: 0.3,
        pointRadius: 2,
        spanGaps: true
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      scales: {
        x: {
          ticks: {
            autoSkip: true,
            maxTicksLimit: 14,
            minRotation: 45,
            maxRotation: 45
          }
        }
      }
    }
  });
}

// Create retention chart
function createRetentionChart(data) {
  const ctx = document.getElementById('retentionChart');
  if (!ctx) return;
  
  if (charts.retention) {
    charts.retention.destroy();
  }
  
  // Use real retention trend data from backend
  // Guard: vérifier spécifiquement que retention_trend existe (pas juste subscriptions)
  if (!data.subscriptions?.retention_trend || data.subscriptions.retention_trend.length === 0) {
    console.log('[RETENTION] Skipped: no retention_trend data yet');
    return;
  }
  
  const retentionTrend = data.subscriptions.retention_trend;
  
  // Aligner les dates avec le graphe Daily Activated Subscriptions
  const mapDateToValue = new Map();
  retentionTrend.forEach(it => {
    if (it && (it.date || it.period)) {
      const dateKey = it.date || it.period;
      const value = Number((it.value ?? it.rate ?? 0) || 0);
      mapDateToValue.set(dateKey, value);
    }
  });
  
  const sorted = Array.from(mapDateToValue.keys()).sort();
  if (sorted.length === 0) {
    // Pas de données - ne pas détruire le canvas, juste retourner
    return;
  }
  
  // Utiliser directement les dates des données plutôt que de générer tous les jours
  // Cela évite d'avoir beaucoup de valeurs nulles
  const days = sorted;
  const retentionData = sorted.map(date => mapDateToValue.get(date));
  
  charts.retention = new Chart(ctx, {
    type: 'line',
    data: {
      labels: days,
      datasets: [{
        label: 'Retention Rate (%)',
        data: retentionData,
        borderColor: '#10b981',
        backgroundColor: 'rgba(16, 185, 129, 0.1)',
        fill: true,
        tension: 0.4
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          display: false
        }
      },
      scales: {
        y: {
          beginAtZero: true,
          suggestedMax: 100,
          ticks: {
            callback: function(value) { return value + '%'; }
          }
        },
        x: {
          ticks: {
            autoSkip: true,
            maxTicksLimit: 14,
            minRotation: 45,
            maxRotation: 45
          }
        }
      }
    }
  });
}

// Create transaction volume chart
function createTransactionVolumeChart(data) {
  const ctx = document.getElementById('transactionVolumeChart');
  if (!ctx) return;
  
  // Guard: ne pas rendre si les données transactions n'ont pas encore chargé
  if (!data.transactions) return;
  
  if (charts.transactionVolume) {
    charts.transactionVolume.destroy();
  }
  
  // Use real daily transactions data from backend
  const dailyTransactions = data.transactions?.daily_volume || [];
  
  if (!dailyTransactions || dailyTransactions.length === 0) {
    // Afficher un message si pas de données
    // Pas de données de transaction - ne pas détruire le canvas
    return;
  }
  
  const days = dailyTransactions.map((item) => item.date || '');
  const transactionData = dailyTransactions.map(item => Number(item.transactions || item.count || 0));
  
  // Build cumulative series
  const cumulativeTransactions = transactionData.reduce((acc, val, idx) => {
    acc.push((acc[idx - 1] || 0) + val);
    return acc;
  }, []);
  
  charts.transactionVolume = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: days,
      datasets: [{
        label: 'Daily Transactions',
        data: transactionData,
        backgroundColor: getThemeColor('accent'),
        borderRadius: 4,
        
      },{
        type: 'line',
        label: 'Cumulative (preview)',
        data: new Array(transactionData.length).fill(null) // hidden in this chart
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: { y: { beginAtZero: true } }
    }
  });

  const cumCtx = document.getElementById('transactionVolumeCumulativeChart');
  if (cumCtx) {
    if (charts.transactionVolumeCumulative) charts.transactionVolumeCumulative.destroy();
    charts.transactionVolumeCumulative = new Chart(cumCtx, {
      type: 'line',
      data: { labels: days, datasets: [{ label: 'Cumulative Transactions', data: cumulativeTransactions, borderColor: getThemeColor('primary'), backgroundColor: getThemeColor('primaryRgba'), fill: false, tension: 0.3 }] },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'top' } }, scales: { y: { beginAtZero: true } } }
    });
  }
}

// Create transacting users chart
function createTransactingUsersChart(data) {
  const ctx = document.getElementById('transactingUsersChart');
  if (!ctx) return;
  
  // Guard: ne pas rendre si les données transactions n'ont pas encore chargé
  if (!data.transactions) return;
  
  if (charts.transactingUsers) {
    charts.transactingUsers.destroy();
  }
  
  // Use real daily transactions data from backend to extract users
  const dailyTransactions = data.transactions?.daily_volume || [];
  
  if (!dailyTransactions || dailyTransactions.length === 0) {
    // Afficher un message si pas de données
    // Pas de données d'utilisateurs - ne pas détruire le canvas
    return;
  }
  
  const days = dailyTransactions.map((item) => item.date || '');
  const userData = dailyTransactions.map(item => Number(item.users || item.unique_users || 0));
  
  const cumulativeUsers = userData.reduce((acc, val, idx) => {
    acc.push((acc[idx - 1] || 0) + val);
    return acc;
  }, []);
  
  charts.transactingUsers = new Chart(ctx, {
    type: 'line',
    data: {
      labels: days,
      datasets: [{
        label: 'Daily Transacting Users',
        data: userData,
        borderColor: getThemeColor('warning'),
        backgroundColor: getThemeColor('warning') === '#3b82f6' ? 'rgba(59, 130, 246, 0.1)' : 'rgba(245, 158, 11, 0.1)',
        fill: true,
        tension: 0.4
      },{
        type: 'line',
        label: 'Cumulative (preview)',
        data: new Array(userData.length).fill(null)
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: { y: { beginAtZero: true } }
    }
  });

  const cumUsersCtx = document.getElementById('transactingUsersCumulativeChart');
  if (cumUsersCtx) {
    if (charts.transactingUsersCumulative) charts.transactingUsersCumulative.destroy();
    charts.transactingUsersCumulative = new Chart(cumUsersCtx, {
      type: 'line',
      data: { labels: days, datasets: [{ label: 'Cumulative Users', data: cumulativeUsers, borderColor: getThemeColor('primary'), backgroundColor: getThemeColor('primaryRgba'), fill: false, tension: 0.3 }] },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'top' } }, scales: { y: { beginAtZero: true } } }
    });
  }
}

// Create top merchants chart
function createTopMerchantsChart(data) {
  const ctx = document.getElementById('topMerchantsChart');
  if (!ctx) return;
  
  if (charts.topMerchants) {
    charts.topMerchants.destroy();
  }
  
  // data.merchants peut être un objet {data: [...], categories: [...]} ou un tableau
  // Guard: ne pas rendre si les données merchants n'ont pas encore chargé
  if (!data.merchants) return;
  
  const merchantsRaw = data.merchants || {};
  const merchants = Array.isArray(merchantsRaw) ? merchantsRaw : (Array.isArray(merchantsRaw.data) ? merchantsRaw.data : []);
  
  if (!merchants || merchants.length === 0) {
    // Afficher un message si pas de données
    // Pas de marchands - ne pas détruire le canvas
    return;
  }
  
  const top10 = merchants.slice(0, 10);
  const merchantNames = top10.map(m => m.name || m.merchant_name || 'Sans nom');
  const merchantValues = top10.map(m => Number(m.current || m.transactions || 0));
  
  charts.topMerchants = new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: merchantNames,
      datasets: [{
        data: merchantValues,
        backgroundColor: [
          getThemeColor('primary'),
          getThemeColor('accent'),
          getThemeColor('success'),
          getThemeColor('warning')
        ],
        borderWidth: 2,
        borderColor: '#ffffff'
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: 'bottom'
        }
      }
    }
  });
}

// Create category chart (dynamique)
function createCategoryChart(data) {
  const ctx = document.getElementById('categoryChart');
  if (!ctx) return;
  
  // Guard: ne pas rendre si les données merchants n'ont pas encore chargé
  if (!data.merchants) return;
  
  if (charts.category) {
    charts.category.destroy();
  }
  
  const dist = data.categoryDistribution || [];
  
  if (!dist || dist.length === 0) {
    // Afficher un message si pas de données
    // Pas de catégories - ne pas détruire le canvas
    return;
  }
  
  const top10 = dist.slice(0, 10);
  // Utiliser transactions pour le volume, mais afficher aussi le nombre de marchands dans le label
  const labels = top10.map(d => `${d.category || 'Sans catégorie'} (${d.merchants ?? d.merchants_count ?? 0} marchands)`);
  // Utiliser transactions pour représenter le volume par catégorie
  const values = top10.map(d => Number(d.transactions ?? d.transaction_count ?? d.count ?? 0));
  const colors = ['#E30613','#3b82f6','#10b981','#f59e0b','#8b5cf6','#06b6d4','#f97316','#64748b','#ec4899','#14b8a6'];
  
  charts.category = new Chart(ctx, {
    type: 'pie',
    data: {
      labels: labels,
      datasets: [{
        data: values,
        backgroundColor: colors.slice(0, labels.length),
        borderWidth: 2,
        borderColor: '#ffffff'
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { position: 'bottom' },
        tooltip: { enabled: true }
      }
    }
  });
}

// Create comparison chart
function createComparisonChart(data) {
  const ctx = document.getElementById('comparisonChart');
  if (!ctx) return;
  
  if (charts.comparison) {
    charts.comparison.destroy();
  }
  
  const k = data?.kpis || {};
  const safe = (obj) => (obj && typeof obj.current !== 'undefined') ? obj : { current: 0, previous: 0 };
  const activated = safe(k.activatedSubscriptions);
  const transactions = safe(k.totalTransactions);
  const merchants = safe(k.activeMerchants);
  const conversion = safe(k.conversionRate);
  // Retention: préférer retentionRateTrue s'il existe, sinon retentionRate
  const retention = safe(k.retentionRateTrue || k.retentionRate);
  
  const currentRaw = [
    activated.current,
    transactions.current,
    merchants.current,
    conversion.current,
    retention.current
  ];
  const previousRaw = [
    activated.previous,
    transactions.previous,
    merchants.previous,
    conversion.previous,
    retention.previous
  ];
  
  const current = [];
  const previous = [];
  for (let i = 0; i < currentRaw.length; i++) {
    const denom = Math.max(Number(currentRaw[i]) || 0, Number(previousRaw[i]) || 0);
    if (denom <= 0) {
      current.push(0);
      previous.push(0);
    } else {
      current.push(+((Number(currentRaw[i]) || 0) * 100 / denom).toFixed(1));
      previous.push(+((Number(previousRaw[i]) || 0) * 100 / denom).toFixed(1));
    }
  }
  
  charts.comparison = new Chart(ctx, {
    type: 'radar',
    data: {
      labels: ['Subscriptions', 'Transactions', 'Merchants', 'Conversion', 'Retention'],
      datasets: [
        {
          label: 'Current Period',
          data: current,
          borderColor: getThemeColor('primary'),
          backgroundColor: getThemeColor('primaryRgba').replace('0.1', '0.2'),
          pointBackgroundColor: getThemeColor('primary')
        },
        {
          label: 'Previous Period',
          data: previous,
          borderColor: '#64748b',
          backgroundColor: 'rgba(100, 116, 139, 0.2)',
          pointBackgroundColor: '#64748b'
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: 'top'
        }
      },
      scales: {
        r: {
          beginAtZero: true,
          max: 100
        }
      }
    }
  });
}

// Nouveaux graphiques pour les KPIs avancés

// Graphique des activations par canal
function createActivationsByChannelChart(data) {
  const ctx = document.getElementById('activationsByChannelChart');
  if (!ctx) return;
  
  if (charts.activationsByChannel) {
    charts.activationsByChannel.destroy();
  }
  
  const activations = data.subscriptions?.activations_by_channel || {};
  // Support both old (numbers) and new (objects with current/previous/change) shapes
  const cbVal = (activations.cb && typeof activations.cb === 'object') ? (activations.cb.current ?? 0) : (activations.cb ?? 0);
  const rechargeVal = (activations.recharge && typeof activations.recharge === 'object') ? (activations.recharge.current ?? 0) : (activations.recharge ?? 0);
  const phoneVal = (activations.phone_balance && typeof activations.phone_balance === 'object') ? (activations.phone_balance.current ?? 0) : (activations.phone_balance ?? 0);
  const otherVal = (activations.other && typeof activations.other === 'object') ? (activations.other.current ?? 0) : (activations.other ?? 0);

  console.log('📊 Activations By Channel Chart:', { activations, cbVal, rechargeVal, phoneVal, otherVal });

  charts.activationsByChannel = new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: ['Carte Bancaire', 'Recharge', 'Solde Téléphonique', 'Autres'],
      datasets: [{
        data: [cbVal, rechargeVal, phoneVal, otherVal],
        backgroundColor: [
          getThemeColor('primary'),
          '#10b981',
          '#f59e0b',
          '#6b7280'
        ],
        borderWidth: 2,
        borderColor: '#fff'
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: 'bottom'
        }
      }
    }
  });
}

// Graphique de distribution des plans
function createPlanDistributionChart(data) {
  const ctx = document.getElementById('planDistributionChart');
  if (!ctx) return;
  
  if (charts.planDistribution) {
    charts.planDistribution.destroy();
  }
  
  const plans = data.subscriptions?.plan_distribution || {};
  const dailyVal = (plans.daily && typeof plans.daily === 'object') ? (plans.daily.current ?? 0) : (plans.daily ?? 0);
  const monthlyVal = (plans.monthly && typeof plans.monthly === 'object') ? (plans.monthly.current ?? 0) : (plans.monthly ?? 0);
  const annualVal = (plans.annual && typeof plans.annual === 'object') ? (plans.annual.current ?? 0) : (plans.annual ?? 0);
  const otherPlanVal = (plans.other && typeof plans.other === 'object') ? (plans.other.current ?? 0) : (plans.other ?? 0);
  
  console.log('📊 Plan Distribution Chart:', { plans, dailyVal, monthlyVal, annualVal, otherPlanVal });
  
  charts.planDistribution = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: ['Journalier', 'Mensuel', 'Annuel', 'Autres'],
      datasets: [{
        label: 'Nombre d\'abonnements',
        data: [dailyVal, monthlyVal, annualVal, otherPlanVal],
        backgroundColor: [
          getThemeColor('primary'),
          '#10b981',
          '#f59e0b',
          '#6b7280'
        ],
        borderRadius: 4
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
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

// Graphique d'analyse de cohortes
function createCohortsAnalysisChart(data) {
  const ctx = document.getElementById('cohortsAnalysisChart');
  if (!ctx) return;
  
  if (charts.cohortsAnalysis) {
    charts.cohortsAnalysis.destroy();
  }
  
  const cohorts = data.subscriptions?.cohorts || [];
  
  // Si pas de données, créer un graphique vide avec des labels par défaut
  const months = cohorts.length > 0 
    ? cohorts.map(c => c.month)
    : ['Aucune donnée'];
  const survivalD30 = cohorts.length > 0
    ? cohorts.map(c => c.survival_d30 || 0)
    : [0];
  const survivalD60 = cohorts.length > 0
    ? cohorts.map(c => c.survival_d60 || 0)
    : [0];
  
  console.log('📊 Cohorts Analysis Chart:', { cohorts_count: cohorts.length, months, survivalD30, survivalD60 });
  
  charts.cohortsAnalysis = new Chart(ctx, {
    type: 'line',
    data: {
      labels: months,
      datasets: [
        {
          label: 'Survie J+30 (%)',
          data: survivalD30,
          borderColor: getThemeColor('primary'),
          backgroundColor: getThemeColor('primaryRgba'),
          fill: false,
          tension: 0.4
        },
        {
          label: 'Survie J+60 (%)',
          data: survivalD60,
          borderColor: '#10b981',
          backgroundColor: 'rgba(16, 185, 129, 0.1)',
          fill: false,
          tension: 0.4
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: 'top'
        }
      },
      scales: {
        y: {
          beginAtZero: true,
          max: 100,
          ticks: {
            callback: function(value) {
              return value + '%';
            }
          }
        }
      }
    }
  });
}

// Update tables
