FROM php:8.4-cli-alpine

RUN apk add --no-cache \
    git \
    unzip \
    sqlite \
    sqlite-dev \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    curl \
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

# Cache composer deps as their own layer: only rebuilds when manifests change.
COPY composer.json composer.lock ./
RUN composer install \
    --prefer-dist \
    --no-progress \
    --no-scripts \
    --no-autoloader \
 && rm -rf /root/.composer/cache

# Source comes in last so editing app code doesn't bust the deps layer.
COPY . .

RUN composer dump-autoload --optimize \
 && composer run-script post-autoload-dump || true

EXPOSE 8000

HEALTHCHECK --interval=5s --timeout=3s --start-period=20s --retries=20 \
    CMD curl -fsS http://localhost:8000/admin/feature-flags > /dev/null || exit 1

CMD ["sh", "-c", "\
    vendor/bin/testbench workbench:build && \
    vendor/bin/testbench db:seed --class='Workbench\\Database\\Seeders\\DatabaseSeeder' && \
    echo '' && \
    echo '====================================================' && \
    echo '  Demo ready: http://localhost:8000/admin/feature-flags' && \
    echo '  Auto-login as demo@example.com (tenant-A)' && \
    echo '====================================================' && \
    echo '' && \
    vendor/bin/testbench serve --host=0.0.0.0 --port=8000 \
"]
