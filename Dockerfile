FROM php:8.2-cli

WORKDIR /app

# Install system tools & GD/Zip (required by Dompdf)
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install mysqli zip gd

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy project files
COPY . /app

# Run composer install to generate clean vendor & Dompdf files
RUN composer install --no-dev --optimize-autoloader

# Start PHP built-in server
CMD php -S 0.0.0.0:$PORT
