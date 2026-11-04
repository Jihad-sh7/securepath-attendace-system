FROM php:8.4-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libzip-dev libicu-dev libonig-dev libxml2-dev libsqlite3-dev \
    && docker-php-ext-install bcmath dom intl mbstring pcntl pdo_mysql pdo_sqlite xml xmlwriter zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY composer.json ./
RUN composer install --no-interaction --prefer-dist --no-scripts
COPY . .
RUN composer dump-autoload --optimize \
    && mkdir -p storage/app/private storage/framework/cache/data storage/framework/sessions storage/framework/testing storage/framework/views storage/logs \
    && chmod -R 777 storage bootstrap/cache

EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
