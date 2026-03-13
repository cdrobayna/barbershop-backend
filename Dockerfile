FROM dunglas/frankenphp:php8.4

WORKDIR /app

RUN install-php-extensions \
    pcntl \
    pdo_pgsql \
    pgsql \
    redis \
    opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --optimize-autoloader

COPY . .

COPY docker/frankenphp/Caddyfile /etc/caddy/Caddyfile
RUN chmod +x /app/docker/entrypoint.sh

ENV SERVER_NAME=:80

ENTRYPOINT ["/app/docker/entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
