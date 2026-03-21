FROM php:8.2-fpm

# Cài đặt các thư viện hệ thống cần thiết
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    # Thêm default-mysql-client để dùng trong script verify DB connection
    default-mysql-client

# Xóa cache apt
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Cài đặt các PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd sockets zip

# Lấy Composer version mới nhất
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Thiết lập thư mục làm việc mặc định trong container
WORKDIR /var/www

# Copy toàn bộ src code (Bắt buộc cho Production)
# Ở môi trường dev, volume mount trong docker-compose sẽ ghi đè đè lên thư mục này.
COPY . .

# Phân quyền cho www-data TRƯỚC khi chuyển user
RUN chown -R www-data:www-data /var/www

# EXPOSE port (FPM mặc định chạy 9000, không cần declare nhưng nên để cho rõ ràng)
EXPOSE 9000

# Entrypoint script xử lý migrate & start FPM
ENTRYPOINT ["/var/www/docker-entrypoint.sh"]
