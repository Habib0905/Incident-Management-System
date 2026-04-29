FROM php:8.3-cli-alpine

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
RUN docker-php-ext-install pdo pdo_pgsql mbstring xml ctype json

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy dependency files first (for layer caching)
COPY composer.json composer.lock ./

# Install dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress

# Copy application code
COPY . .

# Set permissions
RUN chmod +x docker-entrypoint.sh

# Create storage directories
RUN mkdir -p storage/logs storage/framework/{cache,sessions,views} bootstrap/cache && \
    chmod -R 777 storage bootstrap/cache

EXPOSE 8000

ENTRYPOINT ["./docker-entrypoint.sh"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
