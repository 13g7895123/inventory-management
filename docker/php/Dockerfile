FROM php:8.2-fpm-alpine

# ── 安裝系統相依套件 ──
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    libxml2-dev \
    libzip-dev \
    oniguruma-dev \
    icu-dev \
    zip \
    unzip

# ── 安裝 PHP 擴充套件 ──
RUN docker-php-ext-configure intl \
 && docker-php-ext-install \
    pdo \
    pdo_mysql \
    mysqli \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    intl \
    xml \
    zip

# ── 安裝 Redis 擴充套件 ──
RUN pecl install redis \
 && docker-php-ext-enable redis

# ── 安裝 Composer ──
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ── 複製 PHP 設定 ──
COPY php.ini /usr/local/etc/php/conf.d/custom.ini

WORKDIR /var/www/backend

# ── 安裝 Composer 依賴（在 build 時快取，volume mount 前執行）──
COPY ../../backend/composer*.json ./
RUN composer install --no-scripts --no-autoloader --no-interaction 2>/dev/null || true

EXPOSE 9000

CMD ["php-fpm"]
