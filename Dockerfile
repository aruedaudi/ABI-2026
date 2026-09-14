# syntax=docker/dockerfile:1

# APP_MODE viene del .env (via compose). Valores: development | production
ARG APP_MODE=production

# ---------- Stage 1: vendor de Composer (para el build de assets) ----------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --ignore-platform-reqs

# ---------- Stage 2: compilar assets con Vite ----------
# tabler-init.js importa JS que vive en vendor/takielias/*, por eso se copia vendor.
FROM node:20-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.js tailwind.config.js postcss.config.js ./
COPY resources ./resources
COPY public ./public
COPY --from=vendor /app/vendor ./vendor
RUN npm run build

# ---------- Stage 3: runtime PHP + Apache ----------
FROM php:8.2-apache AS app
ARG APP_MODE
ENV APP_MODE=${APP_MODE}

# Extensiones PHP + cliente mysql (para migraciones y roles.sql)
COPY --from=mlocati/php-extension-installer:latest /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_mysql gd zip bcmath exif pcntl intl opcache mbstring \
 && apt-get update \
 && apt-get install -y --no-install-recommends default-mysql-client git unzip \
 && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Apache sirve desde public/ y respeta el .htaccess de Laravel
COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
RUN a2enmod rewrite

WORKDIR /var/www/html

# Dependencias PHP segun el modo (development instala dev-deps; production no)
COPY composer.json composer.lock ./
RUN if [ "$APP_MODE" = "production" ]; then \
        composer install --no-dev --optimize-autoloader --no-interaction --no-scripts --prefer-dist ; \
    else \
        composer install --optimize-autoloader --no-interaction --no-scripts --prefer-dist ; \
    fi

# Codigo de la app + assets ya compilados
COPY . .
COPY --from=assets /app/public/build ./public/build

RUN composer dump-autoload --optimize $( [ "$APP_MODE" = "production" ] && echo --no-dev || true ) \
 && php artisan package:discover --ansi || true
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache \
 && chmod -R ug+rwX storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

EXPOSE 80
ENTRYPOINT ["entrypoint"]
CMD ["apache2-foreground"]
