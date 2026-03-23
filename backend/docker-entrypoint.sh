#!/bin/sh
set -e

echo "======================================="
echo " Ocean Backend - Entrypoint Script"
echo "======================================="

# -----------------------------------------------
# 0. Fix quyền cho các thư mục cần ghi (chạy dưới root)
# -----------------------------------------------
echo "[0/7] Fixing file permissions..."
chown -R www-data:www-data /var/www/vendor 2>/dev/null || true
chown -R www-data:www-data /var/www/storage 2>/dev/null || true
chown -R www-data:www-data /var/www/bootstrap/cache 2>/dev/null || true
chmod -R 775 /var/www/vendor 2>/dev/null || true
chmod -R 775 /var/www/storage 2>/dev/null || true
chmod -R 775 /var/www/bootstrap/cache 2>/dev/null || true

# -----------------------------------------------
# 1. Chờ MySQL sẵn sàng (retry loop)
# -----------------------------------------------
echo "[1/7] Waiting for MySQL to be ready..."
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
echo "[2/7] Installing Composer dependencies..."
composer install --no-interaction --prefer-dist --optimize-autoloader

# -----------------------------------------------
# 3. Generate APP_KEY nếu chưa có
# -----------------------------------------------
echo "[3/7] Checking APP_KEY..."
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "base64:CHANGE_ME" ]; then
    echo "  Generating new APP_KEY..."
    php artisan key:generate --force
else
    echo "  APP_KEY already set. Skipping."
fi

# -----------------------------------------------
# 4. Tạo Storage Symlink (public/storage -> storage/app/public)
# -----------------------------------------------
echo "[4/7] Creating storage link..."
php artisan storage:link --force 2>/dev/null || true

# -----------------------------------------------
# 5. Chạy Database Migration
# -----------------------------------------------
echo "[5/7] Running database migrations..."
php artisan migrate --force

# -----------------------------------------------
# 6. Xóa cache (Quan trọng cho môi trường Dev)
# -----------------------------------------------
echo "[6/7] Clearing Laravel caches for development..."
php artisan config:clear
php artisan route:clear
php artisan view:clear
echo "  Laravel caches cleared successfully!"

# -----------------------------------------------
# 7. Khởi động PHP-FPM
# -----------------------------------------------
echo "[7/7] Starting PHP-FPM..."
exec php-fpm
