FROM php:8.3-apache

RUN apt-get update && apt-get install -y libonig-dev libpng-dev libcurl4-openssl-dev && docker-php-ext-install pdo_mysql mbstring gd curl && a2enmod rewrite headers && rm -rf /var/lib/apt/lists/*

ENV APACHE_DOCUMENT_ROOT=/var/www/html/backend/public

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g'     /etc/apache2/sites-available/*.conf     /etc/apache2/apache2.conf     /etc/apache2/conf-available/*.conf

WORKDIR /var/www/html
