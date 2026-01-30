<!-- Tabs -->
<ul class="nav nav-tabs" id="diagnosticTabs" role="tablist" style="display: none;">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="byPhone-tab" data-bs-toggle="tab" data-bs-target="#byPhone" type="button">
            <i class="fas fa-mobile-alt me-1"></i> Par Numéro
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="byDeliveryCode-tab" data-bs-toggle="tab" data-bs-target="#byDeliveryCode" type="button">
            <i class="fas fa-chart-pie me-1"></i> Par Delivery Code
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="recentTransactions-tab" data-bs-toggle="tab" data-bs-target="#recentTransactions" type="button">
            <i class="fas fa-history me-1"></i> Transactions Récentes
        </button>
    </li>
</ul>

<div class="tab-content mt-3" id="diagnosticTabContent">
    <!-- Tab: Par Numéro -->
    <div class="tab-pane fade show active" id="byPhone" role="tabpanel">
        <div class="table-responsive">
            <table class="table table-hover table-striped" id="phoneTable">
                <thead class="table-dark">
                    <tr>
                        <th>Téléphone</th>
                        <th>Client</th>
                        <th>Total Tentatives</th>
                        <th>DELIVERED</th>
                        <th>NO_BALANCE</th>
                        <th>NOT_DELIVERED</th>
                        <th>Autres</th>
                        <th>Facturé (TND)</th>
                        <th>Dernière Tentative</th>
                    </tr>
                </thead>
                <tbody id="phoneTableBody">
                    <tr>
                        <td colspan="9" class="text-center text-muted">Aucune donnée disponible</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Tab: Par Delivery Code -->
    <div class="tab-pane fade" id="byDeliveryCode" role="tabpanel">
        <div class="table-responsive">
            <table class="table table-hover table-striped" id="deliveryCodeTable">
                <thead class="table-dark">
                    <tr>
                        <th>Delivery Code</th>
                        <th>Nombre Total</th>
                        <th>Numéros Uniques</th>
                        <th>Total Facturé (TND)</th>
                        <th>Pourcentage</th>
                    </tr>
                </thead>
                <tbody id="deliveryCodeTableBody">
                    <tr>
                        <td colspan="5" class="text-center text-muted">Aucune donnée disponible</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Tab: Transactions Récentes -->
    <div class="tab-pane fade" id="recentTransactions" role="tabpanel">
        <div class="table-responsive">
            <table class="table table-hover table-sm" id="transactionsTable">
                <thead class="table-dark">
                    <tr>
                        <th>Date</th>
                        <th>Téléphone</th>
                        <th>Client</th>
                        <th>Delivery Code</th>
                        <th>Facturé (TND)</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody id="transactionsTableBody">
                    <tr>
                        <td colspan="6" class="text-center text-muted">Aucune donnée disponible</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>