FROM php:8.3-fpm-alpine

RUN docker-php-ext-install mysqli pdo_mysql

COPY php.ini /usr/local/etc/php/conf.d/custom.ini

WORKDIR /var/www/mafiaratings
