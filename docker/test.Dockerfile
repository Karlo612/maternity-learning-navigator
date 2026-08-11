FROM composer:2

WORKDIR /workspace/app

COPY app/composer.json app/composer.lock ./
RUN composer install --prefer-dist --no-interaction --no-scripts

COPY app/ ./
RUN touch .env
COPY resources /workspace/resources
COPY governance /workspace/governance

CMD ["php", "artisan", "test"]
