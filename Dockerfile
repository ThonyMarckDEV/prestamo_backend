FROM php:8.2-cli

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    unzip \
    git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd zip pdo pdo_mysql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Set the working directory
WORKDIR /var/www

# Copy essential files for Composer and Laravel
COPY composer.json composer.lock artisan /var/www/
COPY bootstrap /var/www/bootstrap
COPY routes /var/www/routes

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Install PHP dependencies without development environment
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# Copy the rest of the application
COPY . .

# Adjust permissions for Laravel storage and cache directories
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

# Entrypoint que corre migraciones, crea enlace simbólico y arranca el servidor
RUN echo '#!/bin/bash\n\
php artisan config:clear\n\
php artisan migrate --force || true\n\
php artisan storage:link || true\n\
php artisan config:cache\n\
php artisan serve --host=0.0.0.0 --port=8000' > /entrypoint.sh && chmod +x /entrypoint.sh

EXPOSE 8000

# Opcionalmente antes del ENTRYPOINT o CMD
RUN echo "upload_max_filesize=100M\n\
post_max_size=100M\n\
max_file_uploads=20\n\
max_execution_time=300" > /usr/local/etc/php/conf.d/uploads.ini
CMD ["/entrypoint.sh"]
