@php
    $isOoredoo = isset($isOoredoo) ? $isOoredoo : false;
    $theme = isset($theme) ? $theme : 'club_privileges';
@endphp

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Utilisateurs - {{ $isOoredoo ? 'Ooredoo' : 'Club Privilèges' }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        :root {
            @if($isOoredoo)
                --brand-primary: #E30613;
                --brand-secondary: #B91C1C;
                --brand-accent: #FBBF24;
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
            @else
                --brand-primary: #6B46C1;
                --brand-secondary: #8B5CF6;
                --brand-accent: #F59E0B;
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
            @endif
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
            max-width: 1200px; 
            margin: 0 auto; 
            padding: 20px; 
        }
        
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: var(--card);
            padding: 24px;
            border-radius: 12px;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            color: var(--brand-red);
            font-size: 28px;
            font-weight: 700;
            margin: 0;
        }
        
        .header-actions {
            display: flex;
            gap: 12px;
        }
        
        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: var(--brand-red);
            color: white;
        }
        
        .btn-primary:hover {
            background: #c20510;
            text-decoration: none;
        }
        
        .btn-secondary {
            background: var(--bg);
            color: var(--brand-dark);
            border: 1px solid var(--border);
        }
        
        .btn-secondary:hover {
            background: var(--card);
            text-decoration: none;
        }
        
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        
        .btn-danger:hover {
            background: #dc2626;
        }
        
        .card {
            background: var(--card);
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th {
            background: var(--bg);
            padding: 16px;
            text-align: left;
            font-weight: 600;
            color: var(--brand-dark);
            border-bottom: 1px solid var(--border);
        }
        
        .table td {
            padding: 16px;
            border-bottom: 1px solid var(--border);
        }
        
        .table tr:hover {
            background: #f9fafb;
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-active {
            background: #dcfce7;
            color: #16a34a;
        }
        
        .status-inactive {
            background: #fee2e2;
            color: #dc2626;
        }
        
        .status-pending {
            background: #fef3c7;
            color: #d97706;
        }
        
        .status-suspended {
            background: #f3f4f6;
            color: #6b7280;
        }
        
        .role-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 500;
        }
        
        .role-super_admin {
            background: var(--brand-red);
            color: white;
        }
        
        .role-admin {
            background: var(--accent);
            color: white;
        }
        
        .role-collaborator {
            background: var(--success);
            color: white;
        }
        
        .operators-list {
            font-size: 12px;
            color: var(--muted);
        }
        
        .operators-list span {
            display: inline-block;
            background: var(--bg);
            padding: 2px 6px;
            border-radius: 4px;
            margin: 1px;
        }
        
        .alert {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            font-size: 14px;
        }
        
        .alert-success {
            background: #dcfce7;
            color: #16a34a;
            border: 1px solid #bbf7d0;
        }
        
        .alert-danger {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 24px;
            gap: 8px;
        }
        
        .pagination a, .pagination span {
            padding: 8px 12px;
            border-radius: 6px;
            text-decoration: none;
            color: var(--brand-dark);
            border: 1px solid var(--border);
        }
        
        .pagination .active {
            background: var(--brand-red);
            color: white;
            border-color: var(--brand-red);
        }
        
        .pagination a:hover {
            background: var(--bg);
        }
        
        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 24px;
            font-size: 14px;
            color: var(--muted);
        }
        
        .breadcrumb a {
            color: var(--brand-red);
            text-decoration: none;
        }
        
        .breadcrumb a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="breadcrumb">
            @if(Auth::user()->canAccessOperatorsDashboard())
                <a href="{{ route('dashboard') }}">Dashboard</a>
                <span>→</span>
            @else
                <a href="{{ route('sub-stores.dashboard') }}">Sub-Stores Dashboard</a>
                <span>→</span>
            @endif
            <span>Administration</span>
            <span>→</span>
            <span>Utilisateurs</span>
        </div>
        
        <div class="header">
            <h1>Gestion des Utilisateurs</h1>
            <div class="header-actions">
                @if(Auth::user()->canAccessOperatorsDashboard())
                    <a href="{{ route('dashboard') }}" class="btn btn-secondary">
                        ← Retour au Dashboard
                    </a>
                @else
                    <a href="{{ route('sub-stores.dashboard') }}" class="btn btn-secondary">
                        ← Retour au Sub-Stores Dashboard
                    </a>
                @endif
                @if(Auth::user()->isSuperAdmin() || Auth::user()->isAdmin())
                    <a href="{{ route('admin.users.create') }}" class="btn btn-primary">
                        + Nouvel Utilisateur
                    </a>
                @endif
            </div>
        </div>
        
        @if(session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif
        
        @if(session('error'))
            <div class="alert alert-danger">
                {{ session('error') }}
            </div>
        @endif
        
        <div class="card">
            <table class="table">
                <thead>
                    <tr>
                        <th>Utilisateur</th>
                        <th>Rôle</th>
                        <th>Opérateurs</th>
                        <th>Statut</th>
                        <th>Dernière connexion</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                        <tr>
                            <td>
                                <div>
                                    <div style="font-weight: 600;">{{ $user->name }}</div>
                                    <div style="font-size: 12px; color: var(--muted);">{{ $user->email }}</div>
                                    @if($user->phone)
                                        <div style="font-size: 12px; color: var(--muted);">{{ $user->phone }}</div>
                                    @endif
                                </div>
                            </td>
                            <td>
                                @if($user->role)
                                    <span class="role-badge role-{{ $user->role->name }}">
                                        {{ $user->role->display_name }}
                                    </span>
                                @else
                                    <span style="color: var(--muted);">Aucun rôle</span>
                                @endif
                            </td>
                            <td>
                                <div class="operators-list">
                                    @forelse($user->operators as $operator)
                                        <span>
                                            {{ $operator->operator_name }}
                                            @if($operator->is_primary)
                                                (Principal)
                                            @endif
                                        </span>
                                    @empty
                                        <span style="color: var(--muted);">Aucun opérateur</span>
                                    @endforelse
                                </div>
                            </td>
                            <td>
                                <span class="status-badge status-{{ $user->status }}">
                                    {{ ucfirst($user->status) }}
                                </span>
                            </td>
                            <td>
                                @if($user->last_login_at)
                                    <div>{{ $user->last_login_at->format('d/m/Y H:i') }}</div>
                                    @if($user->last_login_ip)
                                        <div style="font-size: 12px; color: var(--muted);">{{ $user->last_login_ip }}</div>
                                    @endif
                                @else
                                    <span style="color: var(--muted);">Jamais connecté</span>
                                @endif
                            </td>
                            <td>
                                <div style="display: flex; gap: 8px;">
                                    @if(Auth::user()->isSuperAdmin() || (Auth::user()->isAdmin() && $user->isCollaborator()))
                                        <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;">
                                            Modifier
                                        </a>
                                    @endif
                                    
                                    @if(Auth::user()->isSuperAdmin() && $user->id !== Auth::id() && !$user->isSuperAdmin())
                                        <form action="{{ route('admin.users.destroy', $user) }}" method="POST" style="display: inline;">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger" style="padding: 6px 12px; font-size: 12px;" 
                                                    onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur ?')">
                                                Supprimer
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 40px; color: var(--muted);">
                                Aucun utilisateur trouvé.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        @if($users->hasPages())
            <div class="pagination">
                {{ $users->links() }}
            </div>
        @endif
    </div>
</body>
</html>
