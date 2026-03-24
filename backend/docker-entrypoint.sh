#!/bin/sh

echo "======================================="
echo " Ocean Backend - Entrypoint Script"
echo "======================================="

# -----------------------------------------------
# 0. Create required directories & fix permissions
# -----------------------------------------------
echo "[1/7] Setting up directories and permissions..."
mkdir -p /var/www/storage/framework/cache/data
mkdir -p /var/www/storage/framework/sessions
mkdir -p /var/www/storage/framework/views
mkdir -p /var/www/storage/logs
mkdir -p /var/www/storage/app/public
mkdir -p /var/www/bootstrap/cache

chown -R www-data:www-data /var/www/storage 2>/dev/null || true
chown -R www-data:www-data /var/www/bootstrap/cache 2>/dev/null || true
chmod -R 777 /var/www/storage 2>/dev/null || true
chmod -R 777 /var/www/bootstrap/cache 2>/dev/null || true

# -----------------------------------------------
# 1. Wait for MySQL using PHP PDO (no SSL issues)
# -----------------------------------------------
echo "[2/7] Waiting for MySQL..."
MAX_TRIES=30
COUNT=0
while [ "$COUNT" -lt "$MAX_TRIES" ]; do
    if php -r "
        try {
            new PDO(
                'mysql:host=' . getenv('DB_HOST') . ';port=' . (getenv('DB_PORT') ?: '3306'),
                getenv('DB_USERNAME'),
                getenv('DB_PASSWORD'),
                [PDO::ATTR_TIMEOUT => 3]
            );
            echo 'OK';
        } catch (Exception \$e) {
            exit(1);
        }
    " 2>/dev/null; then
        echo "  MySQL is ready!"
        break
    fi
    COUNT=$((COUNT + 1))
    echo "  Retrying ($COUNT/$MAX_TRIES)..."
    sleep 2
done

if [ "$COUNT" -ge "$MAX_TRIES" ]; then
    echo "ERROR: MySQL not ready after ${MAX_TRIES} retries."
    exit 1
fi

# -----------------------------------------------
# 2. Composer install
# -----------------------------------------------
echo "[3/7] Installing Composer dependencies..."
cd /var/www
composer install --no-interaction --prefer-dist --optimize-autoloader 2>&1
chown -R www-data:www-data /var/www/vendor 2>/dev/null || true

# -----------------------------------------------
# 3. Generate APP_KEY if needed
# -----------------------------------------------
echo "[4/7] Checking APP_KEY..."
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "base64:CHANGE_ME" ]; then
    echo "  Generating new APP_KEY..."
    php artisan key:generate --force
else
    echo "  APP_KEY already set."
fi

# -----------------------------------------------
# 4. Storage symlink
# -----------------------------------------------
echo "[5/7] Creating storage link..."
rm -f /var/www/public/storage
ln -sf /var/www/storage/app/public /var/www/public/storage

# -----------------------------------------------
# 5. Clear ALL caches before migration
# -----------------------------------------------
echo "[6/7] Clearing caches & running migrations..."
php artisan config:clear 2>&1 || true
php artisan cache:clear 2>&1 || true
php artisan route:clear 2>&1 || true
php artisan view:clear 2>&1 || true

# -----------------------------------------------
# 6. Database migration (non-fatal)
# -----------------------------------------------
php artisan migrate --force 2>&1 || echo "WARNING: Migration had errors, continuing..."

# -----------------------------------------------
# 7. Start PHP-FPM
# -----------------------------------------------
echo "[7/7] Starting PHP-FPM..."
echo "======================================="
echo " Backend READY on port 9000"
echo "======================================="

exec php-fpm
