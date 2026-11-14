FROM php:8.4-apache

ARG UID=1000
ARG GID=1000

# System packages and PHP extensions
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git unzip openssl libicu-dev libzip-dev \
    && docker-php-ext-install -j"$(nproc)" intl pdo_mysql zip opcache \
    && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Run Apache as the host user so bind-mounted files stay writable
RUN groupmod -o -g "${GID}" www-data && usermod -o -u "${UID}" -g "${GID}" www-data

# Self-signed certificate for HTTPS
RUN openssl req -x509 -nodes -newkey rsa:2048 -days 3650 \
        -subj "/CN=localhost" \
        -addext "subjectAltName=DNS:localhost,IP:127.0.0.1" \
        -keyout /etc/ssl/private/freedom-analytics.key \
        -out /etc/ssl/certs/freedom-analytics.crt

# Apache configuration
COPY .docker/vhost.conf /etc/apache2/sites-available/000-default.conf
COPY .docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini
RUN a2enmod rewrite ssl headers && a2dissite default-ssl >/dev/null 2>&1 || true

WORKDIR /var/www/html

# Application code (overridden by the bind mount in docker-compose for development)
COPY --chown=www-data:www-data . /var/www/html
RUN composer install --no-interaction --no-progress --prefer-dist \
    && chown -R www-data:www-data /var/www/html

EXPOSE 80 443
