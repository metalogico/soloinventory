FROM node:24-bookworm-slim@sha256:55fb5f5a527ea2c5c4a95f963e48ef90aa38c7b346054fdc8ee9957a2b55d205 AS frontend
WORKDIR /build
COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts
COPY vite.config.js ./
COPY resources ./resources
RUN npm run build

FROM php:8.4-apache-bookworm@sha256:e653ff90d7f6e2e6f81eddb07c864f1eae224ccbf5143f82a44f25e2f110e320 AS php-base
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libonig-dev \
        libsqlite3-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libwebp-dev \
        unzip \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install -j2 pdo_sqlite mbstring gd opcache \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2@sha256:b6dcd72ca98fa19d0e5b8476de179859704891fad06dd9e0b96833d4c430a5fc /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/html

FROM php-base AS dependencies
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-autoloader
COPY app ./app
COPY bootstrap ./bootstrap
COPY config ./config
COPY routes ./routes
COPY database ./database
COPY artisan ./
RUN composer dump-autoload --no-dev --optimize --no-scripts

FROM php-base AS app
COPY --chown=www-data:www-data . .
COPY --from=dependencies --chown=www-data:www-data /var/www/html/vendor ./vendor
COPY --from=frontend --chown=www-data:www-data /build/public/build ./public/build
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/soloinventory.ini
RUN sed -i 's/Listen 80/Listen 8080/' /etc/apache2/ports.conf \
    && mkdir -p storage/app/private/soloinventory/photos storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs /backups \
    && chown -R www-data:www-data storage bootstrap/cache /backups \
    && chmod +x docker/entrypoint.sh
USER www-data
EXPOSE 8080
ENTRYPOINT ["/var/www/html/docker/entrypoint.sh"]
CMD ["apache2-foreground"]
