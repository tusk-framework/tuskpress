FROM ghcr.io/tusk-framework/tusk-engine:v0.3.4 AS tusk-engine

FROM ghcr.io/roadrunner-server/roadrunner:v2025.1.15 AS roadrunner

FROM composer:2 AS composer

FROM php:8.3-cli-bookworm

COPY --from=tusk-engine /usr/local/bin/tusk /usr/local/bin/tusk
COPY --from=roadrunner /usr/bin/rr /usr/bin/rr
COPY --from=composer /usr/bin/composer /usr/bin/composer

RUN apt-get update \
    && apt-get install -y --no-install-recommends libonig-dev \
    && docker-php-ext-install -j"$(nproc)" mbstring pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

COPY . .
RUN composer check-platform-reqs --no-dev

EXPOSE 8080

CMD ["tusk", "start"]
