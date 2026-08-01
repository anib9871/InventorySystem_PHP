FROM php:8.2-cli

# Install required system tools and packages
RUN apt-get update && apt-get install -y \
    unzip \
    git \
    libzip-dev \
    libpng-dev \
    && docker-php-ext-install mysqli zip gd

# Copy composer binary from official image
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy project files
COPY . /app

# Install PHP packages (Dompdf)
RUN composer install --no-dev --optimize-autoloader

# Start server
CMD php -S 0.0.0.0:$PORT
