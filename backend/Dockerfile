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

# Config PHP cho phép upload file nặng
RUN echo "upload_max_filesize = 50M" > /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size = 50M" >> /usr/local/etc/php/conf.d/uploads.ini

# Lấy Composer version mới nhất
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Cho phép Composer chạy dưới quyền superuser (root) mà không bị cảnh báo
ENV COMPOSER_ALLOW_SUPERUSER=1

# Thiết lập thư mục làm việc mặc định trong container
WORKDIR /var/www

# Copy toàn bộ src code (Bắt buộc cho Production)
# Ở môi trường dev, volume mount trong docker-compose sẽ ghi đè đè lên thư mục này.
COPY . .

# Phân quyền cho www-data TRƯỚC khi mount volume (khi dev volume sẽ đè lên)
RUN chown -R www-data:www-data /var/www

# KHÔNG CHẠY DƯỚI QUYỀN NON-ROOT KHI DEV TRÊN WINDOWS
# Nếu bật USER www-data, trên Windows/WSL2 mount volume sẽ gặp lỗi "Operation not permitted"
# USER www-data
# EXPOSE port (FPM mặc định chạy 9000, không cần declare nhưng nên để cho rõ ràng)
EXPOSE 9000

# Đảm bảo file script entrypoint có quyền thực thi (tránh lỗi Permission denied)
RUN chmod +x /var/www/docker-entrypoint.sh

# Entrypoint script xử lý migrate & start FPM
ENTRYPOINT ["/var/www/docker-entrypoint.sh"]
