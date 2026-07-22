# Stage 1: Composer dependencies
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN docker-php-ext-install -j$(nproc) pcntl && \
    composer install --no-dev --optimize-autoloader --no-interaction

# Stage 2: Node/Tailwind assets
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources ./resources
COPY tailwind.config.js vite.config.js ./
RUN npm run build

# Stage 3: Production PHP-FPM + Nginx
FROM php:8.4-fpm-alpine AS production

RUN apk add --no-cache \
    nginx \
    postgresql-dev \
    libpq \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    oniguruma-dev \
    libxml2-dev \
    libzip-dev \
    supervisor \
    autoconf \
    gcc \
    g++ \
    make \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_pgsql \
        pgsql \
        opcache \
        pcntl \
        gd \
        mbstring \
        xml \
        zip \
        exif \
        bcmath \
    && pecl install redis \
    && docker-php-ext-enable redis

COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/99-geo119.ini
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

WORKDIR /var/www
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build
COPY . .

RUN addgroup -g 1000 www && adduser -u 1000 -G www -D www \
    && mkdir -p /var/lib/nginx/logs /var/lib/nginx/tmp/client_body /var/lib/nginx/tmp/proxy \
    /var/lib/nginx/tmp/fastcgi /var/lib/nginx/tmp/uwsgi /var/lib/nginx/tmp/scgi \
    /var/run/nginx-proxy \
    storage/logs storage/framework/cache/data storage/framework/sessions storage/framework/views \
    bootstrap/cache \
    && chown -R www:www /var/lib/nginx /var/run/nginx-proxy storage bootstrap/cache \
    && chmod -R 775 /var/lib/nginx /var/run/nginx-proxy storage bootstrap/cache

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
