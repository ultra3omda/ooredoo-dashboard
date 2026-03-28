@php
    $theme = $theme ?? 'club_privileges';
    $isOoredoo = $theme === 'ooredoo';
    $isClubPrivileges = $theme === 'club_privileges';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>{{ $isOoredoo ? 'Ooredoo Privileges' : 'Club Privilèges' }} - Comprehensive Performance Dashboard</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Manrope:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <style>
    :root {
      --brand-primary: #6C4BA0;
      --brand-secondary: #D4A843;
      --theme-name: 'Club Privilèges';
      --brand-dark: #FFFFFF;
      --bg: #0D0A1A;
      --card: #161131;
      --card-hover: #1E1745;
      --muted: #A1A1AA;
      --success: #10b981;
      --warning: #f59e0b;
      --danger: #ef4444;
      --accent: #D4A843;
      --border: #2A2350;
      --brand-red: var(--brand-primary);
      --text-primary: #FFFFFF;
      --text-secondary: #A1A1AA;
      --glass-bg: rgba(22, 17, 49, 0.8);
    }
    
    * { box-sizing: border-box; }
    html, body { 
      margin: 0; 
      padding: 0; 
      background: var(--bg); 
      color: var(--text-primary); 
      font-family: 'Manrope', 'Inter', system-ui, -apple-system, sans-serif;
      line-height: 1.5;
    }

    /* Subtle noise texture overlay */
    body::before {
      content: '';
      position: fixed;
      inset: 0;
      background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.04'/%3E%3C/svg%3E");
      pointer-events: none;
      z-index: 0;
      opacity: 0.5;
    }
    
    .container { 
      max-width: 1600px; 
      margin: 0 auto; 
      padding: 12px 24px; 
      position: relative;
      z-index: 1;
    }
    
    /* Header - Crystal Glass */
    .header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      background: rgba(13, 10, 26, 0.85);
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
      padding: 12px 20px;
      border-radius: 16px;
      margin-bottom: 16px;
      border: 1px solid rgba(255,255,255,0.06);
      box-shadow: 0 4px 24px rgba(0,0,0,0.4);
      width: 100%;
      box-sizing: border-box;
    }
    
    .header-left {
      display: flex;
      align-items: center;
      gap: 16px;
    }
    
    .logo {
      width: 120px;
      height: auto;
    }
    
    .header h1 {
      font-size: 20px;
      font-weight: 700;
      margin: 0;
      color: var(--text-primary);
      font-family: 'Outfit', sans-serif;
      letter-spacing: -0.5px;
    }
    
    .header-right {
      font-size: 14px;
      color: var(--muted);
      display: flex;
      align-items: center;
      gap: 16px;
    }
    
    .user-menu {
      display: flex;
      align-items: center;
      gap: 12px;
      background: rgba(255,255,255,0.04);
      padding: 6px 14px;
      border-radius: 10px;
      border: 1px solid var(--border);
    }
    
    .user-info {
      display: flex;
      flex-direction: column;
      align-items: flex-end;
    }
    
    .user-name {
      font-weight: 600;
      color: var(--text-primary);
      font-size: 13px;
    }
    
    .user-role {
      font-size: 11px;
      color: var(--muted);
    }
    
    .admin-btn {
      background: var(--brand-red);
      color: white;
      padding: 6px 12px;
      border-radius: 6px;
      font-size: 12px;
      font-weight: 500;
      text-decoration: none;
      transition: all 0.2s ease;
    }
    
    .admin-btn:hover {
      background: #5B3FA0;
      text-decoration: none;
    }
    
    .logout-btn {
      background: transparent;
      border: 1px solid var(--border);
      color: var(--muted);
      padding: 6px 12px;
      border-radius: 6px;
      font-size: 12px;
      cursor: pointer;
      transition: all 0.2s ease;
    }
    
    .logout-btn:hover {
      border-color: var(--danger);
      color: var(--danger);
    }
    
    /* Navigation Tabs */
    /* ===== Navigation - Dark Floating Pill Menu ===== */
    .nav-wrapper {
      background: rgba(22, 17, 49, 0.6);
      backdrop-filter: blur(16px);
      -webkit-backdrop-filter: blur(16px);
      border-radius: 50px;
      padding: 4px;
      margin-bottom: 16px;
      border: 1px solid rgba(255,255,255,0.05);
      box-shadow: 0 2px 16px rgba(0,0,0,0.3);
      position: sticky;
      top: 8px;
      z-index: 100;
    }
    .nav-tabs {
      display: flex;
      align-items: center;
      gap: 2px;
      overflow-x: auto;
      scrollbar-width: none;
      -ms-overflow-style: none;
      -webkit-overflow-scrolling: touch;
      padding-bottom: 2px;
    }
    .nav-tabs::-webkit-scrollbar { display: none; }

    .nav-group {
      display: flex;
      align-items: center;
      gap: 2px;
      flex-shrink: 0;
    }
    .nav-divider {
      width: 1px;
      height: 20px;
      background: rgba(255,255,255,0.08);
      margin: 0 4px;
      flex-shrink: 0;
    }
    .nav-group-label {
      font-size: 0.6rem;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: var(--muted);
      padding: 0 6px;
      white-space: nowrap;
      opacity: 0.7;
      display: none;
    }
    
    .nav-tab {
      padding: 8px 16px;
      text-align: center;
      border-radius: 50px;
      cursor: pointer;
      font-weight: 500;
      font-size: 0.82rem;
      transition: all 0.25s ease;
      border: none;
      background: transparent;
      color: var(--muted);
      flex-shrink: 0;
      white-space: nowrap;
      min-width: fit-content;
      position: relative;
    }
    
    .nav-tab.active {
      background: rgba(255,255,255,0.1);
      color: #FFFFFF;
      box-shadow: inset 0 0 0 1px rgba(255,255,255,0.1);
    }
    
    .nav-tab:hover:not(.active) {
      background: rgba(255,255,255,0.05);
      color: #FFFFFF;
    }
    
    .nav-tab .tab-icon {
      margin-right: 4px;
      font-size: 0.8rem;
    }

    /* Bouton flottant Agent IA - Glassmorphism */
    .ai-fab {
      position: fixed;
      bottom: 24px;
      right: 24px;
      width: 52px;
      height: 52px;
      border-radius: 50%;
      background: rgba(255,255,255,0.05);
      backdrop-filter: blur(24px);
      color: white;
      border: 1px solid rgba(255,255,255,0.1);
      cursor: pointer;
      box-shadow: 0 8px 32px rgba(108, 75, 160, 0.15);
      z-index: 1000;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: all 0.3s ease;
    }
    .ai-fab:hover {
      transform: scale(1.08);
      box-shadow: 0 8px 40px rgba(108, 75, 160, 0.3);
      border-color: rgba(108, 75, 160, 0.3);
    }
    .ai-fab svg { width: 24px; height: 24px; }
    .ai-fab-tooltip {
      position: absolute;
      right: 60px;
      background: var(--brand-dark);
      color: white;
      padding: 6px 12px;
      border-radius: 6px;
      font-size: 0.78rem;
      white-space: nowrap;
      opacity: 0;
      pointer-events: none;
      transition: opacity 0.2s;
    }
    .ai-fab:hover .ai-fab-tooltip { opacity: 1; }

    /* Panel Agent IA (slide-in) */
    .ai-panel-overlay {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,0.3);
      z-index: 1001;
      backdrop-filter: blur(2px);
    }
    .ai-panel-overlay.open { display: block; }
    .ai-panel {
      position: fixed;
      top: 0;
      right: -480px;
      width: 460px;
      max-width: 90vw;
      height: 100vh;
      background: #0A0A1A;
      z-index: 1002;
      transition: right 0.3s ease;
      box-shadow: -4px 0 32px rgba(0,0,0,0.4);
      display: flex;
      flex-direction: column;
      border-left: 1px solid var(--border);
    }
    .ai-panel.open { right: 0; }
    .ai-panel-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 16px 20px;
      border-bottom: 1px solid var(--border);
    }
    .ai-panel-header h3 {
      margin: 0;
      font-size: 1rem;
      font-weight: 600;
    }
    .ai-panel-close {
      background: none;
      border: none;
      cursor: pointer;
      padding: 4px;
      color: var(--muted);
      font-size: 1.2rem;
    }
    .ai-panel-body {
      flex: 1;
      overflow-y: auto;
    }

    /* Lien diagnostic dans Timwe */
    .timwe-diagnostic-link {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 6px 14px;
      background: rgba(255,255,255,0.04);
      border: 1px solid var(--border);
      border-radius: 8px;
      color: var(--muted);
      font-size: 0.82rem;
      font-weight: 500;
      text-decoration: none;
      transition: all 0.2s;
    }
    .timwe-diagnostic-link:hover {
      background: var(--brand-primary);
      color: white;
      border-color: var(--brand-primary);
    }
    
    /* Tab Content */
    .tab-content {
      display: none;
      overflow-x: hidden;
      max-width: 100%;
    }
    
    .tab-content.active {
      display: block;
    }
    
    /* Filters */
    .filters {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
      gap: 16px;
      margin-bottom: 24px;
    }
    
    /* Responsive filters pour mobile */
    @media (max-width: 600px) {
      .filters {
        grid-template-columns: 1fr;
        gap: 12px;
      }
    }
    
    .filter-card {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 10px;
      padding: 16px;
    }
    
    .filter-label {
      font-size: 12px;
      color: var(--muted);
      margin-bottom: 8px;
      font-weight: 500;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    .filter-value {
      font-weight: 600;
      font-size: 14px;
    }
    
    /* Grid Layout */
    .grid {
      display: grid;
      grid-template-columns: repeat(12, 1fr);
      gap: 20px;
      margin-bottom: 24px;
    }
    
    .card {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 16px;
      padding: 20px;
      box-shadow: 0 2px 12px rgba(0,0,0,0.2);
      transition: all 0.3s ease;
      position: relative;
      overflow: hidden;
    }
    
    .card:hover {
      border-color: rgba(255,255,255,0.08);
    }
    
    /* KPI Cards */
    .kpi-card {
      grid-column: span 3;
      text-align: center;
    }
    
    /* Tracing beam effect for first KPI */
    .kpi-card:first-child {
      border-color: rgba(108, 75, 160, 0.2);
      box-shadow: 0 0 24px rgba(108, 75, 160, 0.06), inset 0 1px 0 rgba(108, 75, 160, 0.1);
    }
    
    .kpi-title {
      font-size: 0.68rem;
      color: var(--muted);
      margin-bottom: 8px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 1px;
      font-family: 'Manrope', sans-serif;
    }
    
    .kpi-value {
      font-size: 2.2rem;
      font-weight: 700;
      margin-bottom: 4px;
      color: var(--text-primary);
      font-family: 'Outfit', sans-serif;
      letter-spacing: -1px;
      line-height: 1.1;
    }
    
    .kpi-delta {
      font-size: 12px;
      font-weight: 500;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 4px;
    }
    
    .delta-positive { color: var(--success); }
    .delta-negative { color: var(--danger); }
    .delta-neutral { color: var(--muted); }
    
    /* Chart Cards */
    .chart-card {
      grid-column: span 6;
      min-height: 350px;
    }
    
    .chart-card.full-width {
      grid-column: span 12;
    }
    
    .chart-title {
      font-size: 14px;
      font-weight: 600;
      margin-bottom: 16px;
      color: var(--text-primary);
      font-family: 'Outfit', sans-serif;
    }
    
    .chart-container {
      height: 300px;
      position: relative;
    }
    
    /* Table */
    .table-card {
      grid-column: span 12;
      overflow-x: auto; /* Scroll horizontal sur mobile */
    }
    
    /* Table responsive wrapper */
    .table-wrapper {
      overflow-x: auto;
      -webkit-overflow-scrolling: touch; /* Smooth scrolling sur iOS */
    }
    
    .enhanced-table {
      min-width: 600px; /* Largeur minimale pour éviter le rétrécissement excessif */
    }
    
    .table-container {
      overflow-x: auto;
    }
    
    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 14px;
    }
    
    th, td {
      padding: 12px 16px;
      text-align: left;
      border-bottom: 1px solid var(--border);
    }
    
    th {
      background: rgba(255,255,255,0.03);
      font-weight: 600;
      color: var(--muted);
      font-size: 11px;
      text-transform: uppercase;
      letter-spacing: 0.8px;
      font-family: 'Manrope', sans-serif;
    }
    
    tr:hover {
      background: rgba(255,255,255,0.02);
    }
    
    td { color: var(--text-secondary); }
    
    /* Badges */
    .badge {
      display: inline-flex;
      align-items: center;
      padding: 4px 8px;
      border-radius: 6px;
      font-size: 12px;
      font-weight: 500;
    }
    
    .badge-success { background: rgba(16, 185, 129, 0.15); color: #10b981; }
    .badge-warning { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }
    .badge-danger { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
    .badge-info { background: rgba(212, 168, 67, 0.1); color: #D4A843; }
    .badge-secondary { background: rgba(255,255,255,0.05); color: var(--muted); }

    /* Styles pour la pagination */
    .subscriptions-pagination {
      margin-top: 16px;
      padding: 16px;
      border-top: 1px solid var(--border);
      display: flex;
      flex-direction: column;
      gap: 12px;
      align-items: center;
    }

    .pagination-controls {
      display: flex;
      gap: 8px;
      align-items: center;
    }

    .pagination-btn {
      padding: 8px 12px;
      border: 1px solid var(--border);
      background: var(--card-bg);
      color: var(--text);
      border-radius: 6px;
      cursor: pointer;
      font-size: 14px;
      transition: all 0.2s ease;
      min-width: 40px;
    }

    .pagination-btn:hover {
      background: var(--hover-bg);
      border-color: var(--brand-red);
    }

    .pagination-btn.active {
      background: var(--brand-red);
      color: white;
      border-color: var(--brand-red);
    }

    .pagination-btn:disabled {
      opacity: 0.5;
      cursor: not-allowed;
    }

    .pagination-info {
      font-size: 14px;
      color: var(--muted);
      text-align: center;
    }
    
    /* Progress Bar */
    .progress-bar {
      width: 100%;
      height: 8px;
      background: #e2e8f0;
      border-radius: 4px;
      overflow: hidden;
      margin-top: 8px;
    }
    
    .progress-fill {
      height: 100%;
      background: var(--brand-red);
      transition: width 0.3s ease;
    }
    
    /* Insights Section */
    .insights-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 20px;
    }
    
    .insight-card {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 20px;
    }
    
    .insight-title {
      font-size: 16px;
      font-weight: 600;
      margin-bottom: 12px;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    
    .insight-list {
      list-style: none;
      padding: 0;
      margin: 0;
    }
    
    .insight-list li {
      padding: 8px 0;
      border-bottom: 1px solid #f1f5f9;
      font-size: 14px;
    }
    
    .insight-list li:last-child {
      border-bottom: none;
    }
    
    /* Loading State */
    .loading {
      display: flex;
      align-items: center;
      justify-content: center;
      height: 200px;
      color: var(--muted);
    }
    
    .spinner {
      width: 20px;
      height: 20px;
      border: 2px solid var(--border);
      border-top: 2px solid var(--brand-red);
      border-radius: 50%;
      animation: spin 1s linear infinite;
      margin-right: 8px;
    }
    
    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }
    
    
    /* Date Input Styles */
    .date-input {
      width: 100%;
      padding: 8px 12px;
      border: 1px solid var(--border);
      border-radius: 6px;
      font-size: 14px;
      font-family: inherit;
      background: rgba(255,255,255,0.04);
      transition: border-color 0.2s;
    }
    
    .date-input:focus {
      outline: none;
      border-color: var(--brand-red);
      box-shadow: 0 0 0 3px rgba(108, 75, 160, 0.1);
    }
    
    .btn-refresh {
      width: 100%;
      padding: 8px 12px;
      background: var(--brand-primary);
      color: white;
      border: none;
      border-radius: 8px;
      font-size: 14px;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.2s;
    }
    
    .btn-refresh:hover {
      background: #5B3FA0;
      box-shadow: 0 4px 16px rgba(108, 75, 160, 0.3);
    }
    
    .btn-refresh:active {
      transform: translateY(1px);
    }
    
    .btn-refresh:disabled {
      background: rgba(255,255,255,0.1);
      color: var(--muted);
      cursor: not-allowed;
      transform: none;
    }
    
    /* Loading and notification styles */
    .loading-overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(13, 10, 26, 0.7);
      backdrop-filter: blur(4px);
      display: flex;
      justify-content: center;
      align-items: center;
      z-index: 9999;
    }
    
    .loading-spinner {
      background: var(--card);
      padding: 30px;
      border-radius: 16px;
      text-align: center;
      box-shadow: 0 10px 40px rgba(0,0,0,0.5);
      border: 1px solid var(--border);
      color: var(--text-primary);
    }
    
    .spinner {
      border: 3px solid rgba(255,255,255,0.1);
      border-top: 3px solid var(--brand-primary);
      border-radius: 50%;
      width: 36px;
      height: 36px;
      animation: spin 0.8s linear infinite;
      margin: 0 auto 15px;
    }
    
    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }
    
    /* Skeleton loading styles */
    .skeleton-text {
      height: 24px;
      background: linear-gradient(90deg, rgba(255,255,255,0.03) 25%, rgba(255,255,255,0.06) 50%, rgba(255,255,255,0.03) 75%);
      background-size: 200% 100%;
      animation: skeleton-loading 1.5s infinite;
      border-radius: 4px;
      width: 80%;
    }
    
    .skeleton-text-small {
      height: 16px;
      background: linear-gradient(90deg, rgba(255,255,255,0.03) 25%, rgba(255,255,255,0.06) 50%, rgba(255,255,255,0.03) 75%);
      background-size: 200% 100%;
      animation: skeleton-loading 1.5s infinite;
      border-radius: 4px;
      width: 60%;
    }
    
    @keyframes skeleton-loading {
      0% {
        background-position: 200% 0;
      }
      100% {
        background-position: -200% 0;
      }
    }
    
    .notification {
      position: fixed;
      top: 20px;
      right: 20px;
      padding: 12px 18px;
      border-radius: 10px;
      z-index: 1000;
      max-width: 400px;
      box-shadow: 0 8px 32px rgba(0,0,0,0.4);
      animation: slideIn 0.3s ease;
      backdrop-filter: blur(16px);
      font-size: 14px;
    }
    
    .notification.error {
      background: rgba(239, 68, 68, 0.15);
      color: #fca5a5;
      border-left: 3px solid #ef4444;
    }
    
    .notification.success {
      background: rgba(16, 185, 129, 0.15);
      color: #6ee7b7;
      border-left: 3px solid #10b981;
    }
    
    .notification.info {
      background: rgba(212, 168, 67, 0.1);
      color: #D4A843;
      border-left: 3px solid #D4A843;
    }
    
    @keyframes slideIn {
      from { transform: translateX(100%); opacity: 0; }
      to { transform: translateX(0); opacity: 1; }
    }
    
    /* Operator selector styling */
    .operator-select {
      width: 100%;
      padding: 8px 12px;
      border: 2px solid var(--border);
      border-radius: 8px;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      font-size: 14px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
    }
    
    .operator-select:hover {
      border-color: var(--brand-red);
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(108, 75, 160, 0.15);
    }
    
    .operator-select:focus {
      outline: none;
      border-color: var(--brand-red);
      box-shadow: 0 0 0 3px rgba(108, 75, 160, 0.1);
    }
    
    .operator-select option {
      background: #1E1745;
      color: var(--text-primary);
      padding: 8px;
    }
    
    /* Enhanced insights styling */
    .insight-item {
      display: flex;
      align-items: flex-start;
      gap: 10px;
      padding: 8px 0;
      border-bottom: 1px solid #f1f5f9;
    }
    
    .insight-item:last-child {
      border-bottom: none;
    }
    
    .insight-icon {
      font-size: 16px;
      margin-top: 2px;
      flex-shrink: 0;
    }
    
    .insight-text {
      flex: 1;
      line-height: 1.4;
    }
    
    .high-priority {
      background: rgba(239, 68, 68, 0.1);
      padding: 8px;
      border-radius: 6px;
      border-left: 3px solid #ef4444;
    }
    
    .medium-priority {
      background: rgba(245, 158, 11, 0.1);
      padding: 8px;
      border-radius: 6px;
      border-left: 3px solid #f59e0b;
    }
    
    .action-item {
      background: rgba(59, 130, 246, 0.1);
      padding: 8px;
      border-radius: 6px;
      border-left: 3px solid #3b82f6;
    }
    
    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    
    /* Enhanced Filters Bar */
    .enhanced-filters-bar {
      background: var(--glass-bg);
      backdrop-filter: blur(16px);
      -webkit-backdrop-filter: blur(16px);
      border: 1px solid var(--border);
      border-radius: 16px;
      padding: 16px 20px;
      margin-bottom: 20px;
      box-shadow: 0 4px 24px rgba(0,0,0,0.2);
    }
    
    /* Mobile responsive filters */
    @media (max-width: 768px) {
      .enhanced-filters-bar {
        padding: 12px;
        margin-bottom: 16px;
      }
    }

    .date-selection-section {
      margin-bottom: 24px;
    }

    .section-title {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 15px;
      font-weight: 600;
      color: var(--text-primary);
      margin-bottom: 16px;
      font-family: 'Outfit', sans-serif;
    }

    .section-icon {
      font-size: 20px;
    }

    .date-periods {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 24px;
    }
    
    /* Responsive date periods pour mobile */
    @media (max-width: 900px) {
      .date-periods {
        grid-template-columns: 1fr;
        gap: 20px;
      }
    }

    .date-period {
      background: rgba(255,255,255,0.03);
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 16px;
    }

    .period-header {
      display: flex;
      align-items: center;
      gap: 8px;
      margin-bottom: 16px;
    }

    .period-icon {
      font-size: 16px;
    }

    .period-label {
      font-weight: 600;
      color: var(--text-primary);
      font-size: 0.85rem;
    }

    .date-inputs {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 12px;
    }
    
    /* Mobile responsive date inputs */
    @media (max-width: 600px) {
      .date-inputs {
        flex-direction: column;
        gap: 8px;
        align-items: stretch;
      }
      
      .date-separator {
        text-align: center;
        order: 1; /* Place separator between inputs */
      }
      
      /* Multi-select mobile responsive */
      .multi-select-dropdown {
        max-height: 200px;
      }
      
      .checkbox-label {
        padding: 10px 0;
        font-size: 16px; /* Plus grand pour mobile */
      }
      
      .checkmark {
        width: 18px;
        height: 18px;
      }
    }

    .date-input-group {
      flex: 1;
    }

    .date-input-group label {
      display: block;
      font-size: 12px;
      color: var(--muted);
      margin-bottom: 4px;
      font-weight: 500;
    }

    .enhanced-date-input {
      width: 100%;
      padding: 10px 12px;
      border: 1px solid var(--border);
      border-radius: 8px;
      font-size: 14px;
      background: rgba(255,255,255,0.04);
      color: var(--text-primary);
      transition: all 0.2s;
    }

    .enhanced-date-input:focus {
      outline: none;
      border-color: var(--brand-primary);
      box-shadow: 0 0 0 3px rgba(108, 75, 160, 0.15);
      border-color: var(--brand-red);
      box-shadow: 0 0 0 3px rgba(108, 75, 160, 0.1);
    }

    .date-separator {
      color: var(--muted);
      font-weight: 500;
      margin-top: 20px;
    }

    .period-display {
      font-size: 13px;
      color: var(--muted);
      font-style: italic;
      text-align: center;
    }

    .controls-section {
      display: flex;
      align-items: flex-end;
      gap: 24px;
      flex-wrap: wrap;
    }

    .control-group {
      min-width: 200px;
    }

    .control-label {
      display: flex;
      align-items: center;
      gap: 6px;
      font-size: 13px;
      font-weight: 600;
      color: var(--text-primary);
      margin-bottom: 8px;
    }

    .control-icon {
      font-size: 16px;
    }

    .enhanced-select {
      width: 100%;
      padding: 10px 12px;
      border: 1px solid var(--border);
      border-radius: 8px;
      font-size: 14px;
      background: rgba(255,255,255,0.04);
      color: var(--text-primary);
      cursor: pointer;
      transition: all 0.2s;
    }

    .enhanced-select:focus {
      outline: none;
      border-color: var(--brand-red);
      box-shadow: 0 0 0 3px rgba(108, 75, 160, 0.1);
    }
    
    /* Multi-select styles */
    .multi-select-container {
      position: relative;
      width: 100%;
    }
    
    .multi-select-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 10px 12px;
      border: 1px solid var(--border);
      border-radius: 8px;
      background: rgba(255,255,255,0.04);
      color: var(--text-primary);
      cursor: pointer;
      transition: all 0.2s;
      user-select: none;
      font-size: 14px;
    }
    
    .multi-select-header:hover {
      border-color: var(--brand-primary);
      box-shadow: 0 0 0 3px rgba(108, 75, 160, 0.1);
    }
    
    .dropdown-arrow {
      transition: transform 0.2s ease;
      font-size: 12px;
      color: var(--muted);
    }
    
    .multi-select-header.open .dropdown-arrow {
      transform: rotate(180deg);
    }
    
    .multi-select-dropdown {
      position: absolute;
      top: 100%;
      left: 0;
      right: 0;
      background: #1E1745;
      border: 1px solid var(--border);
      border-top: none;
      border-radius: 0 0 8px 8px;
      box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
      z-index: 1000;
      max-height: 250px;
      overflow-y: auto;
    }
    
    .select-all-option {
      padding: 8px 12px;
      border-bottom: 1px solid var(--border);
      background: rgba(255,255,255,0.03);
    }
    
    .operators-list {
      max-height: 200px;
      overflow-y: auto;
    }
    
    .checkbox-label {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 8px 0;
      cursor: pointer;
      font-size: 14px;
      user-select: none;
      transition: background 0.2s ease;
    }
    
    .checkbox-label:hover {
      background: rgba(108, 75, 160, 0.08);
    }
    
    .checkbox-label input[type="checkbox"] {
      display: none;
    }
    
    .checkmark {
      width: 16px;
      height: 16px;
      border: 1px solid var(--border);
      border-radius: 3px;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: all 0.2s ease;
      flex-shrink: 0;
    }
    
    .checkbox-label input[type="checkbox"]:checked + .checkmark {
      background: var(--brand-red);
      border-color: var(--brand-red);
    }
    
    .checkbox-label input[type="checkbox"]:checked + .checkmark::after {
      content: '✓';
      color: white;
      font-size: 11px;
      font-weight: bold;
    }
    
    .operator-option {
      padding: 4px 12px;
    }
    
    /* Eklektik Integration Styles */
    .eklektik-filters {
      display: flex;
      gap: 12px;
      flex-wrap: wrap;
      margin-bottom: 16px;
    }
    
    .api-status-item {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 8px;
      padding: 16px;
      text-align: center;
    }
    
    .status-label {
      font-size: 12px;
      color: var(--muted);
      margin-bottom: 8px;
      font-weight: 600;
      text-transform: uppercase;
    }
    
    .status-value {
      font-size: 16px;
      font-weight: 600;
      color: var(--text);
    }
    
    .status-indicator {
      margin-right: 8px;
    }
    
    .status-indicator.success {
      color: var(--success);
    }
    
    .status-indicator.warning {
      color: var(--warning);
    }
    
    .status-indicator.danger {
      color: var(--danger);
    }
    
    .loading-spinner {
      color: var(--muted);
      font-style: italic;
    }
    
    /* Service and Status Badges */
    .service-badge, .status-badge {
      padding: 4px 8px;
      border-radius: 12px;
      font-size: 11px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    .service-badge.service-subscription {
      background: #dbeafe;
      color: #1e40af;
    }
    
    .service-badge.service-promotion {
      background: #fed7d7;
      color: #c53030;
    }
    
    .service-badge.service-notification {
      background: #fef5e7;
      color: #d69e2e;
    }
    
    .service-badge.service-unknown {
      background: #f7fafc;
      color: #4a5568;
    }
    
    .status-badge.status-active {
      background: #d1fae5;
      color: #065f46;
    }
    
    .status-badge.status-inactive {
      background: #fed7d7;
      color: #c53030;
    }
    
    .status-badge.status-pending {
      background: #fef5e7;
      color: #d69e2e;
    }
    
    .status-badge.status-unknown {
      background: #f7fafc;
      color: #4a5568;
    }
    
    /* Usage meter */
    .usage-meter {
      position: relative;
      width: 100%;
      max-width: 120px;
    }
    
    .usage-bar {
      height: 8px;
      background: linear-gradient(90deg, #22c55e 0%, #eab308 70%, #ef4444 100%);
      border-radius: 4px;
      transition: width 0.3s ease;
    }
    
    .usage-text {
      font-size: 11px;
      color: var(--muted);
      margin-top: 2px;
      display: block;
    }
    
    /* Action buttons */
    .action-buttons {
      display: flex;
      gap: 4px;
    }
    
    .btn-sm {
      padding: 4px 8px;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      font-size: 12px;
      transition: all 0.2s ease;
    }
    
    .btn-sm.btn-primary {
      background: var(--brand-red);
      color: white;
    }
    
    .btn-sm.btn-primary:hover {
      background: #5B3FA0;
      transform: translateY(-1px);
    }
    
    .btn-sm.btn-secondary {
      background: rgba(255,255,255,0.05);
      color: var(--text-secondary);
      border: 1px solid var(--border);
    }
    
    .btn-sm.btn-secondary:hover {
      background: rgba(255,255,255,0.1);
      transform: translateY(-1px);
    }
    
    /* Test Statistics Cards */
    .test-stat-card {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 8px;
      padding: 16px;
      text-align: center;
    }
    
    .stat-label {
      font-size: 12px;
      color: var(--muted);
      margin-bottom: 8px;
      font-weight: 600;
      text-transform: uppercase;
    }
    
    .stat-value {
      font-size: 24px;
      font-weight: 700;
      color: var(--text-primary);
      font-family: 'Outfit', sans-serif;
    }
    
    /* Progress animations */
    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }

    /* KPI Entrance Animations */
    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(16px); }
      to { opacity: 1; transform: translateY(0); }
    }
    
    .kpi-card {
      animation: fadeUp 0.5s ease both;
    }
    .kpi-card:nth-child(1) { animation-delay: 0s; }
    .kpi-card:nth-child(2) { animation-delay: 0.08s; }
    .kpi-card:nth-child(3) { animation-delay: 0.16s; }
    .kpi-card:nth-child(4) { animation-delay: 0.24s; }
    
    .chart-card {
      animation: fadeUp 0.5s ease both;
      animation-delay: 0.3s;
    }
    
    /* Scrollbar dark theme */
    ::-webkit-scrollbar { width: 6px; height: 6px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 3px; }
    ::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.2); }
    
    /* Selection color */
    ::selection { background: rgba(108, 75, 160, 0.3); color: white; }
    
    /* Button success style */
    .btn-success {
      background: #10b981;
      color: white;
      border: 1px solid #10b981;
      padding: 8px 16px;
      border-radius: 6px;
      font-weight: 500;
      transition: all 0.2s ease;
      cursor: pointer;
    }
    
    .btn-success:hover {
      background: #059669;
      border-color: #059669;
      transform: translateY(-1px);
    }
    
    .btn-success:disabled {
      background: #9ca3af;
      border-color: #9ca3af;
      cursor: not-allowed;
      transform: none;
    }
    
    /* Responsive Eklektik */
    @media (max-width: 768px) {
      .eklektik-filters {
        flex-direction: column;
        gap: 8px;
      }
      
      .api-status-item {
        padding: 12px;
      }
      
      .status-label {
        font-size: 11px;
      }
      
      .status-value {
        font-size: 14px;
      }
      
      /* KPIs Eklektik responsive */
      .kpi-card {
        grid-column: span 6 !important; /* 2 par ligne sur mobile */
      }
    }
    
    @media (max-width: 600px) {
      .kpi-card {
        grid-column: span 6 !important; /* 2 par ligne sur petit mobile */
        margin-bottom: 12px;
      }
      
      .kpi-title {
        font-size: 11px;
      }
      
      .kpi-value {
        font-size: 24px;
      }
      
      .kpi-delta {
        font-size: 11px;
      }
    }
    
    @media (max-width: 480px) {
      .kpi-card {
        grid-column: span 6 !important;
        margin-bottom: 10px;
        padding: 12px;
      }
      
      .kpi-title {
        font-size: 10px;
      }
      
      .kpi-value {
        font-size: 20px;
      }
      
      .kpi-delta {
        font-size: 10px;
      }
    }
      
      .usage-meter {
        max-width: 80px;
      }
      
      .action-buttons {
        flex-direction: column;
        gap: 2px;
      }
      
      .btn-sm {
        padding: 3px 6px;
        font-size: 11px;
      }
    }

    .control-info {
      font-size: 12px;
      color: var(--muted);
      margin-top: 4px;
    }

    .status-badge {
      display: inline-flex;
      align-items: center;
      padding: 8px 12px;
      background: #dcfce7;
      color: #166534;
      border-radius: 6px;
      font-size: 13px;
      font-weight: 500;
      margin-top: 20px;
    }

    .action-buttons {
      display: flex;
      gap: 12px;
      flex-wrap: wrap;
      margin-left: auto;
    }

    .enhanced-btn {
      padding: 8px 14px;
      border: 1px solid var(--border);
      border-radius: 10px;
      font-size: 13px;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.25s ease;
      display: flex;
      align-items: center;
      gap: 6px;
      font-family: 'Manrope', sans-serif;
    }

    .btn-primary {
      background: var(--brand-primary);
      color: white;
      border-color: var(--brand-primary);
    }

    .btn-primary:hover {
      background: #5B3FA0;
      transform: translateY(-1px);
      box-shadow: 0 4px 16px rgba(108, 75, 160, 0.3);
    }

    .btn-secondary {
      background: rgba(255,255,255,0.05);
      color: var(--text-secondary);
      border-color: var(--border);
    }

    .btn-secondary:hover {
      background: rgba(255,255,255,0.1);
      color: var(--text-primary);
      border-color: rgba(255,255,255,0.15);
      transform: translateY(-1px);
    }

    .btn-accent {
      background: rgba(255,255,255,0.05);
      color: var(--text-secondary);
      border-color: var(--border);
    }

    .btn-accent:hover {
      background: rgba(255,255,255,0.1);
      color: var(--text-primary);
      transform: translateY(-1px);
    }

    .btn-info {
      background: rgba(255,255,255,0.05);
      color: var(--text-secondary);
      border-color: var(--border);
    }

    .btn-info:hover {
      background: rgba(255,255,255,0.1);
      color: var(--text-primary);
      transform: translateY(-1px);
    }

    .performance-indicator {
      display: flex;
      align-items: center;
      gap: 6px;
      padding: 8px 12px;
      background: rgba(16, 185, 129, 0.1);
      border: 1px solid rgba(16, 185, 129, 0.3);
      border-radius: 20px;
      font-size: 12px;
      font-weight: 500;
      color: #059669;
      animation: pulse 2s infinite;
    }

    /* Animations pour les messages d'optimisation */
    @keyframes slideIn {
      from {
        transform: translateX(100%);
        opacity: 0;
      }
      to {
        transform: translateX(0);
        opacity: 1;
      }
    }

    @keyframes slideOut {
      from {
        transform: translateX(0);
        opacity: 1;
      }
      to {
        transform: translateX(100%);
        opacity: 0;
      }
    }

    .performance-icon {
      font-size: 14px;
    }

    @keyframes pulse {
      0%, 100% { opacity: 0.8; }
      50% { opacity: 1; }
    }

    /* Merchants Section Styles */
    .merchants-kpis-row {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 20px;
      margin-bottom: 24px;
    }
    .merchants-kpis-row .kpi-card { grid-column: span 1 !important; }
    .merchants-kpi { min-height: 120px; }
    .merchants-kpi .kpi-value { font-size: 32px; }
    .merchants-kpi .kpi-delta { min-height: 18px; }

    .trans-kpis-row {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 20px;
      margin-bottom: 24px;
    }
    .trans-kpis-row .kpi-card { grid-column: span 1 !important; }

    .sub-kpis-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 24px; }
    .sub-kpis-row .kpi-card { grid-column: span 1 !important; }

    .merchants-kpi {
      grid-column: span 1;
      display: flex;
      align-items: center;
      gap: 16px;
      padding: 20px;
      min-height: 120px;
    }

    .kpi-icon {
      font-size: 32px;
      opacity: 0.8;
    }

    .kpi-content {
      flex: 1;
    }

    .merchants-charts-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 24px;
      margin-bottom: 32px;
    }

    .merchants-chart {
      grid-column: span 1;
    }

    .chart-header {
      border-bottom: 1px solid var(--border);
      padding-bottom: 16px;
      margin-bottom: 20px;
    }

    .chart-subtitle {
      font-size: 13px;
      color: var(--muted);
      margin-top: 4px;
    }

    .merchants-table-section {
      margin-bottom: 32px;
    }

    .merchants-table {
      width: 100%;
    }

    .table-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding-bottom: 16px;
      border-bottom: 1px solid var(--border);
      margin-bottom: 20px;
    }

    .table-title {
      font-size: 18px;
      font-weight: 600;
      color: var(--brand-dark);
    }

    .table-actions {
      display: flex;
      gap: 12px;
    }

    .enhanced-table {
      width: 100%;
      border-collapse: collapse;
    }

    .enhanced-table th {
      background: rgba(255,255,255,0.03);
      font-weight: 600;
      color: var(--muted);
      font-size: 12px;
      text-transform: uppercase;
      letter-spacing: 0.8px;
      padding: 14px 16px;
      border-bottom: 1px solid var(--border);
      font-family: 'Manrope', sans-serif;
    }

    .enhanced-table td {
      padding: 14px 16px;
      border-bottom: 1px solid rgba(255,255,255,0.03);
      color: var(--text-secondary);
    }

    .enhanced-table tr:hover {
      background: rgba(255,255,255,0.02);
    }

    /* === SYSTÈME RESPONSIVE AMÉLIORÉ 5-BREAKPOINTS === */
    
    /* Large Desktop (>1400px) - Layout optimal */
    @media (min-width: 1400px) {
      .container { max-width: 1600px; }
      
      /* Header reste aligné avec le contenu élargi */
      .header {
        padding: 16px 20px;
      }
      
      /* Grid KPI optimal large desktop */
      .main-grid {
        grid-template-columns: repeat(12, 1fr);
        gap: 20px;
      }
    }
    
    /* Desktop (1200px - 1400px) */
    @media (max-width: 1200px) {
      .merchants-kpis-row {
        grid-template-columns: repeat(3, 1fr);
      }
      
      .merchants-kpi:nth-child(4),
      .merchants-kpi:nth-child(5) {
        grid-column: span 1;
      }
      
      /* Grid plus compact */
      .main-grid {
        gap: 16px;
      }
    }
    
    /* Tablet Large (900px - 1200px) */
    @media (max-width: 900px) {
      .kpi-card { grid-column: span 4; } /* 3 par ligne */
      .chart-card { grid-column: span 6; } /* 2 par ligne */
      
      .trans-kpis-row,
      .sub-kpis-row {
        grid-template-columns: repeat(3, 1fr);
      }
      
      /* Typography responsive tablet large */
      .kpi-value {
        font-size: clamp(28px, 4vw, 36px);
      }
      .kpi-label {
        font-size: clamp(13px, 2.5vw, 15px);
      }
    }

    /* Tablet (768px - 900px) */
    @media (max-width: 768px) {
      .kpi-card { grid-column: span 6; } /* 2 par ligne */
      .chart-card { 
        grid-column: span 12; /* 1 par ligne */
        min-height: 280px;
      }
      
      .header {
        padding: 14px 16px;
        flex-wrap: wrap;
        gap: 12px;
      }
      
      .header h1 {
        font-size: 20px;
      }
      
      /* Navigation horizontale scrollable */
      .nav-wrapper {
        margin-bottom: 16px;
        padding: 6px;
        border-radius: 10px;
      }
      .nav-tabs { 
        flex-wrap: nowrap;
        gap: 2px;
        overflow-x: auto;
      }
      .nav-divider { display: none; }
      .nav-tab { 
        padding: 8px 12px;
        font-size: 0.78rem;
      }
      
      .merchants-kpis-row {
        grid-template-columns: repeat(2, 1fr);
      }
      
      .trans-kpis-row,
      .sub-kpis-row {
        grid-template-columns: repeat(2, 1fr);
      }
      
      .merchants-charts-row {
        grid-template-columns: 1fr;
      }
      
      .date-periods {
        grid-template-columns: 1fr;
        gap: 16px;
      }
      
      .controls-section {
        flex-direction: column;
        align-items: stretch;
      }
      
      .action-buttons {
        margin-left: 0;
        justify-content: center;
      }
      
      .kpi-value {
        font-size: clamp(28px, 4vw, 36px);
      }
      .kpi-label {
        font-size: clamp(13px, 2.5vw, 15px);
      }
      .kpi-change {
        font-size: clamp(10px, 2.5vw, 12px);
      }
      
      /* Tables scrollables */
      .table-container {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
      }
      .table-wrapper {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        border-radius: 8px;
      }
      
      .enhanced-table {
        min-width: 500px;
        font-size: 13px;
      }
      
      .enhanced-table th,
      .enhanced-table td {
        padding: 12px 8px;
      }

      /* Filtres empilés */
      .enhanced-filters-bar {
        padding: 12px;
      }
      .enhanced-filters-bar > div {
        flex-wrap: wrap;
      }
    }
    
    /* Mobile Large (480px - 768px) */
    @media (max-width: 600px) {
      .kpi-card { grid-column: span 6; }
      .chart-card { min-height: 250px; }
      
      .container { padding: 12px 8px; }
      
      .header {
        padding: 10px 8px;
        gap: 8px;
      }
      
      /* Navigation scrollable horizontale */
      .nav-wrapper {
        margin-bottom: 12px;
        padding: 4px;
        border-radius: 10px;
        top: 2px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.15);
      }
      .nav-tabs {
        flex-wrap: nowrap !important;
        overflow-x: auto;
        gap: 1px;
      }
      .nav-divider { display: none; }
      
      .nav-tab {
        padding: 7px 10px;
        font-size: 0.72rem;
        min-width: max-content;
        margin: 0;
      }
      
      .kpi-value { 
        font-size: clamp(18px, 5vw, 26px); 
      }
      .kpi-label { 
        font-size: clamp(10px, 2.5vw, 12px); 
      }
      .kpi-change { 
        font-size: clamp(11px, 2.5vw, 13px); 
      }
      
      .kpi-card {
        padding: 10px 12px;
        min-height: 70px;
      }
      
      .logo {
        width: 90px;
        height: auto;
      }
      
      .header h1 {
        font-size: 16px;
      }
      
      .user-menu {
        padding: 6px 10px;
      }
      
      .user-name {
        font-size: 11px;
      }
      
      .user-role {
        font-size: 9px;
      }
      
      .admin-btn, .logout-btn {
        padding: 4px 8px;
        font-size: 10px;
      }

      /* Filtres empilés verticalement */
      .filters-grid {
        grid-template-columns: 1fr !important;
        gap: 12px !important;
      }
      .operator-row {
        flex-wrap: wrap;
      }
      .operator-row .multi-select-container {
        max-width: 100% !important;
        width: 100%;
      }
      .enhanced-filters-bar {
        padding: 10px;
      }
      .date-inputs {
        flex-direction: column;
        gap: 6px;
        align-items: stretch;
      }
      .date-separator { text-align: center; }
      .operator-select { width: 100%; }

      /* Tables avec scroll horizontal */
      .table-container, .table-card {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
      }
      table {
        min-width: 500px;
      }
      table th, table td {
        padding: 8px 6px;
        font-size: 0.75rem;
        white-space: nowrap;
      }
      
      /* Chart title */
      .chart-title {
        font-size: 12px;
      }
      
      /* Grid gap */
      .grid {
        gap: 10px;
      }
    }
    
    /* Mobile Small (<480px) */
    @media (max-width: 480px) {
      .kpi-card { grid-column: span 6; }
      .chart-card { min-height: 200px; }
      
      .container { padding: 8px 6px; }
      
      /* Navigation ultra compacte, scroll horizontal forcé */
      .nav-wrapper {
        padding: 3px;
        margin-bottom: 10px;
        border-radius: 8px;
      }
      .nav-tabs {
        flex-wrap: nowrap !important;
        overflow-x: auto;
        gap: 1px;
        padding: 2px 0;
      }
      .nav-tab {
        padding: 6px 8px;
        font-size: 0.68rem;
        border-radius: 6px;
      }
      .nav-divider { display: none; }
      .ai-fab { width: 44px; height: 44px; bottom: 16px; right: 16px; }
      .ai-panel { width: 100vw; max-width: 100vw; }
      
      .header {
        padding: 8px 6px;
        flex-direction: column;
        gap: 8px;
        text-align: center;
      }
      
      .header-left, .header-right {
        justify-content: center;
        width: 100%;
      }
      
      .header-left {
        flex-direction: column;
        gap: 6px;
      }
      
      .logo {
        width: 70px;
        height: auto;
      }
      
      .header h1 {
        font-size: 14px;
        text-align: center;
      }
      
      .user-menu {
        flex-wrap: wrap;
        gap: 6px;
        padding: 6px;
        align-items: center;
        justify-content: center;
      }
      
      .user-info {
        align-items: center;
        text-align: center;
      }
      
      .admin-btn, .logout-btn {
        padding: 3px 6px;
        font-size: 9px;
      }
      
      .merchants-kpis-row,
      .trans-kpis-row,
      .sub-kpis-row {
        grid-template-columns: 1fr 1fr;
        gap: 8px;
      }
      
      .kpi-value { 
        font-size: clamp(16px, 5vw, 22px); 
      }
      h1, h2 { 
        font-size: clamp(16px, 5vw, 22px); 
      }
      h3 { 
        font-size: clamp(14px, 4vw, 18px); 
      }
      
      /* Filtres empilés en 1 colonne */
      .filters-grid {
        grid-template-columns: 1fr !important;
        gap: 10px !important;
      }
      .operator-row {
        flex-direction: column;
        align-items: stretch;
      }
      .operator-row .multi-select-container {
        max-width: 100% !important;
        width: 100%;
      }
      .enhanced-filters-bar {
        padding: 8px;
      }
      
      .date-inputs {
        flex-direction: column;
        gap: 6px;
      }
      
      .date-separator {
        text-align: center;
        margin: 4px 0;
      }
      
      /* Tables ultra responsive */
      .table-container, .table-card {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
      }
      table {
        min-width: 400px;
      }
      table th, table td {
        padding: 6px 4px;
        font-size: 0.7rem;
      }
      
      .table-wrapper {
        font-size: 10px;
        border-radius: 6px;
      }
      
      .enhanced-table {
        min-width: 320px;
      }
      
      .enhanced-table th,
      .enhanced-table td {
        padding: 6px 3px;
        font-size: 10px;
      }
      
      .enhanced-table th {
        font-size: 9px;
        text-transform: none;
        letter-spacing: 0;
      }
      
      /* Card padding réduit */
      .card {
        padding: 12px;
        border-radius: 8px;
      }
      
      /* Grid gap réduit */
      .grid {
        gap: 8px;
      }
      
      /* Boutons plus petits */
      .btn-primary, .btn-secondary {
        padding: 6px 12px;
        font-size: 0.75rem;
      }
      
      /* Chart title compact */
      .chart-title {
        font-size: 11px;
        padding: 8px 12px;
      }

      /* Reporting tab responsive */
      #reporting .grid {
        grid-template-columns: 1fr !important;
      }
      #reporting .table-container {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
      }
      #reporting table {
        min-width: 500px;
      }
      #reporting .btn-primary,
      #reporting .btn-secondary {
        padding: 8px 12px;
        font-size: 0.78rem;
        white-space: nowrap;
      }
    }

    /* Styles pour les indicateurs de chargement */
    .loading-spinner {
      display: inline-block;
      animation: spin 1s linear infinite;
      font-size: 16px;
      color: var(--brand-red);
    }

    .error-text {
      color: #dc2626;
      font-weight: 500;
    }

    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }

    /* Styles pour les KPIs Eklektik */
    .kpi-card {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 16px;
      padding: 20px;
      margin-bottom: 0;
      box-shadow: 0 2px 12px rgba(0,0,0,0.2);
      transition: all 0.3s ease;
    }

    .kpi-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(0,0,0,0.3);
      border-color: rgba(255,255,255,0.08);
    }

    .kpi-value {
      font-size: 2.2rem;
      font-weight: 700;
      color: var(--text-primary);
      margin-bottom: 4px;
      font-family: 'Outfit', sans-serif;
      letter-spacing: -1px;
      line-height: 1.1;
    }

    .kpi-label {
      font-size: 0.68rem;
      color: var(--muted);
      text-transform: uppercase;
      letter-spacing: 1px;
      font-family: 'Manrope', sans-serif;
    }
  </style>
