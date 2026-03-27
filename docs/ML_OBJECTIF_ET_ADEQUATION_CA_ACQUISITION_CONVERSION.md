# Ce que fait le modèle ML – Adéquation CA / Acquisition / Conversion

## 1. Ce que fait le modèle exactement

### Cible prédite (label)

Le modèle prédit une **variable binaire** :

- **1 (succès)** : le client a **au moins un opérateur** (Timwe, Eklektik ou Ooredoo) avec :
  - activité sur cet opérateur (`*_has_activity` = 1), **et**
  - taux de succès de facturation sur cet opérateur **> 20 %** (`*_success_rate` > 0,2).
- **0** : aucun opérateur ne vérifie ces deux conditions.

En résumé : **« Ce client est-il actuellement un bon payeur sur au moins un canal ? »** (au sens : activité + taux de succès > 20 %).

### Entrées (features)

- Données **multi-opérateur** : tentatives, succès, abonnements, cohérence (Timwe, Eklektik, Ooredoo), **sans** les colonnes qui définissent directement la cible (pour éviter le data leakage).
- Contexte : diversité opérateurs, préférences prix/fréquence, offres quotidiennes/mensuelles, engagement, etc.

### Sortie

- **Probabilité de succès de facturation** (`payment_success_probability`) : score entre 0 et 1.
- En aval (côté PHP), ce score est utilisé avec des règles pour proposer aussi : moment de facturation optimal, prix/fréquence suggérés, etc.

### Où c’est utilisé dans l’app

- **Dashboard ML** : top des clients par probabilité, détail par client.
- **Recommandations** : priorisation des clients à facturer / à relancer (ceux avec une forte probabilité).
- **Agent IA** : contexte « probabilité de succès » pour les réponses.
- **A/B tests** : comparaison stratégie « ML » vs « rule-based » sur le taux de succès de facturation.

Donc le modèle sert à **identifier les clients pour lesquels une tentative de facturation a le plus de chances de réussir** (sur au moins un opérateur), et à **prioriser** les actions de facturation / relance.

---

## 2. Est-ce le bon modèle pour améliorer le CA, l’acquisition et la conversion ?

### Chiffre d’affaires (CA)

- **Oui, partiellement.**  
  Le modèle ne prédit pas le **montant** du CA, mais **qui a le plus de chances de payer** quand on tente une facturation. En priorisant ces clients (moment, canal, offre), on peut :
  - augmenter le **taux de succès** des tentatives (moins de NO_BALANCE / échecs inutiles),
  - mieux **cibler** les relances et le timing,
  - donc **améliorer le CA** à volume d’abonnés constant en convertissant plus de tentatives en paiements.

- **Limite** : il ne prédit pas le **revenu par client** (ARPU, LTV) ni « combien ce client va générer ». Pour optimiser le CA de façon plus fine, il faudrait en plus un modèle de **revenue / LTV** ou au moins des segments par valeur.

### Acquisition (nouveaux abonnés)

- **Non.**  
  Le modèle est entraîné sur des **clients déjà présents** dans `ml_client_features` (abonnés / ayant des transactions). Il ne voit pas :
  - les visiteurs non inscrits,
  - les prospects,
  - le parcours d’inscription.

Il répond à la question : **« Parmi les clients qu’on a déjà, qui a le plus de chances de payer ? »**, pas « Qui va s’inscrire ? ».

Pour améliorer **l’acquisition**, il faudrait d’autres modèles / données, par exemple :
- scoring d’**acquisition** (propension à s’inscrire),
- analyse du **parcours d’inscription** (landing, formulaire, première souscription),
- campagnes et canaux d’acquisition.

### Conversion (au sens large)

- **Ça dépend de quelle conversion on parle.**

| Type de conversion | Le modèle actuel |
|--------------------|-------------------|
| **Visiteur → inscrit / abonné** | Non : pas de données visiteurs / parcours d’inscription. |
| **Inscrit / abonné → 1er paiement réussi** | Oui, indirectement : il identifie les clients « à fort potentiel de succès de facturation », donc utiles pour prioriser qui convertir en payant (timing, offre, canal). |
| **Trial / gratuit → payant** | Même idée : prioriser les clients les plus « prêts à payer » pour les campagnes de passage au payant. |
| **Tentative de facturation → paiement effectif** | Oui, c’est le cœur du modèle : prédiction du **succès de facturation** (taux > 20 % et activité sur au moins un opérateur). |

Donc le modèle est **bien adapté à la conversion « tentative de facturation → paiement »** et à la **priorisation** des clients pour maximiser cette conversion. Il n’est **pas** conçu pour la conversion « visiteur → inscrit » ou « prospect → client ».

---

## 3. Synthèse

| Objectif | Adéquation du modèle actuel | Comment l’utiliser / ce qui manque |
|----------|-----------------------------|------------------------------------|
| **Améliorer le CA** | Oui, en partie | Prioriser les facturations et relances sur les clients à forte probabilité ; optimiser timing/offre. Pour aller plus loin : ajouter un objectif ou un modèle lié au **revenu / LTV**. |
| **Améliorer l’acquisition** | Non | Données et cible du modèle = clients déjà en base. Il faut un autre dispositif (scoring acquisition, parcours, campagnes) pour l’acquisition. |
| **Améliorer la conversion** | Oui pour la conversion « facturation → paiement » | Utiliser la probabilité pour prioriser qui facturer/relancer et quand. Pas adapté pour la conversion « visiteur → inscrit » sans données dédiées. |

En une phrase : **le modèle actuel est un outil de prédiction du succès de facturation et de priorisation des clients pour la facturation ; il est pertinent pour améliorer le CA et la conversion « tentative → paiement », mais pas pour l’acquisition (nouveaux clients) ni pour une optimisation directe du revenu par client (LTV/ARPU) sans évolution.**  
Pour aller plus loin sur le CA, on peut envisager une **cible ou un modèle complémentaire** (ex. revenu à 30 j, LTV, ou régression du montant payé).
