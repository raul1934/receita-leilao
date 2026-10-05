FROM php:8.5-fpm

# UID/GID do usuário do host, para que os arquivos criados pelo container
# (vendor/, storage/, logs) não fiquem com dono root em hosts Linux.
ARG UID=1000
ARG GID=1000

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip poppler-utils \
    && docker-php-ext-install pdo_mysql pcntl \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN groupmod -o -g "${GID}" www-data \
    && usermod -o -u "${UID}" -g "${GID}" www-data

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

ENV COMPOSER_HOME=/tmp/composer
WORKDIR /var/www/html
USER www-data

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]
