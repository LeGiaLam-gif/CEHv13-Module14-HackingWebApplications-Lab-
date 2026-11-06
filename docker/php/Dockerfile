FROM php:8.2-apache
RUN apt-get update && apt-get install -y unzip git libzip-dev \
    && docker-php-ext-install pdo pdo_mysql zip
RUN printf "upload_max_filesize=2M\npost_max_size=24M\nmax_file_uploads=20\n" > /usr/local/etc/php/conf.d/uploads.ini
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/sites-available/*.conf /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf
RUN a2enmod rewrite
WORKDIR /var/www/html
EXPOSE 80
