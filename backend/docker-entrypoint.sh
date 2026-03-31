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
mkdir -p /var/www/storage/tmp

# -----------------------------------------------
# 2. Đợi MySQL (Giữ nguyên logic PHP PDO của bạn)
# -----------------------------------------------
echo "[2/7] Waiting for MySQL..."
MAX_TRIES=30
COUNT=0
while [ "$COUNT" -lt "$MAX_TRIES" ]; do
    if php -r "try { new PDO('mysql:host='.getenv('DB_HOST').';port='.(getenv('DB_PORT')?:'3306'), getenv('DB_USERNAME'), getenv('DB_PASSWORD'), [PDO::ATTR_TIMEOUT=>3]); echo 'OK'; } catch (Exception \$e) { exit(1); }" 2>/dev/null; then
        echo "  MySQL is ready!"
        break
    fi
    COUNT=$((COUNT + 1))
    echo "  Retrying ($COUNT/$MAX_TRIES)..."
    sleep 2
done

# -----------------------------------------------
# 3. Composer install
# -----------------------------------------------
echo "[3/7] Installing Composer dependencies..."
cd /var/www
# Chạy composer với tư cách root để tránh lỗi permission lúc ghi vendor
composer install --no-interaction --prefer-dist --optimize-autoloader

# -----------------------------------------------
# 4. FIX QUYỀN TRIỆT ĐỂ (QUAN TRỌNG NHẤT)
# -----------------------------------------------
echo "[4/7] Fixing permissions for Storage & Cache..."
# Đảm bảo www-data sở hữu toàn bộ code để tránh xung đột với máy host
chown -R www-data:www-data /var/www/storage || true
chown -R www-data:www-data /var/www/bootstrap/cache || true
chown -R www-data:www-data /var/www/vendor || true

# Cấp quyền 777 để cả owner (www-data) và others (do mount volume Windows) đều có quyền ghi
find /var/www/storage -type d -exec chmod 777 {} + || true
find /var/www/storage -type f -exec chmod 666 {} + || true
find /var/www/bootstrap/cache -type d -exec chmod 777 {} + || true

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
php artisan migrate --force --no-interaction || echo "WARNING: Migration failed."

# -----------------------------------------------
# 7. Start PHP-FPM and Reverb
# -----------------------------------------------
echo "[7/7] Starting Reverb and PHP-FPM..."
echo "======================================="
echo " Backend READY on port 9000"
echo " WebSocket (Reverb) READY on port 8383"
echo "======================================="

# Khởi chạy Reverb WebSocket Server chạy ngầm (Background) và gắn nohup để không bị kill khi exec php-fpm
nohup php artisan reverb:start --host="0.0.0.0" --port=8383 > /var/www/storage/logs/reverb.log 2>&1 &

# Thực thi PHP-FPM
exec php-fpm
