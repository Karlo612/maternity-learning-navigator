FROM composer:2 AS php_dependencies
WORKDIR /build
COPY app/composer.json app/composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-scripts --optimize-autoloader

FROM node:24-alpine@sha256:d32cdf619f63fe0471182d08996dd516c6275bb5fd31ae06e55a570bd9e1ad43 AS frontend
WORKDIR /build
COPY app/package.json app/package-lock.json ./
RUN npm ci
COPY app/ ./
RUN npm run build

FROM php:8.4-fpm-alpine@sha256:5992f8b7433fe7fa96dfbf67746c86d6c41bc91e686eac38fe531c72a02e40e4
RUN apk add --no-cache nginx supervisor icu-dev libzip-dev \
    && docker-php-ext-install pdo_mysql intl opcache \
    && mkdir -p /run/nginx
WORKDIR /var/www/html
COPY app/ ./
COPY --from=php_dependencies /build/vendor ./vendor
COPY --from=frontend /build/public/build ./public/build
COPY resources /governance
COPY governance/review_signoffs.json /governance/review_signoffs.json
COPY governance/demo_review_manifest.json /governance/demo_review_manifest.json
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisord.conf
RUN rm -f bootstrap/cache/packages.php bootstrap/cache/services.php \
    && php artisan package:discover --ansi \
    && chown -R www-data:www-data storage bootstrap/cache
EXPOSE 8080
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
