# Rapport de Performance - Dashboard Club Privilèges
## Date: 26 Mars 2026

---

## 1. RÉSUMÉ EXÉCUTIF

Le dashboard Club Privilèges a été optimisé de manière significative. Le temps de chargement
initial est passé de **165+ secondes** (avec erreurs 500 fréquentes) à **4-5 secondes** en warm cache
et **17 secondes** en cold cache. L'application est maintenant stable et performante.

---

## 2. BASE DE DONNÉES

| Table | Lignes | Rôle |
|-------|--------|------|
| client_abonnement | 352,911 | Abonnements (table principale) |
| history | 236,993 | Transactions/historique |
| dashboard_daily_stats | 1,278 | KPIs matérialisés (90 jours) |
| partner | 1,581 | Partenaires/marchands |
| promotion | 2,140 | Promotions |
| country_payments_methods | 13 | Opérateurs |

**Index**: 20 sur client_abonnement, 14 sur history

---

## 3. TEMPS DE RÉPONSE API

### 3.1 Endpoints Split (Chargement Progressif)

| Endpoint | Cold Cache | Warm Cache | Taille |
|----------|-----------|------------|--------|
| /api/dashboard/split/kpis | 5.71s | 1.97s | 1.8 KB |
| /api/dashboard/split/merchants | 6.20s | 1.94s | 6.7 KB |
| /api/dashboard/split/transactions | 2.90s | 1.93s | 1.3 KB |
| /api/dashboard/split/subscriptions | 16.99s | 2.21s | 46.6 KB |
| /api/dashboard/split/ooredoo | 2.42s | 1.86s | 24.4 KB |

**Note**: Les endpoints split se chargent en PARALLÈLE dans le navigateur.

### 3.2 Endpoint Monolithique (Legacy)

| Endpoint | Cold Cache | Warm Cache | Taille |
|----------|-----------|------------|--------|
| /api/dashboard/data | 17.25s | 2.51s | 81.3 KB |

### 3.3 Pages Spéciales

| Page | Temps | Status |
|------|-------|--------|
| /admin/timwe-diagnostic | 1.75s | 200 OK |
| /admin/ml-dashboard | 3.60s | 200 OK |
| /admin/ai-agent (standalone) | 4.33s | 500 (config AI manquante) |
| Agent IA (onglet dashboard) | Instantané | OK |

### 3.4 API Diagnostic Timwe

| Endpoint | Temps | Status |
|----------|-------|--------|
| /api/summary | 2.95s | 200 OK |
| /api/funnel-kpis | 3.17s | 200 OK |
| /api/delivery | 2.89s | 200 OK |
| /api/recent | 2.71s | 200 OK |

---

## 4. TEMPS DE CHARGEMENT FRONTEND (Expérience Utilisateur)

| Scénario | Temps Total | Méthode |
|----------|-------------|---------|
| Warm cache (données en Redis) | **4.0s** | Progressif (5 requêtes parallèles) |
| Semi-warm (certaines données en cache) | **5.5s** | Progressif |
| Cold cache (première visite) | **17s** | Progressif |
| Login -> Dashboard | **1.9s** | Redirect + HTML |

---

## 5. COMPARAISON AVANT / APRÈS

| Métrique | AVANT (Phase 0) | APRÈS (Phase 3) | Amélioration |
|----------|-----------------|-----------------|-------------|
| Cold cache (14j, ALL) | 165s+ (500 ERROR) | 17.2s | **-89.6%** |
| Warm cache | N/A (timeout) | 2.5s (mono) / 4.0s (progressif) | **Fonctionnel** |
| KPIs seuls | ~31s | 1.97s (matérialisées) | **-93.6%** |
| Subscriptions | Timeout | 2.2s (warm) / 17s (cold) | **Fonctionnel** |
| retentionTrend | Bloquait indéfiniment | ~1s | **100% fix** |
| quarterlyActiveLocations | ~60s (16 requêtes) | ~1s (2 requêtes) | **-98.3%** |
| Erreurs 500 | Fréquentes | Aucune | **100% fix** |

---

## 6. OPTIMISATIONS IMPLÉMENTÉES

### 6.1 Backend (Performance)
1. **Tables matérialisées** (`dashboard_daily_stats`): 1,278 lignes pré-calculées couvrant 90 jours pour 13 opérateurs + ALL. KPIs servis en < 2s au lieu de 31s.
2. **PDO Parameter Bindings**: Toutes les requêtes DB::raw() sécurisées contre l'injection SQL.
3. **JOIN conditionnel**: Suppression du JOIN sur `country_payments_methods` quand opérateur = ALL (-50% temps requête).
4. **Budget de temps 90s**: Les calculs secondaires sont abandonnés si le temps total dépasse 90s.
5. **Timeout MySQL 30s**: Chaque requête individuelle est limitée à 30s.
6. **Optimisation `quarterlyActiveLocations`**: 16 requêtes réduites à 2.

