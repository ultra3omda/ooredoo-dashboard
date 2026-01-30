@extends('layouts.app')

@section('title', 'Diagnostic Notifications Timwe')

@section('content')
<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h3 class="mb-0">
                        <i class="fas fa-stethoscope me-2"></i>
                        Diagnostic des Notifications Timwe
                    </h3>
                    <p class="mb-0 mt-2 small">Analyse détaillée des réponses API Timwe par numéro et type de delivery code</p>
                </div>
                
                <div class="card-body">
                    <!-- Filtres -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Date Début</label>
                            <input type="date" id="start_date" class="form-control" value="{{ \Carbon\Carbon::now()->subDays(30)->format('Y-m-d') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Date Fin</label>
                            <input type="date" id="end_date" class="form-control" value="{{ \Carbon\Carbon::now()->format('Y-m-d') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Rechercher Téléphone</label>
                            <input type="text" id="search_phone" class="form-control" placeholder="Ex: +21612345678">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Filtrer Delivery Code</label>
                            <select id="delivery_code" class="form-select">
                                <option value="">Tous</option>
                                <option value="DELIVERED">DELIVERED</option>
                                <option value="NO_BALANCE">NO_BALANCE</option>
                                <option value="NOT_DELIVERED">NOT_DELIVERED</option>
                                <option value="UNKNOWN">UNKNOWN</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-12">
                            <button id="btnSearch" class="btn btn-primary me-2">
                                <i class="fas fa-search me-1"></i> Rechercher
                            </button>
                            <button id="btnExport" class="btn btn-success" disabled>
                                <i class="fas fa-file-csv me-1"></i> Exporter CSV
                            </button>
                            <span id="loadingIndicator" class="ms-3 text-muted" style="display: none;">
                                <i class="fas fa-spinner fa-spin me-1"></i> Chargement...
                            </span>
                        </div>
                    </div>
                    
                    <!-- Résumé -->
                    <div id="summarySection" class="row mb-4" style="display: none;">
                        <div class="col-md-2">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h6 class="text-muted small">Total Transactions</h6>
                                    <h3 id="totalTransactions" class="mb-0 text-primary">-</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h6 class="text-muted small">Numéros Uniques</h6>
                                    <h3 id="uniquePhones" class="mb-0 text-info">-</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h6 class="text-muted small">Facturés</h6>
                                    <h3 id="totalBilled" class="mb-0 text-success">-</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h6 class="text-muted small">Taux Facturation</h6>
                                    <h3 id="billingRate" class="mb-0 text-warning">-</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h6 class="text-muted small">Revenu Total (TND)</h6>
                                    <h3 id="totalRevenue" class="mb-0 text-success">-</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h6 class="text-muted small">Types Delivery</h6>
                                    <h3 id="deliveryCodesCount" class="mb-0 text-secondary">-</h3>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    @include('admin.timwe-diagnostic-tabs')
                </div>
            </div>
        </div>
    </div>
</div>

@include('admin.timwe-diagnostic-scripts')
@endsection
