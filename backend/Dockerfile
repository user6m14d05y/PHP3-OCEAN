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
    default-mysql-client

# Xóa cache apt
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Cài đặt các PHP extensions + OPcache
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd sockets zip opcache

# ==================== PHP PERFORMANCE CONFIG ====================
RUN echo "\
upload_max_filesize = 50M\n\
post_max_size = 50M\n\
memory_limit = 256M\n\
max_execution_time = 60\n\
\n\
; === OPcache - Cache compiled PHP files ===\n\
opcache.enable=1\n\
opcache.memory_consumption=128\n\
opcache.interned_strings_buffer=16\n\
opcache.max_accelerated_files=10000\n\
opcache.validate_timestamps=1\n\
opcache.revalidate_freq=2\n\
opcache.save_comments=1\n\
opcache.enable_file_override=1\n\
\n\
; === Realpath cache ===\n\
realpath_cache_size=4096K\n\
realpath_cache_ttl=600\n\
" > /usr/local/etc/php/conf.d/custom.ini

# ==================== PHP-FPM TUNING ====================
RUN echo "\
[www]\n\
pm = dynamic\n\
pm.max_children = 20\n\
pm.start_servers = 5\n\
pm.min_spare_servers = 3\n\
pm.max_spare_servers = 10\n\
pm.max_requests = 500\n\
pm.process_idle_timeout = 10s\n\
request_terminate_timeout = 60\n\
" > /usr/local/etc/php-fpm.d/zz-tuning.conf

# Lấy Composer version mới nhất
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Cho phép Composer chạy dưới quyền superuser (root) mà không bị cảnh báo
ENV COMPOSER_ALLOW_SUPERUSER=1

# Thiết lập thư mục làm việc mặc định trong container
WORKDIR /var/www

# Copy toàn bộ src code (Bắt buộc cho Production)
COPY . .

# Phân quyền cho www-data TRƯỚC khi mount volume
RUN chown -R www-data:www-data /var/www

EXPOSE 9000

# Đảm bảo entrypoint có quyền thực thi
RUN chmod +x /var/www/docker-entrypoint.sh

ENTRYPOINT ["/var/www/docker-entrypoint.sh"]