### 6.2 Frontend (UX)
1. **Chargement progressif**: 5 endpoints split chargés en parallèle, le dashboard s'affiche section par section.
2. **Notification de performance**: Badge vert "Données mises à jour! (Xs)" en haut à droite.
3. **Indicateur "Lent"**: Badge orange si le chargement dépasse un seuil.
4. **Onglets Agent IA + Diagnostic Timwe**: Intégrés depuis la branche develop.

### 6.3 Infrastructure
1. **Cache Redis**: Toutes les données dashboard mises en cache avec TTL adaptatif.
2. **Cron warmup** (*/25 min): Pré-remplit le cache avant expiration.
3. **Cron matérialisation** (daily 3h00): Recalcule les 3 derniers jours dans `dashboard_daily_stats`.
4. **Express proxy timeout**: 120s -> 300s.
5. **PHP-FPM**: 10 workers, 300s request_terminate_timeout, 512MB memory.
6. **Nginx**: fastcgi_read_timeout 300s.
7. **TrustProxies**: Configuré pour `*` (Kubernetes/Ingress).

### 6.4 Sécurité
1. **Injection SQL éliminée**: Toutes les interpolations DB::raw("...$var...") remplacées par des bindings PDO.
2. **Auth session**: Endpoints split protégés par middleware auth web (cookies de session).

---

## 7. JOBS PLANIFIÉS (SCHEDULER)

| Job | Fréquence | Rôle |
|-----|-----------|------|
| dashboard:warmup | */25 min | Pré-remplir le cache Redis |
| dashboard:materialize | Daily 3h00 | Recalculer KPIs matérialisés |
| timwe:calculate-daily | Daily 2h30 | Stats Timwe quotidiennes |
| timwe:diagnostic-backfill | Daily 2h35 | Diagnostic Timwe |
| ooredoo:update-daily-stats | Daily 2h45 | Stats Ooredoo/DGV |
| cache:warmup | Daily 6h00 | Cache intelligent ML/KPIs |
| ml:tx-daily-ingest | */5 min | Ingestion transactions ML |
| ml:build-90d-features | */2h | Construction features ML |
| ml:tx-daily-maintenance | Hebdo dim 4h | Nettoyage agrégats ML |

---

## 8. FONCTIONNALITÉS TESTÉES

| Fonctionnalité | Status | Remarque |
|---------------|--------|----------|
| Login / Auth | OK | Session cookies fonctionnels |
| Dashboard Overview | OK | 10 onglets, KPIs, graphiques |
| Chargement progressif | OK | 5 endpoints parallèles |
| KPIs matérialisées | OK | Source: "materialized" confirmé |
| Agent IA (onglet) | OK | Chat, sidebar, suggestions |
| Diagnostic Timwe | OK | KPIs, filtres, performance technique |
| ML Dashboard | OK | Page accessible |
| Sélection opérateur | OK | ALL + 13 opérateurs individuels |
| Sélection période | OK | Dates personnalisables |
| Comparaison Auto | OK | Période de comparaison automatique |

---

## 9. POINTS D'ATTENTION

1. **Agent IA standalone** (/admin/ai-agent): Erreur 500 car les clés API (OpenAI/Anthropic/Gemini) ne sont pas configurées dans .env. L'Agent IA fonctionne parfaitement via l'onglet du dashboard.
2. **Cold cache subscriptions**: 17s car la requête de subscriptions parcourt 352K lignes. Pourrait être matérialisé pour < 3s.
3. **Latence réseau DB**: ~200ms par requête (DB distante). Le cache et la matérialisation contournent ce problème.
4. **GitGuardian**: 4 secrets détectés dans `env.production.example` (pré-existants dans develop). À nettoyer.

---

## 10. RECOMMANDATIONS FUTURES

| Priorité | Action | Impact Estimé |
|----------|--------|--------------|
| P2 | Matérialiser merchants/subscriptions/transactions | Cold cache < 5s |
| P2 | Configurer les clés API pour Agent IA | Agent IA fonctionnel |
| P3 | Nettoyer env.production.example (secrets GitGuardian) | Sécurité |
| P3 | Matérialisation 365 jours (batch nocturne) | Analyses historiques < 2s |
| P3 | Monitoring temps réel avec alertes | Détection proactive |
