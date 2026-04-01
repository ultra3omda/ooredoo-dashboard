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
                --brand-primary: #6C4BA0;
                --brand-secondary: #D4A843;
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
            color: var(--text-primary); 
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
            color: var(--text-primary);
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
            color: var(--text-primary);
            border-bottom: 1px solid var(--border);
        }
        
        .table td {
            padding: 16px;
            border-bottom: 1px solid var(--border);
        }
        
        .table tr:hover {
            background: var(--table-stripe);
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
        
        /* Responsive Admin - Mobile Card Layout */
        @media (max-width: 768px) {
            .container { padding: 12px 8px; }
            .page-header { flex-direction: column; align-items: stretch; gap: 12px; }
            .page-header h1 { font-size: 20px; text-align: center; }
            .page-header .header-actions { justify-content: center; flex-wrap: wrap; gap: 8px; }
            .page-header .header-actions a { font-size: 13px; padding: 10px 16px; }
            .breadcrumb { font-size: 12px; }
            
            /* Table -> Card layout on mobile */
            .table thead { display: none; }
            .table, .table tbody, .table tr, .table td { display: block; width: 100%; }
            .table tr { 
                padding: 16px; 
                margin-bottom: 12px; 
                border: 1px solid var(--border); 
                border-radius: 10px; 
                background: var(--card);
                box-shadow: 0 1px 3px rgba(0,0,0,0.06);
            }
            .table td { 
                padding: 4px 0; 
                border: none;
                font-size: 13px;
            }
            .table td:before {
                content: attr(data-label);
                display: block;
                font-size: 11px;
                font-weight: 600;
                color: var(--muted);
                text-transform: uppercase;
                letter-spacing: 0.5px;
                margin-bottom: 2px;
                margin-top: 8px;
            }
            .table td:first-child:before { margin-top: 0; }
            /* Hide invitation link column on mobile */
            .table td.td-link { display: none; }
            .actions { flex-direction: row; gap: 6px; margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--border); }
            .actions .btn-sm { flex: 1; justify-content: center; padding: 8px 10px; font-size: 12px; }
        }
    .dark-mode { --brand-dark:#FFF; --bg:#0D0A1A; --card:#161131; --card-hover:#1E1745; --muted:#A1A1AA; --border:#2A2350; --text-primary:#FFF; --text-secondary:#A1A1AA; --input-bg:#1E1745; --input-border:#2A2350; --shadow-sm:0 1px 3px rgba(0,0,0,0.3); --shadow-md:0 4px 12px rgba(0,0,0,0.4); --table-stripe:rgba(255,255,255,0.03); --success:#10b981; --warning:#f59e0b; --danger:#ef4444; --accent:#D4A843; }
    </style>
<script>(function(){var s=localStorage.getItem("dashboard-theme");if(s==="dark")document.documentElement.classList.add("dark-mode");}());</script>
</head>
<body>
    @include('partials._admin-header')
    <div class="container">
        <div class="page-header" style="margin-top: 16px;">
            <h1>Gestion des Invitations</h1>
            <div class="header-actions">
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
            <div class="table-wrapper">
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
                            <td data-label="Invite">
                                <div>
                                    <div style="font-weight: 600;">{{ $invitation->first_name }} {{ $invitation->last_name }}</div>
                                    <div style="font-size: 12px; color: var(--muted);">{{ $invitation->email }}</div>
                                </div>
                            </td>
                            <td data-label="Role & Operateur">
                                <div>
                                    @if($invitation->role)
                                        <span class="role-badge role-{{ $invitation->role->name }}">
                                            {{ $invitation->role->display_name }}
                                        </span>
                                    @endif
                                    <div style="font-size: 12px; color: var(--muted); margin-top: 4px;">
                                        {{ $invitation->operator_name }}
                                        @php
                                            $additionalData = $invitation->additional_data;
                                            $campaigns = is_array($additionalData) ? ($additionalData['campaign_access'] ?? []) : [];
                                        @endphp
                                        @if(!empty($campaigns))
                                            <div style="margin-top: 4px; display: flex; flex-wrap: wrap; gap: 3px;">
                                                @foreach($campaigns as $camp)
                                                    <span style="background: rgba(59,130,246,0.1); color: #2563eb; padding: 1px 6px; border-radius: 4px; font-size: 10px;">{{ $camp }}</span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td data-label="Invite par">
                                <div style="font-size: 14px;">{{ $invitation->invitedBy->name ?? 'Inconnu' }}</div>
                                <div style="font-size: 12px; color: var(--muted);">{{ $invitation->invitedBy->email ?? '' }}</div>
                            </td>
                            <td data-label="Statut">
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
                            <td data-label="Date">
                                <div>{{ $invitation->created_at->format('d/m/Y H:i') }}</div>
                                @if($invitation->accepted_at)
                                    <div style="font-size: 12px; color: var(--success);">
                                        Acceptée le {{ $invitation->accepted_at->format('d/m/Y H:i') }}
                                    </div>
                                @endif
                            </td>
                            <td class="td-link" data-label="Lien">
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
