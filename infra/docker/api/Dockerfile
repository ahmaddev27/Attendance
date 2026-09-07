# syntax=docker/dockerfile:1
#
# TAQAT API — Laravel 11 (PHP 8.4)
# Build context: ./apps/api  (see docker-compose.yml / docker-compose.prod.yml)
#
# NOTE: PHP 8.4 is required — symfony/clock 8.1 and nesbot/carbon 3.13 (locked
# in composer.lock via laravel/framework 11) both hard-require PHP >= 8.4.1.

############################################
# Stage 1 — composer: install PHP dependencies
############################################
FROM php:8.4-cli-alpine AS composer_build

# mbstring + zip are needed just to run `composer install` for a Laravel app:
# laravel/framework's composer.json hard-requires ext-mbstring, and several
# common packages (and Laravel itself) require ext-zip.
RUN apk add --no-cache git unzip libzip-dev oniguruma-dev ${PHPIZE_DEPS} \
    && docker-php-ext-install mbstring zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Install dependencies before copying the rest of the app so this (slow) layer
# stays cached as long as composer.json/composer.lock don't change.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .
RUN composer install --no-dev --optimize-autoloader --prefer-dist --no-interaction

############################################
# Stage 2 — runtime: PHP 8.4 FPM Alpine
############################################
FROM php:8.4-fpm-alpine AS runtime

# --- System libraries + PHP extensions --------------------------------------
# pdo_mysql, gd, zip, bcmath, opcache, exif, pcntl: required by spec.
# mbstring: required by laravel/framework itself (composer.json + runtime).
#
# NOTE: we deliberately do NOT install the phpredis extension. The API uses
# the pure-PHP `predis/predis` client (REDIS_CLIENT=predis) so we skip the
# `pecl install redis` step — pecl often fails to resolve a release compatible
# with the current PHP version (No releases available), and predis matches
# Laravel's Redis facade one-for-one.
RUN apk add --no-cache \
        libzip \
        libpng \
        libjpeg-turbo \
        freetype \
        oniguruma \
        rsync \
        tzdata \
    && apk add --no-cache --virtual .build-deps \
        ${PHPIZE_DEPS} \
        libzip-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        oniguruma-dev \
        linux-headers \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        mbstring \
        gd \
        zip \
        bcmath \
        exif \
        pcntl \
        opcache \
    && apk del .build-deps \
    && rm -rf /tmp/pear /var/cache/apk/*

# --- PHP-FPM listen address --------------------------------------------------
# The default php-fpm config binds to 127.0.0.1:9000, but nginx (in another
# container) reaches us over the Docker network — that needs a TCP listener on
# all interfaces. Same as `listen = 0.0.0.0:9000` in www.conf.
RUN sed -i 's|^listen = .*|listen = 0.0.0.0:9000|' /usr/local/etc/php-fpm.d/www.conf

ENV TZ=Asia/Amman

# --- PHP runtime configuration -----------------------------------------------
RUN { \
        echo "memory_limit=256M"; \
        echo "upload_max_filesize=32M"; \
        echo "post_max_size=32M"; \
        echo "max_execution_time=120"; \
        echo "date.timezone=${TZ}"; \
    } > /usr/local/etc/php/conf.d/90-taqat.ini \
    && { \
        echo "opcache.enable=1"; \
        echo "opcache.enable_cli=0"; \
        echo "opcache.memory_consumption=192"; \
        echo "opcache.interned_strings_buffer=16"; \
        echo "opcache.max_accelerated_files=20000"; \
        echo "opcache.jit=tracing"; \
        echo "opcache.jit_buffer_size=64M"; \
    } > /usr/local/etc/php/conf.d/91-opcache.ini

# --- Application code ---------------------------------------------------------
# Shipped only at /var/www/html-dist (never baked directly at /var/www/html)
# so the final image doesn't carry two copies of vendor/. /var/www/html itself
# is populated by entrypoint.sh at container start:
#   - dev:  bind-mounted over by docker-compose.yml -> entrypoint leaves it alone
#   - prod: docker-compose.prod.yml mounts /var/www/html as a named volume
#           shared read-only with nginx (so nginx can reach public/index.php
#           without ever bind-mounting live source) -> entrypoint (re)seeds it
#           from html-dist on every start, keeping storage/ untouched
WORKDIR /var/www/html-dist
COPY --from=composer_build /var/www/html /var/www/html-dist
RUN chown -R www-data:www-data /var/www/html-dist \
    && chmod -R 775 /var/www/html-dist/storage /var/www/html-dist/bootstrap/cache

WORKDIR /var/www/html

RUN cat <<'EOF' > /usr/local/bin/entrypoint.sh
#!/bin/sh
set -e

if [ "$APP_ENV" = "production" ]; then
    # Production never bind-mounts live source. (Re)seed the runtime path from
    # the immutable image copy on every start, excluding storage/ so
    # logs/cache/sessions persist across deploys and container recreations.
    rsync -a --delete --exclude='storage' /var/www/html-dist/ /var/www/html/
    mkdir -p \
        /var/www/html/storage/framework/cache \
        /var/www/html/storage/framework/sessions \
        /var/www/html/storage/framework/views \
        /var/www/html/storage/logs \
        /var/www/html/storage/app/public
    chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
    chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache
    # Safe to disable filesystem checks: the image is immutable at runtime.
    echo "opcache.validate_timestamps=0" > /usr/local/etc/php/conf.d/99-opcache-env.ini
else
    # Local/dev: /var/www/html is a bind mount of the host source tree (see
    # docker-compose.yml) — never overwrite it. Keep opcache checking file
    # mtimes so edits are picked up without restarting the container.
    echo "opcache.validate_timestamps=1" > /usr/local/etc/php/conf.d/99-opcache-env.ini
fi

exec "$@"
EOF
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
