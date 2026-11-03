# Imágenes de Clave de Alma.
#   target runtime → PHP-FPM (app, worker y scheduler)
#   target web     → Nginx con los archivos públicos compilados

# 1) Base: PHP con las extensiones que necesita la aplicación
FROM php:8.3-fpm-alpine AS base

RUN apk add --no-cache icu-libs libzip libpng libjpeg-turbo libwebp freetype \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev libzip-dev libpng-dev \
        libjpeg-turbo-dev libwebp-dev freetype-dev linux-headers \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql intl zip gd bcmath opcache pcntl exif \
    && pecl install redis && docker-php-ext-enable redis \
    && apk del .build-deps

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-clavedealma.ini
WORKDIR /var/www/html

# 2) Dependencias PHP y recursos del panel (Filament)
FROM base AS vendor
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction
COPY . .
RUN composer dump-autoload --optimize --no-dev \
    && php artisan package:discover --ansi \
    && php artisan filament:assets

# 3) CSS y JS de la tienda
FROM node:22-alpine AS assets
WORKDIR /var/www/html
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
COPY --from=vendor /var/www/html/vendor ./vendor
RUN npm run build

# 4) Imagen de la aplicación
FROM base AS runtime
COPY --chown=www-data:www-data --from=vendor /var/www/html /var/www/html
COPY --chown=www-data:www-data --from=assets /var/www/html/public/build ./public/build
RUN rm -rf .env docker tests \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views \
        storage/logs storage/app/public bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache
USER www-data
CMD ["php-fpm"]

# 5) Imagen web (Nginx)
FROM nginx:1.27-alpine AS web
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=vendor /var/www/html/public /var/www/html/public
COPY --from=assets /var/www/html/public/build /var/www/html/public/build
