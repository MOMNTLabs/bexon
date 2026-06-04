FROM dunglas/frankenphp:1-php8.3

RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev \
    && docker-php-ext-install pgsql pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY . /app
COPY Caddyfile /etc/caddy/Caddyfile
COPY docker-entrypoint.sh /usr/local/bin/bexon-entrypoint

RUN mkdir -p /app/storage \
    && chown -R www-data:www-data /app/storage \
    && chmod +x /usr/local/bin/bexon-entrypoint

EXPOSE 8080

CMD ["/usr/local/bin/bexon-entrypoint"]

