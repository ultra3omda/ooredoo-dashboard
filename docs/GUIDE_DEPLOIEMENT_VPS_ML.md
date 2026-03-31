# Guide de Déploiement VPS - Système de Recommandation ML (FastAPI + LightGBM)

> **Club Privileges** - Moteur de recommandation marchands basé sur LightGBM, servi par FastAPI.
> Ce guide couvre l'installation, la configuration et la mise en production sur un VPS Ubuntu.

---

## Table des matières

1. [Pré-requis](#1-pré-requis)
2. [Architecture du système](#2-architecture-du-système)
3. [Installation Python & Environnement virtuel](#3-installation-python--environnement-virtuel)
4. [Installation des dépendances](#4-installation-des-dépendances)
5. [Configuration .env](#5-configuration-env)
6. [Structure des fichiers](#6-structure-des-fichiers)
7. [Entraînement initial du modèle](#7-entraînement-initial-du-modèle)
8. [Configuration Supervisor (daemon FastAPI)](#8-configuration-supervisor-daemon-fastapi)
9. [Configuration Nginx (reverse proxy)](#9-configuration-nginx-reverse-proxy)
10. [Vérification & Tests](#10-vérification--tests)
11. [Endpoints API disponibles](#11-endpoints-api-disponibles)
12. [Maintenance & Re-entraînement](#12-maintenance--re-entraînement)
13. [Dépannage (Troubleshooting)](#13-dépannage-troubleshooting)
14. [Sécurité en production](#14-sécurité-en-production)

---

## 1. Pré-requis

| Composant      | Version minimale | Notes                                      |
|----------------|------------------|--------------------------------------------|
| Ubuntu         | 20.04 LTS+       | Testé sur 22.04 et 24.04                   |
| Python         | 3.10+            | 3.11 recommandé pour les performances      |
| pip            | 23+              |                                            |
| Supervisor     | 4+               | Gestion des processus daemon               |
| Nginx          | 1.18+            | Reverse proxy                              |
| MySQL          | 5.7+ / 8.0       | Base `clubprivileges` accessible           |
| Redis          | 6+               | Utilisé par Laravel pour le cache          |
| RAM            | 2 Go minimum     | LightGBM utilise ~500 Mo lors du training  |
| Espace disque  | 1 Go libre       | Pour le modèle (.joblib ~50 Mo) et les logs|

---

## 2. Architecture du système

```
┌─────────────────────────────────────────────────────────┐
│                       NAVIGATEUR                        │
│  Dashboard Admin → /admin/merchant-recommendations      │
│  Dashboard Sub-Store → /sub-stores/{id}                 │
└──────────────────────┬──────────────────────────────────┘
                       │ HTTPS
                       ▼
              ┌────────────────┐
              │     NGINX      │
              │  (port 80/443) │
              └───┬────────┬───┘
                  │        │
    /api/merchant-│        │  Autres routes
    recommendations        │  (Laravel)
                  │        │
                  ▼        ▼
         ┌──────────┐  ┌──────────────┐
         │ FastAPI   │  │  PHP-FPM     │
         │ port 8001 │  │  (Laravel)   │
         └─────┬─────┘  └──────────────┘
               │
       ┌───────┼────────┐
       │       │        │
       ▼       ▼        ▼
   predict  train    track
   _merchant _merchant interactions
   .py       _recommender
              .py
       │               │
       ▼               ▼
  ┌──────────┐  ┌────────────┐
  │ LightGBM │  │   MySQL    │
  │  .joblib  │  │ clubpriv.  │
  └──────────┘  └────────────┘
```

**Flux de données :**
- Le **navigateur** appelle les endpoints `/api/merchant-recommendations/*` directement (FastAPI)
- **FastAPI** (port 8001) sert les prédictions ML et gère le tracking des interactions
- **Laravel** (via PHP-FPM) gère le dashboard admin, l'authentification et le proxy vers FastAPI pour certaines routes
- Le **modèle LightGBM** (.joblib) est chargé en mémoire par `predict_merchant.py`
- Le **re-entraînement** est lancé en tâche de fond (background thread) via l'endpoint `/api/merchant-recommendations/retrain`

---

## 3. Installation Python & Environnement virtuel

```bash
# Installer Python 3.11 et les outils
sudo apt update
sudo apt install -y python3.11 python3.11-venv python3.11-dev python3-pip

# Créer un environnement virtuel dédié
cd /var/www/votre-projet   # Racine de votre projet Laravel
python3.11 -m venv .venv

# Activer l'environnement
source .venv/bin/activate

# Vérifier
python3 --version   # → Python 3.11.x
pip --version       # → pip 23.x+
```

> **Important :** Toujours utiliser le Python de l'environnement virtuel dans Supervisor
> (chemin : `/var/www/votre-projet/.venv/bin/python3`)

---

## 4. Installation des dépendances

```bash
# Activer le venv
source .venv/bin/activate

# Installer les dépendances ML
pip install lightgbm scikit-learn pandas numpy pymysql joblib python-dotenv

# Installer les dépendances FastAPI
pip install fastapi uvicorn httpx

# (Optionnel) Si vous utilisez l'IA générative pour les rapports
pip install litellm openai

# Vérifier que tout est installé
pip list | grep -E "lightgbm|fastapi|pymysql|scikit"
```

**Liste complète des packages nécessaires :**

| Package          | Utilisation                              |
|------------------|------------------------------------------|
| `fastapi`        | Serveur API Python                       |
| `uvicorn`        | Serveur ASGI pour FastAPI                |
| `httpx`          | Client HTTP async (proxy vers Laravel)   |
| `python-dotenv`  | Lecture du fichier .env                  |
| `pymysql`        | Connexion MySQL pour le ML               |
| `lightgbm`       | Modèle de ranking ML (LambdaRank)       |
| `scikit-learn`   | Métriques d'évaluation (NDCG)           |
| `pandas`         | Manipulation des données d'entraînement  |
| `numpy`          | Calculs numériques                       |
| `joblib`         | Sérialisation/chargement du modèle      |

---

## 5. Configuration .env

Le fichier `.env` à la racine du projet est lu à la fois par Laravel et par les scripts Python ML.
Les scripts Python lisent directement ce fichier pour la connexion MySQL.

**Variables requises pour le ML :**

```dotenv
# Base de données MySQL (déjà configurée pour Laravel)
DB_CONNECTION=mysql
DB_HOST=51.38.187.245
DB_PORT=3306
DB_DATABASE=clubprivileges
DB_USERNAME=looker_user
DB_PASSWORD=votre_mot_de_passe

# Redis (utilisé par Laravel pour le cache des recommandations)
REDIS_HOST=51.38.187.245
REDIS_PORT=7905
REDIS_PASSWORD=votre_redis_password
CACHE_DRIVER=redis

# URL de l'application (utilisée par server.py pour les headers proxy)
APP_URL=https://preprod.dashboard.clubprivileges.app

# Chemin Python du venv (utilisé par les commandes Artisan)
PYTHON_PATH=/var/www/votre-projet/.venv/bin/python3
```

> **Note :** Les scripts Python (`predict_merchant.py`, `train_merchant_recommender.py`) lisent le `.env` manuellement (pas via Laravel). Ils utilisent uniquement `DB_HOST`, `DB_PORT`, `DB_USERNAME`, `DB_PASSWORD`, `DB_DATABASE`.

> **Note :** MongoDB n'est PAS nécessaire. Le système utilise exclusivement MySQL.

---

## 6. Structure des fichiers

```
votre-projet/
├── .env                                # Config partagée Laravel + Python
├── backend/
│   └── server.py                       # FastAPI : proxy + endpoints ML
├── ml_models/
│   ├── train_merchant_recommender.py   # Script d'entraînement LightGBM
│   ├── predict_merchant.py             # Moteur d'inférence (scoring)
│   ├── merchant_recommender.joblib     # Modèle entraîné (généré)
│   ├── merchant_fallback_popular.json  # Fallback popularité (généré)
│   └── merchant_recommender_metrics.json # Métriques du modèle (généré)
├── app/
│   └── Services/
│       └── MLMerchantRecommendationService.php  # Service Laravel
├── resources/views/admin/
│   └── merchant-recommendations.blade.php       # Dashboard Admin ML
└── resources/views/sub-stores/
    └── dashboard.blade.php                      # Dashboard Sub-Store
```

**Fichiers générés par l'entraînement :**
- `merchant_recommender.joblib` (~50 Mo) : Modèle LightGBM sérialisé
- `merchant_fallback_popular.json` : Top marchands par popularité (utilisé si le modèle n'est pas disponible)
- `merchant_recommender_metrics.json` : NDCG@5, NDCG@10, nombre d'échantillons, date d'entraînement

---

## 7. Entraînement initial du modèle

```bash
# Activer le venv
source .venv/bin/activate

# Lancer l'entraînement (durée : 1-3 minutes selon les données)
cd /var/www/votre-projet
python3 ml_models/train_merchant_recommender.py
```

**Sortie attendue :**
```
[1/5] Extracting merchants catalog...
      → 576 marchands catalogués
[2/5] Extracting user profiles...
      → 19,234 profils utilisateurs
[3/5] Building training pairs...
      → 139,456 paires d'entraînement
[4/5] Training LightGBM Ranker...
      → Training terminé en 12.3s
[5/5] Evaluation...
      → NDCG@5:  0.987
      → NDCG@10: 0.973
Model saved: ml_models/merchant_recommender.joblib
Fallback saved: ml_models/merchant_fallback_popular.json
```

**Ce que fait le script d'entraînement :**
1. Peuple/met à jour la table `cp_merchants_catalog` (catalogue marchands enrichi)
2. Peuple/met à jour la table `cp_user_profile` (profils utilisateurs agrégés)
3. Génère des paires utilisateur-marchand avec des features (user_avg_visits, visit_count, loyalty_score, etc.)
4. Entraîne un modèle LightGBM en mode **LambdaRank** (optimisé pour le ranking)
5. Sauvegarde le modèle et les fichiers de fallback

**Tables MySQL créées/utilisées :**

| Table                       | Rôle                                        |
|-----------------------------|---------------------------------------------|
| `cp_merchants_catalog`      | Catalogue marchands enrichi (features ML)   |
| `cp_user_profile`           | Profils utilisateurs agrégés                |
| `cp_user_merchant_history`  | Historique visites par paire user-marchand  |
| `cp_user_offer_interactions`| Tracking des interactions (feedback loop)   |

---

## 8. Configuration Supervisor (daemon FastAPI)

Créer le fichier de configuration Supervisor :

```bash
sudo nano /etc/supervisor/conf.d/fastapi.conf
```

**Contenu :**

```ini
[program:fastapi]
command=/var/www/votre-projet/.venv/bin/python3 -m uvicorn server:app --host 127.0.0.1 --port 8001 --workers 2
directory=/var/www/votre-projet/backend
user=www-data
autostart=true
autorestart=true
stderr_logfile=/var/log/supervisor/fastapi.err.log
stdout_logfile=/var/log/supervisor/fastapi.out.log
stderr_logfile_maxbytes=10MB
stdout_logfile_maxbytes=10MB
environment=PATH="/var/www/votre-projet/.venv/bin:%(ENV_PATH)s"
stopwaitsecs=30
killasgroup=true
stopasgroup=true
```

**Appliquer la configuration :**

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start fastapi

# Vérifier le statut
sudo supervisorctl status fastapi
# → fastapi    RUNNING   pid 12345, uptime 0:00:05

# Voir les logs
sudo tail -f /var/log/supervisor/fastapi.err.log
```

**Commandes Supervisor utiles :**

```bash
sudo supervisorctl restart fastapi    # Redémarrer après mise à jour du code
sudo supervisorctl stop fastapi       # Arrêter
sudo supervisorctl tail fastapi stderr # Voir les logs d'erreur en temps réel
```

---

## 9. Configuration Nginx (reverse proxy)

Ajouter un bloc `location` dans votre fichier de configuration Nginx existant pour router les appels `/api/merchant-recommendations` vers FastAPI :

```bash
sudo nano /etc/nginx/sites-available/votre-site.conf
```

**Bloc à ajouter dans le `server { }` :**

```nginx
# ─── FastAPI ML Recommendations ───────────────────────────────
# Route les endpoints ML directement vers FastAPI (port 8001)
location /api/merchant-recommendations {
    proxy_pass http://127.0.0.1:8001;
    proxy_http_version 1.1;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;

    # Timeout élevé pour le retrain (~60-120s)
    proxy_read_timeout 300s;
    proxy_connect_timeout 10s;
    proxy_send_timeout 300s;
}

# ─── FastAPI AI Suggestions (rapports IA) ─────────────────────
location /api/report-ai-suggestions {
    proxy_pass http://127.0.0.1:8001;
    proxy_http_version 1.1;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_read_timeout 60s;
}
```

**Tester et recharger Nginx :**

```bash
sudo nginx -t          # Vérifier la syntaxe
sudo nginx -s reload   # Recharger la configuration
```

> **Important :** Le bloc `location /api/merchant-recommendations` doit apparaître AVANT le bloc PHP (`location ~ \.php$`) pour que Nginx route en priorité vers FastAPI.

**Exemple de configuration Nginx complète :**

```nginx
server {
    listen 80;
    server_name preprod.dashboard.clubprivileges.app;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl;
    server_name preprod.dashboard.clubprivileges.app;

    root /var/www/votre-projet/public;
    index index.php;

    ssl_certificate /etc/letsencrypt/live/preprod.dashboard.clubprivileges.app/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/preprod.dashboard.clubprivileges.app/privkey.pem;

    # ── FastAPI endpoints (AVANT le bloc PHP) ──
    location /api/merchant-recommendations {
        proxy_pass http://127.0.0.1:8001;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 300s;
        proxy_connect_timeout 10s;
        proxy_send_timeout 300s;
    }

    location /api/report-ai-suggestions {
        proxy_pass http://127.0.0.1:8001;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 60s;
    }

    # ── Laravel (PHP-FPM) ──
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

---

## 10. Vérification & Tests

### Test 1 : FastAPI Health Check

```bash
curl -s http://127.0.0.1:8001/api/merchant-recommendations/health | python3 -m json.tool
```

**Réponse attendue :**
```json
{
    "status": "ready",
    "model_loaded": true,
    "fallback_available": true,
    "trained_at": "2026-04-02T15:30:00",
    "n_train_samples": 139456,
    "eval_results": {
        "ndcg@5": 0.987,
        "ndcg@10": 0.973
    }
}
```

### Test 2 : Recommandations pour un client

```bash
curl -s -X POST http://127.0.0.1:8001/api/merchant-recommendations \
  -H "Content-Type: application/json" \
  -d '{"client_id": 1234, "top_k": 5}' | python3 -m json.tool
```

**Réponse attendue :**
```json
{
    "success": true,
    "client_id": 1234,
    "count": 5,
    "source": "ml_model",
    "recommendations": [
        {
            "partner_id": 42,
            "partner_name": "Monoprix",
            "category_name": "Grande Distribution",
            "score": 8.73,
            "rank": 1,
            "reason": "Basé sur vos visites fréquentes dans cette catégorie",
            "already_visited": true,
            "active_promotions": 3,
            "avg_discount": 15.5
        }
    ]
}
```

### Test 3 : Retrain (via Nginx, en externe)

```bash
# Lancer le retrain
curl -s -X POST https://preprod.dashboard.clubprivileges.app/api/merchant-recommendations/retrain \
  -H "Content-Type: application/json" | python3 -m json.tool

# → {"started": true, "message": "Retrain lancé en arrière-plan"}

# Vérifier le statut (quelques secondes après)
curl -s https://preprod.dashboard.clubprivileges.app/api/merchant-recommendations/retrain/status \
  | python3 -m json.tool

# → {"status": "running", ...} ou {"status": "completed", ...}
```

### Test 4 : Via le dashboard Admin

1. Connectez-vous à `https://votre-domaine/admin/merchant-recommendations`
2. Vérifiez que les KPIs (marchands, profils, interactions) s'affichent
3. Testez la recherche par ID client
4. Cliquez sur **"Retrain modèle"** → Le bouton affiche la progression en temps réel

---

## 11. Endpoints API disponibles

| Méthode | Endpoint                                        | Description                              |
|---------|--------------------------------------------------|------------------------------------------|
| POST    | `/api/merchant-recommendations`                  | Obtenir des recommandations pour un client |
| GET     | `/api/merchant-recommendations/health`           | Statut du moteur ML (modèle, métriques)  |
| POST    | `/api/merchant-recommendations/retrain`          | Lancer le re-entraînement (async)        |
| GET     | `/api/merchant-recommendations/retrain/status`   | Statut du re-entraînement en cours       |
| POST    | `/api/merchant-recommendations/track`            | Tracker une interaction utilisateur      |
| GET     | `/api/merchant-recommendations/stats`            | Statistiques d'utilisation (7 jours)     |
| GET     | `/api/merchant-recommendations/stats/timeline`   | Timeline des interactions (30 jours)     |
| GET     | `/api/merchant-recommendations/categories`       | Liste des catégories marchands           |
| POST    | `/api/report-ai-suggestions`                     | Suggestions IA pour les rapports         |

---

## 12. Maintenance & Re-entraînement

### Re-entraînement manuel (CLI)

```bash
source /var/www/votre-projet/.venv/bin/activate
python3 /var/www/votre-projet/ml_models/train_merchant_recommender.py
sudo supervisorctl restart fastapi   # Recharger le nouveau modèle
```

### Re-entraînement automatique (CRON hebdomadaire)

```bash
sudo crontab -e
```

Ajouter :
```cron
# Re-entraînement ML chaque lundi à 3h du matin
0 3 * * 1 /var/www/votre-projet/.venv/bin/python3 /var/www/votre-projet/ml_models/train_merchant_recommender.py >> /var/log/ml_retrain.log 2>&1 && supervisorctl restart fastapi
```

### Re-entraînement via l'UI

- Dashboard Admin → `/admin/merchant-recommendations` → Bouton **"Retrain modèle"**
- Le retrain se lance en arrière-plan, le bouton affiche la progression

### Mise à jour du code

```bash
cd /var/www/votre-projet
git pull origin main

# Si des dépendances Python ont changé
source .venv/bin/activate
pip install -r backend/requirements.txt

# Redémarrer FastAPI
sudo supervisorctl restart fastapi

# Si des dépendances PHP ont changé
composer install --no-dev
php artisan config:cache
php artisan route:cache
```

---

## 13. Dépannage (Troubleshooting)

### Erreur : "Connection refused" sur port 8001

```bash
# Vérifier que FastAPI tourne
sudo supervisorctl status fastapi

# Si STOPPED ou FATAL, voir les logs
sudo tail -50 /var/log/supervisor/fastapi.err.log

# Relancer
sudo supervisorctl restart fastapi
```

### Erreur : "504 Gateway Timeout" sur le retrain

**Cause :** Nginx coupe la connexion avant la fin du retrain.

**Solution 1 (déjà appliquée) :** Le retrain est maintenant asynchrone (retourne immédiatement `{"started": true}`, le processing se fait en background).

**Solution 2 :** Si d'autres endpoints sont lents, augmenter le timeout Nginx :
```nginx
location /api/merchant-recommendations {
    proxy_read_timeout 300s;  # 5 minutes
}
```

### Erreur : "ModuleNotFoundError: No module named 'pymysql'"

```bash
# Installer dans le bon venv
source /var/www/votre-projet/.venv/bin/activate
pip install pymysql
```

### Erreur : "No module named 'lightgbm'"

```bash
source /var/www/votre-projet/.venv/bin/activate
pip install lightgbm
```

### Le modèle retourne "fallback_only" dans le health check

Le fichier `merchant_recommender.joblib` n'existe pas → Lancer l'entraînement initial :
```bash
source .venv/bin/activate
python3 ml_models/train_merchant_recommender.py
```

### Les recommandations sont vides pour un client

**Causes possibles :**
1. Le client n'a aucun historique de visites → Le système retourne le fallback popularité
2. Le modèle n'a pas été entraîné avec ce client → Re-entraîner le modèle
3. Le filtre `exclude_visited=true` exclut tous les marchands → Désactiver le filtre

### Les KPIs du dashboard Admin affichent 0

Le endpoint `/api/merchant-recommendations/stats` interroge la table `cp_user_offer_interactions`.
Si elle est vide, c'est normal au début. Les interactions se remplissent progressivement avec l'utilisation.

### Logs utiles

```bash
# Logs FastAPI
sudo tail -f /var/log/supervisor/fastapi.err.log

# Logs Nginx
sudo tail -f /var/log/nginx/error.log

# Logs Laravel
tail -f /var/www/votre-projet/storage/logs/laravel.log
```

---

## 14. Sécurité en production

### Checklist

- [ ] **Pas d'accès direct à FastAPI depuis l'extérieur** : Le port 8001 ne doit être accessible que depuis `127.0.0.1` (via Nginx)
- [ ] **Firewall** : Bloquer le port 8001 en entrée
  ```bash
  sudo ufw deny 8001
  ```
- [ ] **HTTPS obligatoire** : Le certificat SSL (Let's Encrypt) est configuré dans Nginx
- [ ] **Pas de clés API dans le code** : Tout est dans le `.env`
- [ ] **Permissions fichiers** :
  ```bash
  chmod 600 /var/www/votre-projet/.env
  chmod 755 /var/www/votre-projet/ml_models/
  chmod 644 /var/www/votre-projet/ml_models/*.py
  ```
- [ ] **Utilisateur dédié** : FastAPI tourne en `www-data` (pas en root)

### Rotation des logs

```bash
# Créer /etc/logrotate.d/fastapi
cat <<EOF | sudo tee /etc/logrotate.d/fastapi
/var/log/supervisor/fastapi.*.log {
    weekly
    rotate 4
    compress
    missingok
    notifempty
}
EOF
```

---

## Résumé des commandes essentielles

```bash
# ── Statut ──
sudo supervisorctl status fastapi
curl -s http://127.0.0.1:8001/api/merchant-recommendations/health | python3 -m json.tool

# ── Redémarrage ──
sudo supervisorctl restart fastapi

# ── Logs ──
sudo tail -f /var/log/supervisor/fastapi.err.log

# ── Re-entraînement ──
source /var/www/votre-projet/.venv/bin/activate
python3 ml_models/train_merchant_recommender.py

# ── Mise à jour code ──
git pull && sudo supervisorctl restart fastapi
```

---

*Document généré le 2 avril 2026 — Club Privileges Dashboard v2.0*
