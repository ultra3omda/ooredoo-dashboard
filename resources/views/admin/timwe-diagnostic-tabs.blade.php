<!-- Tab: Par Numéro -->
<div class="tab-content active" id="byPhone">
    <div class="table-container">
        <table id="phoneTable">
            <thead>
                <tr>
                    <th class="sortable" data-sort="phone" data-type="string">
                        Téléphone <span class="sort-icon">⇅</span>
                    </th>
                    <th class="sortable" data-sort="client_name" data-type="string">
                        Client <span class="sort-icon">⇅</span>
                    </th>
                    <th class="sortable" data-sort="total_attempts" data-type="number">
                        Total Tentatives <span class="sort-icon">⇅</span>
                    </th>
                    <th class="sortable" data-sort="delivered" data-type="number">
                        DELIVERED <span class="sort-icon">⇅</span>
                    </th>
                    <th class="sortable" data-sort="no_balance" data-type="number">
                        NO_BALANCE <span class="sort-icon">⇅</span>
                    </th>
                    <th class="sortable" data-sort="not_delivered" data-type="number">
                        NOT_DELIVERED <span class="sort-icon">⇅</span>
                    </th>
                    <th class="sortable" data-sort="other" data-type="number">
                        Autres <span class="sort-icon">⇅</span>
                    </th>
                    <th class="sortable" data-sort="total_charged_tnd" data-type="number">
                        Facturé (TND) <span class="sort-icon">⇅</span>
                    </th>
                    <th class="sortable" data-sort="subscription_date" data-type="date">
                        Date Inscription <span class="sort-icon">⇅</span>
                    </th>
                    <th class="sortable" data-sort="last_attempt" data-type="date">
                        Dernière Tentative <span class="sort-icon">⇅</span>
                    </th>
                    <th style="width: 100px;">Actions</th>
                </tr>
            </thead>
            <tbody id="phoneTableBody">
                <tr>
                    <td colspan="11" style="text-align: center; color: var(--muted); padding: 40px;">
                        Aucune donnée disponible
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    
    <!-- Pagination -->
    <div id="paginationByPhone" class="pagination-container" style="display: none;"></div>
</div>

<!-- Modal Détails Transactions -->
<div id="phoneDetailsModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>📋 Détails des Transactions</h3>
            <button class="modal-close" onclick="diagnosticApp.closePhoneDetails()">&times;</button>
        </div>
        <div class="modal-body">
            <div id="phoneDetailsContent">
                <p style="text-align: center; color: var(--muted); padding: 40px;">Chargement...</p>
            </div>
        </div>
    </div>
</div>

<!-- Tab: Par Delivery Code -->
<div class="tab-content" id="byDeliveryCode">
    <div class="table-container">
        <table id="deliveryCodeTable">
            <thead>
                <tr>
                    <th>Delivery Code</th>
                    <th>Nombre Total</th>
                    <th>Numéros Uniques</th>
                    <th>Total Facturé (TND)</th>
                    <th style="width: 200px;">Pourcentage</th>
                </tr>
            </thead>
            <tbody id="deliveryCodeTableBody">
                <tr>
                    <td colspan="5" style="text-align: center; color: var(--muted); padding: 40px;">
                        Aucune donnée disponible
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    
    <!-- Pagination -->
    <div id="paginationByDeliveryCode" class="pagination-container" style="display: none;"></div>
</div>

<!-- Tab: Transactions Récentes -->
<div class="tab-content" id="recentTransactions">
    <div class="table-container">
        <table id="transactionsTable">
            <thead>
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
                    <td colspan="6" style="text-align: center; color: var(--muted); padding: 40px;">
                        Aucune donnée disponible
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    
    <!-- Pagination -->
    <div id="paginationRecentTransactions" class="pagination-container" style="display: none;"></div>
</div>