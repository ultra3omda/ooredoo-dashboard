@php
    $isOoredoo = isset($isOoredoo) ? $isOoredoo : false;
    $theme = isset($theme) ? $theme : 'club_privileges';
@endphp

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Invitations - {{ $isOoredoo ? 'Ooredoo' : 'Club Privilèges' }}</title>
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
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        
        .btn-warning {
            background: var(--warning);
            color: white;
        }
        
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
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
        
        .status-pending {
            background: #fef3c7;
            color: #d97706;
        }
        
        .status-accepted {
            background: #dcfce7;
            color: #16a34a;
        }
        
        .status-expired {
            background: #fee2e2;
            color: #dc2626;
        }
        
        .status-cancelled {
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
        
        .actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .invitation-link {
            font-family: monospace;
            font-size: 12px;
            background: var(--bg);
            padding: 4px 8px;
            border-radius: 4px;
            word-break: break-all;
            color: var(--accent);
        }
        
        .copy-btn {
            background: none;
            border: 1px solid var(--border);
            color: var(--muted);
            padding: 2px 6px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 11px;
        }
        
        .copy-btn:hover {
            background: var(--bg);
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
            <a href="{{ route('admin.users.index') }}">Administration</a>
            <span>→</span>
            <span>Invitations</span>
        </div>
        
        <div class="header">
            <h1>Gestion des Invitations</h1>
            <div class="header-actions">
                <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">
                    Utilisateurs
                </a>
                <a href="{{ route('admin.invitations.create') }}" class="btn btn-primary">
                    + Nouvelle Invitation
                </a>
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
                        <th>Invité</th>
                        <th>Rôle & Opérateur</th>
                        <th>Invité par</th>
                        <th>Statut</th>
                        <th>Date</th>
                        <th>Lien d'invitation</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($invitations as $invitation)
                        <tr>
                            <td>
                                <div>
                                    <div style="font-weight: 600;">{{ $invitation->first_name }} {{ $invitation->last_name }}</div>
                                    <div style="font-size: 12px; color: var(--muted);">{{ $invitation->email }}</div>
                                </div>
                            </td>
                            <td>
                                <div>
                                    @if($invitation->role)
                                        <span class="role-badge role-{{ $invitation->role->name }}">
                                            {{ $invitation->role->display_name }}
                                        </span>
                                    @endif
                                    <div style="font-size: 12px; color: var(--muted); margin-top: 4px;">
                                        {{ $invitation->operator_name }}
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div style="font-size: 14px;">{{ $invitation->invitedBy->name ?? 'Inconnu' }}</div>
                                <div style="font-size: 12px; color: var(--muted);">{{ $invitation->invitedBy->email ?? '' }}</div>
                            </td>
                            <td>
                                <span class="status-badge status-{{ $invitation->status }}">
                                    @switch($invitation->status)
                                        @case('pending')
                                            En attente
                                            @break
                                        @case('accepted')
                                            Acceptée
                                            @break
                                        @case('expired')
                                            Expirée
                                            @break
                                        @case('cancelled')
                                            Annulée
                                            @break
                                        @default
                                            {{ ucfirst($invitation->status) }}
                                    @endswitch
                                </span>
                                @if($invitation->status === 'pending')
                                    <div style="font-size: 12px; color: var(--muted); margin-top: 4px;">
                                        Expire le {{ $invitation->expires_at->format('d/m/Y') }}
                                    </div>
                                @endif
                            </td>
                            <td>
                                <div>{{ $invitation->created_at->format('d/m/Y H:i') }}</div>
                                @if($invitation->accepted_at)
                                    <div style="font-size: 12px; color: var(--success);">
                                        Acceptée le {{ $invitation->accepted_at->format('d/m/Y H:i') }}
                                    </div>
                                @endif
                            </td>
                            <td>
                                @if($invitation->status === 'pending')
                                    <div style="max-width: 200px;">
                                        <div class="invitation-link">
                                            {{ route('auth.invitation', $invitation->token) }}
                                        </div>
                                        <button class="copy-btn" onclick="copyToClipboard('{{ route('auth.invitation', $invitation->token) }}')">
                                            Copier
                                        </button>
                                    </div>
                                @else
                                    <span style="color: var(--muted); font-size: 12px;">Non disponible</span>
                                @endif
                            </td>
                            <td>
                                <div class="actions">
                                    @if($invitation->status === 'pending')
                                        @if(Auth::user()->isSuperAdmin() || $invitation->invited_by === Auth::id())
                                            <form action="{{ route('admin.invitations.resend', $invitation) }}" method="POST" style="display: inline;">
                                                @csrf
                                                <button type="submit" class="btn btn-warning btn-sm">
                                                    Renvoyer
                                                </button>
                                            </form>
                                            
                                            <form action="{{ route('admin.invitations.cancel', $invitation) }}" method="POST" style="display: inline;">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="btn btn-secondary btn-sm" 
                                                        onclick="return confirm('Annuler cette invitation ?')">
                                                    Annuler
                                                </button>
                                            </form>
                                        @endif
                                    @endif
                                    
                                    @if(Auth::user()->isSuperAdmin() || $invitation->invited_by === Auth::id())
                                        <form action="{{ route('admin.invitations.destroy', $invitation) }}" method="POST" style="display: inline;">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger btn-sm" 
                                                    onclick="return confirm('Supprimer cette invitation ?')">
                                                Supprimer
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 40px; color: var(--muted);">
                                Aucune invitation trouvée.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        @if($invitations->hasPages())
            <div style="display: flex; justify-content: center; margin-top: 24px;">
                {{ $invitations->links() }}
            </div>
        @endif
    </div>
    
    <script>
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                // Feedback visuel
                const btn = event.target;
                const originalText = btn.textContent;
                btn.textContent = 'Copié !';
                btn.style.background = 'var(--success)';
                btn.style.color = 'white';
                
                setTimeout(() => {
                    btn.textContent = originalText;
                    btn.style.background = '';
                    btn.style.color = '';
                }, 2000);
            }).catch(function(err) {
                console.error('Erreur lors de la copie: ', err);
                alert('Impossible de copier le lien');
            });
        }
    </script>
</body>
</html>
