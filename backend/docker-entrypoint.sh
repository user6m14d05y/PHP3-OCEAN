#!/bin/sh
set -e

echo "======================================="
echo " Ocean Backend - Entrypoint Script"
echo "======================================="

# -----------------------------------------------
# 1. Chờ MySQL sẵn sàng (retry loop)
# -----------------------------------------------
echo "[1/5] Waiting for MySQL to be ready..."
MAX_TRIES=30
COUNT=0
until php -r "
    try {
        \$pdo = new PDO(
            'mysql:host=' . getenv('DB_HOST') . ';port=' . getenv('DB_PORT', '3306'),
            getenv('DB_USERNAME'),
            getenv('DB_PASSWORD')
        );
        echo 'DB connected';
    } catch (Exception \$e) {
        exit(1);
    }
" 2>/dev/null; do
    COUNT=$((COUNT + 1))
    if [ "$COUNT" -ge "$MAX_TRIES" ]; then
        echo "ERROR: MySQL is not ready after ${MAX_TRIES} retries. Aborting."
        exit 1
    fi
    echo "  MySQL not ready yet... retrying ($COUNT/$MAX_TRIES)"
    sleep 2
done
echo "  MySQL is ready!"

# -----------------------------------------------
# 2. Cài đặt Composer dependencies
# -----------------------------------------------
echo "[2/5] Installing Composer dependencies..."
composer install --no-interaction --prefer-dist --optimize-autoloader

# -----------------------------------------------
# 3. Generate APP_KEY nếu chưa có
# -----------------------------------------------
echo "[3/5] Checking APP_KEY..."
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "base64:CHANGE_ME" ]; then
    echo "  Generating new APP_KEY..."
    php artisan key:generate --force
else
    echo "  APP_KEY already set. Skipping."
fi

# -----------------------------------------------
# 4. Chạy Database Migration
# -----------------------------------------------
echo "[4/5] Running database migrations..."
php artisan migrate --force

# -----------------------------------------------
# 5. Khởi động PHP-FPM
# -----------------------------------------------
echo "[5/5] Starting PHP-FPM..."
exec php-fpm
