FROM dunglas/frankenphp:php8.4

WORKDIR /app

RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    && rm -rf /var/lib/apt/lists/*

RUN install-php-extensions \
    pcntl \
    pdo_pgsql \
    pgsql \
    redis \
    zip \
    opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock artisan ./ 
RUN composer install --no-interaction --prefer-dist --optimize-autoloader

COPY . .

COPY docker/frankenphp/Caddyfile /etc/caddy/Caddyfile
RUN chmod +x /app/docker/entrypoint.sh

ENV SERVER_NAME=:80

ENTRYPOINT ["/app/docker/entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
