FROM php:8.2-apache
RUN apt-get update && apt-get install -y libssl-dev pkg-config git unzip && rm -rf /var/lib/apt/lists/*
RUN pecl install mongodb && docker-php-ext-enable mongodb
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN a2enmod rewrite
COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html && chmod -R 755 /var/www/html
EXPOSE 80