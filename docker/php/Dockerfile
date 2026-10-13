ARG PHP_VERSION=8.5
FROM php:${PHP_VERSION}-fpm-bookworm AS runtime
ARG LOCAL_UID=1000
ARG LOCAL_GID=1000
RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip curl cron libicu-dev libzip-dev libpng-dev libjpeg62-turbo-dev \
    libfreetype6-dev libxml2-dev libxslt1-dev libonig-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j2 bcmath gd intl pdo_mysql mysqli soap sockets zip xsl pcntl exif ftp \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2.10 /usr/bin/composer /usr/local/bin/composer
RUN groupadd -g ${LOCAL_GID} shi3ld && useradd -m -u ${LOCAL_UID} -g shi3ld shi3ld \
    && mkdir -p /tmp/composer && chown -R shi3ld:shi3ld /tmp/composer
COPY docker/php/development.ini /usr/local/etc/php/conf.d/shi3ld.ini
ENV COMPOSER_HOME=/tmp/composer
WORKDIR /workspace
USER shi3ld
FROM runtime AS magento
WORKDIR /workspace/magento
FROM runtime AS laravel
WORKDIR /workspace/laravel
