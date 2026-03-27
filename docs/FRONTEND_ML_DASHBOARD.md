# Utiliser le dashboard ML et les suggestions avec le frontend

## Accès

- **URL de la page** : `/admin/ml-dashboard`
- **Droits** : utilisateur connecté avec rôle **admin** ou **super_admin** (middleware `role:admin,super_admin`).
- **Connexion** : session Laravel (cookie). Les appels API depuis la même origine envoient automatiquement les cookies.

---

## 1. Utilisation dans le navigateur (page intégrée)

1. Se connecter à l’application (login).
2. Aller sur **`/admin/ml-dashboard`**.
3. La page affiche :
   - KPIs portefeuille (taux de succès, clients, churn)
   - **Recommandations prioritaires** (avec boutons Approuver / Simuler / Rejeter)
   - **Suggestions Stratégie (Acquisition, Conversion, Facturation)** : bloc dédié à droite
   - Prédictions récentes, tendances, etc.
4. **Actualiser** : bouton « Actualiser » qui appelle `GET /admin/ml-dashboard/data` et met à jour KPIs, recommandations et **suggestions stratégie**.
5. **Nouvelles recommandations** : bouton qui appelle `POST /admin/ml-dashboard/recommendations/generate`, puis recharge les données (les suggestions stratégie sont incluses dans les recommandations globales).

Aucun code front supplémentaire n’est nécessaire pour cette page ; tout est géré en Blade + JavaScript dans `resources/views/admin/ml-dashboard.blade.php`.

---

## 2. Utilisation via l’API (SPA, mobile, autre front)

Toutes les routes sont préfixées par **`/admin/ml-dashboard`** et nécessitent une **session authentifiée** (cookie) ou un **token** si vous ajoutez plus tard une auth API.

### Données globales du dashboard (recommandé pour le front)

```http
GET /admin/ml-dashboard/data
Accept: application/json
```

**Paramètre optionnel** : `date` (query string, format `Y-m-d`) pour une date d’analyse.

**Exemple** : `GET /admin/ml-dashboard/data?date=2026-02-18`

**Réponse (extrait)** :

```json
{
  "success": true,
  "data": {
    "portfolio": { "total_clients": 1234, "avg_success_rate": 42.5, ... },
    "segments": [ ... ],
    "recommendations": {
      "recommendations": [ ... ],
      "summary": { "total": 5, "critical_count": 1, ... }
    },
    "strategy_suggestions": [
      {
        "type": "global_strategy",
        "current_strategy": "...",
        "recommended_strategy": "...",
        "reasoning": "[Acquisition] ...",
        "priority": "high",
        "expected_impact_percentage": 15
      }
    ],
    "predictions": [ ... ],
    "trends": { ... },
    "model_performance": { ... }
  }
}
```

- **`strategy_suggestions`** : liste des suggestions acquisition / conversion / taux de facturation (et Eklektik USSD). À afficher dans un bloc dédié (priorité, raison, impact attendu).
- **`recommendations`** : recommandations stockées (pricing, timing, global_strategy, etc.) avec actions possibles (approuver, simuler, rejeter) via les routes ci‑dessous.

### Autres endpoints utiles

| Méthode | URL | Rôle |
|--------|-----|------|
| GET | `/admin/ml-dashboard/data` | Données complètes (portfolio, segments, recommandations, **strategy_suggestions**, prédictions, tendances) |
| POST | `/admin/ml-dashboard/recommendations/generate` | Génère de nouvelles recommandations (corps optionnel : `{"date": "2026-02-18"}`) |
| POST | `/admin/ml-dashboard/recommendations/status` | Met à jour le statut d’une recommandation : `{"recommendation_id": 123, "status": "approved"}` |
| POST | `/admin/ml-dashboard/recommendations/simulate` | Simule l’impact : `{"recommendation_id": 123}` |
| POST | `/admin/ml-dashboard/predict` | Prédiction pour un client : `{"client_id": 456, "prediction_date": "2026-02-18"}` |
| GET | `/admin/ml-dashboard/client/{clientId}` | Détails d’un client (features, tendances, prédiction, recommandations) |

Pour les **POST**, envoyer en général :
- `Content-Type: application/json`
- `Accept: application/json`
- `X-Requested-With: XMLHttpRequest`
- En session web : le token CSRF (header `X-CSRF-TOKEN` ou champ `_token` selon la config Laravel).

---

## 3. Afficher les suggestions stratégie dans votre front

1. Appeler **`GET /admin/ml-dashboard/data`** (avec ou sans `?date=...`).
2. Lire **`data.strategy_suggestions`** (tableau).
3. Pour chaque élément :
   - **priority** : `critical` | `high` | `medium` | `low` → badge / couleur.
   - **recommended_strategy** : titre ou résumé de la suggestion.
   - **reasoning** : explication (souvent préfixée par `[Acquisition]`, `[Conversion]`, `[Taux de facturation]`, `[Eklektik USSD]`).
   - **expected_impact_percentage** : gain attendu en %.

Exemple d’affichage type « carte » :

- Badge priorité + **+X%** à droite
- Titre : `recommended_strategy`
- Sous-titre / description : `reasoning`

Aucune action côté API n’est nécessaire pour les suggestions stratégie : elles sont calculées à la volée ; il suffit de les afficher.

---

## 4. Lien depuis le reste de l’app

- Depuis la page **AI Agent** : lien « Retour ML Dashboard » vers `/admin/ml-dashboard`.
- Pour ajouter un lien dans le menu ou le layout admin : utiliser l’URL **`/admin/ml-dashboard`** ou la route nommée **`route('admin.ml.dashboard')`** en Blade.

Exemple en Blade :

```blade
<a href="{{ route('admin.ml.dashboard') }}">Dashboard ML</a>
```

En JavaScript (même origine) :

```javascript
window.location.href = '/admin/ml-dashboard';
// ou avec base URL dynamique
fetch('/admin/ml-dashboard/data').then(r => r.json()).then(data => { ... });
```
