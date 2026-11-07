FROM php:8.2-cli

WORKDIR /app

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libzip-dev curl \
    && docker-php-ext-install zip \
    && curl -sS https://getcomposer.org/installer -o /tmp/composer-setup.php \
    && php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer \
    && rm -f /tmp/composer-setup.php \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY tests/lab/composer.json /app/composer.json
RUN composer install --no-interaction --prefer-dist --no-progress --no-scripts

COPY tests/lab /app

CMD ["vendor/bin/phpunit", "--configuration", "phpunit.xml"]
