# ═══════════════════════════════════════════════════════════════════════════
#  Dockerfile PRODUKSI — dipakai Coolify (build dari git, bukan bind-mount)
# ═══════════════════════════════════════════════════════════════════════════
#
# BEDA FUNDAMENTAL dari `Dockerfile` (dev):
# - Dev: `./:/var/www` di-bind-mount, jadi image cuma perlu berisi vendor/aset
#   "cadangan" — kode aslinya selalu datang dari host.
# - Prod (Coolify): TIDAK ADA bind-mount. Image harus SELF-CONTAINED — kode,
#   vendor, DAN public/build semua dibakar ke image saat build. Kalau salah
#   satu ketinggalan, container jalan dengan kode/aset basi tanpa error yang
#   jelas (beda dari dev yang langsung ketahuan lewat bind-mount).
#
# Tiga tahap:
#   1. `assets`   — build Vite (Tailwind + Alpine bundle)
#   2. `vendor`   — composer install (dipisah biar cache layer composer tidak
#                   invalidasi tiap kali cuma resources/ yang berubah)
#   3. runtime    — image akhir: PHP-FPM + kode + vendor + aset, non-root

FROM node:26-alpine AS assets

WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
# Tailwind v4 memindai kelas dari Blade (resources/views) DAN string kelas di
# app/ (komponen tabel dsb) — seluruh repo perlu ikut disalin, bukan cuma resources/.
COPY . .
# Vite membaca VITE_* dari process.env SAAT BUILD (`import.meta.env` di-inline
# ke bundle), bukan runtime — env var container `app` (VITE_REVERB_APP_KEY di
# docker-compose.prod.yml) datang TERLALU LAMBAT, sudah lewat masa build image.
# Wajib masuk lewat --build-arg supaya resources/js/echo.js dapat app key yang
# benar, bukan undefined. Server-side (Laravel) tetap baca REVERB_APP_KEY
# biasa dari env container — dua jalur ini sengaja terpisah.
ARG VITE_REVERB_APP_KEY
ENV VITE_REVERB_APP_KEY=${VITE_REVERB_APP_KEY}
RUN npm run build

FROM composer:2.10 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
# --no-scripts: artisan belum ada (kode app belum di-copy tahap ini), semua
# composer script (package:discover dst) yang butuh bootstrap Laravel bakal
# gagal. package:discover dijalankan ulang di entrypoint setelah kode lengkap.
# --ignore-platform-reqs: image `composer` tidak punya ext-pcntl (disyaratkan
# Horizon) dkk — ekstensi itu baru dipasang di tahap `runtime`, tempat vendor/
# ini benar-benar dijalankan. Tanpa flag ini build berhenti di sini dengan
# "laravel/horizon requires ext-pcntl". Cek ekstensi tetap terjadi saat runtime
# lewat vendor/composer/platform_check.php.
RUN composer install --no-dev --no-interaction --no-progress --no-scripts --optimize-autoloader --no-autoloader --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative --no-scripts --ignore-platform-reqs

FROM php:8.5-fpm-trixie AS runtime

# `procps`/`netcat-openbsd` — dipakai HEALTHCHECK worker (horizon/reverb/scheduler)
# di docker-compose.prod.yml, sama alasannya dengan Dockerfile dev.
# `poppler-utils` (pdftoppm) — PdfPageRasterizer baca QR dari kwitansi PDF.
RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    libpng-dev \
    libjpeg62-turbo-dev \
    libwebp-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    libicu-dev \
    libzip-dev \
    unzip \
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

COPY --from=composer:2.10 /usr/bin/composer /usr/bin/composer
COPY docker/php/production.ini /usr/local/etc/php/conf.d/zz-production.ini

# Pool PHP-FPM: bawaan image cuma 5 worker. Nilai di bawah default untuk server
# ±2 GB RAM; setel lewat env di Coolify (lihat catatan di docker/php/fpm-pool.conf).
ENV FPM_MAX_CHILDREN=20 \
    FPM_START_SERVERS=6 \
    FPM_MIN_SPARE_SERVERS=4 \
    FPM_MAX_SPARE_SERVERS=10
COPY docker/php/fpm-pool.conf /usr/local/etc/php-fpm.d/zz-pool.conf

WORKDIR /var/www

# Kode aplikasi (tanpa vendor/ — datang dari tahap `vendor` di bawah;
# .dockerignore juga sudah exclude node_modules, vendor, public/build).
COPY . /var/www
COPY --from=vendor /app/vendor /var/www/vendor
COPY --from=assets /app/public/build /var/www/public/build

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

RUN mkdir -p storage/logs \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/app/public \
        bootstrap/cache \
    && chown -R www-data:www-data /var/www \
    && chmod -R 775 storage bootstrap/cache

USER www-data

EXPOSE 9000
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php-fpm"]

# ── Stage `web` — image nginx terpisah, dipakai service `nginx` di compose ──
#
# Dibangun dari Dockerfile YANG SAMA (docker-compose.prod.yml pakai
# `target: web`) supaya tahap `assets` di atas dipakai bareng lewat build
# cache Docker — bukan build Vite dua kali.
#
# public/build dibakar langsung ke image ini (bukan lewat volume dari service
# `app`) karena tanpa bind-mount di prod, satu-satunya cara nginx & php-fpm
# melihat aset build yang SAMA adalah masing-masing bawa salinannya sendiri
# dari tahap `assets` yang identik. /storage/ tetap lewat volume `storage_data`
# yang di-share dengan app — itu berkas RUNTIME (upload), bukan hasil build.
FROM nginx:1.31-alpine AS web
COPY docker/nginx/default.prod.conf /etc/nginx/conf.d/default.conf
# Seluruh public/ (minus build/hot/storage — sudah di-exclude .dockerignore)
# dibakar langsung, bukan file per file. Dulu cuma favicon.ico/robots.txt
# yang ke-copy manual: logo.svg, favicon.svg/png, public/images/ ketinggalan
# → 404 di nginx, logo gak tampil. index.php juga WAJIB ikut (lihat bawah)
# walau isinya gak pernah dieksekusi di sini.
COPY public/ /var/www/public/
# Overlay hasil build Vite PALING TERAKHIR — pastikan public/build yang
# kepakai punya aset fresh dari stage `assets`, bukan sisa apa pun dari COPY
# public/ di atas (harusnya kosong karena .dockerignore, tapi jangan andalkan
# urutan itu diam-diam).
COPY --from=assets /app/public/build /var/www/public/build
# Catatan index.php (ikut ter-copy lewat `COPY public/` di atas): nginx di
# sini TIDAK PERNAH mengeksekusinya (cuma fastcgi_pass ke app:9000), tapi
# filenya WAJIB ADA secara fisik — directive `index index.php ...;` di
# default.prod.conf butuh file ini KETEMU di filesystem buat bikin internal
# redirect ke location ~ \.php$. Tanpa ini: request "/" nemu direktori root
# (ada), nginx coba resolve index, index.php/index.html gak ketemu, autoindex
# off → 403 Forbidden duluan sebelum sempat sampai fallback try_files.
EXPOSE 80
