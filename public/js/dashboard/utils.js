/**
 * Dashboard - Module Utilitaires
 * Fonctions helper partagées entre tous les modules
 */

function calculateChange(current, previous) {
  if (!previous || previous === 0) return 0;
  return ((current - previous) / previous) * 100;
}

/**
 * Formate un nombre avec espaces pour milliers, virgule pour décimales
 * @param {number} value - Nombre à formater
 * @param {number} decimals - Nombre de décimales (défaut 3)
 * @returns {string} - Nombre formaté (ex: "20 238,000")
 */
function formatNumber(value, decimals = 3) {
  if (value === null || value === undefined || isNaN(value)) return '0,000';
  
  const num = Number(value);
  const fixed = num.toFixed(decimals);
  
  // Séparer partie entière et décimale
  const [integerPart, decimalPart] = fixed.split('.');
  
  // Ajouter espaces pour les milliers
  const withSpaces = integerPart.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
  
  // Remplacer le point par une virgule pour les décimales
  return decimalPart ? `${withSpaces},${decimalPart}` : withSpaces;
}

/**
 * Formate un pourcentage avec virgule au lieu de point
 * @param {number} value - Valeur du pourcentage
 * @param {number} decimals - Nombre de décimales (défaut 3)
 * @returns {string} - Pourcentage formaté (ex: "9,290%")
 */
function formatPercentage(value, decimals = 3) {
  if (value === null || value === undefined || isNaN(value)) return '0,000%';
  return formatNumber(value, decimals) + '%';
}

function getServiceIcon(serviceType) {
  const icons = {
    'SUBSCRIPTION': '📱',
    'PROMOTION': '🎯',
    'NOTIFICATION': '🔔',
    'default': '📞'
  };
  return icons[serviceType] || icons.default;
}

function getStatusIcon(status) {
  const icons = {
    'ACTIVE': '✅',
    'INACTIVE': '❌',
    'PENDING': '⏳',
    'default': '❓'
  };
  return icons[status] || icons.default;
}

function formatDate(dateString) {
  if (!dateString) return 'N/A';
  const date = new Date(dateString);
  return date.toLocaleDateString('fr-FR') + ' ' + date.toLocaleTimeString('fr-FR', { 
    hour: '2-digit', 
    minute: '2-digit' 
  });
}

