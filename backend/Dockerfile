FROM php:8.4-cli-alpine

# Install system dependencies
RUN apk add --no-cache \
    postgresql-dev \
    curl \
    unzip \
    git \
    libzip-dev \
    python3 \
    bash

# Install PHP extensions
RUN docker-php-ext-install pdo_pgsql

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /app

# Copy all application code
COPY . .

# Install dependencies (ignore platform req since lock file may be from PHP 8.4 host)
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress --ignore-platform-req=php

# Set permissions
RUN chmod +x docker-entrypoint.sh

# Create storage directories
RUN mkdir -p storage/logs storage/framework/{cache,sessions,views} bootstrap/cache && \
    chmod -R 777 storage bootstrap/cache

EXPOSE 8000

ENTRYPOINT ["./docker-entrypoint.sh"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
