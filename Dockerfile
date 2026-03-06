FROM php:8.2-apache

# Install mysqli
RUN docker-php-ext-install mysqli

# Disable conflicting MPM modules
RUN a2dismod mpm_event mpm_worker

# Enable correct MPM for PHP
RUN a2enmod mpm_prefork

# Enable rewrite
RUN a2enmod rewrite

# Copy project files
COPY . /var/www/html/

# Permissions
RUN chown -R www-data:www-data /var/www/html
