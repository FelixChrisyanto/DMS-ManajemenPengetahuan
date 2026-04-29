FROM php:8.2-apache

RUN docker-php-ext-install pdo pdo_sqlite
RUN apt-get update && apt-get install -y libsqlite3-dev

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html

EXPOSE 80