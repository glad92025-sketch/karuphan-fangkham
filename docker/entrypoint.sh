#!/usr/bin/env bash
set -e

PORT="${PORT:-8080}"
sed -ri "s/Listen 80/Listen ${PORT}/g" /etc/apache2/ports.conf
sed -ri "s/:80>/:${PORT}>/g" /etc/apache2/sites-available/*.conf

# Use Render's public URL automatically if APP_URL isn't explicitly set
if [ -z "${APP_URL:-}" ] && [ -n "${RENDER_EXTERNAL_URL:-}" ]; then
  export APP_URL="${RENDER_EXTERNAL_URL}"
fi

# Ensure APP_KEY is a valid 32-byte Laravel key (handles Render generateValue format)
if [ -z "${APP_KEY:-}" ]; then
  export APP_KEY="base64:$(openssl rand -base64 32)"
elif [[ "${APP_KEY}" != base64:* ]] && [ "${#APP_KEY}" -ne 32 ]; then
  export APP_KEY="base64:$(printf '%s' "${APP_KEY}" | openssl dgst -sha256 -binary | base64)"
fi

if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ] && [ ! -f database/database.sqlite ]; then
  mkdir -p database
  touch database/database.sqlite
  chown www-data:www-data database/database.sqlite
fi

# Wait for external DB (PostgreSQL/MySQL) if starting up alongside Web Service
for i in $(seq 1 20); do
  if php artisan migrate --force --no-interaction; then
    break
  fi
  echo "Waiting for database connection... ($i/20)"
  sleep 2
done

# Seed initial Excel data & default accounts ONLY if users table is still empty
USER_COUNT=$(php -r '
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try { echo App\Models\User::count(); } catch (Throwable $e) { echo "0"; }
')

if [ "${USER_COUNT:-0}" = "0" ]; then
  echo "Database is empty — seeding initial Samnak Palad assets & accounts..."
  php artisan db:seed --force --no-interaction
fi

rm -f public/storage
php artisan storage:link --no-interaction || true

php artisan config:cache
php artisan route:cache
php artisan view:cache

chown -R www-data:www-data storage bootstrap/cache

exec apache2-foreground
