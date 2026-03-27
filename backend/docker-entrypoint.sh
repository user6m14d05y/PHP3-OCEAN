#!/bin/sh
set -e

echo "======================================="
echo " Ocean Backend - Entrypoint Script"
echo "======================================="

# -----------------------------------------------
# 1. Khởi tạo cấu trúc thư mục cơ bản
# -----------------------------------------------
echo "[1/7] Preparing directory structure..."
mkdir -p /var/www/storage/app/public/thumbnails
mkdir -p /var/www/storage/framework/cache/data
mkdir -p /var/www/storage/framework/sessions
mkdir -p /var/www/storage/framework/views
mkdir -p /var/www/storage/logs
mkdir -p /var/www/bootstrap/cache

# -----------------------------------------------
# 2. Đợi MySQL (Giữ nguyên logic PHP PDO của bạn)
# -----------------------------------------------
echo "[2/7] Waiting for MySQL..."
MAX_TRIES=30
COUNT=0
MYSQL_READY=false
while [ "$COUNT" -lt "$MAX_TRIES" ]; do
    if php -r "try { new PDO('mysql:host='.getenv('DB_HOST').';port='.(getenv('DB_PORT')?:'3306'), getenv('DB_USERNAME'), getenv('DB_PASSWORD'), [PDO::ATTR_TIMEOUT=>3]); echo 'OK'; } catch (Exception \$e) { exit(1); }" 2>/dev/null; then
        echo "  MySQL is ready!"
        MYSQL_READY=true
        break
    fi
    COUNT=$((COUNT + 1))
    echo "  Retrying ($COUNT/$MAX_TRIES)..."
    sleep 2
done

if [ "$MYSQL_READY" = false ]; then
    echo "ERROR: MySQL not ready after $MAX_TRIES attempts. Exiting."
    exit 1
fi

# -----------------------------------------------
# 3. Composer install
# -----------------------------------------------
echo "[3/7] Installing Composer dependencies..."
cd /var/www

# Chạy composer với tư cách root để tránh lỗi permission lúc ghi vendor
if ! composer install --no-interaction --prefer-dist --optimize-autoloader; then
    echo "ERROR: Composer install failed! Exiting."
    exit 1
fi

# Kiểm tra autoload tồn tại
if [ ! -f /var/www/vendor/autoload.php ]; then
    echo "ERROR: vendor/autoload.php not found after composer install! Exiting."
    exit 1
fi

# -----------------------------------------------
# 4. FIX QUYỀN TRIỆT ĐỂ (QUAN TRỌNG NHẤT)
# -----------------------------------------------
echo "[4/7] Fixing permissions for Storage & Cache..."
# Đảm bảo www-data sở hữu toàn bộ code để tránh xung đột với máy host
chown -R www-data:www-data /var/www/storage
chown -R www-data:www-data /var/www/bootstrap/cache
chown -R www-data:www-data /var/www/vendor

# Cấp quyền 775 để cả owner (www-data) và group đều có quyền ghi
find /var/www/storage -type d -exec chmod 775 {} +
find /var/www/storage -type f -exec chmod 664 {} +
find /var/www/bootstrap/cache -type d -exec chmod 775 {} +

# -----------------------------------------------
# 5. Laravel Setup (Key, Link, Cache)
# -----------------------------------------------
echo "[5/7] Laravel setup tasks..."
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "base64:CHANGE_ME" ]; then
    php artisan key:generate --force
fi

# Link storage (Xóa link cũ nếu sai và tạo lại)
php artisan storage:link --force || true

# Clear cache để nhận diện permission mới
php artisan config:clear
php artisan cache:clear

# -----------------------------------------------
# 6. Database migration
# -----------------------------------------------
echo "[6/7] Running migrations..."
if ! php artisan migrate --force --no-interaction; then
    echo "WARNING: Migration failed, but continuing to start PHP-FPM..."
fi

# -----------------------------------------------
# 7. Start PHP-FPM
# -----------------------------------------------
echo "[7/7] Starting PHP-FPM..."
echo "======================================="
echo " Backend READY on port 9000"
echo "======================================="

# Thực thi PHP-FPM
exec php-fpm