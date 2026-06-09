FROM php:8.4-fpm

# Install system dependencies
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