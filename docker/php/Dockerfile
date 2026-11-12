FROM php:8.3-fpm

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libicu-dev libzip-dev \
    && docker-php-ext-install intl pdo_mysql zip opcache \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1

COPY php.ini /usr/local/etc/php/conf.d/app.ini
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
# sed : supprime d'éventuels retours chariot Windows (CRLF) qui casseraient le script
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint.sh && chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /app
ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
