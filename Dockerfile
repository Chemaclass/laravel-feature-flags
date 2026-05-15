FROM php:8.4-cli-alpine

RUN apk add --no-cache \
    git \
    unzip \
    sqlite \
    sqlite-dev \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    autoconf \
    g++ \
    make \
 && docker-php-ext-install \
    pdo \
    pdo_sqlite \
    zip \
    intl \
 && apk del autoconf g++ make

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1 \
    COMPOSER_MEMORY_LIMIT=-1

EXPOSE 8000

CMD ["sh", "-c", "composer install --prefer-dist --no-progress && vendor/bin/testbench workbench:build && vendor/bin/testbench db:seed --class='Workbench\\Database\\Seeders\\DatabaseSeeder' && vendor/bin/testbench serve --host=0.0.0.0 --port=8000"]
