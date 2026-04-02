#!/bin/bash
set -e

PROJECT_DIR="/var/www/dashboard_cp"
BACKEND_DIR="$PROJECT_DIR/backend"
PHP_BIN="/usr/bin/php"
COMPOSER_BIN="/usr/local/bin/composer"

echo "========================================"
echo " DEPLOIEMENT PRODUCTION - $(date '+%Y-%m-%d %H:%M:%S')"
echo "========================================"

cd "$PROJECT_DIR"

# ──────────────────────────────────────────
# 1. LARAVEL
# ──────────────────────────────────────────
echo ""
echo "[1/6] Mode maintenance ON..."
$PHP_BIN artisan down --retry=30 || true

echo "[2/6] Composer install (production)..."
$COMPOSER_BIN install --no-dev --no-interaction --prefer-dist --optimize-autoloader 2>&1 || {
    echo "  -> Lock file desynchronise, execution de composer update..."
    $COMPOSER_BIN update --no-dev --no-interaction --prefer-dist --optimize-autoloader
}

echo "[3/6] Migrations..."
$PHP_BIN artisan migrate --force

echo "[4/6] Cache et optimisation..."
$PHP_BIN artisan optimize:clear
$PHP_BIN artisan config:cache
$PHP_BIN artisan route:cache
$PHP_BIN artisan view:cache

echo "[5/6] Mode maintenance OFF..."
$PHP_BIN artisan up

# ──────────────────────────────────────────
# 2. FASTAPI
# ──────────────────────────────────────────
echo ""
echo "[6/6] FastAPI - pip install + restart..."
cd "$BACKEND_DIR"

if [ -f "venv/bin/activate" ]; then
    source venv/bin/activate
fi

# Liberer de l'espace disque
pip cache purge 2>/dev/null || true
sudo apt-get clean 2>/dev/null || true

# Installer avec noms de packages uniquement (sans versions)
# pour compatibilite avec le Python du serveur
awk -F'[=<>!;]' '{gsub(/^ +| +$/,"",$1); if($1 && $1 !~ /^#/ && $1 !~ /^-/) print $1}' requirements.txt > /tmp/requirements_compat.txt
pip install -r /tmp/requirements_compat.txt \
    --extra-index-url https://d33sy5i8bnduwe.cloudfront.net/simple/ \
    --no-cache-dir --quiet

sudo supervisorctl restart fastapi_dashboard_prod

echo ""
echo "========================================"
echo " DEPLOIEMENT PRODUCTION TERMINE AVEC SUCCES"
echo " $(date '+%Y-%m-%d %H:%M:%S')"
echo "========================================"
