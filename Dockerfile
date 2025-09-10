# Dockerfile for PHP RAG Client Testing
FROM php:8.2-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy composer files
COPY composer.json composer.lock* ./

# Install dependencies
RUN composer install --no-scripts --no-autoloader --prefer-dist

# Copy source code
COPY . .

# Generate autoload
RUN composer dump-autoload --optimize

# Create directories for test artifacts
RUN mkdir -p /app/tests/reports /app/tests/coverage

# Set correct permissions
RUN chmod -R 755 /app

# Default command runs all tests
CMD ["./vendor/bin/phpunit", "--configuration", "phpunit.xml"]