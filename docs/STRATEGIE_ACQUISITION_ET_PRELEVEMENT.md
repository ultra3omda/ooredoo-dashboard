# Stratégie d'acquisition et de prélèvement

## Canaux d'acquisition

### 1. Bulk SMS
- Les **agrégateurs** (Eklektik, DGV/Ooredoo, Timwe) envoient des **bulk SMS** pour l'acquisition.
- Offre : **activation par défaut après une période de gratuité**.
- Après la période gratuite, chaque agrégateur **prélève selon son propre mode** (voir ci‑dessous).

### 2. Campagne digitale
- Même logique que le bulk SMS : **seul le canal change** (digital au lieu de SMS).
- Activation après période gratuite, puis prélèvement selon le type d'abonnement et l'agrégateur.

### 3. Exception Eklektik – USSD
- **Eklektik** : une partie du **chiffre d'affaires facturé** peut concerner des **clients non présents en base** (pas dans l’app).
- Raison : activation d’abonnement par **USSD** sans téléchargement de l’application.
- Conséquence : les KPIs “clients en base” et “CA Eklektik” peuvent être décalés ; une partie du CA Eklektik provient de ces utilisateurs USSD.

---

## Prélèvement par agrégateur / type d’offre

- Chaque agrégateur applique **sa propre logique** de prélèvement selon le **type d’abonnement**.
- **Daily (0,3 DT/jour)** : le client peut avoir **plusieurs tentatives de facturation dans la journée** ou **une seule**, selon l’agrégateur.
- **Mensuel (ex. 3 DT)** : généralement une tentative par cycle (ou selon la logique opérateur).
- Les autres types (weekly, etc.) suivent la logique propre à chaque agrégateur.

---

## Objectifs du modèle et des suggestions

Le système doit produire des **suggestions** pour améliorer :

1. **Acquisition** – volume et qualité des nouveaux inscrits (par canal SMS / digital quand la donnée est disponible, et par agrégateur).
2. **Conversion** – passage de la période gratuite au payant (trial → premier prélèvement réussi).
3. **Taux de facturation** – proportion de tentatives de prélèvement qui réussissent (timing, type d’offre, nombre de tentatives/jour pour le daily).
4. **CA** – en priorisant les bons créneaux et offres (daily 0,3 DT vs mensuel 3 DT, par agrégateur).

Les suggestions sont calculées à partir des données ML (segments, taux de succès par opérateur, prédictions) et des règles métier alignées sur cette stratégie (bulk SMS, digital, USSD Eklektik, prélèvement par agrégateur/offre).

---

## Implémentation

- **Service** : `App\Services\AcquisitionStrategySuggestionService::getStrategySuggestions()`
- **Intégration** : les suggestions sont fusionnées aux recommandations globales lors de la génération (`MLRecommendationService::generateRecommendations`) et exposées dans :
  - l’API dashboard : `GET /admin/ml-dashboard/data` → clé `strategy_suggestions`
  - la page admin ML : bloc « Suggestions Stratégie (Acquisition, Conversion, Facturation) »
- **Utilisation frontend** : voir **`docs/FRONTEND_ML_DASHBOARD.md`** (accès page, API, affichage des suggestions dans une SPA ou autre front).
