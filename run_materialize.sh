#!/bin/bash
cd /app
php artisan dashboard:materialize --days=90 > /app/storage/logs/mat90.log 2>&1
