FROM php:8.1-fpm-bookworm AS base

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        libcurl4-openssl-dev \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libonig-dev \
        libpng-dev \
        libxml2-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        curl \
        gd \
        intl \
        mbstring \
        mysqli \
        opcache \
        pdo_mysql \
        xml \
        zip \
    && rm -rf /var/lib/apt/lists/*

RUN pecl install swoole-4.8.13 \
    && docker-php-ext-enable swoole

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY back-end/composer.json back-end/composer.lock ./
RUN composer install \
    --classmap-authoritative \
    --no-ansi \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist

COPY back-end/ ./

RUN mkdir -p cache logs runtime storage/exports storage/tokens uploads \
    && chown -R www-data:www-data cache logs runtime storage uploads

FROM base AS api

USER www-data

EXPOSE 9000

CMD ["php-fpm"]

FROM base AS worker

USER www-data
WORKDIR /var/www/html/myswoole

EXPOSE 9530

CMD ["php", "start.php"]
