FROM php:8.2-apache

RUN docker-php-ext-install mysqli pdo pdo_mysql \
    && a2enmod ssl rewrite

COPY . /var/www/html/
COPY certs/ca.pem /etc/ssl/certs/ca.pem

RUN chown -R www-data:www-data /var/www/html/

EXPOSE 80
CMD ["apache2-foreground"]
