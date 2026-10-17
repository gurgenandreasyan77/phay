# syntax=docker/dockerfile:1

# ---- shared PHP base: extensions needed by the Laravel API -----------------
FROM php:8.3-fpm-alpine AS php-base
WORKDIR /var/www/html

RUN apk add --no-cache libpq fcgi libpng libjpeg-turbo freetype \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS postgresql-dev libpng-dev libjpeg-turbo-dev freetype-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_pgsql pgsql bcmath opcache gd \
    && apk del .build-deps

COPY --from=configs php/php.ini /usr/local/etc/php/conf.d/99-servio.ini
COPY --from=configs php/fpm-pool.conf /usr/local/etc/php-fpm.d/zz-servio.conf

# ---- dev: source is bind-mounted at runtime, vendor/ comes from the host --
FROM php-base AS dev
ENV APP_ENV=local
EXPOSE 9000
HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD sh -c 'SCRIPT_NAME=/ping SCRIPT_FILENAME=/ping REQUEST_METHOD=GET cgi-fcgi -bind -connect 127.0.0.1:9000 | grep -q pong'
CMD ["php-fpm"]

# ---- composer binary, reused to install dependencies for prod builds ------
FROM composer:2 AS composer

# ---- vendor: install production dependencies without dev tooling ----------
FROM php-base AS vendor
COPY --from=composer /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

# ---- app: full application code + optimized autoloader --------------------
FROM vendor AS app
COPY . .
RUN composer dump-autoload --optimize --no-dev \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/testing storage/framework/views storage/logs \
    && chown -R www-data:www-data storage bootstrap/cache

# ---- php: production PHP-FPM runtime (workers already run as www-data) ----
FROM php-base AS php
COPY --from=configs php/opcache.prod.ini /usr/local/etc/php/conf.d/99-opcache.ini
COPY --from=app --chown=www-data:www-data /var/www/html /var/www/html
EXPOSE 9000
HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD sh -c 'SCRIPT_NAME=/ping SCRIPT_FILENAME=/ping REQUEST_METHOD=GET cgi-fcgi -bind -connect 127.0.0.1:9000 | grep -q pong'
CMD ["php-fpm"]

# ---- nginx: serves /public and proxies *.php to the php-fpm container -----
FROM nginx:alpine AS nginx
COPY --from=configs production/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=app /var/www/html/public /var/www/html/public
EXPOSE 80
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD wget --no-verbose --tries=1 --spider http://127.0.0.1/api/health || exit 1
