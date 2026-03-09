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

# ── 安裝 Redis 擴充套件（需要 autoconf/gcc 等 build 工具，裝完即刪）──
RUN apk add --no-cache --virtual .build-deps \
    autoconf \
    gcc \
    g++ \
    make \
    musl-dev \
 && pecl install redis \
 && docker-php-ext-enable redis \
 && apk del .build-deps

# ── 安裝 Composer ──
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ── 複製 PHP 設定 ──
COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini

# ── 複製容器啟動腳本 ──
COPY docker/php/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

WORKDIR /var/www/backend

# ── 安裝 Composer 依賴（build-time cache，加速首次啟動）──
# context 為專案根目錄，故使用 backend/ 前綴
COPY backend/composer*.json ./
RUN composer install --no-scripts --no-autoloader --no-interaction --no-progress 2>/dev/null || true

EXPOSE 9000

ENTRYPOINT ["/entrypoint.sh"]
