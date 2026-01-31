@extends('layouts.app')

@section('title', 'Configuration Eklektik')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">
            <!-- Navigation Eklektik -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="card-title">
                        ⚙️ Configuration Eklektik
                        <span class="badge badge-info" id="cron-status-badge">Chargement...</span>
                    </h3>
                </div>
                <div class="card-body">
                    <div class="nav nav-pills nav-fill" id="eklektik-nav" role="tablist">
                        <a class="nav-item nav-link {{ request()->routeIs('admin.eklektik-cron') ? 'active' : '' }}" 
                           href="{{ route('admin.eklektik-cron') }}" role="tab">
                            ⚙️ Configuration Cron
                        </a>
                        <a class="nav-item nav-link {{ request()->routeIs('admin.eklektik.sync') ? 'active' : '' }}" 
                           href="{{ route('admin.eklektik.sync') }}" role="tab">
                            🔄 Gestion des Synchronisations
                        </a>
                        <a class="nav-item nav-link {{ request()->routeIs('admin.eklektik.sync.tracking') ? 'active' : '' }}" 
                           href="{{ route('admin.eklektik.sync.tracking') }}" role="tab">
                            📈 Suivi des Synchronisations
                        </a>
                        <a class="nav-item nav-link" 
                           href="{{ route('admin.eklektik.dashboard') }}" role="tab" target="_blank">
                            📊 Dashboard Complet
                        </a>
                        <a class="nav-item nav-link {{ request()->routeIs('admin.cp-sync.*') ? 'active' : '' }}" 
                           href="{{ route('admin.cp-sync.index') }}" role="tab">
                            🔄 Sync Club Privilèges
                        </a>
                    </div>
                </div>
            </div>

            <!-- Contenu spécifique à chaque vue -->
            @yield('eklektik-content')
        </div>
    </div>
</div>

<style>
.nav-pills .nav-link {
    color: var(--brand-primary);
    border: 1px solid var(--border);
    margin: 0 2px;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.nav-pills .nav-link:hover {
    background-color: var(--bg);
    border-color: var(--brand-primary);
}

.nav-pills .nav-link.active {
    background-color: var(--brand-primary);
    border-color: var(--brand-primary);
    color: white;
}

.nav-pills .nav-link.active:hover {
    background-color: var(--brand-secondary);
    border-color: var(--brand-secondary);
}

.card {
    border: 1px solid var(--border);
    border-radius: 12px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.card-header {
    background-color: var(--bg);
    border-bottom: 1px solid var(--border);
    border-radius: 12px 12px 0 0;
}

.card-title {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
    color: var(--brand-dark);
}

.badge {
    font-size: 12px;
    padding: 4px 8px;
    border-radius: 4px;
}

.badge-info {
    background-color: var(--accent);
    color: white;
}

.badge-success {
    background-color: var(--success);
    color: white;
}

.badge-danger {
    background-color: var(--danger);
    color: white;
}

.badge-warning {
    background-color: var(--warning);
    color: white;
}
</style>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

@yield('scripts')
@endsection
