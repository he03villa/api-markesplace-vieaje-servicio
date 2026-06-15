#!/bin/sh
set -e

echo "Generando caches de Laravel..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "Iniciando Octane..."
exec "$@"