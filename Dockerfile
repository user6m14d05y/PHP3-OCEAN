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
    libzip-dev

# Xóa cache apt
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Cài đặt các PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd sockets zip

# Lấy Composer version mới nhất
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Thiết lập thư mục làm việc mặc định trong container
WORKDIR /var/www

# Copy code backend vào container (Nếu cần build cho production)
# COPY backend/ .

# Phân quyền cho www-data
RUN chown -R www-data:www-data /var/www
