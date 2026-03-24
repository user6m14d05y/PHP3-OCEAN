#!/bin/sh
set -e

echo "======================================="
echo " Ocean Backend - Entrypoint Script"
echo "======================================="

# -----------------------------------------------
# 0. Fix quyền cho các thư mục cần ghi (chạy dưới root)
#    Dùng chmod 777 để đảm bảo www-data luôn ghi được
#    kể cả khi volume mount từ host với UID khác.
# -----------------------------------------------
echo "[0/7] Fixing file permissions..."
chown -R www-data:www-data /var/www/vendor 2>/dev/null || true
chown -R www-data:www-data /var/www/storage 2>/dev/null || true
chown -R www-data:www-data /var/www/bootstrap/cache 2>/dev/null || true
chmod -R 775 /var/www/vendor 2>/dev/null || true
chmod -R 777 /var/www/storage 2>/dev/null || true
chmod -R 777 /var/www/bootstrap/cache 2>/dev/null || true

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
ln -sf /var/www/storage/app/public /var/www/public/storage

# -----------------------------------------------
# 5. Chạy Database Migration
# -----------------------------------------------
echo "[5/7] Running database migrations..."
php artisan migrate --force

# -----------------------------------------------
# 6. Khởi động PHP-FPM
# -----------------------------------------------
echo "[6/6] Starting PHP-FPM..."
# Xóa cache cũ đi phòng trường hợp config cache đang làm crash app
php artisan cache:clear || true
php artisan config:clear || true
php artisan route:clear || true
php artisan view:clear || true

exec php-fpm
