FROM php:8.2-apache

# Install mysqli
RUN docker-php-ext-install mysqli

# Fix Apache MPM conflict
RUN a2dismod mpm_event && a2enmod mpm_prefork

# Enable rewrite
RUN a2enmod rewrite

# Copy project
COPY . /var/www/html/

# Permissions
RUN chown -R www-data:www-data /var/www/html
