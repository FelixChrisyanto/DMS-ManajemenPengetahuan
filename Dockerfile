FROM php:8.2-apache

RUN apt-get update && apt-get install -y libsqlite3-dev \
    && docker-php-ext-install pdo pdo_sqlite

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html \
    && a2enmod rewrite

ENV APACHE_RUN_USER www-data
ENV APACHE_RUN_GROUP www-data

EXPOSE 80

CMD ["apache2-foreground"]