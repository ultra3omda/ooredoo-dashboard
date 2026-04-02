# Variables .env pour les 4 phases ML

Récapitulatif des variables à déclarer dans `.env` pour les 4 phases d’optimisation ML.

---

## Déjà présentes dans votre .env

Vous avez déjà :

- **DB_*** : base de données (utilisée par Laravel et par `train_model.py` via le .env).
- **OPENAI_API_KEY**, **ANTHROPIC_API_KEY**, **GEMINI_API_KEY** (+ modèles) : agent IA (Phase 2).
- **REDIS_HOST**, **REDIS_PORT**, **REDIS_PASSWORD** : Redis (optionnel pour Phase 1 queues et Phase 4 cache).
- **CACHE_DRIVER=file** : cache (Phase 4 fonctionne en file ; Redis permet stats + mémoire).
- **QUEUE_CONNECTION=sync** : queues (Phase 1 en synchrone ; mettre `redis` ou `database` pour parallélisation).

Aucune variable **obligatoire** supplémentaire n’est requise pour faire tourner les 4 phases avec la config actuelle.

---

## Variables optionnelles à ajouter selon l’usage

### Phase 1 – Extraction features (queues)

| Variable | Valeur exemple | Rôle |
|----------|----------------|------|
| `QUEUE_CONNECTION` | `redis` ou `database` | Activer les jobs en queue (ml-extraction). Par défaut `sync` = pas de queue. |
| `ML_EXTRACTION_CHUNK_SIZE` | `500` | Nombre de clients par job (défaut 500). |

Si vous utilisez Redis pour les queues, les variables **REDIS_*** doivent être définies (vous les avez déjà).

---

### Phase 2 – Agent IA

Aucune variable supplémentaire : les clés API et modèles sont déjà dans votre `.env`.

---

### Phase 3 – Modèle ML Python

| Variable | Valeur exemple | Rôle |
|----------|----------------|------|
| `PYTHON_PATH` | `python3` ou `py` ou `C:\...\python.exe` | Exécutable Python utilisé par le bridge PHP. Défaut : `python3`. À définir si `python3` n’est pas dans le PATH (ex. Windows). |

La connexion DB pour `train_model.py` est lue depuis le même `.env` (DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD, DB_PORT).

---

### Phase 4 – Cache intelligent

| Variable | Valeur exemple | Rôle |
|----------|----------------|------|
| `CACHE_DRIVER` | `redis` | Utiliser Redis pour le cache. Avec `redis`, les stats (hits/misses) et la mémoire sont renseignées. |
| `REDIS_HOST` | `127.0.0.1` ou votre hôte | Déjà dans votre .env si Redis est utilisé. |
| `REDIS_PORT` | `6379` | Idem. |
| `REDIS_PASSWORD` | `null` ou votre mot de passe | Idem. |

Avec `CACHE_DRIVER=file`, le cache intelligent fonctionne ; seules les stats détaillées Redis et la mémoire ne sont pas disponibles.

---

## Exemple de bloc à copier dans .env (optionnel)

À ajouter seulement si vous activez les options concernées :

```env
# --- ML 4 phases (optionnel) ---
# Phase 1 - Queues (décommenter pour parallélisation)
# QUEUE_CONNECTION=redis
# ML_EXTRACTION_CHUNK_SIZE=500

# Phase 3 - Python (si python3 pas dans le PATH)
# PYTHON_PATH=py
# ou sous Windows: PYTHON_PATH=C:\Python311\python.exe

# Phase 4 - Cache Redis (pour stats + mémoire)
# CACHE_DRIVER=redis
```

---

## Résumé

- **Rien d’obligatoire à ajouter** : avec votre `.env` actuel, les 4 phases peuvent tourner (extraction en sync, cache en file, pas de Redis obligatoire).
- **À ajouter si besoin** :
  - **Phase 1 (queues)** : `QUEUE_CONNECTION=redis` (et éventuellement `ML_EXTRACTION_CHUNK_SIZE`).
  - **Phase 3 (Python)** : `PYTHON_PATH` si la commande `python3` / `py` n’est pas trouvée.
  - **Phase 4 (stats cache)** : `CACHE_DRIVER=redis` (avec REDIS_* déjà présents).
