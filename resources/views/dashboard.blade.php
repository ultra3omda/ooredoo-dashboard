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
  <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <style>
    :root {
      @if($isOoredoo)
      --brand-primary: #E30613;
      --brand-secondary: #DC2626;
      --theme-name: 'Ooredoo';
      @else
      --brand-primary: #6B46C1;
      --brand-secondary: #8B5CF6;
      --theme-name: 'Club Privilèges';
      @endif
      --brand-dark: #1f2937;
      --bg: #f8fafc;
      --card: #ffffff;
      --muted: #64748b;
      --success: #10b981;
      --warning: #f59e0b;
      --danger: #ef4444;
      --accent: #3b82f6;
      --border: #e2e8f0;
      /* Backward compatibility */
      --brand-red: var(--brand-primary);
    }
    
    * { box-sizing: border-box; }
    html, body { 
      margin: 0; 
      padding: 0; 
      background: var(--bg); 
      color: var(--brand-dark); 
      font-family: 'Inter', system-ui, -apple-system, sans-serif;
      line-height: 1.5;
    }
    
    .container { 
      max-width: 1400px; 
      margin: 0 auto; 
      padding: 20px; 
    }
    
    /* Header */
    .header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      background: var(--card);
      padding: 16px 20px; /* Aligné avec le reste du contenu */
      border-radius: 12px;
      margin-bottom: 24px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.1);
      width: 100%; /* Prend toute la largeur disponible */
      box-sizing: border-box; /* Inclut padding dans la largeur */
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
      font-size: 24px;
      font-weight: 700;
      margin: 0;
      color: var(--brand-dark);
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
      background: var(--bg);
      padding: 8px 16px;
      border-radius: 8px;
      border: 1px solid var(--border);
    }
    
    .user-info {
      display: flex;
      flex-direction: column;
      align-items: flex-end;
    }
    
    .user-name {
      font-weight: 600;
      color: var(--brand-dark);
      font-size: 14px;
    }
    
    .user-role {
      font-size: 12px;
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
      background: #c20510;
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
    .nav-tabs {
      display: flex;
      background: var(--card);
      border-radius: 12px;
      padding: 8px;
      margin-bottom: 24px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.1);
      /* Sticky navigation for mobile */
      position: sticky;
      top: 0;
      z-index: 100;
      /* Single line on mobile */
      overflow-x: auto;
      scrollbar-width: none; /* Firefox */
      -ms-overflow-style: none; /* IE/Edge */
    }
    
    /* Hide scrollbar for webkit browsers */
    .nav-tabs::-webkit-scrollbar {
      display: none;
    }
    
    .nav-tab {
      flex: 1;
      padding: 12px 16px;
      text-align: center;
      border-radius: 8px;
      cursor: pointer;
      font-weight: 500;
      transition: all 0.2s;
      border: none;
      background: transparent;
      color: var(--muted);
      /* Mobile: prevent shrinking below content size */
      flex-shrink: 0;
      white-space: nowrap;
      min-width: fit-content;
    }
    
    .nav-tab.active {
      background: var(--brand-red);
      color: white;
    }
    
    .nav-tab:hover:not(.active) {
      background: #f1f5f9;
    }
    
    /* Tab Content */
    .tab-content {
      display: none;
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
      border-radius: 12px;
      padding: 20px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    
    /* KPI Cards */
    .kpi-card {
      grid-column: span 3;
      text-align: center;
    }
    
    .kpi-title {
      font-size: 12px;
      color: var(--muted);
      margin-bottom: 8px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    .kpi-value {
      font-size: 28px;
      font-weight: 700;
      margin-bottom: 4px;
      color: var(--brand-dark);
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
      font-size: 16px;
      font-weight: 600;
      margin-bottom: 16px;
      color: var(--brand-dark);
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
      background: #f8fafc;
      font-weight: 600;
      color: var(--brand-dark);
      font-size: 12px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    tr:hover {
      background: #f8fafc;
    }
    
    /* Badges */
    .badge {
      display: inline-flex;
      align-items: center;
      padding: 4px 8px;
      border-radius: 6px;
      font-size: 12px;
      font-weight: 500;
    }
    
    .badge-success { background: #dcfce7; color: #166534; }
    .badge-warning { background: #fef3c7; color: #92400e; }
    .badge-danger { background: #fee2e2; color: #991b1b; }
    .badge-info { background: #dbeafe; color: #1e40af; }
    .badge-secondary { background: #f3f4f6; color: #374151; }

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
      background: white;
      transition: border-color 0.2s;
    }
    
    .date-input:focus {
      outline: none;
      border-color: var(--brand-red);
      box-shadow: 0 0 0 3px rgba(227, 6, 19, 0.1);
    }
    
    .btn-refresh {
      width: 100%;
      padding: 8px 12px;
      background: var(--brand-red);
      color: white;
      border: none;
      border-radius: 6px;
      font-size: 14px;
      font-weight: 500;
      cursor: pointer;
      transition: background-color 0.2s;
    }
    
    .btn-refresh:hover {
      background: #c41e3a;
    }
    
    .btn-refresh:active {
      transform: translateY(1px);
    }
    
    .btn-refresh:disabled {
      background: #ccc;
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
      background: rgba(0,0,0,0.3);
      display: flex;
      justify-content: center;
      align-items: center;
      z-index: 9999;
    }
    
    .loading-spinner {
      background: white;
      padding: 30px;
      border-radius: 10px;
      text-align: center;
      box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    }
    
    .spinner {
      border: 4px solid #f3f3f3;
      border-top: 4px solid var(--brand-red);
      border-radius: 50%;
      width: 40px;
      height: 40px;
      animation: spin 1s linear infinite;
      margin: 0 auto 15px;
    }
    
    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }
    
    /* Skeleton loading styles */
    .skeleton-text {
      height: 24px;
      background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
      background-size: 200% 100%;
      animation: skeleton-loading 1.5s infinite;
      border-radius: 4px;
      width: 80%;
    }
    
    .skeleton-text-small {
      height: 16px;
      background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
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
      padding: 15px 20px;
      border-radius: 8px;
      z-index: 1000;
      max-width: 400px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.2);
      animation: slideIn 0.3s ease;
    }
    
    .notification.error {
      background: #f8d7da;
      color: #721c24;
      border-left: 4px solid #dc3545;
    }
    
    .notification.success {
      background: #d4edda;
      color: #155724;
      border-left: 4px solid #28a745;
    }
    
    .notification.info {
      background: #d1ecf1;
      color: #0c5460;
      border-left: 4px solid #17a2b8;
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
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    
    .operator-select:focus {
      outline: none;
      border-color: var(--brand-red);
      box-shadow: 0 0 0 3px rgba(227, 6, 19, 0.1);
    }
    
    .operator-select option {
      background: white;
      color: var(--brand-dark);
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
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 16px;
      padding: 24px;
      margin-bottom: 32px;
      box-shadow: 0 4px 6px rgba(0,0,0,0.05);
    }
    
    /* Mobile responsive filters */
    @media (max-width: 768px) {
      .enhanced-filters-bar {
        padding: 16px;
        margin-bottom: 20px;
      }
    }

    .date-selection-section {
      margin-bottom: 24px;
    }

    .section-title {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 18px;
      font-weight: 600;
      color: var(--brand-dark);
      margin-bottom: 20px;
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
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 12px;
      padding: 20px;
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
      color: var(--brand-dark);
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
      background: white;
      transition: all 0.2s;
    }

    .enhanced-date-input:focus {
      outline: none;
      border-color: var(--brand-red);
      box-shadow: 0 0 0 3px rgba(227, 6, 19, 0.1);
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
      font-size: 14px;
      font-weight: 600;
      color: var(--brand-dark);
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
      background: white;
      cursor: pointer;
      transition: all 0.2s;
    }

    .enhanced-select:focus {
      outline: none;
      border-color: var(--brand-red);
      box-shadow: 0 0 0 3px rgba(227, 6, 19, 0.1);
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
      background: white;
      cursor: pointer;
      transition: all 0.2s;
      user-select: none;
      font-size: 14px;
    }
    
    .multi-select-header:hover {
      border-color: var(--brand-red);
      box-shadow: 0 0 0 3px rgba(227, 6, 19, 0.1);
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
      background: white;
      border: 1px solid var(--border);
      border-top: none;
      border-radius: 0 0 8px 8px;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
      z-index: 1000;
      max-height: 250px;
      overflow-y: auto;
    }
    
    .select-all-option {
      padding: 8px 12px;
      border-bottom: 1px solid var(--border);
      background: #f8fafc;
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
      background: rgba(227, 6, 19, 0.05);
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
      background: #f8fafc;
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
      background: #dc2626;
      transform: translateY(-1px);
    }
    
    .btn-sm.btn-secondary {
      background: #f1f5f9;
      color: var(--text);
      border: 1px solid var(--border);
    }
    
    .btn-sm.btn-secondary:hover {
      background: #e2e8f0;
      transform: translateY(-1px);
    }
    
    /* Test Statistics Cards */
    .test-stat-card {
      background: white;
      border: 1px solid #e2e8f0;
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
      color: var(--text);
    }
    
    /* Progress animations */
    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }
    
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
        grid-column: span 12 !important; /* 1 par ligne sur mobile */
      }
    }
    
    @media (max-width: 600px) {
      .kpi-card {
        grid-column: span 12 !important; /* 1 par ligne sur petit mobile */
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
        grid-column: span 12 !important;
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
      padding: 10px 16px;
      border: none;
      border-radius: 8px;
      font-size: 14px;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.2s;
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .btn-primary {
      background: var(--brand-red);
      color: white;
    }

    .btn-primary:hover {
      background: #c41e3a;
      transform: translateY(-1px);
    }

    .btn-secondary {
      background: var(--accent);
      color: white;
    }

    .btn-secondary:hover {
      background: #2563eb;
      transform: translateY(-1px);
    }

    .btn-accent {
      background: var(--success);
      color: white;
    }

    .btn-accent:hover {
      background: #059669;
      transform: translateY(-1px);
    }

    .btn-info {
      background: linear-gradient(135deg, #36d1dc 0%, #5b86e5 100%);
      color: white;
    }

    .btn-info:hover {
      background: linear-gradient(135deg, #2bbac6 0%, #4a75d3 100%);
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
      background: #f8fafc;
      font-weight: 600;
      color: var(--brand-dark);
      font-size: 13px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      padding: 16px;
      border-bottom: 2px solid var(--border);
    }

    .enhanced-table td {
      padding: 16px;
      border-bottom: 1px solid #f1f5f9;
    }

    .enhanced-table tr:hover {
      background: #f8fafc;
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
        min-height: 280px; /* Hauteur réduite */
      }
      
      /* Header responsive sur tablet */
      .header {
        padding: 14px 16px;
        flex-wrap: wrap;
        gap: 12px;
      }
      
      .header h1 {
        font-size: 20px; /* Titre plus petit sur tablet */
      }
      
      .nav-tabs { 
        flex-direction: column;
        gap: 8px;
      }
      .nav-tab { 
        text-align: center; 
        padding: 12px 16px;
      }
      
      .merchants-kpis-row {
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
      
      /* Typography responsive tablet */
      .kpi-value {
        font-size: clamp(26px, 4.5vw, 32px);
      }
      .kpi-label {
        font-size: clamp(12px, 3vw, 14px);
      }
      .kpi-change {
        font-size: clamp(10px, 2.5vw, 12px);
      }
      
      /* Enhanced table responsiveness for tablet */
      .table-wrapper {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
      }
      
      .enhanced-table {
        min-width: 500px;
        font-size: 13px;
      }
      
      .enhanced-table th,
      .enhanced-table td {
        padding: 12px 8px;
      }
    }
    
    /* Mobile Large (480px - 768px) */
    @media (max-width: 600px) {
      .kpi-card { grid-column: span 6; } /* 2 par ligne maintenu */
      .chart-card { min-height: 250px; }
      
      .container { padding: 16px 12px; }
      
      /* Header alignment sur mobile */
      .header {
        padding: 12px 12px; /* Même padding que le container */
      }
      
      /* Navigation tabs optimisées pour mobile */
      .nav-tabs {
        margin-bottom: 16px;
        padding: 6px;
        border-radius: 10px;
        /* Amélioration sticky - plus proche du header */
        top: 2px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.15);
      }
      
      .nav-tab {
        padding: 10px 12px;
        font-size: 14px;
        min-width: max-content;
        /* Plus compact sur mobile */
        margin: 0 2px;
      }
      
      .nav-tab:first-child {
        margin-left: 0;
      }
      
      .nav-tab:last-child {
        margin-right: 0;
      }
      
      /* KPI values responsive - taille réduite */
      .kpi-value { 
        font-size: clamp(20px, 4.5vw, 28px); 
      }
      .kpi-label { 
        font-size: clamp(10px, 2.5vw, 12px); 
      }
      .kpi-change { 
        font-size: clamp(11px, 2.5vw, 13px); 
      }
      
      /* Réduction de la hauteur des cartes KPI pour mobile */
      .kpi-card {
        padding: 12px 16px;
        min-height: 75px;
      }
      
      /* Logo responsive sur mobile */
      .logo {
        width: 100px;
        height: auto;
      }
      
      .header h1 {
        font-size: 18px;
      }
      
      /* User menu responsive */
      .user-menu {
        padding: 6px 12px;
      }
      
      .user-name {
        font-size: 12px;
      }
      
      .user-role {
        font-size: 10px;
      }
      
      .admin-btn {
        padding: 4px 8px;
        font-size: 10px;
      }
      
      .logout-btn {
        padding: 4px 8px;
        font-size: 10px;
      }
    }
    
    /* Mobile Small (<480px) */
    @media (max-width: 480px) {
      .kpi-card { grid-column: span 12; } /* 1 par ligne sur très petit écran */
      .chart-card { min-height: 220px; }
      
      .container { padding: 12px 8px; }
      
      /* Navigation tabs ultra compactes */
      .nav-tabs {
        padding: 4px;
        margin-bottom: 12px;
      }
      
      .nav-tab {
        padding: 8px 10px;
        font-size: 13px;
        border-radius: 6px;
      }
      
      /* Header alignment sur très petit mobile */
      .header {
        padding: 8px 8px; /* Même padding que le container */
        flex-direction: column;
        gap: 12px;
        text-align: center;
      }
      
      .header-left, .header-right {
        justify-content: center;
        width: 100%;
      }
      
      .header-left {
        flex-direction: column;
        gap: 8px;
      }
      
      /* Logo très compact sur très petit mobile */
      .logo {
        width: 80px;
        height: auto;
      }
      
      .header h1 {
        font-size: 16px;
        text-align: center;
      }
      
      /* User menu stack vertical sur très petit mobile */
      .user-menu {
        flex-direction: column;
        gap: 8px;
        padding: 8px;
        align-items: center;
      }
      
      .user-info {
        align-items: center;
        text-align: center;
      }
      
      .admin-btn, .logout-btn {
        padding: 4px 8px;
        font-size: 9px;
        min-width: 60px;
      }
      
      .merchants-kpis-row,
      .trans-kpis-row,
      .sub-kpis-row {
        grid-template-columns: 1fr;
        gap: 12px;
      }
      
      /* Typography ultra mobile */
      .kpi-value { 
        font-size: clamp(20px, 6vw, 28px); 
      }
      h1, h2 { 
        font-size: clamp(18px, 5vw, 24px); 
      }
      h3 { 
        font-size: clamp(16px, 4vw, 20px); 
      }
      
      .enhanced-filters-bar {
        padding: 16px;
      }
      
      .date-inputs {
        flex-direction: column;
        gap: 8px;
      }
      
      .date-separator {
        text-align: center;
        margin: 8px 0;
      }
      
      /* Tables très responsive */
      .table-wrapper {
        font-size: 11px;
        border-radius: 6px;
      }
      
      .enhanced-table {
        min-width: 320px; /* Largeur minimale pour très petit mobile */
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
      background: white;
      border-radius: 8px;
      padding: 16px;
      margin-bottom: 16px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
      transition: transform 0.2s, box-shadow 0.2s;
    }

    .kpi-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    }

    .kpi-value {
      font-size: 24px;
      font-weight: bold;
      color: var(--brand-red);
      margin-bottom: 4px;
    }

    .kpi-label {
      font-size: 12px;
      color: #666;
      text-transform: uppercase;
      letter-spacing: 0.5px;
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

    <!-- Navigation Tabs -->
    <div class="nav-tabs">
      <button class="nav-tab active" onclick="showTab('overview')">Overview</button>
      <button class="nav-tab" onclick="showTab('subscriptions')">Subscriptions</button>
      <button class="nav-tab" onclick="showTab('transactions')">Transactions</button>
      <button class="nav-tab" onclick="showTab('merchants')">Merchants</button>
      @if(Auth::user()->canViewTimweSection())
      <button class="nav-tab" onclick="showTab('timwe')">📱 Timwe</button>
      @endif
      @if(Auth::user()->canViewTimweSection())
      <button class="nav-tab" onclick="showTab('ooredoo')">📱 Ooredoo/DGV</button>
      @endif
      @if(Auth::user()->canViewEklektikSection())
      <button class="nav-tab" onclick="showTab('eklektik')">📞 Eklektik</button>
      @endif
      <button class="nav-tab" onclick="showTab('comparison')">Comparison</button>
      @if(Auth::user()->isSuperAdmin())
      <button class="nav-tab" onclick="showTab('ai-agent')">Agent IA</button>
      <button class="nav-tab" onclick="window.location.href='{{ route('admin.timwe-diagnostic') }}'">Diagnostic Timwe</button>
      @endif
      <!-- <button class="nav-tab" onclick="showTab('insights')">Insights</button> -->
    </div>

    <script>
    // Tab switching functionality - Défini avant les boutons pour éviter l'erreur "showTab is not defined"
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

      // Add active class to clicked tab
      event.target.classList.add('active');
      
      // Auto-scroll to center active tab on mobile
      if (typeof centerActiveTab === 'function') {
        centerActiveTab(event.target);
      }
      
      // Ne pas recharger les données Eklektik à chaque visite d'onglet
      // (les données se chargent en une seule fois au démarrage ou via le bouton d'actualisation)
      if (tabName === 'eklektik') {
        console.log('📞 Onglet Eklektik activé (sans rechargement des données)');
      }
      
      // Masquer la section dates / périodes sur l'onglet Agent IA
      var filtersBar = document.querySelector('.filters-bar, .date-filters, [class*="filter"]');
      if (filtersBar) {
        filtersBar.style.display = (tabName === 'ai-agent') ? 'none' : '';
      }
      
      // Charger l'historique des conversations Agent IA dès l'ouverture de l'onglet
      if (tabName === 'ai-agent' && typeof initializeAIDashboard === 'function') {
        initializeAIDashboard();
      }
      
      // Resize charts when tab becomes visible
      setTimeout(() => {
        // Resize main dashboard charts
        Object.values(charts).forEach(chart => {
          if (chart && typeof chart.resize === 'function') {
            chart.resize();
          }
        });
        
        // Eklektik charts removed - no need to resize
      }, 100);
    }
    </script>

    <!-- Enhanced Date & Filters Bar -->
    <div class="enhanced-filters-bar">
      <!-- Date Selection Section -->
      <div class="date-selection-section">
        <div class="section-title">
          <span class="section-icon">📅</span>
          <span>Sélection des Périodes</span>
        </div>
        
        <div class="date-periods">
          <!-- Période Principale -->
          <div class="date-period primary-period">
            <div class="period-header">
              <span class="period-icon">🔵</span>
              <span class="period-label">Période Principale</span>
            </div>
            <div class="date-inputs">
              <div class="date-input-group">
                <label>Du</label>
                <input type="date" id="start-date" class="enhanced-date-input" onchange="updateDateRange()">
              </div>
              <div class="date-separator">→</div>
              <div class="date-input-group">
                <label>Au</label>
                <input type="date" id="end-date" class="enhanced-date-input" onchange="updateDateRange()">
              </div>
            </div>
            <div class="period-display" id="primaryPeriod">Chargement...</div>
          </div>

          <!-- Période de Comparaison -->
          <div class="date-period comparison-period">
            <div class="period-header">
              <span class="period-icon">🟡</span>
              <span class="period-label">Période de Comparaison</span>
            </div>
            <div class="date-inputs">
              <div class="date-input-group">
                <label>Du</label>
                <input type="date" id="comparison-start-date" class="enhanced-date-input" onchange="updateDateRange()">
              </div>
              <div class="date-separator">→</div>
              <div class="date-input-group">
                <label>Au</label>
                <input type="date" id="comparison-end-date" class="enhanced-date-input" onchange="updateDateRange()">
              </div>
            </div>
            <div class="period-display" id="comparisonPeriod">Chargement...</div>
          </div>
        </div>
      </div>



      <!-- Filters & Controls Section -->
      <div class="controls-section">
        <div class="control-group">
          <div class="control-label">
            <span class="control-icon">📱</span>
            <span>Opérateurs</span>
          </div>
          <div class="multi-select-container">
            <div class="multi-select-header" onclick="toggleOperatorDropdown()">
              <span id="selected-operators-text">📱 Tous les opérateurs</span>
              <span class="dropdown-arrow">▼</span>
            </div>
            <div id="operators-dropdown" class="multi-select-dropdown" style="display: none;">
              <div class="select-all-option">
                <label class="checkbox-label">
                  <input type="checkbox" id="select-all-operators" onchange="handleSelectAllOperators()" checked>
                  <span class="checkmark"></span>
                  <span>📱 Tous les opérateurs</span>
                </label>
              </div>
              <div class="operators-list" id="operators-list">
            <!-- Les opérateurs seront chargés dynamiquement -->
              </div>
            </div>
          </div>
          <div id="operator-info" class="control-info">
            Chargement des opérateurs...
          </div>
        </div>

                        <div class="action-buttons">
                  <button class="btn-primary enhanced-btn" onclick="loadDashboardData()" id="refresh-btn">
                    <span id="refresh-text">📊 Actualiser</span>
                    <span id="refresh-loading" style="display: none;">⏳ Chargement...</span>
                  </button>
                  
                  <button class="btn-secondary enhanced-btn" onclick="setSmartComparison()">
                    🔄 Comparaison Auto
                  </button>
                  
                  <button class="btn-accent enhanced-btn" onclick="toggleDatePickerMode()">
                    📆 Raccourcis
                  </button>
                  
                  <button class="btn-info enhanced-btn" onclick="showKeyboardShortcutsHelp()">
                    ⌨️ Aide
                  </button>
                  
                  <!-- Performance indicator -->
                  <div class="performance-indicator" id="performance-indicator" style="display: none;">
                    <span class="performance-icon">⚡</span>
                    <span class="performance-text">Cache</span>
                  </div>
                </div>
      </div>
    </div>

    <!-- Tab 1: Overview -->
    <div id="overview" class="tab-content active">
      <!-- KPIs Row 1 (4 KPI) -->
      <div class="grid">
        <div class="card kpi-card">
          <div class="kpi-title">Activated Subscriptions <span style="margin-left:4px; cursor: help; color: var(--muted);" title="PÉRIODE: Nb d'abonnements activés entre start et end (client_abonnement_creation ∈ [start,end)).">ⓘ</span></div>
          <div class="kpi-value" id="activatedSubscriptions">Loading...</div>
          <div class="kpi-delta" id="activatedSubscriptionsDelta">Loading...</div>
        </div>
        <div class="card kpi-card">
          <div class="kpi-title">Active Subscriptions <span style="margin-left:4px; cursor: help; color: var(--muted);" title="PÉRIODE: Activés dans la période et encore actifs à la fin (expiration NULL ou > end).">ⓘ</span></div>
          <div class="kpi-value" id="activeSubscriptions">Loading...</div>
          <div class="kpi-delta" id="activeSubscriptionsDelta">Loading...</div>
        </div>
        <div class="card kpi-card">
          <div class="kpi-title">Retention Rate <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Pourcentage d’abonnés qui restent actifs à la fin de la période.">ⓘ</span></div>
          <div class="kpi-value" id="overview-retentionRate">Loading...</div>
          <div class="kpi-delta" id="overview-retentionRateDelta">Loading...</div>
        </div>
        <div class="card kpi-card">
          <div class="kpi-title">Conversion Rate <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Part des abonnés qui ont effectué au moins un achat pendant la période.">ⓘ</span></div>
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
          <div class="chart-title">Performance Overview - Period Comparison</div>
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
          <div class="kpi-title">Activated Subscriptions <span style="margin-left:4px; cursor: help; color: var(--muted);" title="PÉRIODE: Nb d'abonnements activés (client_abonnement_creation ∈ [start,end)).">ⓘ</span></div>
          <div class="kpi-value" id="sub-activatedSubscriptions">Loading...</div>
          <div class="kpi-delta" id="sub-activatedSubscriptionsDelta">Loading...</div>
        </div>
        <div class="card kpi-card">
          <div class="kpi-title">Active Subscriptions <span style="margin-left:4px; cursor: help; color: var(--muted);" title="PÉRIODE: Activés dans la période et encore actifs à la fin (expiration NULL ou > end).">ⓘ</span></div>
          <div class="kpi-value" id="sub-activeSubscriptions">Loading...</div>
          <div class="kpi-delta" id="sub-activeSubscriptionsDelta">Loading...</div>
        </div>
        <div class="card kpi-card">
          <div class="kpi-title">Retention Rate <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Sur 100 nouveaux abonnés, combien restent actifs à la fin de la période.">ⓘ</span></div>
          <div class="kpi-value" id="sub-retentionRate">Loading...</div>
          <div class="kpi-delta" id="sub-retentionRateDelta">Loading...</div>
        </div>
        <div class="card kpi-card">
          <div class="kpi-title">Conversion Rate (Période) <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Parmi les abonnés actifs, part de ceux qui ont payé au moins une fois.">ⓘ</span></div>
          <div class="kpi-value" id="sub-conversionRate">Loading...</div>
          <div class="kpi-delta" id="sub-conversionRateDelta">Loading...</div>
        </div>
      </div>

      <!-- Subscriptions KPIs: Row 2 (2 KPI) -->
      <div class="sub-kpis-row">
        <div class="card kpi-card">
          <div class="kpi-title">Deactivated (Période) <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Période: Tous les abonnements expirés dans la période sélectionnée.">ⓘ</span></div>
          <div class="kpi-value" id="sub-deactivatedSubscriptions">Loading...</div>
          <div class="kpi-delta" id="sub-deactivatedSubscriptionsDelta">Loading...</div>
        </div>
        <div class="card kpi-card">
          <div class="kpi-title">Deactivated (Cohorte) <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Cohorte: Abonnements créés dans [start,end) puis expirés dans la période.">ⓘ</span></div>
          <div class="kpi-value" id="sub-lostSubscriptions">Loading...</div>
          <div class="kpi-delta" id="sub-lostSubscriptionsDelta">Loading...</div>
        </div>
        <div class="card kpi-card">
          <div class="kpi-title">Taux de churn <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Part des nouveaux abonnés qui ont résilié (ont pris fin) pendant la période.">ⓘ</span></div>
          <div class="kpi-value" id="sub-retentionRateTrue">Loading...</div>
          <div class="kpi-delta" id="sub-retentionRateTrueDelta">Loading...</div>
        </div>
        <div class="card kpi-card">
          <div class="kpi-title">Transactions (Période) <span style="margin-left:4px; cursor: help; color: var(--muted);" title="PÉRIODE: Nb de transactions (history.time ∈ [start,end)).">ⓘ</span></div>
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
          <div class="kpi-title">Total Transactions <span style="margin-left:4px; cursor: help; color: var(--muted);" title="PÉRIODE: Nb de transactions (history.time ∈ [start,end)).">ⓘ</span></div>
          <div class="kpi-value" id="trans-totalTransactions">Loading...</div>
          <div class="kpi-delta" id="trans-totalTransactionsDelta">Loading...</div>
        </div>
        <div class="card kpi-card">
          <div class="kpi-title">Total Transactions (Cohorte) <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Achats réalisés par les abonnés inscrits pendant la période.">ⓘ</span></div>
          <div class="kpi-value" id="trans-cohortTransactions">Loading...</div>
          <div class="kpi-delta" id="trans-cohortTransactionsDelta">Loading...</div>
        </div>
        <div class="card kpi-card">
          <div class="kpi-title">Transacting Users (Période) <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Nombre de personnes qui ont payé au moins une fois pendant la période.">ⓘ</span></div>
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
          <div class="kpi-title">Conversion Rate (Cohorte) <span style="margin-left:4px; cursor: help; color: var(--muted);" title="(Transacting Users (Cohorte)) / (Active Subscriptions (Période)).">ⓘ</span></div>
          <div class="kpi-value" id="trans-convCohort">Loading...</div>
          <div class="kpi-delta" id="trans-convCohortDelta">Loading...</div>
        </div>
        <div class="card kpi-card">
          <div class="kpi-title">Conversion Rate (Période) <span style="margin-left:4px; cursor: help; color: var(--muted);" title="(Transacting Users (Période)) / (Active Subscriptions (Période)).">ⓘ</span></div>
          <div class="kpi-value" id="trans-convPeriod">Loading...</div>
          <div class="kpi-delta" id="trans-convPeriodDelta">Loading...</div>
        </div>
        <div class="card kpi-card">
          <div class="kpi-title">Transactions/User <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Transactions (période) / Utilisateurs transigeants (période).">ⓘ</span></div>
          <div class="kpi-value" id="trans-transactionsPerUser">Loading...</div>
          <div class="kpi-delta" id="trans-transactionsPerUserDelta">Loading...</div>
        </div>
        <div class="card kpi-card">
          <div class="kpi-title">Avg. Durée entre 2 transactions <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Moyenne des intervalles entre transactions par utilisateur (jours).">ⓘ</span></div>
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
            <div class="kpi-title">Total Transactions <span style="margin-left:4px; cursor: help; color: var(--muted);" title="PÉRIODE: Nb de transactions (history.time ∈ [start,end)).">ⓘ</span></div>
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
          <div class="kpi-title">Revenus TTC <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Revenus totaux TTC générés via Eklektik">ⓘ</span></div>
          <div class="kpi-value" id="eklektik-revenue-ttc">Loading...</div>
          <div class="kpi-delta" id="eklektik-revenue-ttc-delta">Loading...</div>
        </div>
        <div class="card kpi-card" style="grid-column: span 3;">
          <div class="kpi-title">Revenus HT <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Revenus hors taxes calculés selon les formules par opérateur">ⓘ</span></div>
          <div class="kpi-value" id="eklektik-revenue-ht">Loading...</div>
          <div class="kpi-delta" id="eklektik-revenue-ht-delta">Loading...</div>
        </div>
        <div class="card kpi-card" style="grid-column: span 3;">
          <div class="kpi-title">CA BigDeal <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Chiffre d'affaires BigDeal (part des revenus)">ⓘ</span></div>
          <div class="kpi-value" id="eklektik-ca-bigdeal">Loading...</div>
          <div class="kpi-delta" id="eklektik-ca-bigdeal-delta">Loading...</div>
        </div>
        <div class="card kpi-card" style="grid-column: span 3;">
          <div class="kpi-title">Active Subs <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Nombre d'abonnés actifs">ⓘ</span></div>
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

      <!-- Statistiques Timwe KPIs - 3 lignes de KPIs -->
      <div class="grid">
        <!-- Première ligne - 4 KPIs principaux -->
        <div class="card kpi-card" style="grid-column: span 3;">
          <div class="kpi-title">Taux de Facturation <span style="margin-left:4px; cursor: help; color: var(--muted);" title="(Clients facturés) / (Total clients Timwe) * 100. Seules les transactions avec pricepointId=63980 ET mnoDeliveryCode=DELIVERED sont comptées.">ⓘ</span></div>
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
          <div class="kpi-title">Active Subscriptions <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Nombre d'abonnements actifs à la fin de la période">ⓘ</span></div>
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
          <div class="kpi-title">Active Subscriptions <span style="margin-left:4px; cursor: help; color: var(--muted);" title="Nombre d'abonnements actifs à la fin de la période">ⓘ</span></div>
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

    <!-- Tab: Agent IA (Style ChatGPT avec Sidebar) -->
    @if(Auth::user()->isSuperAdmin())
    <div id="ai-agent" class="tab-content">
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

  <script>
    // Définition immédiate des couleurs thème - CRITIQUE pour éviter les erreurs
    window.THEME_COLORS = {
      @if($isOoredoo)
      primary: '#E30613',
      primaryRgba: 'rgba(227, 6, 19, 0.1)',
      secondary: '#DC2626',
      accent: '#3b82f6',
      success: '#10b981',
      warning: '#f59e0b',
      @else
      primary: '#6B46C1',
      primaryRgba: 'rgba(107, 70, 193, 0.1)',
      secondary: '#8B5CF6',
      accent: '#F59E0B',
      success: '#10b981',
      warning: '#3b82f6',
      @endif
      muted: '#64748b',
      mutedRgba: 'rgba(100, 116, 139, 0.2)'
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
        primary: '#E30613',
        primaryRgba: 'rgba(227, 6, 19, 0.1)',
        secondary: '#DC2626',
        accent: '#3b82f6',
        success: '#10b981',
        warning: '#f59e0b',
        muted: '#64748b',
        mutedRgba: 'rgba(100, 116, 139, 0.2)'
      };
      
      return fallbackColors[colorName] || '#E30613';
    }

    // Alias sécurisé pour THEME_COLORS
    const safeThemeColors = new Proxy({}, {
      get: function(target, prop) {
        return getThemeColor(prop);
      }
    });

    // Fonction pour afficher les états de chargement des KPIs
    function showEklektikStatsLoading() {
      const elements = [
        'kpi-revenue-ttc',
        'kpi-revenue-ht',
        'kpi-ca-bigdeal',
        'kpi-bigdeal-percentage'
      ];

      elements.forEach(id => {
        const element = document.getElementById(id);
        if (element) {
          element.innerHTML = '<div class="loading-spinner">🔄</div>';
        }
      });
    }

    // Fonction pour afficher les erreurs des KPIs
    function showEklektikStatsError() {
      const elements = [
        'kpi-revenue-ttc',
        'kpi-revenue-ht',
        'kpi-ca-bigdeal',
        'kpi-bigdeal-percentage'
      ];

      elements.forEach(id => {
        const element = document.getElementById(id);
        if (element) {
          element.innerHTML = '<span class="error-text">❌ Erreur</span>';
        }
      });
    }

    // Charger les données Eklektik (sera définie plus tard)
    async function loadEklektikData() {
      console.log('🔄 Chargement des données Eklektik...');

      // Afficher l'état de chargement
      showEklektikStatsLoading();

      try {
        // Charger les KPIs
        const kpisResponse = await fetch('/api/eklektik-dashboard/kpis');
        const kpisData = await kpisResponse.json();

        if (kpisData.success) {
          updateEklektikStatsDisplay(kpisData.data);
        } else {
          console.error('❌ Erreur KPIs Eklektik:', kpisData.message);
          showEklektikStatsError();
        }

        // Charger les statistiques par opérateur
        const operatorsResponse = await fetch('/api/eklektik-dashboard/revenue-distribution');
        const operatorsData = await operatorsResponse.json();

        if (operatorsData.success) {
          updateEklektikOperatorsStats(operatorsData.data.distribution);
        } else {
          console.error('❌ Erreur opérateurs Eklektik:', operatorsData.message);
        }

      } catch (error) {
        console.error('❌ Erreur lors du chargement des données Eklektik:', error);
        showEklektikStatsError();
      }
    }

    // Mettre à jour l'affichage des statistiques Eklektik
    function updateEklektikStatsDisplay(data) {
      console.log('📊 Mise à jour des KPIs Eklektik:', data);

      // Mettre à jour les éléments KPI avec les données
      if (data && data.kpis) {
        // Revenue TTC
        const revenueTtcElement = document.getElementById('kpi-revenue-ttc');
        if (revenueTtcElement && data.kpis.total_revenue_ttc !== undefined) {
          revenueTtcElement.innerHTML = formatNumber(data.kpis.total_revenue_ttc) + ' €';
        }

        // Revenue HT
        const revenueHtElement = document.getElementById('kpi-revenue-ht');
        if (revenueHtElement && data.kpis.total_revenue_ht !== undefined) {
          revenueHtElement.innerHTML = formatNumber(data.kpis.total_revenue_ht) + ' €';
        }

        // CA BigDeal
        const caBigdealElement = document.getElementById('kpi-ca-bigdeal');
        if (caBigdealElement && data.kpis.total_facturation !== undefined) {
          caBigdealElement.innerHTML = formatNumber(data.kpis.total_facturation) + ' €';
        }

        // Pourcentage BigDeal
        const bigdealPercentageElement = document.getElementById('kpi-bigdeal-percentage');
        if (bigdealPercentageElement && data.kpis.bigdeal_percentage !== undefined) {
          bigdealPercentageElement.innerHTML = data.kpis.bigdeal_percentage.toFixed(1) + '%';
        }
      }
    }

    // Mobile-optimized chart options with enhanced 5-breakpoint system
    function getMobileOptimizedChartOptions(customOptions = {}) {
      const screenWidth = window.innerWidth;
      const isLargeDesktop = screenWidth >= 1400;
      const isDesktop = screenWidth >= 1200 && screenWidth < 1400;
      const isTabletLarge = screenWidth >= 900 && screenWidth < 1200;
      const isTablet = screenWidth >= 768 && screenWidth < 900;
      const isMobileLarge = screenWidth >= 600 && screenWidth < 768;
      const isMobileSmall = screenWidth >= 480 && screenWidth < 600;
      const isMobileTiny = screenWidth < 480;
      
      // Determine font sizes based on breakpoint
      let legendFontSize, tooltipTitleSize, tooltipBodySize, tickFontSize, padding;
      
      if (isLargeDesktop) {
        legendFontSize = 13; tooltipTitleSize = 15; tooltipBodySize = 14; tickFontSize = 12; padding = 24;
      } else if (isDesktop) {
        legendFontSize = 12; tooltipTitleSize = 14; tooltipBodySize = 13; tickFontSize = 11; padding = 20;
      } else if (isTabletLarge) {
        legendFontSize = 11; tooltipTitleSize = 13; tooltipBodySize = 12; tickFontSize = 10; padding = 16;
      } else if (isTablet) {
        legendFontSize = 10; tooltipTitleSize = 12; tooltipBodySize = 11; tickFontSize = 9; padding = 12;
      } else if (isMobileLarge) {
        legendFontSize = 9; tooltipTitleSize = 11; tooltipBodySize = 10; tickFontSize = 8; padding = 10;
      } else if (isMobileSmall) {
        legendFontSize = 8; tooltipTitleSize = 10; tooltipBodySize = 9; tickFontSize = 7; padding = 8;
      } else { // isMobileTiny
        legendFontSize = 7; tooltipTitleSize = 9; tooltipBodySize = 8; tickFontSize = 6; padding = 6;
      }
      
      const isMobile = screenWidth < 768;
      const isSmallMobile = screenWidth < 480;
      
      const baseOptions = {
        responsive: true,
        maintainAspectRatio: false,
        layout: {
          padding: padding
        },
        plugins: {
          legend: {
            display: true,
            position: isMobile ? 'bottom' : 'top',
            labels: {
              boxWidth: isMobile ? (isSmallMobile ? 8 : 10) : 15,
              padding: isMobile ? (isSmallMobile ? 6 : 8) : 15,
              font: {
                size: legendFontSize
              },
              usePointStyle: isMobile // Utilise des points au lieu de carrés sur mobile
            }
          },
          tooltip: {
            enabled: true,
            mode: isMobile ? 'nearest' : 'index',
            intersect: false,
            titleFont: {
              size: tooltipTitleSize
            },
            bodyFont: {
              size: tooltipBodySize
            },
            padding: isMobile ? (isSmallMobile ? 6 : 8) : 12,
            caretSize: isMobile ? 4 : 6
          }
        },
        scales: {
          x: {
            ticks: {
              font: {
                size: tickFontSize
              },
              maxRotation: isMobile ? (isSmallMobile ? 60 : 45) : 0,
              minRotation: isMobile ? (isSmallMobile ? 60 : 45) : 0,
              maxTicksLimit: isMobile ? (isSmallMobile ? 5 : 8) : undefined
            },
            grid: {
              display: !isSmallMobile,
              lineWidth: isMobile ? 0.5 : 1
            }
          },
          y: {
            ticks: {
              font: {
                size: tickFontSize
              },
              maxTicksLimit: isMobile ? (isSmallMobile ? 4 : 6) : undefined
            },
            grid: {
              display: true,
              lineWidth: isMobile ? 0.5 : 1
            }
          }
        },
        interaction: {
          mode: 'nearest',
          axis: 'x',
          intersect: false
        },
        elements: {
          point: {
            radius: isMobile ? (isSmallMobile ? 2 : 3) : 4,
            hoverRadius: isMobile ? (isSmallMobile ? 4 : 5) : 6
          },
          line: {
            borderWidth: isMobile ? (isSmallMobile ? 1.5 : 2) : 3,
            tension: 0.1 // Lignes légèrement plus lisses sur mobile
          }
        }
      };
      
      // Simple merge avec priorité aux options personnalisées
      return Object.assign({}, baseOptions, customOptions);
    }

    // Window resize handler for mobile optimization
    let resizeTimeout;
    window.addEventListener('resize', function() {
      clearTimeout(resizeTimeout);
      resizeTimeout = setTimeout(function() {
        // Re-render charts with new mobile settings
        if (typeof charts !== 'undefined') {
          Object.keys(charts).forEach(key => {
            if (charts[key] && charts[key].resize) {
              charts[key].resize();
            }
          });
        }
        
        // Eklektik charts removed (they were buggy)
      }, 250);
    });

    // Initialize dashboard (charge tout en une seule fois)
    document.addEventListener('DOMContentLoaded', async function() {
      // Dropdown Profil
      const toggle = document.getElementById('profileMenuToggle');
      const dropdown = document.getElementById('profileDropdown');
      if (toggle && dropdown) {
        toggle.addEventListener('click', function(e) {
          e.stopPropagation();
          dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
        });
        document.addEventListener('click', function() {
          dropdown.style.display = 'none';
        });
      }
      // Configuration globale Chart.js pour désactiver les animations
      if (typeof Chart !== 'undefined') {
        Chart.defaults.animation = false;
        Chart.defaults.animations = {
          duration: 0
        };
        Chart.defaults.transitions = {
          active: {
            animation: {
              duration: 0
            }
          },
          resize: {
            animation: {
              duration: 0
            }
          }
        };
        
        // Désactiver complètement toutes les animations
        Chart.defaults.plugins = Chart.defaults.plugins || {};
        Chart.defaults.plugins.legend = Chart.defaults.plugins.legend || {};
        Chart.defaults.plugins.legend.animation = false;
        
        // Désactiver les animations de survol
        Chart.defaults.elements = Chart.defaults.elements || {};
        Chart.defaults.elements.point = Chart.defaults.elements.point || {};
        Chart.defaults.elements.point.hoverRadius = 0;
        Chart.defaults.elements.line = Chart.defaults.elements.line || {};
        Chart.defaults.elements.line.tension = 0;
        Chart.defaults.responsive = true;
        Chart.defaults.maintainAspectRatio = false;
        Chart.defaults.interaction = {
          intersect: false,
          mode: 'index'
        };
        
        // Configuration Chart.js pour un comportement normal (pas d'interception globale)
        if (typeof Chart !== 'undefined') {
          // Configuration légère pour améliorer les performances
          Chart.defaults.maintainAspectRatio = false;
          Chart.defaults.responsive = true;
        }
        
        
        
        
        
        console.log('✅ Chart.js configuré avec succès');
      } else {
        console.error('❌ Chart.js non chargé');
      }

      // Charger les données Eklektik une seule fois au démarrage
      try {
        if (typeof loadEklektikData === 'function') {
          await loadEklektikData();
        }
        if (typeof loadEklektikCharts === 'function') {
          setTimeout(() => loadEklektikCharts(), 150);
        }
      } catch (e) {
        console.warn('Eklektik initial load skipped:', e);
      }
      
      setDefaultDates();
      updateDateRange();
      initializeDashboard();
      
      // Initialize mobile navigation
      initializeMobileNavigation();
      
      // Auto-refresh every 5 minutes
      setInterval(loadDashboardData, 5 * 60 * 1000);
      
      // Initialize keyboard shortcuts
      initializeKeyboardShortcuts();
    });
    
    // Initialize mobile-specific navigation features
    function initializeMobileNavigation() {
      // Center active tab on page load (mobile)
      const activeTab = document.querySelector('.nav-tab.active');
      if (activeTab && window.innerWidth <= 768) {
        setTimeout(() => centerActiveTab(activeTab), 200);
      }
      
      // Add touch/swipe support for tab navigation (optional)
      if (window.innerWidth <= 768) {
        addMobileSwipeSupport();
      }
    }
    
    // Add swipe support for mobile tab navigation
    function addMobileSwipeSupport() {
      const tabsContainer = document.querySelector('.nav-tabs');
      let startX = 0;
      let scrollLeft = 0;
      
      tabsContainer.addEventListener('touchstart', (e) => {
        startX = e.touches[0].pageX - tabsContainer.offsetLeft;
        scrollLeft = tabsContainer.scrollLeft;
      }, { passive: true });
      
      tabsContainer.addEventListener('touchmove', (e) => {
        const x = e.touches[0].pageX - tabsContainer.offsetLeft;
        const walk = (x - startX) * 2; // Adjust scroll speed
        tabsContainer.scrollLeft = scrollLeft - walk;
      }, { passive: true });
    }
    
    // Advanced keyboard shortcuts for power users
    function initializeKeyboardShortcuts() {
      document.addEventListener('keydown', function(e) {
        // Only trigger shortcuts when no input is focused
        if (document.activeElement.tagName === 'INPUT' || 
            document.activeElement.tagName === 'SELECT' || 
            document.activeElement.tagName === 'TEXTAREA') {
          return;
        }
        
        // Ctrl/Cmd + R - Refresh dashboard
        if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
          e.preventDefault();
          loadDashboardData();
          showNotification('🔄 Dashboard actualisé via raccourci clavier', 'info', 2000);
        }
        
        // Tab navigation: 1-5 for tabs
        if (['1', '2', '3', '4', '5'].includes(e.key)) {
          e.preventDefault();
          const tabs = ['overview', 'subscriptions', 'transactions', 'merchants', 'eklektik'];
          const tabName = tabs[parseInt(e.key) - 1];
          if (tabName) {
            showTab(tabName);
            // Update visual feedback
            document.querySelectorAll('.nav-tab').forEach(tab => tab.classList.remove('active'));
            document.querySelector(`[onclick="showTab('${tabName}')"]`).classList.add('active');
            showNotification(`📊 Onglet ${tabName} activé`, 'info', 1500);
          }
        }
        
        // E for Export (if on merchants tab)
        if (e.key === 'e' || e.key === 'E') {
          const activeTab = document.querySelector('.tab-content.active');
          if (activeTab && activeTab.id === 'merchants') {
            e.preventDefault();
            exportMerchantsData();
            showNotification('📥 Export des données marchands lancé', 'success', 2000);
          }
        }
        
        // D for Date shortcuts modal
        if (e.key === 'd' || e.key === 'D') {
          e.preventDefault();
          toggleDatePickerMode();
          showNotification('📅 Raccourcis de dates', 'info', 1500);
        }
        
        // H for Help (show shortcuts)
        if (e.key === 'h' || e.key === 'H' || e.key === '?') {
          e.preventDefault();
          showKeyboardShortcutsHelp();
        }
        
        // Escape to close modals/notifications
        if (e.key === 'Escape') {
          // Close date shortcuts modal if open
          const modal = document.getElementById('date-shortcuts-modal');
          if (modal && modal.style.display !== 'none') {
            modal.style.display = 'none';
          }
          
          // Close help modal if open
          const helpModal = document.getElementById('shortcuts-help-modal');
          if (helpModal && helpModal.style.display !== 'none') {
            helpModal.style.display = 'none';
          }
          
          // Close all notifications
          document.querySelectorAll('.notification').forEach(n => n.remove());
        }
      });
    }
    
    function showKeyboardShortcutsHelp() {
      // Remove existing help modal
      const existing = document.getElementById('shortcuts-help-modal');
      if (existing) existing.remove();
      
      const modal = document.createElement('div');
      modal.id = 'shortcuts-help-modal';
      modal.innerHTML = `
        <div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 10001; display: flex; align-items: center; justify-content: center;">
          <div style="background: white; border-radius: 12px; padding: 30px; max-width: 500px; max-height: 80vh; overflow-y: auto;">
            <div style="display: flex; justify-content: between; align-items: center; margin-bottom: 20px;">
              <h3 style="margin: 0; color: var(--brand-red); font-size: 20px;">⌨️ Raccourcis Clavier</h3>
              <button onclick="document.getElementById('shortcuts-help-modal').remove()" style="background: none; border: none; font-size: 20px; cursor: pointer; margin-left: auto;">×</button>
            </div>
            
            <div style="space-y: 12px;">
              <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f0f0f0;">
                <span><kbd style="background: #f3f4f6; padding: 4px 8px; border-radius: 4px; font-family: monospace;">Ctrl+R</kbd></span>
                <span>Actualiser le dashboard</span>
              </div>
              
              <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f0f0f0;">
                <span><kbd style="background: #f3f4f6; padding: 4px 8px; border-radius: 4px; font-family: monospace;">1-4</kbd></span>
                <span>Naviguer entre les onglets</span>
              </div>
              
              <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f0f0f0;">
                <span><kbd style="background: #f3f4f6; padding: 4px 8px; border-radius: 4px; font-family: monospace;">E</kbd></span>
                <span>Exporter (onglet Marchands)</span>
              </div>
              
              <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f0f0f0;">
                <span><kbd style="background: #f3f4f6; padding: 4px 8px; border-radius: 4px; font-family: monospace;">D</kbd></span>
                <span>Raccourcis de dates</span>
              </div>
              
              <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f0f0f0;">
                <span><kbd style="background: #f3f4f6; padding: 4px 8px; border-radius: 4px; font-family: monospace;">H / ?</kbd></span>
                <span>Afficher cette aide</span>
              </div>
              
              <div style="display: flex; justify-content: space-between; padding: 8px 0;">
                <span><kbd style="background: #f3f4f6; padding: 4px 8px; border-radius: 4px; font-family: monospace;">Esc</kbd></span>
                <span>Fermer modales/notifications</span>
              </div>
            </div>
            
            <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px; font-size: 14px; color: #6c757d;">
              💡 <strong>Astuce :</strong> Ces raccourcis fonctionnent uniquement quand aucun champ de saisie n'est actif.
            </div>
          </div>
        </div>
      `;
      
      document.body.appendChild(modal);
      
      // Close on background click
      modal.addEventListener('click', function(e) {
        if (e.target === modal) {
          modal.remove();
        }
      });
    }

    // Initialize dashboard in correct order - optimized for speed
    async function initializeDashboard() {
      try {
        // Show immediate loading state for KPIs (skeleton)
        showKPISkeleton();
        
        // Start loading dashboard data immediately (most important)
        loadDashboardData();
        
        // Load operators in parallel (non-blocking)
        loadOperators().catch(error => {
          console.warn('Operators loading failed:', error);
          // Ne pas utiliser setupFallbackOperators - laisser loadOperators gérer les retries
        });
        
      } catch (error) {
        console.error('Erreur lors de l\'initialisation:', error);
        hideKPISkeleton();
        showNotification('Erreur lors de l\'initialisation du dashboard', 'error');
      }
    }
    
    // Cette fonction n'est plus utilisée - les opérateurs doivent toujours venir de l'API
    // Conservée uniquement pour référence mais ne devrait jamais être appelée
    function setupFallbackOperators() {
      console.warn('⚠️ setupFallbackOperators appelée - cela ne devrait pas arriver');
      const operatorInfo = document.getElementById('operator-info');
      if (operatorInfo) {
        operatorInfo.textContent = 'Erreur: Impossible de charger les opérateurs depuis l\'API. Veuillez rafraîchir la page.';
        operatorInfo.style.color = '#ef4444';
      }
    }
    
    // Show skeleton loading for KPIs immediately
    function showKPISkeleton() {
      const kpiValues = document.querySelectorAll('.kpi-value');
      kpiValues.forEach(el => {
        el.innerHTML = '<div class="skeleton-text"></div>';
      });
      
      const kpiDeltas = document.querySelectorAll('.kpi-delta');
      kpiDeltas.forEach(el => {
        // Ne pas ajouter de skeleton pour les KPIs Timwe (qui seront masqués par updateKPI)
        const isTimweKPI = el.id && el.id.startsWith('timwe-');
        if (!isTimweKPI) {
          el.innerHTML = '<div class="skeleton-text-small"></div>';
        }
      });
      
      // Reset progress bars to 0
      const progressBars = document.querySelectorAll('.progress-fill');
      progressBars.forEach(bar => {
        bar.style.width = '0%';
      });
    }
    
    // Hide skeleton loading
    function hideKPISkeleton() {
      // This will be replaced by real values when updateKPIs is called
    }
    
    // Progress bar issue resolved: height was 0px
    
    // Update Overview conversion progress bar safely
    function updateOverviewConversionProgressBar(conversionRateData) {
      const conversionProgress = document.getElementById('overview-conversionProgress');
      
      if (conversionProgress && conversionRateData && typeof conversionRateData.current !== 'undefined') {
        const percentage = Math.min(100, Math.max(0, (conversionRateData.current / 30) * 100));
        
        conversionProgress.style.width = `${percentage}%`;
        conversionProgress.style.transition = 'width 0.5s ease-in-out';
        conversionProgress.style.backgroundColor = getThemeColor('primary');
        conversionProgress.style.height = '8px'; // Fixed: same as transactions
        conversionProgress.style.display = 'block';
        
      } else if (conversionProgress) {
        // Fallback: set to 0% if no data
        conversionProgress.style.width = '0%';
        conversionProgress.style.height = '8px';
      }
    }
    
    // Update conversion progress bar safely
    function updateConversionProgressBar(conversionRateData) {
      const conversionProgress = document.getElementById('trans-conversionProgress');
      
      if (conversionProgress && conversionRateData && typeof conversionRateData.current !== 'undefined') {
        const percentage = Math.min(100, Math.max(0, (conversionRateData.current / 30) * 100));
        
        conversionProgress.style.width = `${percentage}%`;
        conversionProgress.style.transition = 'width 0.5s ease-in-out';
        conversionProgress.style.backgroundColor = getThemeColor('primary');
        conversionProgress.style.height = '8px'; // Fixed: was 0px height
        conversionProgress.style.display = 'block';
        
      } else if (conversionProgress) {
        // Fallback: set to 0% if no data
        conversionProgress.style.width = '0%';
        conversionProgress.style.height = '8px';
      }
    }

    // Tab switching functionality - Supprimé (défini plus haut)
    

    
    
    
    function updateEklektikTable(numbers) {
      const tbody = document.getElementById('eklektik-numbers-tbody');
      
      if (!numbers || numbers.length === 0) {
        tbody.innerHTML = `
          <tr>
            <td colspan="9" style="text-align: center; padding: 40px; color: var(--muted);">
              📱 Aucun numéro Eklektik trouvé
            </td>
          </tr>
        `;
        return;
      }
      
      tbody.innerHTML = numbers.map(number => `
        <tr>
          <td><strong>${number.phone_number}</strong></td>
          <td>
            <span class="service-badge service-${(number.service_type || 'unknown').toLowerCase()}">
              ${getServiceIcon(number.service_type)} ${number.service_type || 'Unknown'}
            </span>
          </td>
          <td>
            <span class="status-badge status-${(number.status || 'unknown').toLowerCase()}">
              ${getStatusIcon(number.status)} ${number.status || 'Unknown'}
            </span>
          </td>
          <td>
            <span class="operator-badge operator-${(number.operator || 'unknown').toLowerCase()}">
              ${number.operator === 'TT' ? '🔵' : number.operator === 'Orange' ? '🟠' : '❓'} ${number.operator || 'Unknown'}
            </span>
          </td>
          <td style="font-size: 11px; max-width: 120px; overflow: hidden; text-overflow: ellipsis;">
            ${number.payment_method || 'Unknown'}
          </td>
          <td>
            ${number.eklektik_summary ? `
              <div style="font-size: 10px;">
                <div>✅ ${number.eklektik_summary.active_offers ? number.eklektik_summary.active_offers.length : 0} actifs</div>
                <div>📋 ${number.eklektik_summary.available_offers_count || 0} disponibles</div>
                <div>❌ ${number.eklektik_summary.error_offers_count || 0} erreurs</div>
              </div>
            ` : '<span style="color: var(--muted); font-size: 11px;">Non testé</span>'}
          </td>
          <td>
            <strong style="color: var(--primary);">${number.price || 0} TND</strong>
            ${number.duration ? `<br><small>${number.duration} jours</small>` : ''}
          </td>
          <td>
            <span class="source-badge source-${(number.source || 'unknown').toLowerCase()}" style="font-size: 10px;">
              ${number.source === 'EKLEKTIK_API_TESTED' ? '🟢 API Testé' : 
                number.source === 'LOCAL_DATABASE_EKLEKTIK_ONLY' ? '🔵 Local' : 
                number.source === 'LOCAL_DATABASE_READY_FOR_API_TEST' ? '🟡 Prêt pour Test' :
                number.source === 'FALLBACK_LOCAL_DATA' ? '🟡 Fallback' : '❓ Unknown'}
            </span>
          </td>
          <td>
            <div class="action-buttons">
              <button class="btn-sm btn-primary" onclick="viewEklektikDetails('${number.phone_number}')" title="Voir détails">
                👁️
              </button>
              <button class="btn-sm btn-secondary" onclick="testEklektikNumber('${number.phone_number}')" title="Tester">
                🧪
              </button>
            </div>
          </td>
        </tr>
      `).join('');
    }
    
    function updateEklektikApiStatus(apiStatus) {
      // Connection status
      const connectionEl = document.getElementById('eklektik-api-status');
      const isConnected = apiStatus.connected !== false;
      connectionEl.innerHTML = `
        <span class="status-indicator ${isConnected ? 'success' : 'danger'}">
          ${isConnected ? '✅' : '❌'}
        </span>
        ${isConnected ? 'Connecté' : 'Déconnecté'}
      `;
      
      // Response time
      const responseTimeEl = document.getElementById('eklektik-response-time');
      const responseTime = apiStatus.responseTime || 0;
      const timeStatus = responseTime < 1000 ? 'success' : responseTime < 3000 ? 'warning' : 'danger';
      responseTimeEl.innerHTML = `
        <span class="status-indicator ${timeStatus}">⚡</span>
        ${responseTime}ms
      `;
      
      // Last sync
      const lastSyncEl = document.getElementById('eklektik-last-sync');
      lastSyncEl.innerHTML = `
        <span class="status-indicator">📊</span>
        ${formatDate(apiStatus.lastSync) || 'Jamais'}
      `;
      
      // Sync status
      const syncStatusEl = document.getElementById('eklektik-sync-status');
      const syncStatus = apiStatus.syncStatus || 'unknown';
      const syncIcon = syncStatus === 'success' ? '✅' : syncStatus === 'error' ? '❌' : '⏳';
      syncStatusEl.innerHTML = `
        <span class="status-indicator">${syncIcon}</span>
        ${syncStatus === 'success' ? 'OK' : syncStatus === 'error' ? 'Erreur' : 'En cours'}
      `;
    }
    
    function createEklektikCharts(chartsData) {
      console.log('🔍 [EKLEKTIK DEBUG] Création des graphiques avec données:', chartsData);
      
      // Destroy existing charts to prevent conflicts
      if (window.eklektikCharts) {
        Object.values(window.eklektikCharts).forEach(chart => {
          if (chart && typeof chart.destroy === 'function') {
            chart.destroy();
          }
        });
      }
      window.eklektikCharts = {};
      
      // Usage by service chart
      const usageCtx = document.getElementById('eklektik-usage-chart')?.getContext('2d');
      if (usageCtx && chartsData.serviceUsage) {
        console.log('📊 [EKLEKTIK] Création graphique usage service:', chartsData.serviceUsage);
        window.eklektikCharts.usage = new Chart(usageCtx, {
          type: 'doughnut',
          data: {
            labels: chartsData.serviceUsage.labels || [],
            datasets: [{
              data: chartsData.serviceUsage.data || [],
              backgroundColor: ['#ef4444', '#f97316', '#eab308', '#22c55e', '#3b82f6'],
              borderWidth: 2,
              borderColor: '#ffffff'
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: {
              duration: 1000
            },
            plugins: {
              legend: {
                position: 'bottom'
              }
            }
          }
        });
      }
      
      // Timeline chart
      const timelineCtx = document.getElementById('eklektik-timeline-chart')?.getContext('2d');
      if (timelineCtx && chartsData.timeline) {
        console.log('📈 [EKLEKTIK] Création graphique timeline:', chartsData.timeline);
        window.eklektikCharts.timeline = new Chart(timelineCtx, {
          type: 'line',
          data: {
            labels: chartsData.timeline.labels || [],
            datasets: [{
              label: 'Appels API',
              data: chartsData.timeline.data || [],
              borderColor: '#3b82f6',
              backgroundColor: 'rgba(59, 130, 246, 0.1)',
              tension: 0.4,
              fill: true
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: {
              duration: 1000
            },
            interaction: {
              intersect: false,
              mode: 'index'
            },
            scales: {
              y: {
                beginAtZero: true,
                grid: {
                  color: 'rgba(0,0,0,0.1)'
                }
              },
              x: {
                grid: {
                  color: 'rgba(0,0,0,0.1)'
                }
              }
            }
          }
        });
      }
    }
    
    // Variables globales pour la pagination
    let currentPage = 1;
    let pageSize = 20;
    let totalNumbers = 0;
    let allEklektikNumbers = [];
    let autoRefreshInterval = null;

    function setupPagination(numbers) {
      allEklektikNumbers = numbers || [];
      totalNumbers = allEklektikNumbers.length;
      currentPage = 1;
      
      updatePaginationDisplay();
      updateTableWithPagination();
    }

    function updatePaginationDisplay() {
      const start = ((currentPage - 1) * pageSize) + 1;
      const end = Math.min(currentPage * pageSize, totalNumbers);
      
      document.getElementById('eklektik-pagination-info').textContent = 
        `Affichage des numéros ${start}-${end} sur ${totalNumbers}`;
      
      // Update button states
      document.getElementById('prev-page-btn').disabled = currentPage <= 1;
      document.getElementById('next-page-btn').disabled = currentPage >= Math.ceil(totalNumbers / pageSize);
    }

    function changePage(direction) {
      const maxPages = Math.ceil(totalNumbers / pageSize);
      
      if (direction === 1 && currentPage < maxPages) {
        currentPage++;
      } else if (direction === -1 && currentPage > 1) {
        currentPage--;
      }
      
      updatePaginationDisplay();
      updateTableWithPagination();
    }

    function changePageSize() {
      pageSize = parseInt(document.getElementById('page-size-select').value);
      currentPage = 1; // Reset to first page
      updatePaginationDisplay();
      updateTableWithPagination();
    }

    function updateTableWithPagination() {
      const start = (currentPage - 1) * pageSize;
      const end = start + pageSize;
      const pageNumbers = allEklektikNumbers.slice(start, end);
      
      updateEklektikTable(pageNumbers);
    }

    function toggleAutoRefresh() {
      // Auto-refresh désactivé pour stabilité (demande utilisateur)
      if (autoRefreshInterval) {
        clearInterval(autoRefreshInterval);
        autoRefreshInterval = null;
      }
      const checkbox = document.getElementById('auto-refresh-checkbox');
      if (checkbox) checkbox.checked = false;
      console.log('❌ Auto-actualisation désactivée');
    }
    
    
    // Bulk test functionality
    async function startBulkTest() {
      try {
        const btn = document.getElementById('bulk-test-btn');
        const progressDiv = document.getElementById('bulk-test-progress');
        const progressText = document.getElementById('test-progress-text');
        const progressFill = document.getElementById('test-progress-fill');
        const summaryDiv = document.getElementById('test-results-summary');
        
        // Disable button and show progress
        btn.disabled = true;
        btn.textContent = '🧪 Test en cours...';
        progressDiv.style.display = 'block';
        summaryDiv.style.display = 'none';
        
        progressText.textContent = 'Authentification...';
        progressFill.style.width = '10%';
        
        console.log('🧪 [EKLEKTIK] Démarrage du test en masse...');
        
        // Start the bulk test
        const response = await fetch('/api/eklektik/test-all', {
          method: 'POST',
          headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            limit: 50, // Limit for demo
            operator: 'ALL'
          })
        });
        
        progressText.textContent = 'Test des numéros en cours...';
        progressFill.style.width = '50%';
        
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const result = await response.json();
        
        progressText.textContent = 'Traitement des résultats...';
        progressFill.style.width = '90%';
        
        console.log('✅ [EKLEKTIK] Test en masse terminé:', result);
        
        // Update results
        updateBulkTestResults(result);
        
        progressText.textContent = 'Terminé !';
        progressFill.style.width = '100%';
        
        // Hide progress after a moment
        setTimeout(() => {
          progressDiv.style.display = 'none';
          btn.disabled = false;
          btn.textContent = '🧪 Tester Tous les Numéros';
        }, 2000);
        
        showNotification('✅ Test en masse terminé avec succès !', 'success', 3000);
        
      } catch (error) {
        console.error('❌ [EKLEKTIK] Erreur lors du test en masse:', error);
        
        // Reset UI on error
        const btn = document.getElementById('bulk-test-btn');
        const progressDiv = document.getElementById('bulk-test-progress');
        
        progressDiv.style.display = 'none';
        btn.disabled = false;
        btn.textContent = '🧪 Tester Tous les Numéros';
        
        showNotification('❌ Erreur lors du test en masse', 'error', 3000);
      }
    }
    
    function updateBulkTestResults(result) {
      const summaryDiv = document.getElementById('test-results-summary');
      const tableBody = document.getElementById('eklektik-numbers-tbody');
      
      if (!result.success || !result.statistics) {
        showNotification('❌ Erreur dans les résultats du test', 'error', 3000);
        return;
      }
      
      const stats = result.statistics;
      
      // Update statistics
      document.getElementById('test-stat-total').textContent = stats.total || 0;
      document.getElementById('test-stat-active').textContent = stats.active || 0;
      document.getElementById('test-stat-inactive').textContent = (stats.available || 0) + (stats.timeout || 0);
      document.getElementById('test-stat-errors').textContent = stats.errors || 0;
      document.getElementById('test-stat-success-rate').textContent = `${stats.success_rate || 0}%`;
      document.getElementById('test-stat-avg-time').textContent = `${stats.avg_response_time || 0}ms`;
      
      // Afficher les timeouts séparément si présents
      if (stats.timeout > 0) {
        console.log(`⏱️ [EKLEKTIK] ${stats.timeout} timeout(s) détecté(s) - API Eklektik lente`);
      }
      
      // Show summary
      summaryDiv.style.display = 'block';
      
      // Update table with test results
      if (result.results && result.results.length > 0) {
        tableBody.innerHTML = result.results.map(testResult => `
          <tr>
            <td><strong>${testResult.msisdn}</strong></td>
            <td>
              <span class="service-badge service-subscription">
                📱 SUBSCRIPTION
              </span>
            </td>
            <td>
              <span class="status-badge status-${(testResult.final_status || 'unknown').toLowerCase()}">
                ${getStatusIcon(testResult.final_status)} ${testResult.final_status || 'Unknown'}
              </span>
            </td>
            <td>
              <span class="operator-badge operator-${(testResult.operator || 'unknown').toLowerCase()}">
                ${testResult.operator === 'TT' ? '🔵' : testResult.operator === 'Orange' ? '🟠' : '❓'} ${testResult.operator || 'Unknown'}
              </span>
            </td>
            <td style="font-size: 11px; max-width: 120px; overflow: hidden; text-overflow: ellipsis;">
              ${testResult.payment_method || 'Unknown'}
            </td>
            <td>
              <div style="font-size: 10px;">
                <div>🧪 ${testResult.tests ? testResult.tests.length : 0} tests</div>
                <div>✅ ${testResult.summary && testResult.summary.active_offers ? testResult.summary.active_offers.length : 0} actifs</div>
                <div>📋 ${testResult.summary && testResult.summary.available_offers_count ? testResult.summary.available_offers_count : 0} disponibles</div>
              </div>
            </td>
            <td>
              <strong style="color: var(--primary);">${testResult.subscription_name || 'N/A'}</strong>
              <br><small>${testResult.response_time_ms || 0}ms</small>
            </td>
            <td>
              <span class="source-badge" style="font-size: 10px;">
                🟢 API Réel Testé
              </span>
            </td>
            <td>
              <div class="action-buttons">
                <button class="btn-sm btn-primary" onclick="viewTestDetails('${testResult.msisdn}')" title="Voir détails">
                  👁️
                </button>
                <button class="btn-sm btn-secondary" onclick="testEklektikNumber('${testResult.msisdn}')" title="Tester">
                  🧪
                </button>
              </div>
            </td>
          </tr>
        `).join('');
      } else {
        tableBody.innerHTML = `
          <tr>
            <td colspan="9" style="text-align: center; padding: 40px; color: var(--muted);">
              📱 Aucun résultat de test disponible
            </td>
          </tr>
        `;
      }
    }
    
    function viewTestDetails(msisdn) {
      showNotification(`👁️ Détails pour le test du numéro ${msisdn}`, 'info', 2000);
      console.log(`[EKLEKTIK] Demande de détails pour ${msisdn}`);
      // TODO: Implement detailed view
    }
    
    function viewEklektikDetails(phoneNumber) {
      showNotification(`👁️ Détails pour ${phoneNumber}`, 'info', 2000);
      // TODO: Implement detail view
    }
    
    function testEklektikNumber(phoneNumber) {
      showNotification(`🧪 Test du numéro ${phoneNumber}...`, 'info', 2000);
      // TODO: Implement number testing
    }
    
    function showEklektikError(message) {
      const tbody = document.getElementById('eklektik-numbers-tbody');
      tbody.innerHTML = `
        <tr>
          <td colspan="7" style="text-align: center; padding: 40px; color: var(--danger);">
            ❌ ${message}
          </td>
        </tr>
      `;
    }

    // ========================================
    // NOUVELLES FONCTIONS POUR STATISTIQUES EKLEKTIK
    // ========================================

    // Variables globales pour les graphiques Eklektik
    let eklektikCharts = {};

    // Variables globales pour les opérateurs
    let availableOperators = [];
    let selectedOperators = []; // Sera initialisé selon le rôle utilisateur
    let hasAllOption = false; // Indique si "Tous les opérateurs" est disponible

    // Center active tab on mobile
    function centerActiveTab(activeTab) {
      const navTabs = document.querySelector('.nav-tabs');
      const tabRect = activeTab.getBoundingClientRect();
      const navRect = navTabs.getBoundingClientRect();
      
      // Only auto-scroll on mobile/tablet
      if (window.innerWidth <= 768) {
        const scrollLeft = activeTab.offsetLeft - (navRect.width / 2) + (tabRect.width / 2);
        navTabs.scrollTo({
          left: Math.max(0, scrollLeft),
          behavior: 'smooth'
        });
      }
    }


    // Fonction obsolète supprimée - utilisez le composant eklektik-charts

    // Fonction utilitaire pour récupérer les statistiques
    async function fetchEklektikStats(endpoint, params) {
      const url = new URL(endpoint, window.location.origin);
      Object.keys(params).forEach(key => url.searchParams.append(key, params[key]));
      
      const response = await fetch(url, {
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json'
        }
      });
      
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      
      return await response.json();
    }

    // Charger les données Eklektik
    // loadEklektikData déjà définie plus haut

    // showEklektikStatsLoading, showEklektikStatsError et updateEklektikStatsDisplay déjà définies plus haut

    // Mettre à jour les statistiques par opérateur
    function updateEklektikOperatorsStats(distribution) {
      const container = document.getElementById('eklektik-operators-stats');
      if (!container) return;

      let html = '';
      for (const [operator, data] of Object.entries(distribution)) {
        html += `
          <div class="card mb-2">
            <div class="card-body">
              <h6 class="card-title">${operator}</h6>
              <p class="card-text">
                <strong>Revenus TTC:</strong> ${new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'TND' }).format(data.revenue_ttc || 0)}<br>
                <strong>Revenus HT:</strong> ${new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'TND' }).format(data.revenue_ht || 0)}<br>
                <strong>CA BigDeal:</strong> ${new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'TND' }).format(data.ca_bigdeal || 0)}
              </p>
            </div>
          </div>
        `;
      }

      container.innerHTML = html || '<div class="text-center text-muted">Aucune donnée disponible</div>';
    }

    // Créer les graphiques des statistiques Eklektik
    async function createEklektikStatsCharts(data) {
      const { overviewChart, revenueEvolution, revenueDistribution } = data;
      
      console.log('🎨 [CHARTS] Création des graphiques avec données:', data);
      
      // Détruire les graphiques existants
      console.log('🗑️ [CHARTS] Destruction des graphiques existants:', Object.keys(eklektikCharts));
      Object.values(eklektikCharts).forEach(chart => {
        if (chart) {
          console.log('🗑️ [CHARTS] Destruction d\'un graphique');
          chart.destroy();
        }
      });
      eklektikCharts = {};
      
      console.log('📊 [CHARTS] Création des nouveaux graphiques...');
      
      // Attendre un peu avant de créer les graphiques pour éviter les conflits
      setTimeout(() => {
        // Graphique multi-axes principal (Vue d'ensemble)
        createEklektikOverviewChart(overviewChart?.chart);
        
        // Graphique d'évolution des revenus
        createEklektikRevenueEvolutionChart(revenueEvolution?.chart);
        
        // Graphique de répartition par opérateur
        createEklektikOperatorsDistributionChart(revenueDistribution?.pie_chart);
        
        // Graphique CA par partenaire
        createEklektikCAPartnersChart(revenueDistribution?.bar_chart);
        
        // Afficher les statistiques par opérateur
        if (revenueDistribution?.data?.distribution) {
          displayEklektikOperatorsStats(revenueDistribution.data.distribution);
        } else {
          console.warn('❌ [OPERATORS STATS] Données de distribution manquantes:', revenueDistribution);
        }
      }, 50); // Délai de 50ms pour éviter les conflits de rendu
    }

    // Graphique multi-axes principal (Vue d'ensemble)
    function createEklektikOverviewChart(chartData) {
      const ctx = document.getElementById('eklektik-overview-chart');
      if (!ctx || !chartData) {
        console.log('❌ [OVERVIEW CHART] Pas de données ou contexte manquant');
        return;
      }
      
      // Vérifier si le graphique existe déjà et a les mêmes données
      if (eklektikCharts.overview && eklektikCharts.overview.data) {
        const currentData = JSON.stringify(eklektikCharts.overview.data);
        const newData = JSON.stringify(chartData);
        if (currentData === newData) {
          console.log('🔄 [OVERVIEW CHART] Données identiques, pas de recréation');
          return;
        }
      }
      
      // Détruire le graphique existant s'il existe
      if (eklektikCharts.overview) {
        console.log('🗑️ [OVERVIEW CHART] Destruction du graphique existant');
        eklektikCharts.overview.destroy();
        eklektikCharts.overview = null;
      }
      
      // Attendre un peu avant de créer le nouveau graphique
      setTimeout(() => {
      console.log('🎨 [DEBUG] Création du graphique multi-axes avec données:', chartData);
      
      // Créer le graphique avec des options ultra-strictes
      const chartOptions = {
        responsive: true,
        maintainAspectRatio: false,
        animation: false,
        animations: {
          duration: 0
        },
        // Désactiver complètement toutes les animations
        transitions: {
          active: {
            animation: {
              duration: 0
            }
          },
          resize: {
            animation: {
              duration: 0
            }
          }
        },
        elements: {
          point: {
            hoverRadius: 0
          },
          line: {
            tension: 0
          }
        },
        plugins: {
          legend: {
            animation: false
          },
          tooltip: {
            animation: false
          }
        },
        interaction: {
          mode: 'index',
          intersect: false,
        },
        scales: {
          x: {
            display: true,
            title: {
              display: true,
              text: 'Date'
            }
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
            grid: {
              drawOnChartArea: false,
            }
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
            grid: {
              drawOnChartArea: false,
            }
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
            grid: {
              drawOnChartArea: false,
            }
          }
        },
        plugins: {
          legend: {
            display: true,
            position: 'bottom',
            labels: {
              usePointStyle: true,
              padding: 20
            }
          },
          tooltip: {
            mode: 'index',
            intersect: false,
            callbacks: {
              label: function(context) {
                let label = context.dataset.label || '';
                if (label) {
                  label += ': ';
                }
                
                if (context.dataset.yAxisID === 'y-revenue') {
                  label += new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'TND' }).format(context.parsed.y * 1000);
                } else if (context.dataset.yAxisID === 'y-active') {
                  label += new Intl.NumberFormat('fr-FR').format(context.parsed.y);
                } else if (context.dataset.yAxisID === 'y-rate') {
                  label += context.parsed.y.toFixed(2) + '%';
                }
                
                return label;
              }
            }
          }
        }
      };
      
      console.log('🔧 [DEBUG] Options du graphique:', chartOptions);
      
      // Créer le graphique avec interception de requestAnimationFrame
      // Pas d'interception globale - laissons les autres graphiques fonctionner normalement
      
      try {
        eklektikCharts.overview = new Chart(ctx, {
          type: 'bar',
          data: chartData,
          options: chartOptions
        });
      } catch (error) {
        console.error('❌ [OVERVIEW CHART] Erreur lors de la création:', error);
      } finally {
        // Restaurer requestAnimationFrame
        window.requestAnimationFrame = originalRAF;
        window.cancelAnimationFrame = originalCAF;
      }
      
      console.log('✅ [OVERVIEW CHART] Graphique multi-axes créé avec succès');
      console.log('🔍 [DEBUG] Graphique overview:', eklektikCharts.overview);
      }, 10); // Délai de 10ms pour éviter les conflits de rendu
    }

    // Graphique d'évolution des revenus
    function createEklektikRevenueEvolutionChart(chartData) {
      const ctx = document.getElementById('eklektik-revenue-evolution-chart');
      if (!ctx || !chartData) {
        console.log('❌ [REVENUE EVOLUTION CHART] Pas de données ou contexte manquant');
        return;
      }
      
      // Vérifier si le graphique existe déjà et a les mêmes données
      if (eklektikCharts.revenueEvolution && eklektikCharts.revenueEvolution.data) {
        const currentData = JSON.stringify(eklektikCharts.revenueEvolution.data);
        const newData = JSON.stringify(chartData);
        if (currentData === newData) {
          console.log('🔄 [REVENUE EVOLUTION CHART] Données identiques, pas de recréation');
          return;
        }
      }
      
      // Détruire le graphique existant s'il existe
      if (eklektikCharts.revenueEvolution) {
        console.log('🗑️ [REVENUE EVOLUTION CHART] Destruction du graphique existant');
        eklektikCharts.revenueEvolution.destroy();
        eklektikCharts.revenueEvolution = null;
      }
      
      eklektikCharts.revenueEvolution = new Chart(ctx, {
        type: 'line',
        data: chartData,
        options: {
          responsive: true,
          maintainAspectRatio: false,
          animation: false,
          animations: {
            duration: 0
          },
          scales: {
            y: {
              beginAtZero: true,
              ticks: {
                callback: function(value) {
                  return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'TND' }).format(value);
                }
              }
            }
          },
          plugins: {
            tooltip: {
              callbacks: {
                label: function(context) {
                  return context.dataset.label + ': ' + 
                    new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'TND' }).format(context.parsed.y);
                }
              }
            }
          }
        }
      });
    }

    // Graphique de répartition par opérateur
    function createEklektikOperatorsDistributionChart(chartData) {
      const ctx = document.getElementById('eklektik-operators-distribution-chart');
      if (!ctx || !chartData) {
        console.log('❌ [OPERATORS DISTRIBUTION CHART] Pas de données ou contexte manquant');
        return;
      }
      
      // Vérifier si le graphique existe déjà et a les mêmes données
      if (eklektikCharts.operatorsDistribution && eklektikCharts.operatorsDistribution.data) {
        const currentData = JSON.stringify(eklektikCharts.operatorsDistribution.data);
        const newData = JSON.stringify(chartData);
        if (currentData === newData) {
          console.log('🔄 [OPERATORS DISTRIBUTION CHART] Données identiques, pas de recréation');
          return;
        }
      }
      
      // Détruire le graphique existant s'il existe
      if (eklektikCharts.operatorsDistribution) {
        console.log('🗑️ [OPERATORS DISTRIBUTION CHART] Destruction du graphique existant');
        eklektikCharts.operatorsDistribution.destroy();
        eklektikCharts.operatorsDistribution = null;
      }
      
      eklektikCharts.operatorsDistribution = new Chart(ctx, {
        type: 'doughnut',
        data: chartData,
        options: {
          responsive: true,
          maintainAspectRatio: false,
          animation: false,
          animations: {
            duration: 0
          },
          plugins: {
            tooltip: {
              callbacks: {
                label: function(context) {
                  return context.label + ': ' + 
                    new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'TND' }).format(context.parsed);
                }
              }
            }
          }
        }
      });
    }

    // Graphique CA par partenaire
    function createEklektikCAPartnersChart(chartData) {
      const ctx = document.getElementById('eklektik-ca-partners-chart');
      if (!ctx || !chartData) {
        console.log('❌ [CA PARTNERS CHART] Pas de données ou contexte manquant');
        return;
      }
      
      // Vérifier si le graphique existe déjà et a les mêmes données
      if (eklektikCharts.caPartners && eklektikCharts.caPartners.data) {
        const currentData = JSON.stringify(eklektikCharts.caPartners.data);
        const newData = JSON.stringify(chartData);
        if (currentData === newData) {
          console.log('🔄 [CA PARTNERS CHART] Données identiques, pas de recréation');
          return;
        }
      }
      
      // Détruire le graphique existant s'il existe
      if (eklektikCharts.caPartners) {
        console.log('🗑️ [CA PARTNERS CHART] Destruction du graphique existant');
        eklektikCharts.caPartners.destroy();
        eklektikCharts.caPartners = null;
      }
      
      eklektikCharts.caPartners = new Chart(ctx, {
        type: 'bar',
        data: chartData,
        options: {
          responsive: true,
          maintainAspectRatio: false,
          animation: false,
          animations: {
            duration: 0
          },
          scales: {
            y: {
              beginAtZero: true,
              ticks: {
                callback: function(value) {
                  return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'TND' }).format(value);
                }
              }
            }
          },
          plugins: {
            tooltip: {
              callbacks: {
                label: function(context) {
                  return context.dataset.label + ': ' + 
                    new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'TND' }).format(context.parsed.y);
                }
              }
            }
          }
        }
      });
    }

    // Afficher les statistiques par opérateur
    function displayEklektikOperatorsStats(distribution) {
      const container = document.getElementById('eklektik-operators-stats');
      if (!container || !distribution) {
        console.log('❌ [OPERATORS STATS] Pas de données ou conteneur manquant');
        return;
      }
      
      let html = '';
      
      for (const [operator, data] of Object.entries(distribution)) {
        const newSubs = (data.new_subscriptions ?? data.new_subs ?? data.subscriptions ?? data.activated ?? 0);
        const active = (data.active_subscribers ?? data.active ?? 0);
        const fact = (data.facturation ?? 0);
        const rev = (data.revenue_ttc ?? data.ca_bigdeal ?? 0);
        const formattedNewSubs = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(newSubs);
        const formattedActive = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(active);
        const formattedFact = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(fact);
        const formattedRev = new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'TND', maximumFractionDigits: 0 }).format(rev);
        html += `
          <div class="card mb-2" style="border: 1px solid var(--border); border-radius: 8px; padding: 12px;">
            <div class="card-body" style="padding: 0;">
              <h6 class="card-title" style="margin: 0 0 8px 0; font-weight: 600; color: var(--brand-dark);">${operator}</h6>
              <div style="font-size: 12px; line-height: 1.4;">
                <div><strong>Active subs:</strong> ${formattedActive}</div>
                <div><strong>Nouveaux abonnements:</strong> ${formattedNewSubs}</div>
                <div><strong>Facturations:</strong> ${formattedFact}</div>
                <div><strong>Revenus TTC:</strong> ${formattedRev}</div>
              </div>
            </div>
          </div>
        `;
      }
      
      container.innerHTML = html;
    }

    // Fonctions pour les boutons de configuration
    async function checkEklektikSyncStatus() {
      try {
        const response = await fetch('/api/eklektik-dashboard/sync-status');
        const data = await response.json();
        
        if (data.success && data.data) {
          const status = data.data;
          const statusValue = status.status || 'unknown';
          const statusColor = statusValue === 'healthy' ? 'success' : 
                             statusValue === 'warning' ? 'warning' : 'danger';
          
          const lastSync = status.last_sync ? 
            new Date(status.last_sync).toLocaleString('fr-FR') : 'Jamais';
          
          const totalRecords = status.total_records || 0;
          
          alert(`Statut Eklektik: ${statusValue.toUpperCase()}\nDernière sync: ${lastSync}\nEnregistrements: ${totalRecords}`);
        } else {
          alert('Erreur: Impossible de récupérer le statut de synchronisation');
        }
      } catch (error) {
        console.error('❌ [EKLEKTIK SYNC] Erreur lors de la vérification du statut:', error);
        alert('Erreur lors de la vérification du statut de synchronisation');
      }
    }

    async function clearEklektikCache() {
      try {
        const response = await fetch('/api/eklektik-dashboard/clear-cache', { method: 'POST' });
        const data = await response.json();
        
        if (data.success) {
          alert('Cache vidé avec succès!');
          console.log('Cache vidé - les graphiques vont se recharger automatiquement');
        } else {
          alert('Erreur lors du vidage du cache: ' + data.message);
        }
      } catch (error) {
        console.error('❌ [EKLEKTIK CACHE] Erreur lors du vidage du cache:', error);
        alert('Erreur lors du vidage du cache');
      }
    }

    // Graphique d'évolution des abonnements
    function createEklektikSubscriptionsChart(data) {
      const ctx = document.getElementById('eklektik-subscriptions-chart');
      if (!ctx || !data) {
        console.log('❌ [SUBSCRIPTIONS CHART] Pas de données ou contexte manquant', { ctx: !!ctx, data: data });
        return;
      }
      
      console.log('📊 [SUBSCRIPTIONS CHART] Données reçues:', data);
      console.log('📊 [SUBSCRIPTIONS CHART] Contexte canvas:', { 
        width: ctx.width, 
        height: ctx.height, 
        offsetWidth: ctx.offsetWidth, 
        offsetHeight: ctx.offsetHeight 
      });
      
      // Détruire le graphique existant s'il existe
      if (eklektikCharts.subscriptions) {
        console.log('🗑️ [SUBSCRIPTIONS CHART] Destruction du graphique existant');
        eklektikCharts.subscriptions.destroy();
        eklektikCharts.subscriptions = null;
      }
      
      eklektikCharts.subscriptions = new Chart(ctx, {
        type: 'line',
        data: {
          labels: ['Nouveaux', 'Actifs', 'Désabonnements'],
          datasets: [{
            label: 'Abonnements',
            data: [
              data.kpis?.sub_count || 0,
              data.kpis?.active_subscriptions || 0,
              data.kpis?.unsub_count || 0
            ],
            borderColor: '#3b82f6',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            tension: 0.4
          }]
        },
        options: {
          responsive: true,
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

    // Graphique d'évolution des revenus
    function createEklektikRevenueChart(data) {
      const ctx = document.getElementById('eklektik-revenue-chart');
      if (!ctx || !data) {
        console.log('❌ [REVENUE CHART] Pas de données ou contexte manquant', { ctx: !!ctx, data: data });
        return;
      }
      
      console.log('📊 [REVENUE CHART] Données reçues:', data);
      console.log('📊 [REVENUE CHART] Contexte canvas:', { 
        width: ctx.width, 
        height: ctx.height, 
        offsetWidth: ctx.offsetWidth, 
        offsetHeight: ctx.offsetHeight 
      });
      
      // Détruire le graphique existant s'il existe
      if (eklektikCharts.revenue) {
        console.log('🗑️ [REVENUE CHART] Destruction du graphique existant');
        eklektikCharts.revenue.destroy();
        eklektikCharts.revenue = null;
      }
      
      eklektikCharts.revenue = new Chart(ctx, {
        type: 'bar',
        data: {
          labels: ['RENEW', 'CHARGE'],
          datasets: [{
            label: 'Revenus (TND)',
            data: [
              data.revenue_by_action?.RENEW || 0,
              data.revenue_by_action?.CHARGE || 0
            ],
            backgroundColor: ['#10b981', '#f59e0b']
          }]
        },
        options: {
          responsive: true,
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

    // Graphique de répartition par action
    function createEklektikActionsPieChart(data) {
      const ctx = document.getElementById('eklektik-actions-pie-chart');
      if (!ctx || !data?.kpis) {
        console.log('❌ [ACTIONS CHART] Pas de données ou contexte manquant', { ctx: !!ctx, data: data });
        return;
      }
      
      if (typeof Chart === 'undefined') {
        console.error('❌ [ACTIONS CHART] Chart.js non chargé');
        return;
      }
      
      // Détruire le graphique existant s'il existe
      if (eklektikCharts.actions) {
        eklektikCharts.actions.destroy();
        eklektikCharts.actions = null;
      }
      
      const kpis = data.kpis;
      console.log('📊 [ACTIONS CHART] Données KPIs:', kpis);
      console.log('📊 [ACTIONS CHART] Contexte canvas:', { 
        width: ctx.width, 
        height: ctx.height, 
        offsetWidth: ctx.offsetWidth, 
        offsetHeight: ctx.offsetHeight 
      });
      
      const actions = [
        { label: 'SUB', value: kpis.new_subscriptions || 0, color: '#3b82f6' },
        { label: 'RENEW', value: kpis.renewals || 0, color: '#10b981' },
        { label: 'CHARGE', value: kpis.charges || 0, color: '#f59e0b' },
        { label: 'UNSUB', value: kpis.unsubscriptions || 0, color: '#ef4444' }
      ];
      
      console.log('📊 [ACTIONS CHART] Actions calculées:', actions);
      
      // Filtrer les actions avec des valeurs > 0
      const filteredActions = actions.filter(action => action.value > 0);
      
      console.log('📊 [ACTIONS CHART] Actions filtrées:', filteredActions);
      
      if (filteredActions.length === 0) {
        console.log('⚠️ [ACTIONS CHART] Aucune action avec valeur > 0');
        return;
      }
      
      console.log('📊 [ACTIONS CHART] Création du graphique avec données:', {
        labels: filteredActions.map(action => action.label),
        data: filteredActions.map(action => action.value)
      });
      
      eklektikCharts.actions = new Chart(ctx, {
        type: 'bar',
        data: {
          labels: filteredActions.map(action => action.label),
          datasets: [{
            label: 'Nombre d\'actions',
            data: filteredActions.map(action => action.value),
            backgroundColor: filteredActions.map(action => action.color),
            borderColor: filteredActions.map(action => action.color),
            borderWidth: 1
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: true,
          aspectRatio: 2,
          interaction: {
            intersect: false,
            mode: 'index'
          },
          plugins: {
            legend: {
              display: false
            },
            tooltip: {
              callbacks: {
                label: function(context) {
                  const label = context.label || '';
                  const value = context.parsed.y;
                  const total = context.dataset.data.reduce((a, b) => a + b, 0);
                  const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                  return `${label}: ${value} (${percentage}%)`;
                }
              }
            }
          },
          scales: {
            y: {
              beginAtZero: true,
              ticks: {
                stepSize: 1
              }
            }
          },
          animation: false,
          transitions: {
            active: {
              animation: {
                duration: 0
              }
            },
            resize: {
              animation: {
                duration: 0
              }
            }
          },
          layout: {
            padding: {
              top: 10,
              bottom: 10,
              left: 10,
              right: 10
            }
          }
        }
      });
      
      console.log('✅ [ACTIONS CHART] Graphique créé avec succès');
    }

    // Graphique de répartition par opérateur
    async function createEklektikOperatorsChart(data) {
      const ctx = document.getElementById('eklektik-operators-chart');
      if (!ctx || !data?.operators_distribution) {
        console.log('❌ [OPERATORS CHART] Pas de données ou contexte manquant', { ctx: !!ctx, data: data });
        return;
      }
      
      if (typeof Chart === 'undefined') {
        console.error('❌ [OPERATORS CHART] Chart.js non chargé');
        return;
      }
      
      console.log('📊 [OPERATORS CHART] Données opérateurs:', data.operators_distribution);
      console.log('📊 [OPERATORS CHART] Contexte canvas:', { 
        width: ctx.width, 
        height: ctx.height, 
        offsetWidth: ctx.offsetWidth, 
        offsetHeight: ctx.offsetHeight 
      });
      
      // Détruire le graphique existant s'il existe
      if (eklektikCharts.operators) {
        console.log('🗑️ [OPERATORS CHART] Destruction du graphique existant');
        try {
          eklektikCharts.operators.destroy();
        } catch (e) {
          console.warn('Erreur lors de la destruction du graphique opérateurs:', e);
        }
        eklektikCharts.operators = null;
      }
      
      // Attendre un tick pour éviter les conflits de rendu
      await new Promise(resolve => setTimeout(resolve, 10));
      
      // Extraire les données des opérateurs
      const operatorsData = data.operators_distribution;
      const operators = Object.keys(operatorsData);
      const values = operators.map(op => operatorsData[op].total);
      
      console.log('📊 [OPERATORS CHART] Opérateurs extraits:', operators);
      console.log('📊 [OPERATORS CHART] Valeurs extraites:', values);
      
      // Couleurs pour chaque opérateur
      const colors = {
        'Orange': '#FF9500',
        'TT': '#FF6384',
        'Taraji': '#4BC0C0',
        'Timwe': '#36A2EB',
        'Ooredoo': '#FFCE56',
        'Unknown': '#9E9E9E'
      };
      
      eklektikCharts.operators = new Chart(ctx, {
        type: 'bar',
        data: {
          labels: operators,
          datasets: [{
            label: 'Transactions par Opérateur',
            data: values,
            backgroundColor: operators.map(op => colors[op] || '#9E9E9E'),
            borderColor: operators.map(op => colors[op] || '#9E9E9E'),
            borderWidth: 1
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: true,
          aspectRatio: 2,
          interaction: {
            intersect: false,
            mode: 'index'
          },
          plugins: {
            legend: {
              display: false
            },
            tooltip: {
              callbacks: {
                label: function(context) {
                  const label = context.label || '';
                  const value = context.parsed.y;
                  const total = context.dataset.data.reduce((a, b) => a + b, 0);
                  const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                  const operatorData = operatorsData[label];
                  return `${label}: ${value} transactions (${percentage}%)\n` +
                         `- Abonnements: ${operatorData.sub}\n` +
                         `- Renouvellements: ${operatorData.renew}\n` +
                         `- Facturations: ${operatorData.charge}\n` +
                         `- Revenus: ${operatorData.revenue} TND`;
                }
              }
            }
          },
          scales: {
            y: {
              beginAtZero: true,
              ticks: {
                stepSize: 1
              }
            }
          },
          layout: {
            padding: {
              top: 10,
              bottom: 10,
              left: 10,
              right: 10
            }
          },
          animation: false,
          transitions: {
            active: {
              animation: {
                duration: 0
              }
            },
            resize: {
              animation: {
                duration: 0
              }
            }
          }
        }
      });
      
      console.log('✅ [OPERATORS CHART] Graphique créé avec succès');
    }

    // Graphique du taux de facturation
    function createEklektikBillingRateChart(data) {
      const ctx = document.getElementById('eklektik-billing-rate-chart');
      if (!ctx || !data) {
        console.log('❌ [BILLING RATE CHART] Pas de données ou contexte manquant', { ctx: !!ctx, data: data });
        return;
      }
      
      console.log('📊 [BILLING RATE CHART] Données reçues:', data);
      console.log('📊 [BILLING RATE CHART] Contexte canvas:', { 
        width: ctx.width, 
        height: ctx.height, 
        offsetWidth: ctx.offsetWidth, 
        offsetHeight: ctx.offsetHeight 
      });
      
      // Détruire le graphique existant s'il existe
      if (eklektikCharts.billingRate) {
        console.log('🗑️ [BILLING RATE CHART] Destruction du graphique existant');
        eklektikCharts.billingRate.destroy();
        eklektikCharts.billingRate = null;
      }
      
      eklektikCharts.billingRate = new Chart(ctx, {
        type: 'bar',
        data: {
          labels: ['Taux de Facturation'],
          datasets: [{
            label: 'Taux (%)',
            data: [data.billing_rate || 0],
            backgroundColor: '#10b981'
          }]
        },
        options: {
          responsive: true,
          plugins: {
            legend: {
              display: false
            }
          },
          scales: {
            y: {
              beginAtZero: true,
              max: 100
            }
          }
        }
      });
    }

    // Afficher l'erreur des statistiques
    function showEklektikStatsError(message) {
      const elements = [
        'eklektik-billing-rate', 'eklektik-revenue', 'eklektik-active-subscriptions',
        'eklektik-new-subscriptions', 'eklektik-unsubscriptions', 'eklektik-renewals', 'eklektik-charges', 'eklektik-billed-clients'
      ];
      
      elements.forEach(id => {
        const element = document.getElementById(id);
        if (element) {
          element.textContent = 'Erreur';
        }
      });
      
      console.error('❌ [EKLEKTIK STATS]', message);
    }

    // Exporter les statistiques Eklektik
    function exportEklektikStats() {
      showNotification('📥 Export des statistiques Eklektik en cours...', 'info', 2000);
      // TODO: Implémenter l'export des statistiques
    }

    // Debug pour les événements de redimensionnement (désactivé pour éviter les boucles)
    // window.addEventListener('resize', function() {
    //   console.log('📏 [RESIZE] Redimensionnement détecté');
    //   clearTimeout(resizeTimeout);
    //   resizeTimeout = setTimeout(() => {
    //     console.log('📏 [RESIZE] Redimensionnement terminé, recréation des graphiques');
    //     if (Object.keys(eklektikCharts).length > 0) {
    //       // Les graphiques se rechargent automatiquement
    //     }
    //   }, 300);
    // });

    // Initialisation du dashboard - les graphiques Eklektik se chargent automatiquement
    document.addEventListener('DOMContentLoaded', function() {
      console.log('🏁 [INIT] Initialisation du dashboard - configuration terminée');
    });
    
    // Helper functions
    
    /**
     * Calcule le changement en pourcentage entre deux valeurs
     * @param {number} current - Valeur actuelle
     * @param {number} previous - Valeur précédente
     * @returns {number} - Pourcentage de changement
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
          { name: 'merchants', url: `/api/dashboard/split/merchants?${queryString}`, label: 'Marchands', weight: 25 },
          { name: 'transactions', url: `/api/dashboard/split/transactions?${queryString}`, label: 'Transactions', weight: 15 },
          { name: 'subscriptions', url: `/api/dashboard/split/subscriptions?${queryString}`, label: 'Abonnements', weight: 30 },
          { name: 'ooredoo_stats', url: `/api/dashboard/split/ooredoo?${queryString}`, label: 'Ooredoo', weight: 10 }
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
            }
            break;
          case 'merchants':
            if (json.data) {
              window._dashboardData.merchants = json.data;
              window._dashboardData.categoryDistribution = json.categoryDistribution || [];
              dashboardData = window._dashboardData;
              updateMerchantKPIs(json.data, window._dashboardData.kpis);
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
              // Mettre a jour les graphiques et tables d'abonnements
              if (typeof updateCharts === 'function') {
                try { updateCharts(window._dashboardData); } catch(e) {}
              }
              if (typeof updateTables === 'function') {
                try { updateTables(window._dashboardData); } catch(e) {}
              }
            }
            break;
          case 'ooredoo_stats':
            if (json.data) {
              window._dashboardData.ooredoo_stats = json.data;
              dashboardData = window._dashboardData;
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
      
      if (loadTime < 500) {
        // Fast load - likely from cache
        indicator.style.display = 'flex';
        indicator.querySelector('.performance-text').textContent = 'Cache ⚡';
        indicator.style.background = 'rgba(16, 185, 129, 0.1)';
        indicator.style.borderColor = 'rgba(16, 185, 129, 0.3)';
        indicator.style.color = '#059669';
        
        // Hide after 3 seconds
        setTimeout(() => {
          indicator.style.display = 'none';
        }, 3000);
      } else if (loadTime < 2000) {
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
    
    // Set smart comparison period (same duration as primary, just before)
    function setSmartComparison() {
      const startDate = new Date(document.getElementById('start-date').value);
      const endDate = new Date(document.getElementById('end-date').value);
      
      if (startDate && endDate) {
        const duration = Math.ceil((endDate - startDate) / (1000 * 60 * 60 * 24));
        
        const comparisonEndDate = new Date(startDate);
        comparisonEndDate.setDate(comparisonEndDate.getDate() - 1);
        const comparisonStartDate = new Date(comparisonEndDate);
        comparisonStartDate.setDate(comparisonStartDate.getDate() - duration);
        
        document.getElementById('comparison-start-date').value = comparisonStartDate.toISOString().split('T')[0];
        document.getElementById('comparison-end-date').value = comparisonEndDate.toISOString().split('T')[0];
        
        updateDateRange();
        loadDashboardData();
      }
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
      
      // Timwe Tab KPIs (super admin uniquement)
      if (kpis?.billingRateTimwe) {
        updateKPI('timwe-billing-rate', normalizeKPI(kpis?.billingRateTimwe), '%');
        updateKPI('timwe-total-billings', normalizeKPI(kpis?.totalTimweBillings));
        
        // Récupérer les statistiques mensuelles groupées Timwe depuis les données du dashboard
        if (dashboardData && dashboardData.subscriptions && dashboardData.subscriptions.timwe_monthly_stats) {
          updateTimweStatisticsTable(dashboardData.subscriptions.timwe_monthly_stats);
          
          // DÉSACTIVÉ POUR OPTIMISATION: Tableau des transactions Timwe par utilisateur
          // if (dashboardData.subscriptions.timwe_transactions_by_user) {
          //   updateTimweTransactionsTable(dashboardData.subscriptions.timwe_transactions_by_user);
          // } else {
          //   updateTimweTransactionsTable([]);
          // }
          
          // Calculer les KPIs agrégés avec comparaison (depuis les données mensuelles)
          const monthlyStats = dashboardData.subscriptions.timwe_monthly_stats || [];
          const monthlyStatsComparison = dashboardData.subscriptions.timwe_monthly_stats_comparison || [];
          
          const totals = calculateTimweTotals(monthlyStats);
          const comparisonTotals = monthlyStatsComparison.length > 0 
            ? calculateTimweComparisonTotals(monthlyStatsComparison) 
            : null;
          
          console.log('🔍 [TIMWE] Statistiques:', {
            current: monthlyStats.length,
            comparison: monthlyStatsComparison.length,
            hasSeparateComparison: !!dashboardData.subscriptions.timwe_monthly_stats_comparison
          });
          
          // Helper pour créer un objet KPI avec ou sans comparaison
          const makeKPI = (current, previous) => {
            if (previous === null || previous === undefined || !comparisonTotals) {
              return { current, previous: 0, change: 0 };
            }
            return {
              current,
              previous,
              change: calculateChange(current, previous)
            };
          };
          
          // Mise à jour des KPIs avec comparaison (si disponible)
          const newSubsKPI = makeKPI(totals.newSubs, comparisonTotals?.newSubs);
          console.log('🔍 [TIMWE KPI] Nouveaux Abonnements:', newSubsKPI);
          
          updateKPI('timwe-active-subs', makeKPI(
            totals.activeSubsEndOfPeriod,
            comparisonTotals?.activeSubsEndOfPeriod
          ));
          
          updateKPI('timwe-new-subscriptions', newSubsKPI);
          
          updateKPI('timwe-unsubscriptions', makeKPI(
            totals.unsubs,
            comparisonTotals?.unsubs
          ));
          
          updateKPI('timwe-simchurn', makeKPI(
            totals.simchurn,
            comparisonTotals?.simchurn
          ));
          
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
          
          // Calculer le nombre de jours de la période pour normaliser l'ARPU
          const startDate = document.getElementById('start-date')?.value;
          const endDate = document.getElementById('end-date')?.value;
          let periodDays = 30; // Défaut
          if (startDate && endDate) {
            const start = new Date(startDate);
            const end = new Date(endDate);
            periodDays = Math.ceil((end - start) / (1000 * 60 * 60 * 24)) || 30;
          }
          
          // Taux de Croissance Nette = ((New Subs - Unsubs - Simchurn) / Active Subs) * 100
          const netGrowth = totals.newSubs - totals.unsubs - totals.simchurn;
          const netGrowthRate = totals.activeSubsEndOfPeriod > 0 
            ? (netGrowth / totals.activeSubsEndOfPeriod) * 100 
            : 0;
          
          const netGrowthRateComparison = comparisonTotals && comparisonTotals.activeSubsEndOfPeriod > 0
            ? ((comparisonTotals.newSubs - comparisonTotals.unsubs - comparisonTotals.simchurn) / comparisonTotals.activeSubsEndOfPeriod) * 100
            : null;
          
          // Formater le taux de croissance avec 2 décimales
          const netGrowthFormatted = formatNumber(netGrowthRate, 2);
          const netGrowthComparisonFormatted = netGrowthRateComparison !== null ? formatNumber(netGrowthRateComparison, 2) : 0;
          
          updateKPI('timwe-net-growth-rate', {
            current: netGrowthFormatted,
            previous: netGrowthComparisonFormatted,
            change: netGrowthRateComparison !== null ? calculateChange(netGrowthRate, netGrowthRateComparison) : 0
          }, '%');
          
          // ARPU mensuel normalisé (30 jours)
          // Formule : (Revenu Total / Active Subs) * (30 / Nombre de jours)
          const arpuValue = totals.activeSubsEndOfPeriod > 0 
            ? (totals.revenueTnd / totals.activeSubsEndOfPeriod) * (30 / periodDays)
            : 0;
          const arpuFormatted = formatNumber(arpuValue, 3);
          
          updateKPI('timwe-arpu', { current: arpuFormatted, previous: 0, change: 0 }, ' TND');
          
          const avgBillingValue = kpis?.totalTimweBillings?.current > 0 
            ? totals.revenueTnd / kpis.totalTimweBillings.current
            : 0;
          const avgBillingFormatted = formatNumber(avgBillingValue, 3);
          updateKPI('timwe-avg-billing-revenue', { current: avgBillingFormatted, previous: 0, change: 0 }, ' TND');
        }
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
        
            // Si le backend ne calcule pas la part, on la calcule côté client
            const enriched = Array.isArray(merchants) ? merchants.slice() : [];
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
        // Utiliser formatNumber avec 0 décimales pour les entiers, sauf si c'est un pourcentage ou déjà formaté
        const formattedValue = (typeof safe.current === 'string') ? safe.current : formatNumber(safe.current, 0);
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
          
          // Inverser la couleur pour les KPI où une baisse est positive (ex: deactivated, churn, durée entre transactions)
          const inverse = elementId.includes('deactivated') || elementId.includes('churn') || elementId.includes('lostSubscriptions') || elementId.includes('retentionRateTrue') || elementId.includes('avgInterTxDays');
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

    // Update charts
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
      // Ne pas afficher ces graphiques pour les collaborateurs
      @if(!Auth::user()->isCollaborator())
      createTransactionsByOperatorChart(data);
      createTransactionsByPlanChart(data);
      @endif

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
      const retentionTrend = data.subscriptions?.retention_trend || [];
      
      if (!retentionTrend || retentionTrend.length === 0) {
        // Afficher un message si pas de données
        ctx.parentElement.innerHTML = '<div style="padding: 20px; text-align: center; color: var(--muted);">Aucune donnée de rétention disponible</div>';
        return;
      }
      
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
        ctx.parentElement.innerHTML = '<div style="padding: 20px; text-align: center; color: var(--muted);">Aucune donnée de rétention disponible</div>';
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
      
      if (charts.transactionVolume) {
        charts.transactionVolume.destroy();
      }
      
      // Use real daily transactions data from backend
      const dailyTransactions = data.transactions?.daily_volume || [];
      
      if (!dailyTransactions || dailyTransactions.length === 0) {
        // Afficher un message si pas de données
        ctx.parentElement.innerHTML = '<div style="padding: 20px; text-align: center; color: var(--muted);">Aucune donnée de transaction disponible</div>';
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
      
      if (charts.transactingUsers) {
        charts.transactingUsers.destroy();
      }
      
      // Use real daily transactions data from backend to extract users
      const dailyTransactions = data.transactions?.daily_volume || [];
      
      if (!dailyTransactions || dailyTransactions.length === 0) {
        // Afficher un message si pas de données
        ctx.parentElement.innerHTML = '<div style="padding: 20px; text-align: center; color: var(--muted);">Aucune donnée d\'utilisateurs disponible</div>';
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
      
      const merchants = data.merchants || [];
      
      if (!merchants || merchants.length === 0) {
        // Afficher un message si pas de données
        ctx.parentElement.innerHTML = '<div style="padding: 20px; text-align: center; color: var(--muted);">Aucun marchand disponible</div>';
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
      
      if (charts.category) {
        charts.category.destroy();
      }
      
      const dist = data.categoryDistribution || [];
      
      if (!dist || dist.length === 0) {
        // Afficher un message si pas de données
        ctx.parentElement.innerHTML = '<div style="padding: 20px; text-align: center; color: var(--muted);">Aucune catégorie disponible</div>';
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
    function updateTables(data) {
      updateMerchantsTable(data.merchants);
      updateComparisonTable(data.kpis);
      // Chargement paresseux du tableau des abonnements
      setTimeout(() => {
        updateSubscriptionsTable(data.subscriptions);
        updateDailyStatisticsTable(data.subscriptions);
      }, 200);
    }
    
    // Variables pour le tableau des statistiques quotidiennes
    let allDailyStatistics = [];
    let currentDailyStatsSortColumn = -1;
    let dailyStatsSortDirection = 'asc';
    
    // Fonction pour mettre à jour le tableau des statistiques quotidiennes
    function updateDailyStatisticsTable(subscriptions) {
      const tbody = document.getElementById('daily-statistics-body');
      if (!tbody) return;
      
      // Récupérer les statistiques quotidiennes
      let dailyStats = [];
      if (subscriptions && subscriptions.daily_statistics && Array.isArray(subscriptions.daily_statistics)) {
        dailyStats = subscriptions.daily_statistics;
      }
      
      allDailyStatistics = dailyStats;
      
      if (!dailyStats || dailyStats.length === 0) {
        tbody.innerHTML = '<tr><td colspan="12" class="no-data" style="text-align: center; padding: 40px; color: var(--muted);">Aucune donnée disponible</td></tr>';
        return;
      }
      
      renderDailyStatisticsTable();
    }
    
    // Fonction pour afficher le tableau des statistiques quotidiennes
    function renderDailyStatisticsTable() {
      const tbody = document.getElementById('daily-statistics-body');
      if (!tbody) return;
      
      if (!allDailyStatistics || allDailyStatistics.length === 0) {
        tbody.innerHTML = '<tr><td colspan="12" class="no-data" style="text-align: center; padding: 40px; color: var(--muted);">Aucune donnée disponible</td></tr>';
        return;
      }
      
      tbody.innerHTML = allDailyStatistics.map(row => {
        const dimension = row.dimension || '-';
        const offre = row.offre || 'N/A';
        const newSub = row.new_sub || 0;
        const unsub = row.unsub || 0;
        const simchurn = row.simchurn || 0;
        const revSimchurn = row.rev_simchurn || 0;
        const activeSub = row.active_sub || 0;
        const nbFacturation = row.nb_facturation || 0;
        const tauxFacturation = row.taux_facturation || 0;
        const revenuTTC = row.revenu_ttc_tnd || row.revenu_ttc_local || 0;
        const revenuUSD = row.revenu_ttc_usd || 0;
        const revenuTND = row.revenu_ttc_tnd || row.revenu_ttc_local || 0;
        
        return `
          <tr>
            <td>${dimension}</td>
            <td>${offre}</td>
            <td>${newSub}</td>
            <td>${unsub}</td>
            <td>${simchurn}</td>
            <td>${revSimchurn}</td>
            <td>${activeSub.toLocaleString()}</td>
            <td>${nbFacturation.toLocaleString()}</td>
            <td>${tauxFacturation.toFixed(2)}%</td>
            <td>${revenuTTC.toFixed(2)}</td>
            <td>${revenuUSD.toFixed(2)}</td>
            <td>${revenuTND.toFixed(2)}</td>
          </tr>
        `;
      }).join('');
    }
    
    // Fonction pour trier le tableau des statistiques quotidiennes
    function sortDailyStatistics(columnIndex) {
      if (currentDailyStatsSortColumn === columnIndex) {
        dailyStatsSortDirection = dailyStatsSortDirection === 'asc' ? 'desc' : 'asc';
      } else {
        currentDailyStatsSortColumn = columnIndex;
        dailyStatsSortDirection = 'asc';
      }
      
      allDailyStatistics.sort((a, b) => {
        let aVal, bVal;
        
        switch(columnIndex) {
          case 0: aVal = a.dimension; bVal = b.dimension; break;
          case 1: aVal = a.offre; bVal = b.offre; break;
          case 2: aVal = a.new_sub || 0; bVal = b.new_sub || 0; break;
          case 3: aVal = a.unsub || 0; bVal = b.unsub || 0; break;
          case 4: aVal = a.simchurn || 0; bVal = b.simchurn || 0; break;
          case 5: aVal = a.rev_simchurn || 0; bVal = b.rev_simchurn || 0; break;
          case 6: aVal = a.active_sub || 0; bVal = b.active_sub || 0; break;
          case 7: aVal = a.nb_facturation || 0; bVal = b.nb_facturation || 0; break;
          case 8: aVal = a.taux_facturation || 0; bVal = b.taux_facturation || 0; break;
          case 9: aVal = a.revenu_ttc_local || 0; bVal = b.revenu_ttc_local || 0; break;
          case 10: aVal = a.revenu_ttc_usd || 0; bVal = b.revenu_ttc_usd || 0; break;
          case 11: aVal = a.revenu_ttc_tnd || 0; bVal = b.revenu_ttc_tnd || 0; break;
          default: return 0;
        }
        
        if (typeof aVal === 'string') {
          return dailyStatsSortDirection === 'asc' ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
        } else {
          return dailyStatsSortDirection === 'asc' ? aVal - bVal : bVal - aVal;
        }
      });
      
      renderDailyStatisticsTable();
    }
    
    // Fonction pour filtrer le tableau des statistiques quotidiennes
    function filterDailyStatistics() {
      const searchInput = document.getElementById('daily-stats-search');
      if (!searchInput) return;
      
      const searchTerm = searchInput.value.toLowerCase();
      
      if (!searchTerm) {
        renderDailyStatisticsTable();
        return;
      }
      
      const filtered = allDailyStatistics.filter(row => {
        return (
          (row.dimension && row.dimension.toLowerCase().includes(searchTerm)) ||
          (row.offre && row.offre.toLowerCase().includes(searchTerm)) ||
          String(row.new_sub || '').includes(searchTerm) ||
          String(row.unsub || '').includes(searchTerm) ||
          String(row.active_sub || '').includes(searchTerm)
        );
      });
      
      const tbody = document.getElementById('daily-statistics-body');
      if (!tbody) return;
      
      if (filtered.length === 0) {
        tbody.innerHTML = '<tr><td colspan="12" class="no-data" style="text-align: center; padding: 40px; color: var(--muted);">Aucun résultat trouvé</td></tr>';
        return;
      }
      
      tbody.innerHTML = filtered.map(row => {
        const dimension = row.dimension || '-';
        const offre = row.offre || 'N/A';
        const newSub = row.new_sub || 0;
        const unsub = row.unsub || 0;
        const simchurn = row.simchurn || 0;
        const revSimchurn = row.rev_simchurn || 0;
        const activeSub = row.active_sub || 0;
        const nbFacturation = row.nb_facturation || 0;
        const tauxFacturation = row.taux_facturation || 0;
        const revenuTTC = row.revenu_ttc_tnd || row.revenu_ttc_local || 0;
        const revenuUSD = row.revenu_ttc_usd || 0;
        const revenuTND = row.revenu_ttc_tnd || row.revenu_ttc_local || 0;
        
        return `
          <tr>
            <td>${dimension}</td>
            <td>${offre}</td>
            <td>${newSub}</td>
            <td>${unsub}</td>
            <td>${simchurn}</td>
            <td>${revSimchurn}</td>
            <td>${activeSub.toLocaleString()}</td>
            <td>${nbFacturation.toLocaleString()}</td>
            <td>${tauxFacturation.toFixed(2)}%</td>
            <td>${revenuTTC.toFixed(2)}</td>
            <td>${revenuUSD.toFixed(2)}</td>
            <td>${revenuTND.toFixed(2)}</td>
          </tr>
        `;
      }).join('');
    }
    
    // Fonction pour exporter en Excel (simplifiée - copie dans le presse-papier)
    function exportDailyStatistics() {
      if (!allDailyStatistics || allDailyStatistics.length === 0) {
        alert('Aucune donnée à exporter');
        return;
      }
      
      // Créer le CSV
      let csv = 'Dimension,Offre,New sub,Unsub,Simchurn,Rev Simchurn,Active Sub,NB facturation,Taux Facturation,Revenu TTC local,Revenu TTC USD,Revenu TTC TND\n';
      
      allDailyStatistics.forEach(row => {
        csv += `${row.dimension || ''},${row.offre || 'N/A'},${row.new_sub || 0},${row.unsub || 0},${row.simchurn || 0},${row.rev_simchurn || 0},${row.active_sub || 0},${row.nb_facturation || 0},${row.taux_facturation || 0},${row.revenu_ttc_local || 0},${row.revenu_ttc_usd || 0},${row.revenu_ttc_tnd || 0}\n`;
      });
      
      // Créer un blob et télécharger
      const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
      const link = document.createElement('a');
      const url = URL.createObjectURL(blob);
      link.setAttribute('href', url);
      link.setAttribute('download', `statistiques_quotidiennes_${new Date().toISOString().split('T')[0]}.csv`);
      link.style.visibility = 'hidden';
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
    }
    
    // Fonction pour copier les données
    function copyDailyStatistics() {
      if (!allDailyStatistics || allDailyStatistics.length === 0) {
        alert('Aucune donnée à copier');
        return;
      }
      
      // Créer le texte tabulé
      let text = 'Dimension\tOffre\tNew sub\tUnsub\tSimchurn\tRev Simchurn\tActive Sub\tNB facturation\tTaux Facturation\tRevenu TTC local\tRevenu TTC USD\tRevenu TTC TND\n';
      
      allDailyStatistics.forEach(row => {
        text += `${row.dimension || ''}\t${row.offre || 'N/A'}\t${row.new_sub || 0}\t${row.unsub || 0}\t${row.simchurn || 0}\t${row.rev_simchurn || 0}\t${row.active_sub || 0}\t${row.nb_facturation || 0}\t${row.taux_facturation || 0}\t${row.revenu_ttc_local || 0}\t${row.revenu_ttc_usd || 0}\t${row.revenu_ttc_tnd || 0}\n`;
      });
      
      navigator.clipboard.writeText(text).then(() => {
        alert('Données copiées dans le presse-papier !');
      }).catch(err => {
        console.error('Erreur lors de la copie:', err);
        alert('Erreur lors de la copie');
      });
    }
    
    // ===== FONCTIONS TIMWE =====
    let allTimweStatistics = [];
    let currentTimweStatsSortColumn = 0;
    let timweStatsSortDirection = 'asc';
    
    function calculateTimweTotals(monthlyStats) {
      if (!monthlyStats || monthlyStats.length === 0) {
        return {
          newSubs: 0,
          unsubs: 0,
          simchurn: 0,
          simchurnRevenue: 0,
          activeSubsEndOfPeriod: 0,
          revenueTnd: 0,
          caBigdealHt: 0
        };
      }
      
      const totals = {
        newSubs: 0,
        unsubs: 0,
        simchurn: 0,
        simchurnRevenue: 0,
        activeSubsEndOfPeriod: 0,
        revenueTnd: 0,
        caBigdealHt: 0
      };
      
      // Sommer les totaux mensuels
      monthlyStats.forEach(month => {
        totals.newSubs += Number(month.total_new_sub) || 0;
        totals.unsubs += Number(month.total_unsub) || 0;
        totals.simchurn += Number(month.total_simchurn) || 0;
        totals.simchurnRevenue += Number(month.total_rev_simchurn) || 0;
        totals.revenueTnd += Number(month.total_revenu_ttc_tnd) || 0;
        totals.caBigdealHt += Number(month.ca_bigdeal_ht) || 0;
      });
      
      // Active Subs = valeur du DERNIER mois de la période
      const lastMonth = monthlyStats[0]; // Le premier dans l'ordre décroissant
      totals.activeSubsEndOfPeriod = lastMonth ? (Number(lastMonth.total_active_sub) || 0) : 0;
      
      return totals;
    }
    
    function calculateTimweComparisonTotals(monthlyStatsComparison) {
      // Utiliser directement les données mensuelles de comparaison du backend
      if (!monthlyStatsComparison || monthlyStatsComparison.length === 0) {
        console.log('🔍 [TIMWE COMPARISON] Pas de données de comparaison');
        return null;
      }
      
      return calculateTimweTotals(monthlyStatsComparison);
    }
    
    // Stockage des mois Timwe et leur état d'expansion
    let allTimweMonthlyStats = [];
    let expandedMonths = new Set();
    
    function updateTimweStatisticsTable(monthlyStats) {
      const tbody = document.getElementById('timweStatsTableBody');
      if (!tbody) return;
      
      if (!monthlyStats || monthlyStats.length === 0) {
        tbody.innerHTML = '<tr><td colspan="10" class="no-data" style="text-align: center; padding: 40px; color: var(--muted);">Aucune donnée disponible</td></tr>';
        return;
      }
      
      allTimweMonthlyStats = monthlyStats;
      renderTimweStatisticsTable();
    }
    
    function renderTimweStatisticsTable() {
      const tbody = document.getElementById('timweStatsTableBody');
      if (!tbody) return;
      
      if (!allTimweMonthlyStats || allTimweMonthlyStats.length === 0) {
        tbody.innerHTML = '<tr><td colspan="10" class="no-data" style="text-align: center; padding: 40px; color: var(--muted);">Aucune donnée disponible</td></tr>';
        return;
      }
      
      let html = '';
      
      allTimweMonthlyStats.forEach((month, idx) => {
        const isExpanded = expandedMonths.has(month.month_key);
        const expandIcon = isExpanded ? '▼' : '▶';
        
        // Ligne du mois (cliquable)
        html += `
          <tr style="background: var(--card); border-bottom: 2px solid var(--border); cursor: pointer; font-weight: 600;" 
              onclick="toggleTimweMonth('${month.month_key}')">
            <td style="padding: 12px; text-align: center;">${expandIcon}</td>
            <td style="padding: 12px;">${month.display_label}</td>
            <td style="padding: 12px; text-align: center;">${formatNumber(month.total_new_sub, 0)}</td>
            <td style="padding: 12px; text-align: center;">${formatNumber(month.total_unsub, 0)}</td>
            <td style="padding: 12px; text-align: center;">${formatNumber(month.total_simchurn, 0)}</td>
            <td style="padding: 12px; text-align: center;">${formatNumber(month.total_active_sub, 0)}</td>
            <td style="padding: 12px; text-align: center;">${formatNumber(month.total_nb_facturation, 0)}</td>
            <td style="padding: 12px; text-align: center;">${formatPercentage(month.total_taux_facturation, 3)}</td>
            <td style="padding: 12px; text-align: center;">${formatNumber(month.total_revenu_ttc_tnd, 3)} TND</td>
            <td style="padding: 12px; text-align: center;">${formatNumber(month.ca_bigdeal_ht, 3)} TND</td>
          </tr>
        `;
        
        // Lignes des détails quotidiens (affichées seulement si le mois est expandé)
        if (isExpanded && month.daily_details && month.daily_details.length > 0) {
          month.daily_details.forEach(day => {
            html += `
              <tr style="background: rgba(0,0,0,0.02); border-bottom: 1px solid var(--border);">
                <td style="padding: 8px;"></td>
                <td style="padding: 8px; padding-left: 30px; font-size: 13px;">${day.dimension}</td>
                <td style="padding: 8px; text-align: center; font-size: 13px;">${formatNumber(day.new_sub || 0, 0)}</td>
                <td style="padding: 8px; text-align: center; font-size: 13px;">${formatNumber(day.unsub || 0, 0)}</td>
                <td style="padding: 8px; text-align: center; font-size: 13px;">${formatNumber(day.simchurn || 0, 0)}</td>
                <td style="padding: 8px; text-align: center; font-size: 13px;">${formatNumber(day.active_sub || 0, 0)}</td>
                <td style="padding: 8px; text-align: center; font-size: 13px;">${formatNumber(day.nb_facturation || 0, 0)}</td>
                <td style="padding: 8px; text-align: center; font-size: 13px;">${formatPercentage(day.taux_facturation || 0, 3)}</td>
                <td style="padding: 8px; text-align: center; font-size: 13px; color: var(--muted);">-</td>
                <td style="padding: 8px; text-align: center; font-size: 13px; color: var(--muted);">-</td>
              </tr>
            `;
          });
        }
      });
      
      tbody.innerHTML = html;
    }
    
    function toggleTimweMonth(monthKey) {
      if (expandedMonths.has(monthKey)) {
        expandedMonths.delete(monthKey);
      } else {
        expandedMonths.add(monthKey);
      }
      renderTimweStatisticsTable();
    }
    
    function sortTimweStatistics(columnIndex) {
      if (currentTimweStatsSortColumn === columnIndex) {
        timweStatsSortDirection = timweStatsSortDirection === 'asc' ? 'desc' : 'asc';
      } else {
        currentTimweStatsSortColumn = columnIndex;
        timweStatsSortDirection = 'asc';
      }
      
      allTimweStatistics.sort((a, b) => {
        let aVal, bVal;
        
        switch(columnIndex) {
          case 0: aVal = a.dimension; bVal = b.dimension; break;
          case 1: aVal = a.offre; bVal = b.offre; break;
          case 2: aVal = a.new_sub || 0; bVal = b.new_sub || 0; break;
          case 3: aVal = a.unsub || 0; bVal = b.unsub || 0; break;
          case 4: aVal = a.simchurn || 0; bVal = b.simchurn || 0; break;
          case 5: aVal = a.rev_simchurn || 0; bVal = b.rev_simchurn || 0; break;
          case 6: aVal = a.active_sub || 0; bVal = b.active_sub || 0; break;
          case 7: aVal = a.nb_facturation || 0; bVal = b.nb_facturation || 0; break;
          case 8: aVal = a.taux_facturation || 0; bVal = b.taux_facturation || 0; break;
          case 9: aVal = a.revenu_ttc_tnd || a.revenu_ttc_local || 0; bVal = b.revenu_ttc_tnd || b.revenu_ttc_local || 0; break;
          case 10: aVal = a.revenu_ttc_usd || 0; bVal = b.revenu_ttc_usd || 0; break;
          default: return 0;
        }
        
        if (typeof aVal === 'string') {
          return timweStatsSortDirection === 'asc' ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
        } else {
          return timweStatsSortDirection === 'asc' ? aVal - bVal : bVal - aVal;
        }
      });
      
      renderTimweStatisticsTable();
    }
    
    function filterTimweStats() {
      // Fonction simplifiée : on filtre simplement par le nom du mois
      renderTimweStatisticsTable();
    }
    
    function exportTimweStatsToExcel() {
      if (!allTimweMonthlyStats || allTimweMonthlyStats.length === 0) {
        alert('Aucune donnée à exporter');
        return;
      }
      
      let csv = 'Période,New Sub,Unsub,Simchurn,Active Sub,NB Facturation,Taux Facturation %,Revenu TTC (TND),CA BigDeal HT (TND)\n';
      
      allTimweMonthlyStats.forEach(month => {
        // Ligne du mois (avec formatage français)
        csv += `${month.display_label},${formatNumber(month.total_new_sub, 0)},${formatNumber(month.total_unsub, 0)},${formatNumber(month.total_simchurn, 0)},${formatNumber(month.total_active_sub, 0)},${formatNumber(month.total_nb_facturation, 0)},${formatPercentage(month.total_taux_facturation, 3)},${formatNumber(month.total_revenu_ttc_tnd, 3)},${formatNumber(month.ca_bigdeal_ht, 3)}\n`;
        
        // Lignes des détails quotidiens
        if (month.daily_details && month.daily_details.length > 0) {
          month.daily_details.forEach(day => {
            csv += `  ${day.dimension},${formatNumber(day.new_sub || 0, 0)},${formatNumber(day.unsub || 0, 0)},${formatNumber(day.simchurn || 0, 0)},${formatNumber(day.active_sub || 0, 0)},${formatNumber(day.nb_facturation || 0, 0)},${formatPercentage(day.taux_facturation || 0, 3)},-,-\n`;
          });
        }
      });
      
      const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
      const link = document.createElement('a');
      const url = URL.createObjectURL(blob);
      link.setAttribute('href', url);
      link.setAttribute('download', `timwe_statistiques_mensuelles_${new Date().toISOString().split('T')[0]}.csv`);
      link.style.visibility = 'hidden';
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
    }
    
    function copyTimweStatsToClipboard() {
      if (!allTimweMonthlyStats || allTimweMonthlyStats.length === 0) {
        alert('Aucune donnée à copier');
        return;
      }
      
      let text = 'Période\tNew Sub\tUnsub\tSimchurn\tActive Sub\tNB Facturation\tTaux Facturation %\tRevenu TTC (TND)\tCA BigDeal HT (TND)\n';
      
      allTimweMonthlyStats.forEach(month => {
        // Ligne du mois (avec formatage français)
        text += `${month.display_label}\t${formatNumber(month.total_new_sub, 0)}\t${formatNumber(month.total_unsub, 0)}\t${formatNumber(month.total_simchurn, 0)}\t${formatNumber(month.total_active_sub, 0)}\t${formatNumber(month.total_nb_facturation, 0)}\t${formatPercentage(month.total_taux_facturation, 3)}\t${formatNumber(month.total_revenu_ttc_tnd, 3)}\t${formatNumber(month.ca_bigdeal_ht, 3)}\n`;
        
        // Lignes des détails quotidiens
        if (month.daily_details && month.daily_details.length > 0) {
          month.daily_details.forEach(day => {
            text += `  ${day.dimension}\t${formatNumber(day.new_sub || 0, 0)}\t${formatNumber(day.unsub || 0, 0)}\t${formatNumber(day.simchurn || 0, 0)}\t${formatNumber(day.active_sub || 0, 0)}\t${formatNumber(day.nb_facturation || 0, 0)}\t${formatPercentage(day.taux_facturation || 0, 3)}\t-\t-\n`;
          });
        }
      });
      
      navigator.clipboard.writeText(text).then(() => {
        alert('Données copiées dans le presse-papier !');
      }).catch(err => {
        console.error('Erreur lors de la copie:', err);
        alert('Erreur lors de la copie');
      });
    }

    // ========== TIMWE TRANSACTIONS BY USER FUNCTIONS ==========
    // DÉSACTIVÉ POUR OPTIMISATION - TOUTES LES FONCTIONS CI-DESSOUS SONT COMMENTÉES
    /*
    let allTimweTransactions = [];
    let currentTimweTransactionsPage = 1;
    let timweTransactionsPerPage = 25;
    let currentTimweTransactionsSortColumn = 1; // Default: sort by nb_transactions
    let timweTransactionsSortDirection = 'desc';
    let filteredTimweTransactions = [];

    function updateTimweTransactionsTable(transactions) {
      const tbody = document.getElementById('timweTransactionsTableBody');
      if (!tbody) return;
      
      if (!transactions || transactions.length === 0) {
        // Vérifier si c'est une longue période
        const startDate = document.getElementById('start-date')?.value;
        const endDate = document.getElementById('end-date')?.value;
        let message = 'Aucune transaction disponible';
        
        if (startDate && endDate) {
          const start = new Date(startDate);
          const end = new Date(endDate);
          const diffDays = Math.ceil((end - start) / (1000 * 60 * 60 * 24));
          
          if (diffDays > 90) {
            message = '⚠️ Tableau désactivé pour les périodes > 90 jours (optimisation des performances). Veuillez sélectionner une période plus courte.';
          }
        }
        
        tbody.innerHTML = `<tr><td colspan="6" class="no-data" style="text-align: center; padding: 40px; color: var(--muted);">${message}</td></tr>`;
        return;
      }
      
      allTimweTransactions = transactions;
      filteredTimweTransactions = transactions;
      renderTimweTransactionsTable();
    }

    function renderTimweTransactionsTable() {
      const tbody = document.getElementById('timweTransactionsTableBody');
      if (!tbody) return;
      
      if (!filteredTimweTransactions || filteredTimweTransactions.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="no-data" style="text-align: center; padding: 40px; color: var(--muted);">Aucune transaction trouvée</td></tr>';
        return;
      }
      
      // Pagination
      const start = (currentTimweTransactionsPage - 1) * timweTransactionsPerPage;
      const end = start + timweTransactionsPerPage;
      const pageData = filteredTimweTransactions.slice(start, end);
      
      tbody.innerHTML = pageData.map(row => {
        const clientId = row.client_id || '-';
        const nbTransactions = row.nb_transactions || 0;
        const derniereTransactionId = row.derniere_transaction_id || '-';
        const derniereDate = row.derniere_date ? new Date(row.derniere_date).toLocaleString('fr-FR') : '-';
        const lastStatus = row.last_status || '-';
        
        // Badge de statut basé sur la facturation
        let statusBadge = '';
        if (lastStatus === 'RENOUVELÉ') {
          statusBadge = '<span style="padding: 4px 12px; background: #d1fae5; color: #065f46; border-radius: 12px; font-size: 11px; font-weight: 600;">✅ RENOUVELÉ</span>';
        } else if (lastStatus === 'NON RENOUVELÉ') {
          statusBadge = '<span style="padding: 4px 12px; background: #fee2e2; color: #991b1b; border-radius: 12px; font-size: 11px; font-weight: 600;">❌ NON RENOUVELÉ</span>';
        } else {
          statusBadge = '<span style="padding: 4px 12px; background: #f3f4f6; color: #374151; border-radius: 12px; font-size: 11px; font-weight: 600;">' + lastStatus + '</span>';
        }
        
        return `
          <tr>
            <td><strong>${clientId}</strong></td>
            <td><span style="font-weight: 600; color: var(--primary);">${nbTransactions}</span></td>
            <td>${derniereTransactionId}</td>
            <td>${derniereDate}</td>
            <td>${statusBadge}</td>
            <td>
              <button onclick="viewClientTimweTransactions(${clientId})" 
                      style="padding: 4px 8px; background: var(--primary); color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 11px;">
                📊 Détails
              </button>
            </td>
          </tr>
        `;
      }).join('');
      
      updateTimweTransactionsPagination();
    }

    function updateTimweTransactionsPagination() {
      const paginationDiv = document.getElementById('timweTransactionsPagination');
      if (!paginationDiv) return;
      
      const totalPages = Math.ceil(filteredTimweTransactions.length / timweTransactionsPerPage);
      
      if (totalPages <= 1) {
        paginationDiv.innerHTML = '';
        return;
      }
      
      let html = '';
      
      // Previous button
      if (currentTimweTransactionsPage > 1) {
        html += `<button onclick="changeTimweTransactionsPage(${currentTimweTransactionsPage - 1})" style="padding: 8px 12px; border: 1px solid var(--border); background: white; border-radius: 4px; cursor: pointer;">‹ Précédent</button>`;
      }
      
      // Page numbers
      for (let i = 1; i <= Math.min(totalPages, 5); i++) {
        const isActive = i === currentTimweTransactionsPage;
        html += `<button onclick="changeTimweTransactionsPage(${i})" style="padding: 8px 12px; border: 1px solid var(--border); background: ${isActive ? 'var(--primary)' : 'white'}; color: ${isActive ? 'white' : 'black'}; border-radius: 4px; cursor: pointer;">${i}</button>`;
      }
      
      if (totalPages > 5) {
        html += '<span style="padding: 8px;">...</span>';
        html += `<button onclick="changeTimweTransactionsPage(${totalPages})" style="padding: 8px 12px; border: 1px solid var(--border); background: white; border-radius: 4px; cursor: pointer;">${totalPages}</button>`;
      }
      
      // Next button
      if (currentTimweTransactionsPage < totalPages) {
        html += `<button onclick="changeTimweTransactionsPage(${currentTimweTransactionsPage + 1})" style="padding: 8px 12px; border: 1px solid var(--border); background: white; border-radius: 4px; cursor: pointer;">Suivant ›</button>`;
      }
      
      paginationDiv.innerHTML = html;
    }

    function changeTimweTransactionsPage(page) {
      currentTimweTransactionsPage = page;
      renderTimweTransactionsTable();
    }

    function changeTimweTransactionsPerPage(perPage) {
      timweTransactionsPerPage = parseInt(perPage);
      currentTimweTransactionsPage = 1;
      renderTimweTransactionsTable();
    }

    function sortTimweTransactions(columnIndex) {
      if (currentTimweTransactionsSortColumn === columnIndex) {
        timweTransactionsSortDirection = timweTransactionsSortDirection === 'asc' ? 'desc' : 'asc';
      } else {
        currentTimweTransactionsSortColumn = columnIndex;
        timweTransactionsSortDirection = 'desc'; // Default to desc for numbers
      }
      
      filteredTimweTransactions.sort((a, b) => {
        let aVal, bVal;
        
        switch(columnIndex) {
          case 0: aVal = a.client_id || 0; bVal = b.client_id || 0; break;
          case 1: aVal = a.nb_transactions || 0; bVal = b.nb_transactions || 0; break;
          case 2: aVal = a.derniere_transaction_id || 0; bVal = b.derniere_transaction_id || 0; break;
          case 3: aVal = a.derniere_date || ''; bVal = b.derniere_date || ''; break;
          case 4: aVal = a.last_status || ''; bVal = b.last_status || ''; break;
          default: return 0;
        }
        
        if (typeof aVal === 'string') {
          return timweTransactionsSortDirection === 'asc' ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
        } else {
          return timweTransactionsSortDirection === 'asc' ? aVal - bVal : bVal - aVal;
        }
      });
      
      currentTimweTransactionsPage = 1;
      renderTimweTransactionsTable();
    }

    function filterTimweTransactions() {
      const searchInput = document.getElementById('timweTransactionsSearch');
      if (!searchInput) return;
      
      const searchTerm = searchInput.value.toLowerCase();
      
      if (!searchTerm) {
        filteredTimweTransactions = allTimweTransactions;
      } else {
        filteredTimweTransactions = allTimweTransactions.filter(row => {
          return (
            String(row.client_id || '').toLowerCase().includes(searchTerm) ||
            String(row.derniere_transaction_id || '').toLowerCase().includes(searchTerm) ||
            String(row.last_status || '').toLowerCase().includes(searchTerm) ||
            String(row.derniere_date || '').toLowerCase().includes(searchTerm)
          );
        });
      }
      
      currentTimweTransactionsPage = 1;
      renderTimweTransactionsTable();
    }

    function exportTimweTransactionsToExcel() {
      if (!allTimweTransactions || allTimweTransactions.length === 0) {
        alert('Aucune donnée à exporter');
        return;
      }
      
      let csv = 'Client ID,Nb Transactions,Dernière Transaction,Dernière Date,Statut\n';
      
      allTimweTransactions.forEach(row => {
        csv += `${row.client_id || ''},${row.nb_transactions || 0},${row.derniere_transaction_id || ''},${row.derniere_date || ''},${row.last_status || ''}\n`;
      });
      
      const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
      const link = document.createElement('a');
      const url = URL.createObjectURL(blob);
      link.setAttribute('href', url);
      link.setAttribute('download', `timwe_transactions_par_utilisateur_${new Date().toISOString().split('T')[0]}.csv`);
      link.style.visibility = 'hidden';
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
    }

    function copyTimweTransactionsToClipboard() {
      if (!allTimweTransactions || allTimweTransactions.length === 0) {
        alert('Aucune donnée à copier');
        return;
      }
      
      let text = 'Client ID\tNb Transactions\tDernière Transaction\tDernière Date\tStatut\n';
      
      allTimweTransactions.forEach(row => {
        text += `${row.client_id || ''}\t${row.nb_transactions || 0}\t${row.derniere_transaction_id || ''}\t${row.derniere_date || ''}\t${row.last_status || ''}\n`;
      });
      
      navigator.clipboard.writeText(text).then(() => {
        alert('Données copiées dans le presse-papier !');
      }).catch(err => {
        console.error('Erreur lors de la copie:', err);
        alert('Erreur lors de la copie');
      });
    }

    // Variables globales pour le modal
    let currentClientTransactions = [];
    let currentModalClientId = null;
    let filteredModalTransactions = [];
    let modalSortColumn = 3; // Default: sort by date
    let modalSortDirection = 'desc';

    async function viewClientTimweTransactions(clientId) {
      currentModalClientId = clientId;
      
      // Afficher le modal
      const modal = document.getElementById('clientTransactionsModal');
      modal.style.display = 'block';
      
      // Mettre à jour le client ID
      document.getElementById('modalClientId').textContent = clientId;
      
      // Réinitialiser la table
      document.getElementById('modalTransactionsTableBody').innerHTML = `
        <tr>
          <td colspan="5" style="text-align: center; padding: 40px;">
            <i class="fas fa-spinner fa-spin"></i> Chargement des transactions...
          </td>
        </tr>
      `;
      
      try {
        // Récupérer les dates de la période sélectionnée
        const startDate = document.getElementById('start-date')?.value || '';
        const endDate = document.getElementById('end-date')?.value || '';
        
        // Appeler l'API pour récupérer les transactions du client
        const response = await fetch(`/api/timwe-client-transactions/${clientId}?start_date=${startDate}&end_date=${endDate}`, {
          method: 'GET',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          },
          credentials: 'include'
        });
        
        if (!response.ok) {
          const errorText = await response.text();
          console.error('Erreur API:', errorText);
          throw new Error(`Erreur ${response.status}: ${response.statusText}`);
        }
        
        const data = await response.json();
        
        if (data.success) {
          currentClientTransactions = data.transactions || [];
          filteredModalTransactions = currentClientTransactions;
          
          // Mettre à jour les stats
          updateModalClientStats(data.stats);
          
          // Afficher les transactions
          renderModalTransactions();
        } else {
          throw new Error(data.message || 'Erreur inconnue');
        }
        
      } catch (error) {
        console.error('Erreur:', error);
        document.getElementById('modalTransactionsTableBody').innerHTML = `
          <tr>
            <td colspan="5" style="text-align: center; padding: 40px; color: #ef4444;">
              <i class="fas fa-exclamation-triangle"></i> Erreur lors du chargement des transactions: ${error.message}
            </td>
          </tr>
        `;
      }
    }

    function updateModalClientStats(stats) {
      const statsDiv = document.getElementById('modalClientStats');
      
      const totalTransactions = stats.total_transactions || 0;
      const renewals = stats.renewals || 0;
      const unsubscriptions = stats.unsubscriptions || 0;
      const facture = stats.facture || 0;
      const tentativeNB = stats.tentative_nb || 0;
      const tentative = stats.tentative || 0;
      const firstTransaction = stats.first_transaction_date ? new Date(stats.first_transaction_date).toLocaleDateString('fr-FR') : '-';
      const lastTransaction = stats.last_transaction_date ? new Date(stats.last_transaction_date).toLocaleDateString('fr-FR') : '-';
      
      statsDiv.innerHTML = `
        <div style="background: white; padding: 15px; border-radius: 6px; border-left: 4px solid #667eea;">
          <div style="color: #6b7280; font-size: 12px; margin-bottom: 5px;">Total Transactions</div>
          <div style="font-size: 24px; font-weight: 600; color: #111827;">${totalTransactions}</div>
        </div>
        <div style="background: white; padding: 15px; border-radius: 6px; border-left: 4px solid #10b981;">
          <div style="color: #6b7280; font-size: 12px; margin-bottom: 5px;">✅ Facturé</div>
          <div style="font-size: 24px; font-weight: 600; color: #059669;">${facture}</div>
        </div>
        <div style="background: white; padding: 15px; border-radius: 6px; border-left: 4px solid #f59e0b;">
          <div style="color: #6b7280; font-size: 12px; margin-bottom: 5px;">⚠️ Tentative NB</div>
          <div style="font-size: 24px; font-weight: 600; color: #d97706;">${tentativeNB}</div>
        </div>
        <div style="background: white; padding: 15px; border-radius: 6px; border-left: 4px solid #6b7280;">
          <div style="color: #6b7280; font-size: 12px; margin-bottom: 5px;">🔄 Tentative</div>
          <div style="font-size: 24px; font-weight: 600; color: #4b5563;">${tentative}</div>
        </div>
        <div style="background: white; padding: 15px; border-radius: 6px; border-left: 4px solid #3b82f6;">
          <div style="color: #6b7280; font-size: 12px; margin-bottom: 5px;">🔄 Renouvellements</div>
          <div style="font-size: 18px; font-weight: 600; color: #2563eb;">${renewals}</div>
        </div>
        <div style="background: white; padding: 15px; border-radius: 6px; border-left: 4px solid #ef4444;">
          <div style="color: #6b7280; font-size: 12px; margin-bottom: 5px;">❌ Désabonnements</div>
          <div style="font-size: 18px; font-weight: 600; color: #dc2626;">${unsubscriptions}</div>
        </div>
        <div style="background: white; padding: 15px; border-radius: 6px; border-left: 4px solid #8b5cf6;">
          <div style="color: #6b7280; font-size: 12px; margin-bottom: 5px;">📅 Première</div>
          <div style="font-size: 13px; font-weight: 600; color: #7c3aed;">${firstTransaction}</div>
        </div>
        <div style="background: white; padding: 15px; border-radius: 6px; border-left: 4px solid #ec4899;">
          <div style="color: #6b7280; font-size: 12px; margin-bottom: 5px;">📅 Dernière</div>
          <div style="font-size: 13px; font-weight: 600; color: #db2777;">${lastTransaction}</div>
        </div>
      `;
    }

    function renderModalTransactions() {
      const tbody = document.getElementById('modalTransactionsTableBody');
      
      if (!filteredModalTransactions || filteredModalTransactions.length === 0) {
        tbody.innerHTML = `
          <tr>
            <td colspan="8" style="text-align: center; padding: 40px; color: #6b7280;">
              <i class="fas fa-inbox"></i> Aucune transaction trouvée
            </td>
          </tr>
        `;
        return;
      }
      
      tbody.innerHTML = filteredModalTransactions.map(tx => {
        const transactionId = tx.transaction_history_id || '-';
        const reference = tx.reference || '-';
        const status = tx.status || '-';
        const date = tx.created_at ? new Date(tx.created_at).toLocaleString('fr-FR') : '-';
        
        // Badge de statut original
        let statusBadge = '';
        if (status.includes('RENEWED')) {
          statusBadge = '<span style="padding: 4px 12px; background: #dbeafe; color: #1e40af; border-radius: 12px; font-size: 11px; font-weight: 600;">🔄 RENOUVELÉ</span>';
        } else if (status.includes('UNSUBSCRIPTION')) {
          statusBadge = '<span style="padding: 4px 12px; background: #fee2e2; color: #991b1b; border-radius: 12px; font-size: 11px; font-weight: 600;">❌ DÉSABONNÉ</span>';
        } else {
          statusBadge = '<span style="padding: 4px 12px; background: #f3f4f6; color: #374151; border-radius: 12px; font-size: 11px; font-weight: 600;">' + status + '</span>';
        }
        
        // Badge de statut de facturation
        let billingStatusBadge = '';
        const billingStatus = tx.billing_status || 'tentative';
        const billingLabel = tx.billing_status_label || 'Tentative';
        
        if (billingStatus === 'facture') {
          billingStatusBadge = '<span style="padding: 4px 12px; background: #d1fae5; color: #065f46; border-radius: 12px; font-size: 11px; font-weight: 600;">✅ FACTURÉ</span>';
        } else if (billingStatus === 'tentative_nb') {
          billingStatusBadge = '<span style="padding: 4px 12px; background: #fef3c7; color: #92400e; border-radius: 12px; font-size: 11px; font-weight: 600;">⚠️ TENTATIVE NB</span>';
        } else {
          billingStatusBadge = '<span style="padding: 4px 12px; background: #e5e7eb; color: #374151; border-radius: 12px; font-size: 11px; font-weight: 600;">🔄 TENTATIVE</span>';
        }
        
        // Delivery Code
        const deliveryCode = tx.mno_delivery_code || '-';
        let deliveryCodeBadge = '';
        if (deliveryCode === 'DELIVERED') {
          deliveryCodeBadge = '<span style="padding: 4px 8px; background: #d1fae5; color: #065f46; border-radius: 8px; font-size: 10px; font-weight: 600;">DELIVERED</span>';
        } else if (deliveryCode === 'NO_BALANCE') {
          deliveryCodeBadge = '<span style="padding: 4px 8px; background: #fef3c7; color: #92400e; border-radius: 8px; font-size: 10px; font-weight: 600;">NO_BALANCE</span>';
        } else if (deliveryCode === '-') {
          deliveryCodeBadge = '<span style="padding: 4px 8px; background: #f3f4f6; color: #6b7280; border-radius: 8px; font-size: 10px;">-</span>';
        } else {
          deliveryCodeBadge = '<span style="padding: 4px 8px; background: #e0e7ff; color: #3730a3; border-radius: 8px; font-size: 10px; font-weight: 600;">' + deliveryCode + '</span>';
        }
        
        // Montant
        const amount = tx.total_charged || 0;
        const amountDisplay = amount > 0 
          ? '<span style="color: #059669; font-weight: 600;">' + amount.toFixed(3) + ' TND</span>'
          : '<span style="color: #6b7280;">0.000 TND</span>';
        
        return `
          <tr>
            <td style="padding: 12px; border-bottom: 1px solid #e5e7eb;">
              <strong style="color: #111827;">#${transactionId}</strong>
            </td>
            <td style="padding: 12px; border-bottom: 1px solid #e5e7eb;">
              <code style="background: #f3f4f6; padding: 4px 8px; border-radius: 4px; font-size: 12px;">${reference}</code>
            </td>
            <td style="padding: 12px; border-bottom: 1px solid #e5e7eb;">
              ${statusBadge}
            </td>
            <td style="padding: 12px; border-bottom: 1px solid #e5e7eb;">
              ${billingStatusBadge}
            </td>
            <td style="padding: 12px; border-bottom: 1px solid #e5e7eb;">
              ${deliveryCodeBadge}
            </td>
            <td style="padding: 12px; border-bottom: 1px solid #e5e7eb; text-align: right;">
              ${amountDisplay}
            </td>
            <td style="padding: 12px; border-bottom: 1px solid #e5e7eb; color: #6b7280;">
              <i class="fas fa-clock" style="margin-right: 5px;"></i>${date}
            </td>
            <td style="padding: 12px; border-bottom: 1px solid #e5e7eb; text-align: center;">
              <button onclick="viewTransactionDetails(${transactionId}, '${encodeURIComponent(JSON.stringify(tx.result_details || {}))}' )" style="padding: 6px 12px; background: #667eea; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px;">
                <i class="fas fa-eye"></i> Voir
              </button>
            </td>
          </tr>
        `;
      }).join('');
    }

    function filterModalTransactions() {
      const searchTerm = document.getElementById('modalTransactionsSearch')?.value.toLowerCase() || '';
      const statusFilter = document.getElementById('modalStatusFilter')?.value || '';
      const billingFilter = document.getElementById('modalBillingFilter')?.value || '';
      
      filteredModalTransactions = currentClientTransactions.filter(tx => {
        const matchesSearch = !searchTerm || 
          (tx.reference && tx.reference.toLowerCase().includes(searchTerm)) ||
          (tx.status && tx.status.toLowerCase().includes(searchTerm)) ||
          (tx.transaction_history_id && String(tx.transaction_history_id).includes(searchTerm)) ||
          (tx.mno_delivery_code && tx.mno_delivery_code.toLowerCase().includes(searchTerm));
        
        const matchesStatus = !statusFilter || 
          (statusFilter === 'RENEWED' && tx.status.includes('RENEWED')) ||
          (statusFilter === 'UNSUBSCRIPTION' && tx.status.includes('UNSUBSCRIPTION'));
        
        const matchesBilling = !billingFilter || tx.billing_status === billingFilter;
        
        return matchesSearch && matchesStatus && matchesBilling;
      });
      
      renderModalTransactions();
    }

    function sortModalTransactions(columnIndex) {
      if (modalSortColumn === columnIndex) {
        modalSortDirection = modalSortDirection === 'asc' ? 'desc' : 'asc';
      } else {
        modalSortColumn = columnIndex;
        modalSortDirection = 'asc';
      }
      
      filteredModalTransactions.sort((a, b) => {
        let aVal, bVal;
        
        switch(columnIndex) {
          case 0: aVal = a.transaction_history_id || 0; bVal = b.transaction_history_id || 0; break;
          case 1: aVal = a.reference || ''; bVal = b.reference || ''; break;
          case 2: aVal = a.status || ''; bVal = b.status || ''; break;
          case 3: aVal = a.billing_status || ''; bVal = b.billing_status || ''; break;
          case 4: aVal = a.mno_delivery_code || ''; bVal = b.mno_delivery_code || ''; break;
          case 5: aVal = a.total_charged || 0; bVal = b.total_charged || 0; break;
          case 6: aVal = a.created_at || ''; bVal = b.created_at || ''; break;
          default: return 0;
        }
        
        if (typeof aVal === 'string') {
          return modalSortDirection === 'asc' ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
        } else {
          return modalSortDirection === 'asc' ? aVal - bVal : bVal - aVal;
        }
      });
      
      renderModalTransactions();
    }

    function exportClientTransactions() {
      if (!currentClientTransactions || currentClientTransactions.length === 0) {
        alert('Aucune transaction à exporter');
        return;
      }
      
      let csv = 'Transaction ID,Référence,Statut Original,Statut Facturation,Delivery Code,Montant (TND),Date\n';
      
      currentClientTransactions.forEach(tx => {
        const transactionId = tx.transaction_history_id || '';
        const reference = tx.reference || '';
        const status = tx.status || '';
        const billingStatus = tx.billing_status_label || '';
        const deliveryCode = tx.mno_delivery_code || '';
        const amount = tx.total_charged || 0;
        const date = tx.created_at || '';
        
        csv += `${transactionId},"${reference}","${status}","${billingStatus}","${deliveryCode}",${amount},"${date}"\n`;
      });
      
      const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
      const link = document.createElement('a');
      const url = URL.createObjectURL(blob);
      link.setAttribute('href', url);
      link.setAttribute('download', `timwe_client_${currentModalClientId}_transactions_${new Date().toISOString().split('T')[0]}.csv`);
      link.style.visibility = 'hidden';
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
    }

    function closeClientTransactionsModal() {
      document.getElementById('clientTransactionsModal').style.display = 'none';
      currentClientTransactions = [];
      currentModalClientId = null;
      filteredModalTransactions = [];
    }

    function viewTransactionDetails(transactionId, resultDetailsEncoded) {
      try {
        const resultDetails = JSON.parse(decodeURIComponent(resultDetailsEncoded));
        
        // Formater le JSON pour affichage
        const jsonFormatted = JSON.stringify(resultDetails, null, 2);
        
        // Créer le contenu HTML
        let htmlContent = '<div style="padding: 20px;">';
        htmlContent += '<h3 style="margin-top: 0; color: #111827;">Détails de la Transaction #' + transactionId + '</h3>';
        
        if (resultDetails && Object.keys(resultDetails).length > 0) {
          htmlContent += '<div style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 15px; margin-top: 15px;">';
          htmlContent += '<h4 style="margin: 0 0 10px 0; color: #374151;">Détails Result (JSON)</h4>';
          htmlContent += '<pre style="background: #1f2937; color: #f9fafb; padding: 15px; border-radius: 6px; overflow-x: auto; font-size: 13px; line-height: 1.6;">' + jsonFormatted + '</pre>';
          htmlContent += '</div>';
          
          // Afficher les champs importants
          if (resultDetails.mnoDeliveryCode || resultDetails.totalCharged !== undefined) {
            htmlContent += '<div style="margin-top: 20px; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">';
            
            if (resultDetails.mnoDeliveryCode) {
              htmlContent += '<div style="background: white; padding: 15px; border: 1px solid #e5e7eb; border-radius: 8px;">';
              htmlContent += '<div style="font-size: 12px; color: #6b7280; margin-bottom: 5px;">Delivery Code</div>';
              htmlContent += '<div style="font-size: 18px; font-weight: 600; color: #111827;">' + resultDetails.mnoDeliveryCode + '</div>';
              htmlContent += '</div>';
            }
            
            if (resultDetails.totalCharged !== undefined) {
              htmlContent += '<div style="background: white; padding: 15px; border: 1px solid #e5e7eb; border-radius: 8px;">';
              htmlContent += '<div style="font-size: 12px; color: #6b7280; margin-bottom: 5px;">Montant Chargé</div>';
              htmlContent += '<div style="font-size: 18px; font-weight: 600; color: ' + (resultDetails.totalCharged > 0 ? '#059669' : '#6b7280') + ';">' + resultDetails.totalCharged + ' TND</div>';
              htmlContent += '</div>';
            }
            
            htmlContent += '</div>';
          }
        } else {
          htmlContent += '<div style="padding: 40px; text-align: center; color: #6b7280; background: #f9fafb; border-radius: 8px; margin-top: 15px;">';
          htmlContent += '<i class="fas fa-info-circle" style="font-size: 48px; margin-bottom: 15px; opacity: 0.5;"></i><br>';
          htmlContent += 'Aucun détail result disponible pour cette transaction';
          htmlContent += '</div>';
        }
        
        htmlContent += '</div>';
        
        // Utiliser SweetAlert si disponible, sinon alert simple
        if (typeof Swal !== 'undefined') {
          Swal.fire({
            html: htmlContent,
            width: '800px',
            showCloseButton: true,
            showConfirmButton: true,
            confirmButtonText: 'Fermer',
            confirmButtonColor: '#667eea'
          });
        } else {
          // Fallback: créer un modal simple
          const modalHtml = `
            <div id="detailsModal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10001; display: flex; align-items: center; justify-content: center;">
              <div style="background: white; max-width: 800px; max-height: 80vh; overflow-y: auto; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
                ${htmlContent}
                <div style="padding: 0 20px 20px;">
                  <button onclick="document.getElementById('detailsModal').remove()" style="width: 100%; padding: 12px; background: #667eea; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 600;">
                    Fermer
                  </button>
                </div>
              </div>
            </div>
          `;
          document.body.insertAdjacentHTML('beforeend', modalHtml);
        }
        
      } catch (error) {
        console.error('Erreur lors du parsing des détails:', error);
        alert('Erreur lors de l\'affichage des détails de la transaction');
      }
    }

    // Fermer le modal en cliquant en dehors
    document.addEventListener('click', function(event) {
      const modal = document.getElementById('clientTransactionsModal');
      if (event.target === modal) {
        closeClientTransactionsModal();
      }
    });
    */
    // FIN DÉSACTIVATION - Toutes les fonctions Timwe Transactions sont commentées ci-dessus

    // ========== OOREDOO/DGV FUNCTIONS ==========
    let allOoredooMonthlyStats = [];
    let expandedOoredooMonths = new Set();
    let currentOoredooStatsSortColumn = 0;
    let ooredooStatsSortDirection = 'asc';

    function calculateOoredooTotals(monthlyStats) {
      if (!monthlyStats || monthlyStats.length === 0) {
        return {
          newSubs: 0,
          unsubs: 0,
          billings: 0,
          activeSubsEndOfPeriod: 0,
          revenueTnd: 0
        };
      }
      
      const totals = {
        newSubs: 0,
        unsubs: 0,
        billings: 0,
        activeSubsEndOfPeriod: 0,
        revenueTnd: 0
      };
      
      // Sommer les totaux mensuels
      monthlyStats.forEach(month => {
        totals.newSubs += Number(month.total_new_sub) || 0;
        totals.unsubs += Number(month.total_unsub) || 0;
        totals.billings += Number(month.total_nb_facturation) || 0;
        totals.revenueTnd += Number(month.total_revenu_tnd) || 0;
      });
      
      // Active Subs = valeur du DERNIER mois de la période
      const lastMonth = monthlyStats[0]; // Le premier dans l'ordre décroissant
      totals.activeSubsEndOfPeriod = lastMonth ? (Number(lastMonth.total_active_sub) || 0) : 0;
      
      return totals;
    }

    function updateOoredooKPIs(data) {
      console.log('🔍 [OOREDOO] Mise à jour des KPIs:', data);
      console.log('🔍 [OOREDOO] ooredoo_stats:', data?.ooredoo_stats);
      console.log('🔍 [OOREDOO] monthly_stats:', data?.ooredoo_stats?.ooredoo_monthly_stats);
      console.log('🔍 [OOREDOO] monthly_stats_comparison:', data?.ooredoo_stats?.ooredoo_monthly_stats_comparison);
      
      if (!data || !data.ooredoo_stats) {
        console.warn('⚠️ [OOREDOO] Données manquantes');
        return;
      }
      
      // Récupérer les statistiques mensuelles groupées Ooredoo
      if (data.ooredoo_stats.ooredoo_monthly_stats) {
        updateOoredooStatisticsTable(data.ooredoo_stats.ooredoo_monthly_stats);
        
        // Calculer les KPIs agrégés avec comparaison
        const monthlyStats = data.ooredoo_stats.ooredoo_monthly_stats || [];
        const monthlyStatsComparison = data.ooredoo_stats.ooredoo_monthly_stats_comparison || [];
        
        const totals = calculateOoredooTotals(monthlyStats);
        const comparisonTotals = monthlyStatsComparison.length > 0 
          ? calculateOoredooTotals(monthlyStatsComparison) 
          : null;
        
        console.log('🔍 [OOREDOO] Statistiques:', {
          current_months: monthlyStats.length,
          comparison_months: monthlyStatsComparison.length,
          totals: totals,
          comparisonTotals: comparisonTotals
        });
        
        // Helper pour créer un objet KPI avec ou sans comparaison
        const makeKPI = (current, previous, decimals = 0) => {
          const currentNum = Number(current) || 0;
          const previousNum = Number(previous) || 0;
          
          if (previous === null || previous === undefined || previousNum === 0) {
            return { 
              current: formatNumber(currentNum, decimals), 
              previous: 0, 
              change: 0 
            };
          }
          return {
            current: formatNumber(currentNum, decimals),
            previous: formatNumber(previousNum, decimals),
            change: calculateChange(currentNum, previousNum)
          };
        };
        
        // Taux de Facturation
        const billingRateCurrent = totals.activeSubsEndOfPeriod > 0 
          ? (totals.billings / totals.activeSubsEndOfPeriod * 100) 
          : 0;
        const billingRatePrevious = comparisonTotals && comparisonTotals.activeSubsEndOfPeriod > 0
          ? (comparisonTotals.billings / comparisonTotals.activeSubsEndOfPeriod * 100)
          : null;
        updateKPI('ooredoo-billing-rate', makeKPI(billingRateCurrent, billingRatePrevious, 2), '%');
        
        // Total Facturations
        updateKPI('ooredoo-total-billings', makeKPI(totals.billings, comparisonTotals?.billings, 0));
        
        // Active Subs: pas de delta (valeur à la fin de période)
        updateKPI('ooredoo-active-subs', {
          current: formatNumber(totals.activeSubsEndOfPeriod, 0),
          previous: 0,
          change: 0
        });
        
        // Nouveaux Abonnements
        updateKPI('ooredoo-new-subscriptions', makeKPI(totals.newSubs, comparisonTotals?.newSubs, 0));
        
        // Désabonnements
        updateKPI('ooredoo-unsubscriptions', makeKPI(totals.unsubs, comparisonTotals?.unsubs, 0));
        
        // Revenu Total TND
        updateKPI('ooredoo-revenue-tnd', makeKPI(totals.revenueTnd, comparisonTotals?.revenueTnd, 3), ' TND');
        
        // ARPU: pas de delta (calcul global)
        // Calculer le nombre de jours de la période pour normaliser l'ARPU
        const startDate = document.getElementById('start-date')?.value;
        const endDate = document.getElementById('end-date')?.value;
        let periodDays = 30; // Défaut
        if (startDate && endDate) {
          const start = new Date(startDate);
          const end = new Date(endDate);
          periodDays = Math.ceil((end - start) / (1000 * 60 * 60 * 24)) || 30;
        }
        const arpuValue = totals.activeSubsEndOfPeriod > 0 
          ? (totals.revenueTnd / totals.activeSubsEndOfPeriod) * (30 / periodDays)
          : 0;
        updateKPI('ooredoo-arpu', {
          current: formatNumber(arpuValue, 3),
          previous: 0,
          change: 0
        }, ' TND');
        
        // Revenu Moyen/Facturation: pas de delta (calcul global)
        const avgBillingValue = totals.billings > 0 
          ? totals.revenueTnd / totals.billings
          : 0;
        updateKPI('ooredoo-avg-billing-revenue', {
          current: formatNumber(avgBillingValue, 3),
          previous: 0,
          change: 0
        }, ' TND');
      } else {
        console.warn('⚠️ [OOREDOO] Pas de ooredoo_monthly_stats dans les données');
      }
    }

    function updateOoredooStatisticsTable(monthlyStats) {
      const tbody = document.getElementById('ooredooStatsTableBody');
      if (!tbody) return;
      
      if (!monthlyStats || monthlyStats.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="no-data" style="text-align: center; padding: 40px; color: var(--muted);">Aucune donnée disponible</td></tr>';
        return;
      }
      
      allOoredooMonthlyStats = monthlyStats;
      renderOoredooStatisticsTable();
    }

    function renderOoredooStatisticsTable() {
      const tbody = document.getElementById('ooredooStatsTableBody');
      if (!tbody) return;
      
      if (!allOoredooMonthlyStats || allOoredooMonthlyStats.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="no-data" style="text-align: center; padding: 40px; color: var(--muted);">Aucune donnée disponible</td></tr>';
        return;
      }
      
      let html = '';
      
      allOoredooMonthlyStats.forEach((month, idx) => {
        const isExpanded = expandedOoredooMonths.has(month.month_key);
        const expandIcon = isExpanded ? '▼' : '▶';
        
        // Ligne du mois (cliquable)
        html += `
          <tr style="background: var(--card); border-bottom: 2px solid var(--border); cursor: pointer; font-weight: 600;" 
              onclick="toggleOoredooMonth('${month.month_key}')">
            <td style="padding: 12px; text-align: center;">${expandIcon}</td>
            <td style="padding: 12px;">${month.display_label}</td>
            <td style="padding: 12px; text-align: center;">${formatNumber(month.total_new_sub, 0)}</td>
            <td style="padding: 12px; text-align: center;">${formatNumber(month.total_unsub, 0)}</td>
            <td style="padding: 12px; text-align: center;">${formatNumber(month.total_active_sub, 0)}</td>
            <td style="padding: 12px; text-align: center;">${formatNumber(month.total_nb_facturation, 0)}</td>
            <td style="padding: 12px; text-align: center;">${formatPercentage(month.total_taux_facturation, 3)}</td>
            <td style="padding: 12px; text-align: center;">${formatNumber(month.total_revenu_tnd, 3)} TND</td>
          </tr>
        `;
        
        // Lignes des détails quotidiens (affichées seulement si le mois est expandé)
        if (isExpanded && month.daily_details && month.daily_details.length > 0) {
          month.daily_details.forEach(day => {
            html += `
              <tr style="background: rgba(0,0,0,0.02); border-bottom: 1px solid var(--border);">
                <td style="padding: 8px;"></td>
                <td style="padding: 8px; padding-left: 30px; font-size: 13px;">${day.stat_date}</td>
                <td style="padding: 8px; text-align: center; font-size: 13px;">${formatNumber(day.new_subscriptions || 0, 0)}</td>
                <td style="padding: 8px; text-align: center; font-size: 13px;">${formatNumber(day.unsubscriptions || 0, 0)}</td>
                <td style="padding: 8px; text-align: center; font-size: 13px;">${formatNumber(day.active_subscriptions || 0, 0)}</td>
                <td style="padding: 8px; text-align: center; font-size: 13px;">${formatNumber(day.total_billings || 0, 0)}</td>
                <td style="padding: 8px; text-align: center; font-size: 13px;">${formatPercentage(day.billing_rate || 0, 3)}</td>
                <td style="padding: 8px; text-align: center; font-size: 13px;">${formatNumber(day.revenue_tnd || 0, 3)} TND</td>
              </tr>
            `;
          });
        }
      });
      
      tbody.innerHTML = html;
    }
    
    function toggleOoredooMonth(monthKey) {
      if (expandedOoredooMonths.has(monthKey)) {
        expandedOoredooMonths.delete(monthKey);
      } else {
        expandedOoredooMonths.add(monthKey);
      }
      renderOoredooStatisticsTable();
    }

    function sortOoredooStatistics(columnIndex) {
      // TODO: Implement sorting for monthly stats if needed
      console.log('Sort Ooredoo column:', columnIndex);
    }

    function filterOoredooStats() {
      // TODO: Implement filtering for monthly stats if needed
      console.log('Filter Ooredoo stats');
    }

    function exportOoredooStatsToExcel() {
      if (!allOoredooMonthlyStats || allOoredooMonthlyStats.length === 0) {
        alert('Aucune donnée à exporter');
        return;
      }
      
      let csv = 'Période,New Sub,Unsub,Active Sub,NB Facturation,Taux Facturation %,Revenu TND\n';
      
      allOoredooMonthlyStats.forEach(month => {
        csv += `${month.display_label},${month.total_new_sub || 0},${month.total_unsub || 0},${month.total_active_sub || 0},${month.total_nb_facturation || 0},${month.total_taux_facturation || 0},${month.total_revenu_tnd || 0}\n`;
      });
      
      const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
      const link = document.createElement('a');
      const url = URL.createObjectURL(blob);
      link.setAttribute('href', url);
      link.setAttribute('download', `ooredoo_statistiques_${new Date().toISOString().split('T')[0]}.csv`);
      link.style.visibility = 'hidden';
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
    }

    function copyOoredooStatsToClipboard() {
      if (!allOoredooMonthlyStats || allOoredooMonthlyStats.length === 0) {
        alert('Aucune donnée à copier');
        return;
      }
      
      let text = 'Période\tNew Sub\tUnsub\tActive Sub\tNB Facturation\tTaux Facturation %\tRevenu TND\n';
      
      allOoredooMonthlyStats.forEach(month => {
        text += `${month.display_label}\t${month.total_new_sub || 0}\t${month.total_unsub || 0}\t${month.total_active_sub || 0}\t${month.total_nb_facturation || 0}\t${month.total_taux_facturation || 0}\t${month.total_revenu_tnd || 0}\n`;
      });
      
      navigator.clipboard.writeText(text).then(() => {
        alert('Données copiées dans le presse-papier !');
      }).catch(err => {
        console.error('Erreur lors de la copie:', err);
        alert('Erreur lors de la copie');
      });
    }

    // Update merchants table with enhanced data and pagination
    function updateMerchantsTable(merchants) {
      allMerchants = merchants || [];
      currentMerchantsPage = 1;
      
      if (!allMerchants || allMerchants.length === 0) {
        const tbody = document.getElementById('merchantsTableBody');
        if (tbody) {
          tbody.innerHTML = '<tr><td colspan="7" class="no-data" style="text-align: center; padding: 40px; color: var(--muted);">Aucun marchand disponible</td></tr>';
        }
        // Mettre à jour la pagination
        document.getElementById('merchantsPaginationInfo').textContent = 'Affichage de 0-0 sur 0 marchands';
        document.getElementById('merchantsPrevBtn').disabled = true;
        document.getElementById('merchantsNextBtn').disabled = true;
        return;
      }
      
      renderMerchantsPage();
    }

    // Variables globales pour la pagination des abonnements
    let allSubscriptionDetails = [];
    let currentSubscriptionPage = 1;
    let subscriptionsPerPage = 25;

    // Update subscriptions table with details
    function updateSubscriptionsTable(subscriptions) {
      const tbody = document.getElementById('subs-details-body');
      if (!tbody) return;
      
      // Afficher indicateur de chargement spécifique
      tbody.innerHTML = '<tr><td colspan="6" class="loading">🔄 Chargement des détails...</td></tr>';
      
      // Gestion de la nouvelle structure avec meta
      let detailsData = [];
      let meta = null;
      
      if (subscriptions && subscriptions.details) {
        if (Array.isArray(subscriptions.details)) {
          // Ancienne structure (compatibilité)
          detailsData = subscriptions.details;
        } else if (subscriptions.details.data && Array.isArray(subscriptions.details.data)) {
          // Nouvelle structure avec meta
          detailsData = subscriptions.details.data;
          meta = subscriptions.details.meta;
        } else if (subscriptions.details.data === undefined && Object.keys(subscriptions.details).length > 0) {
          // Si c'est un objet avec des propriétés mais pas de .data, peut-être que c'est déjà un tableau d'objets
          const testItem = subscriptions.details[0] || subscriptions.details;
          if (testItem && (testItem.first_name !== undefined || testItem.client_prenom !== undefined)) {
            detailsData = Array.isArray(subscriptions.details) ? subscriptions.details : [subscriptions.details];
          }
        }
      }
      
      // Si pas de données, afficher le message
      if (!detailsData || detailsData.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="no-data" style="text-align: center; padding: 40px; color: var(--muted);">Aucune donnée disponible</td></tr>';
        return;
      }
      
      // Simule un petit délai pour montrer le chargement
      setTimeout(() => {
        allSubscriptionDetails = detailsData;
        currentSubscriptionPage = 1;
        renderSubscriptionsPage();
        
        // Afficher les informations de performance
        if (meta) {
          updateSubscriptionTableInfo(meta);
        }
      }, 100);
    }

    function updateSubscriptionTableInfo(meta) {
      const tableTitle = document.querySelector('#subscriptions .table-title');
      if (tableTitle && meta) {
        const infoSpan = tableTitle.querySelector('.table-info') || document.createElement('span');
        infoSpan.className = 'table-info';
        infoSpan.innerHTML = ` <small style="color: #666; font-weight: normal;">(${meta.total_count} clients - ${meta.execution_time_ms}ms)</small>`;
        
        if (!tableTitle.querySelector('.table-info')) {
          tableTitle.appendChild(infoSpan);
        }
      }
    }

    function renderSubscriptionsPage() {
      const tbody = document.getElementById('subs-details-body');
      if (!tbody) return;
      
      const startIndex = (currentSubscriptionPage - 1) * subscriptionsPerPage;
      const endIndex = startIndex + subscriptionsPerPage;
      const pageData = allSubscriptionDetails.slice(startIndex, endIndex);
      
      if (!pageData || pageData.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="no-data" style="text-align: center; padding: 40px; color: var(--muted);">Aucune donnée disponible</td></tr>';
        return;
      }
      
      tbody.innerHTML = pageData.map(row => {
        // Gérer différents formats de données (objets Laravel ou tableaux associatifs)
        const firstName = row.first_name || row.client_prenom || '';
        const lastName = row.last_name || row.client_nom || '';
        const fullName = `${firstName} ${lastName}`.trim() || '-';
        const phone = row.phone || row.client_telephone || '-';
        const operator = row.operator || row.country_payments_methods_name || '-';
        const plan = row.plan || '-';
        const clientId = row.client_id || null;
        const planBadgeClass = 
          plan === 'Trial' ? 'badge-primary' :
          plan === 'Journalier' ? 'badge-warning' :
          plan === 'Mensuel' ? 'badge-info' :
          plan === 'Annuel' ? 'badge-success' : 'badge-secondary';
        
        // Formater les dates
        const activationDate = row.activation_date || row.client_abonnement_creation || null;
        const endDate = row.end_date || row.client_abonnement_expiration || null;
        const formattedActivation = activationDate ? (typeof activationDate === 'string' ? activationDate.substring(0, 10) : activationDate) : '-';
        const formattedEnd = endDate ? (typeof endDate === 'string' ? endDate.substring(0, 10) : endDate) : '-';
        
        // Bouton détails (seulement si client_id est disponible)
        // Échapper les apostrophes dans le nom pour éviter les erreurs JavaScript
        const escapedName = fullName.replace(/'/g, "\\'").replace(/"/g, '&quot;');
        const detailsButton = clientId ? 
          `<button onclick="showUserSubscriptionsDetails(${clientId}, '${escapedName}')" class="btn-details" style="padding: 6px 12px; background: var(--accent); color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 13px; transition: all 0.2s; font-weight: 500;" onmouseover="this.style.background='var(--brand-primary)'" onmouseout="this.style.background='var(--accent)'">Détails</button>` :
          `<span style="color: var(--muted); font-size: 12px;">-</span>`;
        
        return `
          <tr>
            <td>${fullName}</td>
            <td>${phone}</td>
            <td>${operator}</td>
            <td><span class="badge ${planBadgeClass}">${plan}</span></td>
            <td>${formattedActivation}</td>
            <td>${formattedEnd}</td>
            <td>${detailsButton}</td>
          </tr>
        `;
      }).join('');
      
      updateSubscriptionsPagination();
    }

    function updateSubscriptionsPagination() {
      const totalPages = Math.ceil(allSubscriptionDetails.length / subscriptionsPerPage);
      const pagination = document.querySelector('.subscriptions-pagination');
      
      if (pagination && totalPages > 1) {
        let paginationHTML = '<div class="pagination-controls">';
        
        // Previous button
        if (currentSubscriptionPage > 1) {
          paginationHTML += `<button onclick="changeSubscriptionPage(${currentSubscriptionPage - 1})" class="pagination-btn">‹ Précédent</button>`;
        }
        
        // Page numbers
        const startPage = Math.max(1, currentSubscriptionPage - 2);
        const endPage = Math.min(totalPages, currentSubscriptionPage + 2);
        
        for (let i = startPage; i <= endPage; i++) {
          const activeClass = i === currentSubscriptionPage ? 'active' : '';
          paginationHTML += `<button onclick="changeSubscriptionPage(${i})" class="pagination-btn ${activeClass}">${i}</button>`;
        }
        
        // Next button
        if (currentSubscriptionPage < totalPages) {
          paginationHTML += `<button onclick="changeSubscriptionPage(${currentSubscriptionPage + 1})" class="pagination-btn">Suivant ›</button>`;
        }
        
        paginationHTML += `</div><div class="pagination-info">Page ${currentSubscriptionPage} sur ${totalPages} (${allSubscriptionDetails.length} éléments)</div>`;
        pagination.innerHTML = paginationHTML;
      }
    }

    function changeSubscriptionPage(page) {
      currentSubscriptionPage = page;
      renderSubscriptionsPage();
    }

    function changeSubscriptionsPerPage(perPage) {
      subscriptionsPerPage = parseInt(perPage);
      currentSubscriptionPage = 1;
      renderSubscriptionsPage();
    }

    // Fonction pour afficher les détails des abonnements d'un utilisateur
    async function showUserSubscriptionsDetails(clientId, clientName) {
      // Supprimer la modale existante si elle existe
      const existing = document.getElementById('user-subscriptions-modal');
      if (existing) existing.remove();
      
      // Créer la modale avec indicateur de chargement
      const modal = document.createElement('div');
      modal.id = 'user-subscriptions-modal';
      modal.innerHTML = `
        <div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 10001; display: flex; align-items: center; justify-content: center;">
          <div style="background: white; border-radius: 12px; padding: 30px; max-width: 900px; max-height: 80vh; overflow-y: auto; width: 90%;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
              <h3 style="margin: 0; color: var(--brand-primary); font-size: 20px;">📋 Abonnements de ${clientName}</h3>
              <button onclick="document.getElementById('user-subscriptions-modal').remove()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: var(--muted); padding: 0; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;">×</button>
            </div>
            <div id="user-subscriptions-content" style="min-height: 200px;">
              <div style="text-align: center; padding: 40px; color: var(--muted);">
                <div style="margin-bottom: 10px;">🔄 Chargement des abonnements...</div>
              </div>
            </div>
          </div>
        </div>
      `;
      document.body.appendChild(modal);
      
      try {
        // Appeler l'API
        const response = await fetch(`/api/dashboard/subscriptions/${clientId}`, {
          method: 'GET',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
          }
        });
        
        const data = await response.json();
        const contentDiv = document.getElementById('user-subscriptions-content');
        
        if (!data.success || !data.subscriptions || data.subscriptions.length === 0) {
          contentDiv.innerHTML = `
            <div style="text-align: center; padding: 40px; color: var(--muted);">
              <div style="font-size: 48px; margin-bottom: 10px;">📭</div>
              <div>Aucun abonnement trouvé pour cet utilisateur</div>
            </div>
          `;
          return;
        }
        
        // Afficher les abonnements dans un tableau
        const subscriptions = data.subscriptions;
        const totalSubscriptions = data.total_subscriptions || subscriptions.length;
        
        let tableHTML = `
          <div style="margin-bottom: 15px; color: var(--muted); font-size: 14px;">
            Total: <strong>${totalSubscriptions}</strong> abonnement(s)
          </div>
          <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
            <thead>
              <tr style="background: var(--bg); border-bottom: 2px solid var(--border);">
                <th style="padding: 12px; text-align: left; font-weight: 600; color: var(--brand-dark);">Opérateur</th>
                <th style="padding: 12px; text-align: left; font-weight: 600; color: var(--brand-dark);">Plan</th>
                <th style="padding: 12px; text-align: left; font-weight: 600; color: var(--brand-dark);">Type</th>
                <th style="padding: 12px; text-align: left; font-weight: 600; color: var(--brand-dark);">Date Activation</th>
                <th style="padding: 12px; text-align: left; font-weight: 600; color: var(--brand-dark);">Date Fin</th>
                <th style="padding: 12px; text-align: left; font-weight: 600; color: var(--brand-dark);">Statut</th>
                <th style="padding: 12px; text-align: left; font-weight: 600; color: var(--brand-dark);">Prix</th>
              </tr>
            </thead>
            <tbody>
        `;
        
        subscriptions.forEach(sub => {
          const operator = sub.operator || '-';
          const plan = sub.plan || '-';
          const subscriptionType = sub.subscription_type || '-';
          const subscriptionName = sub.subscription_name || '-';
          const activationDate = sub.activation_date ? (typeof sub.activation_date === 'string' ? sub.activation_date.substring(0, 10) : sub.activation_date) : '-';
          const endDate = sub.end_date ? (typeof sub.end_date === 'string' ? sub.end_date.substring(0, 10) : sub.end_date) : '-';
          const status = sub.status || 'Inconnu';
          // ⭐ CORRECTION: Les plans Trial sont gratuits
          const price = (plan === 'Trial' || parseFloat(sub.price) === 0) 
            ? '<span style="color: var(--success); font-weight: 600;">Gratuit</span>' 
            : (sub.price ? parseFloat(sub.price).toFixed(2) + ' TND' : '-');
          
          const statusBadge = status === 'Actif' ? 
            '<span style="background: var(--success); color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px;">Actif</span>' :
            '<span style="background: var(--muted); color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px;">Expiré</span>';
          
          const planBadgeClass = 
            plan === 'Trial' ? 'var(--brand-primary)' :
            plan === 'Journalier' ? 'var(--warning)' :
            plan === 'Mensuel' ? 'var(--accent)' :
            plan === 'Annuel' ? 'var(--success)' : 'var(--muted)';
          
          tableHTML += `
            <tr style="border-bottom: 1px solid var(--border);">
              <td style="padding: 12px;">${operator}</td>
              <td style="padding: 12px;"><span style="background: ${planBadgeClass}; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px;">${plan}</span></td>
              <td style="padding: 12px;">${subscriptionName}</td>
              <td style="padding: 12px;">${activationDate}</td>
              <td style="padding: 12px;">${endDate}</td>
              <td style="padding: 12px;">${statusBadge}</td>
              <td style="padding: 12px;">${price}</td>
            </tr>
          `;
        });
        
        tableHTML += `
            </tbody>
          </table>
        `;
        
        contentDiv.innerHTML = tableHTML;
        
      } catch (error) {
        console.error('Erreur lors de la récupération des abonnements:', error);
        const contentDiv = document.getElementById('user-subscriptions-content');
        contentDiv.innerHTML = `
          <div style="text-align: center; padding: 40px; color: var(--danger);">
            <div style="font-size: 48px; margin-bottom: 10px;">⚠️</div>
            <div>Erreur lors du chargement des abonnements</div>
            <div style="font-size: 12px; margin-top: 10px; color: var(--muted);">${error.message}</div>
          </div>
        `;
      }
    }
    
    function renderMerchantsPage() {
      const tbody = document.getElementById('merchantsTableBody');
      if (!tbody) return;
      
      const startIndex = (currentMerchantsPage - 1) * merchantsPerPage;
      const endIndex = startIndex + merchantsPerPage;
      const pageData = allMerchants.slice(startIndex, endIndex);
      
      tbody.innerHTML = pageData.map((merchant, index) => {
        const globalIndex = startIndex + index;
        // Calcul du changement plus robuste
        let change = 0;
        let badgeClass = 'badge-info';
        let changeText = 'Nouveau';
        let statusClass = 'badge-success';
        let statusText = 'Actif';
        
        if (merchant.previous > 0) {
          change = ((merchant.current - merchant.previous) / merchant.previous * 100);
          const isPositive = change > 0;
          badgeClass = isPositive ? 'badge-success' : 'badge-danger';
          changeText = `${isPositive ? '+' : ''}${change.toFixed(1)}%`;
        } else if (merchant.current > 0) {
          badgeClass = 'badge-success';
          changeText = 'Nouveau';
        }
        
        // Déterminer le statut basé sur la performance
        if (merchant.current === 0) {
          statusClass = 'badge-danger';
          statusText = 'Inactif';
        } else if (change < -20) {
          statusClass = 'badge-warning';
          statusText = 'En baisse';
        } else if (change > 20) {
          statusClass = 'badge-success';
          statusText = 'En croissance';
        }
        
        const shareVal = (typeof merchant.share === 'number') ? merchant.share : 0;
        
        return `
          <tr>
            <td>
              <div style="display: flex; align-items: center; gap: 8px;">
                <span style="font-size: 16px;">${globalIndex < 3 ? '🏆' : globalIndex < 10 ? '⭐' : '📊'}</span>
                <div>
                  <strong>${merchant.name}</strong>
                  <div style="font-size: 12px; color: #666; margin-top: 2px;">
                    Position: #${globalIndex + 1}
                  </div>
                </div>
              </div>
            </td>
            <td>
              <span class="badge badge-info" style="background: #e0f2fe; color: #0277bd;">
                ${merchant.category}
              </span>
            </td>
            <td>
              <strong style="color: var(--brand-red);">${merchant.current.toLocaleString()}</strong>
            </td>
            <td>
              <span style="color: #666;">${merchant.previous.toLocaleString()}</span>
            </td>
            <td>
              <span class="badge ${badgeClass}">${changeText}</span>
            </td>
            <td>
              <div style="display: flex; align-items: center; gap: 8px;">
                <strong>${shareVal}%</strong>
                <div style="width: 60px; height: 4px; background: #e2e8f0; border-radius: 2px; overflow: hidden;">
                  <div style="width: ${Math.min(shareVal * 2, 100)}%; height: 100%; background: var(--brand-red);"></div>
                </div>
              </div>
            </td>
            <td>
              <span class="badge ${statusClass}">${statusText}</span>
            </td>
          </tr>
        `;
      }).join('');
      
      updateMerchantsPagination();
    }
    
    function updateMerchantsPagination() {
      const totalMerchants = allMerchants.length;
      const totalPages = Math.ceil(totalMerchants / merchantsPerPage);
      const startIndex = (currentMerchantsPage - 1) * merchantsPerPage + 1;
      const endIndex = Math.min(currentMerchantsPage * merchantsPerPage, totalMerchants);
      
      // Update pagination info
      const infoEl = document.getElementById('merchantsPaginationInfo');
      if (infoEl) {
        infoEl.textContent = `Affichage de ${startIndex}-${endIndex} sur ${totalMerchants} marchands`;
      }
      
      // Update page numbers
      const pageNumbersEl = document.getElementById('merchantsPageNumbers');
      if (pageNumbersEl) {
        pageNumbersEl.textContent = `Page ${currentMerchantsPage} sur ${totalPages}`;
      }
      
      // Update button states
      const prevBtn = document.getElementById('merchantsPrevBtn');
      const nextBtn = document.getElementById('merchantsNextBtn');
      
      if (prevBtn) {
        prevBtn.disabled = currentMerchantsPage <= 1;
        prevBtn.style.opacity = currentMerchantsPage <= 1 ? '0.5' : '1';
        prevBtn.style.cursor = currentMerchantsPage <= 1 ? 'not-allowed' : 'pointer';
      }
      
      if (nextBtn) {
        nextBtn.disabled = currentMerchantsPage >= totalPages;
        nextBtn.style.opacity = currentMerchantsPage >= totalPages ? '0.5' : '1';
        nextBtn.style.cursor = currentMerchantsPage >= totalPages ? 'not-allowed' : 'pointer';
      }
    }
    
    function changeMerchantsPerPage() {
      const select = document.getElementById('merchantsPerPage');
      merchantsPerPage = parseInt(select.value);
      currentMerchantsPage = 1;
      renderMerchantsPage();
    }
    
    function previousMerchantsPage() {
      if (currentMerchantsPage > 1) {
        currentMerchantsPage--;
        renderMerchantsPage();
      }
    }
    
    function nextMerchantsPage() {
      const totalPages = Math.ceil(allMerchants.length / merchantsPerPage);
      if (currentMerchantsPage < totalPages) {
        currentMerchantsPage++;
        renderMerchantsPage();
      }
    }

    // Add export function for merchants data
    function exportMerchantsData() {
      if (!dashboardData || !dashboardData.merchants) {
        showNotification('Aucune donnée à exporter', 'warning');
        return;
      }
      
      const csvContent = "data:text/csv;charset=utf-8," + 
        "Merchant,Category,Current,Previous,Change,Market Share,Status\n" +
        dashboardData.merchants.map(merchant => {
          const change = merchant.previous > 0 ? 
            ((merchant.current - merchant.previous) / merchant.previous * 100).toFixed(1) + '%' : 
            'Nouveau';
          return `"${merchant.name}","${merchant.category}",${merchant.current},${merchant.previous},"${change}",${merchant.share}%,"Active"`;
        }).join("\n");
      
      const encodedUri = encodeURI(csvContent);
      const link = document.createElement("a");
      link.setAttribute("href", encodedUri);
      link.setAttribute("download", `merchants_data_${new Date().toISOString().split('T')[0]}.csv`);
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      
      showNotification('Données exportées avec succès', 'success');
    }

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
        
        return `
          <tr>
            <td><strong>${metric.name}</strong></td>
            <td>${formatNumber(current)}</td>
            <td>${formatNumber(previous)}</td>
            <td>${absoluteChange > 0 ? '+' : ''}${formatNumber(absoluteChange)}</td>
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

    /* Agent IA Styles (ChatGPT-like avec Sidebar) */
    .ai-conversation-item {
      transition: all 0.2s;
      border: 1px solid transparent;
    }
    .ai-conversation-item:hover {
      background: #f3f4f6 !important;
      border-color: #d1d5db;
    }
    .ai-conversation-item.active {
      border-left-color: #6366f1 !important;
      background: white !important;
    }
    .ai-sidebar button:hover {
      opacity: 0.8;
      transform: translateY(-1px);
    }
    .ai-message-user {
      padding: 16px 24px;
      background: white;
      border-bottom: 1px solid #f0f0f0;
    }
    .ai-message-assistant {
      padding: 16px 24px; 
      background: #f7f7f8;
      border-bottom: 1px solid #f0f0f0;
    }
    .ai-message-content {
      max-width: 100%;
      line-height: 1.6;
      color: #374151;
    }
    .ai-message-user .ai-message-content {
      font-weight: 500;
    }
    .ai-suggestion-simple:hover {
      background: #e5e7eb !important;
      border-color: #d1d5db !important;
    }
    #aiSendBtn:hover {
      background: #4f46e5;
      transform: scale(1.05);
    }
    #aiSendBtn:disabled {
      background: #d1d5db;
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
      currentDiv.style.cssText = 'padding: 12px; margin: 4px 0; background: ' + (isCurrentActive ? 'white' : '#f9fafb') + '; border-radius: 8px; border-left: 3px solid ' + (isCurrentActive ? '#6366f1' : 'transparent') + '; cursor: pointer;';
      currentDiv.innerHTML = '<div style="display: flex; justify-content: space-between; align-items: center;"><div style="flex: 1; min-width: 0;" onclick="selectCurrentConversation()"><div style="font-size: 0.85rem; font-weight: 500; color: #374151;">' + currentTitle + '</div></div><button type="button" onclick="event.stopPropagation(); renameCurrentConversation();" style="background: #6366f1; border: none; color: white; padding: 4px 8px; border-radius: 4px; font-size: 0.7rem; cursor: pointer;">Nommer</button></div>';
      container.appendChild(currentDiv);
      aiConversationsFromApi.forEach(function(conv) {
        if (conv.session_id === aiSessionDashboard) return;
        const isActive = activeId === conv.session_id;
        const title = (conv.title || 'Sans titre');
        const item = document.createElement('div');
        item.className = 'ai-conversation-item' + (isActive ? ' active' : '');
        item.style.cssText = 'padding: 12px; margin: 4px 0; background: ' + (isActive ? 'white' : '#f9fafb') + '; border-radius: 8px; border-left: 3px solid ' + (isActive ? '#6366f1' : 'transparent') + '; cursor: pointer;';
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
        if (data.success) { appendAIMessage('assistant', data.message); if (data.session_id) { aiSessionDashboard = data.session_id; document.getElementById('aiCurrentSession').textContent = data.session_id.substr(0, 8); document.getElementById('aiSessionSidebar').textContent = data.session_id.substr(0, 8); } loadConversationsFromDatabase(); }
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
  </script>

</body>
</html>