</head>
<body>
  <div class="container">
    <!-- Header -->
    <div class="header">
      <div class="header-left">
        @if($isOoredoo)
        <img src="{{ asset('images/ooredoo-logo.png') }}" alt="Ooredoo" class="logo" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
        <svg class="logo" viewBox="0 0 200 60" fill="none" xmlns="http://www.w3.org/2000/svg" style="display: none;">
          <rect width="200" height="60" fill="var(--brand-primary)"/>
          <text x="20" y="35" fill="white" font-family="Arial, sans-serif" font-size="24" font-weight="bold">ooredoo</text>
        </svg>
        <h1>Ooredoo Privileges - Performance Dashboard</h1>
        @else
        <svg class="logo" viewBox="0 0 200 60" fill="none" xmlns="http://www.w3.org/2000/svg">
          <defs>
            <linearGradient id="clubGradient" x1="0%" y1="0%" x2="100%" y2="0%">
              <stop offset="0%" style="stop-color:var(--brand-primary);stop-opacity:1" />
              <stop offset="100%" style="stop-color:var(--brand-secondary);stop-opacity:1" />
            </linearGradient>
          </defs>
          <rect width="200" height="60" fill="url(#clubGradient)" rx="8"/>
          <text x="20" y="25" fill="white" font-family="Arial, sans-serif" font-size="16" font-weight="bold">Club</text>
          <text x="20" y="45" fill="#F59E0B" font-family="Arial, sans-serif" font-size="14" font-weight="600" font-style="italic">Privilèges</text>
        </svg>
        <h1>Club Privilèges - Performance Dashboard</h1>
        @endif
      </div>
      <div class="header-right">
        <span>📊</span>
        <span>{{ Auth::user()->isSuperAdmin() ? 'Vue Globale' : 'Vue ' . (Auth::user()->getPrimaryOperatorName() ?? 'Opérateur') }}</span>
        
        <div class="user-menu">
          <div class="user-info" id="profileMenuToggle" style="cursor: pointer;">
            <div class="user-name">{{ Auth::user()->name ?? 'Utilisateur' }}</div>
            <div class="user-role">{{ Auth::user()->role->display_name ?? 'Aucun rôle' }}</div>
          </div>

          <div id="profileDropdown" class="dropdown" style="display:none; position:absolute; right:20px; top:60px; background: var(--card); border:1px solid var(--border); border-radius: 8px; min-width: 220px; z-index: 999; box-shadow: 0 8px 24px rgba(0,0,0,0.08);">
            @if(Auth::user()->canInviteCollaborators())
            <a href="{{ route('admin.users.index') }}" class="admin-btn" style="display:block; margin:8px;">Utilisateurs</a>
            <a href="{{ route('admin.invitations.index') }}" class="admin-btn" style="display:block; margin:8px;">Invitations</a>
            @endif
            <a href="{{ route('password.change') }}" class="admin-btn" style="display:block; margin:8px;">🔒 Mot de passe</a>
            @if(Auth::user()->canAccessSubStoresDashboard())
            <a href="{{ route('sub-stores.dashboard') }}" class="admin-btn" style="display:block; margin:8px;">🏪 Sub-Stores</a>
            @endif
            @if(Auth::user()->canAccessEklektikConfig())
            <a href="{{ route('admin.eklektik-cron') }}" class="admin-btn" style="display:block; margin:8px;">⚙️ Configuration Eklektik</a>
            <a href="{{ route('admin.eklektik.sync') }}" class="admin-btn" style="display:block; margin:8px;">🔄 Gestion des Synchronisations</a>
            <a href="{{ route('admin.eklektik.sync-tracking') }}" class="admin-btn" style="display:block; margin:8px;">📈 Suivi des Synchronisations</a>
            @endif
            <form action="{{ route('auth.logout') }}" method="POST" style="display:block; margin:8px;">
              @csrf
              <button type="submit" class="logout-btn" style="width:100%;" onclick="return confirm('Êtes-vous sûr de vouloir vous déconnecter ?')">Déconnexion</button>
            </form>
          </div>
        </div>
      </div>
    </div>

    <!-- Navigation Tabs - Groupée et simplifiée -->
    <div class="nav-wrapper">
      <div class="nav-tabs">
        <!-- Groupe: Données principales -->
        <div class="nav-group">
          <button class="nav-tab active" data-tab="overview" onclick="showTab('overview')">Overview</button>
          <button class="nav-tab" data-tab="subscriptions" onclick="showTab('subscriptions')">Subscriptions</button>
          <button class="nav-tab" data-tab="transactions" onclick="showTab('transactions')">Transactions</button>
          <button class="nav-tab" data-tab="merchants" onclick="showTab('merchants')">Merchants</button>
        </div>

        <!-- Séparateur -->
        <div class="nav-divider"></div>

        <!-- Groupe: Opérateurs -->
        <div class="nav-group">
          @if(Auth::user()->canViewTimweSection())
          <button class="nav-tab" data-tab="timwe" onclick="showTab('timwe')">Timwe</button>
          @endif
          @if(Auth::user()->canViewTimweSection())
          <button class="nav-tab" data-tab="ooredoo" onclick="showTab('ooredoo')">Ooredoo/DGV</button>
          @endif
          @if(Auth::user()->canViewEklektikSection())
          <button class="nav-tab" data-tab="eklektik" onclick="showTab('eklektik')">Eklektik</button>
          @endif
        </div>

        <!-- Séparateur -->
        <div class="nav-divider"></div>

        <!-- Groupe: Outils -->
        <div class="nav-group">
          <button class="nav-tab" data-tab="comparison" onclick="showTab('comparison')">Comparison</button>
          @if(Auth::user()->isSuperAdmin())
          <button class="nav-tab" data-tab="reporting" onclick="showTab('reporting')" data-testid="reporting-tab">Reporting</button>
          @endif
        </div>
      </div>
    </div>

    <!-- Bouton flottant Agent IA -->
    @if(Auth::user()->isSuperAdmin())
    <button class="ai-fab" onclick="toggleAIPanel()" title="Agent IA" data-testid="ai-agent-fab">
      <span class="ai-fab-tooltip">Agent IA</span>
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 2a2 2 0 012 2c0 .74-.4 1.39-1 1.73V7h1a7 7 0 017 7h1a1 1 0 110 2h-1.07A7 7 0 0113 22h-2a7 7 0 01-6.93-6H3a1 1 0 110-2h1a7 7 0 017-7h1V5.73A2 2 0 0112 2zm-1 9a1 1 0 100 2 1 1 0 000-2zm4 0a1 1 0 100 2 1 1 0 000-2z"/></svg>
    </button>

    <!-- Panel Agent IA (slide-in) -->
    <div class="ai-panel-overlay" id="aiPanelOverlay" onclick="toggleAIPanel()"></div>
    <div class="ai-panel" id="aiPanel">
      <div class="ai-panel-header">
        <h3>Agent IA</h3>
        <button class="ai-panel-close" onclick="toggleAIPanel()" data-testid="ai-panel-close">&times;</button>
      </div>
      <div class="ai-panel-body" id="aiPanelBody">
        <!-- Le contenu Agent IA sera déplacé ici dynamiquement -->
      </div>
    </div>
    @endif

    <script>
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
    </script>

    <!-- Enhanced Date & Filters Bar -->
    <div class="enhanced-filters-bar">
      <!-- Compact single-row layout -->
      <div class="filters-grid" style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 16px; align-items: end;">
        
        <!-- Période Principale -->
        <div>
          <div style="font-size: 0.7rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: var(--muted); margin-bottom: 6px;">Période Principale</div>
          <div style="display: flex; gap: 8px; align-items: center;">
            <input type="date" id="start-date" class="enhanced-date-input" onchange="updateDateRange()" style="flex:1;">
            <span style="color: var(--muted); font-size: 0.8rem;">au</span>
            <input type="date" id="end-date" class="enhanced-date-input" onchange="updateDateRange()" style="flex:1;">
          </div>
          <div class="period-display" id="primaryPeriod" style="font-size: 0.72rem; color: var(--muted); margin-top: 4px;"></div>
        </div>

        <!-- Période de Comparaison -->
        <div>
          <div style="font-size: 0.7rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: var(--muted); margin-bottom: 6px;">Comparaison</div>
          <div style="display: flex; gap: 8px; align-items: center;">
            <input type="date" id="comparison-start-date" class="enhanced-date-input" onchange="updateDateRange()" style="flex:1;">
            <span style="color: var(--muted); font-size: 0.8rem;">au</span>
            <input type="date" id="comparison-end-date" class="enhanced-date-input" onchange="updateDateRange()" style="flex:1;">
          </div>
          <div class="period-display" id="comparisonPeriod" style="font-size: 0.72rem; color: var(--muted); margin-top: 4px;"></div>
        </div>

        <!-- Actions inline -->
        <div style="display: flex; flex-direction: column; gap: 6px;">
          <button class="btn-primary enhanced-btn" onclick="autoCompareAndLoad()" id="refresh-btn" style="justify-content: center;">
            <span id="refresh-text">Actualiser</span>
            <span id="refresh-loading" style="display: none;">Chargement...</span>
          </button>
          <div style="display: flex; gap: 4px;">
            <button class="btn-secondary enhanced-btn" onclick="setSmartComparison()" style="font-size: 0.72rem; padding: 4px 8px;">Auto</button>
            <button class="btn-secondary enhanced-btn" onclick="toggleDatePickerMode()" style="font-size: 0.72rem; padding: 4px 8px;">Raccourcis</button>
          </div>
        </div>
      </div>

      <!-- Operator selector row -->
      <div class="operator-row" style="display: flex; align-items: center; gap: 12px; margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--border);">
        <div style="font-size: 0.7rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: var(--muted); white-space: nowrap;">Opérateurs</div>
        <div class="multi-select-container" style="flex: 1; max-width: 320px;">
          <div class="multi-select-header" onclick="toggleOperatorDropdown()">
            <span id="selected-operators-text">Tous les opérateurs</span>
            <span class="dropdown-arrow">&#9662;</span>
          </div>
          <div id="operators-dropdown" class="multi-select-dropdown" style="display: none;">
            <div class="select-all-option">
              <label class="checkbox-label">
                <input type="checkbox" id="select-all-operators" onchange="handleSelectAllOperators()" checked>
                <span class="checkmark"></span>
                <span>Tous les opérateurs</span>
              </label>
            </div>
            <div class="operators-list" id="operators-list">
            </div>
          </div>
        </div>
        <div id="operator-info" class="control-info" style="font-size: 0.72rem; color: var(--muted);">
          Chargement des opérateurs...
        </div>
        <div style="margin-left: auto;">
          <button class="btn-secondary enhanced-btn" onclick="showKeyboardShortcutsHelp()" style="font-size: 0.72rem; padding: 4px 10px;">Aide</button>
        </div>
      </div>
    </div>

    <!-- Tab 1: Overview -->
    <div id="overview" class="tab-content active">
      <!-- KPIs Row 1 (4 KPI) -->
      <div class="grid">
        <div class="card kpi-card">
          <div class="kpi-title">Activated Subscriptions <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Nombre d'abonnements dont la date de création tombe dans la période sélectionnée (client_abonnement_creation ∈ [start, end)). Tous opérateurs ou filtrés selon le filtre actif.">ⓘ</span></div>
          <div class="kpi-value" id="activatedSubscriptions">Loading...</div>
          <div class="kpi-delta" id="activatedSubscriptionsDelta">Loading...</div>
        </div>
        <div class="card kpi-card">
          <div class="kpi-title">Active Subscriptions <span style="margin-left:4px; cursor: help; color: var(--muted);" title="COHORTE PÉRIODE : Abonnements créés dans [start, end) ET encore actifs à la fin de la période (expiration NULL ou >= end). Ce n'est PAS la base active totale — uniquement les nouveaux de la période qui sont restés.">ⓘ</span></div>
          <div class="kpi-value" id="activeSubscriptions">Loading...</div>
          <div class="kpi-delta" id="activeSubscriptionsDelta">Loading...</div>
        </div>
        <div class="card kpi-card">
          <div class="kpi-title">Retention Rate <span style="margin-left:4px; cursor: help; color: var(--muted);" title="(Active Subscriptions / Activated Subscriptions) × 100. Pourcentage des NOUVEAUX abonnés de la période qui sont encore actifs à la fin. Formule : cohorte active ÷ cohorte activée.">ⓘ</span></div>
          <div class="kpi-value" id="overview-retentionRate">Loading...</div>
          <div class="kpi-delta" id="overview-retentionRateDelta">Loading...</div>
        </div>
        <div class="card kpi-card">
          <div class="kpi-title">Conversion Rate <span style="margin-left:4px; cursor: help; color: var(--muted);" title="(Transacting Users période / Active Subscriptions cohorte) × 100. Part des utilisateurs ayant transigé parmi les abonnés de la cohorte encore actifs.">ⓘ</span></div>
          <div class="kpi-value" id="conversionRate">Loading...</div>
          <div class="progress-bar">
            <div class="progress-fill" id="overview-conversionProgress" style="width: 0%"></div>
          </div>
          <div style="font-size: 12px; color: var(--muted); margin-top: 4px;">Target: 30%</div>
        </div>
      </div>

      <!-- KPIs Row 2 (4 KPI) -->
      <div class="grid">
        <div class="card kpi-card">
          <div class="kpi-title">Total Transactions <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Nombre total d’achats effectués pendant la période.">ⓘ</span></div>
          <div class="kpi-value" id="totalTransactions">Loading...</div>
          <div class="kpi-delta" id="totalTransactionsDelta">Loading...</div>
        </div>
        <div class="card kpi-card">
          <div class="kpi-title">Cohort Transactions <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Transactions effectuées par les abonnements dont la date de création est dans [start,end).">ⓘ</span></div>
          <div class="kpi-value" id="cohortTransactions">Loading...</div>
          <div class="kpi-delta" id="cohortTransactionsDelta">Loading...</div>
        </div>
        <div class="card kpi-card">
          <div class="kpi-title">Transacting Users (Période) <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Nombre total d'utilisateurs transigeants durant la période.">ⓘ</span></div>
          <div class="kpi-value" id="totalTransactingUsers">Loading...</div>
          <div class="kpi-delta" id="totalTransactingUsersDelta">Loading...</div>
        </div>
        <div class="card kpi-card">
          <div class="kpi-title">Transacting Users (Cohorte) <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Clients de la cohorte (créés dans [start,end)) ayant transigé dans la période.">ⓘ</span></div>
          <div class="kpi-value" id="cohortTransactingUsers">Loading...</div>
          <div class="kpi-delta" id="cohortTransactingUsersDelta">Loading...</div>
        </div>
        </div>

        <!-- Overview Chart -->
      <div class="grid">
        <div class="card chart-card full-width">
          <div class="chart-title">Performance Overview - Period Comparison <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Comparaison visuelle des KPIs principaux entre la période actuelle (bleu) et la période de comparaison (gris). Permet d'identifier rapidement les tendances.">ⓘ</span></div>
          <div class="chart-container">
            <canvas id="overviewChart"></canvas>
          </div>
        </div>
      </div>

      <!-- Global snapshots row removed on request -->
    </div>

    <!-- Tab 2: Detailed Subscription Analysis -->
    <div id="subscriptions" class="tab-content">
      <!-- Subscriptions KPIs: Row 1 (4 KPI) -->
      <div class="sub-kpis-row">
        <div class="card kpi-card">
          <div class="kpi-title">Activated Subscriptions <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Nombre total d'abonnements créés durant la période sélectionnée (client_abonnement_creation ∈ [start, end)). Identique à l'Overview.">ⓘ</span></div>
          <div class="kpi-value" id="sub-activatedSubscriptions">Loading...</div>
          <div class="kpi-delta" id="sub-activatedSubscriptionsDelta">Loading...</div>
        </div>
        <div class="card kpi-card">
          <div class="kpi-title">Active Subscriptions <span style="margin-left:4px; cursor: help; color: var(--muted);" title="COHORTE : Abonnements créés dans la période ET encore actifs à la fin (expiration NULL ou >= end). Ne représente PAS la base active totale, uniquement la rétention de la cohorte.">ⓘ</span></div>
          <div class="kpi-value" id="sub-activeSubscriptions">Loading...</div>
          <div class="kpi-delta" id="sub-activeSubscriptionsDelta">Loading...</div>
        </div>
        <div class="card kpi-card">
          <div class="kpi-title">Retention Rate <span style="margin-left:4px; cursor: help; color: var(--muted);" title="(Active Subs cohorte / Activated Subs) × 100. Sur 100 nouveaux abonnés de la période, combien sont encore actifs à la fin.">ⓘ</span></div>
          <div class="kpi-value" id="sub-retentionRate">Loading...</div>
          <div class="kpi-delta" id="sub-retentionRateDelta">Loading...</div>
        </div>
        <div class="card kpi-card">
          <div class="kpi-title">Conversion Rate (Période) <span style="margin-left:4px; cursor: help; color: var(--muted);" title="(Transacting Users période / Active Subs cohorte) × 100. Parmi les abonnés de la cohorte encore actifs, part de ceux ayant effectué au moins une transaction.">ⓘ</span></div>
          <div class="kpi-value" id="sub-conversionRate">Loading...</div>
          <div class="kpi-delta" id="sub-conversionRateDelta">Loading...</div>
        </div>
      </div>

      <!-- Subscriptions KPIs: Row 2 (2 KPI) -->
      <div class="sub-kpis-row">
        <div class="card kpi-card">
          <div class="kpi-title">Deactivated (Période) <span style="margin-left:4px; cursor: help; color: var(--muted);" title="TOUS les abonnements dont la date d'expiration tombe dans la période, quelle que soit leur date de création.">ⓘ</span></div>
          <div class="kpi-value" id="sub-deactivatedSubscriptions">Loading...</div>
          <div class="kpi-delta" id="sub-deactivatedSubscriptionsDelta">Loading...</div>
        </div>
        <div class="card kpi-card">
          <div class="kpi-title">Deactivated (Cohorte) <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Abonnements créés dans [start, end) ET dont la date d'expiration tombe aussi dans cette même période. Sous-ensemble de la cohorte.">ⓘ</span></div>
          <div class="kpi-value" id="sub-lostSubscriptions">Loading...</div>
          <div class="kpi-delta" id="sub-lostSubscriptionsDelta">Loading...</div>
        </div>
        <div class="card kpi-card">
          <div class="kpi-title">Taux de churn <span style="margin-left:4px; cursor: help; color: var(--muted);" title="(Deactivated cohorte / Activated) × 100. Part des nouveaux abonnés de la cohorte qui ont été désactivés durant la période.">ⓘ</span></div>
          <div class="kpi-value" id="sub-retentionRateTrue">Loading...</div>
          <div class="kpi-delta" id="sub-retentionRateTrueDelta">Loading...</div>
        </div>
        <div class="card kpi-card">
          <div class="kpi-title">Transactions (Période) <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Nombre total de transactions effectuées dans la période (history.time ∈ [start, end)), tous abonnés confondus.">ⓘ</span></div>
          <div class="kpi-value" id="sub-totalTransactions">Loading...</div>
          <div class="kpi-delta" id="sub-totalTransactionsDelta">Loading...</div>
        </div>
        </div>

      <!-- Subscription Trends (two charts side by side) -->
      <div class="grid">
        <div class="card chart-card">
          <div class="chart-title">Retention Rate Trend <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Part des abonnés qui restent actifs au fil du temps. Plus la courbe est haute, plus les clients restent.">ⓘ</span></div>
          <div class="chart-container">
            <canvas id="retentionChart"></canvas>
          </div>
        </div>

        <div class="card chart-card">
          <div class="chart-title">Daily Activated Subscriptions <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Nombre d'abonnements commencés chaque jour. Un pic = beaucoup de nouveaux inscrits ce jour-là.">ⓘ</span></div>
          <div class="chart-container">
            <canvas id="subscriptionTrendChart"></canvas>
          </div>
          </div>
        </div>

      <!-- Nouveaux KPIs Avancés - Masqué pour les collaborateurs -->
      @if(!Auth::user()->isCollaborator())
      <div class="grid" style="margin-top: 20px;">
        <h3 style="grid-column: 1 / -1; margin-bottom: 15px; color: var(--text); font-size: 18px; font-weight: 600;">📊 Analyses Avancées</h3>
        
        <!-- Activations par Canal -->
        <div class="card kpi-card">
          <div class="kpi-title">Activations CB <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Nombre d’abonnements commencés via carte bancaire.">ⓘ</span></div>
          <div class="kpi-value" id="sub-activationsCB">Loading...</div>
          <div class="kpi-delta" id="sub-activationsCBDelta">Loading...</div>
        </div>
        <div class="card kpi-card">
          <div class="kpi-title">Activations Recharge <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Nombre d’abonnements commencés via recharge.">ⓘ</span></div>
          <div class="kpi-value" id="sub-activationsRecharge">Loading...</div>
          <div class="kpi-delta" id="sub-activationsRechargeDelta">Loading...</div>
        </div>
        <div class="card kpi-card">
          <div class="kpi-title">Activations Solde Tél. <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Nombre d’abonnements commencés via solde téléphonique.">ⓘ</span></div>
          <div class="kpi-value" id="sub-activationsPhone">Loading...</div>
          <div class="kpi-delta" id="sub-activationsPhoneDelta">Loading...</div>
        </div>

        <!-- Répartition par Plan -->
        <div class="card kpi-card">
          <div class="kpi-title">Plans Journaliers <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Combien d’abonnements de type journalier ont été pris.">ⓘ</span></div>
          <div class="kpi-value" id="sub-plansDaily">Loading...</div>
          <div class="kpi-delta" id="sub-plansDailyDelta">Loading...</div>
        </div>
        <div class="card kpi-card">
          <div class="kpi-title">Plans Mensuels <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Combien d’abonnements mensuels ont été pris.">ⓘ</span></div>
          <div class="kpi-value" id="sub-plansMonthly">Loading...</div>
          <div class="kpi-delta" id="sub-plansMonthlyDelta">Loading...</div>
        </div>
        <div class="card kpi-card">
          <div class="kpi-title">Plans Annuels <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Combien d’abonnements annuels ont été pris.">ⓘ</span></div>
          <div class="kpi-value" id="sub-plansAnnual">Loading...</div>
          <div class="kpi-delta" id="sub-plansAnnualDelta">Loading...</div>
        </div>

        <!-- Métriques de Performance -->
        <div class="card kpi-card">
          <div class="kpi-title">Taux de Renouvellement <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Part des abonnements qui ont été repris à la fin de la période.">ⓘ</span></div>
          <div class="kpi-value" id="sub-renewalRate">Loading...</div>
          <div class="kpi-delta" id="sub-renewalRateDelta">Loading...</div>
        </div>
        <div class="card kpi-card">
          <div class="kpi-title">Durée de Vie Moyenne <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Nombre moyen de jours pendant lesquels un abonnement reste actif.">ⓘ</span></div>
          <div class="kpi-value" id="sub-averageLifespan">Loading...</div>
          <div class="kpi-delta" id="sub-averageLifespanDelta">Loading...</div>
        </div>
        
      </div>
      @endif

      <!-- Graphiques Avancés - Masqués pour les collaborateurs -->
      @if(!Auth::user()->isCollaborator())
      <div class="grid" style="margin-top: 20px;">
        <div class="card chart-card">
          <div class="chart-title">Répartition des Activations par Canal <span style="margin-left:4px; cursor: help; color: var(--muted);" title="D'où viennent les activations: carte, recharge, solde téléphonique…">ⓘ</span></div>
          <div class="chart-container">
            <canvas id="activationsByChannelChart"></canvas>
          </div>
        </div>

        <div class="card chart-card">
          <div class="chart-title">Distribution des Plans d'Abonnement <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Quels types de plans (journalier, mensuel, annuel) sont le plus choisis.">ⓘ</span></div>
          <div class="chart-container">
            <canvas id="planDistributionChart"></canvas>
          </div>
        </div>

        <div class="card chart-card">
          <div class="chart-title">Analyse de Cohortes - Survie J+30/J+60 <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Comment les groupes d'inscrits par date continuent d'utiliser le service après 30/60 jours.">ⓘ</span></div>
          <div class="chart-container">
            <canvas id="cohortsAnalysisChart"></canvas>
          </div>
        </div>
      </div>
      @endif

      <!-- Tableau des abonnements (détails) -->
      <div class="card table-card" style="margin-top: 20px;">
        <div class="table-header">
          <div class="table-title">📋 Détails des Abonnements</div>
                  <div class="table-controls">
          <select class="table-pagination" onchange="changeSubscriptionsPerPage(this.value)">
            <option value="25">25 par page</option>
            <option value="50">50 par page</option>
            <option value="100">100 par page</option>
          </select>
          <button class="export-btn">Exporter</button>
        </div>
        </div>
        <div class="table-container table-wrapper">
          <table class="enhanced-table">
            <thead>
              <tr>
                <th>Client</th>
                <th>Téléphone</th>
                <th>Opérateur</th>
                <th>Plan</th>
                <th>Date Activation</th>
                <th>Date Fin</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="subs-details-body">
              <tr><td colspan="7" class="loading">Chargement...</td></tr>
            </tbody>
          </table>
        </div>
        <div class="subscriptions-pagination"></div>
      </div>

    </div>

    <!-- Tab 3: Detailed Transaction Analysis -->
    <div id="transactions" class="tab-content">
      <div class="trans-kpis-row">
        <!-- Transaction KPIs -->
        <div class="card kpi-card">
          <div class="kpi-title">Total Transactions <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Nombre total de transactions effectuées dans la période (history.time ∈ [start, end)), tous abonnés confondus.">ⓘ</span></div>
          <div class="kpi-value" id="trans-totalTransactions">Loading...</div>
          <div class="kpi-delta" id="trans-totalTransactionsDelta">Loading...</div>
        </div>
        <div class="card kpi-card">
          <div class="kpi-title">Total Transactions (Cohorte) <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Transactions effectuées par les abonnés dont la date de création ET la date de transaction tombent dans [start, end). Sous-ensemble des Total Transactions.">ⓘ</span></div>
          <div class="kpi-value" id="trans-cohortTransactions">Loading...</div>
          <div class="kpi-delta" id="trans-cohortTransactionsDelta">Loading...</div>
        </div>
        <div class="card kpi-card">
          <div class="kpi-title">Transacting Users (Période) <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Nombre d'utilisateurs uniques (client_id distincts) ayant effectué au moins une transaction dans la période, tous abonnements confondus.">ⓘ</span></div>
          <div class="kpi-value" id="trans-transactingUsers">Loading...</div>
          <div class="kpi-delta" id="trans-transactingUsersDelta">Loading...</div>
        </div>
        <div class="card kpi-card">
          <div class="kpi-title">Transacting Users (Cohorte) <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Clients de la cohorte (créés dans [start,end)) ayant transigé dans la période.">ⓘ</span></div>
          <div class="kpi-value" id="trans-cohortTransactingUsers">Loading...</div>
          <div class="kpi-delta" id="trans-cohortTransactingUsersDelta">Loading...</div>
        </div>
      </div>

      <!-- Transactions KPIs: Row 2 (4 KPI alignés comme Overview) -->
      <div class="trans-kpis-row">
        <div class="card kpi-card">
          <div class="kpi-title">Conversion Rate (Cohorte) <span style="margin-left:4px; cursor: help; color: var(--muted);" title="(Transacting Users Cohorte / Active Subscriptions Cohorte) × 100. Part des abonnés de la cohorte encore actifs qui ont transigé.">ⓘ</span></div>
          <div class="kpi-value" id="trans-convCohort">Loading...</div>
          <div class="kpi-delta" id="trans-convCohortDelta">Loading...</div>
        </div>
        <div class="card kpi-card">
          <div class="kpi-title">Conversion Rate (Période) <span style="margin-left:4px; cursor: help; color: var(--muted);" title="(Transacting Users Période / Active Subscriptions Cohorte) × 100. Part de TOUS les utilisateurs ayant transigé, rapportée à la cohorte active.">ⓘ</span></div>
          <div class="kpi-value" id="trans-convPeriod">Loading...</div>
          <div class="kpi-delta" id="trans-convPeriodDelta">Loading...</div>
        </div>
        <div class="card kpi-card">
          <div class="kpi-title">Transactions/User <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Total Transactions / Transacting Users (Période). Nombre moyen de passages en caisse par utilisateur actif.">ⓘ</span></div>
          <div class="kpi-value" id="trans-transactionsPerUser">Loading...</div>
          <div class="kpi-delta" id="trans-transactionsPerUserDelta">Loading...</div>
        </div>
        <div class="card kpi-card">
          <div class="kpi-title">Avg. Durée entre 2 transactions <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Durée moyenne en jours entre deux transactions consécutives pour un même utilisateur. Plus cette valeur est basse, plus les utilisateurs sont fréquents.">ⓘ</span></div>
          <div class="kpi-value" id="trans-avgInterTxDays">Loading...</div>
          <div class="kpi-delta" id="trans-avgInterTxDaysDelta">Loading...</div>
          </div>
        </div>

      <div class="grid">

        <!-- Transaction Charts -->
        <div class="card chart-card">
          <div class="chart-title">Daily Transaction Volume <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Nombre d'achats/passages en caisse effectués chaque jour.">ⓘ</span></div>
          <div class="chart-container">
            <canvas id="transactionVolumeChart"></canvas>
          </div>
        </div>

        <div class="card chart-card">
          <div class="chart-title">Transacting Users Trend <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Combien de personnes ont payé au moins une fois chaque jour.">ⓘ</span></div>
          <div class="chart-container">
            <canvas id="transactingUsersChart"></canvas>
          </div>
        </div>

        <!-- Cumulative Charts (separated) -->
        <div class="card chart-card">
          <div class="chart-title">Cumulative Transactions <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Total qui s'ajoute jour après jour. Comme un compteur qui monte.">ⓘ</span></div>
          <div class="chart-container">
            <canvas id="transactionVolumeCumulativeChart"></canvas>
          </div>
        </div>

        <div class="card chart-card">
          <div class="chart-title">Cumulative Transacting Users <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Nombre de personnes uniques cumulées qui ont payé au fil des jours.">ⓘ</span></div>
          <div class="chart-container">
            <canvas id="transactingUsersCumulativeChart"></canvas>
          </div>
        </div>

        <!-- Nouveaux graphiques d'analyse des transactions - Masqués pour les collaborateurs -->
        @if(!Auth::user()->isCollaborator())
        <div class="card chart-card">
          <div class="chart-title">📊 Transactions par Opérateurs <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Répartition des transactions par moyen de paiement/opérateur.">ⓘ</span></div>
          <div class="chart-container">
            <canvas id="transactionsByOperatorChart"></canvas>
          </div>
        </div>

        <div class="card chart-card">
          <div class="chart-title">📋 Transactions par Plans d'Abonnement <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Combien de transactions pour chaque type de plan.">ⓘ</span></div>
          <div class="chart-container">
            <canvas id="transactionsByPlanChart"></canvas>
          </div>
        </div>
        @endif
      </div>
    </div>

    <!-- Tab 4: Merchant Analysis -->
    <div id="merchants" class="tab-content">
      <!-- KPIs Section - 8 cartes (2 lignes de 4) -->
      <div class="merchants-kpis-row">
        <div class="card kpi-card merchants-kpi">
          <div class="kpi-icon">🏪</div>
          <div class="kpi-content">
            <div class="kpi-title">Total Merchants <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Nombre total de partenaires (table partner).">ⓘ</span></div>
            <div class="kpi-value" id="merch-totalPartners">Loading...</div>
            <div class="kpi-delta" id="merch-totalPartnersDelta">→ 0.0%</div>
          </div>
        </div>
        <div class="card kpi-card merchants-kpi">
          <div class="kpi-icon">📈</div>
          <div class="kpi-content">
            <div class="kpi-title">Active Merchants <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Marchands ayant eu au moins une transaction dans la période (history.time ∈ [start,end)).">ⓘ</span></div>
            <div class="kpi-value" id="merch-activeMerchants">Loading...</div>
            <div class="kpi-delta" id="merch-activeMerchantsDelta">Loading...</div>
          </div>
        </div>
        <div class="card kpi-card merchants-kpi">
          <div class="kpi-icon">📍</div>
          <div class="kpi-content">
            <div class="kpi-title">Total Points de Vente <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Nombre total de points de vente (partner_location) des marchands actifs.">ⓘ</span></div>
            <div class="kpi-value" id="merch-totalLocationsActive">Loading...</div>
            <div class="kpi-delta" id="merch-totalLocationsActiveDelta">→ 0.0%</div>
          </div>
        </div>
        <div class="card kpi-card merchants-kpi">
          <div class="kpi-icon">% </div>
          <div class="kpi-content">
            <div class="kpi-title">Active Merchant Ratio <span style="margin-left:4px; cursor: help; color: var(--muted);" title="(Active Merchants) / (Total Merchants) × 100.">ⓘ</span></div>
            <div class="kpi-value" id="merch-activeMerchantRatio">Loading...</div>
            <div class="kpi-delta" id="merch-activeMerchantRatioDelta">Loading...</div>
          </div>
        </div>
      </div>

      <div class="merchants-kpis-row">
        <div class="card kpi-card merchants-kpi">
          <div class="kpi-icon">🔢</div>
          <div class="kpi-content">
            <div class="kpi-title">Total Transactions <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Nombre total de transactions effectuées dans la période (history.time ∈ [start, end)), tous abonnés confondus.">ⓘ</span></div>
            <div class="kpi-value" id="merch-totalTransactions">Loading...</div>
            <div class="kpi-delta" id="merch-totalTransactionsDelta">Loading...</div>
          </div>
        </div>
        <div class="card kpi-card merchants-kpi">
          <div class="kpi-icon">💳</div>
          <div class="kpi-content">
            <div class="kpi-title">Transactions/Merchant <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Transactions opérateur chez marchands / Marchands actifs (période).">ⓘ</span></div>
            <div class="kpi-value" id="merch-transactionsPerMerchant">Loading...</div>
            <div class="kpi-delta" id="merch-transactionsPerMerchantDelta">Loading...</div>
          </div>
        </div>
        <div class="card kpi-card merchants-kpi">
          <div class="kpi-icon">🏆</div>
          <div class="kpi-content">
            <div class="kpi-title">Top Merchant <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Meilleur marchand par volume (part de marché période).">ⓘ</span></div>
            <div class="kpi-value" id="merch-topMerchantShare">Loading...</div>
            <div class="kpi-delta" id="merch-topMerchantName">Loading...</div>
          </div>
        </div>
        <div class="card kpi-card merchants-kpi">
          <div class="kpi-icon">🎯</div>
          <div class="kpi-content">
            <div class="kpi-title">Diversity <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Niveau basé sur le nombre de marchands actifs (période).">ⓘ</span></div>
            <div class="kpi-value" id="merch-diversity">Loading...</div>
            <div class="kpi-delta" id="merch-diversityDetail">Loading...</div>
          </div>
        </div>
      </div>

      <!-- Charts Section - 2 graphiques côte à côte -->
      <div class="merchants-charts-row">
        <div class="card chart-card merchants-chart">
          <div class="chart-header">
            <div class="chart-title">🏪 Top Merchants by Volume <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Les marchands avec le plus de transactions dans la période.">ⓘ</span></div>
            <div class="chart-subtitle">Transactions par marchand</div>
          </div>
          <div class="chart-container">
            <canvas id="topMerchantsChart"></canvas>
          </div>
        </div>

        <div class="card chart-card merchants-chart">
          <div class="chart-header">
            <div class="chart-title">📊 Distribution by Category <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Classement des transactions par types de magasins (ex: restaurants, mode).">ⓘ</span></div>
            <div class="chart-subtitle">Répartition par catégorie</div>
          </div>
          <div class="chart-container">
            <canvas id="categoryChart"></canvas>
          </div>
        </div>
      </div>

      <!-- Ligne suivante: évolution points de vente actifs -->
      <div class="merchants-charts-row">
        <div class="card chart-card">
          <div class="chart-title">Active Points of Sale Over Time <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Nombre de boutiques actives visibles trimestre par trimestre.">ⓘ</span></div>
          <div class="chart-container">
            <canvas id="activeLocationsTrend"></canvas>
          </div>
        </div>
      </div>

      <!-- Table Section - Tableau pleine largeur -->
      <div class="merchants-table-section">
        <div class="card table-card merchants-table">
          <div class="table-header">
            <div class="table-title">📋 Performance Détaillée des Marchands</div>
            <div class="table-actions">
              <select id="merchantsPerPage" onchange="changeMerchantsPerPage()" style="margin-right: 10px; padding: 4px 8px; border: 1px solid var(--border); border-radius: 4px;">
                <option value="10">10 par page</option>
                <option value="25" selected>25 par page</option>
                <option value="50">50 par page</option>
                <option value="100">100 par page</option>
              </select>
              <button class="btn-secondary" onclick="exportMerchantsData()">📥 Exporter</button>
            </div>
          </div>
          <div class="table-container table-wrapper">
            <table class="enhanced-table">
              <thead>
                <tr>
                  <th>Merchant</th>
                  <th>Category</th>
                  <th>Current</th>
                  <th>Previous</th>
                  <th>Change</th>
                  <th>Market Share</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody id="merchantsTableBody">
                <tr>
                  <td colspan="7" class="loading">
                    <div class="spinner"></div>
                    Chargement des données marchands...
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          
          <!-- Pagination Controls -->
          <div class="pagination-controls" style="display: flex; justify-content: space-between; align-items: center; padding: 16px; border-top: 1px solid var(--border);">
            <div class="pagination-info">
              <span id="merchantsPaginationInfo">Affichage de 1-25 sur 0 marchands</span>
            </div>
            <div class="pagination-buttons">
              <button id="merchantsPrevBtn" onclick="previousMerchantsPage()" style="padding: 8px 12px; margin-right: 8px; border: 1px solid var(--border); border-radius: 4px; background: var(--card); cursor: pointer;" disabled>
                ← Précédent
              </button>
              <span id="merchantsPageNumbers" style="margin: 0 16px; font-weight: 500;"></span>
              <button id="merchantsNextBtn" onclick="nextMerchantsPage()" style="padding: 8px 12px; margin-left: 8px; border: 1px solid var(--border); border-radius: 4px; background: var(--card); cursor: pointer;" disabled>
                Suivant →
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Tab 5: Eklektik Integration -->
    @if(Auth::user()->canViewEklektikSection())
    <div id="eklektik" class="tab-content">


      <!-- Statistiques Eklektik KPIs - 8 KPIs sur 2 lignes -->
      <div class="grid">
        <!-- Première ligne - 4 KPIs -->
        <div class="card kpi-card" style="grid-column: span 3;">
          <div class="kpi-title">Revenus TTC <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Revenus Toutes Taxes Comprises générés via la plateforme Eklektik pour la période sélectionnée (somme des montants facturés).">ⓘ</span></div>
          <div class="kpi-value" id="eklektik-revenue-ttc">Loading...</div>
          <div class="kpi-delta" id="eklektik-revenue-ttc-delta">Loading...</div>
        </div>
        <div class="card kpi-card" style="grid-column: span 3;">
          <div class="kpi-title">Revenus HT <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Revenus Hors Taxes calculés en appliquant les formules contractuelles spécifiques à chaque opérateur (TVA déduite selon les taux applicables).">ⓘ</span></div>
          <div class="kpi-value" id="eklektik-revenue-ht">Loading...</div>
          <div class="kpi-delta" id="eklektik-revenue-ht-delta">Loading...</div>
        </div>
        <div class="card kpi-card" style="grid-column: span 3;">
          <div class="kpi-title">CA BigDeal <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Part du chiffre d'affaires revenant à BigDeal selon les termes contractuels avec chaque opérateur (pourcentage du Revenu HT).">ⓘ</span></div>
          <div class="kpi-value" id="eklektik-ca-bigdeal">Loading...</div>
          <div class="kpi-delta" id="eklektik-ca-bigdeal-delta">Loading...</div>
        </div>
        <div class="card kpi-card" style="grid-column: span 3;">
          <div class="kpi-title">Active Subs <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Nombre total d'abonnés actifs sur la plateforme Eklektik à la fin de la période sélectionnée.">ⓘ</span></div>
          <div class="kpi-value" id="eklektik-active-subs">Loading...</div>
          <div class="kpi-delta" id="eklektik-active-subs-delta">Loading...</div>
        </div>
      </div>

      <div class="grid">
        <!-- Deuxième ligne - 4 KPIs -->
        <div class="card kpi-card" style="grid-column: span 3;">
          <div class="kpi-title">Nouveaux Abonnements <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Nouveaux abonnements créés">ⓘ</span></div>
          <div class="kpi-value" id="eklektik-new-subscriptions">Loading...</div>
          <div class="kpi-delta" id="eklektik-new-subscriptions-delta">Loading...</div>
        </div>
        <div class="card kpi-card" style="grid-column: span 3;">
          <div class="kpi-title">Désabonnements <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Nombre de désabonnements">ⓘ</span></div>
          <div class="kpi-value" id="eklektik-unsubscriptions">Loading...</div>
          <div class="kpi-delta" id="eklektik-unsubscriptions-delta">Loading...</div>
        </div>
        <div class="card kpi-card" style="grid-column: span 3;">
          <div class="kpi-title">Simchurn <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Perte d'abonnés (Simchurn)">ⓘ</span></div>
          <div class="kpi-value" id="eklektik-simchurn">Loading...</div>
          <div class="kpi-delta" id="eklektik-simchurn-delta">Loading...</div>
        </div>
        <div class="card kpi-card" style="grid-column: span 3;">
          <div class="kpi-title">Abonnements Facturés <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Nombre total d'abonnements facturés">ⓘ</span></div>
          <div class="kpi-value" id="eklektik-facturation">Loading...</div>
          <div class="kpi-delta" id="eklektik-facturation-delta">Loading...</div>
        </div>
      </div>

      <!-- Graphiques Eklektik - Utilisation du composant optimisé -->
      <div class="grid">
        <div class="card" style="grid-column: span 12;">
          <div class="chart-title">
            📊 Graphiques Eklektik Optimisés
            <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Graphiques Eklektik optimisés pour éliminer le sautillement">ⓘ</span>
          </div>
          {{-- Utiliser le composant graphiques Eklektik --}}
          <x-eklektik-charts />
        </div>
      </div>

      <div class="grid">
        <div class="card" style="grid-column: span 6;">
          <div class="chart-title">
            📊 Statistiques par Opérateur
            <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Détails des statistiques par opérateur">ⓘ</span>
          </div>
          <div id="eklektik-operators-stats" style="max-height: 200px; overflow-y: auto;">
            <div class="text-center" style="padding: 20px;">
              <i class="fas fa-spinner fa-spin"></i> Chargement...
            </div>
          </div>
        </div>
      </div>


    </div>
    @endif

    <!-- Tab 5: Timwe Integration (Super Admin Only) -->
    @if(Auth::user()->canViewTimweSection())
    <div id="timwe" class="tab-content">

      <!-- En-tête Timwe avec lien Diagnostic -->
      @if(Auth::user()->isSuperAdmin())
      <div style="display: flex; justify-content: flex-end; margin-bottom: 12px;">
        <a href="{{ route('admin.timwe-diagnostic') }}" class="timwe-diagnostic-link" data-testid="timwe-diagnostic-link">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
          Diagnostic Timwe
        </a>
      </div>
      @endif

      <!-- Statistiques Timwe KPIs - 3 lignes de KPIs -->
      <div class="grid">
        <!-- Première ligne - 4 KPIs principaux -->
        <div class="card kpi-card" style="grid-column: span 3;">
          <div class="kpi-title">Taux de Facturation <span style="margin-left:4px; cursor: help; color: var(--muted);" title="(Nombre de facturations réussies / Base Active Totale Timwe) × 100. Critères : pricepointId=63980 ET mnoDeliveryCode=DELIVERED uniquement.">ⓘ</span></div>
          <div class="kpi-value" id="timwe-billing-rate">Loading...</div>
        </div>
        <div class="card kpi-card" style="grid-column: span 3;">
          <div class="kpi-title">Taux de Croissance Nette <span style="margin-left:4px; cursor: help; color: var(--muted);" title="((Nouveaux Abonnements - Désabonnements - Simchurn) / Active Subscriptions) × 100. Indique la croissance nette du portefeuille client.">ⓘ</span></div>
          <div class="kpi-value" id="timwe-net-growth-rate">Loading...</div>
          <div class="kpi-delta" id="timwe-net-growth-rate-delta">Loading...</div>
        </div>
        <div class="card kpi-card" style="grid-column: span 3;">
          <div class="kpi-title">Nombre Facturation <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Nombre total de transactions de facturation réussies (pricepointId=63980 ET mnoDeliveryCode=DELIVERED)">ⓘ</span></div>
          <div class="kpi-value" id="timwe-total-billings">Loading...</div>
          <div class="kpi-delta" id="timwe-total-billings-delta">Loading...</div>
        </div>
        <div class="card kpi-card" style="grid-column: span 3;">
          <div class="kpi-title">Active Subscriptions <span style="margin-left:4px; cursor: help; color: var(--muted);" title="BASE ACTIVE TOTALE Timwe au dernier jour de la période (source : timwe_daily_stats). Inclut TOUS les abonnés actifs quelle que soit leur date d'activation. Diffère de l'Overview qui montre uniquement la cohorte de la période.">ⓘ</span></div>
          <div class="kpi-value" id="timwe-active-subs">Loading...</div>
        </div>
      </div>

      <div class="grid">
        <!-- Deuxième ligne - 4 KPIs d'abonnements -->
        <div class="card kpi-card" style="grid-column: span 3;">
          <div class="kpi-title">Nouveaux Abonnements <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Nouveaux abonnements créés dans la période">ⓘ</span></div>
          <div class="kpi-value" id="timwe-new-subscriptions">Loading...</div>
          <div class="kpi-delta" id="timwe-new-subscriptions-delta">Loading...</div>
        </div>
        <div class="card kpi-card" style="grid-column: span 3;">
          <div class="kpi-title">Désabonnements <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Nombre de désabonnements dans la période">ⓘ</span></div>
          <div class="kpi-value" id="timwe-unsubscriptions">Loading...</div>
          <div class="kpi-delta" id="timwe-unsubscriptions-delta">Loading...</div>
        </div>
        <div class="card kpi-card" style="grid-column: span 3;">
          <div class="kpi-title">Simchurn <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Abonnements créés et expirés le même jour">ⓘ</span></div>
          <div class="kpi-value" id="timwe-simchurn">Loading...</div>
          <div class="kpi-delta" id="timwe-simchurn-delta">Loading...</div>
        </div>
        <div class="card kpi-card" style="grid-column: span 3;">
          <div class="kpi-title">Revenu Simchurn <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Revenu généré par les simchurn">ⓘ</span></div>
          <div class="kpi-value" id="timwe-simchurn-revenue">Loading...</div>
          <div class="kpi-delta" id="timwe-simchurn-revenue-delta">Loading...</div>
        </div>
      </div>

      <div class="grid">
        <!-- Troisième ligne - 4 KPIs de revenus -->
        <div class="card kpi-card" style="grid-column: span 3;">
          <div class="kpi-title">Revenu TTC (TND) <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Revenu total TTC basé sur la somme des totalCharged (en TND)">ⓘ</span></div>
          <div class="kpi-value" id="timwe-revenue-tnd">Loading...</div>
          <div class="kpi-delta" id="timwe-revenue-tnd-delta">Loading...</div>
        </div>
        <div class="card kpi-card" style="grid-column: span 3;">
          <div class="kpi-title">CA BigDeal HT (TND) <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Chiffre d'affaires BigDeal Hors Taxes calculé selon le contrat">ⓘ</span></div>
          <div class="kpi-value" id="timwe-revenue-usd">Loading...</div>
          <div class="kpi-delta" id="timwe-revenue-usd-delta">Loading...</div>
        </div>
        <div class="card kpi-card" style="grid-column: span 3;">
          <div class="kpi-title">ARPU (TND) <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Revenu moyen par utilisateur normalisé sur 30 jours : (Revenu Total / Active Subs) × (30 / Nombre de jours)">ⓘ</span></div>
          <div class="kpi-value" id="timwe-arpu">Loading...</div>
        </div>
        <div class="card kpi-card" style="grid-column: span 3;">
          <div class="kpi-title">Revenu Moyen/Facturation <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Revenu moyen par facturation (Revenu Total / Total Facturations)">ⓘ</span></div>
          <div class="kpi-value" id="timwe-avg-billing-revenue">Loading...</div>
        </div>
      </div>

      <!-- Tableau Statistiques Quotidiennes Timwe -->
      <div class="grid">
        <div class="card" style="grid-column: span 12;">
          <div class="chart-title">
            📊 Statistiques Quotidiennes Timwe
            <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Statistiques détaillées par jour pour Timwe">ⓘ</span>
            <button onclick="exportTimweStatsToExcel()" style="float: right; padding: 8px 16px; background: var(--primary); color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; margin-left: 8px;">
              📥 Excel
            </button>
            <button onclick="copyTimweStatsToClipboard()" style="float: right; padding: 8px 16px; background: var(--secondary); color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px;">
              📋 Copy
            </button>
          </div>
          
          <!-- Search bar -->
          <div style="padding: 16px; border-bottom: 1px solid var(--border);">
            <input type="text" id="timweStatsSearch" placeholder="🔍 Rechercher..." 
                   onkeyup="filterTimweStats()" 
                   style="width: 100%; padding: 8px 12px; border: 1px solid var(--border); border-radius: 4px; font-size: 14px;">
          </div>
          
          <div class="table-container" style="max-height: 600px; overflow-y: auto;">
            <table id="timweStatsTable">
              <thead style="position: sticky; top: 0; background: var(--card); z-index: 10;">
                <tr>
                  <th style="width: 30px; text-align: center;"></th>
                  <th style="text-align: left;">Période</th>
                  <th style="text-align: center;">New Sub</th>
                  <th style="text-align: center;">Unsub</th>
                  <th style="text-align: center;">Simchurn</th>
                  <th style="text-align: center;">Active Sub</th>
                  <th style="text-align: center;">NB Facturation</th>
                  <th style="text-align: center;">Taux Fact %</th>
                  <th style="text-align: center;">Revenu TTC (TND)</th>
                  <th style="text-align: center;">CA BigDeal HT (TND)</th>
                </tr>
              </thead>
              <tbody id="timweStatsTableBody">
                <tr>
                  <td colspan="10" style="text-align: center; padding: 40px;">
                    <i class="fas fa-spinner fa-spin"></i> Chargement des statistiques...
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- DÉSACTIVÉ POUR OPTIMISATION: Tableau des Transactions Timwe par Utilisateur -->
      <!-- Ce tableau a été désactivé définitivement pour améliorer les performances du dashboard -->
      <!--
      <div class="grid" style="margin-top: 20px;">
        <div class="card" style="grid-column: span 12;">
          <div class="chart-title">
            📋 Détails des Transactions Timwe par Utilisateur
            <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Liste des transactions Timwe (renouvellements et désabonnements) groupées par utilisateur">ⓘ</span>
          </div>
          <div style="padding: 40px; text-align: center; color: var(--muted);">
            ⚠️ Tableau désactivé pour optimisation des performances
          </div>
        </div>
      </div>
      -->

    </div>
    @endif

    <!-- Tab: Ooredoo/DGV Section -->
    @if(Auth::user()->canViewTimweSection())
    <div id="ooredoo" class="tab-content">

      <!-- Statistiques Ooredoo KPIs - 2 lignes de KPIs -->
      <div class="grid">
        <!-- Première ligne - 4 KPIs principaux -->
        <div class="card kpi-card" style="grid-column: span 3;">
          <div class="kpi-title">Taux de Facturation <span style="margin-left:4px; cursor: help; color: var(--muted);" title="(Clients facturés) / (Total clients Ooredoo) * 100. Transactions de type INVOICE avec statut SUCCESS.">ⓘ</span></div>
          <div class="kpi-value" id="ooredoo-billing-rate">Loading...</div>
          <div class="kpi-delta" id="ooredoo-billing-rate-delta">Loading...</div>
        </div>
        <div class="card kpi-card" style="grid-column: span 3;">
          <div class="kpi-title">Total Facturations <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Nombre total de transactions de facturation réussies (type INVOICE)">ⓘ</span></div>
          <div class="kpi-value" id="ooredoo-total-billings">Loading...</div>
          <div class="kpi-delta" id="ooredoo-total-billings-delta">Loading...</div>
        </div>
        <div class="card kpi-card" style="grid-column: span 3;">
          <div class="kpi-title">Active Subscriptions <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Base totale d'abonnés Ooredoo/DGV actifs au dernier jour de la période (source: ooredoo_daily_stats).">ⓘ</span></div>
          <div class="kpi-value" id="ooredoo-active-subs">Loading...</div>
        </div>
        <div class="card kpi-card" style="grid-column: span 3;">
          <div class="kpi-title">Nouveaux Abonnements <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Nouveaux abonnements créés dans la période (OOREDOO_PAYMENT_SUCCESS)">ⓘ</span></div>
          <div class="kpi-value" id="ooredoo-new-subscriptions">Loading...</div>
          <div class="kpi-delta" id="ooredoo-new-subscriptions-delta">Loading...</div>
        </div>
      </div>

      <div class="grid">
        <!-- Deuxième ligne - 4 KPIs d'abonnements -->
        <div class="card kpi-card" style="grid-column: span 3;">
          <div class="kpi-title">Désabonnements <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Nombre de désabonnements dans la période">ⓘ</span></div>
          <div class="kpi-value" id="ooredoo-unsubscriptions">Loading...</div>
          <div class="kpi-delta" id="ooredoo-unsubscriptions-delta">Loading...</div>
        </div>
        <div class="card kpi-card" style="grid-column: span 3;">
          <div class="kpi-title">Revenu Total TND <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Revenu total en TND (dinars tunisiens)">ⓘ</span></div>
          <div class="kpi-value" id="ooredoo-revenue-tnd">Loading...</div>
          <div class="kpi-delta" id="ooredoo-revenue-tnd-delta">Loading...</div>
        </div>
        <div class="card kpi-card" style="grid-column: span 3;">
          <div class="kpi-title">ARPU (TND) <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Revenu moyen par utilisateur normalisé sur 30 jours : (Revenu Total / Active Subs) × (30 / Nombre de jours)">ⓘ</span></div>
          <div class="kpi-value" id="ooredoo-arpu">Loading...</div>
        </div>
        <div class="card kpi-card" style="grid-column: span 3;">
          <div class="kpi-title">Revenu Moyen/Facturation <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Revenu moyen par transaction de facturation (Revenu Total / Total Facturations)">ⓘ</span></div>
          <div class="kpi-value" id="ooredoo-avg-billing-revenue">Loading...</div>
        </div>
      </div>

      <!-- Tableau Statistiques Mensuelles Ooredoo -->
      <div class="grid">
        <div class="card" style="grid-column: span 12;">
          <div class="chart-title">
            📊 Statistiques Mensuelles Ooredoo/DGV
            <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Statistiques groupées par mois pour Ooredoo/DGV. Cliquez sur un mois pour voir les détails quotidiens.">ⓘ</span>
            <button onclick="exportOoredooStatsToExcel()" style="float: right; padding: 8px 16px; background: var(--primary); color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; margin-left: 8px;">
              📥 Excel
            </button>
            <button onclick="copyOoredooStatsToClipboard()" style="float: right; padding: 8px 16px; background: var(--secondary); color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px;">
              📋 Copy
            </button>
          </div>
          
          <!-- Search bar -->
          <div style="padding: 16px; border-bottom: 1px solid var(--border);">
            <input type="text" id="ooredooStatsSearch" placeholder="🔍 Rechercher..." 
                   onkeyup="filterOoredooStats()" 
                   style="width: 100%; padding: 8px 12px; border: 1px solid var(--border); border-radius: 4px; font-size: 14px;">
          </div>
          
          <div class="table-container" style="max-height: 600px; overflow-y: auto;">
            <table id="ooredooStatsTable">
              <thead style="position: sticky; top: 0; background: var(--card); z-index: 10;">
                <tr>
                  <th style="cursor: pointer; width: 30px;"></th>
                  <th onclick="sortOoredooStatistics(0)" style="cursor: pointer;">Période <span class="sort-icon">⇅</span></th>
                  <th onclick="sortOoredooStatistics(1)" style="cursor: pointer;">New Sub <span class="sort-icon">⇅</span></th>
                  <th onclick="sortOoredooStatistics(2)" style="cursor: pointer;">Unsub <span class="sort-icon">⇅</span></th>
                  <th onclick="sortOoredooStatistics(3)" style="cursor: pointer;">Active Sub <span class="sort-icon">⇅</span></th>
                  <th onclick="sortOoredooStatistics(4)" style="cursor: pointer;">NB Facturation <span class="sort-icon">⇅</span></th>
                  <th onclick="sortOoredooStatistics(5)" style="cursor: pointer;">Taux Fact % <span class="sort-icon">⇅</span></th>
                  <th onclick="sortOoredooStatistics(6)" style="cursor: pointer;">Revenu TND <span class="sort-icon">⇅</span></th>
                </tr>
              </thead>
              <tbody id="ooredooStatsTableBody">
                <tr>
                  <td colspan="8" style="text-align: center; padding: 40px;">
                    <i class="fas fa-spinner fa-spin"></i> Chargement des statistiques...
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

    </div>
    @endif

    <!-- Tab 6: Comparison -->
    <div id="comparison" class="tab-content">
      <div class="grid">
        <!-- Comparison Table -->
        <div class="card table-card">
          <div class="chart-title">Period-over-Period Comparison <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Compare cette période à la période d'avant pour voir si on s'améliore.">ⓘ</span></div>
          <div class="table-container">
            <table>
              <thead>
                <tr>
                  <th>Metric</th>
                  <th>Current Period</th>
                  <th>Previous Period</th>
                  <th>Absolute Change</th>
                  <th>% Change</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody id="comparisonTableBody">
                <tr>
                  <td colspan="6" class="loading">
                    <div class="spinner"></div>
                    Loading comparison data...
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Comparison Chart -->
        <div class="card chart-card full-width">
          <div class="chart-title">Key Metrics Comparison <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Graphique en toile d'araignée: plus la zone verte est grande, mieux c'est par rapport à avant.">ⓘ</span></div>
          <div class="chart-container">
            <canvas id="comparisonChart"></canvas>
          </div>
        </div>
      </div>
    </div>

    <!-- Tab: Reporting Configuration -->
    @if(Auth::user()->isSuperAdmin())
    <div id="reporting" class="tab-content" data-testid="reporting-tab-content">
      <div class="grid" style="grid-template-columns: 1fr; gap: 20px;">

        <!-- Header actions -->
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
          <div>
            <h2 style="margin: 0; color: var(--text-primary); font-size: 1.3rem; font-weight: 700;">Configuration Reporting</h2>
            <p style="margin: 4px 0 0; color: var(--muted); font-size: 0.85rem;">Gerez les destinataires et l'envoi automatique des rapports hebdomadaires</p>
          </div>
          <div style="display: flex; gap: 8px;">
            <button class="btn-primary" onclick="openAddRecipientModal()" data-testid="add-recipient-btn" style="font-size: 0.85rem; padding: 8px 16px;">
              <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="vertical-align: -2px; margin-right: 4px;"><path d="M12 5v14M5 12h14"/></svg>
              Ajouter un destinataire
            </button>
            <button class="btn-secondary enhanced-btn" onclick="sendAllReportsNow()" data-testid="send-all-btn" style="font-size: 0.85rem; padding: 8px 16px;">
              <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="vertical-align: -2px; margin-right: 4px;"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/></svg>
              Envoyer tous les rapports
            </button>
          </div>
        </div>

        <!-- Schedule info card -->
        <div class="card" style="padding: 16px 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
          <div style="display: flex; align-items: center; gap: 12px;">
            <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(108,75,160,0.12); display: flex; align-items: center; justify-content: center;">
              <svg width="20" height="20" fill="none" stroke="var(--brand-primary)" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
            </div>
            <div>
              <div style="color: var(--text-primary); font-weight: 600; font-size: 0.9rem;">Envoi automatique</div>
              <div style="color: var(--muted); font-size: 0.8rem;">Chaque lundi a 08:00 - <span id="reportingActiveCount">0</span> destinataires actifs</div>
            </div>
          </div>
          <div style="color: var(--muted); font-size: 0.8rem;">
            Dernier envoi : <span id="reportingLastRun" style="color: var(--text-primary); font-weight: 500;">--</span>
          </div>
        </div>

        <!-- Recipients table -->
        <div class="card table-card">
          <div class="chart-title" style="display: flex; justify-content: space-between; align-items: center;">
            <span>Destinataires</span>
            <div style="display: flex; gap: 8px;">
              <select id="recipientTypeFilter" onchange="loadRecipients()" style="background: var(--card); color: var(--text-primary); border: 1px solid var(--border); padding: 4px 10px; border-radius: 6px; font-size: 0.8rem;">
                <option value="">Tous les types</option>
                <option value="ceo">CEO</option>
                <option value="marketing">Marketing</option>
                <option value="partner">Partenaire</option>
              </select>
            </div>
          </div>
          <div class="table-container">
            <table>
              <thead>
                <tr>
                  <th>Nom</th>
                  <th>Email</th>
                  <th>Type</th>
                  <th>Partenaire</th>
                  <th>Statut</th>
                  <th>Dernier envoi</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody id="recipientsTableBody" data-testid="recipients-table">
                <tr><td colspan="7" class="loading"><div class="spinner"></div> Chargement...</td></tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Report Logs -->
        <div class="card table-card">
          <div class="chart-title">Historique des envois</div>
          <div class="table-container">
            <table>
              <thead>
                <tr>
                  <th>Date</th>
                  <th>Destinataire</th>
                  <th>Type</th>
                  <th>Periode</th>
                  <th>Statut</th>
                  <th>IA</th>
                  <th>Erreur</th>
                </tr>
              </thead>
              <tbody id="reportLogsTableBody" data-testid="report-logs-table">
                <tr><td colspan="7" class="loading"><div class="spinner"></div> Chargement...</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
    @endif

    <!-- Modal: Ajouter/Modifier Destinataire -->
    <div id="recipientModal" style="display:none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(13,10,26,0.7); z-index: 10000; justify-content: center; align-items: center;" data-testid="recipient-modal">
      <div style="background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 28px; width: 480px; max-width: 95vw; max-height: 90vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
          <h3 id="recipientModalTitle" style="margin: 0; color: var(--text-primary); font-size: 1.1rem;">Ajouter un destinataire</h3>
          <button onclick="closeRecipientModal()" style="background: none; border: none; color: var(--muted); cursor: pointer; font-size: 1.3rem;">&times;</button>
        </div>
        <form id="recipientForm" onsubmit="saveRecipient(event)">
          <input type="hidden" id="recipientId" value="">
          <div style="margin-bottom: 14px;">
            <label style="display: block; color: var(--muted); font-size: 0.8rem; margin-bottom: 4px;">Nom *</label>
            <input type="text" id="recipientName" required class="enhanced-date-input" style="width: 100%; box-sizing: border-box;" data-testid="recipient-name-input">
          </div>
          <div style="margin-bottom: 14px;">
            <label style="display: block; color: var(--muted); font-size: 0.8rem; margin-bottom: 4px;">Email *</label>
            <input type="email" id="recipientEmail" required class="enhanced-date-input" style="width: 100%; box-sizing: border-box;" data-testid="recipient-email-input">
          </div>
          <div style="margin-bottom: 14px;">
            <label style="display: block; color: var(--muted); font-size: 0.8rem; margin-bottom: 4px;">Type de rapport *</label>
            <select id="recipientType" required onchange="togglePartnerField()" class="enhanced-date-input" style="width: 100%; box-sizing: border-box;" data-testid="recipient-type-select">
              <option value="">Choisir...</option>
              <option value="ceo">CEO - Rapport complet tous operateurs</option>
              <option value="marketing">Marketing - Acquisition & Retention</option>
              <option value="partner">Partenaire - Transactions individuelles</option>
            </select>
          </div>
          <div id="partnerFieldGroup" style="display: none; margin-bottom: 14px;">
            <label style="display: block; color: var(--muted); font-size: 0.8rem; margin-bottom: 4px;">Partenaire associe * <span style="font-size: 0.7rem;">(RGPD: seules les donnees de CE partenaire seront incluses)</span></label>
            <input type="text" id="partnerSearch" placeholder="Rechercher un partenaire..." class="enhanced-date-input" style="width: 100%; box-sizing: border-box;" oninput="searchPartners()" autocomplete="off">
            <input type="hidden" id="recipientPartnerId" data-testid="recipient-partner-id">
            <div id="partnerSearchResults" style="max-height: 150px; overflow-y: auto; margin-top: 4px; border-radius: 6px;"></div>
          </div>
          <div style="display: flex; gap: 12px; margin-bottom: 14px;">
            <div style="flex: 1;">
              <label style="display: block; color: var(--muted); font-size: 0.8rem; margin-bottom: 4px;">Jour d'envoi</label>
              <select id="recipientDay" class="enhanced-date-input" style="width: 100%; box-sizing: border-box;">
                <option value="monday">Lundi</option>
                <option value="tuesday">Mardi</option>
                <option value="wednesday">Mercredi</option>
                <option value="thursday">Jeudi</option>
                <option value="friday">Vendredi</option>
              </select>
            </div>
            <div style="flex: 1;">
              <label style="display: block; color: var(--muted); font-size: 0.8rem; margin-bottom: 4px;">Heure d'envoi</label>
              <input type="time" id="recipientTime" value="08:00" class="enhanced-date-input" style="width: 100%; box-sizing: border-box;">
            </div>
          </div>
          <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 20px;">
            <button type="button" onclick="closeRecipientModal()" class="btn-secondary enhanced-btn" style="font-size: 0.85rem; padding: 8px 20px;">Annuler</button>
            <button type="submit" class="btn-primary" style="font-size: 0.85rem; padding: 8px 20px;" data-testid="save-recipient-btn">Enregistrer</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal: Apercu du Rapport -->
    <div id="previewModal" style="display:none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(13,10,26,0.85); z-index: 10001; justify-content: center; align-items: center;" data-testid="preview-modal">
      <div style="background: var(--card); border: 1px solid var(--border); border-radius: 16px; width: 780px; max-width: 95vw; height: 85vh; display: flex; flex-direction: column; overflow: hidden;">
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 16px 24px; border-bottom: 1px solid var(--border);">
          <h3 id="previewTitle" style="margin: 0; color: var(--text-primary); font-size: 1rem; font-weight: 700;">Apercu du rapport</h3>
          <button onclick="closePreviewModal()" style="background: none; border: none; color: var(--muted); cursor: pointer; font-size: 1.4rem; padding: 4px 8px;" data-testid="close-preview-btn">&times;</button>
        </div>
        <div id="previewContent" style="flex: 1; overflow: auto; padding: 0;"></div>
      </div>
    </div>

    <!-- Tab: Agent IA (Style ChatGPT avec Sidebar) -->
    @if(Auth::user()->isSuperAdmin())
    <div id="ai-agent" class="tab-content">
      <!-- Widget Quota + Monitoring -->
      <div id="aiQuotaMonitoring" style="display: flex; gap: 12px; margin-bottom: 16px; flex-wrap: wrap;">
        <!-- Quota du jour -->
        <div style="flex: 1; min-width: 200px; background: white; border-radius: 10px; border: 1px solid #e5e7eb; padding: 14px 18px; display: flex; align-items: center; gap: 14px;">
          <div style="width: 48px; height: 48px; border-radius: 50%; background: #eef2ff; display: flex; align-items: center; justify-content: center;">
            <svg width="22" height="22" fill="none" stroke="#6366f1" stroke-width="2" viewBox="0 0 24 24"><path d="M12 8v4l3 3"/><circle cx="12" cy="12" r="10"/></svg>
          </div>
          <div style="flex:1;">
            <div style="font-size: 0.75rem; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px;">Quota Aujourd'hui</div>
            <div style="display: flex; align-items: baseline; gap: 6px;">
              <span id="aiQuotaUsed" style="font-size: 1.4rem; font-weight: 700; color: #374151;">--</span>
              <span style="font-size: 0.85rem; color: #9ca3af;">/ <span id="aiQuotaLimit">250</span></span>
            </div>
            <div style="margin-top: 6px; height: 5px; background: #f3f4f6; border-radius: 3px; overflow: hidden;">
              <div id="aiQuotaBar" style="height: 100%; width: 0%; background: #6366f1; border-radius: 3px; transition: width 0.5s ease;"></div>
            </div>
          </div>
        </div>
        <!-- Temps de reponse moyen -->
        <div style="flex: 1; min-width: 200px; background: white; border-radius: 10px; border: 1px solid #e5e7eb; padding: 14px 18px; display: flex; align-items: center; gap: 14px;">
          <div style="width: 48px; height: 48px; border-radius: 50%; background: #ecfdf5; display: flex; align-items: center; justify-content: center;">
            <svg width="22" height="22" fill="none" stroke="#10b981" stroke-width="2" viewBox="0 0 24 24"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
          </div>
          <div>
            <div style="font-size: 0.75rem; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px;">Temps moyen</div>
            <span id="aiAvgTime" style="font-size: 1.4rem; font-weight: 700; color: #374151;">--</span>
            <span style="font-size: 0.85rem; color: #9ca3af;">ms</span>
          </div>
        </div>
        <!-- Total conversations 30j -->
        <div style="flex: 1; min-width: 200px; background: white; border-radius: 10px; border: 1px solid #e5e7eb; padding: 14px 18px; display: flex; align-items: center; gap: 14px;">
          <div style="width: 48px; height: 48px; border-radius: 50%; background: #fef3c7; display: flex; align-items: center; justify-content: center;">
            <svg width="22" height="22" fill="none" stroke="#f59e0b" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
          </div>
          <div>
            <div style="font-size: 0.75rem; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px;">Questions (30j)</div>
            <span id="aiTotalQuestions" style="font-size: 1.4rem; font-weight: 700; color: #374151;">--</span>
          </div>
        </div>
        <!-- Tokens consommes -->
        <div style="flex: 1; min-width: 200px; background: white; border-radius: 10px; border: 1px solid #e5e7eb; padding: 14px 18px; display: flex; align-items: center; gap: 14px;">
          <div style="width: 48px; height: 48px; border-radius: 50%; background: #fce7f3; display: flex; align-items: center; justify-content: center;">
            <svg width="22" height="22" fill="none" stroke="#ec4899" stroke-width="2" viewBox="0 0 24 24"><path d="M12 2a10 10 0 100 20 10 10 0 000-20z"/><path d="M12 6v6l4 2"/></svg>
          </div>
          <div>
            <div style="font-size: 0.75rem; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px;">Tokens (30j)</div>
            <span id="aiTotalTokens" style="font-size: 1.4rem; font-weight: 700; color: #374151;">--</span>
          </div>
        </div>
      </div>

      <div style="display: flex; gap: 16px; height: 650px;">
        
        <!-- Sidebar Historique -->
        <div class="ai-sidebar" style="width: 280px; background: #f7f7f8; border-radius: 12px; border: 1px solid #e5e7eb; display: flex; flex-direction: column;">
          <div style="padding: 16px; border-bottom: 1px solid #e5e7eb; background: white; border-radius: 12px 12px 0 0;">
            <div style="display: flex; justify-content: between; align-items: center; margin-bottom: 12px;">
              <h6 style="margin: 0; font-weight: 600; color: #374151;">Conversations</h6>
              <button onclick="newAIConversationNow()" style="background: #6366f1; border: none; color: white; padding: 6px 10px; border-radius: 6px; font-size: 0.8rem; cursor: pointer;">+ Nouveau Chat</button>
            </div>
            <div style="display: flex; gap: 6px;">
              <button onclick="saveCurrentConversation()" style="background: #10b981; border: none; color: white; padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; cursor: pointer;">Sauver</button>
              <button onclick="loadConversationDialog()" style="background: #f59e0b; border: none; color: white; padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; cursor: pointer;">Charger</button>
              <button onclick="clearAllConversations()" style="background: #ef4444; border: none; color: white; padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; cursor: pointer;">Vider</button>
            </div>
          </div>
          <div id="aiConversationsList" style="flex: 1; overflow-y: auto; padding: 8px;">
            <div class="ai-conversation-item active" data-session="current" style="padding: 12px; margin: 4px 0; background: white; border-radius: 8px; border-left: 3px solid #6366f1; cursor: pointer;">
              <div style="font-size: 0.85rem; font-weight: 500; color: #374151;">Conversation Actuelle</div>
              <div style="font-size: 0.75rem; color: #6b7280; margin-top: 4px;">Juste maintenant</div>
            </div>
          </div>
          <div style="padding: 12px; border-top: 1px solid #e5e7eb; background: #f9fafb; border-radius: 0 0 12px 0;">
            <div style="font-size: 0.75rem; color: #6b7280; text-align: center;">
              Session : <code id="aiSessionSidebar" style="font-size: 0.7rem;">nouvelle</code><br>
              Expert ML
            </div>
          </div>
        </div>

        <!-- Zone de Chat Principale -->
        <div class="ai-chat-container" style="flex: 1; background: white; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); display: flex; flex-direction: column;">
          <div class="ai-header" style="padding: 16px 24px; border-bottom: 1px solid #e5e7eb; background: linear-gradient(135deg, #6366f1, #8b5cf6); color: white; border-radius: 12px 12px 0 0;">
            <div style="display: flex; justify-content: between; align-items: center;">
              <div>
                <h5 style="margin: 0; font-weight: 600;">Assistant IA Expert ML</h5>
                <small style="opacity: 0.9;">Recommandations instantanees</small>
              </div>
              <button onclick="newAIConversationNow()" style="background: rgba(255,255,255,0.2); border: none; color: white; padding: 6px 12px; border-radius: 6px; font-size: 0.85rem; cursor: pointer;">+ Nouveau</button>
            </div>
          </div>

          <div id="aiMessagesZone" style="flex: 1; overflow-y: auto; padding: 0;">
            <div class="ai-welcome-msg" style="padding: 24px; background: #f9fafb; border-bottom: 1px solid #f0f0f0;">
              <div style="max-width: 800px;">
                <p style="margin: 0 0 12px 0; color: #374151; font-size: 1rem;">
                  <strong>Salut ! Je suis votre expert IA.</strong> Posez-moi n'importe quelle question sur vos donnees ML et strategies de pricing.
                </p>
                <div style="display: flex; flex-wrap: wrap; gap: 8px; margin-top: 16px;">
                  <button onclick="askAIQuestion('Quel est le taux de succes actuel ?')" style="background: #f3f4f6; border: 1px solid #d1d5db; border-radius: 16px; padding: 6px 12px; font-size: 0.9rem; color: #374151; cursor: pointer;">Taux de succes actuel ?</button>
                  <button onclick="askAIQuestion('Compare quotidien 0.3 TND vs mensuel 3.0 TND')" style="background: #f3f4f6; border: 1px solid #d1d5db; border-radius: 16px; padding: 6px 12px; font-size: 0.9rem; color: #374151; cursor: pointer;">ROI quotidien vs mensuel</button>
                  <button onclick="askAIQuestion('Quelle strategie pour les clients High Risk ?')" style="background: #f3f4f6; border: 1px solid #d1d5db; border-radius: 16px; padding: 6px 12px; font-size: 0.9rem; color: #374151; cursor: pointer;">Strategie High Risk ?</button>
                  <button onclick="askAIQuestion('Explique les top 5 features ML les plus importantes')" style="background: #f3f4f6; border: 1px solid #d1d5db; border-radius: 16px; padding: 6px 12px; font-size: 0.9rem; color: #374151; cursor: pointer;">Top features ML</button>
                </div>
              </div>
            </div>
            <div id="aiMessagesContainer" style="padding: 0; min-height: 200px;"></div>
            <div id="aiTypingIndicator" style="display: none; padding: 16px 24px;">
              <div style="display: flex; align-items: center; color: #6b7280;">
                <div style="display: flex; gap: 4px; margin-right: 8px;">
                  <div style="width: 6px; height: 6px; background: #6b7280; border-radius: 50%; animation: ai-dot1 1.4s infinite;"></div>
                  <div style="width: 6px; height: 6px; background: #6b7280; border-radius: 50%; animation: ai-dot2 1.4s infinite;"></div>
                  <div style="width: 6px; height: 6px; background: #6b7280; border-radius: 50%; animation: ai-dot3 1.4s infinite;"></div>
                </div>
                <span style="font-style: italic; font-size: 0.9rem;">Agent IA analyse vos donnees...</span>
              </div>
            </div>
          </div>

          <div class="ai-input-zone" style="padding: 16px 24px; background: white; border-top: 1px solid #e5e7eb; border-radius: 0 0 12px 12px;">
            <div style="display: flex; align-items: end; gap: 8px; max-width: 100%; position: relative;">
              <div style="flex: 1; position: relative;">
                <textarea id="aiQuestionInput" placeholder="Posez votre question..." style="width: 100%; min-height: 44px; max-height: 120px; padding: 12px 50px 12px 16px; border: 2px solid #e5e7eb; border-radius: 22px; font-size: 1rem; resize: none; outline: none; font-family: inherit;" rows="1" onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendAIQuestionNow();}"></textarea>
                <button id="aiSendBtn" onclick="sendAIQuestionNow()" style="position: absolute; right: 8px; bottom: 6px; width: 32px; height: 32px; background: #6366f1; border: none; border-radius: 50%; color: white; display: flex; align-items: center; justify-content: center; cursor: pointer;">&#10148;</button>
              </div>
            </div>
            <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: 12px; margin-top: 8px;">
              <label for="aiProviderSelectDashboard" style="color: #9ca3af; font-size: 0.8rem; margin: 0;">Modele :</label>
              <select id="aiProviderSelectDashboard" style="font-size: 0.8rem; padding: 4px 8px; border-radius: 6px; border: 1px solid #e5e7eb; color: #374151; min-width: 160px;">
                <option value="gemini" selected>Gemini 2.5 Flash (Rapide)</option>
                <option value="openai">OpenAI GPT-4 (Detaille)</option>
                <option value="anthropic">Claude (Anthropic)</option>
              </select>
              <small style="color: #9ca3af; font-size: 0.8rem;">Session <code id="aiCurrentSession" style="font-size: 0.75rem; color: #6366f1;">nouvelle</code></small>
            </div>
          </div>
        </div>
      </div>
    </div>
    @endif

    <!-- Modal pour nommer la conversation Agent IA -->
    <div id="aiRenameModal" style="display: none; position: fixed; inset: 0; z-index: 9999; background: rgba(0,0,0,0.4); align-items: center; justify-content: center;">
      <div style="background: white; border-radius: 12px; padding: 24px; min-width: 360px; max-width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.2);">
        <div style="font-weight: 600; font-size: 1.1rem; color: #374151; margin-bottom: 12px;">Nommer la conversation</div>
        <input type="text" id="aiRenameModalInput" placeholder="Nom de la conversation" style="width: 100%; padding: 10px 12px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 1rem; margin-bottom: 16px; box-sizing: border-box;">
        <div style="display: flex; gap: 8px; justify-content: flex-end;">
          <button type="button" id="aiRenameModalCancel" style="padding: 8px 16px; border: 1px solid #d1d5db; border-radius: 8px; background: #f9fafb; color: #374151; cursor: pointer; font-size: 0.9rem;">Annuler</button>
          <button type="button" id="aiRenameModalOk" style="padding: 8px 16px; border: none; border-radius: 8px; background: #6366f1; color: white; cursor: pointer; font-size: 0.9rem;">OK</button>
        </div>
      </div>
    </div>

    <!-- Tab 6: Insights (Hidden) -->
    <!--
    <div id="insights" class="tab-content">
      <div class="insights-grid">
        <div class="insight-card">
          <div class="insight-title">
            <span style="color: var(--success);">✅</span>
            Positive Insights
          </div>
          <ul class="insight-list" id="positiveInsights">
            <li class="loading">
              <div class="spinner"></div>
              Loading insights...
            </li>
          </ul>
        </div>

        <div class="insight-card">
          <div class="insight-title">
            <span style="color: var(--warning);">⚠️</span>
            Challenges & Areas for Improvement
          </div>
          <ul class="insight-list" id="challenges">
            <li class="loading">
              <div class="spinner"></div>
              Loading challenges...
            </li>
          </ul>
        </div>

        <div class="insight-card">
          <div class="insight-title">
            <span style="color: var(--accent);">🎯</span>
            Strategic Recommendations
          </div>
          <ul class="insight-list" id="recommendations">
            <li class="loading">
              <div class="spinner"></div>
              Loading recommendations...
            </li>
          </ul>
        </div>

        <div class="insight-card">
          <div class="insight-title">
            <span style="color: var(--brand-red);">🚀</span>
            Next Steps
          </div>
          <ul class="insight-list" id="nextSteps">
            <li class="loading">
              <div class="spinner"></div>
              Loading next steps...
            </li>
          </ul>
        </div>
      </div>
    </div>
    -->
  </div>

  <!-- Modules JS extraits pour maintenabilité -->
  <script src="/js/dashboard/utils.js"></script>
  <script src="/js/dashboard/eklektik.js"></script>
  <script src="/js/dashboard/charts.js"></script>
  <script src="/js/dashboard/timwe.js"></script>
  <script src="/js/dashboard/ooredoo.js"></script>
  <script src="/js/dashboard/tables.js"></script>
  <script src="/js/dashboard/reporting.js"></script>

  <script>
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
          { name: 'timwe_stats', url: `/api/dashboard/split/timwe?${queryString}`, label: 'Timwe', weight: 10 }
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
              dashboardData = window._dashboardData;
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
        { label: 'Ce mois', type: 'month' },
        { label: 'Mois dernier', type: 'lastMonth' }
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
  </script>

  <!-- DÉSACTIVÉ POUR OPTIMISATION: Modal pour afficher les détails des transactions d'un client -->
  <!-- Ce modal a été désactivé définitivement pour améliorer les performances du dashboard -->
  <!--
  <div id="clientTransactionsModal" style="display: none;">Modal désactivé</div>
  -->

  <style>
    @keyframes slideDown {
      from {
        opacity: 0;
        transform: translateY(-50px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    
    #clientTransactionsModal table tbody tr:hover {
      background-color: #f9fafb;
    }
    
    #clientTransactionsModal table tbody tr {
      transition: background-color 0.2s;
    }

    /* Agent IA Styles - Dark Theme */
    #ai-agent [style*="background: white"],
    #ai-agent [style*="background: #f7f7f8"],
    #ai-agent [style*="background: #f9fafb"],
    #ai-agent [style*="background: #f3f4f6"],
    #ai-agent [style*="background: #f9fafb"] {
      background: var(--card) !important;
    }
    #ai-agent [style*="color: #374151"],
    #ai-agent [style*="color: #6b7280"] {
      color: var(--text-secondary) !important;
    }
    #ai-agent [style*="border: 1px solid #e5e7eb"],
    #ai-agent [style*="border: 1px solid #d1d5db"] {
      border-color: var(--border) !important;
    }
    #ai-agent [style*="border-bottom: 1px solid #e5e7eb"],
    #ai-agent [style*="border-top: 1px solid #e5e7eb"],
    #ai-agent [style*="border-bottom: 1px solid #f0f0f0"] {
      border-color: var(--border) !important;
    }
    #ai-agent textarea,
    #ai-agent select,
    #ai-agent input[type="text"] {
      background: rgba(255,255,255,0.04) !important;
      color: var(--text-primary) !important;
      border-color: var(--border) !important;
    }
    #ai-agent [style*="background: #eef2ff"],
    #ai-agent [style*="background: #ecfdf5"],
    #ai-agent [style*="background: #fef3c7"],
    #ai-agent [style*="background: #fce7f3"] {
      background: rgba(255,255,255,0.05) !important;
    }
    #aiRenameModal > div {
      background: var(--card) !important;
      border: 1px solid var(--border);
    }
    #aiRenameModal [style*="color: #374151"] {
      color: var(--text-primary) !important;
    }
    #aiRenameModal input {
      background: rgba(255,255,255,0.04) !important;
      color: var(--text-primary) !important;
      border-color: var(--border) !important;
    }
    #aiRenameModal [style*="background: #f9fafb"] {
      background: rgba(255,255,255,0.05) !important;
      color: var(--text-secondary) !important;
    }
    
    .ai-conversation-item {
      transition: all 0.2s;
      border: 1px solid transparent;
    }
    .ai-conversation-item:hover {
      background: rgba(255,255,255,0.05) !important;
      border-color: var(--border);
    }
    .ai-conversation-item.active {
      border-left-color: var(--brand-primary) !important;
      background: rgba(108, 75, 160, 0.08) !important;
    }
    .ai-sidebar button:hover {
      opacity: 0.8;
      transform: translateY(-1px);
    }
    .ai-message-user {
      padding: 16px 24px;
      background: var(--card);
      border-bottom: 1px solid var(--border);
    }
    .ai-message-assistant {
      padding: 16px 24px; 
      background: rgba(255,255,255,0.02);
      border-bottom: 1px solid var(--border);
    }
    .ai-message-content {
      max-width: 100%;
      line-height: 1.6;
      color: var(--text-secondary);
    }
    .ai-message-user .ai-message-content {
      font-weight: 500;
      color: var(--text-primary);
    }
    .ai-suggestion-simple:hover {
      background: rgba(255,255,255,0.1) !important;
      border-color: var(--border) !important;
    }
    #aiSendBtn:hover {
      background: #5B3FA0 !important;
      transform: scale(1.05);
    }
    #aiSendBtn:disabled {
      background: rgba(255,255,255,0.1) !important;
      cursor: not-allowed;
      transform: none;
    }
    @keyframes ai-dot1 { 0%, 60%, 100% { transform: translateY(0); } 30% { transform: translateY(-10px); } }
    @keyframes ai-dot2 { 0%, 60%, 100% { transform: translateY(0); } 30% { transform: translateY(-10px); } }
    @keyframes ai-dot3 { 0%, 60%, 100% { transform: translateY(0); } 30% { transform: translateY(-10px); } }
    .ai-dot1 { animation-delay: 0ms; }
    .ai-dot2 { animation-delay: 150ms; }
    .ai-dot3 { animation-delay: 300ms; }
  </style>

  <script>
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
  </script>

</body>
</html>

