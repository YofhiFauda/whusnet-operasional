# ── Tahap aset: build Vite (CSS Tailwind + JS termasuk Alpine) ──
#
# Sejak Alpine dicabut dari CDN dan ikut `resources/js/app.js`, `public/build`
# BUKAN lagi sekadar optimasi — tanpa manifest hasil build, Laravel melempar
# ViteException dan seluruh interaksi Alpine (dropdown, drawer, filter) mati.
# `public/build` ada di .gitignore, jadi tidak ikut `COPY . /var/www`; harus
# dibangun di sini supaya image tidak bergantung pada host yang kebetulan
# sudah menjalankan `npm run build`.
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
# Seluruh repo disalin (bukan cuma resources/): Tailwind v4 memindai kelas dari
# Blade di resources/views DAN string kelas di app/ (komponen, helper tabel).
COPY . .
RUN npm run build

FROM php:8.4-fpm

# Install system dependencies
#
# `procps` (pgrep) dan `netcat-openbsd` (nc) dipakai HEALTHCHECK di
# docker-compose.yml untuk worker horizon/scheduler/reverb. Tanpa keduanya
# healthcheck gagal dengan "pgrep: not found" / "nc: not found" — container
# ditandai unhealthy padahal prosesnya jalan normal, sehingga kegagalan
# scheduler yang sebenarnya (tagihan bulanan tak terbit) tidak terlihat.
#
# `poppler-utils` (pdftoppm) dipakai PdfPageRasterizer untuk membaca QR dari
# kwitansi ber-PDF. SENGAJA bukan Imagick: Debian mematikan coder PDF di
# policy.xml ImageMagick secara default, jadi jalur itu gagal dengan pesan
# "not authorized" yang tak ada hubungannya dengan kwitansi.
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libjpeg62-turbo-dev \
    libwebp-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    libicu-dev \
    libzip-dev \
    zip \
    unzip \
    autoconf \
    gcc \
    make \
    procps \
    netcat-openbsd \
    poppler-utils \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-configure intl \
    && docker-php-ext-install \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        intl \
        zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*


    # Install Composer (latest version with security fixes)
COPY --from=composer:2.10 /usr/bin/composer /usr/bin/composer

# Custom PHP settings
# COPY ./docker/php/local.ini /usr/local/etc/php/conf.d/local.ini

# Set working directory
WORKDIR /var/www

# Copy composer files (for caching layer)
# COPY composer.json composer.lock ./


# Copy application
# COPY . .
COPY . /var/www

# Aset hasil tahap `assets`. Ditaruh SETELAH `COPY . /var/www` supaya build di
# sini selalu menang atas `public/build` basi yang mungkin tertinggal di host.
COPY --from=assets /app/public/build /var/www/public/build


# Ensure all required storage directories exist
RUN mkdir -p storage/logs \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 777 storage bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]