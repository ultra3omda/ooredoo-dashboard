# PRD - Dashboard Club Privileges (Ooredoo)

## Probleme Original
Dashboard de performance pour le programme Club Privileges d'Ooredoo Tunisie, necessitant des temps de reponse ultra-rapides (<1s) pour l'agregation massive de donnees sur 5+ ans.

## Architecture Technique
- **Stack**: Laravel 10 (PHP 8.2) + Nginx/PHP-FPM + Redis + MySQL
- **Proxy**: FastAPI (port 8001) -> Nginx (port 8002)
- **Frontend**: Express (port 3000) -> Blade templates + Chart.js
- **Cache**: Redis avec pre-chauffage cron (WarmupSplitEndpoints)
- **Materialisation**: Tables `subscription_daily_stats`, `transaction_daily_stats` via artisan commands

## Ce qui a ete implemente

### Phase 1 - Materialisation (DONE)
- Table `subscription_daily_stats` + commande artisan
- Table `transaction_daily_stats` + commande artisan
- Rewrite de `getSubscriptionsData` et `getKPIsFromMaterialized`

### Phase 2 - Cache Ultra-Rapide (DONE)
- `WarmupSplitEndpoints.php` : pre-cache JSON complet dans Redis
- `DataControllerOptimized.php` : fast-path retournant JSON brut depuis Redis (~5ms)
- Temps de reponse backend : 5-10ms (vs 20-30s avant)

### Phase 3 - Correction Bugs Frontend (DONE - 28/03/2026)
- **Bug P0**: Limite de periode augmentee de 1825 a 2200 jours
- **Bug P0**: Cle de cache simplifiee (start_date + end_date + operator)
- **Bug P1**: Race condition Retention Rate Trend corrigee (handler timwe pre-creait subscriptions={})
- **Bug P1**: Acces donnees merchants corrige (objet vs tableau)
- **Bug P1**: Source categoryDistribution corrigee
- **Bug P1**: Table marchands corrigee
- **Bug P2**: Re-render charts lors du changement d'onglet

### Verification Complete (DONE - 28/03/2026)
- 9/9 onglets verifies pour Lifetime (01/01/2021 - 28/03/2026)
- 0 graphique vide, 0 KPI manquant, 0 tableau bloque
- Rapport complet : `/app/reports/RAPPORT_VERIFICATION_COMPLETE.md`

## Backlog

### P3 - Monitoring (A VENIR)
- Alertes temps reel
- Health checks
- Tableau de bord de monitoring

### P4 - Refactoring (FUTUR)
- Decouplage `DashboardService.php` (~4000 lignes)
- Creation services dedies : SubscriptionService, TransactionService, MerchantService
- Tests unitaires et d'integration

## Integrations 3rd Party
- OpenAI GPT-4 (necessite cle utilisateur)
- Google Gemini 2.5 Flash (necessite cle utilisateur)

## Credentials
- Email: superadmin@ooredoo.tn
- Password: SuperAdmin@2025
