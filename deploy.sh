#!/bin/bash
set -e

PROJECT_DIR="/var/www/preprod_dash_cp"
BACKEND_DIR="$PROJECT_DIR/backend"
PHP_BIN="/usr/bin/php"
COMPOSER_BIN="/usr/local/bin/composer"

echo "========================================"
echo " DEPLOIEMENT - $(date '+%Y-%m-%d %H:%M:%S')"
echo "========================================"

cd "$PROJECT_DIR"

# ──────────────────────────────────────────
# 1. LARAVEL
# ──────────────────────────────────────────
echo ""
echo "[1/6] Mode maintenance ON..."
$PHP_BIN artisan down --retry=30 || true

echo "[2/6] Composer install (production)..."
$COMPOSER_BIN install --no-dev --no-interaction --prefer-dist --optimize-autoloader

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

pip install -r requirements.txt --quiet

sudo supervisorctl restart fastapi_dashboard

echo ""
echo "========================================"
echo " DEPLOIEMENT TERMINE AVEC SUCCES"
echo " $(date '+%Y-%m-%d %H:%M:%S')"
echo "========================================"
